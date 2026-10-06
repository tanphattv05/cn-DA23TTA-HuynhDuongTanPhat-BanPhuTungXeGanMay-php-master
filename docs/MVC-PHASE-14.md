# Bàn giao giai đoạn 14

Hoàn tất phần kiến trúc từ phiên bị ngắt, giữ nguyên nghiệp vụ. Baseline được bàn
giao 1085/1085; lần tạm trước đó 1163/1163. Chạy lại harness chính và entrypoint
architecture-finalization: **1176/1176**. Kiến trúc thêm 91 assertion so baseline
(78 đã có, 13 mới kiểm tra schema). Apache-security riêng **13/13**.

## Trạng thái cuối

scr/app, scr/includes, scr/admin/includes và scr/config không còn tồn tại.
Không còn wrapper tương thích; backend/Middleware/admin.php là middleware đang
hoạt động. Config duy nhất: scr/backend/config/database.php. Bảy helper Admin ở
scr/backend/Support/Admin. Toàn bộ 29 entrypoint dùng backend/bootstrap.php.
Frontend không SQL/POST; entrypoint không SQL/nghiệp vụ. Namespace giữ MotoParts\App.
Xem [cây thư mục](PROJECT-STRUCTURE.md) và [luồng MVC](ARCHITECTURE.md).

## File thay đổi

Giai đoạn 12–13 đã di chuyển Controller/Model/Service/View và đổi các entrypoint;
chúng còn chưa commit, không phải thay đổi mới làm lại trong lượt này.

- Tạo/di chuyển trong giai đoạn 14: backend/config/database.php;
  backend/Support/Admin/{category-input,customer-view,order-filters,order-status,
  product-bootstrap,product-upload,product-validation}.php.
- Sửa: backend/bootstrap.php, backend/Core/View.php; đường dẫn helper/config trong
  Controller Admin, Controller storefront Product/Cart/Checkout và Middleware/Authenticate;
  scr/.gitignore; các suite test có đường dẫn cũ và harness product-management.php.
- Xóa lớp tương thích: app/bootstrap.php, app/Core/View.php, app/.htaccess;
  includes/header.php, navbar.php, footer.php, .gitkeep; admin/auth.php, admin/navbar.php;
  toàn bộ admin/includes gồm header/footer và bảy helper trên; config/database.php,
  config/.gitkeep; test_database.php. Helper chuyển từ backend/Core/Admin sang Support/Admin.
- Tiếp tục lượt này: thêm .htaccess ở gốc; chuẩn hóa tests/architecture-final.php
  thành tests/architecture-finalization.php; thêm tests/apache-security.php.
- Tài liệu: sửa README.md; thêm ARCHITECTURE.md, PROJECT-STRUCTURE.md,
  INSTALLATION-XAMPP.md, TESTING.md, SECURITY.md, MVC-PHASE-14.md,
  MVC-PHASE-14-DESIGN.md, ASSET-SHA256.txt, database-schema.sql trong docs;
  tests/ARCHITECTURE-FINALIZATION.md và
  [kế hoạch](superpowers/plans/2026-10-05-architecture-finalization.md).

## Schema và bảo mật

[SQL](database-schema.sql) chỉ có 5 CREATE TABLE theo thứ tự phụ thuộc. Đã đối chiếu
cột, khóa, index, enum, charset/collation với SHOW CREATE TABLE; bỏ qua bộ đếm
AUTO_INCREMENT khi so sánh. Import vào motoparts_schema ngẫu nhiên thành công,
5 bảng đều rỗng, sau đó dọn DB thử. Không có INSERT, dữ liệu cá nhân, hash, credential
hay Admin mẫu. Không đổi schema/dữ liệu phutung_xemay.

Apache ban đầu để đọc .git/HEAD và directory listing assets. .htaccess mới chặn các
đường dẫn nội bộ repository và tắt listing. Không thay global Apache config hoặc
URL CSS/JS/ảnh. Probe upload sao chép đúng quy tắc hiện có: PHP/PHTML trả 403,
PNG trả 200, probe được dọn trong finally.

## Asset và giới hạn bằng chứng

111 file asset được ghi [SHA256](ASSET-SHA256.txt) ở đầu lượt tiếp tục và đối chiếu
sau cùng. Asset tracked không có diff so HEAD. Manifest của phiên trước chỉ nằm
trong bộ nhớ công cụ đã mất; không tuyên bố đã so lại hash trước toàn bộ giai đoạn 14.
Không sửa asset, vendor, thesis hoặc ảnh upload của người dùng.

## Kiểm tra và thử trực quan

Harness cô lập kiểm tra auth/cart/checkout/history và toàn bộ Admin, bao gồm cạnh
tranh tồn kho, FOR UPDATE, rollback, hoàn kho đúng một lần và giá lịch sử. Kiểm tra
quyền customer/Admin chạy bằng HTTP trên bản sao cô lập; không đăng nhập tài khoản
thật hoặc đặt đơn trên website hiện hành. Apache kiểm tra GET công khai và bảo vệ
file trực tiếp. Không dùng trình duyệt để đánh giá hiển thị desktop/mobile.

PHP lint: 116/116 file ứng dụng/test và index gốc đạt. git diff --check: exit 0.
Audit 10 tài liệu bàn giao: 32 link nội bộ hợp lệ, không còn dấu việc chưa hoàn tất.
Không thấy class hoặc hàm Support trùng, View/layout trùng nội dung, đường dẫn
Windows hard-code hoặc tham chiếu runtime vào thư mục đã bỏ. Toàn bộ 29 entrypoint
và 26 View/include frontend vượt kiểm tra ranh giới trong suite.

HTTP Apache thực tế:

| Đường dẫn/điều kiện | Kết quả |
| --- | --- |
| Trang chủ, products, product-detail?id=1, login, register, cart | 200 |
| Checkout giỏ trống; order-success chưa có receipt | 303 |
| my-orders và Admin chưa đăng nhập | 302 |
| CSS/JS storefront, CSS/JS AdminLTE, ảnh của product-detail?id=1 | 200 |
| 66 file PHP trong backend/frontend | 403 |
| app/bootstrap, includes/header, admin/auth, config/database, test_database cũ | 404 |
| Metadata repository và directory listing assets | 403 |

Hai URL AdminLTE đo đúng là admin/assets/adminlte/css/adminlte.min.css và
admin/assets/adminlte/js/adminlte.min.js. Ảnh được resolve từ URL trang chi tiết.
Không đổi đường dẫn asset để đáp ứng phép đo.

Không tự commit. Không tạo tài khoản/đơn thật. Xem [lệnh kiểm tra](TESTING.md).

Checklist XAMPP:

1. Bật Apache/MySQL, mở URL dự án với /scr/; xem trang chủ, sản phẩm, ảnh, CSS/JS.
2. Kiểm tra navbar/footer và giao diện mobile; login/logout với tài khoản của bạn.
3. Trên DB thử, kiểm tra giỏ hàng, checkout, receipt và lịch sử theo chủ đơn.
4. Admin: sidebar, dashboard, lọc/phân trang, upload sản phẩm và cập nhật trạng thái
   trên đơn thử; hủy lần hai không hoàn kho thêm.
5. Mở backend/frontend PHP trực tiếp phải 403, wrapper cũ 404; asset file vẫn 200.

Hướng dẫn URL và tạo Admin an toàn ở [cài đặt XAMPP](INSTALLATION-XAMPP.md).
