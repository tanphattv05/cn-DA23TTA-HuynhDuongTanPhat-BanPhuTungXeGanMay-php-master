# Chi tiết đơn hàng Admin

Mở scr/admin/orders.php hoặc scr/admin/index.php, nhấn Xem chi tiết.
URL: /cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/scr/admin/order-detail.php?id=<ID>

## Hành vi

- GET chỉ đọc dữ liệu. ID sai hoặc không tồn tại trả 404 theo layout AdminLTE.
- Người nhận/địa chỉ/ghi chú/tổng tiền lấy từ orders; note NULL được xử lý.
- Đơn giá và thành tiền lấy từ order_details, không lấy products.price.
- Tên và ảnh là dữ liệu sản phẩm hiện tại vì schema không lưu bản chụp tên/ảnh.
  LEFT JOIN giữ dòng chi tiết khi bản ghi sản phẩm không còn; ảnh thiếu có nhãn thay thế.
- Tổng các dòng được đối chiếu với orders.total bằng DECIMAL trong MySQL.
  Nếu lệch, hiển thị cảnh báo và cả hai tổng, không sửa dữ liệu.
- Liên kết tài khoản chỉ theo orders.user_id và role customer.
  NULL có thể là khách vãng lai hoặc tài khoản đã xóa (FK SET NULL);
  không suy đoán bằng tên/điện thoại.
- Nhãn và chuyển trạng thái tập trung tại includes/order-status.php,
  dùng chung với danh sách, dashboard, chi tiết khách hàng và update-order.php.
- POST dùng endpoint update-order.php hiện có, CSRF và transaction khóa đơn.
  Quy tắc chuyển trạng thái và vòng lặp hoàn kho giữ nguyên.
- return_to chỉ nhận marker detail; server tự tạo URL nội bộ từ ID hợp lệ.
  Mọi giá trị URL tự gửi đều quay về danh sách. Sau POST dùng 303.
- completed/cancelled không có form cập nhật.
- Không đọc mật khẩu. Không đổi schema hoặc giao diện khách hàng.

## Kiểm thử

Chạy ở gốc repository:

```powershell
D:/xampp/php/php.exe tests/product-management.php --isolated
```

269 kiểm tra đạt: 55 sản phẩm, 81 danh mục, 69 khách hàng, 64 chi tiết đơn.
Tạo database motoparts_test_<random> và bản sao PHP tạm, tự dọn sau test.
Không ghi database thật, không đặt đơn thật.
Test mới dùng schema order_details thật trong database thử; fixture tham chiếu hỏng
được tạo bằng FOREIGN_KEY_CHECKS=0 trên riêng kết nối database thử, sau đó bật lại.

Bao phủ đơn đăng nhập/vãng lai, note NULL, tài khoản/sản phẩm thiếu, giá và tên sản phẩm thay đổi,
ảnh tồn tại, tổng tiền lệch và không lệch, 404, liên kết điều hướng, sidebar,
CSRF/phân quyền, mọi quy tắc chuyển trạng thái, hủy lặp chỉ hoàn kho một lần,
hoàn kho đúng số lượng, rollback khi UPDATE orders thất bại hoặc sản phẩm không còn,
giữ luồng quay về danh sách cũ và chống open redirect.
Test gửi tuần tự; chưa mô phỏng hai POST thực sự đồng thời. Khóa FOR UPDATE hiện có vẫn giữ nguyên.
HTTP tests không thay thế kiểm tra trực quan bằng trình duyệt.

## Checklist XAMPP

- Đăng nhập admin, mở chi tiết từ danh sách, dashboard và lịch sử khách hàng.
- Kiểm tra thông tin người nhận, ảnh, giá lịch sử, tổng tiền và mục sidebar Đơn hàng.
- Mở ID sai: kiểm tra trang 404 và nút quay lại.
- Trên môi trường thử, cập nhật trạng thái rồi kiểm tra URL quay lại và thông báo.
- Trên môi trường thử, hủy đơn một lần; thử gửi lại và xác nhận tồn kho không tăng lần nữa.
- Kiểm tra đơn completed/cancelled không còn form.
- Đăng xuất hoặc đăng nhập customer: không truy cập được chi tiết/cập nhật Admin.
