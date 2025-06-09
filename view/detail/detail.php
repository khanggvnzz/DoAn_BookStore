<?php
session_start();
require_once '../../model/Database.php';

// Khởi tạo database
$db = new Database();

// Xử lý AJAX request cho thêm vào giỏ hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    header('Content-Type: application/json');

    try {
        // Kiểm tra đăng nhập
        if (!isset($_SESSION['user_id'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Vui lòng đăng nhập để thêm sách vào giỏ hàng',
                'redirect' => '/DoAn_BookStore/view/auth/login.php'
            ]);
            exit();
        }

        $bookId = isset($_POST['book_id']) ? intval($_POST['book_id']) : 0;
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
        $userId = $_SESSION['user_id'];

        // Validate dữ liệu
        if ($bookId <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'ID sách không hợp lệ'
            ]);
            exit();
        }

        if ($quantity <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Số lượng phải lớn hơn 0'
            ]);
            exit();
        }

        // Kiểm tra sách có tồn tại không
        $book = $db->fetch("SELECT * FROM books WHERE id = :id", ['id' => $bookId]);

        if (!$book) {
            echo json_encode([
                'success' => false,
                'message' => 'Sách không tồn tại'
            ]);
            exit();
        }

        // Kiểm tra tồn kho
        if ($book['stock'] <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Sách đã hết hàng'
            ]);
            exit();
        }

        // Kiểm tra số lượng có vượt quá tồn kho không
        $currentCartQuantity = 0;
        $existingCartItem = $db->fetch(
            "SELECT quantity FROM cart WHERE user_id = :user_id AND id = :book_id",
            ['user_id' => $userId, 'book_id' => $bookId]
        );

        if ($existingCartItem) {
            $currentCartQuantity = $existingCartItem['quantity'];
        }

        $totalRequestedQuantity = $currentCartQuantity + $quantity;

        if ($totalRequestedQuantity > $book['stock']) {
            $availableQuantity = $book['stock'] - $currentCartQuantity;

            if ($availableQuantity <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Bạn đã thêm tối đa số lượng có thể cho sách này'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => "Chỉ có thể thêm tối đa {$availableQuantity} cuốn nữa vào giỏ hàng"
                ]);
            }
            exit();
        }

        // Thêm vào giỏ hàng
        $result = $db->addToCart($userId, $bookId, $quantity);


        if ($result == 0 || $result) {
            // Lấy thông tin giỏ hàng cập nhật
            $cartCount = $db->getCartItemCount($userId);
            $cartTotal = $db->getCartTotal($userId);

            echo json_encode([
                'success' => true,
                'message' => 'Đã thêm sách vào giỏ hàng thành công!',
                'data' => [
                    'cart_count' => $cartCount,
                    'cart_total' => number_format($cartTotal * 1000, 0, ',', '.') . ' VNĐ',
                    'book_title' => $book['title'],
                    'quantity_added' => $quantity
                ]
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi thêm vào giỏ hàng'
            ]);
        }

    } catch (Exception $e) {
        error_log('Add to cart error: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Có lỗi hệ thống xảy ra: ' . $e->getMessage()
        ]);
    }
    exit();
}

// Xử lý AJAX request cho lấy số lượng giỏ hàng
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_cart_count') {
    header('Content-Type: application/json');

    try {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode([
                'success' => false,
                'count' => 0,
                'message' => 'Chưa đăng nhập'
            ]);
            exit();
        }

        $userId = $_SESSION['user_id'];
        $count = $db->getCartItemCount($userId);
        $total = $db->getCartTotal($userId);

        echo json_encode([
            'success' => true,
            'count' => $count,
            'total' => $total,
            'formatted_total' => number_format($total * 1000, 0, ',', '.') . ' VNĐ'
        ]);

    } catch (Exception $e) {
        error_log('Get cart count error: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'count' => 0,
            'message' => 'Có lỗi xảy ra'
        ]);
    }
    exit();
}

// Lấy ID sách từ URL
$bookId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($bookId <= 0) {
    header('Location: ../home/index.php');
    exit();
}

// Lấy thông tin chi tiết sách
$book = $db->fetch("SELECT * FROM books WHERE id = :id", ['id' => $bookId]);

if (!$book) {
    header('Location: ../home/index.php');
    exit();
}

// Lấy sách liên quan (cùng category, trừ sách hiện tại)
$relatedBooks = $db->fetchAll(
    "SELECT * FROM books WHERE category = :category AND id != :id ORDER BY RAND() LIMIT 4",
    ['category' => $book['category'], 'id' => $bookId]
);

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($book['title']); ?> - Chi tiết sách</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/DoAn_BookStore/view/detail/detail.css">
</head>

<body>
    <?php include '../navigation/navigation.php'; ?>
    <div class="container">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/DoAn_BookStore/index.php?controller=home&action=index"><i
                            class="fas fa-home"></i> Trang
                        chủ</a>
                </li>
                <li class="breadcrumb-item"><a href="/DoAn_BookStore/index.php?controller=books&action=list">Sách</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($book['title']); ?>
                </li>
            </ol>
        </nav>

        <!-- Chi tiết sách -->
        <div class="book-detail-container">
            <div class="row">
                <!-- Hình ảnh sách -->
                <div class="col-md-5">
                    <img src="<?php echo !empty($book['image']) ? '../../images/books/' . $book['image'] : '../../assets/images/no-image.jpg'; ?>"
                        alt="<?php echo htmlspecialchars($book['title']); ?>" class="book-image">
                </div>

                <!-- Thông tin sách -->
                <div class="col-md-7 book-info">
                    <h1 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h1>

                    <p class="book-author">
                        <i class="fas fa-user"></i> Tác giả:
                        <strong><?php echo htmlspecialchars($book['author']); ?></strong>
                    </p>

                    <div class="book-category">
                        <i class="fas fa-tag"></i> <?php echo htmlspecialchars($book['category']); ?>
                    </div>

                    <!-- Rating -->
                    <div class="rating-stars">
                        <?php
                        // Lấy đánh giá trung bình từ comments thay vì từ book rating
                        $avgVote = $db->getBookAverageVote($bookId);
                        $rating = $avgVote['average_vote'];
                        $totalVotes = $avgVote['total_votes'];

                        for ($i = 1; $i <= 5; $i++) {
                            if ($i <= $rating) {
                                echo '<i class="fas fa-star"></i>';
                            } elseif ($i - 0.5 <= $rating) {
                                echo '<i class="fas fa-star-half-alt"></i>';
                            } else {
                                echo '<i class="far fa-star"></i>';
                            }
                        }

                        if ($totalVotes > 0) {
                            echo ' (' . number_format($rating, 1) . '/5 - ' . $totalVotes . ' đánh giá)';
                        } else {
                            echo ' (Chưa có đánh giá)';
                        }
                        ?>
                    </div>

                    <!-- Giá -->
                    <div class="book-price">
                        <i class="fas fa-tags price-icon"></i>
                        <?php
                        $finalPrice = $book['price'] * 1000;
                        echo number_format($finalPrice, 0, ',', '.');
                        ?>
                        <span class="currency">VNĐ</span>
                    </div>

                    <!-- Tồn kho -->
                    <div class="book-stock">
                        <?php if ($book['stock'] > 0): ?>
                            <span class="in-stock">
                                <i class="fas fa-check-circle"></i> Còn hàng (<?php echo $book['stock']; ?> cuốn)
                            </span>
                        <?php else: ?>
                            <span class="out-of-stock">
                                <i class="fas fa-times-circle"></i> Hết hàng
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ($book['stock'] > 0): ?>
                        <!-- Chọn số lượng -->
                        <div class="quantity-selector">
                            <span>Số lượng:</span>
                            <button type="button" class="quantity-btn" onclick="decreaseQuantity()">-</button>
                            <input type="number" id="quantity" class="quantity-input" value="1" min="1"
                                max="<?php echo $book['stock']; ?>">
                            <button type="button" class="quantity-btn" onclick="increaseQuantity()">+</button>
                        </div>

                        <!-- Nút mua hàng -->
                        <div class="purchase-buttons">
                            <button type="button" class="btn btn-add-to-cart"
                                onclick="addToCart(<?php echo $book['id']; ?>)">
                                <i class="fas fa-shopping-cart"></i> Thêm vào giỏ hàng
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Mô tả sách -->
            <?php if (!empty($book['description'])): ?>
                <div class="book-description">
                    <h3><i class="fas fa-info-circle"></i> Mô tả sách</h3>
                    <p><?php echo nl2br(htmlspecialchars($book['description'])); ?></p>
                </div>
            <?php endif; ?>

            <!-- Gợi ý đăng nhập để đánh giá (chỉ hiển thị khi chưa đăng nhập) -->
            <?php if (!isset($_SESSION['user_id'])): ?>
                <div class="login-suggestion-card mt-4">
                    <div class="card border-warning">
                        <div class="card-body text-center">
                            <div class="login-suggestion-icon mb-3">
                                <i class="fas fa-star fa-3x text-warning"></i>
                            </div>
                            <h5 class="card-title text-dark">
                                <i class="fas fa-comments"></i> Bạn đã đọc cuốn sách này chưa?
                            </h5>
                            <p class="card-text text-muted">
                                Hãy chia sẻ cảm nhận và đánh giá của bạn để giúp những độc giả khác có thêm thông tin tham
                                khảo!
                            </p>
                            <div class="login-suggestion-buttons">
                                <a href="/DoAn_BookStore/view/auth/login.php" class="btn btn-warning btn-lg me-2">
                                    <i class="fas fa-sign-in-alt"></i> Đăng nhập để đánh giá
                                </a>
                                <a href="/DoAn_BookStore/view/auth/register.php" class="btn btn-outline-warning btn-lg">
                                    <i class="fas fa-user-plus"></i> Đăng ký tài khoản
                                </a>
                            </div>
                            <div class="login-benefits mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-check-circle text-success"></i> Viết đánh giá và nhận xét
                                    <span class="mx-2">•</span>
                                    <i class="fas fa-check-circle text-success"></i> Lưu sách yêu thích
                                    <span class="mx-2">•</span>
                                    <i class="fas fa-check-circle text-success"></i> Mua sách dễ dàng
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Phần đánh giá và bình luận -->
            <div class="comments-section mt-5">
                <h3><i class="fas fa-comments"></i> Đánh giá và bình luận</h3>

                <!-- Hiển thị số sao trung bình -->
                <?php
                $avgVote = $db->getBookAverageVote($bookId);
                ?>
                <div class="rating-summary mb-4">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="avg-rating">
                                <span class="rating-score"><?php echo $avgVote['average_vote']; ?></span>
                                <div class="rating-stars-large">
                                    <?php
                                    $avgRating = $avgVote['average_vote'];
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $avgRating) {
                                            echo '<i class="fas fa-star"></i>';
                                        } elseif ($i - 0.5 <= $avgRating) {
                                            echo '<i class="fas fa-star-half-alt"></i>';
                                        } else {
                                            echo '<i class="far fa-star"></i>';
                                        }
                                    }
                                    ?>
                                </div>
                                <div class="rating-count">
                                    (<?php echo $avgVote['total_votes']; ?> đánh giá)
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form thêm bình luận (chỉ hiển thị khi đã đăng nhập) -->
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php
                    $hasCommented = $db->hasUserCommentedOnBook($_SESSION['user_id'], $bookId);
                    ?>
                    <?php if (!$hasCommented): ?>
                        <div class="add-comment-form mb-4">
                            <h4>Viết đánh giá</h4>
                            <form id="commentForm">
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <label class="form-label">Đánh giá của bạn:</label>
                                        <div class="rating-input">
                                            <input type="radio" name="rating" value="5" id="star5">
                                            <label for="star5"><i class="fas fa-star"></i></label>
                                            <input type="radio" name="rating" value="4" id="star4">
                                            <label for="star4"><i class="fas fa-star"></i></label>
                                            <input type="radio" name="rating" value="3" id="star3">
                                            <label for="star3"><i class="fas fa-star"></i></label>
                                            <input type="radio" name="rating" value="2" id="star2">
                                            <label for="star2"><i class="fas fa-star"></i></label>
                                            <input type="radio" name="rating" value="1" id="star1">
                                            <label for="star1"><i class="fas fa-star"></i></label>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label for="commentContent" class="form-label">Nội dung bình luận:</label>
                                        <textarea class="form-control" id="commentContent" name="content" rows="4"
                                            placeholder="Chia sẻ cảm nhận của bạn về cuốn sách này..." required></textarea>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-paper-plane"></i> Gửi đánh giá
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Bạn đã đánh giá sách này rồi.
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-sign-in-alt"></i>
                        <a href="/DoAn_BookStore/view/auth/login.php">Đăng nhập</a> để viết đánh giá.
                    </div>
                <?php endif; ?>

                <!-- Danh sách bình luận -->
                <?php
                $commentsData = $db->getCommentsByBookId($bookId, 1, 10);
                $comments = $commentsData['comments'];
                ?>

                <div class="comments-list">
                    <h4>Các đánh giá khác (<?php echo $commentsData['pagination']['total_records']; ?>)</h4>

                    <?php if (!empty($comments)): ?>
                        <div id="commentsList">
                            <?php foreach ($comments as $comment): ?>
                                <div class="comment-item" data-comment-id="<?php echo $comment['cmt_id']; ?>">
                                    <div class="comment-header">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong class="comment-author">
                                                    <?php echo htmlspecialchars($comment['user_name'] ?: $comment['username']); ?>
                                                </strong>
                                                <div class="comment-rating">
                                                    <?php
                                                    if ($comment['vote'] > 0) {
                                                        for ($i = 1; $i <= 5; $i++) {
                                                            if ($i <= $comment['vote']) {
                                                                echo '<i class="fas fa-star text-warning"></i>';
                                                            } else {
                                                                echo '<i class="far fa-star text-muted"></i>';
                                                            }
                                                        }
                                                    }
                                                    ?>
                                                </div>
                                                <small class="text-muted comment-date">
                                                    <?php echo date('d/m/Y H:i', strtotime($comment['create_at'])); ?>
                                                </small>
                                            </div>

                                            <!-- Menu cho comment của user hiện tại -->
                                            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $comment['user_id']): ?>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button"
                                                        data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item edit-comment-btn" href="#"
                                                                data-comment-id="<?php echo $comment['cmt_id']; ?>">
                                                                <i class="fas fa-edit"></i> Chỉnh sửa
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item delete-comment-btn text-danger" href="#"
                                                                data-comment-id="<?php echo $comment['cmt_id']; ?>">
                                                                <i class="fas fa-trash"></i> Xóa
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="comment-content">
                                        <p class="comment-text"><?php echo nl2br(htmlspecialchars($comment['content'])); ?></p>

                                        <?php if (!empty($comment['image'])): ?>
                                            <div class="comment-image">
                                                <img src="../../images/comments/<?php echo htmlspecialchars($comment['image']); ?>"
                                                    class="img-thumbnail" style="max-width: 200px;" alt="Hình ảnh đánh giá">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pagination cho comments -->
                        <?php if ($commentsData['pagination']['total_pages'] > 1): ?>
                            <div class="comments-pagination mt-4">
                                <nav>
                                    <ul class="pagination justify-content-center">
                                        <?php if ($commentsData['pagination']['has_previous']): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="#"
                                                    onclick="loadComments(<?php echo $commentsData['pagination']['current_page'] - 1; ?>)">
                                                    Trước
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php for ($i = 1; $i <= $commentsData['pagination']['total_pages']; $i++): ?>
                                            <li
                                                class="page-item <?php echo $i == $commentsData['pagination']['current_page'] ? 'active' : ''; ?>">
                                                <a class="page-link" href="#" onclick="loadComments(<?php echo $i; ?>)">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>

                                        <?php if ($commentsData['pagination']['has_next']): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="#"
                                                    onclick="loadComments(<?php echo $commentsData['pagination']['current_page'] + 1; ?>)">
                                                    Sau
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="no-comments text-center py-4">
                            <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Chưa có đánh giá nào cho sách này.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sách liên quan -->
        <?php if (!empty($relatedBooks)): ?>
            <div class="related-books">
                <h3><i class="fas fa-book"></i> Sách liên quan</h3>
                <div class="row">
                    <?php foreach ($relatedBooks as $relatedBook): ?>
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                            <div class="related-book-card h-100"
                                onclick="window.location.href='/DoAn_BookStore/view/detail/detail.php?id=<?php echo $relatedBook['id']; ?>'"
                                style="cursor: pointer;">

                                <!-- Fixed size image container -->
                                <div class="related-book-image-container position-relative">
                                    <?php if (!empty($relatedBook['image'])): ?>
                                        <img src="../../images/books/<?php echo htmlspecialchars($relatedBook['image']); ?>"
                                            class="related-book-image" alt="<?php echo htmlspecialchars($relatedBook['title']); ?>"
                                            loading="lazy"
                                            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <!-- Fallback placeholder -->
                                        <div class="related-book-image-placeholder" style="display: none;">
                                            <i class="fas fa-book fa-2x text-muted"></i>
                                        </div>
                                    <?php else: ?>
                                        <div class="related-book-image-placeholder">
                                            <i class="fas fa-book fa-2x text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Card content -->
                                <div class="related-book-content">
                                    <!-- Title -->
                                    <h5 class="related-book-title">
                                        <?php echo htmlspecialchars($relatedBook['title']); ?>
                                    </h5>

                                    <!-- Author -->
                                    <p class="related-book-author">
                                        <i class="fas fa-user"></i>
                                        <?php echo htmlspecialchars($relatedBook['author']); ?>
                                    </p>
                                    <!-- Publisher -->
                                    <?php if (!empty($book['publisher'])): ?>
                                        <p class="text-muted mb-1 book-meta">
                                            <i class="fas fa-building"></i>
                                            <?php echo htmlspecialchars(substr($relatedBook['publisher'], 0, 20)) . (strlen($book['publisher']) > 20 ? '...' : ''); ?>
                                        </p>
                                    <?php endif; ?>

                                    <!-- Price container -->
                                    <div class="related-book-price-container">
                                        <p class="related-book-price">
                                            <i class="fas fa-tags price-icon"></i>
                                            <?php
                                            $relatedFinalPrice = $relatedBook['price'] * 1000;
                                            echo number_format($relatedFinalPrice, 0, ',', '.');
                                            ?>
                                            <span class="currency">VNĐ</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Tăng giảm số lượng
        function increaseQuantity() {
            const quantityInput = document.getElementById('quantity');
            const max = parseInt(quantityInput.getAttribute('max'));
            const current = parseInt(quantityInput.value);

            if (current < max) {
                quantityInput.value = current + 1;
            }
        }

        function decreaseQuantity() {
            const quantityInput = document.getElementById('quantity');
            const current = parseInt(quantityInput.value);

            if (current > 1) {
                quantityInput.value = current - 1;
            }
        }

        // Thêm vào giỏ hàng
        function addToCart(bookId) {
            const quantity = parseInt(document.getElementById('quantity').value);
            const addToCartBtn = document.querySelector('.btn-add-to-cart');

            // Disable button và hiển thị loading
            addToCartBtn.disabled = true;
            const originalText = addToCartBtn.innerHTML;
            addToCartBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang thêm...';

            // Gửi AJAX request đến add_to_cart.php
            fetch('/DoAn_BookStore/view/cart/add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    book_id: bookId,
                    quantity: quantity
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Hiển thị thông báo thành công
                        showNotification('success', data.message);

                        // Cập nhật số lượng giỏ hàng trên header
                        updateCartCount();

                        // Hiển thị thông tin chi tiết
                        if (data.data) {
                            console.log('Đã thêm:', data.data.quantity_added, 'cuốn', data.data.book_title);
                        }
                    } else {
                        // Hiển thị lỗi
                        showNotification('error', data.message);

                        // Nếu cần đăng nhập, chuyển hướng
                        if (data.redirect) {
                            setTimeout(() => {
                                window.location.href = data.redirect;
                            }, 2000);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('error', 'Có lỗi xảy ra khi thêm vào giỏ hàng');
                })
                .finally(() => {
                    // Khôi phục button
                    addToCartBtn.disabled = false;
                    addToCartBtn.innerHTML = originalText;
                });
        }

        // Cập nhật số lượng giỏ hàng
        function updateCartCount() {
            fetch('../../controller/cart/get_cart_count.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Cập nhật số lượng trên header
                        const cartCountElements = document.querySelectorAll('.cart-count');
                        cartCountElements.forEach(element => {
                            element.textContent = data.count;
                        });

                        // Cập nhật tổng tiền nếu có element
                        const cartTotalElements = document.querySelectorAll('.cart-total');
                        cartTotalElements.forEach(element => {
                            element.textContent = data.formatted_total;
                        });

                        // Cập nhật badge số lượng giỏ hàng
                        const cartBadges = document.querySelectorAll('.cart-badge');
                        cartBadges.forEach(badge => {
                            if (data.count > 0) {
                                badge.textContent = data.count;
                                badge.style.display = 'inline';
                            } else {
                                badge.style.display = 'none';
                            }
                        });
                    }
                })
                .catch(error => {
                    console.error('Error updating cart count:', error);
                });
        }

        // Hiển thị thông báo
        function showNotification(type, message) {
            // Tạo element thông báo
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; max-width: 400px;';

            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;

            document.body.appendChild(notification);

            // Tự động ẩn sau 5 giây
            setTimeout(() => {
                if (notification.parentNode) {
                    const bsAlert = new bootstrap.Alert(notification);
                    bsAlert.close();
                }
            }, 5000);
        }

        // Validate số lượng khi thay đổi
        document.getElementById('quantity').addEventListener('change', function () {
            const value = parseInt(this.value);
            const max = parseInt(this.getAttribute('max'));

            if (value < 1) {
                this.value = 1;
                showNotification('error', 'Số lượng tối thiểu là 1');
            } else if (value > max) {
                this.value = max;
                showNotification('error', `Số lượng tối đa là ${max} cuốn`);
            }
        });

        // Enhanced image loading for related books
        document.addEventListener('DOMContentLoaded', function () {
            // Load cart count when page loads
            updateCartCount();

            const relatedImages = document.querySelectorAll('.related-book-image');
            relatedImages.forEach(img => {
                img.addEventListener('load', function () {
                    this.style.opacity = '1';
                });

                img.addEventListener('error', function () {
                    console.log('Related book image failed to load:', this.src);
                    this.style.display = 'none';
                    const placeholder = this.nextElementSibling;
                    if (placeholder) {
                        placeholder.style.display = 'flex';
                    }
                });
            });
        });

        // Xử lý phím Enter trong input số lượng
        document.getElementById('quantity').addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const bookId = <?php echo $book['id']; ?>;
                addToCart(bookId);
            }
        });

        // Xử lý double click để tránh spam
        let isProcessing = false;
        document.querySelector('.btn-add-to-cart').addEventListener('click', function (e) {
            if (isProcessing) {
                e.preventDefault();
                return false;
            }
            isProcessing = true;
            setTimeout(() => {
                isProcessing = false;
            }, 2000);
        });

        // Comment functionality
        document.addEventListener('DOMContentLoaded', function () {
            // Handle comment form submission
            const commentForm = document.getElementById('commentForm');
            if (commentForm) {
                commentForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    submitComment();
                });
            }

            // Handle edit comment
            document.addEventListener('click', function (e) {
                if (e.target.classList.contains('edit-comment-btn') || e.target.closest('.edit-comment-btn')) {
                    e.preventDefault();
                    const btn = e.target.closest('.edit-comment-btn');
                    const commentId = btn.getAttribute('data-comment-id');
                    editComment(commentId);
                }
            });

            // Handle delete comment
            document.addEventListener('click', function (e) {
                if (e.target.classList.contains('delete-comment-btn') || e.target.closest('.delete-comment-btn')) {
                    e.preventDefault();
                    const btn = e.target.closest('.delete-comment-btn');
                    const commentId = btn.getAttribute('data-comment-id');
                    deleteComment(commentId);
                }
            });
        });

        // Submit comment
        function submitComment() {
            const form = document.getElementById('commentForm');
            const formData = new FormData();

            const rating = form.querySelector('input[name="rating"]:checked');
            const content = form.querySelector('#commentContent').value.trim();

            if (!rating) {
                showNotification('error', 'Vui lòng chọn số sao đánh giá');
                return;
            }

            if (!content) {
                showNotification('error', 'Vui lòng nhập nội dung bình luận');
                return;
            }

            formData.append('action', 'add_comment');
            formData.append('book_id', <?php echo $bookId; ?>);
            formData.append('rating', rating.value);
            formData.append('content', content);

            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang gửi...';

            fetch('/DoAn_BookStore/view/detail/comment_handler.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('success', data.message);
                        // Reload page để hiển thị comment mới
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        showNotification('error', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('error', 'Có lỗi xảy ra khi gửi đánh giá');
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                });
        }

        // Edit comment
        function editComment(commentId) {
            // Implement edit functionality if needed
            showNotification('info', 'Tính năng chỉnh sửa đang được phát triển');
        }

        // Delete comment
        function deleteComment(commentId) {
            if (!confirm('Bạn có chắc chắn muốn xóa đánh giá này?')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'delete_comment');
            formData.append('comment_id', commentId);

            fetch('/DoAn_BookStore/view/detail/comment_handler.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('success', data.message);
                        // Remove comment from DOM
                        const commentElement = document.querySelector(`[data-comment-id="${commentId}"]`);
                        if (commentElement) {
                            commentElement.remove();
                        }
                    } else {
                        showNotification('error', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('error', 'Có lỗi xảy ra khi xóa đánh giá');
                });
        }

        // Load comments (for pagination)
        function loadComments(page) {
            // Implement if you want AJAX pagination
            window.location.href = `?id=<?php echo $bookId; ?>&comment_page=${page}#comments-section`;
        }
    </script>

    <style>
        /* Notification styles */
        .alert {
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            animation: slideInRight 0.3s ease-out;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Button loading state */
        .btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        /* Loading spinner */
        .fa-spinner {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Add to cart button hover effect */
        .btn-add-to-cart:hover:not(:disabled) {
            background-color: #218838;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            transition: all 0.2s ease;
        }

        .btn-buy-now:hover:not(:disabled) {
            background-color: #e0a800;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            transition: all 0.2s ease;
        }

        /* Comment Section Styles */
        .comments-section {
            background: #f8f9fa;
            padding: 2rem;
            border-radius: 10px;
            margin-top: 2rem;
        }

        .rating-summary {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .avg-rating {
            text-align: center;
        }

        .rating-score {
            font-size: 3rem;
            font-weight: bold;
            color: #ffc107;
        }

        .rating-stars-large i {
            font-size: 1.5rem;
            color: #ffc107;
            margin: 0 2px;
        }

        .rating-count {
            color: #6c757d;
            margin-top: 0.5rem;
        }

        /* Rating Input */
        .rating-input {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 5px;
        }

        .rating-input input[type="radio"] {
            display: none;
        }

        .rating-input label {
            cursor: pointer;
            font-size: 1.5rem;
            color: #ddd;
            transition: color 0.2s;
        }

        .rating-input label:hover,
        .rating-input label:hover~label,
        .rating-input input[type="radio"]:checked~label {
            color: #ffc107;
        }

        /* Add Comment Form */
        .add-comment-form {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Comments List */
        .comments-list {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .comment-item {
            border-bottom: 1px solid #eee;
            padding: 1.5rem 0;
        }

        .comment-item:last-child {
            border-bottom: none;
        }

        .comment-header {
            margin-bottom: 1rem;
        }

        .comment-author {
            color: #2c3e50;
            font-size: 1.1rem;
        }

        .comment-rating {
            margin: 0.25rem 0;
        }

        .comment-rating i {
            font-size: 0.9rem;
        }

        .comment-date {
            font-size: 0.85rem;
        }

        .comment-content {
            color: #495057;
            line-height: 1.6;
        }

        .comment-text {
            margin-bottom: 1rem;
        }

        .comment-image img {
            border-radius: 4px;
            margin-top: 0.5rem;
        }

        /* No Comments */
        .no-comments {
            color: #6c757d;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .comments-section {
                padding: 1rem;
            }

            .rating-score {
                font-size: 2rem;
            }

            .rating-stars-large i {
                font-size: 1.2rem;
            }
        }
    </style>
</body>

</html>