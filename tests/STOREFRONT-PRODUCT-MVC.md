# Kiểm tra sản phẩm storefront MVC

Chạy từ gốc repository:

```powershell
D:/xampp/php/php.exe tests/product-management.php --isolated
```

tests/storefront-product-mvc.php được harness gọi sau toàn bộ hồi quy Admin.
Không chạy độc lập, không trỏ fixture vào database thật.
Harness tạo DB motoparts_test_<random>, sao chép schema và mã vào thư mục tạm,
chạy PHP server loopback rồi tự dọn DB/thư mục thử trong finally.
Suite kiểm tra đúng tên DB trước ghi fixture; các thao tác xóa cuối chỉ dọn
fixture sản phẩm/đơn trong DB dùng một lần này. Không tạo đơn thật.

Kết quả giai đoạn 6: baseline 479, sau chuyển đổi 558 (79 kiểm tra mới).

Bao phủ:
- MVC, entrypoint mỏng và HTTP 403 file nội bộ với guest/customer/admin.
- Danh sách đầy đủ theo ID giảm dần, chi tiết, giá/stock/danh mục/thương hiệu.
- Ảnh và JavaScript HTTP 200; description/HTML đặc biệt, NULL, hết hàng/rỗng.
- ID thiếu, sai kiểu/mảng, âm/0/thập phân/overflow/injection/không tồn tại.
- Navbar/session cho ba loại người dùng, link chi tiết/quay lại.
- Action add_cart giữ nguyên: redirect, số lượng, badge và chặn vượt stock.
- GET và add_cart không thay giá/tồn kho.
- Query failure, connection initialization exception: 503 có layout,
  không lộ thông tin và có log an toàn.

Kiểm tra thủ công XAMPP:
1. Mở /scr/pages/products.php, xem desktop/mobile và chuyển đến chi tiết.
2. Kiểm tra ảnh, giá, thông tin, stock, mô tả và nút quay lại.
3. Trong môi trường thử, thêm số lượng 2 vào giỏ; quay về hai trang xem badge.
4. Thử hết hàng và ID sai; so sánh layout/thông báo.
5. Thử đăng xuất/customer/admin; không yêu cầu quyền Admin để xem catalog.
6. Mở /scr/app/Models/StorefrontProduct.php nhận 403.
7. Kiểm tra Network: ảnh và /scr/assets/js/main.js không 404.

Chưa tự động kiểm tra hiển thị trực quan/JavaScript và end-to-end checkout.