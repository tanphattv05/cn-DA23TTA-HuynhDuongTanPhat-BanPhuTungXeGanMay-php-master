# Website bán phụ tùng xe gắn máy

## Giới thiệu

Website bán phụ tùng xe gắn máy được xây dựng nhằm hỗ trợ người dùng tìm kiếm, xem thông tin và mua các sản phẩm phụ tùng xe gắn máy trực tuyến.

Hệ thống cho phép khách hàng xem danh sách sản phẩm, xem chi tiết sản phẩm, tìm kiếm, quản lý giỏ hàng và thực hiện đặt hàng. Đồng thời, hệ thống cung cấp khu vực quản trị giúp quản lý sản phẩm, danh mục, khách hàng và đơn hàng.

Dự án được xây dựng theo mô hình **MVC (Model - View - Controller)**, sử dụng PHP để xử lý phía máy chủ và MySQL để lưu trữ dữ liệu.

---

## Công nghệ sử dụng

### Frontend

- HTML5
- CSS3
- JavaScript
- Bootstrap 5

### Backend

- PHP

### Database

- MySQL

### Kiến trúc

- MVC (Model - View - Controller)

### Admin

- AdminLTE
- Bootstrap

### Môi trường phát triển

- XAMPP
- Apache
- MySQL
- phpMyAdmin
- Visual Studio Code
- Git / GitHub

---

## Kiến trúc hệ thống

Dự án sử dụng mô hình **MVC** nhằm phân tách giao diện, xử lý nghiệp vụ và dữ liệu.

```text
MVC
│
├── Model
│   └── Xử lý dữ liệu và tương tác với MySQL
│
├── View
│   └── Hiển thị giao diện cho người dùng
│
└── Controller
    └── Tiếp nhận yêu cầu và xử lý nghiệp vụ
