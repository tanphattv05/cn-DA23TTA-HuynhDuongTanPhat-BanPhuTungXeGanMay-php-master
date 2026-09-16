<?php
session_start();

$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($productId && isset($_SESSION['cart'][$productId])) {
    unset($_SESSION['cart'][$productId]);
}

header('Location: ../pages/cart.php');
exit;