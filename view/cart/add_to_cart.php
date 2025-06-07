<?php
session_start();
require_once __DIR__ . '/../../model/Database.php';

// Set header cho JSON response
header('Content-Type: application/json');

// Kiểm tra method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Kiểm tra user đã đăng nhập
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập để thêm vào giỏ hàng']);
    exit;
}

// Lấy dữ liệu từ POST
$input = json_decode(file_get_contents('php://input'), true);
$bookId = isset($input['book_id']) ? (int) $input['book_id'] : 0;
$quantity = isset($input['quantity']) ? (int) $input['quantity'] : 1;
$userId = $_SESSION['user_id'];

// Validate input
if ($bookId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID sách không hợp lệ']);
    exit;
}

if ($quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Số lượng không hợp lệ']);
    exit;
}

try {
    $database = new Database();

    // Kiểm tra sách có tồn tại và còn hàng không
    $book = $database->fetch("SELECT * FROM books WHERE id = :id", ['id' => $bookId]);

    if (!$book) {
        echo json_encode(['success' => false, 'message' => 'Sách không tồn tại']);
        exit;
    }

    if ($book['stock'] < $quantity) {
        echo json_encode(['success' => false, 'message' => 'Không đủ hàng trong kho']);
        exit;
    }

    // Thêm vào giỏ hàng
    $result = $database->addToCart($userId, $bookId, $quantity);

    if ($result == 0 || $result) {
        // Lấy số lượng items trong giỏ hàng để cập nhật UI
        $cartItemCount = $database->getCartItemCount($userId);

        echo json_encode([
            'success' => true,
            'message' => 'Đã thêm sách vào giỏ hàng thành công',
            'cart_count' => $cartItemCount
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra khi thêm vào giỏ hàng']);
    }

} catch (Exception $e) {
    error_log('Add to cart error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra, vui lòng thử lại']);
}
?>