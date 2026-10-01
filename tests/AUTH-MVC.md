# Kiểm tra Auth MVC (giai đoạn 9)

Chạy từ gốc repository, XAMPP MySQL phải sẵn sàng:

```powershell
D:/xampp/php/php.exe tests/product-management.php --isolated
```

`auth-mvc.php` là suite được harness nạp sau checkout; không chạy riêng ngoài harness.
Harness chỉ đọc schema DB hiện tại, tạo `motoparts_test_<random>` và bản sao PHP trong
thư mục tạm; fixture user/order/session, PHP HTTP server và worker chỉ dùng DB thử.
Cuối chạy (kể cả assertion thất bại) harness drop DB thử, dừng server và dọn thư mục tạm.
Worker cạnh tranh được dừng trong finally. Không tạo account/order trên DB thật.

## Phạm vi

- Entrypoint mỏng; tách View/Controller/Service/Model và guard HTTP403.
- Register validation, array input, NUL, password 6–72 byte, email trùng/normalization,
  customer cưỡng chế từ server, hash, old input không password, escape HTML.
- Login đúng customer/admin, sai thông tin chung lỗi, SQL injection, CSRF, redirect cố định.
- Session ID đổi, public whitelist không hash, cart và token cart/Admin giữ khi login.
- Logout chỉ POST+auth CSRF, đổi session ID, xóa receipt/private data, giữ cart.
- Guest/customer/admin, role giả, session role sai, user đã xóa, ownership đơn hàng.
- DB unavailable: không xác thực nhầm, thông báo an toàn và 503 trên trang bảo vệ.
- Hồi quy checkout tự điền/user_id, toàn bộ module MVC 1–8.
- Hai PHP processes/kết nối DB đăng ký cùng email. Holder giữ insert chưa commit;
  contender vượt precheck nhưng chờ UNIQUE lock, kiểm chứng qua INNODB_TRX. Release holder:
  đúng một created, một DomainException tiếng Việt, một customer trong DB. Cần quyền
  đọc information_schema.INNODB_TRX (tài khoản XAMPP dùng chạy harness có quyền này).

## Kết quả mới nhất (2026-09-28)

Baseline **723/723**. RED trước mã nghiệp vụ: form login thiếu CSRF.
Cuối **800/800**, trong đó auth **77**, hồi quy **723**.
PHP lint **23/23**; git diff --check đạt; Apache app directory và 9 file auth mới HTTP403.
Đã chạy thật HTTP/DB/session/2 processes; không chỉ kiểm tra chuỗi mã nguồn.

Lỗi trung gian: MySQL chưa chạy; test cũ lấy nhầm auth token mới ở header; fixture race
ba giao dịch tạo deadlock 1213 do rollback blocker. Đã khởi động MySQL, đặt tên trường
logout riêng và sửa fixture thành hai đăng ký cạnh tranh, rồi chạy lại toàn bộ đạt.

Chưa kiểm tra UI trực quan, CSS/mobile và thao tác bằng trình duyệt. Checklist nằm ở
`docs/MVC-PHASE-9.md`. Cạnh tranh test ở Service/Model qua hai tiến trình, không phải
hai trình duyệt; builtin PHP server của harness xử lý HTTP tuần tự. Không test tải lớn.
Không có schema migration; không đổi dữ liệu thật; không tự commit.
