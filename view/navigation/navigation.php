<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get categories for dropdown
$categories = [];
try {
    if (!class_exists('Database')) {
        require_once __DIR__ . '/../../model/Database.php';
    }

    $database = new Database();
    $categories = $database->fetchAll("SELECT DISTINCT category FROM books WHERE category IS NOT NULL AND category != '' ORDER BY category");
} catch (Exception $e) {
    error_log('Error loading categories: ' . $e->getMessage());
    $categories = [];
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$isAdmin = isset($_SESSION['admin']) && $_SESSION['admin'] === true;
$username = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : (isset($_SESSION['username']) ? $_SESSION['username'] : '');
$userEmail = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '';
$userPermission = isset($_SESSION['user_permission']) ? $_SESSION['user_permission'] : 'user';

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/DoAn_BookStore/view/navigation/navigation.css">
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="/DoAn_BookStore/">
                <i class="fas fa-book"></i> BookStore
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/DoAn_BookStore/">
                            <i class="fas fa-home"></i> Trang chủ
                        </a>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-list"></i> Danh mục
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <li>
                                <a class="dropdown-item" href="/DoAn_BookStore/">
                                    <i class="fas fa-th-large"></i> Tất cả sách
                                </a>
                            </li>

                            <?php if (!empty($categories) && count($categories) > 0): ?>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <?php foreach ($categories as $category): ?>
                                    <?php if (!empty($category['category'])): ?>
                                        <li>
                                            <a class="dropdown-item"
                                                href="/DoAn_BookStore/?category=<?php echo urlencode(trim($category['category'])); ?>">
                                                <i class="fas fa-tag"></i>
                                                <?php echo htmlspecialchars(trim($category['category'])); ?>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li>
                                    <span class="dropdown-item-text text-muted">
                                        <i class="fas fa-info-circle"></i> Chưa có danh mục
                                    </span>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="/DoAn_BookStore/about">
                            <i class="fas fa-info-circle"></i> Giới thiệu
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="/DoAn_BookStore/contact">
                            <i class="fas fa-envelope"></i> Liên hệ
                        </a>
                    </li>
                </ul>

                <!-- Search Form -->
                <form class="d-flex search-form me-3" method="GET"
                    action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <div class="input-group">
                        <input type="hidden" name="controller" value="books">
                        <input type="hidden" name="action" value="search">
                        <input class="form-control" type="search" name="q" placeholder="Tìm kiếm sách..."
                            value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
                        <button class="btn btn-outline-light" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>

                <!-- User Authentication -->
                <div class="d-flex align-items-center">
                    <?php if ($isLoggedIn): ?>
                        <!-- Shopping Cart (for logged in users) -->
                        <div class="position-relative me-3">
                            <a href="/DoAn_BookStore/cart" class="btn btn-outline-light">
                                <i class="fas fa-shopping-cart"></i>
                                <span class="badge bg-danger cart-badge" id="cart-count" style="display: none;">0</span>
                            </a>
                        </div>

                        <!-- Logged in user dropdown -->
                        <div class="dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center text-light text-decoration-none"
                                href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false"
                                style="cursor: pointer;">
                                <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIiIGhlaWdodD0iMzIiIHZpZXdCb3g9IjAgMCAzMiAzMiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMTYiIGN5PSIxNiIgcj0iMTYiIGZpbGw9IiM2NjdlZWEiLz4KPHBhdGggZD0iTTE2IDEwQzEzLjc5IDEwIDEyIDExLjc5IDEyIDE0QzEyIDE2LjIxIDEzLjc5IDE4IDE2IDE4QzE4LjIxIDE4IDIwIDE2LjIxIDIwIDE0QzIwIDExLjc5IDE4LjIxIDEwIDE2IDEwWk0xNiAyMEM5IDIwIDkgMjIgOSAyMlYyNEgyM1YyMkMyMyAyMiAyMyAyMCAxNiAyMFoiIGZpbGw9IndoaXRlIi8+Cjwvc3ZnPgo="
                                    alt="Avatar" class="user-avatar">
                                <span class="d-none d-md-inline-block">
                                    <?php echo htmlspecialchars($username); ?>
                                    <?php if ($isAdmin): ?>
                                        <span class="user-role-badge">Admin</span>
                                    <?php endif; ?>
                                </span>
                            </a>

                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">

                                <!-- User Profile Options -->
                                <li>
                                    <a class="dropdown-item" href="/DoAn_BookStore/view/profile/profile.php">
                                        <i class="fas fa-user"></i> Hồ sơ cá nhân
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="/DoAn_BookStore/orders">
                                        <i class="fas fa-shopping-bag"></i> Đơn hàng của tôi
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="/DoAn_BookStore/wishlist">
                                        <i class="fas fa-heart"></i> Yêu thích
                                    </a>
                                </li>

                                <!-- Admin Options (only if admin) -->
                                <?php if ($isAdmin): ?>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="/DoAn_BookStore/view/admin/admin.php">
                                            <i class="fas fa-tachometer-alt"></i> Bảng điều khiển
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="/DoAn_BookStore/admin/books">
                                            <i class="fas fa-book"></i> Quản lý sách
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="/DoAn_BookStore/admin/users">
                                            <i class="fas fa-users"></i> Quản lý người dùng
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="/DoAn_BookStore/admin/orders">
                                            <i class="fas fa-shopping-cart"></i> Quản lý đơn hàng
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <!-- Settings and Logout -->
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li>
                                    <a class="dropdown-item" href="/DoAn_BookStore/settings">
                                        <i class="fas fa-cog"></i> Cài đặt
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item text-danger" href="/DoAn_BookStore/view/auth/logout.php"
                                        onclick="return confirm('Bạn có chắc muốn đăng xuất?')">
                                        <i class="fas fa-sign-out-alt"></i> Đăng xuất
                                    </a>
                                </li>
                            </ul>
                        </div>

                    <?php else: ?>
                        <!-- Login/Register buttons -->
                        <a href="/DoAn_BookStore/view/auth/login.php" class="btn btn-outline-light me-2">
                            <i class="fas fa-sign-in-alt"></i> Đăng nhập
                        </a>
                        <a href="/DoAn_BookStore/view/auth/register.php" class="btn btn-light">
                            <i class="fas fa-user-plus"></i> Đăng ký
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show m-0" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success'];
            unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show m-0" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error'];
            unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Bootstrap JS - Load before our custom script -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            console.log('Navigation loaded');
            console.log('User logged in: <?php echo $isLoggedIn ? "true" : "false"; ?>');
            console.log('User is admin: <?php echo $isAdmin ? "true" : "false"; ?>');

            // Check if Bootstrap is loaded
            if (typeof bootstrap === 'undefined') {
                console.error('Bootstrap JavaScript is not loaded');
                return;
            }

            // Initialize all dropdowns manually
            const dropdownElementList = document.querySelectorAll('.dropdown-toggle');
            const dropdownList = [...dropdownElementList].map(dropdownToggleEl => {
                return new bootstrap.Dropdown(dropdownToggleEl, {
                    boundary: 'viewport'
                });
            });

            console.log('Dropdowns initialized:', dropdownList.length);

            // Update cart count if user is logged in
            <?php if ($isLoggedIn): ?>
                updateCartCount();
            <?php endif; ?>

            // Manual dropdown toggle for debugging
            const userDropdown = document.getElementById('userDropdown');
            if (userDropdown) {
                userDropdown.addEventListener('click', function (e) {
                    e.preventDefault();
                    console.log('User dropdown clicked');

                    const dropdownMenu = this.nextElementSibling;
                    const isShown = dropdownMenu.classList.contains('show');

                    // Hide all other dropdowns
                    document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                        menu.classList.remove('show');
                    });

                    // Toggle current dropdown
                    if (!isShown) {
                        dropdownMenu.classList.add('show');
                    }
                });
            }

            // Close dropdown when clicking outside
            document.addEventListener('click', function (e) {
                if (!e.target.closest('.dropdown')) {
                    document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                        menu.classList.remove('show');
                    });
                }
            });

            // Auto-hide alerts after 5 seconds
            setTimeout(function () {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function (alert) {
                    if (alert && bootstrap.Alert) {
                        const bsAlert = new bootstrap.Alert(alert);
                        if (bsAlert) {
                            bsAlert.close();
                        }
                    }
                });
            }, 5000);
        });

        // Function to update cart count
        function updateCartCount() {
            // Fallback to localStorage for now
            try {
                const cartCount = localStorage.getItem('cart_count') || '0';
                const cartBadge = document.getElementById('cart-count');
                if (cartBadge) {
                    cartBadge.textContent = cartCount;
                    cartBadge.style.display = parseInt(cartCount) > 0 ? 'inline' : 'none';
                }
            } catch (error) {
                console.log('Cart count update failed:', error);
            }
        }

        // Function to show notification
        function showNotification(message, type = 'info') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 80px; right: 20px; z-index: 9999; min-width: 300px;';
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation' : 'info'}-circle"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            document.body.appendChild(alertDiv);

            // Auto remove after 3 seconds
            setTimeout(() => {
                if (alertDiv && alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 3000);
        }
    </script>
</body>

</html>