# Giai đoạn 18 — Sản phẩm yêu thích

Bàn giao bổ sung ngày 13/10/2026. Phiên trước hoàn thành triển khai và kiểm thử,
dừng do hết quota trước khi viết tài liệu. Phiên tiếp tục chỉ audit, lint và viết
tài liệu; không sửa mã nghiệp vụ, import database thật, commit hoặc tạo ZIP.

## Chức năng và luồng MVC

- Customer đã xác thực từ database được thêm/bỏ yêu thích, xem danh sách riêng.
- Guest được đưa đến đăng nhập bằng URL cố định; admin bị từ chối (403).
- Catalog và chi tiết có trái tim theo trạng thái lưu; navbar customer có Yêu thích.
- Trang yêu thích hiển thị ảnh nếu có, tên, giá, tồn kho, chi tiết và bỏ yêu thích.
  Thêm giỏ sử dụng nguyên endpoint add_cart.php và CSRF hiện có; hết hàng không có form thêm giỏ.
- pages/favorites.php → FavoriteController::index → Favorite → View favorites/index.
- actions/toggle_favorite.php → kiểm POST, Authenticate, role customer, CartCsrf,
  product_id và operation → Model → redirect 303 tới địa chỉ nội bộ cố định.
- Tên endpoint là toggle, nhưng operation là add/remove tường minh: thêm trùng
  không đảo thành bỏ; DELETE lặp không thêm lại. UNIQUE bảo vệ cặp user/product.
- user_id lấy từ tài khoản đã xác thực, không lấy từ GET/POST. Prepared statements
  và điều kiện user_id giới hạn mọi dữ liệu; View escape HTML, không SQL/xử lý POST.
- Catalog lấy trạng thái bằng một truy vấn theo danh sách ID đang hiển thị,
  không truy vấn từng sản phẩm. Dùng lại Authenticate, CartCsrf và helper ID của CartService.

Sau thao tác từ catalog/chi tiết, chuyển tới chi tiết sản phẩm tương ứng;
từ trang yêu thích thì quay lại favorites.php. Không nhận URL redirect tùy ý.
Đăng nhập không tự thực hiện lại thao tác yêu thích của guest.

## File tạo/sửa toàn giai đoạn

Tạo:

- scr/backend/Models/Favorite.php
- scr/backend/Controllers/Storefront/FavoriteController.php
- scr/frontend/Views/storefront/favorites/index.php
- scr/frontend/includes/storefront/favorite-button.php
- scr/pages/favorites.php
- scr/actions/toggle_favorite.php
- docs/migrations/phase-18-favorites.sql
- tests/storefront-favorites.php
- docs/MVC-PHASE-18.md

Sửa:

- scr/backend/Controllers/Storefront/ProductController.php
- scr/frontend/Views/storefront/products/index.php
- scr/frontend/Views/storefront/products/detail.php
- scr/frontend/includes/storefront/navbar.php
- tests/product-management.php
- tests/storefront-product-mvc.php
- tests/storefront-ui-browser.mjs
- tests/fixtures/storefront-ui-protected.json

Fixture navbar cũ được đối chiếu với tên tài khoản thực trong DB thử sau khi
Authenticate xác thực, thay vì tên giả tự gán vào session. Snapshot chỉ ghi nhận
Controller sản phẩm được tích hợp và các file backend/entrypoint yêu thích mới.
Không thay Admin, cấu hình, năm bảng cũ, ảnh, CSS hoặc nghiệp vụ cart/checkout/auth.

## Migration và cài đặt

File: [migrations/phase-18-favorites.sql](migrations/phase-18-favorites.sql).

Chỉ CREATE TABLE favorites với id, user_id, product_id, created_at;
UNIQUE(user_id, product_id), khóa ngoại tới users.id và products.id.
Cả hai ON DELETE CASCADE chỉ dọn bản ghi favorites phụ thuộc khi tài khoản/sản
phẩm bị xóa; không thay quy tắc xóa hoặc dữ liệu trong các bảng cha.
Kiểu ID INT có dấu và InnoDB tương thích schema hiện tại.

**Chưa import migration vào database thật.** Khi muốn bật chức năng:
sao lưu database, mở phpMyAdmin, chọn đúng phutung_xemay và import file migration
một lần khi bảng favorites chưa tồn tại. Bản cài mới cần import database-schema.sql
trước, rồi migration này. Không import lại schema gốc vào database đang có dữ liệu.

Khi thiếu migration, trang yêu thích trả 503 với thông báo chung; catalog vẫn
hoạt động, phần yêu thích báo chưa sẵn sàng. Không tự tạo bảng lúc chạy website.

## Kiểm thử và bằng chứng

Baseline phiên triển khai: 1512/1512. Lệnh đã chạy thành công trên DB cô lập:

~~~text
D:/xampp/php/php.exe tests/storefront-favorites.php --isolated --favorites-browser
~~~

- Tổng hồi quy: **1564/1564**, trong đó yêu thích **47** (gồm hai assertion
  khởi động/kết thúc browser).
- Browser: **405/405**, gồm 18 assertion yêu thích: 6 tại mỗi viewport 390/768/1440.
  Kiểm tra trạng thái, POST thêm/bỏ thật trên fixture, focus, computed style,
  kích thước/overflow và menu active; giữ kiểm thử catalog/navbar trước đó.
- Log: %TEMP%/motoparts-18-final.log. Phiên bàn giao đã đọc lại log thành công.
- Screenshot favorites-390.png, favorites-768.png, favorites-1440.png trong
  %TEMP%/motoparts-ui-browser-DMq1qi/; ảnh 390/1440 đã được xem trong phiên triển khai.
- PHP lint toàn bộ: 128/128; đã chạy lại khi bàn giao.
- git diff --check: đạt; Git chỉ cảnh báo chuẩn hóa LF/CRLF.
- Bộ mới đã require tại tests/product-management.php; không cộng các lượt chạy
  lại thành số kiểm tra độc lập.

Đã kiểm guest/customer/admin, phương thức POST, CSRF sai/mảng, ID sai/injection,
sản phẩm không tồn tại, thêm lặp, XSS, bỏ yêu thích, dữ liệu giữa hai tài khoản,
session tài khoản đã xóa, sản phẩm còn/hết hàng và endpoint giỏ hàng hiện có.
Đã import migration trong DB thử, kiểm UNIQUE, khóa ngoại, cascade xóa tài khoản/
sản phẩm và giả lập thiếu bảng để xác nhận catalog vẫn chạy.

Harness chỉ ghi database motoparts_test_* ngẫu nhiên và bản sao runtime tạm;
finally dọn chúng. Năm bảng/schema thật không bị sửa. HTTP Apache phiên triển khai:
catalog/CSS/JS 200; favorites guest 302; toggle bằng GET 405; file nội bộ 403;
Admin guest 302. Không chạy lại DB/browser khi bàn giao vì không sửa mã chạy.

## URL và checklist thủ công

Base:
http://localhost/cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/scr/

1. Sau import migration, đăng nhập customer, mở pages/products.php và
   pages/product-detail.php?id=ID_THỰC; lưu/bỏ, tải lại kiểm tra trái tim.
2. Mở pages/favorites.php: kiểm ảnh, giá, tồn kho, chi tiết và bỏ yêu thích.
3. Thử thêm vào giỏ với sản phẩm còn hàng; sản phẩm hết hàng không có nút thêm giỏ.
4. Dùng hai customer thử nghiệm để kiểm dữ liệu riêng; guest phải đăng nhập,
   admin không được thao tác yêu thích.
5. Thử trên điện thoại/desktop, kiểm menu active, navbar search và phân trang catalog.

## Giới hạn

- Cần người vận hành import migration trước khi dùng yêu thích trên database thật.
- Danh sách yêu thích chưa phân trang; chưa đo tải với danh sách rất lớn.
- Đã kiểm Edge headless; chưa thay thế kiểm tra thiết bị thật hoặc screen reader.
- Gửi yêu thích từ catalog chuyển tới chi tiết, không quay lại nguyên bộ lọc.
- Không bổ sung chức năng quản trị yêu thích, AJAX hoặc giai đoạn 19.
