<?php
session_start();

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/checkout.php');
    exit;
}

$fullname = trim($_POST['fullname'] ?? '');
$phone = preg_replace('/\s+/', '', trim($_POST['phone'] ?? ''));
$address = trim($_POST['address'] ?? '');
$note = trim($_POST['note'] ?? '');
$cart = $_SESSION['cart'] ?? [];

$_SESSION['checkout_old'] = [
    'fullname' => $fullname,
    'phone' => $phone,
    'address' => $address,
    'note' => $note
];

if ($fullname === '' || mb_strlen($fullname) > 100) {
    $_SESSION['checkout_error'] = 'Họ và tên không hợp lệ.';
    header('Location: ../pages/checkout.php');
    exit;
}

if (!preg_match('/^[0-9]{9,11}$/', $phone)) {
    $_SESSION['checkout_error'] = 'Số điện thoại phải có từ 9 đến 11 chữ số.';
    header('Location: ../pages/checkout.php');
    exit;
}

if ($address === '' || mb_strlen($address) > 500) {
    $_SESSION['checkout_error'] = 'Địa chỉ nhận hàng không hợp lệ.';
    header('Location: ../pages/checkout.php');
    exit;
}

if (mb_strlen($note) > 500) {
    $_SESSION['checkout_error'] = 'Ghi chú không được vượt quá 500 ký tự.';
    header('Location: ../pages/checkout.php');
    exit;
}

if (empty($cart)) {
    $_SESSION['checkout_error'] = 'Giỏ hàng đang trống.';
    header('Location: ../pages/cart.php');
    exit;
}

mysqli_begin_transaction($conn);

try {
    $products = [];
    $total = 0;

    /*
     * Lấy lại giá và tồn kho trực tiếp từ database.
     * Không sử dụng giá gửi từ trình duyệt.
     */
    $selectProduct = mysqli_prepare(
        $conn,
        "SELECT id, name, price, stock
         FROM products
         WHERE id = ?
         FOR UPDATE"
    );

    foreach ($cart as $productId => $quantity) {
        $productId = filter_var($productId, FILTER_VALIDATE_INT);
        $quantity = filter_var($quantity, FILTER_VALIDATE_INT);

        if (!$productId || !$quantity || $quantity < 1) {
            throw new Exception('Dữ liệu giỏ hàng không hợp lệ.');
        }

        mysqli_stmt_bind_param($selectProduct, 'i', $productId);
        mysqli_stmt_execute($selectProduct);

        $product = mysqli_fetch_assoc(
            mysqli_stmt_get_result($selectProduct)
        );

        if (!$product) {
            throw new Exception(
                'Một sản phẩm trong giỏ hàng không còn tồn tại.'
            );
        }

        if ((int) $product['stock'] < $quantity) {
            throw new Exception(
                'Sản phẩm "' . $product['name']
                . '" không còn đủ số lượng.'
            );
        }

        $price = (float) $product['price'];
        $subtotal = $price * $quantity;
        $total += $subtotal;

        $products[] = [
            'id' => (int) $product['id'],
            'quantity' => $quantity,
            'price' => $price
        ];
    }

    /*
     * Chưa làm đăng nhập nên user_id để NULL.
     */
    $userId = isset($_SESSION['user']['id'])
        ? (int) $_SESSION['user']['id']
        : null;

    $insertOrder = mysqli_prepare(
        $conn,
        "INSERT INTO orders
            (user_id, fullname, phone, address, note, total, status)
         VALUES
            (?, ?, ?, ?, ?, ?, 'pending')"
    );

    mysqli_stmt_bind_param(
        $insertOrder,
        'issssd',
        $userId,
        $fullname,
        $phone,
        $address,
        $note,
        $total
    );

    if (!mysqli_stmt_execute($insertOrder)) {
        throw new Exception('Không thể tạo đơn hàng.');
    }

    $orderId = mysqli_insert_id($conn);

    $insertDetail = mysqli_prepare(
        $conn,
        "INSERT INTO order_details
            (order_id, product_id, quantity, price)
         VALUES (?, ?, ?, ?)"
    );

    $updateStock = mysqli_prepare(
        $conn,
        "UPDATE products
         SET stock = stock - ?
         WHERE id = ? AND stock >= ?"
    );

    foreach ($products as $product) {
        $productId = $product['id'];
        $quantity = $product['quantity'];
        $price = $product['price'];

        mysqli_stmt_bind_param(
            $insertDetail,
            'iiid',
            $orderId,
            $productId,
            $quantity,
            $price
        );

        if (!mysqli_stmt_execute($insertDetail)) {
            throw new Exception('Không thể lưu chi tiết đơn hàng.');
        }

        mysqli_stmt_bind_param(
            $updateStock,
            'iii',
            $quantity,
            $productId,
            $quantity
        );

        if (!mysqli_stmt_execute($updateStock)
            || mysqli_stmt_affected_rows($updateStock) !== 1) {
            throw new Exception('Không thể cập nhật tồn kho.');
        }
    }

    mysqli_commit($conn);

    unset(
        $_SESSION['cart'],
        $_SESSION['checkout_old'],
        $_SESSION['checkout_error']
    );

    $_SESSION['completed_order_id'] = $orderId;

    header('Location: ../pages/order-success.php');
    exit;
} catch (Throwable $error) {
    mysqli_rollback($conn);

    $_SESSION['checkout_error'] = $error->getMessage();

    header('Location: ../pages/checkout.php');
    exit;
}