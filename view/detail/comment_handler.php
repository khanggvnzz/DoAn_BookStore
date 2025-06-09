<?php

session_start();
require_once '../../model/Database.php';

// Set header for JSON response
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Vui lòng đăng nhập để thực hiện hành động này'
    ]);
    exit();
}

$db = new Database();
$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Phương thức không được hỗ trợ'
    ]);
    exit();
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'add_comment':
            $bookId = isset($_POST['book_id']) ? intval($_POST['book_id']) : 0;
            $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
            $content = isset($_POST['content']) ? trim($_POST['content']) : '';

            // Validate input
            if ($bookId <= 0) {
                throw new Exception('ID sách không hợp lệ');
            }

            if ($rating < 1 || $rating > 5) {
                throw new Exception('Đánh giá phải từ 1 đến 5 sao');
            }

            if (empty($content)) {
                throw new Exception('Nội dung bình luận không được để trống');
            }

            if (strlen($content) > 1000) {
                throw new Exception('Nội dung bình luận không được quá 1000 ký tự');
            }

            // Check if book exists
            $book = $db->getBookById($bookId);
            if (!$book) {
                throw new Exception('Sách không tồn tại');
            }

            // Check if user already commented
            if ($db->hasUserCommentedOnBook($userId, $bookId)) {
                throw new Exception('Bạn đã đánh giá sách này rồi');
            }

            // Add comment
            $commentData = [
                'user_id' => $userId,
                'id' => $bookId,
                'content' => $content,
                'vote' => $rating
            ];

            $result = $db->addComment($commentData);

            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Đánh giá của bạn đã được gửi thành công!'
                ]);
            } else {
                throw new Exception('Có lỗi xảy ra khi lưu đánh giá');
            }
            break;

        case 'delete_comment':
            $commentId = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;

            if ($commentId <= 0) {
                throw new Exception('ID bình luận không hợp lệ');
            }

            // Get comment to check ownership
            $comment = $db->getCommentById($commentId);
            if (!$comment) {
                throw new Exception('Bình luận không tồn tại');
            }

            // Check if user owns this comment
            if ($comment['user_id'] != $userId) {
                throw new Exception('Bạn không có quyền xóa bình luận này');
            }

            // Delete comment
            $result = $db->removeComment($commentId);

            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Đánh giá đã được xóa thành công!'
                ]);
            } else {
                throw new Exception('Có lỗi xảy ra khi xóa đánh giá');
            }
            break;

        case 'edit_comment':
            $commentId = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;
            $content = isset($_POST['content']) ? trim($_POST['content']) : '';
            $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;

            if ($commentId <= 0) {
                throw new Exception('ID bình luận không hợp lệ');
            }

            if (empty($content)) {
                throw new Exception('Nội dung bình luận không được để trống');
            }

            if ($rating < 1 || $rating > 5) {
                throw new Exception('Đánh giá phải từ 1 đến 5 sao');
            }

            // Get comment to check ownership
            $comment = $db->getCommentById($commentId);
            if (!$comment) {
                throw new Exception('Bình luận không tồn tại');
            }

            // Check if user owns this comment
            if ($comment['user_id'] != $userId) {
                throw new Exception('Bạn không có quyền chỉnh sửa bình luận này');
            }

            // Update comment
            $updateData = [
                'content' => $content,
                'vote' => $rating
            ];

            $result = $db->updateComment($commentId, $updateData);

            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Đánh giá đã được cập nhật thành công!'
                ]);
            } else {
                throw new Exception('Có lỗi xảy ra khi cập nhật đánh giá');
            }
            break;

        default:
            throw new Exception('Hành động không được hỗ trợ');
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>