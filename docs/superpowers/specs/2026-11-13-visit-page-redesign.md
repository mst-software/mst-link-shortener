# Thiết kế lại trang vượt link: "LinkCash Unlock"

Trạng thái: hướng thiết kế do tôi đề xuất theo yêu cầu "đừng giống link4m, phải có sự sáng tạo và đẹp đẽ của riêng mình". Ngày: 2026-11-13. Tài liệu viết tiếng Việt để bạn đọc; mọi chữ trên giao diện, code và comment trong repo là tiếng Anh.

Thay thế phần "giữ bố cục của link4m" ở mục 6.2 của `2026-11-13-keyword-code-flow-design.md`. **Chức năng, các endpoint, mã lỗi và luồng dữ liệu giữ nguyên**, chỉ thay giao diện (HTML, CSS, JS hiển thị).

## 1. Ý tưởng chính

Trang vượt link giống một **buồng mở khóa** (unlock): khách thấy rõ mình đang ở đâu, còn bao lâu, và việc tiếp theo là gì. Thay vì một trang dài đọc từ trên xuống như link4m, đây là một **bảng điều khiển hai cột**: bên trái là "trạm mở khóa" (vòng tiến độ, ô nhập mã, nút tiếp tục), bên phải là **hành trình bốn chặng** thu gọn được.

## 2. Nhận diện (khác link4m)

| Yếu tố | link4m | LinkCash Unlock |
|---|---|---|
| Nền chủ đạo | xanh dương gradient, thanh trên cao | nền sáng ấm (kem nhạt), không có thanh trên nặng, chỉ có logo nhỏ ở góc |
| Màu | xanh dương, nút đỏ | **xanh rừng đậm** làm màu mực và nhấn **xanh chanh (lime)** từ logo LinkCash, một màu cảnh báo cam ấm |
| Hình khối | thẻ trắng cuộn dài, hộp nét đứt | thẻ lớn bo tròn mềm, tầng nổi nhẹ, nền có họa tiết mắt xích (chuỗi liên kết) rất mờ |
| Chữ | chữ hoa đậm xanh | cặp chữ riêng: tiêu đề **Sora** (hình học, hiện đại) và nội dung **Inter**; số đồng hồ dùng chữ số đều (tabular) |
| Mã | một ô nhập dài | **sáu ô nhập rời** (như mã OTP) tự nhảy ô, dán một lần là điền đủ |
| Tiến độ | số giây bằng chữ | **vòng tiến độ** lớn tô dần màu lime, kèm bốn chấm chặng |
| Hướng dẫn | danh sách 6 bước dài với ảnh to | **bốn chặng** gập mở: Search, Visit, Get the code, Paste. Mỗi chặng có một câu ngắn và một nút hành động |

## 3. Bố cục

Desktop (từ 960 px): hai cột trong một khung rộng tối đa 1080 px.
```
+----------------------------------------------------------------------+
|  (logo)                                              Home · Payout   |
|                                                                      |
|  +------------------------------+   +-----------------------------+  |
|  |  UNLOCK YOUR LINK            |   |  1  Search     [done chip]  |  |
|  |        (  vòng tiến độ  )    |   |  2  Visit the site          |  |
|  |          10:00 còn lại       |   |     [Open Google search]    |  |
|  |  [ _ ][ _ ][ _ ][ _ ][ _ ][ _ ]|   |  3  Get the code            |  |
|  |  (captcha)                   |   |  4  Paste it here           |  |
|  |  [   Continue   ]            |   |  ...show an example         |  |
|  |  trạng thái: Enter the code  |   +-----------------------------+  |
|  +------------------------------+   (quảng cáo "Sponsored" Top/Bottom)|
+----------------------------------------------------------------------+
```
Mobile (360 px trở lên): một cột. Thẻ tiến độ ở trên, hành trình bốn chặng bên dưới, và **thanh hành động dính ở đáy màn hình** chứa ô nhập mã và nút Continue để dùng bằng một tay.

## 4. Chi tiết trải nghiệm

- **Từ khóa là một "chip" có thể bấm:** hiện từ khóa lớn, rõ, kèm nút "Copy" và nút chính **"Open Google search"** mở `https://www.google.com/search?q=<keyword>` ở tab mới (`rel="noopener noreferrer"`). Dưới đó là "Find this site: `example-adv***.com`" cũng dạng chip.
- **Hành trình bốn chặng** (mỗi chặng một thẻ nhỏ, mở ra khi tới lượt, tự gập khi xong): chặng 1 Search (mở Google với từ khóa, nhắc dùng Chrome, không bấm quảng cáo được tài trợ, không dùng chế độ ẩn danh bằng ba dòng icon ngắn), chặng 2 Visit (tìm đúng site ở trang 1), chặng 3 Get the code (nhấn nút GET CODE ở cuối bài, chờ, bấm một link bất kỳ rồi quay lại), chặng 4 Paste (dán mã). Ảnh minh họa của chiến dịch nằm trong "Show an example" (gập sẵn), không chiếm chỗ.
- **Mã sáu ô:** tự động nhảy ô, hỗ trợ dán cả chuỗi, phím Backspace lùi ô, chấp nhận chữ thường (tự đổi), hiện trạng thái đúng/sai bằng màu và icon (không chỉ bằng màu).
- **Vòng tiến độ và đồng hồ phiên:** vòng đổi sang màu cam khi còn dưới 60 giây và có dòng chữ cho trình đọc màn hình. Sau khi hết phiên hiện thẻ "Start again" rõ ràng (không bắt khách tự đoán).
- **Nút Continue:** xám cho đến khi đủ mã và qua captcha, rồi chuyển màu lime đậm với hiệu ứng nhẹ. Dòng trạng thái dưới nút: "Enter the code" → "Verify you are human" → "Ready".
- **Captcha** nằm trong một thẻ nhỏ "Quick check" (giữ widget Turnstile như hiện có).
- **Báo lỗi:** liên kết nhỏ "Something wrong? Report it" mở một **bottom sheet** (trên mobile) hoặc hộp thoại (desktop) với ba lựa chọn dạng thẻ bấm được, captcha, Send. Không dùng hộp thoại kiểu cũ.
- **Khung mời đăng ký** gọn hơn: một dải mỏng cuối trang "Earn from your own links" với hai nút, không chiếm cả màn hình.
- **Quảng cáo:** vẫn dùng `<x-visit.ad-slot>` ở Top và Bottom, bọc trong thẻ có nhãn nhỏ "Sponsored" để khách phân biệt rõ với nút bấm của hệ thống.
- **Chế độ chờ giây (wait):** dùng cùng khung: vòng tiến độ đếm theo giây, bốn chấm đổi thành N chấm theo số bước, nút đổi thành "Continue" khi xong. Không có cột hành trình.

## 5. Chuyển động và hỗ trợ tiếp cận

- Chuyển động ngắn (150 đến 250 ms): vòng tiến độ mượt, chặng mở ra gập vào, ô mã rung nhẹ khi sai. Tắt hoàn toàn khi `prefers-reduced-motion`.
- Tương phản chữ tối thiểu 4.5:1, mọi nút và ô cao tối thiểu 48 px, vùng bấm tối thiểu 44 px, viền focus rõ cho bàn phím, `aria-live="polite"` cho đồng hồ và trạng thái, `lang` đúng, không dùng emoji làm icon (dùng SVG nội tuyến).
- Responsive kiểm tra ở 360, 768, 1024 và 1440 px.

## 6. Ràng buộc kỹ thuật

- Chỉ sửa giao diện: `resources/views/visit/*`, `resources/views/layouts/visit.blade.php`, `resources/css/visit.css`, `resources/js/visit.js` (jQuery) và test cấu trúc `tests/Feature/VisitPageTest.php`. Không đổi endpoint, tên trường JSON, mã lỗi.
- Phông chữ Sora và Inter nạp từ Google Fonts (đã được phép ở các layout khác của dự án); có chữ dự phòng hệ thống.
- Không thêm dependency. Tất cả chữ tiếng Anh, không logo hay hình của link4m.

## 7. Điểm tôi tự quyết (bạn xem lại)

- Chọn nền sáng ấm và xanh rừng + lime theo logo LinkCash (thay vì tông xanh dương của link4m) và bố cục hai cột.
- Có nút "Open Google search" mở sẵn kết quả tìm kiếm với từ khóa (tiện cho khách, không đổi luồng tính tiền).
- Mã nhập bằng sáu ô rời.
