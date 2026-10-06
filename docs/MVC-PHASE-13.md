# Giai đoạn 13 — MVC và layout Admin sang backend/frontend

## Kết quả kiến trúc

URL /scr/admin/... → entrypoint → backend/Controllers/Admin → backend Model/helper
→ frontend/Views/admin → frontend/includes/admin. Namespace MotoParts\App giữ nguyên.
Một ứng dụng XAMPP; không rewrite, framework hoặc thiết kế lại AdminLTE.

```text
scr/backend/
  Controllers/Admin/{Category,Product,Customer,Order,Dashboard}Controller.php
  Models/{Category,Product,Customer,Order,Dashboard}.php
  Middleware/admin.php
  Core/Admin/
    product-bootstrap.php, product-validation.php, product-upload.php
    category-input.php, customer-view.php, order-status.php, order-filters.php
  Core/View.php
scr/frontend/
  Views/admin/
    categories/{index,form}.php
    products/{index,form}.php
    customers/{index,detail}.php
    orders/{index,detail}.php
    dashboard/index.php
  includes/admin/{header,footer,pagination}.php
scr/admin/
  các URL PHP cũ; auth.php và includes/* là wrapper tương thích
  assets/adminlte/ giữ nguyên
scr/app/
  bootstrap.php, Core/View.php chỉ còn wrapper
```

Chuyển 5 Controller, 5 Model, 9 View khỏi app. Đưa 7 helper nghiệp vụ/validation
vào backend/Core/Admin, giữ tên hàm để không đổi các quy tắc đang chạy.
Middleware/admin.php chứa phần nối RequireRole/Authenticate với biến conn/baseUrl;
admin/auth.php chỉ bootstrap và nạp bridge này. RequireRole/Authenticate giai đoạn 12
không đổi. Backend helpers có guard chặn truy cập trực tiếp.

Layout header/sidebar và footer chuyển frontend. HTML phân trang tách thành
frontend/includes/admin/pagination.php; hàm customer_pagination giữ API cũ và include
template với đúng path/query/page/pages. Renderer Admin dùng trực tiếp layout mới.
Wrapper cũ kiểm tra Admin, không nhân đôi SQL, validation hoặc HTML. Wrapper header/footer
hiện cũng chặn customer/guest khi mở trực tiếp; không ảnh hưởng URL module Admin.

## Giữ nguyên hành vi

12 entrypoint Admin đổi đường nạp sang backend/bootstrap. Các Controller đổi đường
helper sang backend/Core/Admin. Không thay truy vấn, xử lý request hoặc transaction.
Toàn bộ 5 Model và 9 View có nội dung trùng bản trước di chuyển (bỏ qua LF/CRLF).

Upload helper nằm sâu hơn một cấp: dùng dirname(__DIR__,3) tới scr/assets/images/products.
Đường cleanup ảnh trong ProductController và kiểm tra ảnh trong View đơn vẫn đúng
vì Controller/View giữ cùng độ sâu sau di chuyển. Giữ move_uploaded_file, MIME/content,
2 MB, tên random, chống ghi đè, dọn ảnh mới khi lưu DB lỗi.

Sidebar vẫn dựa SCRIPT_NAME của entrypoint cũ; AdminLTE asset URLs không đổi.
CSRF, prepared statements, lọc/phân trang, redirect nội bộ, trạng thái và FOR UPDATE,
rollback/hoàn kho một lần, doanh thu completed đều giữ nguyên. Không sửa storefront,
config DB, root index.php, scr/.gitignore hoặc vendor; không đổi schema/dữ liệu thật.

## Kiểm tra 2026-10-02

- Workspace có thay đổi giai đoạn 12 chưa commit, được giữ nguyên; không reset/commit.
- Baseline thực tế **990/990**.
- Test RED trước triển khai: chưa có Models/Category trong backend.
- Kết quả cuối **1085/1085**, gồm **95 mới** và 990 hồi quy.
- PHP lint **62 file** thuộc phạm vi Admin/runtime/test đã kiểm tra đạt.
- git diff --check đạt.
- Apache: **30 file nội bộ** mới trả 403; **12 URL Admin guest** chuyển login (302).
- CSS/JS AdminLTE trả 200. HTTP/DB có xác thực, upload và thao tác ghi chạy trong
  database/bản sao cô lập; không dùng account/order thật để thử.
- Lỗi trung gian: tách template phân trang thiếu vòng lặp khiến test giữ keyword lỗi.
  Đã phục hồi markup vòng lặp và chạy lại toàn bộ đạt, không sửa expected hành vi.
- Chưa kiểm tra trực quan/mobile bằng trình duyệt.

## File test/tài liệu

Thêm tests/admin-migration.php, tests/ADMIN-MIGRATION.md và tài liệu này.
Harness nạp suite mới. Các suite MVC category/product/customer/order/dashboard cập nhật
đường dẫn tới code mới; test migration giai đoạn 12 cập nhật assertion vị trí Admin
theo kiến trúc giai đoạn 13. Các assertion nghiệp vụ không bị bỏ.

## Checklist XAMPP

Base: http://localhost/cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/scr/admin/

1. Login Admin, mở index.php: dashboard, nhãn trạng thái, link đơn và sidebar đúng.
2. categories.php/products.php: tìm kiếm, bộ lọc, trang kế tiếp giữ tham số; form active đúng.
3. Trên DB thử: thêm/sửa sản phẩm có/không thay ảnh, kiểm tra ảnh ở storefront.
4. customers.php/customer-detail.php: thống kê, lịch sử đơn, phân trang và link chi tiết.
5. orders.php/order-detail.php: bộ lọc, form trạng thái, redirect giữ ngữ cảnh.
   Chỉ kiểm tra hủy/hoàn kho trên dữ liệu thử cô lập.
6. Customer/guest không vào Admin; login/logout storefront, giỏ và checkout vẫn hoạt động.
7. Kiểm tra desktop/mobile, sidebar collapse, CSS/JS; không mở backend/frontend làm URL UI.

Không tạo đơn/tài khoản thật, không sửa dữ liệu thật, không tự commit.
