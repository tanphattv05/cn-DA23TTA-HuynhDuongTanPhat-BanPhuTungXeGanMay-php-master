# MVC giai đoạn 7 — Giỏ hàng storefront

## File và cấu trúc

Runtime vẫn chỉ ở scr. Không thay index.php gốc hoặc tạo website thứ hai.

Tạo:
- scr/app/Controllers/Storefront/CartController.php
- scr/app/Models/CartProduct.php
- scr/app/Services/CartService.php
- scr/app/Core/CartCsrf.php
- scr/app/Views/storefront/cart/index.php
- tests/cart-mvc.php
- tests/CART-MVC.md
- docs/MVC-PHASE-7.md

Sửa:
- scr/pages/cart.php
- scr/actions/add_cart.php
- scr/actions/update_cart.php
- scr/actions/remove_cart.php
- scr/actions/logout.php
- scr/app/Controllers/Storefront/ProductController.php
- scr/app/Views/storefront/products/detail.php
- tests/product-management.php
- tests/storefront-product-mvc.php

```text
scr/pages/cart.php          → CartController::index()
scr/actions/add_cart.php   → CartController::add()
scr/actions/update_cart.php → CartController::update()
scr/actions/remove_cart.php → CartController::remove()
                               ├─ CartCsrf
                               ├─ CartProduct (MySQLi, chỉ đọc)
                               ├─ CartService (session)
                               └─ View::storefront → storefront/cart/index.php
```

Namespace MotoParts\App và guard MOTOPARTS_MVC_ENTRY theo giai đoạn trước.
Giữ cả bốn URL. Không có chức năng xóa toàn giỏ ở bản cũ nên không thêm endpoint.
Mã app vẫn bị .htaccess Require all denied và guard chặn HTTP.

## Trách nhiệm và luồng

Controller khởi động session, kiểm phương thức/token/đầu vào, gọi Model/Service,
đặt flash và redirect; không SQL/HTML.
Model find/findMany chỉ SELECT id/name/price/image/stock. IN dùng danh sách
placeholder được tạo theo số ID, bind tất cả giá trị, không nối giá trị vào SQL.
Service nhận session theo tham chiếu, chuẩn hóa ID/số lượng, đọc/thêm/cập nhật/xóa,
tổng số lượng và đối chiếu dữ liệu hiện tại; không SQL hoặc HTML.
View nhận dữ liệu sẵn, escape, giữ Bootstrap/header/navbar/footer.

Thêm: POST → token → ID/số lượng nguyên dương → sản phẩm từ DB → Service cộng
số lượng hiện tại, giới hạn theo tồn kho → flash → 303 về ../pages/cart.php.
Tên/giá/tồn kho gửi từ trình duyệt bị bỏ qua.
Hết hàng/không tồn tại không sửa giỏ, có flash rõ ràng.

Cập nhật: POST → token → kiểm toàn bộ ID/số lượng/membership → lấy DB theo batch →
Service kiểm toàn bộ tồn kho → chỉ thay session khi tất cả hợp lệ.
Số lượng 0 vẫn là xóa như bản cũ; âm, mảng, thập phân và chuỗi sai bị từ chối.
Không thêm sản phẩm chưa có trong giỏ thông qua cập nhật.
Khác bản cũ: cập nhật vượt kho bị từ chối toàn bộ với thông báo, thay vì âm thầm
clamp hoặc cập nhật dở dang; thêm giỏ vẫn clamp theo quy ước cũ.

Xóa: POST → token → ID → chỉ xóa dòng tương ứng → flash → 303.
Nút Xóa đổi từ link GET sang form POST, giữ kiểu btn-outline-danger btn-sm.
Form cập nhật có id cart-update, input/button liên kết bằng thuộc tính form;
form xóa riêng, không lồng form. GET action chỉ redirect 303, không sửa giỏ.

## Session, tồn kho và thông báo

Cấu trúc trước/sau giữ nguyên:

```php
$_SESSION['cart'] = [
    12 => 2, // product_id => quantity
    19 => 1,
];
```

Không lưu tên/giá/tồn kho vào session. ID/số lượng chuẩn hóa thành số nguyên.
Khi hiển thị, lấy lại DB; tự giảm về stock hiện tại như trước, loại bỏ sản phẩm
đã xóa/hết hàng và thông báo. Việc đối chiếu hoàn tất trước navbar nên badge đúng.
Nếu DB thất bại, giữ nguyên session cart và trả 503 có layout, không coi lỗi là giỏ rỗng.
GET action không đổi giỏ; GET trang giỏ chỉ đối chiếu tồn kho như hành vi đã chọn.

Flash riêng cart_flash, hiển thị một lần rồi unset; thông báo điều chỉnh tồn kho
chỉ xuất hiện tại lần có thay đổi. Mọi thông báo đều escape.
Lỗi DB ghi error_log với class/code, không message chứa SQL/credentials.
POST lỗi vẫn PRG 303. Return URL cố định, không đọc return_to do trình duyệt gửi.

## CSRF và đăng nhập/đăng xuất

CartCsrf dùng random_bytes(32)/bin2hex, hash_equals; khóa cart_csrf_token riêng.
Không đổi csrf_token của Admin hoặc thêm/thay token checkout.
Form chi tiết sản phẩm và form cập nhật/xóa giỏ đều có hidden csrf_token.
Token không nằm trong URL. Thiếu/sai/array token không được sửa giỏ.

Login hiện có session_regenerate_id(true) và không xóa cart nên giữ nguyên mã.
Logout trước đây chỉ unset user và giữ lại toàn bộ dữ liệu khác.
Nay logout chỉ giữ cart, xóa session state còn lại (thông tin đăng nhập, dữ liệu
người nhận, flash và token), regenerate session ID. Token giỏ được tạo lại khi mở
trang có form; token trước logout không còn hợp lệ. Không giữ quyền Admin.

## Checkout

Không sửa scr/pages/checkout.php hoặc scr/actions/checkout.php.
Checkout vẫn đọc map ID→quantity, lấy giá từ DB; trang thanh toán giới hạn số lượng
theo stock, action đặt hàng kiểm tra lại dưới FOR UPDATE và từ chối nếu không đủ.
Sau thành công vẫn unset cart. Quy tắc transaction/trừ kho giữ nguyên.
Đã chạy luồng đặt hàng chỉ trên DB thử để kiểm chứng giá lịch sử, trừ kho và xóa giỏ.
Không tạo đơn hàng thật.

## Kiểm tra

```powershell
D:/xampp/php/php.exe tests/product-management.php --isolated
```

- Baseline trước sửa: 558/558 đạt.
- Sau triển khai: 645/645 đạt, thêm 87 kiểm tra cart-mvc.
- 558 kiểm tra cũ tiếp tục đạt; suite storefront giai đoạn 6 được cập nhật để gửi
  token mới và mong đợi PRG 303 thay 302 ở add_cart, không bỏ kiểm tra nghiệp vụ.
- Bao phủ guest/customer/admin, thêm lặp/clamp, ID/số lượng sai, update batch
  atomic, membership, zero/xóa, CSRF, GET, flash một lần, HTML, navbar/ảnh.
- Bao phủ giá/stock từ DB, sản phẩm đã xóa, stock giảm/hết, DB failure 503 và giữ giỏ.
- Login/logout thực với tài khoản fixture; token/auth xóa và cart còn nguyên.
- Checkout rỗng, checkout stock cũ bị từ chối; đơn thử thành công dùng giá DB,
  lưu giá lịch sử, trừ kho và xóa session cart.
- Toàn bộ hồi quy Admin/MVC giai đoạn 1–6 đạt.
- PHP lint 15 file PHP mới/sửa và git diff --check đạt.
- Apache thực tế trả 403 cho app/, Model, Controller, Service, CSRF và View mới.
- Chỉ fixture DB motoparts_test_<random>/thư mục tạm; harness tự dọn.
  Không đổi schema thật, không sửa dữ liệu thật và không tự commit.
- Chưa kiểm tra bố cục/click/thông báo confirm trực quan bằng trình duyệt.
  Kiểm thử giỏ/login/logout/checkout thay đổi session/DB chỉ chạy ở môi trường cô lập.

## Checklist XAMPP

Tiền tố: http://localhost/cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/scr

1. Mở /pages/products.php → chi tiết → thêm giỏ; kiểm tra redirect và flash.
2. Mở /pages/cart.php; thử cập nhật, nhập 0 để xóa, nút Xóa và navbar.
3. Kiểm tra desktop/mobile, ảnh/tổng tiền/nút thanh toán, refresh không thêm lại.
4. Trong môi trường thử: thêm vượt kho, nhập sai, token sai, cập nhật nhiều dòng sai.
5. Đăng nhập rồi đăng xuất: giỏ còn, tên/quyền đăng nhập biến mất.
6. Trên DB thử, giảm kho/xóa sản phẩm và tải lại giỏ để xem thông báo đối chiếu.
7. Trên DB thử, mở checkout/đặt hàng; không thử đặt đơn trên dữ liệu thật.
8. Mở /app/Services/CartService.php: phải trả 403.