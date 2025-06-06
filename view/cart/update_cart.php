<?php
// Đảm bảo bắt đầu session
session_start();

// Đảm bảo header JSON được đặt trước bất kỳ output nào
header('Content-Type: application/json');

// Import các file cần thiết
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../model/CartModel.php';

try {
    // Kiểm tra tham số đầu vào
    if (!isset($_POST['book_id'])) {
        throw new Exception('Missing book_id parameter');
    }

    $bookId = (int) $_POST['book_id'];
    $quantity = isset($_POST['quantity']) ? (int) $_POST['quantity'] : 0;

    // Kiểm tra người dùng đăng nhập hay chưa
    $isLoggedIn = isset($_SESSION['user_id']);

    if ($isLoggedIn) {
        // Cập nhật giỏ hàng trong database
        $database = new Database();
        $db = $database->connect();
        $cartModel = new CartModel($db);
        $userId = $_SESSION['user_id'];
        
        if ($quantity === 0) {
            // Xóa sản phẩm khỏi giỏ hàng
            $success = $cartModel->removeFromCart($userId, $bookId);
        } else {
            // Cập nhật số lượng
            $success = $cartModel->updateCartItem($userId, $bookId, $quantity);
        }
        
        echo json_encode(['success' => $success]);
    } else {
        // Cập nhật giỏ hàng trong session
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        if ($quantity === 0) {
            // Xóa sản phẩm khỏi giỏ hàng
            if (isset($_SESSION['cart'][$bookId])) {
                unset($_SESSION['cart'][$bookId]);
            }
        } else {
            // Cập nhật số lượng
            $_SESSION['cart'][$bookId] = $quantity;
        }
        
        echo json_encode(['success' => true]);
    }
} catch (Exception $e) {
    // Trả về lỗi dưới dạng JSON
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>