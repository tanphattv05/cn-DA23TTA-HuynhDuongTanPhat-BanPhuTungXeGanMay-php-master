# Kiến trúc MotoParts

Tài liệu hiện hành sau giai đoạn 14, xác minh ngày 05/10/2026. Nhật ký MVC-PHASE-1
đến 13 mô tả trạng thái lịch sử; không dùng đường dẫn cũ trong đó để triển khai mới.

## Một ứng dụng, hai vùng trách nhiệm

Runtime nằm trong scr. Frontend chứa View/layout, backend chứa Controller, Model,
Service, Middleware, Core, Support và cấu hình. Đây không phải hai server hoặc API
riêng. PHP render HTML phía server; JavaScript/CSS/ảnh giữ URL công khai hiện có.

Luồng: URL → entrypoint → backend/bootstrap.php → Controller → Middleware/Service/Model
→ database → Controller → Core/View → frontend/Views → frontend/includes.

Namespace vẫn là MotoParts\App để giữ API PHP ổn định; autoload chỉ tìm trong backend.
View chỉ tìm trong frontend/Views. Không còn fallback về app, wrapper vòng lặp hoặc
bản triển khai dự phòng. Tên template do code chọn, được kiểm tra chống traversal.

## Trách nhiệm

| Thành phần | Vai trò |
|---|---|
| scr/index.php, pages, actions, admin | Entrypoint mỏng: guard, bootstrap, gọi action |
| Controllers/Storefront | Sản phẩm, auth, cart, checkout, lịch sử đơn, trang chủ |
| Controllers/Admin | Dashboard, danh mục, sản phẩm, khách hàng, đơn hàng |
| Models | Prepared statements, dữ liệu và thao tác transaction/lock |
| Services | Validation nghiệp vụ auth/cart/checkout; không render HTML |
| Middleware | Xác thực session với DB, yêu cầu đăng nhập và role Admin |
| Core | Autoload qua bootstrap, View, CSRF và metadata trạng thái |
| Support/Admin | Helper validation/upload/bộ lọc/định dạng và khởi tạo Admin |
| frontend/Views | Hiển thị, escape dữ liệu; không SQL hoặc xử lý POST |
| frontend/includes | Header/navbar/footer, sidebar và phân trang |

Support/Admin là mã helper có chức năng thật, không phải lớp tương thích. Hàm phân
trang nạp template frontend; phần xử lý input và URL ở backend. Navbar đọc trạng thái
session cho hiển thị, quyền thật được kiểm tra ở middleware trước xử lý nghiệp vụ.

## Dữ liệu và bất biến

- users: email unique theo utf8mb4_general_ci; role customer/admin. Password chỉ đọc
  trong luồng verify của UserAuth, không đưa hash vào session hoặc View.
- products thuộc categories; xóa category có thể cascade sản phẩm nên UI không thêm xóa.
- orders lưu snapshot người nhận, note nullable, total và status; user_id nullable cho guest.
- order_details lưu quantity và price lịch sử; không dùng giá products để sửa đơn cũ.
- Schema không lưu snapshot tên/ảnh: hiển thị tên/ảnh hiện tại hoặc nhãn thay thế nếu thiếu.

Chi tiết schema: [database-schema.sql](database-schema.sql), chỉ dùng để cài DB trống.

## Session và giao dịch

Session user gồm id, fullname, email, phone, role; cart là product_id → quantity.
Login/logout đổi session ID; logout xóa dữ liệu riêng tư và giữ cart. Auth CSRF tách
khỏi cart/checkout và Admin. Customer chỉ xem đơn theo cả order ID và user_id đã xác thực.

Checkout giữ PHP session lock đến sau commit và cập nhật token/cart. Model khóa sản phẩm
bằng FOR UPDATE; Service kiểm tra tồn kho, tính tiền bằng số nguyên đơn vị phần trăm,
lưu orders/order_details và trừ kho trong transaction; rollback khi lỗi.

Admin pending → confirmed/cancelled; confirmed → shipping/cancelled;
shipping → completed. Completed/cancelled không đổi tiếp. Khóa đơn trước kiểm tra,
hoàn kho và cập nhật trạng thái trong cùng transaction để hủy chỉ hoàn kho một lần.
Dashboard doanh thu chỉ cộng total của completed. Không đổi công thức ở giai đoạn 14.

## Quy ước phát triển

Giữ URL công khai, namespace và cấu trúc session. Không SQL trong View/entrypoint;
không nhận tên template hoặc URL redirect tùy ý từ request. Dùng file nhỏ, prepared
statements, POST+CSRF cho ghi dữ liệu. Thay nghiệp vụ cần test cô lập trước/sau.
Không viết runtime ngoài scr hoặc đưa dữ liệu thật vào fixture.

Xem [cây thư mục](PROJECT-STRUCTURE.md), [kiểm thử](TESTING.md), [bảo mật](SECURITY.md).
