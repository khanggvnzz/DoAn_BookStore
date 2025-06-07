<?php

require_once '../../model/Database.php';

// Khởi tạo database
$db = new Database();

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
                            <button type="button" class="btn btn-buy-now" onclick="buyNow(<?php echo $book['id']; ?>)">
                                <i class="fas fa-bolt"></i> Mua ngay
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
            const quantity = document.getElementById('quantity').value;

            // Gửi AJAX request để thêm vào giỏ hàng
            fetch('../../controller/cart/add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    book_id: bookId,
                    quantity: parseInt(quantity)
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Đã thêm sách vào giỏ hàng!');
                        // Cập nhật số lượng giỏ hàng trên header nếu có
                        updateCartCount();
                    } else {
                        alert('Có lỗi xảy ra: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Có lỗi xảy ra khi thêm vào giỏ hàng');
                });
        }

        // Mua ngay
        function buyNow(bookId) {
            const quantity = document.getElementById('quantity').value;

            // Chuyển hướng đến trang thanh toán với thông tin sách
            window.location.href = `../checkout/index.php?book_id=${bookId}&quantity=${quantity}&type=buy_now`;
        }

        // Cập nhật số lượng giỏ hàng
        function updateCartCount() {
            fetch('../../controller/cart/get_cart_count.php')
                .then(response => response.json())
                .then(data => {
                    const cartCountElement = document.querySelector('.cart-count');
                    if (cartCountElement) {
                        cartCountElement.textContent = data.count;
                    }
                })
                .catch(error => {
                    console.error('Error updating cart count:', error);
                });
        }

        // Validate số lượng khi thay đổi
        document.getElementById('quantity').addEventListener('change', function () {
            const value = parseInt(this.value);
            const max = parseInt(this.getAttribute('max'));

            if (value < 1) {
                this.value = 1;
            } else if (value > max) {
                this.value = max;
                alert(`Số lượng tối đa là ${max} cuốn`);
            }
        });

        // Enhanced image loading for related books
        document.addEventListener('DOMContentLoaded', function () {
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
    </script>
</body>

</html>