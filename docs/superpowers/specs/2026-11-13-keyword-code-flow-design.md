# Chế độ "tìm từ khóa lấy mã" cho trang vượt link: thiết kế

Trạng thái: bản nháp chờ chủ dự án duyệt. Ngày: 2026-11-13. Tài liệu viết tiếng Việt để bạn đọc; mọi thứ trong repo (code, comment, tên bảng, chữ trên giao diện, thông báo lỗi) vẫn là tiếng Anh.

Tài liệu này **bổ sung** cho `2026-11-13-member-dashboard-view-counting-design.md` (trang `/go/{alias}` kiểu "chờ theo giây" đã làm). Ảnh tham chiếu: `docs/references/link4m-go/` (12 ảnh bạn gửi, chỉ có trên máy local).

## 1. Mục đích

Trang vượt link kiểu link4m có một cách kiếm tiền khác ngoài quảng cáo ở trang chờ: **nhà quảng cáo thuê khách tìm website của họ trên Google**. Khách làm theo hướng dẫn, vào site nhà quảng cáo, bấm "LẤY MÃ", chờ, rồi lấy mã và dán về trang link để đi tiếp. Nhà quảng cáo có lượt truy cập thật từ Google, còn hệ thống (và member có link) được trả tiền cho lượt đó.

Hệ thống phải làm được **cả hai chế độ** trên cùng một trang `/go/{alias}`:
- **Chế độ từ khóa (keyword):** như 12 ảnh của bạn.
- **Chế độ chờ giây (wait):** trang đếm giây có quảng cáo, đã làm xong, chỉ đổi giao diện cho đồng nhất.

## 2. Mục tiêu

1. Chọn chế độ **tự động, ưu tiên từ khóa** (bạn đã chọn): có chiến dịch đang bật thì dùng chế độ từ khóa, không có thì rơi về chế độ chờ giây. Admin có thể ép một chế độ.
2. Quản lý **chiến dịch** trong admin: từ khóa, website nhà quảng cáo, ảnh hướng dẫn, trọng số, giới hạn mỗi ngày, thời gian bật.
3. **Widget** (một file JavaScript) nhà quảng cáo dán vào site của họ để hiện nút LẤY MÃ, đếm giờ hai giai đoạn và hiện mã.
4. Mã sinh ở server, dùng một lần, hết hạn cùng phiên, gắn với đúng thiết bị của khách.
5. Giao diện trang `/go/{alias}` **giống link4m**: ô nhập mã, captcha, đồng hồ phiên, nút tiếp tục, hướng dẫn 6 bước, hộp "Báo lỗi", video hướng dẫn, khung mời đăng ký, chân trang.
6. Captcha dùng **Cloudflare Turnstile** (đã chạy với khóa mẫu trên localhost; khóa thật đặt trong admin Settings khi chạy thật).
7. Có thể thử hết trên máy local, kể cả phía nhà quảng cáo (trang "nhà quảng cáo giả").

## 3. Không làm (non-goals)

- Bảng điều khiển cho nhà quảng cáo tự đăng ký, nạp tiền, xem báo cáo. Admin tạo chiến dịch bằng tay.
- Thanh toán, hóa đơn, tính tiền với nhà quảng cáo.
- Chứng minh khách **thật sự tìm bằng Google** (xem mục 9: chỉ có tín hiệu gợi ý).
- Tự động tạo ảnh hướng dẫn (admin tải lên, thiếu thì dùng ảnh mặc định). Video hướng dẫn chỉ là một đường dẫn admin đặt.

## 4. Không được xảy ra (anti-goals)

- Mã bị đoán được, dùng lại, hoặc dùng cho phiên của người khác.
- Widget phát mã trên một website không thuộc chiến dịch đó.
- Khách tính tiền khi chưa chờ đủ giờ trên site nhà quảng cáo (đo bằng đồng hồ server, không tin đồng hồ trình duyệt).
- Khách bị kẹt vĩnh viễn: hết chiến dịch hoặc lỗi thì vẫn có lối ra (rơi về chế độ chờ giây, hoặc báo lỗi để admin biết).

## 5. Các hướng làm đã cân nhắc

**Chọn chế độ** (bạn đã chọn cách 1):

| Cách | Ưu | Nhược |
|---|---|---|
| 1. Tự động, ưu tiên từ khóa (**chọn**) | Hết chiến dịch không mất doanh thu; admin vẫn ép được | Phức tạp hơn một chút |
| 2. Member chọn theo từng link | Member linh hoạt | Member chọn chế độ rẻ, chiến dịch không có người làm |
| 3. Công tắc cho cả hệ thống | Đơn giản nhất | Không tự rơi về chế độ chờ |

**Cách gắn mã với đúng khách** (chỉ có một hướng khả thi):
- Khách vào site nhà quảng cáo từ **Google**, không phải từ link của chúng ta, nên không truyền được mã phiên qua URL, cookie của hai site cũng không dùng chung. Vì vậy mã gắn với **IP + user-agent** của thiết bị, và phiên ở trang link gắn với đúng chiến dịch.
- Loại: mã không trạng thái (HMAC, không ghi DB): không ép được dùng một lần, hết hạn và giới hạn số mã mỗi ngày. Loại: nhúng iframe của chúng ta vào site nhà quảng cáo: bị trình duyệt chặn cookie bên thứ ba và các tiện ích chặn quảng cáo.

## 6. Thiết kế

### 6.1 Chọn chế độ khi khách mở link

Cài đặt `visit_mode`: `auto` (mặc định), `keyword`, `wait`. Khi tạo phiên (`GET /{alias}`):
- `wait` → chế độ chờ giây.
- `keyword` hoặc `auto` → chọn ngẫu nhiên theo **trọng số** một chiến dịch đủ điều kiện: đang bật, trong khoảng `starts_at` đến `ends_at`, chưa vượt `daily_limit` (số mã đã cấp trong ngày; 0 là không giới hạn). Có chiến dịch thì phiên ghi `mode = keyword` và `campaign_id`; không có thì ghi `mode = wait` (rơi về chế độ chờ giây, kể cả khi admin ép `keyword`).
- Chiến dịch được chốt một lần lúc tạo phiên, không đổi giữa chừng.

### 6.2 Trang `/go/{alias}` ở chế độ từ khóa

Theo ảnh 1 đến 5 (bố cục, tiêu đề tiếng Anh):
- Thanh trên: logo LinkCash và menu (Home). Tiêu đề: "PLEASE ENTER THE CODE TO CONTINUE TO THE DESTINATION".
- Ô "Enter code", captcha Turnstile, dòng "Session time left: N" (giây, đếm ngược theo `code_session_seconds`, mặc định 600), nút "Click here to continue" (xanh nhạt, bấm được khi đã nhập mã và qua captcha).
- Khối hướng dẫn "HOW TO GET THE CODE - KEYWORD SEARCH": ba lưu ý (dùng Chrome, KHÔNG bấm quảng cáo được tài trợ, KHÔNG dùng chế độ ẩn danh), rồi 6 bước: mở google.com; gõ **từ khóa của chiến dịch** (chữ đỏ); bấm website `domain che bớt ký tự` ở trang 1 (ảnh kết quả Google); kéo xuống cuối bài, bấm nút GET CODE và chờ (ảnh minh họa); bấm một link bất kỳ trên site rồi quay lại vị trí đó (ảnh); dán mã vào ô.
- "Can't get the code?" và nút đỏ "Report an error" mở hộp thoại: chọn "Code not shown" / "Page not shown" / "Other", captcha, Close, Send (xem 6.5).
- Khối video hướng dẫn (nhúng đường dẫn YouTube do admin đặt; trống thì ẩn khối), khung "READY TO EARN MONEY?" kèm Sign up và Sign in, chân trang (logo, Home, Payout Rates, Contact).
- **Nguyên tắc giao diện (bạn yêu cầu: dễ nhìn, thân thiện nhưng quen thuộc):** giữ đúng **bố cục và thứ tự khối** của link4m để người dùng quen tay, nhưng làm sạch hơn: màu xanh dương như ảnh, logo LinkCash, thẻ bo góc có đổ bóng nhẹ, chữ lớn dễ đọc, mỗi bước hướng dẫn có số tròn và ảnh minh họa, ô nhập mã và nút lớn dễ bấm trên điện thoại (cao tối thiểu 48 px), đồng hồ phiên có màu đổi sang đỏ khi còn dưới 60 giây, nút tiếp tục xám đến khi đủ điều kiện rồi chuyển xanh lá kèm dòng trạng thái ("Enter the code", "Verify you are human", "Ready"). Không dùng cửa sổ bật lên (pop-up) của chính chúng ta. Giao diện chữ tiếng Anh, bố cục responsive từ 360 px.

### 6.3 Widget trên site nhà quảng cáo (ảnh 7 đến 11)

Nhà quảng cáo dán một dòng: `<script async src="https://DOMAIN/js/widget.js" data-campaign="WIDGET_KEY"></script>`. Widget (JavaScript thuần, không phụ thuộc thư viện vì chạy trên site người khác) hiện ở cuối trang:
1. Nút đỏ "GET CODE". Bấm vào thì đổi thành "Please wait 60s (1/2)" (giây lấy từ server, mặc định `stage1_seconds` = 60) và dòng nhắc "Please click any link and scroll back to this position to get the CODE".
2. Sau khi khách bấm một link nội bộ và quay lại (trang thứ hai nạp widget cùng với trạng thái trong `localStorage`), nút thành "Get code after 15s (2/2)" (`stage2_seconds` = 15).
3. Hết giờ thì hiện nút "Promo code: abc123" kèm biểu tượng copy. Tên "Promo code" lấy theo ảnh 11 ("Mã KM").

Phía server (tất cả `POST`, JSON):
- `/widget/start`: nhận `campaign` (widget key), `referrer` (`document.referrer`), `page_load_id`, `fingerprint`. Kiểm tra header `Origin` thuộc domain của chiến dịch (và chiến dịch đang bật). Tạo một `code_attempt` ghi IP, `ua_hash`, giờ bắt đầu theo đồng hồ server, `referrer_host`. Trả `attempt_token` (ký HMAC, chứa id) và `stage1_seconds`, `stage2_seconds`. Gọi lại với cùng token thì tiếp tục lần thử cũ.
- `/widget/progress`: chỉ chấp nhận khi đã đủ `stage1_seconds` theo đồng hồ server **và** `page_load_id` khác lần `start`. Ghi `progressed_at`.
- `/widget/code`: chỉ chấp nhận khi đã `progress` và đã đủ thêm `stage2_seconds` kể từ `progressed_at` (đồng hồ server). Sinh mã 6 ký tự chữ thường và số, **bỏ các ký tự dễ nhầm** (`0 o 1 l i`), duy nhất trong phạm vi chiến dịch, hết hạn sau `code_session_seconds`. Gọi lại trả đúng mã cũ (không sinh mã thứ hai). Mỗi lần thử tối đa một mã.
- Mọi endpoint `/widget/*` giới hạn tần suất theo IP và trả CORS đúng với `Origin` hợp lệ.

### 6.4 Nhập mã và kết thúc

`POST /links/check-captcha` (đã có) nhận thêm trường `code` khi phiên là chế độ từ khóa:
1. Kiểm tra captcha Turnstile ở server như hiện tại.
2. Tìm `code_attempt` có mã khớp, cùng chiến dịch với phiên, chưa dùng (`used_at` rỗng), chưa hết hạn. Ghi `used_at` và `visit_session_id` trong cùng một giao dịch có khóa dòng (hai request cùng lúc chỉ một cái thắng).
3. Kiểm tra gắn thiết bị: `ua_hash` phải khớp (sai thì từ chối mã). IP khác thì vẫn nhận mã nhưng view bị đánh dấu không hợp lệ (`IpChanged`), khách vẫn tới đích (giống quy tắc đã chốt).
4. Mã sai hoặc không có: trả lỗi, đếm vào `visit_sessions.code_failures`; quá 5 lần sai thì phiên bị khóa (`Failed`) và khách phải mở lại link.
5. Mã hợp lệ thì chạy tiếp chuỗi kiểm tra hiện có (unique 24 giờ, mạng bị chặn, bot...) rồi ghi view bằng `ViewRecorder` như cũ. **Giá view** lấy theo quốc gia và mức của link như chế độ chờ giây (chưa có hệ số riêng).
Thêm hai lý do không hợp lệ: `MissingCode`, `CodeInvalid`.

### 6.5 Báo lỗi

Hộp thoại "Report an error" theo ảnh 6. Gửi cần captcha. Lưu vào `fraud_flags` như `POST /links/report` hiện có (lý do `visitor_report`, kèm lựa chọn và chiến dịch). Endpoint giữ giới hạn 5 lần mỗi phút; thêm bước kiểm tra captcha.

### 6.6 Chế độ chờ giây (đã có, chỉ đổi giao diện)

Cùng bố cục xanh dương, cùng khung quảng cáo. Nút hiện "Please wait 60s (1/2)" (số bước theo mức của link), hết giờ thì "Click here to continue". Logic server không đổi.

### 6.7 Admin

- **Campaigns** (thêm vào menu admin): tạo, sửa, bật tắt, xóa. Trường: tên, từ khóa, domain (để kiểm tra `Origin`), domain hiển thị che bớt (`ecomparad***.in`, tự gợi ý), widget key (tự sinh), trọng số, giới hạn mỗi ngày, ngày bắt đầu và kết thúc, `stage1_seconds`, `stage2_seconds`, ba ảnh hướng dẫn (kết quả Google, ảnh nút GET CODE, ảnh vị trí), ghi chú. Hiện số mã đã cấp, số view hợp lệ và số lỗi báo từ khách của từng chiến dịch.
- **Settings**: `visit_mode` (`auto|keyword|wait`), `code_session_seconds`, `tutorial_video_url`.

### 6.8 Thử trên máy local

- Khóa Turnstile mẫu của Cloudflare đã đặt trong `.env` (luôn cho qua, chỉ dùng được trên localhost).
- Seeder chiến dịch demo (chỉ ở môi trường local) và trang **"nhà quảng cáo giả"** `/demo/advertiser` (chỉ `app()->isLocal()`): một bài viết dài có nhúng widget với chiến dịch demo, để chạy trọn luồng 6 bước trên một máy.

## 7. Dữ liệu mới

- `campaigns`: như mục 6.7, thêm `widget_key` (duy nhất).
- `code_attempts`: `id` (ulid), `campaign_id`, `ip`, `ua_hash`, `fingerprint`, `referrer_host`, `started_at`, `progressed_at`, `page_load_ids` (json), `code` (duy nhất theo chiến dịch, null đến khi cấp), `issued_at`, `expires_at`, `used_at`, `visit_session_id` (null đến khi dùng).
- `visit_sessions`: thêm `mode` (`wait|keyword`), `campaign_id` (null), `code_failures` (mặc định 0).
- `settings`: các khóa ở 6.7.

## 8. Ràng buộc

Laravel 12, jQuery cho mọi JavaScript của trang chúng ta (widget là JS thuần vì chạy trên site người khác), chạy trên hosting chia sẻ (không Redis, không worker thường trực), mọi chữ và code bằng tiếng Anh, theo `mst-laravel-conventions` và `git-workflow`, chỉ dùng các thư viện đã có.

## 9. Điều cần nói thẳng (giới hạn đã biết)

- **Không chứng minh được khách tìm bằng Google.** Widget gửi `document.referrer`, nhưng nhiều trình duyệt ẩn nó và khách có thể vào thẳng site. Hệ thống ghi `referrer_host` để admin xem thống kê và tạo cờ gian lận khi tỷ lệ bất thường; không chặn khách vì thiếu referrer.
- Ràng buộc IP và user-agent chặn được việc đưa mã cho người khác nhưng không chặn một người tự chạy cả hai phía trên cùng thiết bị. Các lớp kiểm tra gian lận đã có (giới hạn theo IP, quét cờ) vẫn áp dụng.
- Giá trị `stage1_seconds` và `stage2_seconds` mặc định theo ảnh (60 và 15) khá dài; admin chỉnh được theo từng chiến dịch.

## 10. Việc để người làm quyết

Cách đặt tên cột và chỉ mục, định dạng ảnh và giới hạn dung lượng, cách che ký tự domain, cách bố trí responsive trên điện thoại, nội dung chữ tiếng Anh của hướng dẫn, cách xử lý khi `localStorage` bị tắt (widget báo "enable storage"), và thứ tự thực hiện (bảng chiến dịch, widget, trang `/go`, admin, trang demo).

## 11. Điểm tôi tự quyết (bạn xem lại)

- Giữ bố cục và màu xanh dương của link4m nhưng làm sạch và thân thiện hơn (mục 6.2), thay logo.
- Mặc định 60 giây và 15 giây (bạn chốt, số tròn); mã 6 ký tự không có ký tự dễ nhầm; quá 5 lần nhập sai thì khóa phiên.
- Giá view ở chế độ từ khóa dùng chung bảng giá theo quốc gia và mức của link.
- IP khác chỉ làm view không hợp lệ, user-agent khác thì từ chối mã.
- Không làm bảng điều khiển cho nhà quảng cáo trong lần này.

## 12. Quyết định sau khi người đọc độc lập kiểm tra (tôi tự quyết, bạn xem lại)

1. **Chế độ từ khóa thay các bước chờ bằng một bước nhập mã.** Phiên `keyword` không dùng token bước (`StepTokenService`) và không đếm số bước theo mức của link. Mức của link chỉ còn dùng để tính giá. Các luật `MissingSteps` và `DwellTooShort` được thay bằng kiểm tra đồng hồ server ở widget (6.3). Các kiểm tra còn lại giữ nguyên: `NoCookie`, `NoJavascript` (request cuối phải gửi vân tay), captcha, `Bot`, `BlockedNetwork`, `Duplicate`, `IpChanged`. Trong code: `VisitFlow` và `ViewValidator` có thêm một nhánh theo `visit_sessions.mode`, không viết lại nhánh chờ giây.
2. **Lỗi khi nhập mã:** `ua_hash` không khớp, mã sai, mã hết hạn đều tính vào `code_failures`. `ua_hash` không khớp **không** làm mất mã. Captcha sai không đụng tới mã. Khóa phiên sau 5 lần sai, và có thêm giới hạn **10 lần sai mỗi giờ cho mỗi IP và chiến dịch** để mở lại link không lách được. Mã hợp lệ đã được chấp nhận thì **bị tiêu thụ ngay**, kể cả khi bước kiểm tra sau đó làm view thành không hợp lệ (view ghi lý do, khách vẫn tới đích).
3. **`daily_limit` đếm mã đã cấp.** Kiểm tra lại ở `/widget/start`: hết hạn mức hoặc chiến dịch bị tắt thì widget báo "campaign unavailable" và không hiện nút. Phía trang link: khi tải trang hoặc khi nhập mã, nếu chiến dịch của phiên không còn khả dụng **và thiết bị này chưa được cấp mã**, phiên tự chuyển sang chế độ chờ giây (khách không bị kẹt).
4. **Dữ liệu phiên đã có sẵn:** `visit_sessions` đã lưu `ip`, `ua_hash`, `fingerprint`, `country`, `level` lúc tạo phiên. Việc so khớp mã dùng đúng các cột này, không cần thêm cột.
5. **Số bước ở chế độ chờ giây:** mức N có đúng N bước (quy tắc chung của dự án, đã làm ở `VisitFlow`).
6. **Vân tay:** vân tay ở widget chỉ lưu để admin phân tích, **không** dùng để so khớp (hai site khác nguồn, không chia sẻ được). Vân tay của request cuối ở trang link vẫn dùng cho khóa chống trùng 24 giờ như hiện tại.

## 13. Rủi ro kinh doanh cần bạn biết trước khi bán (không chặn việc làm)

Cơ chế này (người dùng được trả tiền để tìm từ khóa, ở lại site, cuộn và bấm thêm bài) tạo tín hiệu hành vi giả cho Google. Đây là kiểu "thao túng kết quả tìm kiếm" mà chính sách chống spam của Google không cho phép, nên:
- Site nhà quảng cáo có thể bị Google phạt; mạng quảng cáo của bạn (nếu có) cũng có thể từ chối hoặc khóa tài khoản khi phát hiện.
- Khuyến nghị đặt vào điều khoản của sản phẩm: nhà quảng cáo tự chịu trách nhiệm về nội dung site và về việc tuân thủ chính sách của các bên; admin duyệt từng chiến dịch. Ảnh mẫu bạn gửi là site cá cược, trong khi điều khoản của LinkCash cấm nội dung cờ bạc và 18+: nên có quy tắc duyệt chiến dịch rõ ràng. Bản demo và dữ liệu seed của mình dùng site giả, không phải site cá cược.

## 14. Mô hình doanh thu (theo mô tả của bạn) và cách thiết kế này đáp ứng

| Mô hình link4m | Trong thiết kế này |
|---|---|
| Mạng quảng cáo bên thứ ba (hiển thị, banner, pop-up) | Đã có: vị trí quảng cáo dán mã của mạng bất kỳ (Task 19), hiện ở trang chờ giây và trang nhập mã (Top, Bottom). Mã quảng cáo pop-up của mạng thứ ba dán được, nhưng chính LinkCash không tự làm pop-up. |
| Dịch vụ SEO / đẩy từ khóa (nhà quảng cáo trả tiền trực tiếp) | Chế độ từ khóa trong tài liệu này (chiến dịch, widget, mã). Việc nhà quảng cáo trả tiền cho bạn nằm ngoài hệ thống (admin tạo chiến dịch bằng tay). |
| Retargeting và affiliate dựa trên cookie | Do mạng quảng cáo tự xử lý bên trong mã dán vào vị trí quảng cáo; LinkCash không cần làm thêm. Lưu ý: nếu dùng cookie quảng cáo thì cần thông báo cookie ở trang vượt link (nên thêm vào điều khoản và chính sách bảo mật). |
