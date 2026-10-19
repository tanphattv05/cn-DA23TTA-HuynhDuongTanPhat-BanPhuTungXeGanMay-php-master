# Giai đoạn 21.2 — Sổ địa chỉ giao hàng

## Chức năng và MVC

Customer vào `scr/pages/addresses.php` từ Hồ sơ của tôi; POST `scr/actions/addresses.php` → AddressController → ShippingAddress. View `frontend/Views/storefront/profile/addresses.php` dùng layout/Bootstrap hiện tại. Có thêm, sửa, xóa, nhiều địa chỉ và đặt mặc định.

Chỉ lấy user_id từ Authenticate. POST + AuthCsrf, prepared statements, kiểm tra sở hữu cho mọi ID, escape HTML và thông báo lỗi database chung. Họ tên/điện thoại/địa chỉ bắt buộc, giới hạn 100/20/500 ký tự; điện thoại 9–11 số.

## Migration

Sao lưu trước, sau đó dùng phpMyAdmin chọn database MotoParts và import **một lần** `docs/migrations/phase-21.2-shipping-addresses.sql`. Không tự import database thật trong quá trình triển khai.

Bảng shipping_addresses có FK user_id → users.id ON DELETE CASCADE. Generated column default_slot là 1 hoặc NULL, UNIQUE(user_id, default_slot) bảo đảm tối đa một mặc định; chỉ mục này cũng hỗ trợ truy vấn theo tài khoản. CHECK giới hạn is_default 0/1. Không sửa users/orders.

Transaction khóa dòng users FOR UPDATE tuần tự hóa thao tác cùng tài khoản, rollback khi lỗi. Địa chỉ đầu tiên tự mặc định. Xóa mặc định chọn địa chỉ còn lại có ID nhỏ nhất; xóa cuối cùng để sổ trống.

## Checkout

Chưa có bản nháp thì điền mặc định. Có thể chọn địa chỉ đã lưu hoặc nhập khác; JavaScript điền các trường và chuyển sang nhập khác khi chỉnh sửa. Nếu tắt JavaScript, cần chọn “Nhập địa chỉ khác” trước khi sửa người nhận.

Server kiểm tra customer/chủ sở hữu và đọc lại người nhận từ database khi POST có address_id. Nhập khác dùng validation checkout cũ. Nội dung được lưu độc lập trong orders, không cập nhật đơn cũ khi sửa/xóa địa chỉ. Không đổi CheckoutService, tồn kho, giá lịch sử, thanh toán hoặc Admin.

Chưa migration: checkout vẫn nhập thủ công; trang địa chỉ báo chưa khả dụng. Địa chỉ đã bị xóa/không thuộc tài khoản bị từ chối khi gửi đơn. Địa chỉ nhập tay ở checkout không tự lưu vào sổ.

## File và kiểm thử

Mới: AddressController, ShippingAddress, View profile/addresses, pages/actions addresses.php, assets/js/checkout-address.js, migration, tests/storefront-addresses.php và tài liệu này.
Sửa: CheckoutController, View checkout/index và profile/index.

Chạy riêng `D:/xampp/php/php.exe tests/storefront-addresses.php --isolated`: tạo database ngẫu nhiên và bản sao runtime, chạy HTTP với dữ liệu thử rồi dọn chính dữ liệu tạm. Không chạy regression toàn bộ/browser.

Kết quả xác minh mới: **46/46 kiểm tra HTTP/database đạt**, gồm CRUD, CSRF, IDOR, UNIQUE mặc định, rollback, checkout trước migration, giá/tồn kho/token một lần và giữ nguyên đơn cũ. **PHP lint 9/9 đạt; git diff --check đạt** (chỉ cảnh báo LF/CRLF). Lượt hoàn tất chỉ bổ sung test và tài liệu, không sửa thêm mã nghiệp vụ.

Checklist XAMPP: import migration sau sao lưu; mở Hồ sơ → Quản lý địa chỉ; thử CRUD/mặc định; kiểm tra chọn địa chỉ/nhập khác tại checkout và giao diện mobile/desktop. Chỉ đặt đơn thử trên bản sao/database thử. Giao diện và JavaScript chưa được kiểm thử trực quan trong lượt hoàn tất.
