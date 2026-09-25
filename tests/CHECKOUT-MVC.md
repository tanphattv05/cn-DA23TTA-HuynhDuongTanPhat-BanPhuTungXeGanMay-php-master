# Kiểm tra checkout MVC

Chạy từ gốc repository:

```powershell
D:/xampp/php/php.exe tests/product-management.php --isolated
```

tests/checkout-mvc.php được harness gọi sau cart-mvc. Không chạy độc lập với DB
thật. Harness tạo database motoparts_test_<random>, sao chép schema/mã PHP và
chạy server loopback; dọn database/thư mục tạm trong finally. Fixture session và
worker chỉ tồn tại trong bản sao tạm, không thêm endpoint thử vào scr thật.

Baseline 645; sau thay đổi 723 kiểm tra đạt, gồm 78 kiểm tra checkout.
Test cart giai đoạn 7 được cập nhật gửi CSRF/submit_token và chờ PRG 303;
không bỏ kiểm tra giá, tồn kho hoặc session.

## Phạm vi

- Entrypoint mỏng, phân chia MVC/Service, HTTP 403 file nội bộ.
- Giỏ trống/sai/mất sản phẩm/hết hàng/vượt kho, old input và tự điền từ DB.
- Validation tên/phone/address/note, array và độ dài, escape HTML.
- CSRF và token gửi đơn một lần; chỉ POST tạo đơn, replay không tạo thêm.
- user_id từ session, guest NULL, pending, không tin giá/tổng từ POST.
- Giá lịch sử order_details.price, tổng DECIMAL chính xác, giới hạn và tràn số.
- Trigger lỗi dòng chi tiết/thao tác kho thứ hai hoặc affected_rows=0:
  rollback toàn bộ, giữ giỏ/old/token; commit mới xóa giỏ.
- Receipt trong session, refresh success, quyền customer và đơn guest.
- Admin list/detail/dashboard/cancel vẫn hoạt động, hoàn kho một lần.
- Hai tiến trình PHP với hai kết nối DB cạnh tranh mua 4 từ stock 5. Parent giữ
  row lock để cả hai chờ; khi nhả, chỉ một đơn thành công và stock còn 1.
- Toàn bộ hồi quy giai đoạn 1–7.

## Giới hạn và kiểm tra trực quan

Chưa thử hai POST đồng thời qua Apache hoặc bố cục/click bằng trình duyệt.
Token dựa trên session locking. Không có idempotency key bền vững trong DB nên
không bảo đảm exactly-once nếu tiến trình chết sau commit trước khi ghi session.

Checklist XAMPP trên DB thử:
1. Giỏ → checkout: bố cục, COD, giá và tự điền.
2. Dữ liệu sai: lỗi/old input, không mất giỏ.
3. Thành công: mã đơn, badge rỗng, mua tiếp; refresh không tạo thêm đơn.
4. Đối chiếu Admin/customer, giá lịch sử và hủy hoàn kho một lần.
5. /scr/app/Services/CheckoutService.php trả 403.

Xem docs/MVC-PHASE-8.md cho luồng transaction và chi tiết triển khai.
