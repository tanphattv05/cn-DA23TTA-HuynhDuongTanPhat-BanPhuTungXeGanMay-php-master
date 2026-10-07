# Giai đoạn 16.1 — Chẩn đoán hồi quy storefront

Ngày 07/10/2026. Giữ toàn bộ thay đổi giai đoạn 16 chưa commit; không thiết kế lại.

## Kết luận có bằng chứng

HTML từ Apache có body.storefront-body và main#main-content trên các trang đã đo.
Bootstrap đứng trước stylesheet ứng dụng. style.css trả 200, Content-Type text/css,
nội dung đúng file trên đĩa; computed variables/media query hoạt động ở cả ba kích
thước. Không thấy lỗi renderer, thiếu namespace hoặc lỗi cú pháp CSS giải thích
việc mất toàn bộ theme trong môi trường sạch hiện tại.

Lỗi triển khai đã xác định: header dùng URL style.css không phiên bản, dù nội dung
CSS đã thay toàn bộ từ giai đoạn trước. Vì vậy HTML mới có thể dùng CSS cũ trong
cache. Mô phỏng đúng CSS cũ chỉ trong DOM của Edge tái hiện skip link luôn hiện,
navbar chữ trắng trên nền sáng và footer mất theme. Đây là bằng chứng cơ chế lệch
phiên bản; chưa có cache/Network log của trình duyệt người dùng để khẳng định đó là
nguyên nhân duy nhất của phiên lỗi được báo. Không gán nguyên nhân thiếu body class
khi HTML thực tế đã đúng, không coi ảnh mô phỏng là ảnh lấy từ trình duyệt người dùng.

Ngoài ra navbar computed background là transparent trước sửa, dù header cha tối.
Đặt nền trực tiếp cho navbar giữ nguyên màu đang thiết kế và làm hợp đồng rõ ràng.

## Sửa tối thiểu

- scr/frontend/includes/storefront/header.php: dòng 6–8 tính SHA256 file CSS; dòng 33
  đưa hash vào style.css?v=... cho mọi View. Class body cố định sẵn ở layout không
  phụ thuộc Controller, được giữ nguyên. Đường dẫn trang và asset gốc không đổi.
- scr/assets/css/style.css: dòng 35 đặt background-color #1d2025 trên navbar.
  Không thêm !important, không sửa thiết kế skip link: computed xác nhận nó ẩn
  ngoài màn hình bình thường và hiện khi focus với z-index 2000.
- tests/product-management.php: sao chép CSS thật vào bản tạm để test hash URL.
- tests/storefront-ui.php: thêm 35 assertion cho body, skip target, version stylesheet
  của từng View và thử đổi CSS trên bản tạm để xác nhận URL đổi theo nội dung.
- tests/storefront-ui-browser.mjs: đo computed style/contrast/geometry, HTTP MIME,
  so CSS response với source, thứ tự stylesheet và ảnh full-page cho từng trang.
  Runtime.evaluate giờ báo lỗi JavaScript thay vì âm thầm trả undefined. Có focus
  emulation cho headless. --diagnose ghi lỗi để điều tra; --diagnose --stale-css
  mô phỏng CSS cũ trong DOM, không phải một lượt kiểm thử đạt.

Không sửa Controller/Model/Core View, SQL, session, CSRF hoặc nghiệp vụ. Fingerprint
8 vùng backend/Admin/vendor/ảnh/JS/entrypoint và git diff đều xác nhận nguyên vẹn.

## Vì sao giai đoạn 16 bỏ sót

Suite PHP kiểm markup và nghiệp vụ, không chạy CSS. Browser cũ dùng profile mới và
chỉ kiểm Bootstrap flex, overflow, màu nút và toggle; chưa kiểm cache, skip focus,
background navbar/footer hoặc vị trí header-main. Vì vậy CSS cũ ở một profile khác
không được mô phỏng. Kiểm tra mới dùng computed styles và kích thước thực, không
chỉ tìm chuỗi CSS hoặc class HTML.

## Kết quả mới

- Baseline trước sửa: 1339/1339.
- product-management.php --isolated và storefront-ui.php --isolated: 1374/1374
  mỗi lần; trong đó 198 UI checks, tăng 35. Không cộng lặp hai lượt.
- Browser thường: 264/264; 6 trang × 3 kích thước, kèm menu mở/đóng trên mobile.
- PHP lint: 119/119 file. git diff --check: exit 0.
- Apache CSS versioned: 200, text/css, bytes khớp source. Các GET công khai trả 200;
  phân quyền Admin/ownership tiếp tục qua regression trên DB cô lập, không đăng nhập
  Admin thật trên Apache để thử. Backend/frontend guard không đổi.

| Viewport | Header khi menu đóng | Khoảng header → main | Navbar/footer |
| --- | --- | --- | --- |
| 390 px | 149,84 px | 0 px | rgb(29,32,37) |
| 768 px | 131,36 px | 0 px | rgb(29,32,37) |
| 1440 px | 131,36 px | 0 px | rgb(29,32,37) |

Cả 18 lượt đều xác nhận body namespace/variables, skip link ngoài viewport khi blur,
hiện trong viewport khi focus, tương phản navbar/footer >=4.5:1, không horizontal
overflow và Bootstrap trước CSS ứng dụng. Header test yêu cầu 80–190 px khi đóng;
main tiếp ngay sau header, không dùng ẩn nội dung để vượt kiểm tra.

## Screenshot và giới hạn

Ảnh full-page cho home/products/cart/login/register ở 390 và 1440 px đã được chụp,
xem lại: skip link ẩn, nền navbar tối/menu rõ, không trắng bất thường ở đầu, footer
tối, form và card đúng cấu trúc. Ảnh có thể được resize khi xem trong chat.

Các thư mục TEMP của lần kiểm tra này:

- Trước sửa, CSS hiện hành: motoparts-ui-browser-vgeWuU.
- CSS cũ mô phỏng: motoparts-ui-browser-uSYyU2.
- Sau sửa cuối: motoparts-ui-browser-5JddtT.

Nằm dưới C:/Users/tanph/AppData/Local/Temp; không đưa profile/cookie/ảnh vào repo.
File tên home/products/cart/login/register-{390,1440}.png. Không đọc password từ
trình duyệt hoặc submit form; ảnh login có thể phản ánh autofill của môi trường.

Chưa xác minh cache của profile người dùng báo lỗi, trình duyệt/thiết bị khác hoặc
screen reader. Chưa chụp Admin đăng nhập bằng tài khoản thật; Admin được bảo toàn
theo fingerprint và regression cô lập. Ảnh catalog dùng lazy loading nên ảnh dưới
fold có thể chưa tải tại thời điểm capture; không xóa/sửa ảnh upload.

## Mở thử

Base: http://localhost/cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/scr/

Thử /, pages/products.php, pages/cart.php, pages/login.php, pages/register.php.
Tải lại trang; URL CSS trong Network phải có ?v=hash. Tab đầu tiên hiện skip link,
Enter chuyển tới main; menu mobile mở/đóng, footer và form không giãn bất thường.
Nếu vẫn lỗi, cần đối chiếu URL đang mở và response CSS của chính profile đó, không
chỉnh lại nghiệp vụ hoặc liên tục thay CSS để che một bản tài nguyên khác đang tải.

Không đổi schema/dữ liệu thật, không tạo đơn/tài khoản, không ZIP, không commit.
