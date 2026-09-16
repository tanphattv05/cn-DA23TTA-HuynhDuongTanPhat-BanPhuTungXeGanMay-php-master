<?php
session_start();

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/cart.php');
    exit;
}

$quantities = $_POST['quantities'] ?? [];

foreach ($quantities as $productId => $quantity) {
    $productId = filter_var($productId, FILTER_VALIDATE_INT);
    $quantity = filter_var($quantity, FILTER_VALIDATE_INT);

    if (!$productId) {
        continue;
    }

    if (!$quantity || $quantity < 1) {
        unset($_SESSION['cart'][$productId]);
        continue;
    }

    $stmt = mysqli_prepare(
        $conn,
        "SELECT stock FROM products WHERE id = ?"
    );

    mysqli_stmt_bind_param($stmt, 'i', $productId);
    mysqli_stmt_execute($stmt);

    $product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$product || (int) $product['stock'] < 1) {
        unset($_SESSION['cart'][$productId]);
        continue;
    }

    $_SESSION['cart'][$productId] = min(
        $quantity,
        (int) $product['stock']
    );
}

header('Location: ../pages/cart.php');
exit;