# Bảo mật và giới hạn hiện tại

## Ranh giới HTTP

frontend và backend có Require all denied trong .htaccess, cùng guard PHP
MOTOPARTS_MVC_ENTRY. Config chỉ nằm trong backend. Entrypoint public gọi Controller;
Admin xác thực bằng RequireRole và đọc lại tài khoản từ DB qua Authenticate.
Không có fallback về scr/app hoặc include cũ. Các URL nội bộ đã bỏ trả 404.

Audit tiếp tục ngày 05/10/2026 phát hiện Apache đang cho directory listing và đọc
.git/HEAD. .htaccess gốc nay dùng Options -Indexes, chặn HTTP vào .git/.codex/.agents/.aws,
docs/tests/thesis. Không đổi asset URL hoặc Apache global config, không dùng rewrite.
Mở tài liệu từ IDE/filesystem thay vì HTTP của website. Không sửa nội dung metadata.

## Input, CSRF và session

Prepared statements cho input SQL; validation phía server, escape dữ liệu HTML.
View không truy vấn hoặc xử lý POST. ID/role không tin từ trình duyệt; lịch sử/chi tiết
customer ràng buộc user_id đã xác thực ngay trong SQL, guest NULL không gán theo tên/phone.

Auth token riêng dùng random_bytes/hash_equals; login/register/logout có kiểm tra CSRF.
Logout POST, form navbar dùng auth_csrf_token. Cart/checkout dùng CartCsrf; Admin có
csrf_token riêng. Checkout còn token gửi một lần. Không truyền token qua query URL.
Login/logout session_regenerate_id(true), giữ cart; session user không chứa password/hash.
Logout xóa receipt/dữ liệu riêng tư/token cũ. Đăng ký bỏ qua role input; email UNIQUE
và xử lý lỗi duplicate key bảo vệ request cạnh tranh. Sai email/sai password chung lỗi.

## Upload

backend/Support/Admin/product-upload.php chỉ nhận JPEG/PNG/WebP ≤2 MB, kiểm tra lỗi
upload, is_uploaded_file, kích thước thật, MIME/fileinfo và getimagesize; giới hạn
20 triệu điểm ảnh. Nếu GD có sẵn sẽ decode pixel. Không SVG hoặc phần mở rộng tùy ý.
Tên ngẫu nhiên, reserve độc quyền, move_uploaded_file, DB chỉ lưu filename. Sửa không
thay ảnh giữ ảnh cũ; không xóa ảnh cũ dùng chung. Khi DB lỗi dọn đúng ảnh mới của thao tác.

scr/assets/images/products/.htaccess tắt ExecCGI/Indexes, tắt PHP engine khi module
hỗ trợ, chặn phần mở rộng thực thi. Test Apache dùng bản sao nguyên .htaccess trong
thư mục probe ngẫu nhiên: control PHP 200, PHP/phtml trong upload 403, PNG 200. Không
tạo probe trong thư mục ảnh thật; finally dọn đúng các file probe của lần chạy.

## Đơn hàng và lỗi

Checkout transaction/FOR UPDATE kiểm tra giá/tồn kho dưới lock, rollback khi lỗi;
Admin khóa đơn và hoàn kho/cập nhật status nguyên tử, không hủy hai lần. Giá lịch sử
lưu order_details.price. Receipt thành công nằm trong session và được dùng một lần.

Giới hạn idempotency: nếu tiến trình chết sau DB commit nhưng trước khi session được
ghi, token/receipt chưa phản ánh commit; không có khóa idempotency bền vững trong schema.
Không tuyên bố xử lý exactly-once cho cửa sổ lỗi này. Không đổi schema trong giai đoạn 14.

DB error không xuất SQL/credentials/stack trace trong luồng ứng dụng; log chỉ loại
exception/mã lỗi trong Controller. Trang phù hợp trả 503, lỗi form dùng flash/PRG.

## Giới hạn triển khai

XAMPP local không phải cấu hình production đã harden. Chưa thêm rate limiting,
remember-me, reset password, email verification hoặc social login. Cookie/HTTPS phụ
thuộc cấu hình PHP/Apache; chưa xác minh Secure/SameSite cho môi trường HTTPS production.
Chưa penetration-test, load-test hoặc kiểm tra trực quan responsive. Không cam kết
bảo mật tuyệt đối từ việc test xanh. Bảo vệ backup và file cấu hình ngoài cơ chế Git;
không chia sẻ mật khẩu thật hay sửa vendor để xử lý vấn đề ứng dụng.

Xem [kiểm thử](TESTING.md) và [cài đặt](INSTALLATION-XAMPP.md).
