# MVC giai đoạn 6 — Sản phẩm phía khách hàng

## Phạm vi và file

Giữ namespace MotoParts\App và quy ước MVC giai đoạn 1–5. Runtime chỉ nằm
trong scr; index.php ở gốc vẫn chỉ redirect, không có website thứ hai.

Tạo:
- scr/app/Controllers/Storefront/ProductController.php
- scr/app/Models/StorefrontProduct.php
- scr/app/Views/storefront/products/index.php
- scr/app/Views/storefront/products/detail.php
- tests/storefront-product-mvc.php
- tests/STOREFRONT-PRODUCT-MVC.md
- docs/MVC-PHASE-6.md

Sửa:
- scr/pages/products.php
- scr/pages/product-detail.php
- scr/app/Core/View.php (thêm storefront, giữ admin nguyên vẹn)
- scr/includes/footer.php (sửa URL main.js về /scr/assets/js/main.js)
- tests/product-management.php (thêm suite storefront cuối cùng)

## Luồng request

```text
scr/
  pages/products.php
    → app/bootstrap.php
    → Controllers/Storefront/ProductController::index()
    → Models/StorefrontProduct::all()
    → View::storefront → Views/storefront/products/index.php

  pages/product-detail.php?id=...
    → app/bootstrap.php
    → Controllers/Storefront/ProductController::detail()
    → validate ID → StorefrontProduct::find(id)
    → View::storefront → Views/storefront/products/detail.php
```

Điểm vào định nghĩa MOTOPARTS_MVC_ENTRY rồi gọi action cố định; không rewrite.
URL vẫn thuộc pages/, nên các liên kết tương đối đến sản phẩm/ảnh/action giỏ hàng
tiếp tục hoạt động. Renderer ghép header.php, navbar.php, template và footer.php
bằng đường dẫn filesystem tuyệt đối bên trong scr, không phụ thuộc cwd.
Renderer chỉ nhận tên template tĩnh, giới hạn storefront/ và cấm traversal.

Controller nhận GET, kiểm tra ID, gọi Model, chuẩn bị URL ảnh/trạng thái nút giỏ,
chọn View và xử lý 404/503. Không dùng bootstrap Admin hoặc yêu cầu đăng nhập.
Model nhận mysqli qua constructor, chỉ truy vấn dữ liệu công khai cần hiển thị,
INNER JOIN categories như bản cũ và ORDER BY products.id DESC. Chi tiết dùng
prepared statement. Không đọc request/session hoặc ghi DB.
View chỉ hiển thị, escape văn bản bằng htmlspecialchars, giữ Bootstrap và
number_format tiền Việt Nam. Không SQL hoặc đọc GET/POST.

## Schema và tương thích

Đã kiểm tra SHOW CREATE TABLE products/categories bằng truy vấn chỉ đọc.
products.id là INT, price DECIMAL(12,2), stock INT; image/brand/description cho
phép NULL. Không đổi schema. Không dùng chung Model Product của Admin để tránh
lẫn quyền ghi/đọc hoặc làm thay đổi logic Admin.

Giữ toàn bộ danh sách hiện có (không tự thêm phân trang/bộ lọc), tên/danh mục/
thương hiệu/giá/ảnh. Tồn kho và mô tả vẫn hiển thị ở chi tiết như bản cũ.
Giữ form POST ../actions/add_cart.php, product_id, quantity, min/max và trạng thái
hết hàng. Không sửa action giỏ, session, login/logout, CSRF hoặc checkout.
Navbar tiếp tục dùng session hiện có; header vẫn khởi động session trước HTML.

Ảnh vẫn ở scr/assets/images/products. URL tên file được basename/rawurlencode
và escape khi xuất; xử lý tên có khoảng trắng/ký tự đặc biệt nhất quán hai trang.
Phát hiện footer cũ trỏ /<project>/assets/js/main.js ngoài scr; sửa duy nhất URL
thành /<project>/scr/assets/js/main.js, file thực có sẵn (không tạo bản sao).

## 404, lỗi và bảo vệ HTTP

ID chấp nhận dạng chữ số nguyên dương chuẩn trong khoảng 1..2147483647.
Thiếu ID, mảng, 0, âm, chữ, thập phân, injection, overflow hoặc không tồn tại
đều trả 404 theo layout cũ có nút quay về. Không ép chuỗi sai thành ID khác.
Dấu +, khoảng trắng, số mũ và số có 0 đầu không được chấp nhận.

Lỗi kết nối/truy vấn trả 503 có header/navbar/footer và thông báo chung.
mysqli strict được bật trước nạp cấu hình; display_errors tắt cho hai trang.
error_log của PHP chỉ ghi tên exception và mã lỗi, không ghi thông điệp có thể
chứa SQL/credentials, request hoặc stack trace.
Lỗi 503 không giả làm danh sách rỗng/404.

scr/app/.htaccess giữ Require all denied. Mỗi file mới có guard.
Apache thực tế: app/ và cả 4 file Model/Controller/View đều trả 403;
pages/products.php 200; detail?id=0 404; /scr/assets/js/main.js 200.
Máy chủ PHP thử (không đọc .htaccess) cũng chặn bằng guard với cả ba loại session.

## Kiểm thử

```powershell
D:/xampp/php/php.exe tests/product-management.php --isolated
```

- Baseline trước sửa: 479/479 đạt.
- Sau chuyển đổi: 558/558 đạt; 79 kiểm tra storefront mới.
- Hồi quy Admin/MVC danh mục, sản phẩm, khách hàng, đơn hàng, Tổng quan đều đạt.
- Bao phủ dữ liệu công khai, liên kết, thứ tự/số lượng, giá, stock, danh mục,
  thương hiệu, ảnh có khoảng trắng, mô tả, NULL, hết hàng, danh sách rỗng.
- Bao phủ 404/SQL injection/XSS, URL cũ, MVC phân tách, guest/customer/admin,
  navbar đăng nhập, badge giỏ, add_cart/stock cap và không thay stock/price.
- Mô phỏng query failure và lỗi khởi tạo kết nối: 503 an toàn và log đã kiểm tra.
- PHP lint 10 file PHP mới/sửa và git diff --check đạt.
- Tất cả ghi fixture chỉ trên DB motoparts_test_<random> và bản sao tạm, tự dọn.
  Không ghi dữ liệu thật, không đổi schema thật, không commit.
- Chưa kiểm tra trực quan responsive/click/JavaScript bằng trình duyệt.
  Chưa chạy end-to-end checkout/đặt đơn; không thay mã các luồng đó.

## Kiểm tra thủ công

Tiền tố:
http://localhost/cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/scr

- /pages/products.php: kiểm tra card/ảnh/giá/danh mục/thương hiệu và mobile.
- /pages/product-detail.php?id=<ID có thật>: kiểm tra thông tin, ảnh, tồn kho,
  mô tả, số lượng, nút quay lại và thêm giỏ trên môi trường thử.
- /pages/product-detail.php?id=0 (hoặc thiếu ID): 404 có layout.
- Kiểm tra navbar khi đăng xuất/customer/admin, số lượng giỏ giữ nguyên.
- /app/Models/StorefrontProduct.php: 403.
- Network trình duyệt: /scr/assets/js/main.js và ảnh trả 200.