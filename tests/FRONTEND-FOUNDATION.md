# Kiểm tra nền móng frontend/backend — giai đoạn 11

```powershell
D:/xampp/php/php.exe tests/product-management.php --isolated
```

Suite frontend-foundation.php được harness nạp cuối, không chạy độc lập. Harness tạo
DB ngẫu nhiên và bản sao PHP vào thư mục tạm, fixture/session chỉ nằm trong bản sao.
finally dừng HTTP server, drop DB thử và dọn thư mục tạm. Không viết vào DB thật.

36 kiểm tra mới: Controller backend, URL /scr/ và index.php, guest/customer/admin,
lời chào escape, session/cart giữ nguyên, badge giỏ, logout POST+CSRF, link Admin,
layout xuất đúng một lần, CSS/JS/product URL, wrapper include cũ, guard 403,
home entrypoint mỏng, View không SQL/session, renderer duy nhất và chống traversal.
863 kiểm tra cũ bao phủ các module Admin/storefront, auth/cart/checkout/history,
CSRF, giao dịch/rollback, giá lịch sử, hoàn kho và cạnh tranh DB.

Kết quả ngày 2026-10-02: baseline 863/863; RED thiếu HomeController; cuối **899/899**.
Lint 15/15; git diff --check đạt. Apache thực xác nhận 200 trang chủ/assets/login,
403 frontend/backend và PHP nội bộ. Các test session có xác thực chạy trên bản sao
qua PHP built-in server; không tạo session fixture trong website thật.

Chưa kiểm tra trực quan, responsive bằng trình duyệt hoặc tải lớn. Checklist chi tiết
và quy ước chuyển module tiếp theo: docs/MVC-PHASE-11.md.
