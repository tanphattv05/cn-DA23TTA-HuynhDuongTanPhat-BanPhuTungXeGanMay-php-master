# Giai đoạn 11 — Nền móng frontend/backend và trang chủ

## Phạm vi

Một ứng dụng PHP/XAMPP, không tạo website/server thứ hai, không rewrite hoặc dependency.
Đã chuyển trang chủ và header/navbar/footer storefront. Giữ URL, session, cart,
checkout, history, AdminLTE, quy tắc trạng thái/tồn kho. Không chuyển các module khác.

```text
scr/
  index.php                              # entrypoint trang chủ
  backend/
    bootstrap.php                        # autoload duy nhất
    Controllers/Storefront/HomeController.php
    Core/View.php                        # renderer duy nhất
    Controllers/Admin/                   # để dành giai đoạn sau
    Models/, Services/, Middleware/, config/
  frontend/
    Views/storefront/home/index.php
    includes/storefront/header.php
    includes/storefront/navbar.php
    includes/storefront/footer.php
    Views/admin/, includes/admin/        # để dành giai đoạn sau
  app/                                   # module chưa chuyển
    bootstrap.php                        # wrapper sang backend/bootstrap.php
    Core/View.php                        # wrapper sang backend/Core/View.php
  includes/header.php, navbar.php, footer.php # wrapper tương thích
  config/database.php                    # vẫn là kết nối hiện hành duy nhất
  pages/, actions/, admin/, assets/       # URL và vị trí giữ nguyên
```

Các thư mục chưa có module được giữ bằng .gitkeep, không tạo lớp rỗng.
Config DB chưa di chuyển; backend/config chỉ là vị trí đích cho lần chuyển riêng.
index.php ngoài scr vẫn chỉ redirect vào scr, không thay đổi. scr/.gitignore không đổi.

## Bootstrap và View tương thích

Giữ namespace `MotoParts\App` để không sửa hàng loạt imports. Backend bootstrap
ánh xạ namespace sang backend trước, nếu chưa có lớp thì dùng app. app/bootstrap.php
chỉ require_once backend/bootstrap.php, không đăng ký một autoloader thứ hai.
app/Core/View.php chỉ nạp lớp View từ backend; không có hai bản renderer hoạt động.

Renderer kiểm tra tên template và chặn traversal. Tìm View trong frontend/Views trước,
nếu chưa chuyển thì dùng app/Views. Template là tên do code chọn, không lấy từ request.
storefront() dùng layout frontend/includes/storefront cho cả View mới lẫn cũ.
admin() tiếp tục layout scr/admin/includes; các View Admin cũ vẫn trong app/Views/admin.

Luồng trang chủ:
`scr/index.php → backend/bootstrap → HomeController → View::storefront → frontend`.
HomeController bắt đầu session và truyền user cho home View. Không cần Model vì trang
chủ hiện tại không có truy vấn DB. View home không đọc session hoặc SQL.
Navbar giữ logic session/cart/AuthCsrf hiện tại; dùng autoload tương thích để gọi lớp
auth còn trong app. Giữ POST logout, token, badge giỏ, liên kết admin theo role.

Ba includes cũ chỉ bootstrap rồi require layout mới; không giữ bản sao HTML.
Asset URL vẫn /scr/assets, không đổi đường dẫn ảnh sản phẩm hoặc JavaScript.
Gỡ bật display_errors trên trang chủ cũ; HomeController tắt xuất lỗi nội bộ.

## Bảo vệ HTTP

frontend/.htaccess và backend/.htaccess dùng Require all denied. Đây là vùng PHP nội bộ,
không phải URL để người dùng mở. Tất cả PHP mới có guard MOTOPARTS_MVC_ENTRY.
Assets vẫn công khai ở scr/assets; app/.htaccess vẫn giữ nguyên.
Không cần sửa cấu hình Apache hoặc rewrite.

## Quy ước chuyển các module sau

1. Chạy baseline, đọc dependency/path thực tế trước khi chuyển từng module.
2. Chuyển class sang backend với namespace cũ; không duy trì hai bản logic.
3. Chuyển View sang frontend/Views, giữ tên template. Xóa bản cũ hoặc để wrapper mỏng
   nếu có đường include phụ thuộc; không sao chép hai bản View để sửa song song.
4. Kiểm tra mọi dirname(__DIR__) khi chuyển file, đặc biệt config, upload và assets.
5. Giữ public entrypoint và URL trong pages/actions/admin, kiểm tra hồi quy cô lập.
6. Config, middleware, services và Admin layout chỉ chuyển khi có phạm vi/test riêng.

## File và kiểm tra — 2026-10-02

Mới: 7 file PHP trong backend/frontend ở cây trên; hai .htaccess; các .gitkeep;
tests/frontend-foundation.php, tests/FRONTEND-FOUNDATION.md và tài liệu này.
Sửa: scr/index.php, scr/app/bootstrap.php, scr/app/Core/View.php,
scr/includes/header.php, navbar.php, footer.php và tests/product-management.php.

- Workspace ban đầu sạch, không tìm thấy AGENTS.md; không commit/reset.
- Baseline **863/863**. RED trước mã triển khai: Home Controller chưa tồn tại trong backend.
- Sau chuyển: **899/899**, gồm **36 mới** và 863 hồi quy cũ trên DB/bản sao cô lập.
- PHP lint **15/15** file mới/sửa; git diff --check đạt.
- Apache: /scr/ và /scr/index.php, CSS, JS, login trả 200.
- Apache: frontend/, backend/, 7 file PHP mới và app/bootstrap.php trả 403.
- Chưa kiểm tra trực quan/mobile bằng trình duyệt; HTTP/DOM và session đã kiểm tra tự động.

Không đổi schema hoặc dữ liệu thật, không tạo tài khoản/đơn thật, không tự commit.
Không thay đổi file quản lý repository, index.php gốc hoặc thesis.

## Checklist XAMPP

Base: http://localhost/cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/scr/

1. Mở /scr/ và /scr/index.php: hero, menu, CSS/JS không đổi và chỉ xuất layout một lần.
2. Guest thấy login/register. Login customer/admin: lời chào, badge cart, link Admin đúng.
3. Logout bằng nút POST; cart vẫn còn, navbar trở về guest.
4. Mở catalog, cart, checkout, history và Admin để xem layout/điều hướng.
5. Xem trên desktop/mobile: menu thu gọn và nút sản phẩm hoạt động.
6. Mọi thao tác tạo đơn/account hoặc đổi trạng thái để thử phải dùng dữ liệu cô lập.
