# Kiểm tra MVC giỏ hàng

Chạy tại gốc repository:

```powershell
D:/xampp/php/php.exe tests/product-management.php --isolated
```

tests/cart-mvc.php được gọi sau suite storefront-product-mvc.
Không chạy riêng hoặc dùng fixture trên database thật.
Harness tạo DB motoparts_test_<random>, bản sao PHP tạm và máy chủ loopback;
tự dọn DB/thư mục thử trong finally. Suite kiểm đúng tên DB trước ghi.
Các endpoint cart-state.php chỉ được tạo trong bản sao tạm để kiểm session,
không tồn tại trong runtime scr. Tài khoản/đơn mới và thay đổi mật khẩu chỉ ở DB thử.

Kết quả: baseline 558; sau triển khai 645, gồm 87 kiểm tra mới.
Các kiểm tra cũ add_cart giai đoạn 6 được cập nhật token và status 303 theo hợp đồng mới.

Bao phủ:
- URL cũ mỏng, tách Controller/Model/Service/View và HTTP 403 nội bộ.
- Thêm guest/customer; admin xem storefront; cộng dồn và cap theo stock.
- ID/số lượng sai, thiếu hàng/không tồn tại, không tin giá/stock/return_to.
- CSRF thiếu/sai/mảng, GET không ghi, PRG, flash chỉ một lần.
- Cập nhật batch atomic, không thêm ngoài giỏ, từ chối vượt kho, zero=xóa.
- Xóa đúng một dòng, form POST không lồng nhau, ảnh, escape, navbar.
- Giá DB, stock giảm, sản phẩm xóa/hết; lỗi DB không xóa session.
- Login/logout thật bằng fixture; cart còn, user/token cũ mất.
- Checkout cũ đọc map, rỗng redirect, thiếu kho không tạo đơn;
  đơn thử thành công dùng giá DB, lưu giá lịch sử, trừ kho và unset cart.

Kiểm tra trực quan XAMPP:
- Dùng giỏ từ chi tiết sản phẩm, cập nhật/xóa bằng các nút; xem mobile.
- Kiểm tra thông báo, badge navbar, đường dẫn ảnh/tổng tiền và link quay lại.
- Đăng nhập/đăng xuất vẫn giữ giỏ.
- Chỉ trong DB thử: cập nhật stock/xóa sản phẩm rồi refresh giỏ; thử checkout.
- Mở /scr/app/Services/CartService.php phải nhận 403.

Chưa tự động kiểm tra pixel/layout, thao tác click và hộp confirm trong trình duyệt.