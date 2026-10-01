# MVC giai đoạn 9 — Đăng ký, đăng nhập, đăng xuất và phân quyền

## Kiến trúc

Một ứng dụng PHP/MySQLi, một server XAMPP; toàn bộ runtime tiếp tục ở `scr/`.
Không thêm framework, rewrite, API hoặc schema. `index.php` gốc không đổi.
Frontend gồm View, assets và layout; pages/actions/admin là entrypoint công khai.
Backend gồm Controller, Service, Model, Middleware, Core và config.

```text
scr/
  pages/login.php, register.php        → AuthController::loginForm/registerForm
  actions/login.php, register.php      → AuthController::login/register
  actions/logout.php                   → AuthController::logout
  admin/auth.php                       → RequireRole::enforce('admin')
  app/
    Controllers/Storefront/AuthController.php
    Models/UserAuth.php
    Services/AuthService.php
    Middleware/Authenticate.php, RequireRole.php
    Core/AuthCsrf.php
    Views/storefront/auth/login.php, register.php, error.php
```

Form → entrypoint → Controller → Service → Model → DB; Controller chọn View hoặc
redirect. View không SQL/POST; Controller và Service không SQL; Model không request/session.
Apache `scr/app/.htaccess` tiếp tục `Require all denied`; PHP còn có guard entrypoint.

## Luồng và validation

- GET form: kiểm tra session hiện có qua Middleware, lấy flash/old input và token,
  render Bootstrap cũ. User đang đăng nhập chuyển về storefront như trước.
- POST register: kiểm tra phương thức, CSRF; trim tên, lowercase/trim email,
  loại khoảng trắng điện thoại. Tên 1–100 ký tự UTF-8; email hợp lệ tối đa 100;
  điện thoại 9–11 chữ số. Không nhận role từ request.
- Mật khẩu giữ tối thiểu 6 byte của phiên bản cũ; thêm giới hạn 72 byte và từ chối NUL
  để tránh bcrypt cắt ngắn âm thầm/lỗi. Xác nhận phải trùng. `password_hash(PASSWORD_DEFAULT)`.
  Không tự đăng nhập; thành công 303 về login. Old input chỉ tên/email/phone, có giới hạn.
- Email kiểm tra trước và xử lý lỗi 1062 từ UNIQUE `users.email` hiện có. Hai kết nối
  cùng đăng ký vẫn chỉ tạo một customer. Không thêm index hay migration.
- POST login: CSRF, email chuẩn hóa, `password_verify`; email không tồn tại/sai mật khẩu
  dùng cùng thông báo. Whitelist public user được tạo sau xác thực. Customer 303 về
  `../index.php`; admin 303 về `../admin/index.php`. Không nhận URL quay lại từ request.
- POST logout: CSRF đúng mới xóa session riêng tư; giữ đúng mảng cart và đổi session ID.
  GET hoặc CSRF sai không đăng xuất, 303 về storefront. Navbar và cả hai nút Admin là form POST.
- Lỗi DB chỉ log loại exception và mã số; không log mật khẩu, hash, token hay session ID.
  Trang bảo vệ lỗi DB trả 503 thân thiện. Lỗi form dùng flash/PRG; dữ liệu xuất được escape.

## Session, CSRF và quyền

Trước/sau đều giữ `$_SESSION['user']` gồm `id`, `fullname`, `email`, `phone`, `role`;
ID là integer. Không đưa password/hash vào session. Cart vẫn `[product_id => quantity]`.
Login/logout gọi `session_regenerate_id(true)`; login giữ cart và CSRF cart/Admin.
Login xóa receipt, old recipient và phiếu gửi checkout cũ khi đổi danh tính.
Logout chỉ giữ cart: user, receipt, old input và token cũ bị xóa; các chức năng tạo lại
CSRF khi mở form tiếp theo. Tab cũ cần tải lại form sau logout.

Auth dùng session key `auth_csrf_token`, `random_bytes(32)` và `hash_equals`.
Form login/register gửi trường `csrf_token`; logout gửi `auth_csrf_token` để phân biệt
với token form nghiệp vụ trên cùng trang. Token không đi qua URL. Login xoay token auth.

Authenticate đọc lại ID/role/public fields từ DB, không lấy password. Session role ngoài
allowlist hoặc user không còn bị vô hiệu hóa; dữ liệu riêng tư bị xóa, cart giữ nguyên.
Role giả admin của customer không vượt DB role. Guest chuyển login; customer vào Admin
nhận 403; admin được phép. `admin/auth.php` vẫn cung cấp `$conn` và `$baseUrl` cho module cũ.

`my-orders.php` và `order-detail.php` chỉ thay phần bảo vệ đầu trang, giữ SQL ràng buộc
`orders.user_id` và giao diện hiện có; không chuyển hai trang này sang MVC.
Checkout chỉ dùng Middleware để lấy ID hợp lệ; không thay transaction/tính tiền/tồn kho.
Tự điền người nhận và gắn user_id được bộ hồi quy giai đoạn 8 kiểm tra.

## File bàn giao

Mới: 9 file runtime trong cây trên (gồm error view), `tests/auth-mvc.php`,
`tests/AUTH-MVC.md`, tài liệu này, `docs/superpowers/plans/2026-09-28-auth-mvc.md`.

Sửa: `scr/pages/login.php`, `register.php`, `my-orders.php`, `order-detail.php`;
`scr/actions/login.php`, `register.php`, `logout.php`; `scr/admin/auth.php`,
`scr/admin/includes/header.php`, `scr/includes/navbar.php`,
`scr/app/Controllers/Storefront/CheckoutController.php`;
`tests/product-management.php` (nạp suite), `tests/cart-mvc.php` (login/logout có CSRF/POST).

## Kết quả ngày 2026-09-28

- Baseline thực tế: 723/723, sau khi khởi động MySQL. Lần đầu MySQL chưa chạy nên không kết nối được.
- TDD RED: test mới thất bại tại form login chưa có CSRF trước khi viết mã nghiệp vụ.
- Kết quả cuối: **800/800**, gồm **77 kiểm tra auth mới** và 723 hồi quy giai đoạn 1–8.
- Kiểm tra email race: hai tiến trình/kết nối riêng; contender chờ unique index khi
  holder chưa commit, sau commit kết quả `created,rejected`, đúng một customer.
- Session fixation, giữ cart, CSRF, phân quyền, lỗi DB, SQL injection đều đạt.
- PHP lint: 23/23 file mới/sửa đạt; `git diff --check` đạt.
- Apache: app directory và 9 file runtime auth mới trả HTTP 403.
- Chưa kiểm tra trực quan bằng trình duyệt; HTTP/DB tự động dùng bản sao và database cô lập.

Các lỗi trung gian đã xử lý: token logout bị test cũ lấy nhầm thay token nghiệp vụ
(đã dùng tên trường riêng); fixture cạnh tranh ban đầu tạo thêm giao dịch chặn rồi rollback,
gây deadlock 1213 nhân tạo (đã thay bằng đúng hai đăng ký với barrier commit).
Không sửa logic nghiệp vụ để né lỗi fixture.

## URL và checklist XAMPP

Base: `http://localhost/cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/scr/`.
URL giữ nguyên: `pages/login.php`, `pages/register.php`, `actions/login.php`,
`actions/register.php`, `actions/logout.php`, `pages/my-orders.php`,
`pages/order-detail.php?id=...`, `admin/index.php` và các module Admin.

1. Trên bản sao DB thử: đăng ký hợp lệ, email trùng, dữ liệu sai; form giữ public input, không mật khẩu.
2. Thêm giỏ, login customer/admin; kiểm tra chuyển trang, navbar, cart và tự điền checkout.
3. Logout bằng nút ở storefront và hai vị trí Admin; cart còn, trang riêng yêu cầu đăng nhập.
4. Mở logout URL bằng GET: vẫn đang đăng nhập; form cũ sau logout cần tải lại token.
5. Customer không vào Admin, không xem đơn người khác; AdminLTE/sidebar vẫn đúng trên desktop/mobile.
6. Chỉ thử tạo đơn/tài khoản với dữ liệu cô lập; không dùng đơn thật để kiểm tra.

Không triển khai rehash, rate limiting, remember-me, quên mật khẩu hay social login trong giai đoạn này.
Không thay schema/dữ liệu thật, không commit; thư mục `thesis/` chưa theo dõi được giữ nguyên.
