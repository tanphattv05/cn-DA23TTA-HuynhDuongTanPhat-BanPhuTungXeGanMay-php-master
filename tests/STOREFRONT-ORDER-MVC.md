# Kiểm tra MVC đơn khách hàng — giai đoạn 10

Chạy từ gốc repository khi MySQL XAMPP sẵn sàng:

```powershell
D:/xampp/php/php.exe tests/product-management.php --isolated
```

Suite storefront-order-mvc.php được harness nạp sau auth; không chạy riêng.
Harness đọc schema hiện tại, tạo DB motoparts_test_<random>, bản sao PHP và session
fixture trong thư mục tạm, chạy HTTP server loopback. Mọi user/product/order thử nằm
trong DB đó. finally dừng server, drop DB thử và dọn đúng thư mục tạm đã tạo, kể cả
khi assertion lỗi. Không dùng session đăng nhập hoặc ghi dữ liệu website thật.

## Phạm vi 63 kiểm tra mới

- Guest bị chuyển login cho hai URL; entrypoint mỏng, View/Controller không SQL,
  Model không request/session/password/SELECT wildcard.
- 12 đơn của customer A giữ đủ danh sách không phân trang, ID giảm dần; loại đơn B,
  vãng lai và admin dù trùng tên/số điện thoại; GET/POST user_id không ảnh hưởng quyền.
- Đọc chi tiết đúng chủ; admin tại storefront cũng phải đúng chủ; customer không vào Admin.
- ID thiếu/rỗng/0/âm/chữ/thập phân/quá lớn/array/SQL injection và đơn ngoại chủ trả 404;
  kiểm tra body giống hệt 404 đơn không tồn tại, không chỉ status.
- Năm nhãn trạng thái tiếng Việt, ngày, liên kết, layout storefront, trạng thái trống.
- Historical price, số lượng, line total và orders.total; đổi giá/tên products không
  đổi giá lịch sử. Tổng fixture cố ý khác sum để bảo đảm không tự tính lại tổng đơn.
- Escape tên người nhận, địa chỉ, ghi chú, tên sản phẩm; không lộ hash/HTML thực thi.
- Ảnh hợp lệ, sản phẩm không còn và ghi chú NULL. Mô phỏng legacy orphan bằng thay
  product_id với FOREIGN_KEY_CHECKS tắt tạm trên đúng kết nối DB thử, bật lại trong finally;
  không đổi schema hoặc FK của DB thật.
- Snapshot orders/order_details/products trước/sau các GET cho thấy không đổi đơn/tồn kho.
- Lỗi orders/order_details bằng rename bảng tạm, phục hồi trong finally: 503 an toàn.
- Model trực tiếp cũng ràng buộc ownership cho cả đơn và chi tiết; file nội bộ 403.
- Admin list/detail được kiểm tra lại; 800 kiểm tra cũ bao phủ sản phẩm/danh mục/customer,
  doanh thu, chuyển trạng thái/hoàn kho, cart, checkout, auth và cạnh tranh DB.

## Kết quả mới nhất: 2026-10-01

Baseline **800/800**. RED đúng tại entrypoint chưa MVC trước mã nghiệp vụ.
Sau chuyển: **863/863**, suite mới **63/63**. PHP lint **10/10**, git diff --check đạt.
Apache thực: app/ và 5 file MVC mới 403, hai URL storefront guest 302 về login.
Các kiểm tra HTTP có xác thực chạy trên bản sao cô lập qua PHP server, không mượn
tài khoản thật hoặc cài endpoint fixture vào website thật.

Chưa kiểm tra UI trực quan/mobile bằng trình duyệt, chưa load-test lịch sử lớn.
Giữ nguyên việc không phân trang và hiển thị tiền làm tròn đến đồng của phiên bản cũ.
Schema không có snapshot tên/ảnh nên chỉ bảo toàn giá/quantity lịch sử.
Xem checklist XAMPP tại docs/MVC-PHASE-10.md.
