# Bộ lọc danh sách đơn Admin

URL: /cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/scr/admin/orders.php

- GET: q, status, from, to, page.
- q tối đa 100 ký tự Unicode. Chuỗi chỉ gồm số hoặc # trước số tìm đúng mã đơn.
  Ví dụ 123, #123, 000123 đều tìm mã 123. Chuỗi khác tìm tên hoặc điện thoại,
  ví dụ Nguyễn hoặc 090-123. Số điện thoại nhập hoàn toàn bằng chữ số cũng được hiểu là mã đơn,
  đúng quy tắc ưu tiên mã; giao diện có hướng dẫn điều này.
- Các ký tự LIKE %, _ và ! trong từ khóa được xử lý như ký tự thường.
- Trạng thái chỉ nhận các giá trị từ order_status_labels() hoặc rỗng.
- Ngày bắt buộc YYYY-MM-DD hợp lệ, từ ngày không lớn hơn đến ngày.
  Từ ngày bắt đầu 00:00:00; đến ngày gồm 23:59:59 theo TIMESTAMP giây hiện tại.
- Bộ lọc sai trả 400, thông báo tiếng Việt và không chạy truy vấn danh sách.
- 10 đơn/trang, ORDER BY id DESC. Page sai về 1; vượt phạm vi về trang cuối.
- COUNT và dữ liệu dùng cùng helper tạo điều kiện, giá trị truyền qua prepared statements.
- Phân trang dùng lại customer_page/customer_pagination hiện có.
- Form POST gửi return_to=list cùng list_context gồm các trường lọc và page.
  Server xác thực lại và tự tạo orders.php?... bằng http_build_query.
  Không chấp nhận URL quay về tùy ý. Cả lỗi CSRF và thành công đều giữ ngữ cảnh hợp lệ.
  Nếu đơn vừa cập nhật không còn khớp trạng thái lọc, đơn sẽ rời danh sách và số trang được tính lại.
- Giữ nguyên các chuyển trạng thái, transaction và hoàn kho. Không đổi dashboard/trang khác.

## Kiểm thử

Chạy từ gốc repository:

```powershell
D:/xampp/php/php.exe tests/product-management.php --isolated
```

340 kiểm tra đạt: 71 kiểm tra bộ lọc mới và 269 kiểm tra hồi quy.
Test dùng database motoparts_test_<random> và bản sao PHP tạm, tự dọn sau khi chạy.
Không ghi dữ liệu thật. Các cập nhật đơn chỉ thực hiện trên fixture.

Bao phủ mã/#mã, tên, điện thoại dạng chuỗi, mọi trạng thái, khoảng ngày riêng/kết hợp,
đầu ngày/cuối ngày, ngày nhuận, ngày/trạng thái sai, từ ngày lớn hơn đến ngày,
q quá dài/array, page sai/vượt phạm vi, COUNT và thứ tự không trùng,
giữ bộ lọc trong link và form, xác thực quyền, CSRF, URL quay về,
số trang giảm sau cập nhật và hồi quy chi tiết/transaction/hoàn kho.

## Checklist trình duyệt

- Mở danh sách, thử mã có/không #, tên, trạng thái và khoảng ngày.
- Chuyển trang, kiểm tra bộ lọc giữ nguyên; thử Xóa bộ lọc.
- Trên dữ liệu thử, cập nhật trạng thái từ danh sách đang lọc và kiểm tra URL/thông báo.
- Kiểm tra giao diện điện thoại, nhãn bộ lọc và bảng cuộn ngang.
- Kiểm tra Xem chi tiết và sidebar Đơn hàng.

Chưa kiểm tra trực quan bằng trình duyệt; kiểm thử HTTP đã kiểm tra HTML và dữ liệu.
