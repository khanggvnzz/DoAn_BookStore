<?php

session_start();
require_once '../../model/Database.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header('Location: /DoAn_BookStore/view/auth/login.php');
    exit();
}

// Khởi tạo database
$db = new Database();
$userId = $_SESSION['user_id'];

// Lấy thông tin người dùng
$user = $db->getUserById($userId);
if (!$user) {
    header('Location: /DoAn_BookStore/view/auth/login.php');
    exit();
}

// Xử lý các tham số từ URL
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$perPage = 10;

// Lấy danh sách đơn hàng
try {
    if (!empty($status)) {
        // Lấy đơn hàng theo trạng thái
        $ordersData = $db->getOrdersByStatus($status, $page, $perPage);
        $orders = array_filter($ordersData['orders'], function ($order) use ($userId) {
            return $order['user_id'] == $userId;
        });
    } else {
        // Lấy tất cả đơn hàng của user
        $ordersData = $db->getOrdersByUserId($userId, $page, $perPage);
        $orders = $ordersData['orders'];
    }

    $pagination = $ordersData['pagination'];
} catch (Exception $e) {
    error_log('Order page error: ' . $e->getMessage());
    $orders = [];
    $pagination = [
        'current_page' => 1,
        'per_page' => $perPage,
        'total_records' => 0,
        'total_pages' => 0,
        'has_previous' => false,
        'has_next' => false
    ];
}

// Xử lý AJAX request cho hủy đơn hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    try {
        if ($_POST['action'] === 'cancel_order') {
            $orderId = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

            if ($orderId <= 0) {
                throw new Exception('ID đơn hàng không hợp lệ');
            }

            // Kiểm tra đơn hàng có thuộc về user không
            if (!$db->isOrderOwnedByUser($orderId, $userId)) {
                throw new Exception('Bạn không có quyền hủy đơn hàng này');
            }

            // Lấy thông tin đơn hàng
            $order = $db->getOrderById($orderId);
            if (!$order) {
                throw new Exception('Đơn hàng không tồn tại');
            }

            // Chỉ cho phép hủy đơn hàng đang chờ xử lý
            if ($order['status'] !== 'pending') {
                throw new Exception('Chỉ có thể hủy đơn hàng đang chờ xử lý');
            }

            // Cập nhật trạng thái đơn hàng
            $result = $db->updateOrderStatus($orderId, 'cancelled');

            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Đã hủy đơn hàng thành công'
                ]);
            } else {
                throw new Exception('Có lỗi xảy ra khi hủy đơn hàng');
            }
        } else {
            throw new Exception('Hành động không hợp lệ');
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit();
}

// Lấy thống kê đơn hàng
try {
    $orderStats = $db->getUserOrderStats($userId);
} catch (Exception $e) {
    error_log('Order stats error: ' . $e->getMessage());
    $orderStats = [
        'pending' => 0,
        'confirmed' => 0,
        'cancelled' => 0,
        'total' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn Hàng Của Tôi - BookStore</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="order.css">
</head>

<body>
    <?php include '../navigation/navigation.php'; ?>

    <div class="container my-5">
        <!-- Page Header -->
        <div class="page-header mb-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="page-title">
                        <i class="fas fa-shopping-bag"></i> Đơn Hàng Của Tôi
                    </h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="/DoAn_BookStore/"><i class="fas fa-home"></i> Trang chủ</a>
                            </li>
                            <li class="breadcrumb-item">
                                <a href="/DoAn_BookStore/view/profile/profile.php">Tài khoản</a>
                            </li>
                            <li class="breadcrumb-item active">Đơn hàng</li>
                        </ol>
                    </nav>
                </div>
                <div class="col-md-4 text-end">
                    <a href="/DoAn_BookStore/" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Tiếp Tục Mua Sắm
                    </a>
                </div>
            </div>
        </div>

        <!-- Order Statistics -->
        <div class="order-stats mb-4">
            <div class="row">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card total">
                        <div class="stat-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $orderStats['total']; ?></h3>
                            <p>Tổng đơn hàng</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card pending">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $orderStats['pending']; ?></h3>
                            <p>Chờ xử lý</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card confirmed">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $orderStats['confirmed']; ?></h3>
                            <p>Đã xác nhận</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card cancelled">
                        <div class="stat-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $orderStats['cancelled']; ?></h3>
                            <p>Đã hủy</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="order-filters mb-4">
            <ul class="nav nav-pills filter-pills">
                <li class="nav-item">
                    <a class="nav-link <?php echo empty($status) ? 'active' : ''; ?>" href="?page=1">
                        <i class="fas fa-list"></i> Tất cả
                        <span class="badge"><?php echo $orderStats['total']; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $status === 'pending' ? 'active' : ''; ?>"
                        href="?status=pending&page=1">
                        <i class="fas fa-clock"></i> Chờ xử lý
                        <span class="badge"><?php echo $orderStats['pending']; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $status === 'confirmed' ? 'active' : ''; ?>"
                        href="?status=confirmed&page=1">
                        <i class="fas fa-check-circle"></i> Đã xác nhận
                        <span class="badge"><?php echo $orderStats['confirmed']; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $status === 'cancelled' ? 'active' : ''; ?>"
                        href="?status=cancelled&page=1">
                        <i class="fas fa-times-circle"></i> Đã hủy
                        <span class="badge"><?php echo $orderStats['cancelled']; ?></span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Orders List -->
        <div class="orders-container">
            <?php if (!empty($orders)): ?>
                <?php foreach ($orders as $order): ?>
                    <?php
                    $orderDetails = $db->getOrderWithBooks($order['order_id']);
                    $statusClass = strtolower($order['status']);
                    $statusIcon = [
                        'pending' => 'fas fa-clock',
                        'confirmed' => 'fas fa-check-circle',
                        'cancelled' => 'fas fa-times-circle'
                    ];
                    $statusText = [
                        'pending' => 'Chờ xử lý',
                        'confirmed' => 'Đã xác nhận',
                        'cancelled' => 'Đã hủy'
                    ];
                    ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <div class="order-info">
                                        <h5 class="order-id">
                                            <i class="fas fa-receipt"></i>
                                            Đơn hàng #<?php echo $order['order_id']; ?>
                                        </h5>
                                        <div class="order-meta">
                                            <span class="order-date">
                                                <i class="fas fa-calendar"></i>
                                                <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
                                            </span>
                                            <span class="order-payment">
                                                <i class="fas fa-credit-card"></i>
                                                <?php echo htmlspecialchars($order['pay_method']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 text-end">
                                    <div class="order-status-container">
                                        <span class="order-status status-<?php echo $statusClass; ?>">
                                            <i class="<?php echo $statusIcon[$order['status']]; ?>"></i>
                                            <?php echo $statusText[$order['status']]; ?>
                                        </span>
                                        <div class="order-total">
                                            <?php echo number_format($order['cost'] * 1000, 0, ',', '.'); ?> VNĐ
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="order-body">
                            <?php if (!empty($orderDetails['items'])): ?>
                                <div class="order-items">
                                    <?php
                                    $displayItems = array_slice($orderDetails['items'], 0, 3);
                                    $remainingCount = count($orderDetails['items']) - 3;
                                    ?>

                                    <?php foreach ($displayItems as $item): ?>
                                        <div class="order-item">
                                            <div class="item-image">
                                                <?php if (!empty($item['book_image'])): ?>
                                                    <img src="../../images/books/<?php echo htmlspecialchars($item['book_image']); ?>"
                                                        alt="<?php echo htmlspecialchars($item['book_title']); ?>">
                                                <?php else: ?>
                                                    <div class="no-image">
                                                        <i class="fas fa-book"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="item-details">
                                                <h6 class="item-title">
                                                    <?php echo htmlspecialchars($item['book_title']); ?>
                                                    <?php if (isset($item['is_deleted']) && $item['is_deleted']): ?>
                                                        <span class="badge bg-secondary">Không còn bán</span>
                                                    <?php endif; ?>
                                                </h6>
                                                <p class="item-author">
                                                    <i class="fas fa-user"></i>
                                                    <?php echo htmlspecialchars($item['book_author']); ?>
                                                </p>
                                                <div class="item-quantity-price">
                                                    <span class="quantity">Số lượng: <?php echo $item['quantity']; ?></span>
                                                    <span class="price">
                                                        <?php echo number_format($item['total_price'] * 1000, 0, ',', '.'); ?> VNĐ
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>

                                    <?php if ($remainingCount > 0): ?>
                                        <div class="more-items">
                                            <p class="text-muted">
                                                <i class="fas fa-ellipsis-h"></i>
                                                Và <?php echo $remainingCount; ?> sản phẩm khác
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="no-items">
                                    <p class="text-muted">Không có thông tin sản phẩm</p>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($order['note'])): ?>
                                <div class="order-note">
                                    <h6><i class="fas fa-sticky-note"></i> Ghi chú:</h6>
                                    <p><?php echo nl2br(htmlspecialchars($order['note'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="order-footer">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <div class="order-summary">
                                        <span class="items-count">
                                            <i class="fas fa-box"></i>
                                            <?php echo count($orderDetails['items']); ?> sản phẩm
                                        </span>
                                        <?php if (isset($orderDetails['total_books'])): ?>
                                            <span class="books-count">
                                                (Tổng: <?php echo $orderDetails['total_books']; ?> cuốn)
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6 text-end">
                                    <div class="order-actions">
                                        <button class="btn btn-outline-primary btn-sm"
                                            onclick="window.location.href='../orders/order_details.php?id=<?php echo $order['order_id']; ?>'">
                                            <i class="fas fa-eye"></i> Xem chi tiết
                                        </button>

                                        <?php if ($order['status'] === 'pending'): ?>
                                            <button class="btn btn-outline-danger btn-sm"
                                                onclick="cancelOrder(<?php echo $order['order_id']; ?>)">
                                                <i class="fas fa-times"></i> Hủy đơn
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($order['status'] === 'confirmed'): ?>
                                            <button class="btn btn-success btn-sm" disabled>
                                                <i class="fas fa-check"></i> Đã xác nhận
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="pagination-container">
                        <nav aria-label="Phân trang đơn hàng">
                            <ul class="pagination justify-content-center">
                                <?php if ($pagination['has_previous']): ?>
                                    <li class="page-item">
                                        <a class="page-link"
                                            href="?<?php echo http_build_query(array_merge($_GET, ['page' => $pagination['previous_page']])); ?>">
                                            <i class="fas fa-chevron-left"></i> Trước
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link"><i class="fas fa-chevron-left"></i> Trước</span>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                                    <li class="page-item <?php echo $i == $pagination['current_page'] ? 'active' : ''; ?>">
                                        <a class="page-link"
                                            href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($pagination['has_next']): ?>
                                    <li class="page-item">
                                        <a class="page-link"
                                            href="?<?php echo http_build_query(array_merge($_GET, ['page' => $pagination['next_page']])); ?>">
                                            Sau <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">Sau <i class="fas fa-chevron-right"></i></span>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>

                        <div class="text-center mt-3">
                            <small class="text-muted">
                                Hiển thị trang <?php echo $pagination['current_page']; ?>
                                trong tổng số <?php echo $pagination['total_pages']; ?> trang
                                (<?php echo $pagination['total_records']; ?> đơn hàng)
                            </small>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- Empty State -->
                <div class="empty-orders">
                    <div class="empty-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h3>
                        <?php if (!empty($status)): ?>
                            Không có đơn hàng nào với trạng thái "<?php echo $statusText[$status]; ?>"
                        <?php else: ?>
                            Bạn chưa có đơn hàng nào
                        <?php endif; ?>
                    </h3>
                    <p class="text-muted">
                        <?php if (!empty($status)): ?>
                            Hãy thử xem tất cả đơn hàng hoặc lọc theo trạng thái khác.
                        <?php else: ?>
                            Hãy khám phá và mua sắm những cuốn sách hay tại BookStore!
                        <?php endif; ?>
                    </p>
                    <div class="empty-actions">
                        <?php if (!empty($status)): ?>
                            <a href="?" class="btn btn-outline-primary">
                                <i class="fas fa-list"></i> Xem tất cả đơn hàng
                            </a>
                        <?php endif; ?>
                        <a href="/DoAn_BookStore/" class="btn btn-primary">
                            <i class="fas fa-book"></i> Khám phá sách
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div class="modal fade" id="orderDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-receipt"></i> Chi tiết đơn hàng
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="orderDetailsContent">
                    <!-- Content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="successToast" class="toast" role="alert">
            <div class="toast-header">
                <i class="fas fa-check-circle text-success me-2"></i>
                <strong class="me-auto">Thành công</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body"></div>
        </div>

        <div id="errorToast" class="toast" role="alert">
            <div class="toast-header">
                <i class="fas fa-exclamation-circle text-danger me-2"></i>
                <strong class="me-auto">Lỗi</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body"></div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // View order details
        async function viewOrderDetails(orderId) {
            try {
                const response = await fetch('../admin/order_details.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=get_order_details&order_id=${orderId}`
                });

                const data = await response.json();

                if (data.success) {
                    document.getElementById('orderDetailsContent').innerHTML = data.html;
                    const modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
                    modal.show();
                } else {
                    showToast('error', data.message || 'Không thể tải chi tiết đơn hàng');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('error', 'Có lỗi xảy ra khi tải chi tiết đơn hàng');
            }
        }

        // Cancel order
        async function cancelOrder(orderId) {
            if (!confirm('Bạn có chắc chắn muốn hủy đơn hàng này?')) {
                return;
            }

            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=cancel_order&order_id=${orderId}`
                });

                const data = await response.json();

                if (data.success) {
                    showToast('success', data.message);
                    // Reload page after 1.5 seconds
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showToast('error', data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('error', 'Có lỗi xảy ra khi hủy đơn hàng');
            }
        }

        // Show toast notification
        function showToast(type, message) {
            const toastId = type === 'success' ? 'successToast' : 'errorToast';
            const toast = document.getElementById(toastId);
            const toastBody = toast.querySelector('.toast-body');

            toastBody.textContent = message;

            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
        }

        // Auto refresh order status every 30 seconds
        setInterval(() => {
            // Only refresh if on pending orders tab
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('status') === 'pending' || !urlParams.get('status')) {
                // Subtle refresh without full page reload for better UX
                // You can implement this with AJAX if needed
            }
        }, 30000);
    </script>
</body>

</html>