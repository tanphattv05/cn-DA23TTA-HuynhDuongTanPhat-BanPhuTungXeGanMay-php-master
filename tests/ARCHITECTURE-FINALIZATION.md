# Xác minh kiến trúc cuối

```powershell
D:/xampp/php/php.exe tests/architecture-finalization.php --isolated
```

Entry CLI này chạy lại toàn bộ harness product-management.php. Khi được harness nạp,
nó thực hiện 91 kiểm tra cuối, không đệ quy chạy harness. Chỉ CLI và cờ --isolated được
chấp nhận để chạy trực tiếp. Không có fixture yêu cầu user nhập mật khẩu thật.

78 kiểm tra có từ phiên trước: file đã nghỉ biến mất/404, không fallback, config và
helper mới 403, 29 entrypoint đồng nhất, frontend không SQL/POST. Thêm 13 kiểm tra:
5 bảng đúng thứ tự phụ thuộc, không câu lệnh/dữ liệu khác, 5 DDL khớp SHOW CREATE TABLE,
import DB trống thành công và 5 bảng không có bản ghi. Counter AUTO_INCREMENT bị bỏ
khi so sánh vì không phải cấu trúc và không cần mang dữ liệu sử dụng vào bản cài mới.

DDL được xác minh trước CREATE database thử; DB thật chỉ đọc metadata. finally đóng
kết nối và drop đúng motoparts_schema_<random>. Không thêm Admin mẫu, không xuất dữ
liệu thật hoặc password hash vào SQL. Thử HTTP Apache riêng bằng apache-security.php.

Kết quả chạy lại 05/10/2026: tổng 1176/1176, suite cuối 91/91; Apache probe 13/13.
Xem [kiểm thử toàn dự án](../docs/TESTING.md) và [báo cáo](../docs/MVC-PHASE-14.md).
