# MVC giai đoạn 3 — Khách hàng Admin

## Phạm vi và quy ước

Giữ quy ước MVC giai đoạn 1/2: namespace MotoParts\App, class PascalCase,
template lowercase, autoload nội bộ và View::admin ghép AdminLTE.
Toàn bộ runtime mới trong scr/app; index.php gốc vẫn chỉ redirect vào scr.
Không thêm dependency/rewrite/schema hoặc thao tác ghi khách hàng.

```text
scr/
  admin/customers.php       -> CustomerController::index()
  admin/customer-detail.php -> CustomerController::detail()
  admin/includes/customer-view.php (giữ nguyên helper dùng chung)
  app/Controllers/Admin/CustomerController.php
  app/Models/Customer.php
  app/Views/admin/customers/index.php
  app/Views/admin/customers/detail.php
```

Request → điểm vào cũ → bootstrap → CustomerController → Customer → View::admin → AdminLTE.

- Constructor controller gọi helper hiện có, qua product-bootstrap.php/auth.php
  kiểm tra quyền trước truy vấn nghiệp vụ/HTML; giữ hợp đồng $conn và $baseUrl.
- Controller đọc keyword/ID/page, xử lý 404/503 và chọn template tĩnh.
- Model chỉ có count, paginate, find, orderStatistics, orderHistory;
  prepared statements và danh sách cột rõ ràng, không SELECT mật khẩu.
- COUNT và danh sách dùng chung điều kiện tìm kiếm.
- Tổng hợp orders theo user_id trước LEFT JOIN; không N+1 hoặc nhân đôi số liệu.
- View giữ nguyên markup và escape dữ liệu, không SQL/request/POST.
- customer_page/customer_date/customer_pagination và order_status_labels được dùng lại,
  không sao chép và không thay đổi helper đang phục vụ danh sách đơn.

## Hành vi giữ nguyên

Chỉ liệt kê customer. Tìm tên/email/điện thoại, 10 khách/trang và giữ keyword.
Khách chưa đặt hàng vẫn hiện với 0 đơn/0 tiền. Chỉ cộng tiền completed.
Lịch sử chỉ theo orders.user_id, 10 dòng/trang, không suy đoán chủ đơn NULL.
ID sai/không tồn tại/admin trả 404 trong layout. Page sai về 1, vượt phạm vi về cuối.
Sidebar và liên kết lịch sử tới order-detail.php Admin không đổi.
Không thêm sửa/xóa/khóa/đổi role/đặt lại mật khẩu.
Không sửa chức năng đăng nhập, sản phẩm, danh mục, đơn hàng hoặc trang khách hàng.

## Bảo vệ HTTP

Dùng nguyên scr/app/.htaccess (Require all denied) và guard MOTOPARTS_MVC_ENTRY.
Đã kiểm tra trên Apache localhost: app/, Model/Controller/hai View mới đều 403.
Hai điểm vào cũ khi chưa đăng nhập trả 302 đến login.
index.php gốc tiếp tục 302 về /scr/.
Máy chủ thử PHP (không đọc .htaccess) cũng chặn file nội bộ bằng guard,
kể cả request có session admin/customer hoặc không đăng nhập.

## Kiểm tra

```powershell
D:/xampp/php/php.exe tests/product-management.php --isolated
```

- Baseline trước sửa: 390 kiểm tra đạt, gồm 69 kiểm tra khách hàng.
- Sau tách MVC: 390 kiểm tra cũ đạt, không thay assertion nghiệp vụ.
- Sau bổ sung tests/customer-mvc.php: 412 kiểm tra đạt (22 kiểm tra mới).
- Hồi quy gồm MVC danh mục, sản phẩm, khách hàng, chi tiết đơn và bộ lọc đơn.
- Bao phủ chỉ customer, tìm/phân trang, thống kê completed, khách không có đơn,
  đơn người khác/vãng lai trùng thông tin, ID sai/admin, 404 có layout,
  phân quyền, escape HTML, liên kết Admin và các bảng users/orders không bị sửa.
- Test MVC mới kiểm tra URL nội bộ, URL cũ, điểm vào mỏng, phân tách lớp,
  truy vấn chỉ đọc và cột SELECT công khai.
- PHP lint và git diff --check được chạy khi bàn giao.
- Chỉ dùng database motoparts_test_<random> cùng bản sao tạm, tự dọn sau chạy.
  Không ghi dữ liệu thật, không commit.
- Chưa kiểm tra thao tác/bố cục trực quan bằng trình duyệt.

## URL và checklist XAMPP

Tiền tố: /cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/scr

1. Đăng nhập admin, mở /admin/customers.php, tìm tên/email/điện thoại và chuyển trang.
2. Nhấn Xem chi tiết; kiểm tra số đơn, tiền completed và phân trang lịch sử.
3. Nhấn mã đơn để mở trang chi tiết đơn Admin.
4. Mở khách chưa có đơn, ID sai/ID admin; kiểm tra thông báo, layout và sidebar.
5. Đăng nhập customer hoặc đăng xuất: không mở được hai trang quản trị.
6. Mở /app/Models/Customer.php: phải nhận 403.
