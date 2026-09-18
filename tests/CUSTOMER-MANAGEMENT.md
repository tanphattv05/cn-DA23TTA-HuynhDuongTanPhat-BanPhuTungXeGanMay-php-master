# Quản lý khách hàng MotoParts

Mở /cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/scr/admin/customers.php
sau khi đăng nhập admin. Nhấn Xem chi tiết để mở customer-detail.php?id=<ID khách hàng>.

## Phạm vi và truy vấn

- Chỉ đọc users có role customer; chỉ SELECT id, fullname, email, phone, created_at.
- Không đọc mật khẩu, không có chức năng sửa/xóa/khóa/đổi role/đặt lại mật khẩu.
- Dùng lại auth và helper truy vấn/escape qua product-bootstrap.php.
- Danh sách tìm theo tên/email/điện thoại; 10 khách/trang, giữ từ khóa.
- orders được GROUP BY user_id trước khi LEFT JOIN users: một dòng thống kê mỗi khách,
  không JOIN order_details, không chạy truy vấn riêng cho từng dòng.
- Tổng số đơn gồm mọi trạng thái; tổng tiền chỉ cộng completed.
- Chi tiết đếm completed/cancelled và phân trang 10 đơn, theo created_at DESC, id DESC.
- Chỉ ghép orders.user_id; đơn NULL không được suy đoán chủ sở hữu từ thông tin liên hệ.
- ID sai/không tồn tại/admin trả 404 trong layout AdminLTE.
- Page sai về trang 1; page hợp lệ vượt phạm vi về trang cuối.
- Không tạo liên kết tới trang chi tiết đơn phía khách hàng.

## Kiểm thử tự động

Chạy tại gốc repository:

```powershell
D:/xampp/php/php.exe tests/product-management.php --isolated
```

Bộ chạy dùng database motoparts_test_<random> và bản sao PHP tạm, gọi thêm
tests/customer-management.php sau test sản phẩm và danh mục.
Chỉ đọc schema thật; không sao chép dữ liệu thật. Các khách hàng/đơn giả được INSERT trực tiếp
vào database thử, không gọi quy trình đặt hàng. Tự dọn database và bản sao thử.
Cần PHP mysqli/curl/mbstring/fileinfo/DOM, proc_open và quyền tạo database thử.

Kết quả: 69 kiểm tra khách hàng + 55 sản phẩm + 81 danh mục = 205 kiểm tra đạt.
Bao phủ tìm kiếm ba trường, phân trang, thống kê số đơn và tiền completed,
khách chưa có đơn, đơn khách khác và đơn NULL trùng tên/điện thoại,
ID/page sai hoặc dạng mảng, 404 có layout, chặn customer và chưa đăng nhập,
escape HTML, không lộ thông tin lỗi database, sidebar active,
không chứa dữ liệu mật khẩu fixture trong response và không sửa users/orders.
Các truy vấn snapshot users trong test cũng chỉ lấy cột công khai cần kiểm tra.

## Kiểm tra trực quan trên XAMPP

- Đăng nhập admin; mở Khách hàng và kiểm tra bảng trên màn hình rộng/hẹp.
- Tìm theo tên, email, điện thoại; chuyển trang và kiểm tra từ khóa được giữ.
- Mở chi tiết một khách có đơn; kiểm tra các nhãn thống kê, trạng thái tiếng Việt và phân trang.
- Mở khách chưa đặt hàng và ID không tồn tại; kiểm tra thông báo, layout và nút quay lại.
- Kiểm tra đúng một mục sidebar active ở cả danh sách và chi tiết.
- Đăng nhập customer hoặc đăng xuất; xác nhận không truy cập được hai trang quản trị.

HTTP tests không thay thế việc kiểm tra bố cục trực quan bằng trình duyệt.
