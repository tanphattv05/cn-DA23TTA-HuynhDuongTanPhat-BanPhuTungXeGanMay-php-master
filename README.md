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
