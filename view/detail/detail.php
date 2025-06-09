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
                        $rating = isset($book['rating']) ? (float) $book['rating'] : 0;
                        for ($i = 1; $i <= 5; $i++) {
                            if ($i <= $rating) {
                                echo '<i class="fas fa-star"></i>';
                            } elseif ($i - 0.5 <= $rating) {
                                echo '<i class="fas fa-star-half-alt"></i>';
                            } else {
                                echo '<i class="far fa-star"></i>';
                            }
                        }
                        echo ' (' . number_format($rating, 1) . '/5)';
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
    </style>
</body>

</html>