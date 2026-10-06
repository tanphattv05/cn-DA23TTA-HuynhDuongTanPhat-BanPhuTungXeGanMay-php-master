# Kiểm tra di chuyển storefront — giai đoạn 12

```powershell
D:/xampp/php/php.exe tests/product-management.php --isolated
```

storefront-migration.php được harness nạp sau frontend-foundation.php.
Harness đọc schema, tạo DB ngẫu nhiên và bản sao PHP/session trong thư mục tạm.
Các test ghi và race dùng DB thử; finally dừng server, drop DB thử, dọn thư mục tạm.
Không thêm fixture endpoint vào website thật.

91 kiểm tra mới:

- 18 class chỉ tồn tại ở backend, reflection xác nhận autoload đúng file, HTTP403.
- 10 View chỉ tồn tại ở frontend, HTTP403; không còn bản logic trùng trong app.
- 16 entrypoint storefront dùng backend bootstrap.
- MVC đơn hàng Admin vẫn ở app, không bị di chuyển ngoài phạm vi.

Các suite storefront-product/cart/checkout/auth/storefront-order cập nhật đường dẫn
nội bộ sang backend/frontend, giữ các assertion nghiệp vụ. Các suite Admin và
foundation giữ nguyên. 899 kiểm tra cũ tiếp tục bao phủ session fixation, auth,
CSRF, quyền sở hữu đơn, checkout transaction/rollback/FOR UPDATE, tồn kho cạnh tranh,
giá lịch sử, đơn guest, receipt, upload và hoàn kho một lần.

Kết quả 2026-10-02: baseline 899; RED thiếu file backend trước chuyển; cuối **990/990**.
Lint **51/51**; git diff --check đạt; Apache trực tiếp 28 file mới trả 403.
Đối chiếu riêng nội dung 28 file với HEAD cho thấy chỉ di chuyển, không sửa code
(bỏ qua LF/CRLF). Apache các trang public trả 200, trang bảo vệ guest chuyển login.

Chưa kiểm tra trực quan/mobile hoặc load-test. Các request xác thực tự động dùng
PHP server bản sao cô lập; kiểm tra Apache ở website thật chỉ dùng request đọc guest.
