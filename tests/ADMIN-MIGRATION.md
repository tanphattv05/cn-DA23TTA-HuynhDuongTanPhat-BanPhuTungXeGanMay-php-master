# Kiểm tra di chuyển MVC Admin — giai đoạn 13

```powershell
D:/xampp/php/php.exe tests/product-management.php --isolated
```

admin-migration.php chạy cuối harness. DB motoparts_test_<random>, PHP server và
session fixture nằm trong bản sao tạm; cuối chạy dừng server, drop DB thử và dọn
thư mục tạm bằng finally. Không cài fixture endpoint vào website thật.

95 kiểm tra mới bao phủ vị trí duy nhất của 10 class và 9 View, autoload backend,
guard HTTP403 cho class/helper/layout, wrapper mỏng, entrypoint bootstrap mới,
sidebar đúng một mục active, asset URL và customer bị chặn qua wrapper.

990 kiểm tra cũ tiếp tục kiểm tra dashboard/doanh thu, CRUD không xóa category/product,
upload/cleanup, tìm kiếm/lọc/phân trang, giá lịch sử, quyền, CSRF, order transaction,
FOR UPDATE, rollback, hoàn kho một lần cùng toàn bộ storefront giai đoạn 12.

Baseline 990/990; RED thiếu backend Models/Category; cuối **1085/1085**.
Một lỗi tách template pagination được test phát hiện, đã sửa trước kết quả cuối.
Lint 62 PHP đạt; git diff --check đạt; Apache 30 file nội bộ 403, 12 Admin guest 302,
CSS/JS AdminLTE 200. Nội dung 5 Model và 9 View trùng bản trước di chuyển.

Kiểm tra Apache website thật chỉ GET guest/assets/internal. HTTP có xác thực và
ghi DB chỉ dùng harness cô lập. Chưa kiểm tra trực quan responsive/trình duyệt.
