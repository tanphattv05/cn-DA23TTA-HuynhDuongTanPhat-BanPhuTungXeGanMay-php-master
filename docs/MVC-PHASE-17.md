# Giai đoạn 17 — Catalog storefront

Hoàn tất xác minh ngày 07/10/2026. Phiên trước dừng do hết giới hạn sử dụng,
trước khi hoàn tất xác minh và tài liệu. Phiên tiếp tục giữ nguyên mã runtime
đã triển khai; bổ sung browser test phân trang/focus, chạy lại kiểm thử và viết tài liệu.

## Mục tiêu và luồng MVC

Giữ URL, giao diện giai đoạn 16/16.1 và nghiệp vụ hiện có. Catalog bổ sung tìm
tên/thương hiệu, danh mục, khoảng giá, tồn kho, sắp xếp và phân trang 12 dòng.

URL pages/products.php → backend/Controllers/Storefront/ProductController.php
→ backend/Models/StorefrontProduct.php → frontend/Views/storefront/products/index.php.

Controller chuẩn hóa GET và chọn View; Model thực hiện SQL; View chỉ hiển thị,
escape HTML, không xử lý POST. Entrypoint cũ không đổi. Lỗi database trả 503
với thông báo chung. Không thêm endpoint ghi hoặc thay đổi chi tiết sản phẩm/giỏ hàng.

## Quy tắc GET

| Tham số | Quy tắc |
|---|---|
| q | Trim, UTF-8 hợp lệ, tối đa 100 ký tự; tìm tên hoặc thương hiệu theo collation database. Quá dài/array báo lỗi; phần giữ lại được giới hạn. |
| category | Rỗng = tất cả; ID nguyên dương dạng chuẩn, tối đa 2147483647, phải tồn tại trong danh mục lấy từ database. Sai báo lỗi. |
| min_price / max_price | Rỗng = không giới hạn; 1–10 chữ số, từ 0 tới 9999999999; không dấu âm, thập phân, số mũ hoặc chữ. Min > max báo lỗi và giữ giá trị nhập. |
| stock | all, in_stock (> 0), out_of_stock (<= 0); sai/array về all. |
| sort | newest, price_asc, price_desc, name_asc; sai/array về newest. |
| page | Số nguyên dương trong miền PHP integer; sai/overflow về 1; vượt số trang về trang cuối; không kết quả là trang 1. |

Các trường ngoài q giới hạn chuỗi giữ lại 32 ký tự. Khi lỗi q/category/giá,
không thực hiện COUNT/list sản phẩm; hiển thị thông báo và form đã nhập.
Các giá trị giá bind dạng chuỗi, không chuyển float để tạo điều kiện SQL.

## Truy vấn và phân trang

- COUNT và danh sách gọi cùng conditions() và cùng JOIN danh mục.
- Tìm LIKE dùng ESCAPE '!': ! → !!, % → !%, _ → !_. Backslash là ký tự
  thường vì escape được chọn rõ là !; không tự ghép đầu vào vào SQL.
- Các giá trị tìm, danh mục, giá, LIMIT/OFFSET đều bind prepared statement.
  Statement đóng trong finally. Chỉ lấy cột cần thiết.
- Sort là ánh xạ SQL nội bộ: newest = created_at DESC, id DESC;
  price_asc/price_desc/name_asc tương ứng giá tăng/giảm/tên tăng, luôn có id DESC
  làm tiêu chí phụ. Không chèn chuỗi sort của GET trực tiếp vào SQL.
- Mỗi request hợp lệ dùng truy vấn danh mục, COUNT và một trang sản phẩm;
  không truy vấn riêng từng sản phẩm.
- Phân trang dùng http_build_query RFC3986, giữ bộ lọc không rỗng; bỏ giá trị
  mặc định stock=all và sort=newest. Hiển thị đầu/cuối, cửa sổ quanh trang hiện
  tại, Trước/Sau và dấu ba chấm. Trang hiện tại có aria-current.
- Form GET có label, không giữ page ẩn nên áp dụng bộ lọc trở lại trang đầu.
  Xóa bộ lọc trỏ products.php không query. Có số kết quả, empty state và badge hết hàng.

## File tạo/sửa trong toàn giai đoạn

Runtime (đã có trước phiên tiếp tục):

- scr/backend/Controllers/Storefront/ProductController.php
- scr/backend/Models/StorefrontProduct.php
- scr/frontend/Views/storefront/products/index.php
- scr/assets/css/style.css

Kiểm thử và tài liệu:

- tests/storefront-product-catalog.php (mới)
- tests/product-management.php
- tests/product-mvc.php
- tests/storefront-product-mvc.php
- tests/storefront-ui.php
- tests/storefront-ui-browser.mjs
- tests/fixtures/storefront-ui-protected.json
- docs/MVC-PHASE-17.md (mới)

Snapshot chỉ cập nhật fingerprint Controller/Model thuộc phạm vi và ghi nhận
ảnh upload có sẵn chưa tracked. Không sửa/xóa ảnh đó. Assertion ảnh cũ được
đổi sang tìm đúng sản phẩm, thay vì giả định luôn có ở trang đầu.

## Bằng chứng kiểm thử

Baseline phiên trước: ban đầu snapshot không nhận ảnh upload mới của người dùng;
sau ghi nhận ảnh, baseline đạt 1374/1374 trước thay đổi runtime.
Phiên tiếp tục ban đầu Apache/MySQL tắt; đã khởi động XAMPP hiện có, không đổi cấu hình.

Các lệnh được chạy lại thành công:

~~~text
D:/xampp/php/php.exe tests/product-management.php --isolated
1460/1460 (catalog 78, UI 206 nằm trong tổng này)

D:/xampp/php/php.exe tests/storefront-product-catalog.php --isolated --browser
1462/1462 (catalog 80 gồm 2 kiểm tra khởi động/kết thúc browser)
Browser cô lập: 324/324

node tests/storefront-ui-browser.mjs
Browser Apache: 291/291
~~~

Không cộng các tổng trên với nhau vì chạy lại cùng bộ hồi quy.

| Viewport | Browser cô lập | Browser Apache |
|---|---:|---:|
| 390 px | 116 | 105 |
| 768 px | 104 | 93 |
| 1440 px | 104 | 93 |

Browser Edge headless kiểm tra computed style, kích thước, overflow, focus,
label, CSS thực tải khớp file và cache version; không chỉ DOM. Fixture có 29
sản phẩm để bấm trang 1/2/3 (12/12/5 dòng), kiểm tra query được giữ, kết hợp
tìm/giá/sort, empty state và reset. Apache browser chỉ GET dữ liệu hiện có.
Ảnh chụp phục vụ rà soát, không phải kiểm thử thủ công của người dùng.

Catalog tự động kiểm tra wildcard %, _, !, backslash; collation/trim, XSS,
injection, category sai, giá âm/chữ/thập phân/mũ/quá lớn/đảo khoảng, stock/sort
allowlist, page sai/tràn/vượt, thứ tự tie, COUNT và các trang, prepared statement
thực tế, lỗi database an toàn. Hồi quy gồm Admin, sản phẩm, danh mục, khách hàng,
đơn hàng, auth, giỏ hàng, checkout, ownership và kiến trúc.

- PHP lint: 120/120 file, không lỗi.
- git diff --check: exit 0 (Git chỉ cảnh báo chuẩn hóa LF/CRLF).
- Apache: catalog và query kết hợp 200; CSS, JavaScript và ảnh upload 200;
  scr/backend/ và scr/frontend/ 403; Admin guest 302.
- Fixture chỉ trong database motoparts_test_* ngẫu nhiên; harness finally dọn
  database và bản sao runtime tạm. Không tạo đơn/tài khoản thật.
- Không sửa schema, cấu hình, dữ liệu thật, Admin, cart/checkout; không commit,
  không tạo ZIP. Ảnh upload của người dùng được giữ nguyên.

## Checklist XAMPP

Mở:
http://localhost/cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/scr/pages/products.php

1. Tìm theo tên/thương hiệu, thử ký tự %, _, ! và backslash.
2. Chọn danh mục, giá, tồn kho và từng cách sắp xếp; chia sẻ URL rồi mở lại.
3. Bấm trang đầu/giữa/cuối với dữ liệu đủ lớn; xác nhận bộ lọc giữ nguyên.
4. Thử URL min_price=2000&max_price=1000, page=-1, sort=bad; xem phản hồi phù hợp.
5. Tìm từ không tồn tại, bấm Xóa bộ lọc; kiểm tra link chi tiết và giỏ hàng
   trong môi trường thử, không tạo đơn thật.
6. Kiểm tra trực quan điện thoại/tablet/desktop, dùng Tab qua form và phân trang.

## Giới hạn

Chưa đo tải lớn hoặc kiểm thử trên thiết bị thật/nhiều trình duyệt; browser
tự động sử dụng Edge trên Windows. LIKE chứa từ có thể quét nhiều bản ghi;
không thêm index/schema trong giai đoạn này. COUNT và danh sách là hai truy vấn
đọc, không cung cấp snapshot cố định khi dữ liệu được thay đổi đồng thời.
Không thay quy tắc tồn kho NULL của schema hiện tại.
