<?php
session_start();

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/products.php');
    exit;
}

$productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
$quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

if (!$productId || !$quantity || $quantity < 1) {
    header('Location: ../pages/products.php');
    exit;
}

$stmt = mysqli_prepare(
    $conn,
    "SELECT id, stock FROM products WHERE id = ?"
);

mysqli_stmt_bind_param($stmt, 'i', $productId);
mysqli_stmt_execute($stmt);

$product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$product || (int) $product['stock'] < 1) {
    header('Location: ../pages/products.php');
    exit;
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$currentQuantity = $_SESSION['cart'][$productId] ?? 0;
$newQuantity = $currentQuantity + $quantity;

/* Không cho số lượng trong giỏ vượt quá tồn kho */
$_SESSION['cart'][$productId] = min(
    $newQuantity,
    (int) $product['stock']
);

header('Location: ../pages/cart.php');
exit;