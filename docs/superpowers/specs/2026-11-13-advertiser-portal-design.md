# Cổng Advertiser (nhà quảng cáo): thiết kế

Trạng thái: bản nháp chờ chủ dự án duyệt. Ngày: 2026-11-13. Tài liệu viết tiếng Việt để bạn đọc; mọi thứ trong repo (code, comment, tên bảng, chữ trên giao diện, thông báo lỗi) vẫn là tiếng Anh.

Tài liệu này **thay đổi một non-goal** của `2026-11-13-keyword-code-flow-design.md` (mục 3: "Bảng điều khiển cho nhà quảng cáo tự đăng ký, nạp tiền, xem báo cáo. Admin tạo chiến dịch bằng tay"). Từ nay advertiser tự làm các việc đó; admin vẫn tạo được chiến dịch bằng tay như cũ.

## 1. Mục đích

Nhà quảng cáo tự đăng ký tài khoản, tự tạo chiến dịch từ khóa, nạp tiền vào ví (chuyển khoản ngân hàng qua SePay hoặc PayPal) và theo dõi chiến dịch. Họ thấy **mọi lượt** phát sinh, kể cả lượt không tính tiền, nhưng chỉ thấy trạng thái *Counted* hoặc *Not counted*, **không thấy lý do** (lý do chỉ admin xem, như hiện nay). Mỗi lượt hợp lệ trừ tiền ví của advertiser và (như đã có) sinh thu nhập cho publisher.

## 2. Mục tiêu

1. Vai trò mới `advertiser`, **tách hẳn** khỏi member (publisher): đăng ký riêng, giao diện riêng, ví riêng. Một người muốn làm cả hai thì dùng hai tài khoản.
2. Advertiser tạo chiến dịch bằng form một trang; chiến dịch ở trạng thái chờ duyệt cho đến khi admin duyệt.
3. Ví advertiser tính bằng USD, nạp bằng SePay (VND, quy đổi theo tỷ giá admin đặt) hoặc PayPal.
4. Trừ tiền theo **giá mỗi lượt hợp lệ**, admin đặt mức mặc định và có thể chỉnh riêng từng chiến dịch.
5. Trang theo dõi chiến dịch dạng phễu (bố cục B đã chốt), bảng các lượt, biểu đồ theo ngày.
6. Admin duyệt/từ chối chiến dịch, xem ví và lịch sử nạp của advertiser, chỉnh giá.

## 3. Không làm (non-goals)

- Một tài khoản có hai vai trò.
- Đấu giá, chọn gói số lượt, ngân sách riêng từng chiến dịch (ví là một, chung cho mọi chiến dịch của advertiser).
- Hóa đơn VAT, rút tiền ngược về cho advertiser (chỉ nạp và tiêu; hoàn tiền thủ công do admin xử lý ngoài hệ thống).
- Hiển thị lý do một lượt không tính tiền cho advertiser.
- Đổi cơ chế widget, mã, captcha của chế độ từ khóa (giữ nguyên).

## 4. Không được xảy ra (anti-goals)

- Advertiser thấy lý do chi tiết của lượt không tính (lộ cách chống gian lận).
- Nạp tiền bị cộng hai lần (lệnh đồng bộ chạy chồng nhau, người dùng bấm hai lần).
- Ví bị âm vì hai lượt ghi cùng lúc, hoặc chiến dịch vẫn phát mã khi ví hết tiền.
- Chiến dịch chưa được duyệt, đã bị từ chối hoặc của advertiser bị khóa vẫn chạy.
- Advertiser xem hoặc sửa dữ liệu chiến dịch, ví của advertiser khác.
- Số dư publisher bị lẫn với ví advertiser (vì tách tài khoản nên chỉ cần đảm bảo vai trò quyết định các màn và quyền).

## 5. Các hướng làm đã cân nhắc

**Mô hình ví:**

| Cách | Ưu | Nhược |
|---|---|---|
| 1. Dùng lại `users.balance_micro` và `transactions` (**chọn**) | Ít bảng mới, `WalletService` và lịch sử có sẵn | Một bảng giao dịch chứa cả hai loại, phân biệt bằng `action` |
| 2. Bảng ví và sổ cái riêng cho advertiser | Tách sạch dòng tiền | Viết lại số dư, khóa dòng, lịch sử |
| 3. Trừ tiền cuối ngày bằng lệnh định kỳ | Ít ghi DB hơn | Ví có thể âm giữa ngày, khó chạy đúng trên hosting chia sẻ |

**Cách tính tiền** (chọn A): A. ví chung + giá mỗi lượt hợp lệ; B. ngân sách riêng từng chiến dịch; C. gói số lượt. Chọn A vì đơn giản và hợp với hệ thống hiện có.

**Giao diện tạo chiến dịch** (chọn A): form một trang, nạp ví ở trang Wallet riêng, thay vì wizard 3 bước.

**Trang theo dõi** (chọn B): phễu hành trình (Started, Code issued, Counted, Not counted) cùng thẻ chi tiêu/số dư/hạn mức hôm nay, biểu đồ theo ngày và bảng lượt; thay vì dashboard thẻ số liệu thuần.

## 6. Thiết kế

### 6.1 Vai trò và đăng ký

- `UserRole` thêm `Advertiser`. Đăng ký advertiser ở form riêng (`/advertiser/signup`, cùng giao diện auth hiện có), đăng nhập dùng chung màn đăng nhập; sau đăng nhập điều hướng theo vai trò (member vào `member.dashboard`, advertiser vào `advertiser.dashboard`, admin như cũ).
- Nhóm route `/advertiser/*` đi qua `auth`, `active` và một middleware kiểm tra vai trò advertiser. Member không vào được, advertiser không vào được khu member hay admin.
- Layout riêng `layouts/advertiser.blade.php` (AdminLTE như khu member, menu: Overview, Campaigns, Wallet, Profile).
- Advertiser không có link rút gọn, referral, rút tiền; các tính năng đó chỉ có ở member.

### 6.2 Chiến dịch của advertiser

- `campaigns` thêm `user_id` (null nếu admin tạo) và `review_status` (`pending`, `approved`, `rejected`) cùng `review_note` (admin ghi lý do từ chối, advertiser thấy được lý do **duyệt**, khác với lý do của từng lượt). `is_active` vẫn là công tắc bật/tắt (advertiser tự bật/tắt chiến dịch đã duyệt).
- Form tạo: tên, từ khóa, domain website, hạn mức mỗi ngày (0 = không giới hạn), ngày bắt đầu/kết thúc (tùy chọn), ba ảnh hướng dẫn (tùy chọn, thiếu thì dùng ảnh mặc định), ghi chú. Các giá trị còn lại (trọng số, stage1/stage2, domain hiển thị che bớt, widget key) lấy mặc định hoặc tự sinh như admin form hiện có.
- Sau khi gửi: `pending`, chưa chạy. Admin duyệt thì `approved` và advertiser thấy đoạn mã dán widget. Từ chối thì `rejected` kèm `review_note`; advertiser sửa và gửi lại thì về `pending`. Sửa từ khóa hoặc domain của chiến dịch đã duyệt thì quay về `pending`.
- `Campaign::scopeAvailable` (cái `CampaignSelector` và `/widget/*` đang dùng) thêm điều kiện: nếu có chủ sở hữu thì `review_status = approved`, chủ sở hữu đang `active`, và ví còn đủ để trả ít nhất một lượt (xem 6.4). Chiến dịch admin tạo (không có chủ) không bị ảnh hưởng.

### 6.3 Ví và nạp tiền

- Số dư là `users.balance_micro` của tài khoản advertiser, đơn vị USD micro như hệ thống hiện tại. `processing_micro` không dùng cho advertiser.
- `transactions` dùng thêm các `action` mới trong `TransactionAction`: `Topup` (loại cộng), `CampaignCharge` (loại trừ), `ChargeRefund` (loại cộng). Hiển thị lịch sử ở trang Wallet.
- Bảng `top_ups`: `user_id`, `provider` (`sepay`, `paypal`), `reference` (mã đối soát hoặc order id, duy nhất theo provider), `amount_micro` (USD), `original_amount` và `original_currency` (VND hoặc USD), `exchange_rate` (cho SePay), `status` (`pending`, `completed`, `failed`), `completed_at`, `raw` (json phản hồi để đối soát). Cộng ví chỉ xảy ra khi chuyển `pending` sang `completed`, trong giao dịch DB có khóa dòng, nên gọi lại không cộng thêm.
- **SePay (đồng bộ bằng cronjob, không dùng webhook):** trang Wallet cho advertiser nhập số tiền VND (có mức tối thiểu do admin đặt), hệ thống tạo bản ghi `top_ups` `pending` với mã nội dung chuyển khoản riêng (ví dụ `LC` + id), hiện QR và thông tin tài khoản. Lệnh `sepay:sync` chạy mỗi phút qua `schedule:run` (cron của hosting), gọi API SePay lấy các giao dịch tiền vào gần đây (token trong `.env`), khớp mã trong nội dung chuyển khoản và số tiền, rồi đánh dấu hoàn tất và cộng ví theo tỷ giá đã khóa. Chuyển thiếu/thừa, tiền về sau khi lệnh hết hạn hoặc không khớp: đánh dấu `mismatch` để admin xử lý tay, không tự cộng. Lệnh không bao giờ ném lỗi khi API SePay hỏng (chỉ ghi log và thử lại ở lần chạy sau). Advertiser thấy ví được cộng sau vài phút.
- **PayPal:** REST API Orders v2 bằng HTTP client có sẵn (không thêm thư viện). Advertiser chọn số tiền USD, hệ thống tạo order, chuyển sang PayPal để duyệt, quay về route return thì capture; chỉ khi capture trả `COMPLETED` mới cộng ví. Key đặt trong `.env`: `PAYPAL_CLIENT_ID`, `PAYPAL_SECRET`, `PAYPAL_MODE` (`sandbox` hoặc `live`); bạn tự điền key.
- Settings mới: tỷ giá VND→USD, mức nạp tối thiểu, giá mặc định mỗi lượt hợp lệ, thông tin tài khoản ngân hàng hiện trên QR.

### 6.4 Trừ tiền

- Giá mỗi lượt hợp lệ: `campaigns.price_micro` (null thì dùng giá mặc định ở Settings).
- Khi `ViewRecorder` ghi một view **hợp lệ** (không phải `Invalid`) của phiên chế độ từ khóa có chiến dịch của advertiser, trong cùng giao dịch DB: khóa dòng user advertiser, trừ giá, tạo `transactions` `CampaignCharge`, ghi `views.campaign_id` và `views.advertiser_cost_micro`. View `Invalid` ghi `campaign_id` nhưng chi phí bằng 0.
- Nếu số dư không đủ trả lượt này: **không** trừ, view vẫn hợp lệ cho publisher (tiền của publisher không phụ thuộc ví lúc đó) nhưng chiến dịch bị tạm dừng tự động. Để hạn chế tình huống này, `scopeAvailable` yêu cầu số dư ≥ giá một lượt (kiểm tra tại `/widget/start` và lúc chọn chiến dịch); số lượt đang bay có thể làm số dư về sát 0, không bao giờ âm.
- View bị hủy sau đó (`Cancelled`, ví dụ cờ gian lận được xử lý) thì hoàn lại `advertiser_cost_micro` bằng `ChargeRefund`.
- Chiến dịch tự tạm dừng vì hết tiền thì hiện cảnh báo trên trang chiến dịch và trang Overview; nạp tiền xong tự chạy lại nếu `is_active` vẫn bật.

### 6.5 Màn hình advertiser (tiếng Anh)

- **Overview:** số dư ví, chi tiêu hôm nay/7 ngày, số chiến dịch theo trạng thái, nút Top up và New campaign, cảnh báo ví sắp hết.
- **Campaigns:** danh sách (tên, từ khóa, trạng thái duyệt, bật/tắt, lượt hôm nay, đã chi) và form tạo/sửa một trang.
- **Campaign detail (bố cục B):** thanh tiêu đề với trạng thái và nút Pause/Resume; phễu `Started` (số `code_attempts`), `Code issued`, `Used - Counted`, `Used - Not counted`; thẻ Spent, Wallet left, Today / daily limit; biểu đồ theo ngày (Started, Issued, Counted); bảng lượt gần đây có bộ lọc All/Counted/Not counted (thời gian, quốc gia, trạng thái, chi phí; **không** hiện lý do, IP đầy đủ hay vân tay). Đoạn mã widget hiện khi đã duyệt.
- **Wallet:** số dư, hai cách nạp (SePay, PayPal), lịch sử nạp và giao dịch.
- **Profile:** đổi mật khẩu, email, tên công ty (dùng lại các trang đang có nếu phù hợp).
- Mọi thứ cho phép phân trang và lọc theo khoảng ngày; số liệu theo múi giờ cài đặt của ứng dụng.

### 6.6 Admin

- Menu Campaigns hiện có: thêm cột chủ sở hữu và trạng thái duyệt, bộ lọc "Pending review", nút duyệt/từ chối (kèm ghi chú).
- Mục mới **Advertisers**: danh sách advertiser, số dư, tổng đã nạp/đã chi, khóa/mở tài khoản; xem lịch sử nạp và giao dịch của từng người. Lượt chi tiết vẫn hiện lý do như hiện nay ở trang admin.
- Settings: các khóa ở 6.3.

## 7. Dữ liệu mới và thay đổi

- `users`: không thêm cột (dùng `role`, `balance_micro`); tùy chọn thêm `company_name` nếu cần.
- `campaigns`: `user_id` (null), `review_status`, `review_note`, `price_micro` (null).
- `views`: `campaign_id` (null), `advertiser_cost_micro` (mặc định 0), chỉ mục theo `campaign_id` và thời gian.
- `top_ups`: như 6.3.
- `settings`: các khóa ở 6.3 và 6.4.
- `TransactionAction`: `Topup`, `CampaignCharge`, `ChargeRefund`. `UserRole`: `Advertiser`.

## 8. Ràng buộc

Laravel 12, jQuery cho JavaScript của trang chúng ta, hosting chia sẻ (không Redis, không worker thường trực), mọi chữ và code bằng tiếng Anh, theo `mst-laravel-conventions` và `git-workflow`, **không thêm thư viện** (PayPal và SePay gọi bằng HTTP client có sẵn). Có test Pest cho: đăng ký và phân quyền theo vai trò, duyệt chiến dịch, lệnh đồng bộ SePay (chống nạp trùng, sai mã, sai số tiền), capture PayPal, trừ tiền/hoàn tiền/hết ví, và việc advertiser không thấy lý do và không thấy dữ liệu của người khác.

## 9. Điều cần nói thẳng (giới hạn đã biết)

- Rủi ro chính sách của chế độ từ khóa (mục 13 của spec cũ) vẫn nguyên. Duyệt từng chiến dịch là lớp bảo vệ chính; nên có điều khoản cho advertiser (cấm cờ bạc/18+, tự chịu trách nhiệm về site) hiển thị khi đăng ký.
- Số liệu phễu "Started" đếm số lượt mở widget, có thể bị bot làm phình; đó là số của luồng, không phải số tính tiền.
- Chênh lệch nhỏ giữa các thời điểm (ví về sát 0 trong lúc nhiều lượt cùng ghi) được xử lý bằng khóa dòng, nhưng chiến dịch có thể chạy thêm vài lượt trước khi bị tạm dừng.
- Hoàn tiền cho advertiser (rút ví về ngân hàng/PayPal) chưa có; admin điều chỉnh số dư thủ công nếu cần.

## 10. Việc để người làm quyết

Tên route, tên cột chính xác và chỉ mục, giao diện form lỗi/thành công, định dạng mã nội dung chuyển khoản SePay, mức tối thiểu mặc định, cách vẽ biểu đồ phễu (CSS thuần hoặc thư viện đã có sẵn trong dự án), thứ tự thực hiện (vai trò và auth, schema, ví và nạp tiền, trừ tiền trong `ViewRecorder`, màn advertiser, admin).

## 11. Điểm tôi tự quyết (bạn xem lại)

- Duyệt từng chiến dịch trước khi chạy; sửa từ khóa/domain của chiến dịch đã duyệt thì phải duyệt lại.
- Ví USD, SePay quy đổi theo tỷ giá admin đặt; không dùng ví VND riêng.
- Giá mỗi lượt: mặc định ở Settings, chỉnh riêng từng chiến dịch.
- Trừ tiền ngay khi view hợp lệ được ghi; hoàn tiền khi view bị hủy; hết ví thì tạm dừng chiến dịch, không làm ảnh hưởng thu nhập publisher của lượt đã ghi.
- Advertiser thấy lý do **duyệt/từ chối chiến dịch** (do admin ghi tay) nhưng không thấy lý do của từng lượt.
- Nạp tối thiểu và thông tin ngân hàng đặt trong Settings.

## 12. Quyết định sau khi người đọc độc lập kiểm tra (tôi tự quyết, bạn xem lại)

1. **Dọn dữ liệu cũ:** `PurgeOldViews` **không xóa** view có `campaign_id` của advertiser (giữ để số liệu phễu, bảng lượt và chứng cứ trừ tiền không co lại). Mọi đường hủy view đều hoàn tiền qua một service duy nhất (ví dụ `AdvertiserBilling::refund`): gồm cả `LinkBanService`, hiện đang hủy hàng loạt bằng `update()` và phải được sửa để gọi service này.
2. **Lượt khi ví không đủ:** vẫn hiện là *Counted* với chi phí 0, vẫn tính vào số liệu của publisher, và chiến dịch bị tạm dừng ngay sau đó. Cột `advertiser_cost_micro = 0` cùng `campaign_id` đủ để phân biệt, không cần cờ riêng.
3. **Tạm dừng vì hết tiền là trạng thái suy ra**, không lưu cột. `scopeAvailable` kiểm tra số dư ≥ giá một lượt; trang advertiser hiện nhãn "Paused: insufficient balance" khi `is_active` bật nhưng ví không đủ. Nạp tiền xong tự chạy lại, không cần hook.
4. **SePay:** tỷ giá được **khóa lúc tạo bản ghi `top_ups`** (lưu `exchange_rate` và `amount_micro` trên dòng pending). Bản ghi pending hết hạn sau 24 giờ. Tiền về sau khi hết hạn hoặc sai số tiền/mã được đánh dấu `mismatch`, hiện trong mục Advertisers của admin để xử lý tay (cộng ví bằng điều chỉnh hoặc bỏ qua).
5. **Đăng ký:** dùng lại hành vi đăng ký member hiện có (chưa có xác minh email). Điều khoản (cấm cờ bạc, 18+) là ô tick bắt buộc, lưu `terms_accepted_at` trên `users`.
6. **Advertiser bị khóa hoặc chiến dịch quay về `pending` giữa chừng:** mã đã cấp nhưng chưa dùng **không** được chấp nhận ở bước nhập mã nữa; phiên rơi về chế độ chờ giây theo quy tắc ở mục 12.3 của spec cũ.

## 13. Quan hệ giữa giá advertiser và thu nhập publisher (bạn đã chọn)

Giá mỗi lượt của advertiser là **một số phẳng** (mặc định ở Settings, chỉnh riêng từng chiến dịch), không phụ thuộc quốc gia hay mức link. Biên lợi nhuận do admin tự lo. Trang Settings và form giá của chiến dịch hiện **cảnh báo** (không chặn) khi giá thấp hơn mức thu nhập publisher cao nhất trong `cpm_rates`.

## 14. Thay đổi sau khi duyệt: SePay dùng cronjob

Bạn yêu cầu bỏ webhook SePay, thay bằng cronjob. Mục 6.3 đã được sửa: không còn route `POST /webhooks/sepay` và không còn khóa API ở header; thay bằng lệnh `sepay:sync` chạy mỗi phút và biến môi trường `SEPAY_API_TOKEN`. Quy tắc `mismatch`, khóa tỷ giá lúc tạo và hạn 24 giờ giữ nguyên; bản ghi quá hạn chưa trả được đánh dấu `failed`. Hosting phải có cron chạy `php artisan schedule:run` mỗi phút.
