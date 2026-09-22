# MVC giai đoạn 2 — Sản phẩm Admin

## Cấu trúc và phạm vi

Website duy nhất nằm trong scr. index.php ở gốc repository chỉ redirect về đường dẫn dự án /scr/.
Không di chuyển .git, docs, tests hoặc các file quản lý repository.
Các file PHP ngoài scr được rà soát: index.php chuyển hướng và tests/*.php chạy CLI,
không phải mã runtime được Controller/View website gọi. Không phát hiện phụ thuộc runtime cục bộ
cần chuyển từ ngoài scr. Không tạo website thứ hai; bản sao tạm chỉ phục vụ kiểm thử và tự dọn.

```text
scr/
  admin/
    products.php             -> ProductController::index()
    product-form.php         -> ProductController::form()
    save-product.php         -> ProductController::save()
    includes/
      product-bootstrap.php  -> auth/config/helpers hiện có
      product-validation.php -> một bản quy tắc validation duy nhất
      product-upload.php     -> một helper upload duy nhất
    assets/product-form.js
  app/
    bootstrap.php
    .htaccess
    Core/View.php
    Controllers/Admin/ProductController.php
    Models/Product.php
    Models/Category.php
    Views/admin/products/index.php
    Views/admin/products/form.php
  config/database.php
  assets/images/products/
  pages/                     -> giữ nguyên
  actions/                   -> giữ nguyên
```

Giữ namespace MotoParts\App, file class PascalCase, template lowercase như giai đoạn 1.
Không thêm dependency, router, rewrite, schema, endpoint xóa.

Request → điểm vào cũ → bootstrap → ProductController → Product/Category → View::admin → AdminLTE.
Controller xác thực qua product-bootstrap.php/auth.php trước nghiệp vụ; quản lý request,
CSRF, flash, transaction, upload, rollback và redirect.
Product Model dùng prepared statements cho count/paginate/find/create/update.
COUNT và danh sách dùng chung bộ điều kiện.
Category Model bổ sung options() và findShared() để đọc dropdown và khóa chia sẻ khi lưu sản phẩm.
Các phương thức danh mục cũ không đổi.

View giữ markup và liên kết tương đối cũ, không SQL/POST; CSRF được truyền bằng biến csrfToken.
Không sao chép validation/upload vào MVC. Helper cũ được gọi trực tiếp từ Controller.
Giá order_details không được cập nhật.

## Đường dẫn cần giữ

- /scr/admin/products.php
- /scr/admin/product-form.php (hoặc ?id=<ID>)
- /scr/admin/save-product.php (POST)
- /scr/admin/assets/product-form.js
- /scr/assets/images/products/<tên-file>

Tất cả dưới tiền tố /cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master.
Đường dẫn filesystem dọn ảnh trong Controller đã đổi theo vị trí mới:
dirname(__DIR__, 3) . '/assets/images/products/'.
Upload vẫn chạy helper tại vị trí cũ và ghi đúng cùng thư mục.
Không tải ảnh mới giữ ảnh cũ; lỗi DB dọn duy nhất ảnh mới của request.
Tên file server sinh ngẫu nhiên và DB chỉ lưu tên, không có đường dẫn tuyệt đối.
Chỉ MIME JPEG/PNG/WebP, tối đa 2 MiB và 20 triệu điểm ảnh như trước.
Không thay cách kiểm tra nội dung ảnh/GD hiện tại.

scr/app/.htaccess tiếp tục Require all denied. Mỗi file mới có guard MOTOPARTS_MVC_ENTRY.
Đã kiểm tra trực tiếp Apache localhost: Model/Controller/View mới HTTP 403;
URL cũ chưa đăng nhập HTTP 302; JavaScript HTTP 200; index.php gốc HTTP 302 về /scr/.

## Kết quả kiểm tra

Chạy từ gốc repository:

```powershell
D:/xampp/php/php.exe tests/product-management.php --isolated
```

- Trước sửa: 360 kiểm tra đạt.
- Sau tách MVC: 360 kiểm tra cũ đạt, không sửa assertion nghiệp vụ cũ.
- Sau bổ sung tests/product-mvc.php: 390 kiểm tra đạt (30 mới).
- Bao gồm sản phẩm, danh mục MVC, khách hàng, chi tiết đơn và bộ lọc đơn.
- Test mới kiểm tra guard HTTP dù có session admin, URL cũ, tách lớp,
  URL JavaScript/ảnh, ảnh trong HTML trang khách hàng, thêm/sửa không ảnh,
  rollback cập nhật có ảnh thất bại, dọn ảnh mới và giữ ảnh cũ, sidebar.
- Test cũ tiếp tục kiểm tra thêm/thay ảnh, file giả/ảnh quá dung lượng,
  CSRF/customer, giá/tồn kho sai, danh mục sai, tìm kiếm/lọc/phân trang,
  giá lịch sử và hoàn kho.
- PHP lint và git diff --check được chạy khi bàn giao.
- Chỉ dùng database motoparts_test_<random> và bản sao tạm; không ghi dữ liệu thật.
- Không tự commit.

Chưa kiểm tra trực quan bố cục hoặc JavaScript preview trong trình duyệt.
Bộ test hiện tại không chạy end-to-end cart/checkout; mã các luồng này được giữ nguyên.

## Checklist XAMPP

1. Đăng nhập admin, mở products.php; thử tên/thương hiệu, danh mục và chuyển trang.
2. Mở form thêm/sửa qua URL cũ; kiểm tra dropdown và sidebar active.
3. Trên dữ liệu thử: thêm ảnh, sửa giữ ảnh, thay ảnh; kiểm tra xem trước và thông báo.
4. Thử giá/tồn kho sai và file không hợp lệ; kiểm tra giữ lại dữ liệu form.
5. Mở trang bán hàng kiểm tra ảnh; kiểm tra cart/checkout bằng môi trường thử.
6. Truy cập trực tiếp /scr/app/Models/Product.php phải trả 403.
