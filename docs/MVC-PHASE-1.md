# MVC giai đoạn 1 — Danh mục Admin

## Phạm vi

Chỉ danh mục Admin được chuyển sang MVC. Không di chuyển scr, không thêm framework,
Composer/ORM, rewrite, migration hay schema mới. Giữ ba URL hiện có:

- /scr/admin/categories.php → CategoryController::index()
- /scr/admin/category-form.php?id=... → CategoryController::form()
- /scr/admin/save-category.php → CategoryController::save() (POST)

Các URL trên nằm dưới tiền tố dự án:
`/cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master`.

## Luồng xử lý

Request → file PHP điểm vào cũ → app/bootstrap.php → CategoryController
→ Category Model → View::admin() → template danh mục + header/footer AdminLTE.

1. File điểm vào định nghĩa MOTOPARTS_MVC_ENTRY, nạp bootstrap và gọi action cố định.
   Không lấy tên class, action hoặc template từ tham số request.
2. Bootstrap chỉ đăng ký autoloader nội bộ cho namespace MotoParts\App.
3. Constructor controller nạp category-input.php → product-bootstrap.php → auth.php.
   Kiểm tra quyền hoàn tất trước truy vấn nghiệp vụ hoặc HTML.
   Connection MySQLi $conn và $baseUrl vẫn dùng cấu hình hiện tại.
4. Controller đọc GET/POST, gọi validation hiện có, kiểm CSRF, quản lý transaction,
   session flash, redirect 303 và HTTP 404/405/503; không chứa SQL danh mục.
5. Model nhận MySQLi qua constructor. Model chỉ thực hiện count, paginate, find,
   nameExists, create, update bằng prepared statements; không đọc request/session.
   Controller điều phối transaction; find(..., true) khóa bản ghi khi sửa.
6. View::admin() truyền các biến đã chuẩn bị vào template và ghép layout hiện tại.
   View chỉ hiển thị/escape dữ liệu, không SQL, POST, redirect hoặc thay session.
   CSRF token được controller truyền thành biến $csrfToken.

## Quy ước

- Namespace: MotoParts\App\... khớp đường dẫn scr/app/...; class/file PascalCase.
- Controllers/Admin/CategoryController.php: action index, form, save.
- Models/Category.php: model theo thực thể, nhận connection; không tự kết nối database.
- Core/View.php: renderer tối thiểu, tên template tĩnh do controller chọn.
- Views/admin/categories/index.php và form.php: tên thư mục/file lowercase.
- app/bootstrap.php: autoload nội bộ, chưa cần router hay base controller tổng quát.
- URL vẫn thuộc scr/admin/*.php, nên liên kết tương đối và SCRIPT_NAME của sidebar không đổi.
- category-input.php vẫn là bản validation duy nhất. Không sao chép validation vào model/view.
- Các helper product_text/product_escape/product_redirect hiện được tái sử dụng như cầu nối.
  Không đổi các helper đang phục vụ chức năng chưa chuyển MVC trong giai đoạn này.
- Các biến template không dùng tên nội bộ của renderer: template, variables, file, adminDirectory, baseUrl.

## Chặn truy cập nội bộ

scr/app/.htaccess dùng Require all denied, phù hợp Apache XAMPP hiện tại (AllowOverride All).
Mỗi file PHP nội bộ còn kiểm tra MOTOPARTS_MVC_ENTRY và trả 403 nếu chạy trực tiếp.
Lớp guard này bảo vệ cả khi chạy PHP built-in server không đọc .htaccess.
Không đặt asset công khai trong app; giữ chúng tại assets.

Đã GET trực tiếp trên Apache localhost: app/, .htaccess, bootstrap, Core, Model,
Controller và hai View đều HTTP 403. Ba URL cũ khi chưa đăng nhập đều HTTP 302 tới login.
Đây là kiểm tra đọc, không dùng tài khoản hoặc thay đổi dữ liệu thật.

## Hành vi được giữ nguyên

Danh sách/tìm kiếm/phân trang 10, số sản phẩm kể cả 0, thêm/sửa, trim, tên 100 ký tự,
mô tả 2.000 ký tự, kiểm trùng theo collation, giữ dữ liệu khi lỗi, CSRF,
quyền Admin, active sidebar, form sản phẩm tự lấy danh mục từ database.
Không có chức năng xóa. Không thêm UNIQUE: hạn chế trùng tên do request đồng thời vẫn như trước.
Không thay đổi logic sản phẩm/khách hàng/đơn hàng/giỏ hàng/đăng nhập/thanh toán.

## Kiểm tra và kết quả

Chạy ở gốc repository:

```powershell
D:/xampp/php/php.exe tests/product-management.php --isolated
```

- Trước sửa: 340 kiểm tra đạt, gồm 81 danh mục.
- Sau tách MVC: 340 kiểm tra cũ đạt, không sửa assertion nghiệp vụ cũ.
- Sau bổ sung bảo vệ MVC: 360 kiểm tra đạt (thêm 20 kiểm tra).
- Test bổ sung tại tests/category-mvc.php kiểm tra URL nội bộ dù có session admin,
  URL cũ đòi đăng nhập, POST trái quyền không ghi, View không SQL/POST,
  Model không request/session/redirect.
- PHP lint và git diff --check được chạy khi bàn giao.
- Database motoparts_test_<random> và bản sao PHP tạm được tạo/dọn tự động.
  Không sửa dữ liệu thật, không commit.
- Chưa kiểm tra bố cục trực quan hoặc thao tác click bằng trình duyệt.
  HTTP test đã kiểm tra URL, form, session, quyền, dữ liệu và HTML.

Checklist XAMPP: mở danh sách/form qua URL cũ; tìm/chuyển trang; trên dữ liệu thử thêm/sửa,
thử tên trùng/CSRF sai; kiểm tra sidebar/dropdown sản phẩm; mở /scr/app/Models/Category.php phải bị 403.

## Các giai đoạn sau

Chuyển từng chức năng và chạy baseline trước/sau theo cùng quy trình:
giữ điểm vào cũ, đưa SQL vào model, điều phối request vào controller, HTML vào view.
Sản phẩm: giữ helper upload và rollback ảnh; đơn hàng: dùng chung quy tắc trạng thái,
giữ transaction/khóa bản ghi và hoàn kho một lần; khách hàng: giữ chế độ chỉ đọc,
các cột SELECT công khai và liên kết user_id chính xác.
Chỉ tách helper dùng chung khi có nhu cầu thực tế và kiểm thử tương ứng.
Không cần chuyển cả website hoặc thêm router/rewrite để tiếp tục.
