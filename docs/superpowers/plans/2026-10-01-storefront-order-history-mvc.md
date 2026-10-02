# MVC giai đoạn 10 — Lịch sử và chi tiết đơn storefront

- [x] Đọc mã thực tế/schema, xác nhận baseline 800 và giữ workspace hiện có.
- [x] Viết suite storefront-order-mvc, chạy RED trước mã nghiệp vụ.
- [x] Tách StorefrontOrder, OrderController và hai View; URL cũ là entrypoint mỏng.
- [x] Dùng Authenticate hiện có, ràng buộc ownership ngay trong SQL, thống nhất 404.
- [x] Chia sẻ nhãn/màu trạng thái với Admin, giữ nguyên transition và tính tiền Admin.
- [x] LEFT JOIN sản phẩm, giá lịch sử, escape và lỗi DB an toàn; không thêm phân trang.
- [x] Chạy hồi quy cô lập, lint/diff, Apache URL/403; hoàn thiện tài liệu và checklist.

Giới hạn: không schema/dữ liệu thật, không ghi đơn/tài khoản thật, không commit.
Runtime trong scr; frontend View/layout, backend Controller/Model/Middleware/Core.
Không cần Service mới vì đây là luồng chỉ đọc. Giữ giao diện và URL hiện tại.
