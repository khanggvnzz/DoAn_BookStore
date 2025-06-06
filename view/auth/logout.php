<?php

session_start();

// Kiểm tra xem người dùng có đang đăng nhập không
if (!isset($_SESSION['user_id'])) {
    // Nếu chưa đăng nhập, chuyển hướng về trang chủ
    header('Location: /DoAn_BookStore/');
    exit;
}

// Lưu thông tin tạm thời để hiển thị thông báo
$username = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : (isset($_SESSION['username']) ? $_SESSION['username'] : 'người dùng');

// Xóa cookie "remember me" nếu có
if (isset($_COOKIE['remember_user'])) {
    setcookie('remember_user', '', time() - 3600, '/', '', false, true);
}

// Xóa tất cả session variables
$_SESSION = array();

// Xóa session cookie nếu có
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Hủy session
session_destroy();

// Bắt đầu session mới để lưu thông báo
session_start();

// Đặt thông báo đăng xuất thành công
$_SESSION['success'] = 'Đã đăng xuất thành công! Hẹn gặp lại ' . htmlspecialchars($username) . '!';

// Chuyển hướng về trang chủ
header('Location: /DoAn_BookStore/');
exit;
?>