# Quản lý danh mục MotoParts

Mở /cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/scr/admin/categories.php sau khi đăng nhập admin.

- Danh sách 10 danh mục/trang, tìm tên và giữ từ khóa khi chuyển trang.
- Số sản phẩm bao gồm cả danh mục rỗng (0).
- Tên được trim, bắt buộc, tối đa 100 ký tự Unicode.
- Mô tả được trim, không bắt buộc, tối đa 2.000 ký tự Unicode.
- Kiểm tra tên trùng bằng phép so sánh SQL theo collation cột name hiện tại (utf8mb4_general_ci).
- Sửa giữ nguyên ID. Không có thao tác xóa, không thay đổi khóa ngoại ON DELETE CASCADE.
- Dùng lại product-bootstrap.php và các helper query/escape/text/redirect/CSRF hiện có.
- Form sản phẩm vốn lấy danh mục từ database nên tự hiển thị danh mục mới, không cần thay đổi mã sản phẩm.

## Giới hạn tính duy nhất

Không thêm migration hoặc UNIQUE. Kiểm tra tên trùng ở ứng dụng không bảo đảm tuyệt đối
khi hai request cùng kiểm tra rồi ghi đồng thời. Khi cần bảo đảm ở mức database,
cần rà soát tên trùng hiện có và phê duyệt riêng việc thêm UNIQUE theo collation mong muốn.
Transaction hiện tại bảo vệ thao tác sửa và kiểm tra tồn tại, không thay thế UNIQUE.

## Kiểm tra tự động

Chạy tại gốc repository:

```powershell
D:/xampp/php/php.exe tests/product-management.php --isolated
```

Bộ chạy cũ dùng lại môi trường test tạm và gọi tests/category-management.php.
Kết quả triển khai: 81 kiểm tra danh mục + 55 kiểm tra hồi quy sản phẩm, tổng 136.
Chỉ đọc schema từ database thật; tạo database motoparts_test_<random>, user/dữ liệu giả
và bản sao PHP trong thư mục tạm. Tự dọn database/bản sao sau test.
Không đặt đơn thật. Kiểm tra lịch sử giá dùng bảng fixture order_details tối giản.
Cần MySQL, PHP mysqli/curl/mbstring/fileinfo, proc_open và quyền tạo database thử.

Bao phủ: danh sách, số lượng 0/24, tìm kiếm, phân trang, thêm/sửa, trim,
biên Unicode 100/2000, tên trống/quá dài/trùng theo collation, giữ tên khi sửa,
giữ form khi lỗi, ID sai/không tồn tại, GET endpoint, CSRF sai, customer bị chặn,
escape HTML, lỗi database không lộ chi tiết, dropdown sản phẩm và sidebar active.
So sánh toàn bộ sản phẩm và lịch sử giá fixture trước/sau bộ test danh mục.

## Kiểm tra thủ công trên trình duyệt

- Đăng nhập admin; mở Danh mục, kiểm tra giao diện AdminLTE và chỉ một mục sidebar active.
- Tìm tên, chuyển trang; kiểm tra từ khóa còn giữ và mô tả được rút gọn.
- Trong môi trường thử: thêm danh mục, sửa tên/mô tả, sửa giữ nguyên tên.
- Thử tên trống/trùng/quá dài và mô tả trên 2.000 ký tự; kiểm tra thông báo và dữ liệu giữ lại.
- Kiểm tra danh mục mới trong form thêm/sửa sản phẩm; không cần lưu sản phẩm.
- Đăng nhập customer và thử mở trang quản trị danh mục.

HTTP tests kiểm tra HTML và dữ liệu; chưa kiểm tra trực quan bằng trình duyệt.
