# MVC giai đoạn 4 — Đơn hàng Admin

## Quy ước và phạm vi

Tiếp tục MVC giai đoạn 1–3: namespace `MotoParts\App`, class PascalCase,
template lowercase, autoload nội bộ và `View::admin` ghép layout AdminLTE.
Toàn bộ runtime nằm trong scr. Không thêm framework, dependency, rewrite hoặc schema.
index.php ở gốc giữ nguyên nhiệm vụ redirect vào scr.

```text
scr/
  admin/
    orders.php       -> OrderController::index()
    order-detail.php -> OrderController::detail()
    update-order.php -> OrderController::update()
    includes/
      order-filters.php  (validation bộ lọc và URL quay về)
      order-status.php   (nhãn, quy tắc chuyển trạng thái, định dạng tiền)
  app/
    Controllers/Admin/OrderController.php
    Models/Order.php
    Views/admin/orders/
      index.php
      detail.php
```

Request → URL cũ → bootstrap → OrderController → Order → View::admin → AdminLTE.

- Constructor Controller gọi helper hiện có, qua product-bootstrap.php/auth.php
  kiểm tra quyền trước truy vấn nghiệp vụ hoặc HTML.
- Controller xử lý GET/POST, ID, bộ lọc, phân trang, CSRF, flash session, 404/400/503,
  quy tắc chuyển trạng thái, điều phối transaction và redirect 303.
- Model chứa toàn bộ SQL đơn hàng: count/paginate dùng chung condition,
  find/items/totals, lock/restoreStock/updateStatus và begin/commit/rollback.
  Giá trị đầu vào dùng prepared statements, SELECT chỉ lấy cột cần thiết.
- SQL builder order_list_condition được chuyển thành Order::condition (private);
  không giữ bản cũ. Validation và return path vẫn dùng order-filters.php.
- View giữ markup/escape và liên kết tương đối theo URL cũ; không SQL hoặc đọc POST.
  Token CSRF được Controller truyền vào. Đường dẫn kiểm tra ảnh được điều chỉnh
  từ vị trí View mới về scr/assets/images/products, URL ảnh không đổi.
- order-status.php giữ nguyên là nguồn duy nhất cho nhãn và chuyển trạng thái,
  dùng chung với dashboard/khách hàng. Không sao chép quy tắc vào Model/View.

## Giao dịch cập nhật trạng thái

Chỉ POST đã qua auth và CSRF mới đi tiếp:

1. begin trên kết nối của Model.
2. SELECT id, status ... FOR UPDATE, đọc lại trạng thái hiện tại.
3. Controller kiểm tra order_transitions:
   pending → confirmed/cancelled; confirmed → shipping/cancelled;
   shipping → completed; completed/cancelled không đi tiếp.
4. Khi hủy, đọc chi tiết theo product_id và cộng lại quantity vào tồn kho.
   Bất kỳ sản phẩm không cập nhật được đều làm thất bại toàn bộ giao dịch.
5. Cập nhật trạng thái rồi commit.
6. Có lỗi: rollback cả tồn kho và trạng thái; lỗi MySQL chỉ hiện thông báo chung.

Không hoàn kho trước khi kiểm tra trạng thái dưới khóa. Request hủy lặp lại
thấy trạng thái cancelled và bị từ chối, không hoàn thêm.
Return destination chỉ nhận marker detail/list; đường dẫn được dựng nội bộ từ
ID hoặc bộ lọc hợp lệ, không nhận URL từ người dùng.

## Hành vi giữ nguyên

- Chuỗi toàn số hoặc #số tìm đúng ID (kể cả chuỗi giống số điện thoại);
  chuỗi khác tìm tên/điện thoại, escape ký tự LIKE.
- Bộ lọc trạng thái, từ/đến ngày bao gồm 23:59:59 ngày cuối; COUNT và danh sách
  dùng cùng điều kiện, ORDER BY id DESC, 10 dòng/trang, giữ bộ lọc khi redirect.
- ID sai/không tồn tại trả 404 theo layout; bộ lọc sai trả 400.
- Chi tiết dùng price của order_details, đối chiếu bằng phép tính DECIMAL;
  lệch orders.total chỉ cảnh báo. Không tính lại từ giá sản phẩm hiện tại.
- LEFT JOIN giữ dòng chi tiết khi sản phẩm không còn; đơn user_id NULL không
  được suy đoán chủ sở hữu. Thông tin nhận hàng lấy từ chính orders.
- Sidebar, dashboard, lịch sử khách hàng và các URL cũ giữ nguyên.
- GET update-order.php vẫn redirect 303, không ghi dữ liệu.
- Không sửa checkout, đặt hàng, frontend khách hàng, schema hay dữ liệu thật.

## Kiểm tra

Lệnh:

```powershell
D:/xampp/php/php.exe tests/product-management.php --isolated
```

- Trước sửa: 412/412 kiểm tra đạt.
- Sau tách MVC, trước thêm test: 412/412 kiểm tra cũ đạt, không đổi assertion nghiệp vụ.
- Sau thêm tests/order-mvc.php: 442/442 đạt (30 kiểm tra mới).
- Hồi quy: danh mục, sản phẩm/upload, khách hàng, MVC giai đoạn 1–3,
  danh sách/bộ lọc đơn, chi tiết và cập nhật trạng thái.
- Bao phủ giá lịch sử, đơn vãng lai, sản phẩm/tài khoản thiếu, cảnh báo tổng lệch,
  phân quyền, CSRF, chuyển trạng thái hợp lệ/sai, redirect nội bộ, hủy lặp,
  lỗi DB sau hoàn kho và sản phẩm thiếu đều rollback toàn bộ.
- Test MVC mới: file nội bộ bị chặn với admin/customer/khách, URL cũ,
  điểm vào mỏng, phân tách SQL/View/HTTP, không SELECT mật khẩu.
- Hai kết nối tới DB thử: kết nối thứ nhất khóa đơn, kết nối thứ hai nhận
  lock timeout 1205; sau rollback có thể lấy khóa, trạng thái/tồn kho không đổi.
- Chỉ dùng database motoparts_test_<random> và bản sao tạm, tự dọn sau chạy.
  Không tạo/cập nhật đơn hàng thật.
- PHP lint cho toàn bộ file PHP mới/sửa và git diff --check đạt.
- HTTP GET trên Apache XAMPP thực tế: scr/app/, Model, Controller và hai View
  đều 403; ba điểm vào cũ khi chưa đăng nhập đều 302 đến trang đăng nhập.
- Chưa kiểm tra bố cục/thao tác bằng trình duyệt; chưa thử hai HTTP POST đồng thời
  trên Apache. Test cạnh tranh khóa dùng hai kết nối DB, không giả định PHP
  built-in server chạy HTTP song song.

## URL và checklist XAMPP

Tiền tố: http://localhost/cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/scr

1. Đăng nhập admin, mở /admin/orders.php; thử mã/#mã, tên/điện thoại, trạng thái,
   khoảng ngày và phân trang. Kiểm tra sidebar chỉ active Đơn hàng.
2. Mở /admin/order-detail.php?id=<ID>; kiểm tra ảnh, thông tin nhận hàng,
   ghi chú, tiền lịch sử, nút quay lại và link từ dashboard/khách hàng.
3. Trên bản sao DB thử, cập nhật từ danh sách có bộ lọc và từ chi tiết:
   URL quay về đúng; thử hủy hai lần và kiểm tra tồn kho chỉ tăng một lần.
4. Thử đơn completed/cancelled không có form; ID sai hiển thị 404.
5. Đăng xuất hoặc dùng customer: ba URL Admin bị chặn.
6. Mở /app/Models/Order.php hoặc /app/Controllers/Admin/OrderController.php:
   phải nhận 403.