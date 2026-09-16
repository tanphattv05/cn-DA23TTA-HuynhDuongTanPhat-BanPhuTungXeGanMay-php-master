<?php
session_start();

/*
 * Chỉ xóa thông tin đăng nhập.
 * Giữ lại giỏ hàng hiện tại của khách.
 */
unset($_SESSION['user']);

session_regenerate_id(true);

header('Location: ../index.php');
exit;