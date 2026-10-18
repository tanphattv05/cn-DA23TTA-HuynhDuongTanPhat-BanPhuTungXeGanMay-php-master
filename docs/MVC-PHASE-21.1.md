# Giai đoạn 21.1 — Hồ sơ khách hàng

- URL: `scr/pages/profile.php`; POST: `scr/actions/profile.php`.
- Luồng: entrypoint → `ProfileController` → `Profile` → `frontend/Views/storefront/profile/index.php`.
- Customer xem/sửa họ tên, email, điện thoại; đổi mật khẩu bằng mật khẩu hiện tại và xác nhận mật khẩu mới.
- Tái sử dụng Authenticate, AuthCsrf, AuthService::publicInput và layout storefront. Navbar customer có liên kết Hồ sơ của tôi thay vị trí lời chào để hạn chế tăng chiều rộng.
- Chỉ sử dụng ID được xác thực từ session; không nhận role/ID từ form. POST + CSRF, prepared statements, UNIQUE email hiện có và escape HTML.
- Tên tối đa 100 ký tự, email hợp lệ tối đa 100 byte, điện thoại 9–11 số. Mật khẩu giữ quy tắc đăng ký 6–72 byte; password_verify/password_hash, không lưu mật khẩu vào flash, HTML hay log.
- Đổi mật khẩu dùng compare-and-swap tránh ghi đè thay đổi đồng thời; đổi session ID và token Auth CSRF. Session hiện tại vẫn đăng nhập; các phiên trên thiết bị khác chưa bị thu hồi (không thêm schema/session registry).
- Dữ liệu form lỗi được giữ riêng theo tài khoản; cập nhật thành công làm mới thông tin session. Không cập nhật thông tin người nhận của đơn cũ.
- Không migration, không sổ địa chỉ, không sửa Admin/giỏ hàng/đơn hàng/favorites/reviews.

## Kiểm thử

`D:/xampp/php/php.exe tests/storefront-profile.php --isolated`

Bộ kiểm thử độc lập tạo database ngẫu nhiên và bản sao PHP tạm, chỉ ghi dữ liệu thử và dọn sau chạy. Bao phủ guest/customer/admin, GET bị chặn, CSRF, IDOR/role injection, validation, email trùng/giữ nguyên, XSS, session, đổi mật khẩu và POST lặp. Không chạy hồi quy toàn bộ hoặc browser.

Kết quả ngày 2026-10-19: 32/32 kiểm tra HTTP/database cô lập đạt; PHP lint 7/7 file tạo/sửa đạt; `git diff --check` đạt (Git có cảnh báo chuyển LF/CRLF cho navbar, không có lỗi whitespace).

File mới: Profile model/controller/view, hai entrypoint, test này và tài liệu. File sửa: navbar storefront.

## Checklist XAMPP

1. Đăng nhập customer, mở Hồ sơ của tôi; kiểm tra form trên điện thoại và desktop.
2. Sửa thông tin, thử email trùng/sai và điện thoại sai; kiểm tra thông báo, dữ liệu giữ lại và thông tin mới.
3. Thử mật khẩu hiện tại sai/xác nhận sai; đổi đúng rồi đăng xuất và đăng nhập bằng mật khẩu mới.
4. Guest chuyển đến đăng nhập; admin bị từ chối trang và POST hồ sơ.

Kiểm thử trực quan responsive chưa chạy; form dùng Bootstrap `col-12 col-lg-6` và layout hiện tại.
