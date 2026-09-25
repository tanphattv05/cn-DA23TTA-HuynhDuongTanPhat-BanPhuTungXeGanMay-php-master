# MVC giai đoạn 8 — Thanh toán và tạo đơn

## File và cấu trúc

Runtime giữ nguyên trong scr, namespace MotoParts\App. Không thêm rewrite,
framework, schema hoặc thay đổi index.php gốc.

```text
scr/pages/checkout.php       → CheckoutController::index()
scr/actions/checkout.php     → CheckoutController::place()
scr/pages/order-success.php  → CheckoutController::success()
scr/app/
  Controllers/Storefront/CheckoutController.php
  Models/Checkout.php
  Services/CheckoutService.php
  Views/storefront/checkout/index.php
  Views/storefront/checkout/success.php
```

Các file hỗ trợ kiểm thử/tài liệu: tests/checkout-mvc.php,
tests/CHECKOUT-MVC.md, docs/MVC-PHASE-8.md. tests/product-management.php gọi suite
mới; tests/cart-mvc.php gửi token checkout và kiểm redirect 303.
Các thay đổi giai đoạn 7 chưa commit được giữ nguyên.

Controller nhận request, validation, session, chọn View và redirect; không SQL.
Model nhận MySQLi, dùng prepared statements, không đọc request/session.
Service điều phối transaction và tính tiền; không HTML hoặc request.
View dùng View::storefront và layout Bootstrap cũ, escape dữ liệu; không SQL.

## GET checkout

- Giỏ trống redirect 303 về cart.php với thông báo.
- Model lấy giá/tồn kho hiện tại, tái sử dụng CartProduct.
- CartService đối chiếu giỏ: loại sản phẩm không còn/hết hàng, giảm số lượng
  theo stock. Nếu thay đổi, quay về giỏ để người dùng kiểm tra trước khi đặt.
- Tự điền tên/điện thoại từ tài khoản DB theo ID session. Old input ưu tiên,
  kể cả giá trị rỗng. Admin vẫn mở được storefront.
- Giữ cấu trúc `$_SESSION['cart'] = [product_id => quantity]` và COD.
- Lỗi DB trả 503 có layout và thông báo chung. Tài khoản session không còn
  hợp lệ trả 409, không có form đặt hàng; không âm thầm chuyển thành guest.

## POST và validation

Chỉ POST tạo đơn. GET action redirect an toàn; đích redirect cố định, không nhận
URL return_to. Validation phía server từ chối array và UTF-8 sai, trim văn bản:

- Họ tên bắt buộc, tối đa 100 ký tự.
- Điện thoại bỏ khoảng trắng, phải có 9–11 chữ số.
- Địa chỉ bắt buộc, tối đa 500 ký tự theo form hiện có.
- Ghi chú tùy chọn, tối đa 500 ký tự theo schema.

Lỗi validation lưu checkout_old/checkout_error và redirect 303; không tạo đơn,
trừ kho hoặc xóa giỏ. Giá trị old input quá dài được giới hạn ở mức limit+1.
Không tin price, total, subtotal hoặc user_id từ POST.

## CSRF và chống gửi lặp

Tái sử dụng CartCsrf với cart_csrf_token (random_bytes, hash_equals), không thay
csrf_token Admin. Form có thêm checkout_submit_token sinh bằng random_bytes(32).
Token không ở URL và được kiểm tra trước khi tạo đơn.

PHP session handler hiện tại là files. Controller giữ khóa session từ
session_start đến khi commit và cập nhật cart/token hoàn tất, không đóng session
sớm. Request tiếp theo cùng session thấy submit token đã bị tiêu thụ sẽ bị từ
chối. Khi validation/transaction thất bại, token còn để người dùng sửa và thử lại.

**Giới hạn idempotency:** schema không có idempotency key UNIQUE. Nếu database đã
commit nhưng tiến trình chết trước khi session được ghi, gửi lại có thể tạo đơn
nữa. Không bảo đảm exactly-once qua sự cố này hoặc giữa các session độc lập.
Đổi session handler cần giữ cơ chế khóa tương đương; bảo đảm bền vững hơn cần
ràng buộc database ở giai đoạn được phép đổi schema.

## Transaction và tồn kho

1. Chuẩn hóa toàn bộ ID/quantity nguyên dương; từ chối giỏ có dòng sai.
2. Sắp xếp ID tăng dần bằng ksort SORT_NUMERIC, begin transaction.
3. Với người đăng nhập, kiểm tài khoản bằng LOCK IN SHARE MODE.
4. Đọc từng sản phẩm theo ID tăng dần bằng SELECT ... FOR UPDATE.
5. Kiểm toàn bộ sản phẩm, giá và stock sau khi đã lấy khóa.
6. Tạo orders với pending, tạo order_details và trừ kho.
7. UPDATE kho có điều kiện stock >= quantity và phải affected_rows = 1.
8. Commit chỉ khi tất cả thành công. Có lỗi thì rollback toàn bộ.

Không render hoặc redirect trong transaction. Chỉ sau commit Controller mới xóa
cart, old input, lỗi và submit token. Rollback giữ giỏ/old input. Lỗi DB được log
bằng exception class/code, không đưa SQL, credentials hoặc stack trace ra response.

## Tiền và giá lịch sử

Schema thực tế dùng DECIMAL(12,2) cho orders.total và order_details.price.
Service tính bằng số nguyên đơn vị 1/100 đồng, kiểm giới hạn 9999999999.99 trước
phép nhân để tránh tràn số, rồi bind chuỗi decimal. Không dùng float để tính số
tiền lưu DB. Runtime đã xác minh PHP_INT_SIZE=8. View vẫn định dạng đến đồng như cũ.

order_details.price lưu giá DB đã khóa lúc đặt, cùng product_id và quantity.
Tổng đơn bằng tổng các dòng. Schema không có tên sản phẩm lịch sử hoặc subtotal;
không tự thêm cột. Tên/ảnh tiếp tục theo products hiện tại, giá đơn cũ không đổi
khi Admin sửa giá sản phẩm.

## Customer, guest và trang thành công

User ID lấy từ session và xác minh DB; guest lưu NULL. Customer/admin còn tồn tại
giữ liên kết ID như hành vi cũ. Session sai hoặc tài khoản bị xóa bị từ chối,
không nhận user_id giả mạo từ request.

Sau commit, Controller lưu completed_order_id/completed_order_user_id trong
session và redirect 303 tới order-success.php. Trang success dùng receipt một
lần trong session, không lấy ID từ URL. Chỉ hiện link chi tiết khi user session
trùng chủ receipt. Refresh quay về mua hàng, không tạo thêm đơn hoặc lộ đơn khác.

Không thay đổi lịch sử/chi tiết customer, quy tắc trạng thái Admin hay hoàn kho.
Dashboard chỉ tính completed; đơn mới pending không được tính vào doanh thu.

## Kiểm thử

```powershell
D:/xampp/php/php.exe tests/product-management.php --isolated
```

- Baseline: 645/645. Sau giai đoạn 8: 723/723, gồm 78 kiểm tra checkout mới.
- Bao phủ validation, token, old input, guest/customer, giả mạo giá/user_id,
  giá lịch sử, tổng DECIMAL và giới hạn chống tràn số.
- Lỗi dòng chi tiết thứ hai, lỗi trừ kho thứ hai hoặc affected_rows=0 đều
  rollback toàn bộ và giữ giỏ/old/token.
- Hai tiến trình PHP/hai kết nối cùng mua 4 từ stock 5: bị chặn bởi khóa sản
  phẩm trước khi parent nhả khóa; một đơn thành công, một bị từ chối, stock còn 1.
- Kiểm tra replay token, refresh success, quyền xem đơn, Admin, dashboard và
  hoàn kho một lần; toàn bộ hồi quy giai đoạn 1–7 vẫn đạt.
- PHP lint 11 file PHP giai đoạn 8 và git diff --check đạt ở lần triển khai.
- Apache đã kiểm tra app/ và năm file MVC mới trả 403.
- Chỉ dùng DB motoparts_test_<random>/bản sao tạm, tự dọn; không đặt đơn thật.
- Chưa kiểm tra trực quan hoặc hai HTTP POST đồng thời qua Apache. Kiểm tra
  cạnh tranh dùng tiến trình PHP/DB thật; kiểm tra replay dùng HTTP tuần tự.

## Checklist XAMPP

Tiền tố URL:
http://localhost/cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/scr

1. Trên DB thử, thêm giỏ rồi mở /pages/checkout.php; xem mobile, COD, giá và tự điền.
2. Nhập sai người nhận: thông báo/old input đúng, giỏ không mất.
3. Đặt đơn thử: /pages/order-success.php hiện mã đơn, badge giỏ rỗng, mua tiếp.
4. Refresh/gửi lại request cũ không tạo đơn thứ hai trong cùng session.
5. Đối chiếu lịch sử customer, Admin, giá lịch sử và hủy hoàn kho một lần.
6. Mở /app/Services/CheckoutService.php: phải nhận 403.

Không dùng dữ liệu thật để thực hiện checklist có thao tác ghi. Không đổi schema
thật, sửa dữ liệu thật hoặc tự commit.
