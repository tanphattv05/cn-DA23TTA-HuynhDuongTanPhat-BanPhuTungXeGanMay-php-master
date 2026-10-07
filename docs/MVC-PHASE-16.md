# Giai đoạn 16 — Nâng cấp giao diện storefront

Ngày thực hiện: 06/10/2026. Đây là bước tiếp tục phát triển giao diện, không phải
bàn giao cuối. ZIP dự phòng giai đoạn 15 ở ngoài dự án không bị đọc/sửa hay tạo lại.

## Phạm vi và kiểm tra trước sửa

Đã đọc README, MVC-PHASE-14 và FINAL-HANDOVER; git status/diff ban đầu sạch.
Baseline mới chạy trên DB cô lập đạt **1176/1176**. Không có AGENTS.md trong workspace.
Frontend trước sửa có 11 View storefront, 3 include storefront, 9 View Admin và
3 include Admin. Giữ nguyên các vị trí đó, không thêm runtime module hoặc wrapper.

Danh sách storefront trước/sau tại scr/frontend:

```text
includes/storefront/{header,navbar,footer}.php
Views/storefront/home/index.php
Views/storefront/products/{index,detail}.php
Views/storefront/auth/{login,register,error}.php
Views/storefront/cart/index.php
Views/storefront/checkout/{index,success}.php
Views/storefront/orders/{index,detail}.php
```

## Những gì đã nâng cấp

- Header có biểu tượng MotoParts, cart badge, active nav, lời chào có xuống dòng cho
  tên dài, link Admin theo role sẵn có. Navbar Bootstrap đóng/mở trên điện thoại.
- Footer có giới thiệu, liên kết nhanh và hỗ trợ/giao hàng ở mức giới thiệu; không
  thêm số điện thoại, email, địa chỉ hoặc cam kết thời gian giao hàng giả.
- Trang chủ có hero đậm, minh họa bánh răng bằng CSS/Bootstrap Icons, CTA, lợi ích
  và nhóm nhu cầu bảo dưỡng. Nội dung tĩnh không giả làm sản phẩm lấy từ database.
- Catalog có card bằng chiều cao, ảnh contain, tên dài xuống dòng, giá rõ và empty
  state. Chi tiết có vùng ảnh, badge tồn kho, cảnh báo còn 1–5 sản phẩm, mô tả và nút
  hết hàng disabled; không tạo form thêm giỏ khi hết hàng.
- Giỏ hàng có ảnh, quantity label riêng từng sản phẩm, bảng cuộn bằng bàn phím,
  tổng cộng và các hành động rõ ràng; giữ form update nhiều sản phẩm và quantity 0.
- Login/register có auth card, mô tả, autocomplete và lỗi role=alert, không giữ
  lại password. Checkout có form và summary hai cột, COD, tổng tiền và action group.
- Thành công có biểu tượng, mã đơn; link chi tiết/lịch sử chỉ khi canViewOrder cho
  phép. Guest vẫn có thể tiếp tục mua hàng, không được suy đoán quyền sở hữu đơn.
- History/detail có bảng/card, nhãn trạng thái lấy từ metadata backend chung,
  giá lịch sử và thông tin nhận hàng. Không đổi phân quyền hoặc phản hồi 404.

## Thiết kế, responsive và accessibility

CSS chỉ tác động `.storefront-body`: ink #202328, canvas #f5f6f8, đỏ #c62032 và đỏ
đậm #a31526. Xanh lá dành cho thành công/còn hàng. Dùng system font, Bootstrap 5.3.3
và Bootstrap Icons 1.11.3 hiện có; không thêm CDN, font, thư viện hoặc JS runtime.
CSS có các phần variables/base/typography/layout/navbar/hero/benefits/products/forms/
cart/checkout/orders/empty/footer/utility/responsive. Hai !important chỉ ghi đè
utility màu badge của Bootstrap trong storefront, không ảnh hưởng metadata Admin.

Catalog 4 cột từ xl, 3 từ lg, 2 từ sm và 1 cột trên điện thoại nhỏ. Checkout một cột
dưới lg; navbar thu gọn dưới xl để đủ chỗ khi đăng nhập. Bảng dài nằm trong vùng cuộn
có tabindex/aria-label; không ẩn dữ liệu để tránh overflow. Ảnh giữ tỷ lệ bằng contain.
Tiêu đề hero nhóm cụm từ bằng span; kiểm tra heading dựa textContent thay vì HTML thô.

Có skip link, main landmark, h1, heading con, label for/id cho input hiển thị, alt
động được escape, aria toggle/current, focus-visible và reduced-motion. Trạng thái
có chữ đi kèm màu. Xem ảnh chụp đã phát hiện và sửa màu chữ link trên nút đặc, đồng
thời chỉnh gutter hero gây tràn vài pixel ở mobile; test kiểm clientWidth thực tế.

## File tạo/sửa

Sửa 14 file storefront trong danh sách trên và scr/assets/css/style.css.
Sửa tests/product-management.php để nạp UI suite; tests/frontend-foundation.php đổi
assertion tiêu đề sang DOM textContent, vẫn kiểm đúng nguyên văn và status 200.
Tạo tests/storefront-ui.php, tests/storefront-ui-browser.mjs,
tests/fixtures/storefront-ui-protected.json và tài liệu này.

Fingerprint lưu hash các file backend, Admin/asset vendor, View/layout Admin, ảnh,
JavaScript, pages/actions sau khi git diff xác nhận chúng giống baseline. Suite so
lại cả danh sách file và byte; nếu giai đoạn sau chủ đích sửa các vùng này, phải rà
soát và cập nhật fingerprint có kiểm soát, không cập nhật chỉ để làm test xanh.
Không lưu config text hoặc dữ liệu người dùng trong fingerprint.

## Kiểm thử

```powershell
D:/xampp/php/php.exe tests/product-management.php --isolated
D:/xampp/php/php.exe tests/storefront-ui.php --isolated
node tests/storefront-ui-browser.mjs
```

Hai lệnh PHP chạy cùng harness: **1339/1339**, gồm baseline **1176** và **163** kiểm
tra UI mới. Không cộng hai lượt chạy thành số assertion độc lập. PHP lint toàn bộ:
**119 file**, không lỗi; git diff --check đạt.

UI suite render View/layout thật qua HTTP ở bản sao tạm, dùng dữ liệu giả dài/XSS,
trạng thái hết hàng/trống/lỗi và các role. Kiểm form POST/action/token, label/alt,
heading/main/navbar, password không repopulate, bulk cart, token checkout, trạng thái
đơn, active navigation, CSRF logout và ranh giới View. Toàn bộ regression Admin,
ownership, checkout transaction, lock/rollback/stock vẫn chạy bằng database cô lập.

Edge headless có sẵn, Node 24, không cài npm package: **84 kiểm tra** trên trang chủ,
catalog, product-detail?id=1, cart trống, login/register ở 390/768/1440 px; Bootstrap
tải, không tràn ngang toàn trang, chữ nút rõ, menu mở/đóng ở 390 px. Chỉ GET và click
toggle, không đăng nhập/submit trên dữ liệu thật. Ảnh chụp trang chủ desktop/mobile
đã được xem lại. Profile/ảnh chụp ở TEMP riêng, không nằm trong website hoặc Git.

HTTP Apache mới:

| URL dưới /scr/ | Kết quả guest |
| --- | --- |
| /, pages/products.php, pages/product-detail.php?id=1 | 200 |
| pages/cart.php, pages/login.php, pages/register.php | 200 |
| pages/checkout.php, pages/order-success.php khi giỏ/receipt trống | 303 |
| pages/my-orders.php, pages/order-detail.php?id=1, admin/index.php | 302 |
| assets/css/style.css, assets/js/main.js, CSS AdminLTE | 200 |
| backend/, frontend/, config và include PHP nội bộ đã kiểm tra | 403 |

Không đổi URL, schema, dữ liệu thật, session, CSRF, action/method, validation server,
giá lịch sử hoặc nghiệp vụ tồn kho. Không sửa backend/AdminLTE/vendor/ảnh upload.
Không tạo tài khoản/đơn thật. Không tạo ZIP và không commit.

## Giới hạn và checklist XAMPP

Chưa thao tác trực quan toàn bộ trạng thái đăng nhập/cart có hàng/checkout/history
bằng trình duyệt; các trạng thái đó đã kiểm tra DOM trên fixture cô lập. Chưa dùng
screen reader, thiết bị iOS/Android thật hay tất cả mức zoom. Test browser cần Edge
ở đường dẫn Windows ghi trong script, Node có WebSocket và product ID 1 còn tồn tại;
thay ID kiểm tra có chủ đích nếu dữ liệu môi trường khác, không tạo sản phẩm thật.

Base URL: http://localhost/cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/scr/

1. Trang chủ `/`, catalog `pages/products.php`, chi tiết `pages/product-detail.php?id=1`:
   xem hero, card, ảnh, tên dài, CTA và footer ở desktop/mobile; dùng Ctrl+F5 nếu CSS cũ.
2. `pages/login.php`, `pages/register.php`: Tab/Shift+Tab, focus, lỗi, label và menu.
3. Trên DB thử, `pages/cart.php`: giỏ trống/có hàng, update nhiều dòng, quantity 0,
   nút xóa, cuộn bảng bằng bàn phím và tổng tiền.
4. `pages/checkout.php`: autofill, form lỗi, COD và layout hai/một cột. Chỉ đặt đơn
   trên DB thử để mở `pages/order-success.php` đúng receipt.
5. Đăng nhập customer trên bản thử: `pages/my-orders.php` và `pages/order-detail.php?id=...`;
   kiểm trạng thái/địa chỉ dài và quyền chủ đơn. ID dùng đơn của tài khoản thử.
6. Kiểm tra AdminLTE sidebar/dashboard như trước; navbar chỉ hiện link Admin cho admin.
