# Kế hoạch triển khai: chế độ "tìm từ khóa lấy mã"

> **Dành cho agent thực thi:** dùng superpowers:subagent-driven-development. Các bước dùng checkbox `- [ ]`. Tài liệu viết tiếng Việt để chủ dự án đọc; mọi thứ trong repo (code, comment, tên bảng, chữ giao diện, tên test) là tiếng Anh.

**Mục tiêu:** trang `/go/{alias}` có hai chế độ (từ khóa và chờ giây) chọn tự động; widget cho nhà quảng cáo; chiến dịch trong admin; giao diện thân thiện, quen thuộc như link4m.

**Kiến trúc:** `visit_sessions.mode` quyết định nhánh xử lý. Chế độ từ khóa thay các bước chờ bằng một bước nhập mã; mã do widget (chạy trên site nhà quảng cáo) xin từ server qua `/widget/*`, đồng hồ đo ở server, gắn IP và user-agent. Nhánh chờ giây giữ nguyên.

**Công nghệ:** Laravel 12, Pest, jQuery cho trang của chúng ta, JavaScript thuần cho widget, Cloudflare Turnstile (khóa mẫu đã đặt trong `.env`).

**Thiết kế:** `docs/superpowers/specs/2026-11-13-keyword-code-flow-design.md` (mục 12 chốt các quyết định). Dựa trên code đã có: `VisitFlow`, `VisitController`, `ViewValidator`, `ViewRecorder`, `StepTokenService`, `visit_sessions`, `views`, `Settings`, `fraud_flags`, `AdSlot`. Ảnh chuẩn giao diện (chỉ local): `docs/references/link4m-go/layer_1_*.png` (xem `README.md` trong thư mục đó).

## Ràng buộc chung

- Mọi thứ trong repo bằng tiếng Anh. Theo `mst-laravel-conventions` và `git-workflow` (commit nhỏ `type(scope): subject`, không `Co-Authored-By`). Không thêm dependency. Mọi JS của trang chúng ta dùng jQuery; `public/js/widget.js` là JS thuần, dưới 8 KB, không phụ thuộc gì.
- Mặc định (trong `config/linkcash.php` và bảng `settings`): `visit_mode = auto`, `code_session_seconds = 600`, `tutorial_video_url = ''`. Mặc định của chiến dịch: `stage1_seconds = 60`, `stage2_seconds = 15`.
- Mã 6 ký tự từ bảng `abcdefghjkmnpqrstuvwxyz23456789` (bỏ `0 o 1 l i`), duy nhất trong phạm vi một chiến dịch, dùng `random_int`.
- Chỉ tin đồng hồ server. Quá 5 lần nhập sai thì khóa phiên; thêm giới hạn 10 lần sai mỗi giờ cho mỗi IP và chiến dịch.
- Không dùng cookie của site nhà quảng cáo; widget lưu trạng thái trong `localStorage`.
- Dữ liệu demo và seed dùng site giả (`demo.localhost`-style), không dùng site cá cược.
- Chủ dự án yêu cầu: viết code kỹ, viết test theo plan nhưng không chạy toàn bộ test giữa chừng; chỉ chạy `pint`, `php -l` và tối đa một lần test tập trung mỗi nhóm. Chạy toàn bộ test một lần ở cuối.

## Giao diện (interface) chung giữa các nhóm

- **Enum** `App\Enums\VisitMode`: `Wait = 'wait'`, `Keyword = 'keyword'`. `InvalidReason` thêm `MissingCode`, `CodeInvalid`. `VisitStatus` giữ nguyên.
- **Model `Campaign`** (bảng `campaigns`): `id`, `name`, `keyword`, `domain` (host chính, ví dụ `example-advertiser.com`), `display_domain` (đã che, ví dụ `example-adv***.com`), `widget_key` (chuỗi 24 ký tự, duy nhất), `is_active` (bool), `weight` (unsigned int, mặc định 1), `daily_limit` (unsigned int, 0 là không giới hạn), `starts_at`, `ends_at` (nullable), `stage1_seconds` (60), `stage2_seconds` (15), `image_google`, `image_button`, `image_position` (đường dẫn trên disk `public`, nullable), `notes` (text, nullable). Quan hệ `attempts(): HasMany`. Scope `scopeAvailable(Builder $q): Builder` (đang bật, trong khoảng ngày) và `Campaign::issuedToday(): int` (số `code_attempts` có `issued_at` trong ngày UTC).
- **Model `CodeAttempt`** (bảng `code_attempts`): `id` (ulid), `campaign_id`, `ip`, `ua_hash` (char 64), `fingerprint` (nullable), `referrer_host` (nullable), `started_at`, `progressed_at` (nullable), `page_load_ids` (json), `code` (nullable; duy nhất theo `campaign_id`), `issued_at`, `expires_at`, `used_at` (nullable), `visit_session_id` (nullable ulid, khóa ngoại). `visit_sessions` thêm `mode` (`wait|keyword`, mặc định `wait`), `campaign_id` (nullable), `code_failures` (unsigned tinyint, mặc định 0).
- **Dịch vụ** `App\Services\Visits\CampaignSelector::pick(): ?Campaign` (theo `Settings` `visit_mode`: `wait` trả null; `auto` hoặc `keyword` chọn ngẫu nhiên theo `weight` trong các chiến dịch `available()` chưa hết `daily_limit`; không có thì null). `App\Services\Visits\CodeIssuer` (widget) và `App\Services\Visits\CodeRedeemer::redeem(VisitSession $session, string $code, VisitorContext $ctx): CodeResult` với enum `CodeResult {Accepted, Invalid, Expired, WrongDevice, Locked}`.
- **Widget API** (JSON, CORS chỉ cho `Origin` thuộc `campaign.domain` hoặc subdomain; chiến dịch phải `available()`):
  - `POST /widget/start` body `{campaign, referrer, page_load_id, fingerprint, attempt_token?}` trả `{attempt_token, stage: 1|2|3, stage1_seconds, stage2_seconds, remaining_seconds, code?}`; lỗi `{error: 'campaign_unavailable'|'bad_origin'|'bad_token'}` mã 4xx.
  - `POST /widget/progress` body `{attempt_token, page_load_id}` trả `{stage: 2, remaining_seconds}`; lỗi `{error: 'too_early', remaining_seconds}` mã 422.
  - `POST /widget/code` body `{attempt_token}` trả `{code}`; lỗi `{error: 'too_early', remaining_seconds}` mã 422. Gọi lại trả đúng mã cũ.
  - `attempt_token` là chuỗi ký HMAC bằng khóa app chứa id lần thử.
- **Trang vượt link, chế độ từ khóa** (mở rộng các endpoint đã có): `POST /links/get-link-info` trả thêm `mode: 'keyword'` và `{keyword, display_domain, images: {google, button, position}, session_seconds_left, tutorial_video_url, captcha_required: true}`; chế độ chờ giây giữ JSON cũ cộng `mode: 'wait'`. `POST /links/check-captcha` nhận thêm `code`; thành công trả `{destination}`, lỗi mã sai trả 422 `{error: 'invalid_code'|'expired_code'|'wrong_device'|'locked', failures_left}`. `POST /links/report` nhận thêm `captcha_response` và `choice` thuộc `code_not_shown|page_not_shown|other` (giữ `reason`/`detail` cũ làm tương thích).

## Review Focus (kiểm tra ở nhóm có ngoặc)

1. Hai yêu cầu nhập cùng một mã cùng lúc chỉ một cái thắng, mã bị tiêu thụ đúng một lần [K4].
2. Mã của chiến dịch A không dùng được cho phiên của chiến dịch B; mã của thiết bị khác (user-agent khác) bị từ chối nhưng không bị mất [K4].
3. `/widget/*` từ `Origin` lạ hoặc chiến dịch đã tắt trả lỗi, không phát mã [K3].
4. Gọi `/widget/code` sớm hơn thời gian chờ trả `too_early` kèm số giây còn lại, dù client giả đồng hồ [K3].
5. Chiến dịch hết hạn mức hoặc bị tắt giữa chừng: khách chưa có mã tự chuyển sang chế độ chờ giây, không bị kẹt [K2].

---

## Nhóm K1: Nền dữ liệu (làm trước, một mình)

**File:** migration `campaigns`, `code_attempts`, `add_keyword_fields_to_visit_sessions`; model `Campaign`, `CodeAttempt`; sửa `VisitSession` (casts, quan hệ `campaign()`, `codeAttempts()`); enum `VisitMode`; sửa `InvalidReason`; factory `CampaignFactory`, `CodeAttemptFactory`; `config/linkcash.php` + `SettingSeeder` thêm `visit_mode`, `code_session_seconds`, `tutorial_video_url`; `CampaignSeeder` (chỉ khi `app()->isLocal()`, một chiến dịch demo: keyword `how to repot a houseplant`, domain `localhost`, `widget_key` cố định `demo-campaign-key-0001`, tắt `daily_limit`); thêm vào `DatabaseSeeder`. Test: `tests/Feature/CampaignSchemaTest.php`.

- [ ] **K1.1** Viết migration và model theo giao diện chung (khóa ngoại, unique `(campaign_id, code)`, chỉ mục `(campaign_id, issued_at)`). Dùng `php artisan make:model -mf --no-interaction`.
- [ ] **K1.2** `Campaign::scopeAvailable`, `Campaign::issuedToday()`, factory (state `inactive()`, `exhausted()`), seeder demo.
- [ ] **K1.3** Test: factory tạo được; unique `(campaign_id, code)`; `available()` loại chiến dịch tắt, chưa tới `starts_at`, quá `ends_at`; `issuedToday()` chỉ đếm hôm nay.
- [ ] **K1.4** `vendor/bin/pint --dirty`, commit `feat(campaigns): add campaign and code attempt schema`.

## Nhóm K2+K4: Nhánh từ khóa phía trang vượt link (backend)

**File:** `app/Services/Visits/{CampaignSelector,CodeRedeemer,CodeResult}.php`; sửa `app/Services/Views/VisitFlow.php` (`start()` chọn chế độ, `stepInfo()` trả dữ liệu từ khóa, chuyển phiên về `wait` khi chiến dịch không còn khả dụng), `ViewValidator` (nhánh `keyword`: bỏ `MissingSteps` và `DwellTooShort`, thêm `MissingCode`, `CodeInvalid`), `ViewRecorder` (không đổi logic ghi, chỉ nhận lý do mới), `VisitController` (`checkCaptcha` nhận `code`, `report` kèm captcha), `app/Http/Requests/Visit/*` (thêm `code`, `captcha_response`, `choice`), `app/Console/Commands/PurgeOldViews.php` (xóa `code_attempts` đã hết hạn hơn 1 ngày). Test: `tests/Feature/KeywordVisitFlowTest.php`, `tests/Unit/CampaignSelectorTest.php`, `tests/Feature/CodeRedeemerTest.php`.

- [ ] **K2.1** `CampaignSelector::pick()` theo giao diện chung (chọn có trọng số bằng `random_int`).
- [ ] **K2.2** `VisitFlow::start()` ghi `mode` và `campaign_id` lúc tạo phiên; `stepInfo()` ở chế độ từ khóa trả dữ liệu theo giao diện chung và `session_seconds_left` tính từ `created_at` và `code_session_seconds`; nếu chiến dịch không còn `available()` hoặc đã vượt hạn mức **và** thiết bị chưa có mã thì đặt `mode = wait` và trả nhánh chờ giây.
- [ ] **K4.1** `CodeRedeemer::redeem()` trong một `DB::transaction` có `lockForUpdate` trên `code_attempts`: tìm theo `code`, `campaign_id` của phiên, `used_at` null, `expires_at` chưa qua; `ua_hash` khác trả `WrongDevice` (cộng lỗi, không tiêu thụ mã); đúng thì ghi `used_at` và `visit_session_id`; so `ip` với `visit_sessions.ip` (khác thì đánh dấu `ip_changed` trên phiên như logic hiện có). Quá 5 `code_failures` hoặc quá 10 lần sai mỗi giờ theo `(ip, campaign)` (dùng `RateLimiter`) thì `Locked`.
- [ ] **K4.2** `VisitController::checkCaptcha` ở phiên `keyword`: kiểm tra captcha trước (captcha sai không đụng tới mã), rồi `redeem`, rồi chuỗi kiểm tra hiện có và `ViewRecorder::record`; lỗi mã trả 422 theo giao diện chung; trả luôn `{destination}` ở mã hợp lệ kể cả khi view bị đánh dấu không hợp lệ.
- [ ] **K4.3** `POST /links/report`: thêm kiểm tra captcha (cùng `CaptchaVerifier`), lưu `choice` và `campaign_id` vào `fraud_flags.details`.
- [ ] **K4.4** Test theo Review Focus 1, 2, 5 và: chế độ `wait` không đổi hành vi; `visit_mode = keyword` mà không có chiến dịch vẫn rơi về `wait`; 5 lần sai thì khóa; mã hợp lệ nhưng captcha sai thì mã chưa bị tiêu thụ.
- [ ] **K4.5** `pint`, commit nhỏ: `feat(visit): pick visit mode and campaign when a session starts`, `feat(visit): redeem keyword codes at the last step`, `feat(visit): require captcha when reporting an error`.

## Nhóm K3: Widget và API widget

**File:** `app/Http/Controllers/WidgetController.php`, `app/Services/Visits/CodeIssuer.php`, `app/Http/Middleware/WidgetCors.php`, `routes/widget.php` (nạp từ `bootstrap/app.php`, không có CSRF, throttle `60,1` theo IP), `public/js/widget.js`. Test: `tests/Feature/WidgetApiTest.php`.

- [ ] **K3.1** `WidgetCors`: đọc `Origin`, so với `campaign.domain` (khớp host hoặc subdomain; ở môi trường local cho phép `localhost` và `127.0.0.1` khi `campaign.domain` là `localhost`); trả `Access-Control-Allow-Origin` đúng origin, xử lý `OPTIONS`.
- [ ] **K3.2** `CodeIssuer`: `start()` tạo hoặc tiếp tục `CodeAttempt` (kiểm tra `available()` và hạn mức ngày), `progress()` (đủ `stage1_seconds` và `page_load_id` khác lần `start`), `issue()` (đủ `stage2_seconds` kể từ `progressed_at`, sinh mã theo ràng buộc chung, `expires_at = now + code_session_seconds`, idempotent).
- [ ] **K3.3** `WidgetController` ba action theo giao diện chung; `attempt_token` ký HMAC.
- [ ] **K3.4** `public/js/widget.js` (JS thuần): chèn khối ở cuối trang, nút "GET CODE" → "Please wait 60s (1/2)" và dòng "Please click any link and scroll back to this position to get the CODE" → ở trang tiếp theo "Get code after 15s (2/2)" → hiện "Promo code: xxxxxx" kèm nút copy. Trạng thái trong `localStorage`; nếu `localStorage` bị tắt hiện "Please enable site storage to get the code". Hiện đúng thông báo khi `campaign_unavailable`. Giao diện widget gọn, chữ tiếng Anh, đủ rõ trên điện thoại.
- [ ] **K3.5** Test theo Review Focus 3, 4 và: gọi lại `/widget/code` trả cùng mã; `Origin` hợp lệ có header CORS; hết hạn mức trả `campaign_unavailable`.
- [ ] **K3.6** `pint`, commit: `feat(widget): add widget api and code issuer`, `feat(widget): add embeddable widget script`.

## Nhóm K5: Giao diện trang `/go/{alias}` (hai chế độ)

**File:** sửa `resources/views/visit/go.blade.php` (tách partial `visit/_keyword.blade.php`, `visit/_wait.blade.php`, `visit/_report-modal.blade.php`), `resources/views/layouts/visit.blade.php`, `resources/css/visit.css`, `resources/js/visit.js`. Test: `tests/Feature/VisitPageTest.php` (cấu trúc HTML, không có tiếng Việt).

- [ ] **K5.1** Bố cục theo `shots` (đọc `docs/references/link4m-go/layer_1_1.png` đến `_5.png`): thanh trên xanh dương gradient với logo LinkCash và menu Home; tiêu đề "PLEASE ENTER THE CODE TO CONTINUE TO THE DESTINATION"; ô "Enter code"; captcha Turnstile (dùng các thuộc tính `data-captcha-*` đã có); "Session time left: N" đếm ngược bằng jQuery, đổi sang đỏ khi dưới 60 giây; nút "Click here to continue".
- [ ] **K5.2** Khối hướng dẫn "HOW TO GET THE CODE - KEYWORD SEARCH": hộp ba lưu ý (Chrome, không bấm quảng cáo, không ẩn danh), 6 bước có số tròn, từ khóa chữ đỏ, domain đã che, ba ảnh theo chiến dịch (ảnh mặc định bằng SVG đơn giản nếu admin chưa tải), "Can't get the code?" kèm nút "Report an error" mở modal (3 lựa chọn, captcha, Close, Send), khối video (ẩn khi `tutorial_video_url` trống), khung "READY TO EARN MONEY?", chân trang.
- [ ] **K5.3** Nguyên tắc giao diện: thẻ bo góc đổ bóng nhẹ, chữ lớn, nút và ô nhập cao tối thiểu 48 px, responsive từ 360 px, nút tiếp tục xám đến khi có mã và qua captcha rồi chuyển xanh lá kèm dòng trạng thái ("Enter the code", "Verify you are human", "Ready"). Không pop-up. Các vị trí quảng cáo `Top`/`Bottom` (`<x-visit.ad-slot>`) vẫn hiện ở cả hai chế độ.
- [ ] **K5.4** Chế độ chờ giây: cùng bố cục xanh dương, nút "Please wait 60s (1/N)" theo `dwell_seconds`, hết giờ thành "Click here to continue" (logic bước giữ nguyên).
- [ ] **K5.5** `visit.js`: một lần gọi `get-link-info` quyết định chế độ theo trường `mode`; nhập mã → `check-captcha` với `code`; hiện lỗi `invalid_code|expired_code|wrong_device|locked` bằng tiếng Anh dễ hiểu; modal báo lỗi gọi `/links/report`.
- [ ] **K5.6** `npm run build`, kiểm tra bằng `playwright-cli` ở 1280 và 360 px; commit `feat(visit): redesign the visit page for keyword and wait modes`.

## Nhóm K6: Admin Campaigns và cài đặt

**File:** `app/Http/Controllers/Admin/CampaignController.php`, `app/Http/Requests/Admin/StoreCampaignRequest.php`, `routes/admin-campaigns.php` (nạp từ `routes/admin.php`), `resources/views/admin/campaigns/{index,form}.blade.php`, sửa trang Settings admin (thêm `visit_mode`, `code_session_seconds`, `tutorial_video_url`), sidebar admin (mục Campaigns). Test: `tests/Feature/Admin/CampaignAdminTest.php`.

- [ ] **K6.1** CRUD chiến dịch theo giao diện chung: sinh `widget_key` tự động, gợi ý `display_domain` che 3 ký tự giữa phần tên miền, tải ba ảnh (jpg/png/webp, tối đa 1 MB mỗi ảnh) vào disk `public`, bật tắt nhanh, bảng danh sách có cột mã đã cấp hôm nay, view hợp lệ, số lần khách báo lỗi.
- [ ] **K6.2** Hiển thị đoạn mã nhúng `<script async src="…/js/widget.js" data-campaign="WIDGET_KEY"></script>` để admin copy.
- [ ] **K6.3** Settings: `visit_mode` thuộc `auto|keyword|wait`, `code_session_seconds` từ 120 đến 3600, `tutorial_video_url` (https, chỉ nhận YouTube/Vimeo hoặc để trống).
- [ ] **K6.4** Test: member 403; tạo chiến dịch sinh `widget_key` duy nhất; ảnh sai định dạng bị từ chối; đổi `visit_mode` có hiệu lực với `CampaignSelector`.
- [ ] **K6.5** `pint`, commit: `feat(admin): add campaign management`, `feat(admin): add visit mode and code settings`.

## Nhóm K7: Trang "nhà quảng cáo giả" để thử trên local

**File:** `routes/web.php` (chỉ đăng ký khi `app()->isLocal()`, đặt **trước** khối vượt link), `resources/views/demo/advertiser.blade.php`, `resources/views/demo/advertiser-page.blade.php`. Test: `tests/Feature/DemoAdvertiserTest.php`.

- [ ] **K7.1** `/demo/advertiser` là bài viết dài giả (nhà vườn, không phải cá cược) với nhiều link nội bộ tới `/demo/advertiser/page-2` và `page-3`; nhúng widget với chiến dịch demo (`data-campaign="demo-campaign-key-0001"`). Ở môi trường không phải local các route này trả 404.
- [ ] **K7.2** Test: local trả 200 và có thẻ script widget; môi trường `production` trả 404.
- [ ] **K7.3** commit `feat(demo): add a local fake advertiser site for the widget`.

## Điểm chung khi gộp và kiểm tra cuối

1. Gộp các nhánh nhóm vào nhánh `feat/keyword-code-flow` (xung đột có thể có ở `routes/web.php`, `routes/admin.php`, `vite.config.js`, sidebar admin).
2. Chạy `php artisan migrate:fresh --seed` trên MySQL và `php artisan test --compact` một lần.
3. Thử tay bằng trình duyệt: tạo link → mở link (chế độ từ khóa) → `/demo/advertiser` lấy mã (chờ 60 giây rồi 15 giây, có thể giảm bằng cách sửa chiến dịch demo xuống vài giây khi thử) → dán mã → đến trang đích; sau đó tắt chiến dịch và mở lại link để thấy chế độ chờ giây.
4. Review cả nhánh bằng một agent mạnh nhất, tập trung vào: tiêu thụ mã, CORS, đồng hồ server, khóa phiên.

## Tự rà soát với thiết kế

- Mục 6.1 chọn chế độ: K2.1, K2.2. Mục 6.2 trang từ khóa: K5. Mục 6.3 widget: K3. Mục 6.4 nhập mã: K4.1, K4.2. Mục 6.5 báo lỗi: K4.3, K5.2. Mục 6.6 chờ giây: K5.4. Mục 6.7 admin: K6. Mục 6.8 thử local: K7 và K1.2. Mục 7 dữ liệu: K1. Mục 12 các quyết định: K4.1 (2), K2.2 (3), K4.2 (1).
- Tên đã kiểm tra nhất quán: `CampaignSelector::pick`, `CodeIssuer::start|progress|issue`, `CodeRedeemer::redeem`, `CodeResult`, `VisitMode`, `Campaign::scopeAvailable`, `Campaign::issuedToday`, các route `/widget/{start,progress,code}`.
- Ngoài phạm vi: bảng điều khiển nhà quảng cáo, thanh toán nhà quảng cáo, chứng minh nguồn Google.
