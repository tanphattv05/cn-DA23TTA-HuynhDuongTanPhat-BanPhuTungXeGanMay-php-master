# Giai đoạn 12 — Chuyển toàn bộ MVC storefront sang frontend/backend

## Phạm vi và luồng

URL cũ → scr/pages hoặc scr/actions → scr/backend/Controllers/Storefront
→ backend Model/Service/Middleware/Core → scr/frontend/Views/storefront
→ scr/frontend/includes/storefront.

Một website PHP/XAMPP, giữ namespace MotoParts\App và autoload giai đoạn 11.
Không thêm chức năng, không thiết kế lại giao diện, không đổi URL hoặc schema.

## Bản đồ file di chuyển

Từ scr/app sang scr/backend, giữ nguyên phần đường dẫn còn lại (18 file):

- Controllers/Storefront: ProductController, AuthController, CartController,
  CheckoutController, OrderController.
- Models: StorefrontProduct, UserAuth, CartProduct, Checkout, StorefrontOrder.
- Services: AuthService, CartService, CheckoutService.
- Middleware: Authenticate, RequireRole.
- Core: AuthCsrf, CartCsrf, OrderStatus.

Từ scr/app/Views/storefront sang scr/frontend/Views/storefront (10 file):

- products/index.php, detail.php
- auth/login.php, register.php, error.php
- cart/index.php
- checkout/index.php, success.php
- orders/index.php, detail.php

Không để bản sao/wrapper của 28 file này trong app. HomeController, home View và
layout đã chuyển ở giai đoạn 11 được giữ nguyên. app chỉ còn MVC Admin và wrapper
bootstrap/Core/View cần cho tương thích. Autoload backend trước, app sau vẫn hoạt động;
Admin gọi middleware/CSRF/status đã chuyển qua cùng namespace, không sửa Admin.

## Điểm vào và đường dẫn

16 entrypoint đổi duy nhất đường nạp app/bootstrap.php → backend/bootstrap.php:

- pages/products.php, product-detail.php, login.php, register.php, cart.php,
  checkout.php, order-success.php, my-orders.php, order-detail.php.
- actions/login.php, register.php, logout.php, add_cart.php, update_cart.php,
  remove_cart.php, checkout.php.

Depth của app và backend/frontend tương đương; đã kiểm tra dirname(__DIR__) tới
scr/config/database.php và scr/assets/images/products vẫn đúng. Config DB hiện hành
tiếp tục ở scr/config, không tạo kết nối thứ hai. Assets/JS giữ vị trí và URL hiện tại.
Root index.php, scr/index.php, scr/.gitignore, vendor và thư mục thesis không sửa.

Nội dung 28 file đã chuyển được đối chiếu với HEAD, trùng hoàn toàn sau chuẩn hóa
LF/CRLF. Không đổi transaction, FOR UPDATE, rollback, giá lịch sử, stock, receipt,
token một lần, CSRF, session/cart, quyền sở hữu đơn hoặc quy tắc trạng thái.
Renderer frontend ưu tiên từ giai đoạn 11 tiếp tục sử dụng; không phải viết renderer mới.

## Kiểm tra ngày 2026-10-02

- Workspace ban đầu sạch; không tìm thấy AGENTS.md trong repo.
- Baseline: **899/899**.
- Test mới RED trước di chuyển: thiếu implementation ProductController trong backend.
- Sau di chuyển: **990/990**, gồm **91 kiểm tra mới** và 899 hồi quy.
- PHP lint **51/51** file mới/sửa; git diff --check đạt.
- Apache: 28 file backend/frontend đã chuyển đều **403**; homepage, products,
  login, register, cart trả **200**; history/detail và Admin guest trả **302** về login.
- HTTP/DB có xác thực và thao tác ghi chạy trong môi trường cô lập của harness.
  Không dùng tài khoản/đơn thật; không đổi schema/dữ liệu thật; không tự commit.
- Chưa kiểm tra trực quan trên trình duyệt hoặc responsive.

Các suite storefront cũ chỉ đổi đường dẫn file để kiểm tra đúng vị trí mới. Không bỏ
assertion hoặc thay expected hành vi nghiệp vụ. Lỗi giới hạn độ dài command line khi
patch một test lớn được xử lý bằng hunk nhỏ; không ảnh hưởng nội dung test.

## Checklist XAMPP

Base: http://localhost/cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/scr/

1. Mở pages/products.php → chi tiết: ảnh, nút thêm giỏ và layout giữ nguyên.
2. Login/register/logout: navbar, role, session và giỏ vẫn đúng; logout bằng POST.
3. Trên DB thử: cập nhật giỏ, checkout hợp lệ/lỗi, trang thành công và receipt.
4. Customer xem lịch sử/chi tiết; đổi ID sang đơn người khác nhận 404.
5. Admin đăng nhập, mở dashboard/products/categories/customers/orders. Chỉ test đổi
   trạng thái/hoàn kho trên dữ liệu cô lập.
6. Kiểm tra desktop/mobile, CSS/JS và ảnh; mở trực tiếp backend/frontend phải bị chặn.

URL công khai giữ nguyên, không truy cập trực tiếp file View hoặc Controller để thử UI.
