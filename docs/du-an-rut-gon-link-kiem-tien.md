---
title: Dự án rút gọn link kiếm tiền
subtitle: Đề xuất ý tưởng làm và bán mã nguồn Laravel chạy trên cPanel
kicker: Đề xuất
author: Phạm Hoàng Tuấn
version: 1.0
status: Bản nháp
date: 12/11/2026
toc: true
---

# Dự án rút gọn link kiếm tiền

## Tóm tắt

Dự án đề xuất xây một bộ mã nguồn website rút gọn link kiếm tiền bằng Laravel, cài được trên hosting cPanel thông thường, rồi bán license cho người muốn tự mở trang kiểu Link4M hay ShrinkMe. Nhóm chỉ làm và bán source; người mua tự vận hành, nhận tiền từ mạng quảng cáo và trả tiền cho người tạo link.

> [!NOTE]
> Dự án đang ở giai đoạn ý tưởng, chưa có sản phẩm; tính năng, giá bán và thời gian trong tài liệu đều là kế hoạch và giả định.

- **Sản phẩm dự kiến:** một file zip gồm mã nguồn, trình cài đặt trên web, trang quản trị, tài liệu và video hướng dẫn; không cần VPS (máy chủ ảo riêng), Redis hay SSH.
- **Khách hàng:** người làm MMO (kiếm tiền online), chủ nhóm hoặc kênh có traffic muốn có trang rút gọn link riêng, và người muốn kinh doanh dịch vụ rút gọn link.
- **Điểm khác biệt dự kiến:** giao diện và tài liệu tiếng Việt, thanh toán nội địa, chạy mượt trên hosting giá rẻ, bộ lọc view chống gian lận tích hợp.
- **Thời gian:** khoảng 1 tháng, bắt đầu ngày 17/11/2026, gồm cả kiểm thử, với 2 dev dùng Claude Code để có bản đầu bán được.

## Các website tham khảo

Thị trường Việt Nam đang chạy hai kiểu: trang nhiệm vụ nhiều bước, giá tính theo đồng mỗi view, và Direct qua trang quảng cáo, giá tính theo USD mỗi 1.000 view. Bảng 1 lấy từ [tổng hợp của Blog DTA](https://blogdta.com/top-10-website-rut-gon-link-kiem-tien-uy-tin-hieu-qua-o-viet-nam-current_date-formatm-y/) (cập nhật 20/10/2026) và [trang giá của ShrinkMe](https://shrinkme.io/payout-rates); mức trả do từng trang tự quy định và thay đổi theo thời điểm.

| Website | Kiểu | Thanh toán | Rút tối thiểu | Điều đáng học theo |
|---------|------|------------|---------------|--------------------|
| Link4M | Nhiệm vụ | MoMo, ngân hàng, PayPal | 35 USD | Duyệt nguồn traffic tài khoản mới trong 30 ngày, các lệnh sau trả không quá 12 ngày |
| Phiên Chợ Số | Nhiệm vụ | USDT BEP20, ngân hàng, thẻ cào | 5 USD | Duyệt rút 5–7 ngày; cấm nguồn 18+ và cờ bạc |
| XTASK | Nhiệm vụ, Direct | USDT BEP20, ngân hàng, MoMo | 5 USD | Duyệt rút trong 1–4 giờ; giá tách theo số bước |
| TrafficVN, Traffic4K, VietUser | Nhiệm vụ, Direct | USDT BEP20 | 10 USD | Giá tách theo nguồn traffic, 400–500 đồng mỗi view; trả trong 24–48 giờ |
| Site2S | Nhiệm vụ, Direct | MoMo, ngân hàng | 5 USD | Admin duyệt nguồn qua Telegram trước khi nhận người tạo link |
| ShortDL | Direct | MoMo, ngân hàng, PayPal, USDT | 5 USD | 7 USD mỗi 1.000 view tại Việt Nam |
| DropLink | Direct | MoMo, ngân hàng, PayPal | 2 USD | 7 USD mỗi 1.000 view tại Việt Nam |
| LinkForEarn | Direct nhiều cấp | Ngân hàng, MoMo, FaucetPay | 0,1 USD | Người tạo link tự chọn cấp Direct, mỗi cấp một mức giá |
| ShrinkMe | Direct | PayPal, Paytm, UPI | 5 USD | Thưởng đăng ký, trả hằng ngày, hoa hồng giới thiệu trọn đời, có API |

Bảng: Các website rút gọn link kiếm tiền tham khảo

Các tên khác cùng thị trường gồm Link1s, 123link, Bom.so, Mmo1s, Megaurl, Traffic68 và LINKTOP.ONE. ShrinkMe cũng công bố điều kiện để một view được tính: khách duy nhất trong 24 giờ, ở lại trang ít nhất 10 giây, bật JavaScript và cookie, và đi tới trang đích. Bộ source nên dùng chính bộ điều kiện này làm mặc định và cho quản trị viên chỉnh từng thông số.

## Các bộ source đang bán

Trên CodeCanyon đã có nhiều script rút gọn link bán theo license, và hai trong số đó chạy được trên cPanel. Bảng 2 liệt kê bốn bộ tiêu biểu và điều đáng học từ mỗi bộ.

| Bộ source | Công nghệ | Giá và doanh số | Điều đáng học |
|-----------|-----------|-----------------|---------------|
| [phpShort](https://codecanyon.net/category/php-scripts/miscellaneous?term=stats) | Laravel, PHP 8.x, MySQL | 59 USD, 969 lượt bán | Nhu cầu mua source Laravel vẫn lớn, cần cập nhật đều |
| [Premium URL Shortener](https://gempixel.com/docs/premium-url-shortener) | PHP 8.2+, MySQL 5.7–8.2 | Giấy phép Regular và Extended | Bộ tính năng đầy đủ nhất: trình cài đặt web, tự cập nhật, trang Cron Jobs, plugin cổng thanh toán |
| [LteUrl](https://codecanyon.net/item/lteurl-link-shortener-reactjs-nodejs/50989338) | Laravel, Inertia.js, React | 25 lượt bán | Ghi rõ chạy được trên cPanel và có dịch vụ cài đặt kèm |
| [Shortny](https://codecanyon.net/category/php-scripts/miscellaneous?term=stats) | PHP 7.x, MySQL 5.x | 29 USD, 381 lượt bán | Script cũ vẫn có người mua nhờ giá thấp và dễ cài |

Bảng: Các bộ source rút gọn link đang bán trên CodeCanyon

### Khoảng trống thị trường

Trong phần tài liệu đã đọc, các bộ trên thiên về công cụ rút gọn và quản lý link thương hiệu. Chưa thấy mô hình chia tiền theo 1.000 view cho người tạo link kèm bảng giá theo quốc gia và thanh toán nội địa. Đây là khoảng trống mà bản tiếng Việt có thể lấp, cần kiểm chứng thêm bằng việc hỏi thử người làm MMO trước khi xây.

### Điều nên học theo

Danh sách dưới đây tổng hợp các điểm mạnh của những bộ source trên mà bản của nhóm nên có.

- **Trình cài đặt trên web:** kiểm tra phiên bản PHP và extension, nhập thông tin MySQL, tạo tài khoản admin, hiện hướng dẫn thêm cron.
- **Tự cập nhật:** dùng mã mua hàng kèm changelog rõ ràng; đây là lý do để khách gia hạn.
- **Trang Cron Jobs trong admin:** liệt kê sẵn từng lệnh để dán vào cPanel.
- **Tuỳ biến an toàn:** dịch ngôn ngữ và theme con ngay trong admin, không mất khi cập nhật.
- **Phân quyền:** vai trò cho nhân viên của người mua, như duyệt rút tiền và kiểm duyệt link.
- **Plugin:** cổng thanh toán và tính năng phụ làm dạng plugin, bán riêng.
- **Giấy phép hai hạng:** Regular cho một trang không thu phí người dùng cuối, Extended cho dịch vụ có thu phí.

## Mô hình kinh doanh làm và bán source

Doanh thu của nhóm đến từ bán license, dịch vụ cài đặt và gia hạn cập nhật. Không phát sinh chi phí quảng cáo, máy chủ vận hành hay quỹ trả tiền người dùng, vì phần đó thuộc về người mua. Bảng 3 đề xuất các gói bán.

| Gói | Nội dung | Giá đề xuất |
|-----|----------|-------------|
| Starter | Mã nguồn, 1 tên miền, tài liệu và video cài đặt, cập nhật 6 tháng | 2–3 triệu đồng |
| Pro | Starter, cài đặt giúp lên hosting của khách, 1 theme riêng, hỗ trợ 12 tháng | 4–5 triệu đồng |
| Business | Pro, nhiều tên miền, đổi giao diện theo thương hiệu, cổng thanh toán riêng | 8–10 triệu đồng |
| Gia hạn cập nhật | Bản vá bảo mật, tính năng mới, hỗ trợ | 30% giá gói mỗi năm |
| Dịch vụ lẻ | Cài đặt trên cPanel, chuyển hosting, theme, plugin | 300–500 nghìn đồng mỗi lần cài |

Bảng: Các gói bán source và giá đề xuất

> [!NOTE]
> Giá là giả định khởi điểm, đặt cạnh mức 25–59 USD của các script quốc tế; nên bán thử cho 5–10 khách đầu để chốt giá.

### Giá trị khách mua nhận được

Lợi nhuận của người vận hành bằng số view hợp lệ nhân với hiệu số giữa doanh thu quảng cáo và mức trả cho người tạo link trên 1.000 view, trừ hosting và chi phí khác. Ví dụ minh hoạ: với 1.000.000 view hợp lệ mỗi tháng, doanh thu quảng cáo 4 USD mỗi 1.000 view là 4.000 USD, trả người tạo link 2,5 USD mỗi 1.000 view là 2.500 USD, còn khoảng 1.500 USD trước chi phí hosting và nhân sự. Đây là con số giả định để giải thích cách tính; tài liệu bán hàng nên trình bày công thức và để khách tự điền số của họ, không cam kết thu nhập.

### Nguồn thu phụ

Ngoài license, nhóm có thêm ba nguồn thu nhỏ từ chính bộ source.

- **Theme:** giao diện và bộ trang vượt link mẫu bán riêng.
- **Plugin:** cổng thanh toán, thông báo Telegram, tích hợp thêm mạng quảng cáo.
- **Tuỳ biến:** nhận làm theo yêu cầu cho gói Business.

## Tính năng của bộ source

Bộ source dự kiến có ba mặt: trang vượt link cho khách, bảng điều khiển cho người tạo link và trang quản trị cho người mua source. Bản đầu (MVP, sản phẩm khả dụng tối thiểu) cần đủ cả ba và mọi thông số chỉnh được từ trang quản trị; Bảng 4 chia tính năng theo hai mức.

| Khu vực | Bản đầu | Bản mở rộng |
|---------|---------|-------------|
| Trang vượt link | Direct 1–3 bước, đếm ngược, ô dán mã quảng cáo, Turnstile hoặc hCaptcha ở bước cuối | Nhiều mẫu giao diện, số bước theo quốc gia hoặc thiết bị |
| Người tạo link | Đăng ký, rút gọn, alias, chọn cấp, thống kê thu nhập, link giới thiệu, yêu cầu rút tiền | API, rút gọn hàng loạt, tên miền riêng, 2FA |
| Bảng giá | Giá theo quốc gia, thiết bị và cấp; thưởng đăng ký; hoa hồng giới thiệu | Khuyến mãi theo thời gian, giá riêng từng người dùng |
| Thanh toán | Ngưỡng rút, ngày chờ duyệt, duyệt tay, xuất CSV; ngân hàng, MoMo, USDT BEP20, thẻ cào, PayPal | Mã VietQR cho lệnh chi, thông báo Telegram và email, lịch trả cố định |
| Quản trị | Duyệt người dùng và nguồn traffic, khoá link, danh sách chặn, cấu hình quảng cáo, nhật ký gian lận | Phân quyền, báo cáo doanh thu và chi phí, tự cập nhật, plugin |
| Nội dung và tuân thủ | Điều khoản, danh mục cấm, nút báo cáo vi phạm hoặc DMCA, thông báo hệ thống | Đa ngôn ngữ trong admin, trang blog và FAQ |

Bảng: Tính năng của bộ source theo bản đầu và bản mở rộng

Trang vượt link cần giữ tổng thời gian chờ ở mức vừa phải, nút "Tiếp tục" rõ ràng, quảng cáo đặt đúng vị trí quảng cáo và không giả làm nút bấm. Trang dễ dùng giữ tỷ lệ hoàn thành cao, và đó cũng là điều người mua source quan tâm.

## Kiến trúc Laravel trên cPanel

Toàn bộ hệ thống là một ứng dụng Laravel dùng MySQL, cache dạng file hoặc database và cron của cPanel. Không cần Redis, VPS hay dịch vụ chạy nền, nên người mua chỉ cần một gói hosting thông thường. Hình 1 minh hoạ các thành phần và luồng dữ liệu.

![Kiến trúc hệ thống: khách, Cloudflare, hosting cPanel với Laravel, MySQL, cron và các dịch vụ ngoài](images/kien-truc-cpanel.png)

Bảng 5 nêu lựa chọn công nghệ cho từng thành phần.

| Thành phần | Lựa chọn | Lý do |
|------------|----------|-------|
| Backend | Laravel bản ổn định mới nhất hỗ trợ PHP 8.2+ | Đúng yêu cầu dự án, cùng mức yêu cầu với các script cùng loại |
| Giao diện | Blade, Alpine.js, Tailwind build sẵn thành file tĩnh | Người mua không cần cài Node |
| Cơ sở dữ liệu (CSDL) | MySQL 5.7+ hoặc MariaDB | Có sẵn trên mọi hosting cPanel |
| Cache | Driver file hoặc database | Lưu bản đồ alias sang link đích và bảng giá |
| Hàng đợi | Driver database, cron chạy `schedule:run` mỗi phút | Thay cho tiến trình nền mà hosting chia sẻ không cho chạy lâu |
| Ghi view | Ghi theo lô, gộp theo ngày bằng cron, dọn view thô sau 30–90 ngày | Giữ bảng view nhỏ để hosting chạy nhanh |
| Đóng gói | File zip có sẵn thư mục vendor, `.htaccess` ở gốc trỏ vào `public` | Không cần Composer hay SSH |
| Cài đặt | Trình cài đặt web kiểm tra môi trường, tạo `.env`, chạy migrate, tạo admin | Người không chuyên vẫn cài được trong vài phút |

Bảng: Lựa chọn công nghệ cho từng thành phần của hệ thống

Các bảng dữ liệu chính gồm `users`, `links`, `views`, `daily_stats`, `cpm_rates`, `withdrawals`, `referrals`, `ad_slots`, `fraud_flags` và `settings`.

## Giao diện dự kiến

Giao diện dự kiến gồm trang chủ công khai để thu hút người đăng ký và bảng điều khiển cho thành viên tạo link. Hai bản phác thảo dưới đây tham khảo bố cục của các trang rút gọn link phổ biến như 123link và Link4M, nhưng dùng thương hiệu, màu sắc và nội dung riêng; tên thương hiệu và số liệu chỉ là mẫu.

### Trang chủ

Hình 2 minh hoạ trang chủ với ô nhập URL ngay đầu trang để khách rút gọn thử không cần đăng nhập. Phía dưới là ba bước bắt đầu kiếm tiền, và thanh trên cùng có liên kết tới bảng giá, tin tức, hỗ trợ cùng nút đăng ký.

![Bản phác thảo trang chủ với ô nhập URL, nút đăng ký và ba bước bắt đầu kiếm tiền](images/giao-dien-trang-chu.png)

### Dashboard thành viên

Hình 3 minh hoạ bảng điều khiển của thành viên, nơi người tạo link theo dõi thu nhập và quản lý link hằng ngày. Thanh bên trái có các mục Tổng quan, Thông báo, Thống kê, Top link, Quản lý link, Rút tiền, Công cụ, Giới thiệu bạn bè, Cài đặt và Hỗ trợ.

![Bản phác thảo dashboard thành viên với thẻ thu nhập, biểu đồ lượt xem, bảng giá và thông báo](images/giao-dien-dashboard.png)

Màn hình Tổng quan gồm các khối sau, mọi nội dung chỉnh được từ trang quản trị.

- **Thẻ thu nhập:** bốn thẻ cho hôm nay, hôm qua, tháng này và số dư khả dụng.
- **Biểu đồ lượt xem:** lượt xem hợp lệ 7 ngày gần nhất.
- **Bảng giá hôm nay:** mức trả theo quốc gia, thiết bị và hoa hồng giới thiệu.
- **Thông báo:** tin do quản trị viên soạn, như khuyến mãi mức trả, bảo trì, quy định mới.
- **Lối tắt:** ô dán link để rút gọn nhanh, nhóm hỗ trợ Telegram và bật thông báo trạng thái tài khoản.

## Chống gian lận và bảo mật

Bộ lọc view chạy ngay trong ứng dụng Laravel, không cần dịch vụ chạy nền riêng nên chạy được trên cPanel. Đây là tính năng bán hàng chính vì người mua chỉ nhận tiền từ mạng quảng cáo khi traffic sạch.

### Điều kiện view hợp lệ

Một view chỉ được tính tiền khi đạt các điều kiện sau, và mọi thông số đều chỉnh được trong admin.

- Khách duy nhất trong 24 giờ theo cặp IP và dấu vân tay trình duyệt.
- Thời gian ở trang tối thiểu, đo ở phía máy chủ bằng token có chữ ký.
- Bật JavaScript và cookie, hoàn thành đủ các bước, tới trang đích.
- Qua Turnstile hoặc hCaptcha; loại user-agent bot và trình duyệt tự động.
- Chặn IP datacenter, VPN và proxy bằng danh sách mạng (ASN) cập nhật qua cron.
- Quốc gia lấy từ header CF-IPCountry khi chạy sau Cloudflare, dự phòng bằng cơ sở dữ liệu GeoLite2.

### Giám sát người tạo link

Phần này giúp người mua phát hiện và xử lý tài khoản gian lận trước khi trả tiền.

- **Cảnh báo bất thường:** tỷ lệ view trên click lệch, nhiều view từ một IP hoặc một dải IP, thời gian ở trang đều như máy.
- **Số dư hai phần:** đang chờ và khả dụng; số ngày chờ do admin đặt để đối chiếu báo cáo của mạng quảng cáo.
- **Duyệt tài khoản mới:** duyệt nguồn traffic và giới hạn số tài khoản trên mỗi IP, như Link4M và Site2S đang làm.
- **Danh mục cấm:** 18+, cờ bạc, lừa đảo ghi trong điều khoản; admin khoá link và huỷ thu nhập liên quan.

### Bảo mật ứng dụng

Ứng dụng áp dụng các biện pháp bảo mật phổ biến của Laravel và quét link độc hại ngay khi tạo.

- Quét link bằng Google Web Risk hoặc Safe Browsing, cộng danh sách chặn từ khoá và tên miền.
- Mật khẩu băm bcrypt hoặc argon2, 2FA cho đăng nhập admin và rút tiền.
- Giới hạn tần suất đăng nhập và API bằng RateLimiter của Laravel.
- Chống SQL injection, XSS, CSRF theo chuẩn Laravel.
- Bản phát hành không chứa mã ẩn hay cửa hậu; license chỉ gắn với tên miền.

## Pháp lý và rủi ro

Rủi ro lớn nhất của người bán source là tương thích với nhiều loại hosting cPanel và khối lượng hỗ trợ sau bán. Hai việc này giảm được bằng kiểm thử rộng trước khi phát hành và trình cài đặt có kiểm tra môi trường; Bảng 6 liệt kê các rủi ro chính.

| Rủi ro | Mức | Cách giảm |
|--------|-----|-----------|
| Hosting cPanel khác nhau về phiên bản PHP, hàm bị khoá, giới hạn cron | Cao | Thử trên ít nhất 3 nhà cung cấp, có trang kiểm tra môi trường, ghi rõ cấu hình tối thiểu |
| Khối lượng hỗ trợ cài đặt lớn | Cao | Video và tài liệu từng bước, trình cài đặt tự kiểm tra, bán dịch vụ cài đặt |
| Người mua dùng source cho nội dung vi phạm | Trung bình | Giấy phép cấm nội dung vi phạm, quét link và danh mục cấm bật mặc định |
| Mạng quảng cáo giới hạn kiểu hiển thị | Trung bình | Hướng dẫn chọn mạng chấp nhận traffic rút gọn link, ô dán mã không phụ thuộc một mạng |
| Dữ liệu cá nhân của khách và thông tin thanh toán | Trung bình | Chỉ thu dữ liệu cần thiết, mã hoá thông tin thanh toán, kèm mẫu chính sách quyền riêng tư |
| Source bị chia sẻ lại | Trung bình | License gắn tên miền, mã mua hàng gắn email, giá trị nằm ở cập nhật và hỗ trợ |
| Thuế và hoá đơn khi bán phần mềm | Thấp | Đăng ký hộ kinh doanh hoặc doanh nghiệp, hỏi kế toán về thuế license và dịch vụ |

Bảng: Các rủi ro chính và cách giảm thiểu

Về mạng quảng cáo, [chính sách vị trí quảng cáo của AdSense](https://support.google.com/adsense/answer/1346295?hl=en) không cho hiện quảng cáo trong một khoảng thời gian định trước khi người dùng xem nội dung, nên người mua cần đọc chính sách từng mạng trước khi dùng.

> [!NOTE]
> Nên nhờ luật sư rà soát hợp đồng license, điều khoản sử dụng và giới hạn trách nhiệm trước khi mở bán; đây là thông tin tham khảo, không phải tư vấn pháp lý.

## Lộ trình

Dự kiến bắt đầu ngày 17/11/2026 và mất khoảng 1 tháng đến khi mở bán, đã gồm kiểm thử; hai dev dùng Claude Code để viết mã, kiểm thử và soạn tài liệu nhanh nhất có thể, nên chi phí gần như bằng 0. Hình 4 cho thấy bốn giai đoạn và hai cổng kiểm tra, chưa qua cổng thì chưa sang giai đoạn sau.

![Lộ trình bốn giai đoạn trong 1 tháng từ 17/11/2026 với hai cổng kiểm tra trước khi mở bán](images/lo-trinh.png)

Nội dung từng giai đoạn như sau.

1. **Chuẩn bị (17/11/2026–20/11/2026):** chốt phạm vi MVP, thiết kế CSDL, wireframe giao diện.
2. **Xây lõi (21/11/2026–05/12/2026):** rút gọn và vượt link, dashboard và quản trị, bộ lọc view, kiểm thử chức năng; cổng kiểm tra là bộ lọc view đạt.
3. **Đóng gói và kiểm thử (06/12/2026–14/12/2026):** trình cài đặt web, thử trên 3 loại hosting, kiểm thử tải và bảo mật, tài liệu và video; cổng kiểm tra là cài được trên 3 hosting.
4. **Mở bán (từ 15/12/2026):** demo và trang bán, 5–10 khách đầu tiên, chốt giá và cập nhật.

Nhân sự tối thiểu là hai dev: một người phụ trách backend Laravel, một người phụ trách giao diện; cả hai dùng Claude Code để sinh mã, viết kiểm thử và soạn tài liệu, còn hỗ trợ bán hàng làm kiêm nhiệm.

> [!WARNING]
> Lịch 1 tháng khá gấp; nếu trình cài đặt chưa chạy ổn trên 3 hosting thì lùi ngày mở bán chứ không bỏ cổng kiểm tra.

## Kế hoạch bán hàng

Bán hàng bắt đầu từ một trang demo chạy thật và một video cài đặt trên cPanel dưới 10 phút. Hai thứ này trả lời câu hỏi lớn nhất của người mua là có tự cài được hay không.

- **Demo công khai:** một trang rút gọn hoàn chỉnh cùng tài khoản admin chỉ đọc để khách xem trang quản trị.
- **Kênh bán ở Việt Nam:** nhóm Facebook và Telegram về MMO, kênh YouTube hướng dẫn, bài blog so sánh các trang hiện có; nhận chuyển khoản hoặc MoMo, giao file sau khi xác nhận.
- **Kênh bán quốc tế:** giai đoạn sau, đăng bản tiếng Anh lên CodeCanyon để đo nhu cầu.
- **Tài liệu:** hướng dẫn cài đặt, cấu hình cron, nối mạng quảng cáo, đặt bảng giá, duyệt rút tiền, mẫu điều khoản và chính sách quyền riêng tư.
- **Hỗ trợ:** nhóm Telegram riêng cho người mua, thời gian phản hồi nêu rõ, gói Pro trở lên có cài đặt giúp.
- **Cập nhật:** phát hành theo tháng kèm changelog; người mua gia hạn để nhận bản mới.

Danh sách dưới đây là các việc cần xong trước ngày mở bán.

- [ ] Trình cài đặt chạy thành công trên ít nhất 3 hosting cPanel khác nhau
- [ ] Bộ lọc view được kiểm thử với traffic giả lập và traffic thật
- [ ] Tài liệu và video cài đặt hoàn chỉnh
- [ ] Hợp đồng license do luật sư rà soát
- [ ] Trang demo và trang bán hàng có bảng so sánh gói
- [ ] Cơ chế kiểm tra mã mua hàng cho tự cập nhật hoạt động

## Kết luận

Làm và bán source rút gọn link Laravel chạy trên cPanel là hướng khả thi: nhu cầu mua source có thật, khoảng trống nằm ở bản tiếng Việt có chia tiền theo view và thanh toán nội địa, còn chi phí của nhóm gần như bằng 0. Thành bại phụ thuộc vào việc cài đặt ổn định trên nhiều hosting và bộ lọc view đủ tốt.

- **Quyết định cần chốt:** phạm vi MVP và bảng giá gói Starter, Pro, Business.
- **Bước tiếp theo:** thiết kế CSDL và bắt đầu giai đoạn Chuẩn bị.
- **Trước khi mở bán:** thử trên 3 hosting và nhờ luật sư rà soát hợp đồng license.

## Tài liệu tham khảo

1. Blog DTA. *Top website rút gọn link kiếm tiền ở Việt Nam*. 2026. https://blogdta.com/top-10-website-rut-gon-link-kiem-tien-uy-tin-hieu-qua-o-viet-nam-current_date-formatm-y/
2. ShrinkMe. *Bảng giá publisher*. 2026. https://shrinkme.io/payout-rates
3. Homecredit. *12 web rút gọn link kiếm tiền*. 2026. https://www.homecredit.vn/blog/top-web-rut-gon-link-kiem-tien-online-khong-scam-101
4. Hidemyacc. *8 trang rút gọn link kiếm tiền*. 2026. https://hidemyacc.com/vi/rut-gon-link-kiem-tien
5. Tino. *Top 9 website rút gọn link kiếm tiền*. 2026. https://tino.vn/blog/website-rut-gon-link-kiem-tien/
6. CodeCanyon. *Danh sách script PHP (phpShort, Shortny)*. 2026. https://codecanyon.net/category/php-scripts/miscellaneous?term=stats
7. CodeCanyon. *LteUrl*. 2026. https://codecanyon.net/item/lteurl-link-shortener-reactjs-nodejs/50989338
8. GemPixel. *Tài liệu Premium URL Shortener*. 2026. https://gempixel.com/docs/premium-url-shortener
9. Google AdSense. *Chính sách vị trí quảng cáo*. 2026. https://support.google.com/adsense/answer/1346295?hl=en
