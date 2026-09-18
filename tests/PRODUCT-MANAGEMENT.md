# Quản lý sản phẩm MotoParts

Đường dẫn: /cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/scr/admin/products.php

Các trang dùng auth.php, layout AdminLTE và kết nối MySQLi hiện tại. Không có chức năng xóa.
Ảnh là tùy chọn; sửa không chọn ảnh giữ nguyên ảnh cũ. Ảnh cũ không bị xóa.
Giới hạn: tên 255 ký tự, thương hiệu 100 ký tự, mô tả 65535 byte UTF-8,
giá 0–9999999999.99, tồn kho 0–2147483647. Ảnh JPEG/PNG/WebP tối đa 2 MiB
và 20 triệu điểm ảnh; tên file do server sinh ngẫu nhiên.
Kiểm tra MIME bằng fileinfo và cấu trúc ảnh bằng getimagesize; giải mã bổ sung nếu có GD.
PHP XAMPP hiện có mbstring/fileinfo, chưa bật GD.

## Kiểm thử tự động

Chạy tại gốc repository:

```powershell
D:/xampp/php/php.exe tests/product-management.php --isolated
```

Cần MySQL đang chạy, PHP có mysqli/curl/mbstring/fileinfo và quyền tạo database.
Bộ test đọc schema từ database hiện tại, tạo database motoparts_test_<random>
và bản sao PHP trong thư mục tạm, chạy HTTP server chỉ trên 127.0.0.1.
Không sao chép dữ liệu thật, không đặt đơn. Database và bản sao thử được dọn trong finally.
Kiểm tra lịch sử giá sử dụng bảng order_details tối giản trong database thử.

## Kiểm tra trên trình duyệt XAMPP

- Đăng nhập admin, mở Sản phẩm; chỉ đúng một mục sidebar active.
- Tìm tên/thương hiệu, lọc danh mục, chuyển trang và kiểm tra bộ lọc được giữ.
- Trong môi trường thử: thêm sản phẩm, sửa không ảnh, thay ảnh; xem trước ảnh.
- Thử giá âm, quá 2 chữ số thập phân, tồn kho âm, danh mục sai, SVG/file giả ảnh và ảnh quá 2 MiB.
- Kiểm tra thông báo lỗi và dữ liệu form được giữ; ảnh phải chọn lại sau lỗi.
- Đăng nhập customer: không mở được các trang quản trị hoặc POST endpoint lưu.
- Mở scr/pages/products.php để kiểm tra sản phẩm mới.

HTTP tests không kiểm tra trực quan AdminLTE hoặc JavaScript xem trước ảnh.
Bảo vệ .htaccess cần Apache AllowOverride phù hợp; cấu hình XAMPP hiện tại cho phép All.
