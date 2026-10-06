# Cấu trúc dự án hiện hành

```text
MotoParts/
  index.php                 chuyển hướng vào scr, không chứa nghiệp vụ
  .htaccess                 tắt listing, chặn metadata/tài liệu/test qua HTTP
  README.md
  docs/                     tài liệu và schema-only, không phải runtime
  tests/                    CLI harness và các suite cô lập
  scr/
    index.php               trang chủ
    pages/                  9 entrypoint storefront
    actions/                7 entrypoint thao tác storefront
    admin/                  12 entrypoint Admin
      assets/adminlte/      tài nguyên công khai giữ nguyên
    assets/                 CSS, JavaScript, ảnh sản phẩm công khai
    frontend/
      .htaccess             chặn HTTP trực tiếp
      Views/
        storefront/         home, products, auth, cart, checkout, orders
        admin/              dashboard, categories, products, customers, orders
      includes/
        storefront/         header, navbar, footer
        admin/              header/sidebar, footer, pagination
    backend/
      .htaccess             chặn HTTP trực tiếp
      bootstrap.php         autoload namespace MotoParts\App
      Controllers/          Storefront và Admin
      Models/               các truy vấn dữ liệu
      Services/             AuthService, CartService, CheckoutService
      Middleware/           Authenticate, RequireRole, bridge admin.php
      Core/                 View, AuthCsrf, CartCsrf, OrderStatus
      Support/Admin/        7 helper nghiệp vụ/validation/upload
      config/database.php   nguồn kết nối MySQLi duy nhất
```

scr/app, scr/includes, scr/admin/includes và scr/config đã không còn tồn tại khi
xác minh. Không còn wrapper tương thích. admin/auth.php, admin/navbar.php và
test_database.php đã nghỉ; URL chức năng không đổi. Bridge backend/Middleware/admin.php
vẫn cần để khởi tạo quyền/conn/baseUrl cho các helper Admin, không phải bản sao auth.

Không di chuyển hoặc chỉnh sửa thesis, vendor, metadata Git hay ảnh upload người dùng.
Một số .gitkeep có thể còn trong thư mục được tạo ở các giai đoạn trước; chúng không
được nạp lúc chạy. Không dùng vị trí trong tài liệu lịch sử để tạo bản code thứ hai.

## URL

Base XAMPP: http://localhost/cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/scr/

- pages: products.php, product-detail.php?id=..., login.php, register.php, cart.php,
  checkout.php, order-success.php, my-orders.php, order-detail.php?id=....
- actions: add_cart.php, update_cart.php, remove_cart.php, login.php, register.php,
  logout.php, checkout.php. Thao tác ghi được Controller kiểm tra POST/CSRF.
- admin: index.php, categories.php, category-form.php, save-category.php, products.php,
  product-form.php, save-product.php, customers.php, customer-detail.php, orders.php,
  order-detail.php, update-order.php.

Assets giữ /scr/assets và /scr/admin/assets/adminlte. Không truy cập trực tiếp
frontend/backend để mở UI. [Cài đặt](INSTALLATION-XAMPP.md) giải thích base URL cố định.
