<?php
require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: orders.php');
    exit;
}

$token = $_POST['csrf_token'] ?? '';

if (!is_string($token)
    || empty($_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $token)) {
    $_SESSION['admin_error'] =
        'Yêu cầu không hợp lệ. Vui lòng thử lại.';

    header('Location: orders.php');
    exit;
}

$orderId = filter_input(
    INPUT_POST,
    'order_id',
    FILTER_VALIDATE_INT
);

$newStatus = $_POST['status'] ?? '';

$transitions = [
    'pending' => ['confirmed', 'cancelled'],
    'confirmed' => ['shipping', 'cancelled'],
    'shipping' => ['completed'],
    'completed' => [],
    'cancelled' => []
];

if (!$orderId || $orderId < 1 || !is_string($newStatus)) {
    $_SESSION['admin_error'] = 'Dữ liệu không hợp lệ.';

    header('Location: orders.php');
    exit;
}

/* Chuyển lỗi MySQL thành exception để transaction được rollback */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    mysqli_begin_transaction($conn);

    /* Khóa đơn hàng để tránh cập nhật đồng thời */
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, status
         FROM orders
         WHERE id = ?
         FOR UPDATE"
    );

    mysqli_stmt_bind_param($stmt, 'i', $orderId);
    mysqli_stmt_execute($stmt);

    $order = mysqli_fetch_assoc(
        mysqli_stmt_get_result($stmt)
    );

    if (!$order) {
        throw new RuntimeException('Không tìm thấy đơn hàng.');
    }

    $oldStatus = $order['status'];
    $allowed = $transitions[$oldStatus] ?? [];

    if (!in_array($newStatus, $allowed, true)) {
        throw new RuntimeException(
            'Không được chuyển đơn hàng sang trạng thái này.'
        );
    }

    /* Hoàn trả tồn kho khi hủy đơn */
    if ($newStatus === 'cancelled') {
        $detailStmt = mysqli_prepare(
            $conn,
            "SELECT product_id, quantity
             FROM order_details
             WHERE order_id = ?
             ORDER BY product_id"
        );

        mysqli_stmt_bind_param($detailStmt, 'i', $orderId);
        mysqli_stmt_execute($detailStmt);

        $details = mysqli_stmt_get_result($detailStmt);

        $restoreStmt = mysqli_prepare(
            $conn,
            "UPDATE products
             SET stock = stock + ?
             WHERE id = ?"
        );

        while ($detail = mysqli_fetch_assoc($details)) {
            $productId = (int) $detail['product_id'];
            $quantity = (int) $detail['quantity'];

            mysqli_stmt_bind_param(
                $restoreStmt,
                'ii',
                $quantity,
                $productId
            );

            mysqli_stmt_execute($restoreStmt);

            if (mysqli_stmt_affected_rows($restoreStmt) !== 1) {
                throw new RuntimeException(
                    'Không thể hoàn trả tồn kho cho sản phẩm.'
                );
            }
        }
    }

    $updateStmt = mysqli_prepare(
        $conn,
        "UPDATE orders SET status = ? WHERE id = ?"
    );

    mysqli_stmt_bind_param(
        $updateStmt,
        'si',
        $newStatus,
        $orderId
    );

    mysqli_stmt_execute($updateStmt);

    mysqli_commit($conn);

    $_SESSION['admin_success'] =
        'Đã cập nhật đơn hàng #' . $orderId . '.';
} catch (Throwable $error) {
    mysqli_rollback($conn);

    if ($error instanceof mysqli_sql_exception) {
        error_log($error->getMessage());

        $_SESSION['admin_error'] =
            'Có lỗi database. Đơn hàng chưa được cập nhật.';
    } else {
        $_SESSION['admin_error'] = $error->getMessage();
    }
}

header('Location: orders.php');
exit;