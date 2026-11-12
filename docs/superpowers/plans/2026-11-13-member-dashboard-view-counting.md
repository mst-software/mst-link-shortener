# Kế hoạch triển khai: Member dashboard, đếm view và quảng cáo

> **Dành cho agent thực thi:** BẮT BUỘC dùng superpowers:subagent-driven-development (khuyên dùng) hoặc superpowers:executing-plans để làm từng task. Các bước dùng checkbox `- [ ]`.
>
> **Ngôn ngữ:** plan này viết tiếng Việt để bạn đọc. Mọi thứ trong repo vẫn là tiếng Anh: code, comment, tên test, và toàn bộ chữ trên giao diện. Tên trong plan (route, class, cột) là tên thật sẽ dùng trong code.

**Mục tiêu:** Làm tài khoản thật, khu member **giống hệt link4m**, luồng đếm view phía server (cách A), thu nhập có sổ cái, vị trí quảng cáo và admin tối thiểu cho LinkCash.

**Kiến trúc:** Một ứng dụng Laravel có 4 layout (công khai, auth, trang vượt link, member/admin). Trang vượt link là một phiên lưu ở server (`visit_sessions`) với token ký dùng một lần cho mỗi bước; view được ghi ở bước cuối qua chuỗi kiểm tra. Tiền là số nguyên micro-USD, chỉ đổi qua `WalletService` (khóa dòng + ghi `Transaction` trong cùng giao dịch). Cron `schedule:run` gom thống kê, giải phóng tiền chờ và dọn view cũ. Không Redis, không worker chạy nền.

**Công nghệ:** PHP 8.4, Laravel 12, Pest 4, Pint, Vite, Tailwind 4 (chỉ trang công khai), jQuery (toàn bộ JavaScript), AdminLTE 2 + Bootstrap 3 (chỉ khu member/admin, chép file vào repo, không thêm package), SQLite khi test, MySQL khi chạy thật, Cloudflare Turnstile.

**Tài liệu thiết kế:** `docs/superpowers/specs/2026-11-13-member-dashboard-view-counting-design.md`. Khi thiết kế và plan này khác nhau (URL, alias, giao diện) thì **plan này đúng**, vì plan đã đối chiếu với web thật.

**Ảnh và HTML chuẩn để so giao diện:** `docs/references/link4m/` (đã nằm trong `.gitignore`, chỉ có trên máy local): `html/` là HTML gốc đã xóa token, `css/` là CSS gốc, `shots/*.png` là ảnh từng trang ở 1280 px. Nếu thư mục này mất, hỏi chủ dự án chứ không tự đoán giao diện.

---

## Đối chiếu với web thật (link4m, link1s)

Bảng này trả lời yêu cầu "đừng làm khác web ngoài kia". Cột "Nguồn" là nơi đã kiểm chứng.

| Mục | Làm theo | Nguồn |
|---|---|---|
| Link rút gọn | `https://domain/{alias}` | link4m: `link4m.org/0r8qv`, `link4m.net/n5XVe9YY` |
| Alias tự sinh | Base62 (`A-Z`, `a-z`, `0-9`), phân biệt hoa thường, **tối thiểu 6 ký tự**; xem mục "Thuật toán tạo alias" bên dưới | link4m: 7 link mẫu, 6 trong 7 có cả chữ hoa lẫn chữ thường; độ dài 5 (1 link cũ), 6 (3 link), 8 (2 link) |
| Luồng khi khách mở link | `GET /{alias}` trả **302** sang `/go/{alias}` (kèm cookie phiên, `Cache-Control: no-store`); `/go/{alias}` là **một trang duy nhất** chứa mọi bước (đếm ngược, captcha, quảng cáo, nút lấy link), các bước chạy bằng AJAX tới `POST /links/get-link-info`, `POST /links/check-captcha`, `POST /links/report`; tiêu đề trang `Untitled #{alias}`; có hộp "Report an error" (Code not shown / Page not shown / Other); có khung mời đăng ký và chân trang | link4m: kiểm tra header thật (`302 Location: /go/0r8qv`, cookie `PHPSESSID` 1 năm), HTML của `/go/0r8qv` và `view.js` |
| Trang thống kê của một link | `GET /{alias}/info`, **chỉ chủ link xem** (khách vào bị chuyển tới đăng nhập). Đây là trang mở bằng nút "Stats" ở danh sách link | link4m: nút "Stats" trỏ `/{alias}/info`; khách ngoài vào `link4m.org/0r8qv/info` nhận 404 nên không công khai |
| Tạo link | form trong sidebar, `POST /links/shorten` (url, alias, ad_type) | link4m: `<form id="shorten" action="/links/shorten">` |
| Lọc link | `POST /member/links` với `filter[alias]`, nút Filter và Reset | link4m trang Links |
| Ẩn link | `POST /member/links/hide` (có trang `/member/links/hidden`) | link4m trang Links |
| Đăng nhập/đăng ký/quên mật khẩu | `/auth/signin`, `/auth/signup`, `/auth/forgot-password`, `/auth/signout` | link1s và link4m |
| Điều khoản, chính sách | `/pages/terms`, `/pages/privacy` | link1s: footer trỏ `/pages/terms`, `/pages/privacy` |
| Trang công khai khác | `/payout-rates`, `/blog` | link1s |
| Link giới thiệu | `/ref/{code}`, mã 7 ký tự | link4m: `link4m.com/ref/6tpCb0n` |
| Menu member | `/member/dashboard`, `/member/notify`, `/member/statistic`, `/member/popular-link`, `/member/links`, `/member/links/hidden`, `/member/withdraws`, `/member/tools/{quick,mass-shrinker,full,api}`, `/member/referrals`, `/member/user/{profile,change-password,change-email}`, `/member/form/support` | link4m: menu và URL thật |
| Dữ liệu biểu đồ | `POST /member/dashboard/get-data` nhận `date` dạng `dd/mm/yyyy`; trả `pay_view_data`, `publisher_earn_data`, `cpm_data`, `ref_data`, `total_view`, `total_publisher_earn`, `total_ref_earn`, `total_income`, `average_cpm` | link4m: yêu cầu thật đã bắt được |
| Chọn tháng | `?month=11/2026` | link4m: `/member/statistic/index?month=11/2026` |
| API | `GET /api?api=TOKEN&url=URL&alias=ALIAS&format=json\|text` và bản của link4m `GET /api-shorten/v2?api=TOKEN&url=URL`, cùng một xử lý. JSON thành công `{"status":"success","message":"","shortenedUrl":"..."}`, lỗi `{"status":"error","message":"..."}` | link4m trang API (có `status`, `shortenedUrl`, đọc `message` khi lỗi) |
| Quick link | `GET /st?api=TOKEN&url=URL` | link4m trang Quick Link |
| Full page script | `<script src="//domain/js/full-script.js">` với biến `linkcash_url`, `linkcash_api_token`, `linkcash_advert`, `linkcash_domains` | link4m trang Full Page Script (đổi tên biến theo thương hiệu) |
| Mass Shrinker | tối đa 20 URL, mỗi dòng một URL | link4m trang Mass Shrinker |
| Phương thức nhận tiền | PayPal, Bank Transfer, Momo, USDT; có bảng mức rút tối thiểu theo phương thức | link4m trang Profile |

**Đã chốt với chủ dự án (13/11/2026):**
1. Alias tự sinh: chữ hoa + chữ thường + số, tối thiểu 6 ký tự (thuật toán ở mục bên dưới).
2. `/{alias}/info` là trang thống kê riêng của từng link, chỉ chủ link xem (giải thích ở mục Task 9).
3. Ô `ad_type` trong form rút gọn là chọn mức (Level 1/2/3, N bước). **Thêm một tính năng so với link4m:** member đặt mức mặc định trong Settings, dùng khi tạo link bằng form, API, Quick Link và Mass Shrinker mà không chỉ định mức.
4. Hai banner Telegram ở trang Overview là quảng cáo riêng của link4m: **không chép**. Mọi thứ khác của Overview giữ nguyên.

**Còn một điểm chưa kiểm chứng:** alias do member tự đặt cho phép những ký tự nào. Tạm dùng chữ, số, `-`, `_`, dài 3 đến 32. Muốn chắc phải tạo thử một link trên link4m (có tác dụng phụ trên tài khoản bạn) nên mình chưa làm.

## Thuật toán tạo alias

**Chọn:** chuỗi ngẫu nhiên Base62 từ bộ sinh số an toàn mật mã, kiểm tra trùng bằng unique index, trùng thì thử lại và tăng độ dài.

```
alphabet = A-Z a-z 0-9                  (62 ký tự, giống link4m: có cả chữ hoa và chữ thường)
chiều dài mặc định = 6                  (62^6 = 56.800.235.584 khả năng)
mỗi ký tự = random_int(0, 61)           (không lệch phân phối, khác với rand() hoặc %)
lưu vào cột có unique index, so sánh nhị phân (phân biệt hoa thường)
trùng (lỗi unique) -> thử lại tối đa 5 lần cùng độ dài, vẫn trùng -> tăng 1 ký tự (tối đa 12)
```

**Vì sao không dùng cách khác** (đã cân nhắc):
- *Đổi số thứ tự (id tăng dần) sang Base62 hoặc thư viện Sqids/Hashids:* không bao giờ trùng và không cần thử lại, nhưng alias đoán được, đối thủ có thể quét toàn bộ link của hệ thống. Link4m có alias trông ngẫu nhiên (`JcxTDJ`, `n5XVe9YY`), nên mình theo hướng ngẫu nhiên.
- *Băm (MD5/SHA) của URL rồi cắt:* hai người rút gọn cùng URL sẽ ra cùng alias, làm hỏng thống kê và tiền theo từng chủ link.
- *Cần bộ đếm tập trung hoặc Redis:* không chạy được trên hosting chia sẻ.

**Xác suất trùng:** với N link đã có, một alias mới trùng với xác suất khoảng N / 62^6. Ví dụ 10 triệu link: khoảng 0,018% mỗi lần tạo, tức hầu như không bao giờ phải thử lại. Khi hệ thống lớn dần, việc tự tăng độ dài giải thích vì sao link4m có cả alias 6 và 8 ký tự (đây là suy đoán của mình, không phải điều đã xác nhận).

**Lưu ý kỹ thuật quan trọng:** MySQL mặc định so sánh **không phân biệt hoa thường** (`utf8mb4_unicode_ci`), nên `Abc` và `abc` sẽ bị coi là trùng và link của người khác có thể bị lẫn. Cột `links.alias` phải dùng collation nhị phân `utf8mb4_bin` trên MySQL (SQLite đã phân biệt sẵn). Test chạy bằng SQLite không bắt được lỗi này, nên migration có kiểm tra driver và plan có một bước kiểm tra tay trên MySQL (Task 8, bước 4).

## Ràng buộc chung

- Mọi thứ trong repo là tiếng Anh: code, comment, PHPDoc, tài liệu, tên test, chữ trên giao diện, thông báo lỗi. Không chép chữ tiếng Việt, logo hay ảnh quảng cáo của link4m: chỉ giữ **bố cục, màu, kích thước, cấu trúc HTML/CSS**.
- Theo `mst-laravel-conventions`: FormRequest cho validation, Eloquent và `Model::query()` (không `DB::` ngoài `DB::transaction`), `casts()`, relationship có kiểu trả về, `config()` thay `env()`, route có tên, `php artisan make:... --no-interaction`, model mới có factory và seeder, kiểu trả về rõ ràng, constructor promotion, luôn có `{}`.
- Theo `git-workflow`: mỗi giai đoạn một branch từ `main` mới nhất, commit nhỏ `type(scope): subject` (chữ thường, một dòng, tối đa 100 ký tự, không `Co-Authored-By`), `git add` từng file, chạy `vendor/bin/pint --dirty` và test liên quan trước khi commit code. Chỉ merge khi chủ dự án nói.
- Mỗi thay đổi có test Pest. Chạy test nhỏ nhất: `php artisan test --compact --filter=...`.
- Không thêm dependency hay thư mục gốc mới khi chưa được duyệt. JavaScript chỉ dùng jQuery (`resources/js/jquery.js` đã gán `window.$`). Biểu đồ vẽ bằng canvas tự viết. AdminLTE/Bootstrap/Font Awesome được **chép file** vào `resources/vendor/` (giữ nguyên giấy phép MIT/SIL ở đầu file), không cài package.
- Chạy được trên hosting chia sẻ: cache, queue, session dùng driver database (đã là mặc định); không Redis; không worker chạy nền.
- Cột tiền là `unsignedBigInteger` micro-USD (1 USD = 1.000.000). Một view kiếm `rate_per_1000_micro / 1000`. Giá tính theo USD cho 1.000 view. Hiển thị tiền như link4m: `$ 0.050` (3 số thập phân, dấu chấm vì UI tiếng Anh).
- Mặc định lưu trong bảng `settings`, admin sửa được: dwell 10 giây, cửa sổ unique 24 giờ, chờ duyệt 3 ngày, rút tối thiểu 5 USD, hoa hồng giới thiệu 12%, phiên vượt link 30 phút, giữ view thô 60 ngày, độ dài alias tự sinh 6.
- Mức N có đúng N bước (1 đến 3). Turnstile chỉ ở bước cuối. Bảng giá theo cặp quốc gia và mức.
- Alias phân biệt hoa thường. Alias bị giữ chỗ (trùng tiền tố route): `auth`, `member`, `admin`, `api`, `api-shorten`, `links`, `st`, `ref`, `pages`, `go`, `js`, `css`, `assets`, `build`, `images`, `storage`, `blog`, `payout-rates`, `favicon.ico`, `robots.txt`, `sitemap.xml`.
- Layout member/admin tách riêng layout công khai, auth và trang vượt link.
- Ảnh dùng `Vite::asset('resources/images/...')`; mọi file entry Vite mới phải thêm vào `vite.config.js`.

## Điểm dễ hỏng cần kiểm tra thêm (Review Focus)

Mỗi mục có test ở task trong ngoặc.

1. Hai request `check-captcha` cùng lúc cho một phiên chỉ được cộng đúng một view [Task 15].
2. URL đích có scheme lạ (`javascript:`, `data:`) hoặc trỏ lại chính LinkCash phải bị từ chối khi tạo link [Task 8].
3. Khách tắt cookie hoặc tắt JavaScript không bao giờ nhận được token bước; view lưu là không hợp lệ nhưng khách vẫn được chuyển tới đích [Task 14, Task 15].
4. Yêu cầu rút tiền lớn hơn số dư, hoặc hai yêu cầu gửi cùng lúc, không được làm số dư âm [Task 24].
5. `date` hoặc `month` sai định dạng, ở tương lai hoặc không tồn tại phải trả 422 JSON, không bao giờ 500 [Task 21].

---

## Giai đoạn 1: Tài khoản và khung member (branch `feat/member-accounts`)

### Task 1: Helper tiền và dịch vụ Settings

**File:** tạo `app/Support/Money.php`, `app/Models/Setting.php`, `app/Services/Settings.php`, `config/linkcash.php`, migration `settings`, factory, seeder. Test: `tests/Unit/MoneyTest.php`, `tests/Feature/SettingsServiceTest.php`.

**Giao diện (interface):**
- Cung cấp: `Money::toMicro(string|int|float $usd): int`, `Money::format(int $micro, int $decimals = 3): string` (trả `"$0.050"`), `Settings::get(string $key, mixed $default = null): mixed`, `Settings::set(string $key, mixed $value): void`, `Settings::int(string $key): int`. `config('linkcash.defaults')` giữ các mặc định ở mục Ràng buộc chung với khóa `dwell_seconds`, `unique_window_hours`, `review_days`, `min_withdraw_micro`, `referral_percent`, `session_minutes`, `view_purge_days`, `alias_length`.

- [ ] **Bước 1: Viết test fail:** `toMicro('0.05') === 50000`, `format(50000) === '$0.050'`, `toMicro('4.5') === 4500000`; `Settings` trả mặc định khi chưa có dòng, `set` rồi `get` ra giá trị mới, `set` xóa cache.
- [ ] **Bước 2: Chạy** `php artisan test --compact --filter="MoneyTest|SettingsServiceTest"`. Kỳ vọng: FAIL.
- [ ] **Bước 3: Viết code** theo chữ ký trên. `Settings` cache cả bảng trong cache database 1 giờ, thiếu thì lấy `config('linkcash.defaults')`.
- [ ] **Bước 4: Chạy lại.** Kỳ vọng: PASS.
- [ ] **Bước 5: Commit** `feat(settings): add money helper and settings service`.

### Task 2: Bảng users, vai trò, factory

**File:** sửa trực tiếp migration `0001_01_01_000000_create_users_table.php` (chưa có user thật), `app/Models/User.php`, `database/factories/UserFactory.php`, `DatabaseSeeder`; tạo `app/Enums/{UserRole,UserStatus,PayoutMethod}.php`. Test: `tests/Feature/UserModelTest.php`.

**Giao diện:**
- Cung cấp: `UserRole::{Member, Admin}`, `UserStatus::{Active, Banned}`, `PayoutMethod::{Paypal, BankTransfer, Momo, Usdt}` (mỗi enum có `label(): string`). Cột `users`: `username` (unique), `email` (unique), `password`, `role`, `status`, `api_token` (unique, 24 ký tự), `referral_code` (unique, 7 ký tự), `referred_by` (nullable, khóa ngoại users), `balance_micro`, `processing_micro` (mặc định 0), `fullname`, `phone_number`, `address`, `district`, `province`, `country`, `traffic_source` (text), `default_level` (tinyint 1 đến 3, mặc định 1: mức mặc định khi tạo link), `payout_method` (nullable), `payout_details` (json, nullable). `User::isAdmin(): bool`, `User::isActive(): bool`, `User::referrer(): BelongsTo`, `User::referrals(): HasMany`. Factory có state `admin()`, `banned()`. Hook `creating` tự sinh `api_token` và `referral_code` khi trống.

- [ ] **Bước 1: Viết test fail:** user mới có `api_token` 24 ký tự và `referral_code` 7 ký tự; `User::factory()->admin()->create()->isAdmin()` đúng; `banned()->isActive()` sai; quan hệ referrer đúng.
- [ ] **Bước 2: Chạy** `--filter=UserModelTest`. Kỳ vọng: FAIL.
- [ ] **Bước 3: Viết code:** cột migration, enum, `casts()` của model (`password` hashed), quan hệ, state factory.
- [ ] **Bước 4: Chạy** filter đó và `php artisan test --compact`. Kỳ vọng: PASS.
- [ ] **Bước 5: Commit** `feat(auth): extend users with roles, tokens and balances`.

### Task 3: Đổi URL công khai cho giống web thật

**File:** sửa `routes/web.php`, `resources/views/**` (mọi `route()` và link), `tests/Feature/*PageTest.php`, `AuthPagesTest.php`; tạo `app/Http/Controllers/ReferralRedirectController.php`. Test: `tests/Feature/PublicUrlsTest.php`.

**Giao diện:**
- Cung cấp: `GET /auth/signin` (`login`), `GET /auth/signup` (`register`), `POST /auth/signout` (`logout`), `GET /auth/forgot-password` (`password.request`), `GET /pages/privacy` (`privacy`), `GET /pages/terms` (`terms`), `GET /ref/{code}` (`referral`: lưu cookie `ref` 30 ngày rồi chuyển về `/auth/signup`). Tên route giữ nguyên nên Blade dùng `route()` không phải sửa. `/payout-rates` và `/blog` giữ nguyên.

- [ ] **Bước 1: Viết test fail:** mỗi URL ở trên trả 200 (hoặc redirect đúng với `/ref/{code}`); URL cũ `/login`, `/register`, `/privacy`, `/terms` trả 404; link ở footer và sidebar trỏ URL mới.
- [ ] **Bước 2: Chạy** `--filter=PublicUrlsTest`. Kỳ vọng: FAIL.
- [ ] **Bước 3: Đổi route**, cập nhật các test cũ cho URL mới.
- [ ] **Bước 4: Chạy** toàn bộ test. Kỳ vọng: PASS.
- [ ] **Bước 5: Commit** `refactor: align public urls with reference sites`.

### Task 4: Xác thực thật

**File:** tạo `app/Http/Requests/Auth/{LoginRequest,RegisterRequest,ForgotPasswordRequest,ResetPasswordRequest}.php`, `resources/views/auth/reset-password.blade.php`; sửa `app/Http/Controllers/Auth/*`, `routes/web.php`, `resources/views/auth/*.blade.php`, `resources/js/auth.js`. Test: `tests/Feature/Auth/{LoginTest,RegisterTest,PasswordResetTest}.php`.

**Giao diện:**
- Tiêu thụ: `User` (Task 2), URL (Task 3).
- Cung cấp: `POST /auth/signin`, `POST /auth/signup`, `POST /auth/forgot-password` (`password.email`), `GET /auth/reset-password/{token}` (`password.reset`), `POST /auth/reset-password` (`password.update`). Đăng nhập thành công chuyển tới `route('member.dashboard')`. `RegisterRequest`: `username` bắt buộc, 3 đến 32 ký tự `A-Za-z0-9_`, duy nhất; `email` duy nhất; `password` tối thiểu 8; `accept` phải được chấp nhận. Cookie `ref` được đổi thành `referred_by` lúc đăng ký.

- [ ] **Bước 1: Viết test fail:** đăng ký tạo user, đăng nhập, chuyển tới dashboard; trùng username báo lỗi; mật khẩu `short` bị từ chối; cookie `ref` ghi `referred_by`; đăng nhập được bằng username và bằng email; user bị khóa không đăng nhập được (`This account is disabled.`); sai 5 lần bị chặn tạm; đăng xuất chạy; luồng quên mật khẩu gửi thông báo (`Notification::fake()`) và mật khẩu mới dùng được.
- [ ] **Bước 2: Chạy** `--filter="LoginTest|RegisterTest|PasswordResetTest"`. Kỳ vọng: FAIL.
- [ ] **Bước 3: Viết code** controller với FormRequest; đăng nhập nhận `username` là username hoặc email; `RateLimiter` theo username cộng IP. Sửa `auth.js` chỉ còn gợi ý phía client rồi gửi form bình thường (bỏ thông báo demo); lỗi server hiện trong `.help-block` có sẵn.
- [ ] **Bước 4: Chạy** filter đó và `--filter=AuthPagesTest`. Kỳ vọng: PASS.
- [ ] **Bước 5: Commit** theo từng phần: `feat(auth): add register and login`, `feat(auth): add password reset`.

### Task 5: Layout member giống link4m (AdminLTE)

**File:** tạo `resources/vendor/adminlte/` (chép `bootstrap.min.css`, `AdminLTE.min.css`, `_all-skins.min.css` (chỉ skin-blue nếu tách được), `all.css` của Font Awesome 5.15 kèm thư mục `webfonts`, `bootstrap.min.js`, `app.min.js` từ `docs/references/link4m/css/` và nguồn AdminLTE 2.4 chính thức), `resources/css/member.css`, `resources/js/member.js`, `resources/views/layouts/member.blade.php`, `resources/views/components/member/{sidebar,shorten-box,stat-box,page-header}.blade.php`, `app/Http/Middleware/EnsureUserIsActive.php`, `routes/member.php`, `app/Http/Controllers/Member/*Controller.php` (trang giữ chỗ). Sửa `bootstrap/app.php` (alias `active`, nạp `routes/member.php`), `vite.config.js`. Test: `tests/Feature/Member/LayoutTest.php`.

**Giao diện:**
- Cung cấp: tên route và URL đúng như bảng "Đối chiếu": `member.dashboard` (`/member/dashboard`), `member.notify`, `member.statistic`, `member.popular-link`, `member.links` (`/member/links`, GET và POST), `member.links.hidden`, `member.withdraws`, `member.tools.quick`, `member.tools.mass-shrinker`, `member.tools.full`, `member.tools.api`, `member.referrals`, `member.user.profile`, `member.user.change-password`, `member.user.change-email`, `member.form.support`. Layout có phần `title`, `breadcrumb`, `content`, `scripts`. `<x-member.stat-box color label value icon />` (4 màu: cam, xanh dương nhạt, xanh lá, đỏ như ảnh). Bố cục phải khớp `shots/dash.png`: thanh trên xanh dương với chữ "Member" bên trái, nút thu/gập sidebar, tên người dùng bên phải; sidebar tối với ô "Shorten a new link" (form `POST /links/shorten`, ô chọn mức đã chọn sẵn `default_level` của member), các mục menu có icon, nhóm có submenu mở ra (Links: All Links / Hidden Links; Tools: Quick Link / Mass Shrinker / Full Page Script / Developers API; Settings: Profile / Change Password / Change Email), chân trang "Copyright © {năm}" và "Version 1.0". Khách chưa đăng nhập chuyển tới `login`; user bị khóa bị đăng xuất kèm lỗi.

- [ ] **Bước 1: Viết test fail:** khách vào `/member/dashboard` bị chuyển tới login; member thấy đủ nhãn menu ở trên; user bị khóa bị chuyển về login; sidebar không có link admin.
- [ ] **Bước 2: Chạy** `--filter=LayoutTest`. Kỳ vọng: FAIL.
- [ ] **Bước 3: Viết code:** layout dùng đúng class của AdminLTE (`skin-blue sidebar-mini`, `main-header`, `main-sidebar`, `content-wrapper`, `main-footer`). Chỉ nạp CSS này ở layout member (qua entry Vite riêng) để không ảnh hưởng trang công khai. Đăng ký mọi route trỏ view giữ chỗ để các task sau chỉ thay nội dung. `member.js` bọc hành vi sidebar của AdminLTE và nút `.js-copy`.
- [ ] **Bước 4: Chạy** filter đó. Kỳ vọng: PASS. Chạy `npm run build`, mở `/member/dashboard` ở 1280, 768 và 360 px bằng `playwright-cli`, so với `shots/dash.png`: khác biệt chỉ được là chữ, logo và ảnh banner.
- [ ] **Bước 5: Commit** `feat(member): add adminlte member layout and route group`.

### Task 6: Trang cài đặt (Profile, Change Password, Change Email)

**File:** tạo `app/Http/Controllers/Member/{ProfileController,PasswordController,EmailController}.php`, `app/Http/Requests/Member/{UpdateProfileRequest,ChangePasswordRequest,ChangeEmailRequest}.php`, `resources/views/member/user/{profile,change-password,change-email}.blade.php`, `resources/js/member-settings.js`, `resources/js/password-meter.js` (tách từ trang đăng ký để dùng chung). Test: `tests/Feature/Member/SettingsPagesTest.php`.

**Giao diện:**
- Tiêu thụ: layout và route Task 5, `PayoutMethod` Task 2.
- Cung cấp: Profile gồm hai khối như `shots/user_profile.png` cộng **một khối thêm** "Link settings" (ô chọn "Default level" Level 1/2/3 lưu vào `users.default_level`, có chú thích "Used when you create a link without choosing a level"; luật `default_level` thuộc `1,2,3`): "Billing address" (Your name, Phone, Address, District, Province, Country (select)) và "Withdrawal info" (Traffic source (textarea), Payment method (radio: PayPal, Bank Transfer, Momo, USDT) kèm trường chi tiết theo phương thức, bảng "Payment method / Minimum withdrawal amount" lấy từ `Settings`, các ghi chú hướng dẫn, nút Submit). Chi tiết: PayPal cần `paypal_email`; Bank Transfer cần `bank_name`, `account_number`, `account_holder`, `branch`, `phone_number`; Momo cần `momo_phone`; USDT cần `usdt_address`. `ChangePasswordRequest` cần `current_password` và `password` (tối thiểu 8; dùng bộ đo độ mạnh có tick xanh như trang đăng ký, không có ô nhập lại). `ChangeEmailRequest` cần `current_password` và `email` mới (duy nhất), không có ô nhập lại email. Thành công thì redirect kèm thông báo `Saved.`

- [ ] **Bước 1: Viết test fail:** cập nhật profile lưu đúng; `default_level` 0 và 4 bị từ chối, 2 được lưu; chọn PayPal mà thiếu `paypal_email` bị từ chối; sai mật khẩu hiện tại bị từ chối ở đổi mật khẩu và đổi email; đổi mật khẩu thành công vẫn giữ phiên; email đã dùng bị từ chối.
- [ ] **Bước 2: Chạy** `--filter=SettingsPagesTest`. Kỳ vọng: FAIL.
- [ ] **Bước 3: Viết code.** `member-settings.js` chỉ hiện trường chi tiết của phương thức đang chọn.
- [ ] **Bước 4: Chạy** filter đó. Kỳ vọng: PASS. So với `shots/user_profile.png`.
- [ ] **Bước 5: Commit** `feat(member): add profile, password and email settings`.

### Task 7: Khung admin và lệnh `make:admin`

**File:** tạo `app/Http/Middleware/EnsureUserIsAdmin.php`, `routes/admin.php`, `resources/views/layouts/admin.blade.php` (cùng AdminLTE, skin khác màu), `app/Http/Controllers/Admin/DashboardController.php`, `app/Console/Commands/MakeAdminCommand.php`, `database/seeders/AdminSeeder.php`. Sửa `bootstrap/app.php` (alias `admin`), `DatabaseSeeder`. Test: `tests/Feature/Admin/AdminAccessTest.php`, `tests/Feature/MakeAdminCommandTest.php`.

**Giao diện:**
- Cung cấp: nhóm route `/admin` (`auth`, `active`, `admin`) tên `admin.dashboard`; layout `layouts.admin` có sidebar để các task sau thêm mục (Settings, Rates, Ad slots, Blocked networks, Fraud flags, Links, Withdrawals, Notices, Support). Lệnh `make:admin {username} {email} {--password=}` (hỏi mật khẩu nếu thiếu, không bao giờ in ra).

- [ ] **Bước 1: Viết test fail:** member vào `/admin` nhận 403; admin nhận 200; khách bị chuyển tới login; lệnh tạo được admin và từ chối username trùng.
- [ ] **Bước 2: Chạy** `--filter="AdminAccessTest|MakeAdminCommandTest"`. Kỳ vọng: FAIL.
- [ ] **Bước 3: Viết code** middleware, layout, lệnh, seeder (admin demo chỉ khi `app()->isLocal()`).
- [ ] **Bước 4: Chạy** filter đó, rồi toàn bộ test và `vendor/bin/pint --dirty`. Kỳ vọng: PASS.
- [ ] **Bước 5: Commit** `feat(admin): add admin area shell and make admin command`. Điểm dừng giai đoạn 1: chỉ merge khi chủ dự án nói.

---

## Giai đoạn 2: Link (branch `feat/links`)

### Task 8: Bảng links, alias và dịch vụ rút gọn

**File:** tạo `app/Models/Link.php`, migration, factory, `app/Enums/LinkStatus.php`, `app/Support/Alias.php`, `app/Services/LinkShortener.php`, `app/Exceptions/InvalidLinkException.php`. Test: `tests/Unit/AliasTest.php`, `tests/Feature/LinkShortenerTest.php`.

**Giao diện:**
- Cung cấp: cột `links`: `user_id`, `alias` (`string(32)`, duy nhất, collation nhị phân `utf8mb4_bin` khi driver là MySQL để phân biệt hoa thường), `url` (text), `level` (1 đến 3), `status` (`LinkStatus::{Active, Hidden, Banned}`), `views_count`, `created_at`. `Alias::isValid(string $alias): bool` (alias tự đặt: 3 đến 32 ký tự `A-Za-z0-9_-`), `Alias::isReserved(string $alias): bool`, `Alias::generate(int $length = 6): string` (thuật toán ở mục "Thuật toán tạo alias": `random_int(0, 61)` trên bảng `A-Za-z0-9`; không bao giờ trả alias bị giữ chỗ). `LinkShortener::create(User $user, string $url, ?string $alias, ?int $level = null): Link` (`$level` null thì dùng `$user->default_level`; khi `$alias` null thì sinh alias, thử lại tối đa 5 lần mỗi độ dài và tăng độ dài tới tối đa 12 khi trùng; ném `InvalidLinkException` có thông báo đọc được). `Link::user(): BelongsTo`.

- [ ] **Bước 1: Viết test fail:** mọi alias bị giữ chỗ ở mục Ràng buộc chung bị từ chối; `Abc` và `abc` là hai alias khác nhau và tạo được cả hai; alias 2 hoặc 33 ký tự hoặc có khoảng trắng bị từ chối; sinh 5.000 alias: tất cả đúng 6 ký tự, chỉ gồm `A-Za-z0-9`, không alias nào trùng nhau, và trên 95% có cả chữ hoa lẫn chữ thường; khi alias vừa sinh đã tồn tại (giả lập bằng cách tạo trước), hàm thử lại và cho alias khác; khi cả 5 lần thử cùng độ dài đều trùng, alias mới dài 7 ký tự; `level` null dùng `default_level` của member (đặt 3 thì link có mức 3); mức 0 và 4 bị từ chối; **(Review Focus 2)** `javascript:alert(1)`, `data:text/html,x`, `ftp://x` và chính host của app bị từ chối, URL 2.049 ký tự bị từ chối, `https://example.com/a?b=c` được lưu nguyên vẹn.
- [ ] **Bước 2: Chạy** `--filter="AliasTest|LinkShortenerTest"`. Kỳ vọng: FAIL.
- [ ] **Bước 3: Viết code** theo chữ ký trên. Kiểm tra host của app bằng `parse_url(config('app.url'), PHP_URL_HOST)`.
- [ ] **Bước 4: Chạy** filter đó. Kỳ vọng: PASS. Sau đó **kiểm tra tay trên MySQL** (test dùng SQLite nên không bắt được lỗi collation): chạy migration trên một cơ sở dữ liệu MySQL, tạo link alias `Abc` rồi `abc`, cả hai phải được lưu; ghi kết quả vào báo cáo của task.
- [ ] **Bước 5: Commit** `feat(links): add links table and shortener service`.

### Task 9: Tạo link, danh sách link và trang thống kê từng link `/{alias}/info`

**File:** tạo `app/Http/Controllers/{LinkShortenController,LinkInfoController}.php`, `app/Http/Controllers/Member/LinkController.php`, `app/Http/Requests/Member/StoreLinkRequest.php`, `app/Traits/ApiResponse.php` (`success`, `error`, `systemError` như dự án tham chiếu), `resources/views/member/links/index.blade.php`, `resources/views/links/info.blade.php`, `resources/js/member-links.js`. Test: `tests/Feature/Member/LinkManagementTest.php`.

**Giao diện:**
- Tiêu thụ: `LinkShortener` (Task 8).
- Cung cấp: `POST /links/shorten` (JSON `{success, message, data: {short_url, alias}}`, nhận `url`, `alias`, `ad_type` = mức 1 đến 3; thiếu `ad_type` thì dùng `default_level` của member), `GET|POST /member/links` (lọc `filter[alias]`, 20 mỗi trang), `GET /member/links/hidden`, `POST /member/links/hide`, `POST /member/links/restore` (chỉ chủ link, người khác 403), `GET /{alias}/info` (`link.info`): **trang thống kê riêng của từng link, chỉ chủ link xem** (khách bị chuyển tới đăng nhập, member khác nhận 403). Đây là trang mở bằng nút "Stats" ở danh sách link, giống link4m. Nội dung: URL đích, link rút gọn, ngày tạo, mức, tổng view hợp lệ, thu nhập từ link, và bảng 30 ngày gần nhất (ngày, view, thu nhập) lấy từ dữ liệu tổng hợp; trước khi có Task 17 trang chỉ hiện số view và ngày tạo. Alias bị giữ chỗ không bao giờ khớp route này. Mỗi link là một thẻ như `shots/links.png`: URL đích là tiêu đề, dòng nhỏ "Stats - ngày giờ - URL đích", ô chỉ đọc chứa link rút gọn kèm nút copy, nút đỏ "Hide" bên phải; trên cùng có ô lọc Alias, nút Filter và liên kết Reset.

- [ ] **Bước 1: Viết test fail:** tạo link hợp lệ trả link rút gọn, dữ liệu sai trả 422 JSON; danh sách chỉ có link của chính member; lọc theo alias thu hẹp kết quả; ẩn đưa link sang danh sách ẩn, khôi phục đưa về; link người khác trả 403; gửi form không có `ad_type` tạo link với `default_level` của member; `/{alias}/info`: chủ link thấy URL đích, số view và ngày tạo, khách bị chuyển tới login, member khác nhận 403.
- [ ] **Bước 2: Chạy** `--filter=LinkManagementTest`. Kỳ vọng: FAIL.
- [ ] **Bước 3: Viết code** controller, request và jQuery của form (`$.ajax`, hiện link rút gọn kèm nút copy, hiện lỗi).
- [ ] **Bước 4: Chạy** filter đó. Kỳ vọng: PASS. So với `shots/links.png`.
- [ ] **Bước 5: Commit** `feat(links): add shorten form, links list and info page`.

### Task 10: Công cụ và API công khai

**File:** tạo `app/Http/Controllers/Member/ToolController.php`, `app/Http/Controllers/Api/ShortenController.php`, `app/Http/Controllers/QuickLinkController.php`, `routes/api.php`, `public/js/full-script.js`, `resources/views/member/tools/{quick,mass-shrinker,full,api}.blade.php`, `app/Http/Requests/Member/MassShrinkRequest.php`. Sửa `bootstrap/app.php` (nạp `routes/api.php` không tiền tố `/api` tự động để giữ đúng `/api` và `/api-shorten/v2`), `routes/member.php`, `routes/web.php`. Test: `tests/Feature/Member/ToolsTest.php`, `tests/Feature/Api/ShortenApiTest.php`.

**Giao diện:**
- Cung cấp: `GET /api?api=&url=&alias=&format=json|text` và `GET /api-shorten/v2?api=&url=` (cùng một xử lý; `format=text` trả đúng link rút gọn dạng chữ thuần), JSON thành công `{"status":"success","message":"","shortenedUrl":"..."}`, lỗi `{"status":"error","message":"..."}` (ví dụ `Invalid API token.`), giới hạn `60,1`. `GET /st?api=&url=` tạo link cho chủ token. `POST /member/tools/mass-shrinker` nhận `urls` (mỗi dòng một URL, tối đa 20, trả kết quả từng dòng). Các trang công cụ hiện token của member, ví dụ dùng và đoạn mã của `full-script.js` (biến `linkcash_url`, `linkcash_api_token`, `linkcash_advert`, `linkcash_domains`).

- [ ] **Bước 1: Viết test fail:** API, Quick Link và Mass Shrinker không có tham số `level` đều tạo link với `default_level` của chủ token (đặt 3 thì link mức 3); token và URL hợp lệ trả JSON thành công ở cả hai đường dẫn; token lạ và user bị khóa trả JSON lỗi; `format=text` trả chữ thuần; lần gọi thứ 61 trong một phút trả 429; mass shrinker từ chối 21 dòng, trả kết quả từng dòng và báo dòng sai mà không dừng cả lô; quick link tạo link cho chủ token.
- [ ] **Bước 2: Chạy** `--filter="ToolsTest|ShortenApiTest"`. Kỳ vọng: FAIL.
- [ ] **Bước 3: Viết code.** `full-script.js` là JavaScript thuần của trình duyệt (chạy trên web của người khác nên không dùng jQuery), dưới 2 KB, đổi các thẻ `<a>` sang link rút gọn qua API.
- [ ] **Bước 4: Chạy** filter đó. Kỳ vọng: PASS. So giao diện với `shots/tools_*.png`.
- [ ] **Bước 5: Commit** `feat(links): add api, quick link, mass shrinker and full page script`. Điểm dừng giai đoạn 2.

---

## Giai đoạn 3: Đếm view (branch `feat/view-counting`)

### Task 11: Cấu trúc dữ liệu đếm view và dữ liệu giá

**File:** tạo migration và model cho `cpm_rates`, `visit_sessions`, `views`, `view_dedup`, `blocked_networks`, `fraud_flags`; `app/Enums/{ViewStatus,InvalidReason,VisitStatus}.php`; factory cho từng model; `database/seeders/CpmRateSeeder.php`. Test: `tests/Feature/ViewSchemaTest.php`.

**Giao diện:**
- Cung cấp: `cpm_rates(country char(2), level tinyint, rate_per_1000_micro bigint)` unique `(country, level)`. `visit_sessions(id ulid, link_id, ip, ua_hash, fingerprint, country, level, current_step, step_issued_at, token_hash, status VisitStatus, expires_at, finished_at)`. `views(id, link_id, user_id, visit_session_id unique, ip, country, level, status ViewStatus::{Pending, Released, Cancelled, Invalid}, invalid_reason ?InvalidReason, earn_micro, matures_at, aggregated_at, created_at)`. `InvalidReason::{MissingSteps, DwellTooShort, CaptchaFailed, Duplicate, BlockedNetwork, Bot, IpChanged, NoJavascript, NoCookie}`. `view_dedup(key_hash char(64) unique, expires_at)`. `blocked_networks(cidr, label ?)`. `fraud_flags(user_id ?, link_id ?, reason, details json, resolved_at ?)`. `CpmRateSeeder` nạp các mức mẫu đang có trên trang Publisher Rates (Vietnam 4.50/6.00/7.50, United States 12/16/20, United Kingdom 10/13/16, Canada 9/12/15, Australia 9/12/14, Germany 8/11/13, France 7/9/11, Japan 6/8/10, Thailand 3.5/4.5/5.5, Philippines 2.5/3.5/4.5, Indonesia 3/4/5, India 2/3/4) và dòng dự phòng `XX` 1.50/2.00/2.50.

- [ ] **Bước 1: Viết test fail:** mỗi factory tạo được dòng; `(country, level)` duy nhất; `view_dedup.key_hash` duy nhất; seeder tạo 13 quốc gia x 3 mức.
- [ ] **Bước 2: Chạy** `--filter=ViewSchemaTest`. Kỳ vọng: FAIL.
- [ ] **Bước 3: Viết code** migration, model (quan hệ có kiểu, `casts()`), factory, seeder; thêm seeder vào `DatabaseSeeder`.
- [ ] **Bước 4: Chạy** filter đó. Kỳ vọng: PASS.
- [ ] **Bước 5: Commit** `feat(views): add view counting schema and rate seed`.

### Task 12: Sổ cái và WalletService

**File:** tạo `app/Models/Transaction.php`, migration, factory, `app/Enums/TransactionAction.php`, `app/Services/WalletService.php`, `app/Exceptions/InsufficientBalanceException.php`. Test: `tests/Feature/WalletServiceTest.php`.

**Giao diện:**
- Cung cấp: `TransactionAction::{ViewEarnings, ReferralCommission, ReferralReversal, EarningsAdjustment, WithdrawRequest, WithdrawPaid, WithdrawRejected}` (có `label(): string`). `transactions(user_id, type 'in'|'out', action, amount_micro, balance_before_micro, balance_after_micro, reference_id ?, description)`. `WalletService::credit(User $user, int $micro, TransactionAction $action, ?string $referenceId = null, string $description = ''): Transaction`, `WalletService::debit(...)` (cùng tham số, ném `InsufficientBalanceException`), `reserveForWithdrawal(User $user, int $micro, string $referenceId): Transaction` (từ khả dụng sang đang xử lý), `settleWithdrawal(...)` (đang xử lý sang đã trả), `releaseWithdrawal(...)` (đang xử lý trả lại khả dụng). Mọi hàm chạy trong `DB::transaction`, lấy lại user bằng `lockForUpdate()` và ghi `Transaction` cùng giao dịch, theo `mst-laravel-conventions/references/wallet-ledger.md`.

- [ ] **Bước 1: Viết test fail:** credit tăng `balance_micro` và ghi `balance_before/after`; debit xuống dưới 0 ném lỗi và giữ nguyên số dư; reserve chuyển khả dụng sang đang xử lý; settle và release đúng như trên; sau một chuỗi thao tác hỗn hợp, `balance_micro` bằng tổng ròng các dòng sổ cái; model cũ truyền vào bị bỏ qua (service tự lấy lại).
- [ ] **Bước 2: Chạy** `--filter=WalletServiceTest`. Kỳ vọng: FAIL.
- [ ] **Bước 3: Viết code** theo chữ ký trên.
- [ ] **Bước 4: Chạy** filter đó. Kỳ vọng: PASS.
- [ ] **Bước 5: Commit** `feat(wallet): add ledger and wallet service`.

### Task 13: Tra giá, ngữ cảnh khách, mạng bị chặn, bot

**File:** tạo `app/Services/Views/{RateResolver,VisitorContext,HeaderCountryResolver,BotUserAgentDetector,BlockedNetworkChecker}.php`, `app/Contracts/CountryResolver.php`. Sửa `AppServiceProvider` (gắn `CountryResolver` với `HeaderCountryResolver`). Test: `tests/Unit/{RateResolverTest,BlockedNetworkCheckerTest,BotUserAgentDetectorTest}.php`, `tests/Feature/VisitorContextTest.php`.

**Giao diện:**
- Cung cấp: `RateResolver::ratePerViewMicro(string $country, int $level): int` (dòng của quốc gia, không có thì dòng `XX`, chia 1000). `VisitorContext` (readonly: `ip`, `uaHash`, `country`, `userAgent`) với `VisitorContext::fromRequest(Request $request, CountryResolver $countries): self`. `CountryResolver::resolve(string $ip, ?string $cfCountryHeader): string` (2 chữ hoa, `XX` khi không rõ). `BlockedNetworkChecker::isBlocked(string $ip): bool` (khớp CIDR IPv4 và IPv6, cache 10 phút). `BotUserAgentDetector::isBot(?string $userAgent): bool` (rỗng, `bot`, `crawl`, `spider`, `headless`, `curl`, `python-requests`, `phantomjs`, `selenium`).

- [ ] **Bước 1: Viết test fail:** quốc gia biết trước trả đúng giá chia 1000; quốc gia lạ mức 2 trả giá `XX` mức 2; `10.0.0.5` bị chặn bởi `10.0.0.0/24` còn `10.0.1.5` thì không; CIDR IPv6 khớp; `HeadlessChrome/120` và user-agent rỗng là bot, iPhone bình thường thì không; `CF-IPCountry: vn` ra `VN`, thiếu header ra `XX`.
- [ ] **Bước 2: Chạy** `--filter="RateResolverTest|BlockedNetworkCheckerTest|BotUserAgentDetectorTest|VisitorContextTest"`. Kỳ vọng: FAIL.
- [ ] **Bước 3: Viết code.** Chỉ tin `Request::ip()` (app cấu hình proxy tin cậy cho Cloudflare); không bao giờ đọc quốc gia do client tự gửi ngoài header của Cloudflare.
- [ ] **Bước 4: Chạy** filter đó. Kỳ vọng: PASS.
- [ ] **Bước 5: Commit** `feat(views): add rate, network and bot detection services`.

### Task 14: Token bước

**File:** tạo `app/Services/Views/StepTokenService.php`. Test: `tests/Unit/StepTokenServiceTest.php`.

**Giao diện:**
- Cung cấp: `StepTokenService::issue(VisitSession $session, int $step): string` (HMAC-SHA256 bằng khóa app trên id phiên, số bước, `ua_hash`, thời điểm cấp, nonce; lưu `sha256` của token vào `visit_sessions.token_hash` và đặt `step_issued_at`), `StepTokenService::verify(VisitSession $session, int $step, string $token, string $uaHash): TokenResult` với `TokenResult` là enum `{Valid, Tampered, Reused, WrongAgent, WrongStep}`. Token dùng một lần: sau khi `Valid` thì xóa `token_hash`. Theo mục 17.3 của thiết kế, token **không** chứa IP.

- [ ] **Bước 1: Viết test fail:** token hợp lệ xác minh được một lần, lần hai trả `Reused`; đổi một ký tự trả `Tampered`; `uaHash` khác trả `WrongAgent`; token bước 1 gửi cho bước 2 trả `WrongStep`; dữ liệu trong token không có IP.
- [ ] **Bước 2: Chạy** `--filter=StepTokenServiceTest`. Kỳ vọng: FAIL.
- [ ] **Bước 3: Viết code** bằng `hash_hmac`, `hash_equals`, nonce `random_bytes(16)`.
- [ ] **Bước 4: Chạy** filter đó. Kỳ vọng: PASS.
- [ ] **Bước 5: Commit** `feat(views): add signed one-time step tokens`.

### Task 15: Trang vượt link `/go/{alias}` (phiên, bước, báo lỗi)

**File:** tạo `app/Services/Views/VisitFlow.php`, `app/Services/Views/StepResult.php` (enum `{Accepted, TooEarly, BadToken, Expired, NoCookie, NoFingerprint}`), `app/Http/Controllers/VisitController.php`, `resources/views/layouts/visit.blade.php`, `resources/views/visit/go.blade.php`, `resources/css/visit.css`, `resources/js/visit.js`. Sửa `routes/web.php` (route vượt link đăng ký **cuối cùng**), `vite.config.js`, `config/services.php` (khóa site và secret của Turnstile, hCaptcha, reCAPTCHA). Test: `tests/Feature/VisitFlowTest.php`.

**Giao diện:**
- Tiêu thụ: `StepTokenService`, `VisitorContext`, `Settings`.
- Cung cấp (giống luồng thật của link4m):
  - `GET /{alias}` (`visit.start`, ràng buộc `[A-Za-z0-9_-]{3,32}`): alias không có, bị ẩn hoặc bị cấm trả 404; còn lại tạo hoặc dùng lại `VisitSession`, đặt cookie `lc_visit` (httpOnly, 30 ngày) và trả **302** tới `/go/{alias}` kèm `Cache-Control: no-store`.
  - `GET /go/{alias}` (`visit.show`): **một trang duy nhất** cho mọi bước, tiêu đề `Untitled #{alias}`, gồm thanh trên có menu (Home, Payout Rates, Contact), chỗ đặt quảng cáo `<x-visit.ad-slot>` (thêm ở Task 19), vùng đếm ngược và nút lấy link, widget captcha ở bước cuối, hộp thoại "Report an error", khung "Ready to earn money? Sign up / Sign in" và chân trang bản quyền. Chuyển bước không tải lại trang.
  - `POST /links/get-link-info` (`visit.info`, nhận `alias`, `fingerprint`, và `token` của bước trước, bước 1 thì không có token): trả JSON `{step, total_steps, dwell_seconds, token, captcha_required}` hoặc `{error}` mã 422. Gọi lần đầu cấp token bước 1; gọi tiếp với token hợp lệ và đã đủ dwell thì cấp token bước kế.
  - `POST /links/report` (`visit.report`, nhận `alias`, `reason` thuộc `code_not_shown|page_not_shown|other`, `detail` tối đa 1.000 ký tự; giới hạn `5,1`): lưu thành dòng `fraud_flags` với `reason = visitor_report` và nội dung trong `details`.
  - `VisitFlow::start(Link $link, VisitorContext $ctx): VisitSession`, `VisitFlow::stepInfo(VisitSession $s, ?string $token, ?string $fingerprint, VisitorContext $ctx): StepInfo` (readonly: `StepResult $result`, `?int $step`, `?int $totalSteps`, `?int $dwellSeconds`, `?string $token`, `bool $captchaRequired`). `visit.js` (jQuery) chạy đếm ngược theo `dwell_seconds`, tạo vân tay trình duyệt (kích thước màn hình, múi giờ, ngôn ngữ, hash canvas), chỉ bật nút sau khi đếm xong rồi mới gọi `get-link-info`.

- [ ] **Bước 1: Viết test fail:** alias lạ trả 404; `GET /{alias}` trả 302 với `Location` là `/go/{alias}`, đặt cookie `lc_visit` và có `Cache-Control: no-store`; `GET /go/{alias}` trả 200 với tiêu đề `Untitled #{alias}`; lần `get-link-info` đầu trả token bước 1; gọi lần hai khi chưa đủ dwell trả `TooEarly` (dùng `Carbon::setTestNow`); đủ dwell thì trả token bước kế; thiếu cookie trả `NoCookie`; thiếu vân tay trả `NoFingerprint`; phiên quá hạn (31 phút) trả `Expired`; user-agent khác bị từ chối; IP khác được chấp nhận và ghi nhớ cho Task 16; **(Review Focus 3)** phiên không gửi vân tay không lấy được token bước 2; `POST /links/report` lưu một dòng `fraud_flags`, từ chối `reason` lạ và tin thứ sáu trong một phút trả 429.
- [ ] **Bước 2: Chạy** `--filter=VisitFlowTest`. Kỳ vọng: FAIL.
- [ ] **Bước 3: Viết code** luồng và controller; mức N có N bước, bước cuối có `captcha_required = true`.
- [ ] **Bước 4: Chạy** filter đó. Kỳ vọng: PASS. Build asset và đi thử link mức 2 trên trình duyệt: `/{alias}` phải chuyển sang `/go/{alias}`.
- [ ] **Bước 5: Commit** `feat(views): add visit page and step flow`.

### Task 16: Kết thúc, chuỗi kiểm tra và ghi view

**File:** tạo `app/Contracts/CaptchaVerifier.php`, `app/Services/Views/{TurnstileVerifier,HCaptchaVerifier,RecaptchaVerifier,FakeCaptchaVerifier,ViewValidator,ViewRecorder,FinishResult}.php`. Sửa `VisitController.php` (thêm `checkCaptcha`), `routes/web.php`, `AppServiceProvider.php`, `resources/js/visit.js`. Test: `tests/Feature/ViewRecordingTest.php`, `tests/Feature/CaptchaVerifierTest.php`.

**Giao diện:**
- Cung cấp: `POST /links/check-captcha` (`visit.check-captcha`, nhận `alias`, `token`, `fingerprint`, `captcha_response`; trả JSON `{destination}`) là bước cuối: kiểm tra captcha, chạy chuỗi kiểm tra và ghi view. `CaptchaVerifier::verify(string $response, string $ip): bool` có ba cài đặt `TurnstileVerifier`, `HCaptchaVerifier`, `RecaptchaVerifier` (mỗi cái gọi siteverify của nhà cung cấp bằng `Http`), chọn bằng setting `captcha_provider` (mặc định `turnstile`, admin đổi được cùng khóa site/secret, giống các script cùng loại hỗ trợ nhiều nhà cung cấp); môi trường testing gắn `FakeCaptchaVerifier`. `ViewValidator::firstFailure(VisitSession $s, VisitorContext $ctx, bool $captchaOk): ?InvalidReason` chạy theo thứ tự: `NoCookie`, `NoJavascript`, `MissingSteps`, `DwellTooShort`, `CaptchaFailed`, `Bot`, `BlockedNetwork`, `Duplicate`, `IpChanged`. `ViewRecorder::record(VisitSession $s, VisitorContext $ctx, ?InvalidReason $reason): View` trong một `DB::transaction`: khóa dòng phiên, trả lại `View` có sẵn nếu phiên đã kết thúc (idempotent), thêm `view_dedup` (`sha256(link_id|ip|fingerprint)`, `expires_at = now + cửa sổ unique`) chỉ với view hợp lệ, lưu `earn_micro` từ `RateResolver` cho view hợp lệ và `0` cho view không hợp lệ, đặt `matures_at = now + review_days`, tăng `links.views_count` cho view hợp lệ. Link đích luôn được trả, kể cả khi view không hợp lệ.

- [ ] **Bước 1: Viết test fail (mỗi luật một test, dùng captcha giả và `Carbon::setTestNow`):** một lượt vượt 3 bước sạch ghi view `Pending` với mức và giá của quốc gia; các lý do `Duplicate` (lần hai cùng IP và vân tay trong 24 giờ không có tiền, sau 25 giờ có tiền), `DwellTooShort`, `CaptchaFailed`, `Bot`, `BlockedNetwork`, `IpChanged`, `NoJavascript`, `NoCookie` đều tạo view không hợp lệ đúng lý do và vẫn trả link đích; test từng verifier (Turnstile, hCaptcha, reCAPTCHA) bằng `Http::fake` (thành công và thất bại) và việc chọn theo `captcha_provider`; **(Review Focus 1)** hai lần gọi `check-captcha` cho một phiên chỉ tạo đúng một dòng `views` và một dòng `view_dedup`; đổi giá sau khi có view không làm đổi `earn_micro`.
- [ ] **Bước 2: Chạy** `--filter="ViewRecordingTest|CaptchaVerifierTest"`. Kỳ vọng: FAIL.
- [ ] **Bước 3: Viết code** theo chữ ký trên. Dùng unique index trên `views.visit_session_id` làm chốt chặn cuối chống ghi trùng.
- [ ] **Bước 4: Chạy** filter đó, rồi toàn bộ test. Kỳ vọng: PASS.
- [ ] **Bước 5: Commit** `feat(views): validate and record views at the last step`.

### Task 17: Gom thống kê, giải phóng tiền, hoa hồng, dọn dữ liệu

**File:** tạo `app/Models/DailyStat.php`, migration `daily_stats(user_id, stat_date, views, earn_micro, ref_earn_micro, unique(user_id, stat_date))`, factory, `app/Console/Commands/{AggregateViews,ReleaseMaturedEarnings,PurgeOldViews}.php`, `app/Services/Views/EarningsReleaser.php`. Sửa `routes/console.php`. Test: `tests/Feature/ViewAggregationTest.php`, `tests/Feature/EarningsReleaseTest.php`.

**Giao diện:**
- Tiêu thụ: `WalletService` (Task 12), `views` (Task 11).
- Cung cấp: lệnh `views:aggregate` (cộng view hợp lệ và đã giải phóng có `aggregated_at` null vào `daily_stats` theo user và ngày UTC, rồi đánh dấu), `earnings:release` (view `Pending` có `matures_at <= now` thành `Released`; mỗi user một lần `WalletService::credit(..., ViewEarnings, ...)` cho tổng; người giới thiệu nhận `referral_percent` của tổng đó qua `ReferralCommission` và `daily_stats.ref_earn_micro` được cập nhật), `views:purge` (xóa view thô đã gom và cũ hơn `view_purge_days`, không bao giờ xóa view đang chờ). Lịch: gom mỗi 5 phút, giải phóng hằng ngày lúc 00:10, dọn hằng ngày lúc 03:00. `EarningsReleaser::release(): int` trả số view đã giải phóng.

- [ ] **Bước 1: Viết test fail:** gom hai lần vẫn cho cùng kết quả; giải phóng cộng đúng tổng và tạo một giao dịch `view_earnings` mỗi user; người giới thiệu nhận 12% bằng giao dịch riêng và người được giới thiệu không bị trừ; view không hợp lệ và bị hủy không bao giờ được giải phóng; view chưa đến `matures_at` vẫn chờ; dọn giữ lại view mới và view đang chờ; chạy giải phóng hai lần không trả hai lần.
- [ ] **Bước 2: Chạy** `--filter="ViewAggregationTest|EarningsReleaseTest"`. Kỳ vọng: FAIL.
- [ ] **Bước 3: Viết code** các lệnh và lịch.
- [ ] **Bước 4: Chạy** filter đó. Kỳ vọng: PASS.
- [ ] **Bước 5: Commit** `feat(views): add aggregation, earnings release and purge`. Điểm dừng giai đoạn 3: đi tay một link mức 3 trên trình duyệt, rồi chạy toàn bộ test và Pint.

---

## Giai đoạn 4: Quảng cáo và admin (branch `feat/ads-admin`)

### Task 18: Cấu hình và bảng giá trong admin

**File:** tạo `app/Http/Controllers/Admin/{SettingController,CpmRateController}.php`, `app/Http/Requests/Admin/{UpdateSettingsRequest,StoreCpmRateRequest}.php`, `resources/views/admin/{settings,rates}/*.blade.php`. Sửa `routes/admin.php`, `app/Http/Controllers/PayoutRateController.php` (trang công khai đọc `cpm_rates`), `resources/views/payout-rates.blade.php`. Test: `tests/Feature/Admin/SettingsAndRatesTest.php`.

**Giao diện:**
- Cung cấp: `admin.settings.edit|update` (mọi tham số luật ở Ràng buộc chung, có kiểm tra khoảng giá trị, cộng `captcha_provider` thuộc `turnstile|hcaptcha|recaptcha` và khóa site/secret tương ứng; khóa secret không bao giờ hiện lại trong form sau khi lưu), `admin.rates.index|store|update|destroy` (mã quốc gia, ba giá theo mức bằng USD, đổi bằng `Money::toMicro`). Trang Publisher Rates công khai hiện các dòng từ cơ sở dữ liệu.

- [ ] **Bước 1: Viết test fail:** member không vào được; đổi dwell làm đổi `Settings::int('dwell_seconds')` và đồng hồ đếm ngược ở trang bước; đổi giá hiện trên trang công khai; mã quốc gia sai bị từ chối; dwell dưới 5 hoặc trên 60 bị từ chối.
- [ ] **Bước 2: Chạy** `--filter=SettingsAndRatesTest`. Kỳ vọng: FAIL.
- [ ] **Bước 3: Viết code.**
- [ ] **Bước 4: Chạy** filter đó và `--filter=PayoutRatesPageTest`. Kỳ vọng: PASS.
- [ ] **Bước 5: Commit** `feat(admin): add settings and rates management`.

### Task 19: Vị trí quảng cáo

**File:** tạo `app/Models/AdSlot.php`, migration, factory, seeder, `app/Enums/{AdPlacement,AdDevice}.php`, `app/Services/AdSlotRepository.php`, `app/View/Components/Visit/AdSlot.php`, `resources/views/components/visit/ad-slot.blade.php`, `app/Http/Controllers/Admin/AdSlotController.php`, `app/Http/Requests/Admin/StoreAdSlotRequest.php`, `resources/views/admin/ad-slots/*.blade.php`. Sửa `resources/views/visit/go.blade.php`, `routes/admin.php`. Test: `tests/Feature/AdSlotsTest.php`.

**Giao diện:**
- Cung cấp: `AdPlacement::{Step1, Step2, Step3, Final, Top, Bottom}`, `AdDevice::{All, Desktop, Mobile}`, `ad_slots(name, placement, device, network, code text, is_active, sort_order)`. `AdSlotRepository::forPlacement(AdPlacement $placement, AdDevice $device): Collection` (chỉ vị trí đang bật, có thứ tự, cache trong cache database, xóa cache mỗi lần admin lưu). `<x-visit.ad-slot :placement="..." />` hiện vị trí quảng cáo trong khối `ad-container`, khối này là anh em của nút Continue, không bao giờ là cha của nút.

- [ ] **Bước 1: Viết test fail:** mã của vị trí đang bật hiện đúng ở trang bước của nó và không hiện ở vị trí khác; vị trí tắt không hiện; vị trí mobile không hiện với user-agent desktop; nút Continue nằm ngoài mọi `.ad-container`; lưu vị trí xóa cache; CRUD admin chạy và member nhận 403.
- [ ] **Bước 2: Chạy** `--filter=AdSlotsTest`. Kỳ vọng: FAIL.
- [ ] **Bước 3: Viết code.** Mã quảng cáo được xuất không escape theo thiết kế (admin tin cậy); form admin ghi rõ điều này.
- [ ] **Bước 4: Chạy** filter đó. Kỳ vọng: PASS.
- [ ] **Bước 5: Commit** `feat(ads): add ad slots and admin management`.

### Task 20: Mạng bị chặn, cờ gian lận, khóa link

**File:** tạo `app/Http/Controllers/Admin/{BlockedNetworkController,FraudFlagController,LinkController}.php`, `app/Console/Commands/{ImportBlockedNetworks,ScanFraud}.php`, `app/Services/LinkBanService.php`, `resources/views/admin/{networks,fraud,links}/*.blade.php`. Sửa `routes/admin.php`, `routes/console.php`. Test: `tests/Feature/Admin/NetworksFraudBansTest.php`.

**Giao diện:**
- Tiêu thụ: `WalletService`, `views`.
- Cung cấp: lệnh `networks:import {file}` (mỗi dòng một CIDR, có thể kèm `# nhãn`, bỏ qua dòng sai và báo số lượng; CIDR trùng thì thay thế), CRUD admin cho `blocked_networks`. `LinkBanService::ban(Link $link, string $reason): void` đặt trạng thái `Banned`, đánh dấu view `Pending` của link là `Cancelled`, và với view đã `Released` thì ghi `EarningsAdjustment` âm cho chủ link và `ReferralReversal` cho người giới thiệu. Lệnh `fraud:scan` (chạy mỗi giờ) tạo `fraud_flags` khi một IP có hơn 20 view hợp lệ trên một link trong một ngày, hoặc tỷ lệ view không hợp lệ của một member vượt 60% trên 50 view trở lên. Admin xem cờ, đánh dấu đã xử lý hoặc khóa link.

- [ ] **Bước 1: Viết test fail:** import bỏ qua dòng sai và báo số lượng; link bị cấm trả 404 ở URL vượt link; khóa link hủy view đang chờ và ghi đảo bút toán cho view đã giải phóng (số dư chủ link và người giới thiệu khớp sổ cái); quét tạo cờ cho IP vượt ngưỡng và không tạo hai lần trong cùng ngày; member không vào được các route này.
- [ ] **Bước 2: Chạy** `--filter=NetworksFraudBansTest`. Kỳ vọng: FAIL.
- [ ] **Bước 3: Viết code.**
- [ ] **Bước 4: Chạy** filter đó. Kỳ vọng: PASS.
- [ ] **Bước 5: Commit** `feat(admin): add blocked networks, fraud flags and link bans`. Điểm dừng giai đoạn 4.

---

## Giai đoạn 5: Các trang liên quan tiền (branch `feat/member-money-pages`)

### Task 21: Overview, Statistic và Top Link

**File:** sửa `app/Http/Controllers/Member/DashboardController.php`, `resources/views/member/dashboard.blade.php`; tạo `app/Http/Controllers/Member/{StatisticController,PopularLinkController}.php`, `app/Services/DashboardStats.php`, `resources/views/member/{statistic,popular-link}.blade.php`, `resources/js/member-statistic.js`, `resources/js/chart.js`. Test: `tests/Feature/Member/DashboardStatsTest.php`.

**Giao diện:**
- Cung cấp: Overview đúng như `shots/dash.png` nhưng bỏ hai banner Telegram: tiêu đề "Statistics" kèm liên kết "View details" tới `/member/statistic?month=m/Y`, 4 ô màu **Today**, **Yesterday**, **This month** (thu nhập, từ `daily_stats`) và **Balance** (`balance_micro`), rồi khung "Notices" liệt kê thông báo đã đăng (Task 23). Statistic đúng như `shots/statistic.png`: ô chọn tháng (`?month=11/2026`), 4 ô **Total views**, **Total income**, **Referral income**, **Average CPM**, khung "Income chart" và bảng theo ngày (Day, Views, Earnings ($), CPM, Referral earnings). `POST /member/dashboard/get-data` nhận `date` dạng `dd/mm/yyyy`, trả JSON `{success, info, pay_view_data, publisher_earn_data, cpm_data, ref_data, total_view, total_publisher_earn, total_ref_earn, total_income, average_cpm}` cho tháng chứa ngày đó, tiền định dạng bằng `Money::format`. `DashboardStats::forMonth(User $user, CarbonImmutable $month): array`. `chart.js` cung cấp `window.LcChart.draw(canvas, series)` vẽ đường và trục bằng canvas. Top Link đúng như `shots/popular-link.png`: ô chọn tháng, khung "Top 10 Links In Month" với bảng No, Links, Shorten Link, View, Link Earnings (xếp theo view rồi thu nhập).

- [ ] **Bước 1: Viết test fail:** `get-data` trả tổng bằng `daily_stats` của tháng và không có dữ liệu member khác; tháng trống trả 0 và chuỗi rỗng; **(Review Focus 5)** `date=banana`, `date=01/01/2999` và thiếu `date` trả 422 JSON; `month=13/2026` và `month=abc` trả 422 ở AJAX và rơi về tháng hiện tại ở trang HTML; khách bị từ chối; CPM trung bình bằng thu nhập trên 1.000 view hợp lệ; Top Link tối đa 10 dòng và đúng thứ tự.
- [ ] **Bước 2: Chạy** `--filter=DashboardStatsTest`. Kỳ vọng: FAIL.
- [ ] **Bước 3: Viết code.**
- [ ] **Bước 4: Chạy** filter đó. Kỳ vọng: PASS. Xem biểu đồ ở 360 và 1200 px, so với các ảnh `shots/`.
- [ ] **Bước 5: Commit** `feat(member): add overview, statistic and top link pages`.

### Task 22: Referrals

**File:** tạo `app/Http/Controllers/Member/ReferralController.php`, `resources/views/member/referrals.blade.php`. Test: `tests/Feature/Member/ReferralsTest.php`.

**Giao diện:**
- Cung cấp: `GET /member/referrals` giống `shots/referrals.png`: khung "My Referrals", đoạn giới thiệu chương trình (hoa hồng lấy từ `Settings`), ô "Your referral link" là `url('/ref/'.$user->referral_code)`, bảng người được giới thiệu (username che còn 3 ký tự đầu cộng `***`, ngày tham gia, tổng hoa hồng đã nhận từ họ), và dòng "You have not referred anyone yet" khi trống.

- [ ] **Bước 1: Viết test fail:** trang hiện đúng mã của member và chỉ người họ giới thiệu; username bị che; có trạng thái trống; tổng hoa hồng bằng các giao dịch `ReferralCommission`.
- [ ] **Bước 2: Chạy** `--filter=ReferralsTest`. Kỳ vọng: FAIL.
- [ ] **Bước 3: Viết code.**
- [ ] **Bước 4: Chạy** filter đó. Kỳ vọng: PASS.
- [ ] **Bước 5: Commit** `feat(member): add referrals page`.

### Task 23: Notifications (`/member/notify`) và Support

**File:** tạo model, migration, factory cho `notices` (`title`, `body`, `is_published`, `published_at`) và `support_messages` (`user_id`, `name`, `email`, `title`, `message`, `resolved_at`); `app/Http/Controllers/Member/{NoticeController,SupportController}.php`, `app/Http/Controllers/Admin/{NoticeController,SupportMessageController}.php`, request, view. Sửa `resources/views/layouts/member.blade.php` (chuông trên thanh đầu hiện 3 thông báo mới nhất). Test: `tests/Feature/NoticesAndSupportTest.php`.

**Giao diện:**
- Cung cấp: `GET /member/notify` (danh sách thông báo đã đăng, mới nhất trước, mỗi thông báo một khung có ngày giờ, tiêu đề và nội dung như `shots/notify.png`, bỏ khung "Get notifications via Telegram"), `GET|POST /member/form/support` (form Name, Email, Title, Message tối đa 5.000 ký tự, như `shots/form_support.png`; giới hạn `5,1`), admin CRUD thông báo (nội dung là chữ thuần, xuất có escape) và hộp thư hỗ trợ có nút đánh dấu đã xử lý.

- [ ] **Bước 1: Viết test fail:** thông báo chưa đăng không hiện với member; tin nhắn hỗ trợ được lưu và hiện trong hộp thư admin; tin thứ sáu trong một phút trả 429; nội dung thông báo chứa `<script>` được escape khi xuất.
- [ ] **Bước 2: Chạy** `--filter=NoticesAndSupportTest`. Kỳ vọng: FAIL.
- [ ] **Bước 3: Viết code.**
- [ ] **Bước 4: Chạy** filter đó. Kỳ vọng: PASS.
- [ ] **Bước 5: Commit** `feat(member): add notifications and support form`.

### Task 24: Withdraws (member và admin)

**File:** tạo `app/Models/Withdrawal.php`, migration `withdrawals(user_id, amount_micro, method, details json, traffic_source, status WithdrawalStatus::{Pending, Paid, Rejected}, admin_note, processed_at, processed_by)`, factory, `app/Enums/WithdrawalStatus.php`, `app/Http/Controllers/Member/WithdrawController.php`, `app/Http/Controllers/Admin/WithdrawalController.php`, `app/Http/Requests/Member/StoreWithdrawalRequest.php`, view. Test: `tests/Feature/WithdrawalsTest.php`.

**Giao diện:**
- Tiêu thụ: `WalletService::reserveForWithdrawal|settleWithdrawal|releaseWithdrawal`, `Settings`.
- Cung cấp: `GET|POST /member/withdraws` giống `shots/withdraws.png`: ba ô **Available balance**, **Processing**, **Total paid** (tổng giao dịch `withdraw_paid`), một nút lớn "Withdraw" (tắt khi dưới mức tối thiểu) mở hộp thoại nhập số tiền và nguồn traffic, rồi khối ghi chú quan trọng (mức tối thiểu lấy từ `Settings`). Yêu cầu bị từ chối khi hồ sơ chưa có phương thức nhận tiền, số tiền dưới mức tối thiểu hoặc lớn hơn số dư khả dụng. Admin: `admin.withdrawals.index` (lọc theo trạng thái), `approve` (gọi `settleWithdrawal`, trạng thái `Paid`), `reject` (bắt buộc `admin_note`, gọi `releaseWithdrawal`). Thao tác admin có tính idempotent: duyệt lần hai trên dòng đã trả không làm gì và báo lỗi.

- [ ] **Bước 1: Viết test fail:** yêu cầu hợp lệ giữ chỗ số tiền (khả dụng giảm, đang xử lý tăng) và tạo dòng `Pending`; dưới mức tối thiểu và chưa có phương thức bị từ chối, **(Review Focus 4)** số tiền lớn hơn số dư bị từ chối và số dư giữ nguyên; hai yêu cầu cộng lại vượt số dư không thể cùng thành công; duyệt đặt `Paid` và cập nhật ô Total paid; từ chối trả tiền lại và bắt buộc có ghi chú; duyệt hai lần không đổi gì; member không duyệt được.
- [ ] **Bước 2: Chạy** `--filter=WithdrawalsTest`. Kỳ vọng: FAIL.
- [ ] **Bước 3: Viết code.**
- [ ] **Bước 4: Chạy** filter đó. Kỳ vọng: PASS.
- [ ] **Bước 5: Commit** `feat(member): add withdrawals with admin approval`. Điểm dừng giai đoạn 5.

---

## Giai đoạn 6: Hoàn thiện (branch `chore/hardening`)

### Task 25: Giới hạn tần suất và rà soát nội dung

**File:** sửa `bootstrap/app.php` (limiter tên `visit` 60/phút theo IP, `register` 5/phút theo IP, `api` 60/phút theo token), `routes/*.php`, `resources/views/payout-rates.blade.php` và `resources/views/pages/*` chỉ ở chỗ nội dung mâu thuẫn với luật đã làm. Test: `tests/Feature/RateLimitTest.php`.

**Giao diện:**
- Cung cấp: các limiter ở trên. Request vượt link thứ 61 từ một IP trong một phút trả 429; lần đăng ký thứ 6 trả 429.

- [ ] **Bước 1: Viết test fail** cho từng limiter.
- [ ] **Bước 2: Chạy** `--filter=RateLimitTest`. Kỳ vọng: FAIL.
- [ ] **Bước 3: Viết code** và kiểm tra nội dung chính sách view đã công bố khớp mặc định của Settings (10 giây, 24 giờ).
- [ ] **Bước 4: Chạy** filter đó. Kỳ vọng: PASS.
- [ ] **Bước 5: Commit** `chore: add rate limiters`.

### Task 26: Kiểm tra toàn tuyến và so giao diện

**File:** test: `tests/Feature/EndToEndEarningsTest.php`.

- [ ] **Bước 1: Viết test chạy trọn vòng:** đăng ký người giới thiệu và một member, member tạo link, khách đi hết link mức 2 (captcha giả, đẩy đồng hồ qua dwell), thời gian trôi qua thời hạn chờ, chạy `views:aggregate` và `earnings:release`, member rút tiền, admin duyệt. Khẳng định: khách được chuyển tới đích; `get-data` bằng `daily_stats`; `balance_micro` cộng `processing_micro` cộng tổng đã trả bằng tổng các dòng sổ cái của member; người giới thiệu nhận đúng 12%.
- [ ] **Bước 2: Chạy** test này. Kỳ vọng: PASS (nó dùng các task đã xong; nếu lỗi thì sửa ở task sở hữu, không sửa test).
- [ ] **Bước 3: Chạy** toàn bộ `php artisan test --compact`, `vendor/bin/pint --dirty`, `npm run build`.
- [ ] **Bước 4: So giao diện bằng `playwright-cli`** ở 1280, 768 và 360 px: từng trang member đặt cạnh ảnh cùng tên trong `docs/references/link4m/shots/`; khác biệt chỉ được là chữ, logo và ảnh. Ghi lại mọi khác biệt còn lại để chủ dự án quyết. Ảnh chụp chỉ lưu trong `.playwright-cli/`.
- [ ] **Bước 5: Commit** `test: add end to end earnings test`.

---

## Tự rà soát với thiết kế

- Mục tiêu 1 đến 8 của thiết kế: tài khoản (Task 2 đến 4, 6), trang member (5, 6, 9, 10, 21 đến 24), tạo link (8 đến 10), đếm view (11 đến 17), thu nhập và rút tiền (12, 17, 24), quảng cáo (19), admin (7, 18 đến 20, 24), jQuery và tiếng Anh (Ràng buộc chung).
- Luật mục 7: bước và dwell (14, 15), captcha (16), unique (16), mạng (13, 16), bot (13, 16), quốc gia (13), ghi view và gom (16, 17). Sổ cái mục 8: 12, 17, 20, 24. Quảng cáo mục 9: 19.
- Tên đã kiểm tra nhất quán giữa các task: các hàm `WalletService`, `StepTokenService::issue|verify`, `VisitFlow::start|stepInfo`, `ViewValidator::firstFailure`, `ViewRecorder::record`, `RateResolver::ratePerViewMicro`, tên route `member.*`, `admin.*`, `visit.*`.
- Ngoài phạm vi (non-goal của thiết kế): chuyển tiền thật, thông báo Telegram và email (ngoài email đặt lại mật khẩu), 2FA, tên miền riêng, trình cài đặt web, dịch vụ chống gian lận bên ngoài, DataTables, CKEditor.
- Việc cần bạn quyết khi đọc: ký tự cho phép của alias tự đặt (điểm duy nhất còn chưa kiểm chứng ở đầu tài liệu); bộ đọc quốc gia GeoLite2 (Task 13 chỉ gắn bộ đọc header Cloudflare, GeoLite2 thay sau); hai ngưỡng quét gian lận ở Task 20 là mặc định của mình, chưa chỉnh được trong admin; Momo có giữ trong danh sách phương thức nhận tiền hay không (link4m có, trang công khai của LinkCash chưa nhắc).
