# Giai đoạn 17.1 — Tìm kiếm trên navbar storefront

Ngày xác minh: 07/10/2026.

## Thay đổi

- Sửa scr/frontend/includes/storefront/navbar.php: form GET role=search, label
  visually-hidden, input q và nút kính lúp có aria-label. Dùng action cố định
  /cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/scr/pages/products.php.
- Sửa scr/assets/css/style.css: CSS scoped storefront, nền trắng/nút đỏ, hover
  và focus rõ. Từ 1400 px: logo–search–menu một hàng, search linh hoạt 360–520 px.
  Dưới 1400 px: logo/toggle hàng đầu, search 100% hàng sau, menu Bootstrap thu gọn.
  Tên đăng nhập dài trên desktop rút gọn bằng ellipsis, title giữ tên đầy đủ.
- Tạo tests/storefront-navbar-search.php; cập nhật tests/product-management.php,
  tests/storefront-ui.php và tests/storefront-ui-browser.mjs.
- Cập nhật đúng hai hash trong tests/fixtures/storefront-ui-protected.json:
  Controller/Model catalog đã chuyển LF sang CRLF trước phiên này. Hash sau
  chuẩn hóa LF khớp snapshot cũ; không chỉnh sửa file backend.

## Luồng GET và bảo toàn

Navbar → URL catalog cũ → Controller chuẩn hóa q → Model catalog → View.
Không thêm endpoint, SQL, xử lý POST hoặc validation trùng.
Navbar dùng filters.q đã chuẩn hóa do Controller cung cấp, chỉ khi entrypoint là
products.php; các trang khác để trống. Giá trị escape ENT_QUOTES | ENT_SUBSTITUTE.
Form chỉ có một trường có name là q; Enter và kính lúp cùng submit GET, không gửi
lại category/giá/stock/sort/page. Giữ menu active, badge, role, logout POST/CSRF,
session và URL CSS có ?v=hash.

## Kết quả kiểm thử mới

Git ban đầu sạch; giữ nguyên mọi file và ảnh có sẵn. Baseline ban đầu vướng
fingerprint CRLF nêu trên; sau xác minh/cập nhật hash, chạy lại đạt 1462/1462.

~~~text
D:/xampp/php/php.exe tests/storefront-navbar-search.php --isolated
1510/1510 — toàn bộ harness; navbar riêng 28.

D:/xampp/php/php.exe tests/storefront-navbar-search.php --isolated --navbar-browser
1512/1512 — thêm 2 assertion khởi động/kết thúc browser.
Browser: 387/387, trong đó navbar 63.
~~~

Không cộng hai lượt chạy thành tổng độc lập. Harness bao gồm hồi quy Admin,
catalog, cart, checkout, auth, đơn hàng và kiến trúc, chỉ ghi fixture vào database
tạm; finally dọn database và bản sao runtime tạm. Không dùng dữ liệu thật làm fixture.

| Viewport | Kiểm tra navbar | Kết quả |
|---|---:|---|
| 320 px | 13 | Đạt |
| 390 px | 13 | Đạt |
| 430 px | 13 | Đạt |
| 768 px | 13 | Đạt |
| 1440 px | 11 | Đạt |

Đo computed style/kích thước/overflow, focus bàn phím, menu đóng/mở, Enter/nút
cho cùng sản phẩm và chỉ q trong URL, CSS version, badge/CSRF và bố cục customer/admin
với tên dài. Kiểm thử PHP bao gồm ký tự %, _, !, backslash, dấu nháy, HTML,
q trống/khoảng trắng/array/quá dài, trang khác không điền q và Admin không có form.
Đã sửa selector bị escape sai trong test browser rồi chạy lại thành công.

- PHP lint: 121/121 file đạt.
- git diff --check: exit 0.
- Apache GET trang chủ/login/CSS/JS/ảnh: 200; frontend/backend: 403; Admin guest: 302.
- Đã chụp và xem navbar-search-390.png, navbar-search-1440.png tại
  C:/Users/tanph/AppData/Local/Temp/motoparts-ui-browser-ZRvShl/.
  Screenshot dùng fixture, ảnh sản phẩm fixture có thể không có file ảnh.
- Không sửa Admin, backend, schema, dữ liệu thật, config, nghiệp vụ giỏ hàng;
  không commit, không tạo ZIP.

## Mở thử và checklist

http://localhost/cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/scr/

1. Nhập tên hoặc thương hiệu, thử Enter và nút kính lúp.
2. Trên catalog đã có bộ lọc, tìm từ mới: URL chỉ còn q; từ khóa được điền lại.
3. Mở trang khác: ô tìm trống. Thử q có dấu nháy/HTML, khoảng trắng và không kết quả.
4. Kiểm tra menu mobile, Tab qua input/nút, badge và menu theo role trên bản thử.
5. Xem ở 320/390/430/768/1440 px; tên dài có title trên desktop. CSS giữ ?v=hash.

Browser tự động sử dụng Edge headless, chưa thay thế kiểm tra thiết bị thật,
screen reader và các trình duyệt khác.
