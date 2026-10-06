# MotoParts — website bán phụ tùng xe gắn máy

PHP thuần + MySQLi trên XAMPP, giao diện Bootstrap và AdminLTE 4. Website hỗ trợ
sản phẩm, giỏ hàng, checkout, tài khoản, lịch sử đơn và khu vực quản trị.

## Kiến trúc hiện tại

```text
scr/
├── frontend/   # View và layout, không truy cập PHP trực tiếp
├── backend/    # Controller, Model, Service, Middleware, Core, Support, config
├── pages/      # URL storefront
├── actions/    # URL thao tác
├── admin/      # URL quản trị và asset AdminLTE hiện có
├── assets/     # CSS, JavaScript, ảnh công khai
└── index.php   # Điểm vào trang chủ
```

URL cũ → entrypoint mỏng → backend Controller → Model/Service → frontend View.
Không cần rewrite. Namespace PHP vẫn là `MotoParts\App`; thư mục `scr/app/` đã bỏ.
`index.php` ở gốc repository chỉ chuyển hướng vào `scr/`.

## Cài đặt và kiểm tra

Yêu cầu: Apache/XAMPP, PHP 8.2 với mysqli/mysqlnd, mbstring, fileinfo và session;
MySQL/MariaDB InnoDB. Test cần curl, DOM và proc_open. Không cần framework/Composer.

1. Giải nén vào htdocs với tên thư mục
   `cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master`, bật Apache/MySQL.
2. Tạo database mới rỗng `phutung_xemay` (utf8mb4_general_ci), import
   `docs/database-schema.sql` bằng phpMyAdmin. Không import đè DB đang dùng.
3. Gói ZIP không có config thật: chép `scr/backend/config/database.example.php`
   thành `database.php` cùng thư mục, điền tài khoản DB của máy cài đặt. Không ghi đè
   config của workspace đang hoạt động. Không đưa config đã điền vào gói phát hành.
4. Đăng ký tài khoản của bạn qua `scr/pages/register.php`, dùng mật khẩu riêng.
   Trong phpMyAdmin, xác minh đúng email/ID của tài khoản vừa tạo, chỉ đổi role dòng
   đó thành `admin`, rồi đăng nhập lại. Không có tài khoản/mật khẩu Admin hard-code.

Storefront: http://localhost/cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/scr/

Admin: http://localhost/cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/scr/admin/

Tên thư mục dự án nằm trong base URL hiện có. Khi thử ZIP ở một XAMPP mới, giữ tên
này; không giải nén đè dự án đang chạy. ZIP không kèm ảnh upload sản phẩm hoặc dữ liệu
kinh doanh. Tạo danh mục/sản phẩm và upload ảnh trên bản cài mới theo nhu cầu.

Làm theo [cài đặt XAMPP](docs/INSTALLATION-XAMPP.md), import
[schema-only](docs/database-schema.sql) vào database mới rỗng và cấu hình duy nhất
tại `scr/backend/config/database.php`. Schema không có tài khoản Admin mặc định.
Không import vào database đang dùng.

```powershell
D:/xampp/php/php.exe tests/product-management.php --isolated
D:/xampp/php/php.exe tests/architecture-finalization.php --isolated
D:/xampp/php/php.exe tests/apache-security.php --isolated
```

Kết quả giai đoạn 14: 1176/1176 assertion của harness, 13/13 kiểm tra Apache riêng.
Hai lệnh đầu chạy cùng harness; không cộng trùng số kiểm tra.

## Tài liệu

- [Kiến trúc và luồng xử lý](docs/ARCHITECTURE.md)
- [Cấu trúc dự án](docs/PROJECT-STRUCTURE.md)
- [Kiểm thử và dữ liệu cô lập](docs/TESTING.md)
- [Bảo mật và giới hạn hiện tại](docs/SECURITY.md)
- [Bàn giao giai đoạn 14](docs/MVC-PHASE-14.md)
- [Thiết kế giai đoạn 14](docs/MVC-PHASE-14-DESIGN.md)
- [Kế hoạch và kết quả thực hiện](docs/superpowers/plans/2026-10-05-architecture-finalization.md)
- [Suite kiến trúc](tests/ARCHITECTURE-FINALIZATION.md)

Tài liệu đọc qua repository/IDE; Apache chặn truy cập HTTP trực tiếp docs, tests và
metadata repository. Các tài liệu giai đoạn cũ mô tả lịch sử chuyển đổi.

## Đóng gói cuối và bảo mật

Chạy `D:/xampp/php/php.exe tests/final-handover.php --isolated` để kiểm tra bàn giao.
Chạy `powershell -File tools/build-handover.ps1` trong repository để kiểm tra lại và
tạo `dist/MotoParts-MVC-Final.zip`. Không có tùy chọn bỏ qua kiểm tra.
Xem [bàn giao cuối](docs/FINAL-HANDOVER.md) về phạm vi và kiểm thử ZIP.

Giữ .htaccess để chặn mã nội bộ và script upload; giữ CSRF/session/phân quyền hiện có.
Không chia sẻ database.php, session/cookie/token hoặc export dữ liệu người dùng.
XAMPP local chưa phải cấu hình production HTTPS đã được harden; giới hạn bảo mật
và idempotency checkout được ghi trong docs/SECURITY.md.
