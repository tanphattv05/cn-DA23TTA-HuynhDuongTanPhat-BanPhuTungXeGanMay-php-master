<?php
session_start();

// Preserve only the cart; discard authentication, recipient data and old tokens.
$cart = is_array($_SESSION['cart'] ?? null) ? $_SESSION['cart'] : [];
$_SESSION = ['cart' => $cart];
session_regenerate_id(true);

header('Location: ../index.php');
exit;