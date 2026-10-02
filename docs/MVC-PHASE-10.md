# MVC giai đoạn 10 — Lịch sử đơn hàng khách hàng

## Phạm vi và cấu trúc

Một website PHP/MySQLi trên XAMPP, toàn bộ runtime tiếp tục trong `scr/`.
Không rewrite, framework, schema mới hoặc server frontend/backend riêng.
Không đổi session, auth, checkout, giỏ hàng hay xử lý trạng thái/tồn kho Admin.

```text
scr/
  pages/my-orders.php                 → OrderController::index()
  pages/order-detail.php?id=...        → OrderController::detail()
  app/
    Controllers/Storefront/OrderController.php
    Models/StorefrontOrder.php
    Core/OrderStatus.php
    Views/storefront/orders/index.php
    Views/storefront/orders/detail.php
  admin/includes/order-status.php     → metadata OrderStatus dùng chung
```

Frontend: hai View, layout `scr/includes`, assets và entrypoint công khai trong
`scr/pages`. Backend: Controller, Model, Middleware, Core và config trong `scr`.
Tài liệu/tests ngoài scr không phải runtime. Không thêm Service vì luồng chỉ đọc
chưa có nghiệp vụ cần một lớp riêng.

Request → entrypoint → OrderController → Authenticate → StorefrontOrder → DB;
Controller chọn View → View::storefront ghép header/navbar/footer hiện có.
Entrypoint không SQL/HTML; Controller không SQL; View không truy vấn/request/session.

## Quyền sở hữu và lỗi

- Middleware giai đoạn 9 xác thực lại user với DB. ID chủ đơn lấy từ kết quả middleware,
  không lấy user_id trong GET/POST. Guest chuyển login theo URL hiện có.
- Danh sách `WHERE user_id = ? ORDER BY id DESC`, chỉ lấy cột hiển thị.
  Mã cũ không có phân trang; tiếp tục hiển thị toàn bộ đơn của user, không thêm phân trang.
- Tìm đơn bằng cả `id = ? AND user_id = ?`. Truy vấn dòng chi tiết cũng JOIN orders
  và ràng buộc cả ID đơn lẫn user_id để không bỏ ownership khi Model được tái sử dụng.
- ID phải là scalar integer dương trong INT signed 1..2147483647. ID sai, thiếu,
  không tồn tại, đơn vãng lai hoặc thuộc người khác cùng status/body 404 theo layout storefront.
- Admin vẫn có thể xem đơn của chính mình tại storefront như trước; chỉ URL Admin
  có quyền quản lý đơn của người khác. Không dùng storefront thay trang Admin.
- Query lỗi trả 503 với thông báo tiếng Việt; chỉ log loại exception và mã số,
  không log SQL, credentials, session ID hoặc dữ liệu người nhận.
- Model dùng prepared statements, không lấy password/hash; mọi dữ liệu văn bản
  động được escape. GET chỉ đọc, không có hủy/sửa/xóa đơn phía khách hàng.

## Dữ liệu và hiển thị

Schema thực tế: orders.id/user_id là INT, user_id có thể NULL; note VARCHAR(500)
cho phép NULL; order_details.price DECIMAL(12,2), quantity INT; products.image nullable.
Schema không lưu snapshot tên/ảnh. Vì vậy dùng tên/ảnh hiện tại nếu sản phẩm còn;
không giả định có thể khôi phục tên cũ. LEFT JOIN bảo toàn dòng chi tiết khi gặp dữ liệu
cũ thiếu sản phẩm, dùng nhãn “Sản phẩm không còn tồn tại”. FK hiện tại thường chặn
xóa sản phẩm đang được tham chiếu; bài test mô phỏng orphan chỉ trong DB cô lập.

Đơn giá lấy từ order_details.price, thành tiền tính bằng phép nhân DECIMAL trong SQL.
Tổng đơn hiển thị orders.total, không tính lại hoặc ghi đè dữ liệu đơn.
Giữ định dạng storefront cũ: tiền Việt Nam với dấu chấm hàng nghìn, 0 chữ số thập phân
(number_format khi hiển thị); không đổi định dạng tiền Admin.
Ảnh chỉ hiển thị nếu có file trong scr/assets/images/products; basename/rawurlencode
và escape URL, không hiện thẻ ảnh hỏng khi thiếu. Ghi chú NULL được bỏ qua như trước.
Bảng có cuộn ngang, dòng chi tiết có thể xuống dòng trên màn hình hẹp.

OrderStatus là nguồn duy nhất cho nhãn tiếng Việt và màu badge. Hai hàm
order_status_labels/order_status_classes của Admin gọi lớp này; helper Admin vẫn
được bảo vệ như cũ. order_transitions và order_money không thay đổi.

## File bàn giao

Mới: 5 file runtime trong scr/app ở cây trên, tests/storefront-order-mvc.php,
tests/STOREFRONT-ORDER-MVC.md, tài liệu này và
docs/superpowers/plans/2026-10-01-storefront-order-history-mvc.md.

Sửa: scr/pages/my-orders.php, scr/pages/order-detail.php,
scr/admin/includes/order-status.php, tests/product-management.php (nạp suite mới).
Không sửa index.php gốc, scr/.gitignore, vendor hoặc thư mục thesis.

## Kiểm tra ngày 2026-10-01

- Workspace ban đầu sạch; không tìm thấy AGENTS.md trong dự án/thư mục cha đã kiểm tra.
- Baseline thực tế: **800/800**.
- TDD RED: sau hai kiểm tra guest, thất bại “Thin history entry: my-orders.php”
  trước khi viết Model/Controller/View mới.
- Sau triển khai: **863/863**, gồm **63 kiểm tra mới** và 800 hồi quy giai đoạn 1–9.
- PHP lint: **10/10** file mới/sửa; git diff --check đạt.
- Apache localhost: scr/app/ và 5 file MVC mới trả **403**; hai URL storefront
  chưa đăng nhập trả **302** về login. HTTP khi đăng nhập được kiểm tra trên bản sao cô lập.
- Không kiểm tra trực quan bằng trình duyệt; chưa xác nhận pixel/layout responsive thực tế.

Công cụ apply_patch gặp lỗi đọc file qua sandbox helper. Các chỉnh sửa được áp dụng
bằng chính executable apply_patch qua shell được phép; không ghi đè bằng lệnh ghi file khác.
Không đổi schema/dữ liệu thật, không tạo đơn/account thật, không tự commit.

## URL và checklist XAMPP

Base: http://localhost/cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/scr/

1. Guest mở pages/my-orders.php và pages/order-detail.php?id=1: chuyển login.
2. Trên bản sao DB thử, login customer A: danh sách đúng đơn của A, mới nhất trước;
   tài khoản chưa có đơn hiện trạng thái trống và liên kết mua sắm.
3. Mở chi tiết từ danh sách: đúng người nhận, ngày, ghi chú, ảnh, giá lịch sử và tổng đã lưu.
4. Thử ID đơn của B/vãng lai, ID âm/chữ/không tồn tại: cùng 404, không lộ thông tin đơn.
5. Kiểm tra tên/ghi chú chứa ký tự HTML được hiển thị như văn bản, ảnh thiếu không vỡ layout.
6. Xem trên điện thoại/desktop; quay lại danh sách, navbar/cart/login/logout vẫn hoạt động.
7. Admin tiếp tục mở admin/orders.php và admin/order-detail.php?id=...; chỉ thử thao tác
   ghi trên dữ liệu cô lập. Các file app không được mở trực tiếp qua HTTP.
