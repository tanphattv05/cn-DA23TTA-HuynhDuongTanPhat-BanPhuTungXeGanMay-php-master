# MVC giai đoạn 5 — Tổng quan Admin

## Luồng và quy ước

Giữ namespace MotoParts\App, class PascalCase, template lowercase và View::admin
như giai đoạn 1–4. Không thêm dependency, rewrite hoặc thay đổi schema.

```text
scr/admin/index.php
  → scr/app/bootstrap.php
  → Controllers/Admin/DashboardController::index()
  → Models/Dashboard::statistics(), latestOrders()
  → Views/admin/dashboard/index.php + layout AdminLTE hiện có
```

- Điểm vào cũ chỉ định nghĩa guard, nạp bootstrap và gọi Controller cố định.
- Controller nạp order-status.php → product-bootstrap.php → auth.php, kiểm tra
  quyền trước truy vấn nghiệp vụ/HTML. Dùng kết nối MySQLi hiện có.
- Model chỉ đọc, nhận kết nối qua constructor; SQL cố định không nhận đầu vào
  người dùng. Chỉ lấy các cột cần hiển thị, không lấy password.
- View giữ bố cục cũ, nhận array thay mysqli_result; không SQL/request.
- Nhãn dùng order_status_labels hiện có. Bảng màu từ dashboard cũ được chuyển
  nguyên vẹn thành order_status_classes trong helper order-status.php.
  Không sao chép hoặc sửa quy tắc chuyển trạng thái.
- Toàn bộ runtime mới ở scr/app, có guard MOTOPARTS_MVC_ENTRY và được bảo vệ
  bởi .htaccess Require all denied hiện có. Không sửa index.php gốc.

## Hành vi

Giữ số sản phẩm, danh mục, tài khoản customer (không gồm admin), tổng đơn và
số pending. Doanh thu chỉ SUM(orders.total) với completed, COALESCE về 0.
Giữ định dạng tiền dashboard cũ (hiển thị làm tròn đến đồng).
10 đơn gần nhất ORDER BY id DESC LIMIT 10; không sắp xếp theo ngày.
Đơn vãng lai vẫn xuất hiện, liên kết đến order-detail.php Admin.
Sidebar chỉ active Tổng quan. Không có thao tác ghi hoặc thay đổi module khác.

Cải thiện xử lý lỗi: khi truy vấn thất bại, trả HTTP 503 với thông báo tiếng Việt
trong layout, không lộ lỗi DB và không hiển thị số 0 giả làm số liệu thật.

## Kiểm tra

```powershell
D:/xampp/php/php.exe tests/product-management.php --isolated
```

- Baseline: 442/442 đạt.
- Sau chuyển đổi: 479/479 đạt, thêm 37 kiểm tra tests/dashboard-mvc.php.
- Hồi quy toàn bộ danh mục, sản phẩm, khách hàng, đơn hàng và MVC giai đoạn 1–4.
- Test Tổng quan: không có đơn; chỉ pending/cancelled; hỗn hợp cả 5 trạng thái;
  chỉ cộng completed; customer không gồm admin; 10 đơn theo ID giảm dần dù ngày
  có thứ tự ngược; liên kết, nhãn/màu, XSS, sidebar và GET không sửa dữ liệu.
- Phân quyền admin/customer/khách; file MVC bị chặn với cả ba loại session;
  mô phỏng lỗi truy vấn trả 503 và thông báo chung.
- PHP lint cả 7 file PHP mới/sửa và git diff --check đạt.
- Apache XAMPP thực tế: app/, Model, Controller, View mới đều 403;
  admin/index.php khi chưa đăng nhập trả 302.
- Database motoparts_test_<random> và bản sao tạm tự dọn sau test.
  Fixture cuối chỉ thay dữ liệu trong DB thử đã xác minh; không sửa dữ liệu thật.
- Giữ nguyên thay đổi giai đoạn 4 chưa commit; không commit.
- Chưa kiểm tra bố cục/thao tác trực quan bằng trình duyệt.
  Bộ hồi quy hiện có không phải kiểm thử end-to-end checkout.

## URL và checklist XAMPP

http://localhost/cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/scr/admin/index.php

1. Đăng nhập admin: đối chiếu các chỉ số và doanh thu completed.
2. Kiểm tra bảng tối đa 10 đơn, nhãn/màu, người nhận/ngày/tiền và link chi tiết.
3. Xem bố cục trên điện thoại; sidebar chỉ active Tổng quan.
4. Đăng xuất: chuyển đăng nhập; dùng customer: nhận 403.
5. Mở /scr/app/Models/Dashboard.php: nhận 403.
6. Dùng bản sao DB thử để xem trạng thái chưa có đơn, không sửa đơn thật.