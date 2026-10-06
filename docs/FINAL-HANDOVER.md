# Bàn giao cuối MotoParts — giai đoạn 15

## Phạm vi và kiến trúc

Website PHP thuần/MySQLi gồm storefront sản phẩm, tài khoản, giỏ hàng, checkout,
receipt, lịch sử/chi tiết đơn đúng chủ sở hữu; Admin gồm dashboard, danh mục, sản
phẩm/upload, khách hàng và đơn hàng. Giai đoạn 15 không đổi nghiệp vụ hay schema.

```text
index.php                 # Chuyển hướng vào scr
scr/
  frontend/Views/         # storefront và admin
  frontend/includes/      # Layout storefront và AdminLTE
  backend/                # Controllers, Models, Services, Middleware, Core, Support
  backend/config/         # Gói chỉ chứa database.example.php
  pages/ actions/ admin/  # Entrypoint URL cũ (admin/assets giữ nguyên)
  assets/                 # CSS/JS; products chỉ có .htaccess trong ZIP
docs/ tests/ tools/       # Tài liệu, kiểm tra và script build
```

Entrypoint → Controller → Model/Service → View. Không fallback app, không wrapper
includes cũ, không bản website thứ hai. Namespace MotoParts\App vẫn giữ nguyên.

## Database và cài đặt

[Schema-only](database-schema.sql) có 5 bảng, chỉ DDL, không INSERT/credential/Admin
mẫu. Harness đối chiếu SHOW CREATE TABLE, import DB tạm ngẫu nhiên và xóa đúng DB
tạm trong finally. DB thật chỉ được đọc metadata, không ghi dữ liệu.

ZIP không chứa database.php hiện hành. Sao chép database.example.php thành
database.php và điền thông tin kết nối riêng trên máy cài. Không có mật khẩu/tài khoản
DB mẫu sử dụng được. Theo [README](../README.md) và [hướng dẫn XAMPP](INSTALLATION-XAMPP.md)
để import vào DB mới rỗng; đăng ký tài khoản riêng rồi đổi role đúng dòng đã xác minh
bằng phpMyAdmin. Không có endpoint tạo Admin công khai hoặc mật khẩu cố định.

## Bảo mật và dữ liệu đóng gói

Giữ prepared statements, CSRF, session và phân quyền, ràng buộc user_id, transaction,
FOR UPDATE, rollback, hoàn kho một lần và giá lịch sử. Apache chặn backend/frontend,
metadata/docs/tests/tools/dist; upload chặn PHP/PHTML. Xem [bảo mật](SECURITY.md).

Build dùng file tracked được cho phép cộng các file giai đoạn 15 được chỉ định,
không quét mù mọi file untracked. Loại .git, dist, thesis, config thật/local, .env,
logs/cache/session, file tạm/backup/database và mọi ảnh upload sản phẩm; giữ .htaccess
thư mục ảnh. Ảnh upload không bị xóa khỏi nguồn. Fixture trong mã test dùng dữ liệu
giả cô lập, không phải export người dùng thật. Không đưa SQL dump dữ liệu vào ZIP.

## Kiểm thử và bằng chứng

Baseline mới ngày 06/10/2026: **1176/1176**. Build bắt buộc chạy lại toàn bộ harness
(auth, cart, checkout, storefront order, Admin, schema và concurrency), final-handover,
Apache probe, lint toàn bộ PHP và git diff --check trước khi tạo ZIP.
final-handover kiểm tra file bắt buộc, đường dẫn cũ, ranh giới View/entrypoint, link
tài liệu, schema-only, HTTP/redirect/asset và cấu hình mẫu; chế độ package-root kiểm
tra nội dung đã lọc trước ZIP và sau khi giải nén ZIP vào thư mục tạm.
Build đối chiếu SHA256 từng file giải nén rồi mới xuất ZIP, dung lượng và SHA256 gói.
Các số chi tiết của lần build được báo trong output thực tế, không cộng baseline
hoặc hai lần kiểm tra cùng payload để làm tăng số kiểm tra độc lập.

Người dùng xác nhận đã kiểm thử thủ công đầy đủ giai đoạn 14. Giai đoạn 15 kiểm tra
HTTP tự động, không tuyên bố đã thao tác trình duyệt hoặc cài ZIP trên XAMPP thứ hai.
Giới hạn: base URL cố định theo tên thư mục; GD tùy chọn chưa có trên máy kiểm tra;
chưa load-test/production HTTPS; idempotency chưa bền vững nếu chết sau commit trước
khi session ghi. Snapshot asset lịch sử giai đoạn 14 không đại diện nội dung ZIP vì
ZIP chủ động bỏ ảnh upload. Không có dữ liệu hoặc danh mục/sản phẩm seed.

## Quy trình build và thử ZIP

```powershell
D:/xampp/php/php.exe tests/final-handover.php --isolated
powershell -File tools/build-handover.ps1
Get-FileHash dist/MotoParts-MVC-Final.zip -Algorithm SHA256
```

Build cần Git, PowerShell/.NET, PHP và Apache/MySQL của workspace đã cấu hình.
Không có skip-tests. Không sửa/xóa file nguồn, không tự commit. Stage ngẫu nhiên ở
TEMP được dọn sau kiểm tra; chỉ publish dist khi tất cả đạt. Git không nằm trong ZIP,
nên muốn build lại cần dùng repository phát triển, không phải bản cài đặt đã giải nén.

Checklist trên máy/XAMPP thử riêng:

1. Kiểm SHA256; giải nén đúng tên thư mục dưới htdocs, không đè website đang dùng.
2. Tạo DB rỗng và import schema; xác nhận 5 bảng rỗng, không Admin có sẵn.
3. Tạo database.php từ mẫu và cấu hình tài khoản DB riêng; bật Apache/MySQL.
4. Mở storefront và Admin theo README; kiểm CSS/JS, navbar, mobile, trang trống.
5. Đăng ký tài khoản, tạo quyền Admin an toàn; thử customer bị chặn Admin.
6. Tạo danh mục/sản phẩm/ảnh thử; kiểm giỏ hàng, checkout, lịch sử đúng tài khoản.
7. Kiểm chuyển trạng thái và hoàn kho một lần trên đơn thử; không dùng đơn thật.
8. Xác nhận PHP backend/frontend/upload bị chặn, không directory listing.

Toàn bộ thao tác ghi ở checklist do người cài thực hiện trên bản thử mới.
