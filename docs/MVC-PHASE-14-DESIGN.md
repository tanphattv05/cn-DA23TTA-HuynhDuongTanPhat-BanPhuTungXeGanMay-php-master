# Thiết kế giai đoạn 14

## Quyết định

Giữ một ứng dụng PHP server-rendered trong scr. frontend chỉ chứa View/layout;
backend giữ nghiệp vụ và truy cập DB. Không tạo frontend SPA hoặc API mới.
Entrypoint công khai giữ nguyên URL, nạp bootstrap backend và gọi Controller.
Namespace MotoParts\App được giữ để không đổi danh tính lớp khi đổi thư mục.

Sau giai đoạn 12–13, không còn module cần fallback app. Vì vậy bỏ fallback autoload
và View, xóa các wrapper đã không còn caller. Không duy trì hai bản helper/config.
Config duy nhất ở backend/config/database.php; helper Admin ở backend/Support/Admin.
Middleware/admin.php vẫn cần để khởi tạo phiên và kiểm tra quyền, không phải wrapper cũ.

Frontend không chứa SQL hoặc xử lý POST. Controller chọn template cố định, View
renderer chỉ tìm trong frontend/Views và reject đường dẫn không hợp lệ. Guard PHP
và .htaccess bảo vệ mã nội bộ. Assets giữ nguyên vị trí và URL, kể cả admin/assets.

## Dữ liệu và kiểm thử

Không migration DB. SQL cài đặt chỉ là 5 CREATE TABLE tương ứng metadata hiện hành,
không dữ liệu mẫu hay mật khẩu Admin. Kiểm tra khớp DDL rồi mới import vào DB ngẫu
nhiên rỗng; cleanup trong finally. Không chạy DDL trên DB thật.

Bảo toàn transaction, FOR UPDATE, rollback, giá lịch sử và quy tắc hoàn kho qua
harness cô lập hiện có. Kiểm tra Apache riêng vì PHP built-in server không đọc
.htaccess. Probe upload nằm trong thư mục tạm riêng, không vào ảnh người dùng.

## Phát hiện khi xác minh

Apache đã cho đọc .git/HEAD và liệt kê thư mục assets. Thêm .htaccess ở gốc với
Options -Indexes và chặn metadata/docs/tests/thesis. Đây là thay đổi kiểm soát truy
cập tài nguyên nội bộ; không rewrite hoặc thay giao diện/URL công khai.

Snapshot SHA256 phiên cũ không còn trong bộ nhớ công cụ. Lưu manifest mới khi tiếp
tục, đối chiếu cuối lượt và Git cho asset tracked; không coi đó là bằng chứng hash
trước toàn bộ giai đoạn 14. Chi tiết ở [báo cáo](MVC-PHASE-14.md).
