# Giai đoạn 19 — Đánh giá và nhận xét sản phẩm

Ngày hoàn tất: 14/10/2026. Không import migration vào database thật.

## Chức năng và quy tắc

Customer được lưu một đánh giá cho mỗi sản phẩm đã mua trong đơn completed.
Kiểm tra bằng orders.user_id đúng tài khoản đăng nhập, INNER JOIN order_details
theo order_id, đúng product_id, quantity > 0. Không suy đoán từ tên/điện thoại,
không gán đơn user_id NULL; pending/confirmed/shipping/cancelled không đủ điều kiện.
Giá trị completed được đối chiếu OrderStatus, Model đơn hàng và schema hiện tại.

Rating nguyên 1–5; nhận xét không bắt buộc, trim và tối đa 1000 ký tự UTF-8.
Customer được sửa đánh giá của mình hoặc xóa; xóa vẫn được phép nếu điều kiện mua
hàng không còn, vì chỉ xóa dữ liệu của chính họ. Admin không có form và bị chặn POST.
Guest đọc được danh sách, được hướng dẫn đăng nhập.

Trang chi tiết hiển thị trung bình, tổng số, tên người đánh giá, rating, nội dung
và ngày tạo. Form sửa điền dữ liệu của chính customer. Danh sách 10 bản ghi/trang,
id DESC, chỉ có Trước/Sau và số trang; review_page sai về 1, vượt phạm vi về trang
cuối. Không tải toàn bộ danh sách. updated_at được lưu khi nội dung/rating thay đổi.

## Luồng MVC

- ProductController::detail gọi ReviewController::context; Model lấy thống kê,
  trang đánh giá, đánh giá của chính user và điều kiện mua hàng.
- View products/detail.php nạp products/reviews.php; không SQL hoặc xử lý POST.
- actions/product_review.php là entrypoint mỏng gọi ReviewController::submit.
- POST kiểm phương thức, Authenticate::requireUser, role customer, CartCsrf,
  ID bằng CartService::integer, operation, rating và độ dài/encoding nhận xét.
- Model lưu theo cặp user_id/product_id, bỏ qua user_id/review_id do client gửi.
  INSERT SELECT kiểm lại EXISTS đơn completed ngay trong truy vấn ghi.
- UNIQUE cùng upsert khiến gửi lặp cùng dữ liệu không tạo trùng hoặc đổi timestamp.
  Xóa là DELETE đúng cặp user/product; gửi lặp không tạo lại đánh giá.
- Thành công redirect 303 tới URL chi tiết cố định kèm #product-reviews.
  Validation sai trả 400, không đủ quyền 403, sản phẩm mất 404, GET action 405.
  Không nhận return URL tùy ý.

Dùng prepared statements, đóng statement trong finally, escape HTML cả tên và
nội dung. Query thống kê không JOIN lịch sử mua hàng nên nhiều lần mua không làm
nhân số đánh giá. Lỗi database không lộ SQL/credentials; không SELECT password.

## File tạo/sửa

Tạo:
- scr/backend/Models/ProductReview.php
- scr/backend/Controllers/Storefront/ReviewController.php
- scr/actions/product_review.php
- scr/frontend/Views/storefront/products/reviews.php
- docs/migrations/phase-19-product-reviews.sql
- tests/storefront-product-reviews.php
- docs/MVC-PHASE-19.md

Sửa:
- scr/backend/Controllers/Storefront/ProductController.php (chỉ nối dữ liệu chi tiết)
- scr/frontend/Views/storefront/products/detail.php (nạp thành phần đánh giá)
- tests/product-management.php (nạp suite mới)
- tests/storefront-ui-browser.mjs
- tests/fixtures/storefront-ui-protected.json (chỉ file backend/action thuộc phạm vi)

Không đổi Admin, Model catalog/đơn hàng, yêu thích, auth, cart/checkout, navbar,
asset/CSS cache busting, cấu hình hoặc schema của các bảng cũ. Git ban đầu sạch.

## Migration

[phase-19-product-reviews.sql](migrations/phase-19-product-reviews.sql) chỉ tạo
product_reviews: id, user_id, product_id, rating, comment, created_at, updated_at.
INT có dấu tương thích users/products; InnoDB, utf8mb4_general_ci; UNIQUE
(user_id, product_id), index (product_id,id), CHECK rating BETWEEN 1 AND 5.
Khóa ngoại tới users/products ON DELETE CASCADE chỉ dọn đánh giá phụ thuộc.

Đã import và kiểm tra trên database thử ngẫu nhiên. **Chưa import database thật.**
Khi muốn bật: sao lưu, chọn đúng phutung_xemay trong phpMyAdmin, import migration
này một lần khi bảng product_reviews chưa tồn tại. Không import lại schema gốc.
Migration không phụ thuộc việc tạo lại favorites hoặc các bảng hiện tại.

Trước import, phần đánh giá báo chưa sẵn sàng; trang chi tiết vẫn 200 và các nút
giỏ hàng/yêu thích vẫn hoạt động. Website không tự tạo bảng khi chạy.

## Kiểm thử mới

Baseline: 1564/1564, browser 405/405.
Lượt hồi quy cuối:

~~~text
D:/xampp/php/php.exe tests/storefront-product-reviews.php --isolated --reviews-browser
1630/1630; riêng đánh giá 64 (gồm hai assertion khởi động/kết thúc browser)
Browser 432/432; riêng đánh giá 9 tại mỗi viewport 390/768/1440
PHP lint 133/133
git diff --check: exit 0
~~~

Log: %TEMP%/motoparts-19-final.log.
Không cộng lượt thử ban đầu 1624 vào tổng cuối.
Suite tích hợp toàn bộ hồi quy cũ, gồm favorites, catalog, navbar, Admin,
auth, giỏ hàng, checkout và đơn hàng. Chỉ database motoparts_test_* và bản sao
runtime tạm nhận dữ liệu thử; harness dọn trong finally.

Đã kiểm:
- Guest/customer/admin; chưa mua, guest order, mọi trạng thái chưa hoàn thành,
  completed và mua sản phẩm khác.
- Rating 1–5, giá trị sai/array/injection, comment 1000/1001 ký tự Unicode,
  encoding sai, CSRF, ID sai/mất.
- Tạo/sửa/xóa và gửi lặp; timestamp khi lặp; user_id/review_id giả không sửa
  dữ liệu tài khoản khác; XSS ở tên/nhận xét và SQL trong comment giữ nguyên.
- Average/count, limit 10, trang sai/vượt, không lặp bản ghi giữa trang.
- Truy vấn ghi độc lập từ chối chưa mua, UNIQUE, CHECK và khóa ngoại thực tế.
- Stock, giá sản phẩm, tổng tiền/trạng thái đơn không đổi khi thao tác đánh giá.
- Giả lập thiếu migration: product detail/cart tiếp tục hoạt động.
- Browser Edge: computed style/kích thước, không tràn ngang, label/focus,
  POST tạo/sửa/xóa thật và chuyển trang trên fixture tại ba viewport.

HTTP Apache chỉ đọc: chi tiết id=1 trả 200; action GET 405;
Model/View nội bộ 403. Không submit đánh giá hoặc sửa đơn trên DB thật.

## Checklist thủ công và giới hạn

Mở:
http://localhost/cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/scr/pages/product-detail.php?id=1#product-reviews

Thay id bằng sản phẩm phù hợp dữ liệu của bạn.

1. Sau import migration, guest xem phần đánh giá; customer chưa mua không có form.
2. Trên bản thử, customer có đơn completed chứa đúng sản phẩm: lưu 1/5 sao,
   sửa nhận xét, tải lại, xóa, kiểm tổng/trung bình.
3. Dùng customer khác và admin: không sửa/xóa đánh giá của người khác.
4. Kiểm tra comment dài, ký tự HTML và danh sách trên 10 đánh giá.
5. Xem ở mobile/tablet/desktop; thử lại yêu thích, giỏ hàng, tìm kiếm navbar.

Chưa đo tải lớn, kiểm thử nhiều trình duyệt/thiết bị thật hoặc screen reader.
Không có duyệt đánh giá/Admin quản lý đánh giá trong giai đoạn này.
Các chỉnh sửa đồng thời của cùng customer dùng lần lưu hợp lệ sau cùng; không có
cơ chế phát hiện xung đột nhiều tab. Lỗi validation hiện ở trang thông báo, chưa
giữ lại bản nháp nhận xét. Không đổi tồn kho/doanh thu/trạng thái đơn.
Không commit, không ZIP, không triển khai giai đoạn 20.
