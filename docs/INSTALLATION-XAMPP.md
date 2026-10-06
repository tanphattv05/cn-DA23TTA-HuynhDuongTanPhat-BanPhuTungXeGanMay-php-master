# Cài MotoParts trên XAMPP

## Điều kiện

Đã kiểm tra với PHP 8.2.12 của XAMPP và MariaDB/MySQL dùng InnoDB, utf8mb4_general_ci.
Runtime cần mysqli/mysqlnd (get_result), mbstring, fileinfo, session và các extension
PHP chuẩn. GD là tùy chọn: nếu có, upload sẽ kiểm tra giải mã ảnh thêm. Test cần
curl, DOM/libxml, proc_open và quyền tạo/xóa database thử, đọc thông tin khóa InnoDB.
Máy kiểm thử hiện không có GD; không tuyên bố đã chạy nhánh GD.

1. Đặt thư mục dự án trong htdocs, giữ tên
   cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master.
2. Bật Apache và MySQL trong XAMPP Control Panel. Apache phải cho phép .htaccess:
   AllowOverride All hoặc nhóm tương đương hỗ trợ Options, authz và RedirectMatch;
   module alias/authz và PHP phải hoạt động. Không cần rewrite.
3. Dùng PHP 8.2 từ XAMPP để tránh thiếu mysqli/mysqlnd/mbstring so với PHP khác trong PATH.
4. Giữ quyền ghi session PHP và scr/assets/images/products cho tài khoản chạy Apache.
   Không cấp khả năng thực thi script trong thư mục ảnh.

Base URL hiện được dùng trong layout/middleware/index gốc theo đúng tên dự án nêu trên.
Không có cấu hình URL tự phát hiện. Nếu đổi tên thư mục hoặc dùng virtual host, cần
rà mọi base URL trong runtime và chạy lại test; tài liệu này giữ cấu hình hiện hành.

## Database mới

Với cài mới, trong phpMyAdmin tạo database trống tên phutung_xemay, charset utf8mb4,
collation utf8mb4_general_ci. Import [database-schema.sql](database-schema.sql).
File chỉ có 5 CREATE TABLE theo thứ tự categories, users, products, orders, order_details;
không có dữ liệu hoặc tài khoản mẫu, không tạo/drop database. Không import file này
vào DB đang có dữ liệu. Nếu phục hồi hệ thống đang dùng, dùng backup riêng của chủ dự án.

Chỉnh bốn biến kết nối host, username, password, database tại
scr/backend/config/database.php theo tài khoản DB do người cài quản lý. Đây là nguồn
triển khai duy nhất; không tạo lại scr/config/database.php. Không ghi credentials vào
tài liệu hoặc commit thay đổi chứa bí mật. File database.local.php trong ignore chưa
có cơ chế tự nạp, không nên tạo nó rồi giả định ứng dụng sẽ dùng.

Giữ nguyên schema: email UNIQUE, FK, DECIMAL(12,2), enum trạng thái. Schema snapshot
đã được đối chiếu với SHOW CREATE TABLE và import vào DB thử rỗng, rồi DB thử được dọn.

## Tạo Admin ban đầu bằng cơ chế hiện tại

Không có mật khẩu Admin mặc định hoặc endpoint công khai tạo Admin.

1. Trên bản cài mới do bạn quản lý, mở pages/register.php và đăng ký tài khoản của bạn
   bằng mật khẩu riêng; ứng dụng hash bằng password_hash và luôn tạo customer.
2. Trong phpMyAdmin của đúng DB mới, tìm dòng users vừa tạo bằng email/ID chính bạn
   đã xác nhận. Chỉ sửa cột role của đúng dòng đó từ customer thành admin; giữ nguyên
   password hash và các cột khác. Không dùng lệnh cập nhật toàn bộ users.
3. Đăng nhập lại qua pages/login.php; xác nhận chuyển tới admin/index.php. Không chia sẻ
   tài khoản này hoặc đưa nó vào SQL schema/test fixture.

Các bước trên là thao tác cài đặt do chủ hệ thống thực hiện; quá trình xác minh tự động
không tạo Admin/tài khoản/đơn thật và không sửa role trong DB thật.

## Kiểm tra sau cài

Mở /scr/: trang chủ 200. Guest mở Admin/history chuyển login; login Admin thấy dashboard.
DB mới không có sản phẩm/danh mục/đơn nên trạng thái trống và chỉ số 0 là bình thường.
Tạo dữ liệu thử trên bản cài thử trước khi kiểm tra checkout hoặc cập nhật trạng thái.
CSS/JS và ảnh phải trả 200; backend/frontend trả 403; thư mục không liệt kê nội dung.

Nếu gặp 503, kiểm tra MySQL đang chạy, DB tồn tại và extension/kết nối đúng; đọc log
server cục bộ, không bật display_errors cho người truy cập. Nếu 500 sau .htaccess,
kiểm tra module/AllowOverride trong Apache. Không xóa .htaccess để bỏ qua bảo vệ.
Nếu form cũ báo CSRF sau logout, tải lại form để nhận token mới.

Trước khi bảo trì dữ liệu đang dùng, sao lưu DB và thư mục ảnh riêng. Không dùng schema
cài mới để reset. [Kiểm thử](TESTING.md) mô tả cách chạy mà không ghi vào dữ liệu thật.
