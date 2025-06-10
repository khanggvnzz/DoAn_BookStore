<?php

require_once __DIR__ . '/../../model/Database.php';
require_once __DIR__ . '/../../model/BookModel.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'newest'; // Thêm sort parameter
$perPage = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 18;
$allowedPerPage = [10, 15, 30, 50, 70, 100];
if (!in_array($perPage, $allowedPerPage)) {
    $perPage = 18; // Default fallback
}

// Get books with pagination
try {
    $result = $database->getBooksWithPagination($page, $perPage, $search, $category, $sort);
    $books = $result['books'];
    $pagination = $result['pagination'];
} catch (Exception $e) {
    $books = [];
    $pagination = [];
    $error_message = "Error loading books: " . $e->getMessage();
}

$baseUrl = '/DoAn_BookStore';
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh Sách - BookStore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="view/view_list/view_list.css">
</head>

<body>
    <?php include 'view/navigation/navigation.php'; ?>
    <?php include 'view/banner/banner.php'; ?>
    <div class="container-fluid py-3">
        <!-- Header -->
        <div class="row mb-3">
            <div class="col-12">
                <h1 class="text-center mb-3">
                    <i class="fas fa-book"></i> Danh Sách
                </h1>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sort and Filter Controls -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex flex-wrap align-items-center justify-content-between bg-light p-3 rounded">
                    <!-- Sort Options -->
                    <div class="d-flex align-items-center mb-2 mb-md-0">
                        <label for="sortSelect" class="form-label me-2 mb-0">
                            <i class="fas fa-sort"></i> Sắp xếp:
                        </label>
                        <select id="sortSelect" class="form-select form-select-sm" style="width: auto;">
                            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Mới nhất</option>
                            <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Cũ nhất</option>
                            <option value="title_asc" <?php echo $sort === 'title_asc' ? 'selected' : ''; ?>>Tên A-Z
                            </option>
                            <option value="title_desc" <?php echo $sort === 'title_desc' ? 'selected' : ''; ?>>Tên Z-A
                            </option>
                            <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Giá thấp -
                                cao</option>
                            <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Giá cao -
                                thấp</option>
                            <option value="author_asc" <?php echo $sort === 'author_asc' ? 'selected' : ''; ?>>Tác giả A-Z
                            </option>
                            <option value="author_desc" <?php echo $sort === 'author_desc' ? 'selected' : ''; ?>>Tác giả
                                Z-A</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Books Grid -->
        <div class="row">
            <?php if (!empty($books)): ?>
                <?php foreach ($books as $book): ?>
                    <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                        <div class="card book-card h-100"
                            onclick="window.location.href='/DoAn_BookStore/view/detail/detail.php?id=<?php echo $book['id']; ?>'"
                            style="cursor: pointer;">

                            <!-- Fixed size image container -->
                            <div class="book-image-container position-relative">
                                <?php if (!empty($book['image'])): ?>
                                    <img src="images/books/<?php echo htmlspecialchars($book['image']); ?>" class="book-image"
                                        alt="<?php echo htmlspecialchars($book['title']); ?>" loading="lazy"
                                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <!-- Fallback placeholder -->
                                    <div class="book-image-placeholder" style="display: none;">
                                        <i class="fas fa-book fa-3x text-muted"></i>
                                    </div>
                                <?php else: ?>
                                    <div class="book-image-placeholder">
                                        <i class="fas fa-book fa-3x text-muted"></i>
                                    </div>
                                <?php endif; ?>

                                <!-- Category badge -->
                                <?php if (!empty($book['category'])): ?>
                                    <span class="position-absolute top-0 end-0 m-2 book-category">
                                        <?php echo htmlspecialchars($book['category']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Card content -->
                            <div class="card-body d-flex flex-column">
                                <!-- Title -->
                                <h6 class="book-title">
                                    <?php echo htmlspecialchars($book['title']); ?>
                                </h6>

                                <!-- Author -->
                                <p class="book-author mb-1">
                                    <i class="fas fa-user"></i>
                                    <?php echo htmlspecialchars($book['author']); ?>
                                </p>

                                <!-- Publisher -->
                                <?php if (!empty($book['publisher'])): ?>
                                    <p class="text-muted mb-1 book-meta">
                                        <i class="fas fa-building"></i>
                                        <?php echo htmlspecialchars(substr($book['publisher'], 0, 20)) . (strlen($book['publisher']) > 20 ? '...' : ''); ?>
                                    </p>
                                <?php endif; ?>

                                <!-- Date -->
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small class="text-muted book-meta">
                                        <i class="fas fa-calendar-alt"></i>
                                        <?php echo date('d/m/Y', strtotime($book['created_at'])); ?>
                                    </small>
                                </div>

                                <!-- Description -->
                                <?php if (!empty($book['description'])): ?>
                                    <p class="book-description mb-2">
                                        <?php echo htmlspecialchars(substr($book['description'], 0, 100)) . '...'; ?>
                                    </p>
                                <?php endif; ?>

                                <!-- Price and button -->
                                <div class="mt-auto">
                                    <div class="price-container">
                                        <p class="book-price">
                                            <i class="fas fa-tags price-icon"></i>
                                            <?php
                                            $finalPrice = $book['price'] * 1000;
                                            echo number_format($finalPrice, 0, ',', '.');
                                            ?>
                                            <span class="currency">VNĐ</span>
                                        </p>
                                    </div>

                                    <div class="d-grid">
                                        <button class="btn btn-primary-custom btn-sm"
                                            onclick="event.stopPropagation(); addToCart(<?php echo $book['id']; ?>)">
                                            <i class="fas fa-shopping-cart"></i> Thêm vào giỏ
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info text-center" role="alert">
                        <i class="fas fa-info-circle fa-3x mb-3"></i>
                        <h4>Không có sách nào</h4>
                        <p>Hiện tại chưa có sách nào trong cơ sở dữ liệu.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if (!empty($pagination) && $pagination['total_pages'] > 1): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <?php echo $database->generatePaginationHTML($pagination, $baseUrl); ?>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function addToCart(bookId, quantity = 1) {
            event.stopPropagation();

            // Hiển thị loading
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang thêm...';
            button.disabled = true;

            // Gửi AJAX request
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
                        showNotification(data.message, 'success');

                        // Cập nhật số lượng giỏ hàng trên header (nếu có)
                        updateCartCount(data.cart_count);

                        // Reset button
                        button.innerHTML = '<i class="fas fa-check"></i> Đã thêm';
                        button.classList.remove('btn-primary-custom');
                        button.classList.add('btn-success');

                        // Sau 2 giây reset lại button
                        setTimeout(() => {
                            button.innerHTML = originalText;
                            button.classList.remove('btn-success');
                            button.classList.add('btn-primary-custom');
                            button.disabled = false;
                        }, 2000);

                    } else {
                        // Hiển thị thông báo lỗi
                        showNotification(data.message, 'error');

                        // Reset button
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Có lỗi xảy ra, vui lòng thử lại', 'error');

                    // Reset button
                    button.innerHTML = originalText;
                    button.disabled = false;
                });
        }

        // Hàm hiển thị thông báo
        function showNotification(message, type = 'info') {
            // Tạo thông báo toast
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0`;
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;

            // Thêm vào container
            let toastContainer = document.getElementById('toast-container');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'toast-container';
                toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
                toastContainer.style.zIndex = '9999';
                document.body.appendChild(toastContainer);
            }

            toastContainer.appendChild(toast);

            // Hiển thị toast
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();

            // Xóa toast sau khi ẩn
            toast.addEventListener('hidden.bs.toast', () => {
                toast.remove();
            });
        }

        // Hàm cập nhật số lượng giỏ hàng
        function updateCartCount(count) {
            const cartCountElements = document.querySelectorAll('.cart-count, #cart-count');
            cartCountElements.forEach(element => {
                element.textContent = count;
                if (count > 0) {
                    element.style.display = 'inline';
                }
            });
        }

        // Sort functionality
        document.getElementById('sortSelect').addEventListener('change', function () {
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('sort', this.value);
            currentUrl.searchParams.set('page', '1'); // Reset to first page when sorting
            window.location.href = currentUrl.toString();
        });

        // Enhanced image loading
        document.addEventListener('DOMContentLoaded', function () {
            const images = document.querySelectorAll('.book-image');
            images.forEach(img => {
                img.addEventListener('load', function () {
                    this.style.opacity = '1';
                });

                img.addEventListener('error', function () {
                    console.log('Image failed to load:', this.src);
                    this.style.display = 'none';
                    const placeholder = this.nextElementSibling;
                    if (placeholder) {
                        placeholder.style.display = 'flex';
                    }
                });
            });
        });
    </script>

    <?php include 'view/footer/footer.php'; ?>
</body>

</html>