# Kiểm thử MotoParts

Chạy từ thư mục repository bằng PHP của XAMPP:

```powershell
D:/xampp/php/php.exe tests/product-management.php --isolated
D:/xampp/php/php.exe tests/architecture-finalization.php --isolated
D:/xampp/php/php.exe tests/apache-security.php --isolated
```

Lệnh thứ hai là entrypoint CLI thay thế chạy cùng toàn bộ harness, không chỉ 91 assertion
kiến trúc. Các suite auth/cart/checkout/Admin là file được harness nạp; không gọi riêng
khi chưa có fixture. Không có file architecture-final.php trùng suite sau chuẩn hóa tên.

## Cô lập và dọn dữ liệu

Harness đọc config và SHOW CREATE TABLE của DB hiện hành, tạo motoparts_test_<random>,
bản sao PHP/session dưới thư mục tạm và PHP built-in server loopback. Mọi tài khoản,
đơn, upload thử nằm trong đó. finally dừng server, drop đúng DB đã tạo và dọn đúng thư
mục tạm. Worker race dùng DB thử. Schema installer tạo motoparts_schema_<random> riêng,
so sánh DDL với DB thật trước khi import, xác nhận 5 bảng rỗng và dọn trong finally.
Không import schema vào database thật; không copy dữ liệu người dùng làm fixture.

Tài khoản DB chạy harness cần CREATE/DROP database và quyền đọc thông tin khóa InnoDB.
Nếu tiến trình bị kill hoặc mất điện, finally có thể không chạy; chỉ dọn DB/thư mục
motoparts_test/motoparts_schema do lần kiểm tra đó tạo sau khi xác minh tên. Không reset
phutung_xemay. Không chạy các fixture endpoint trên website thật.

## Phạm vi

- Admin product/category/customer/order/dashboard: quyền, CSRF, input, lọc/tìm kiếm,
  pagination, upload/cleanup, doanh thu completed, giá lịch sử và trạng thái sidebar.
- Auth: hash, generic login error, fixation, public session whitelist, cart giữ qua
  login/logout, role giả/user đã xóa, UNIQUE email với hai kết nối.
- Cart/checkout: CSRF, stock, snapshot tiền, transaction/rollback/FOR UPDATE, request
  lặp token, khách đăng nhập/guest, receipt và cạnh tranh tồn kho bằng process riêng.
- History: owner-bound SQL, 404 đồng nhất, guest không lẫn, escape HTML và ảnh thiếu.
- Kiến trúc: entrypoint mỏng, frontend không SQL/POST, guard nội bộ, không wrapper,
  schema-only đúng DDL/index/FK/enum/collation và import an toàn.

Apache-security dùng probe PHP/PNG vô hại trong scr/_httpcheck_<random>, sao chép quy
tắc upload vào probe và dọn tất cả trong finally. Không ghi vào thư mục ảnh người dùng
hoặc DB. Nó kiểm tra Apache thật; built-in server không đọc .htaccess.

## Kết quả

Baseline trước giai đoạn 14: 1085/1085. Phiên trước đạt tạm 1163/1163.
Sau thêm xác minh schema, chạy lại ngày 05/10/2026: **1176/1176**; 91 assertion kiến trúc
cuối = 78 đã có + 13 schema. Apache-security riêng: **13/13**. Không cộng các lần chạy
lặp cùng harness để thổi số kiểm tra. Kết quả lint/HTTP/link/asset chi tiết ở
[báo cáo giai đoạn 14](MVC-PHASE-14.md).

Đã quan sát lỗi môi trường khi Apache/MySQL chưa chạy và khởi động dịch vụ trước test.
Các test trên DB cô lập không chứng minh giao diện trực quan desktop/mobile hoặc tải
đồng thời lớn. Auth/customer/Admin được kiểm tra bằng PHP built-in server cô lập; không
dùng tài khoản thật để tự đăng nhập trên website hiện hành.
