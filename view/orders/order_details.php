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

// Lấy ID đơn hàng từ URL
$orderId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($orderId <= 0) {
    header('Location: order.php');
    exit();
}

try {
    // Lấy thông tin đơn hàng
    $order = $db->getOrderById($orderId);

    if (!$order) {
        $_SESSION['error'] = 'Đơn hàng không tồn tại';
        header('Location: order.php');
        exit();
    }

    // Kiểm tra quyền sở hữu đơn hàng
    if ($order['user_id'] != $userId) {
        $_SESSION['error'] = 'Bạn không có quyền xem đơn hàng này';
        header('Location: order.php');
        exit();
    }

    // Lấy chi tiết đơn hàng với danh sách sách
    $orderDetails = $db->getOrderWithBooks($orderId);

} catch (Exception $e) {
    error_log('Order details error: ' . $e->getMessage());
    $_SESSION['error'] = 'Có lỗi xảy ra khi tải thông tin đơn hàng';
    header('Location: order.php');
    exit();
}

// Định nghĩa trạng thái
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
$statusColor = [
    'pending' => 'warning',
    'confirmed' => 'success',
    'cancelled' => 'danger'
];
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi Tiết Đơn Hàng #<?php echo $order['order_id']; ?> - BookStore</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="order.css">

    <style>
        .order-detail-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        .order-info-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .status-badge {
            font-size: 1.1rem;
            padding: 0.5rem 1rem;
            border-radius: 25px;
        }

        .book-item {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .book-item:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .book-image {
            width: 80px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
        }

        .summary-card {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 1.5rem;
            border-radius: 0 10px 10px 0;
        }

        .action-buttons .btn {
            margin: 0.25rem;
            min-width: 140px;
        }
    </style>
</head>

<body>
    <?php include '../navigation/navigation.php'; ?>

    <!-- Order Detail Header -->
    <div class="order-detail-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1>
                        <i class="fas fa-receipt"></i>
                        Chi Tiết Đơn Hàng #<?php echo $order['order_id']; ?>
                    </h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb text-white-50">
                            <li class="breadcrumb-item">
                                <a href="/DoAn_BookStore/" class="text-white"><i class="fas fa-home"></i> Trang chủ</a>
                            </li>
                            <li class="breadcrumb-item">
                                <a href="order.php" class="text-white">Đơn hàng</a>
                            </li>
                            <li class="breadcrumb-item active text-white">Chi tiết #<?php echo $order['order_id']; ?>
                            </li>
                        </ol>
                    </nav>
                </div>
                <div class="col-md-4 text-end">
                    <span class="status-badge bg-<?php echo $statusColor[$order['status']]; ?>">
                        <i class="<?php echo $statusIcon[$order['status']]; ?>"></i>
                        <?php echo $statusText[$order['status']]; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="container my-5">
        <!-- Order Information -->
        <div class="order-info-card">
            <h4 class="mb-4">
                <i class="fas fa-info-circle text-primary"></i>
                Thông Tin Đơn Hàng
            </h4>

            <div class="row">
                <div class="col-md-6">
                    <div class="info-group mb-3">
                        <label class="fw-bold text-muted">Mã đơn hàng:</label>
                        <p class="mb-0">#<?php echo $order['order_id']; ?></p>
                    </div>

                    <div class="info-group mb-3">
                        <label class="fw-bold text-muted">Ngày đặt hàng:</label>
                        <p class="mb-0">
                            <i class="fas fa-calendar text-primary"></i>
                            <?php echo date('d/m/Y H:i:s', strtotime($order['created_at'])); ?>
                        </p>
                    </div>

                    <div class="info-group mb-3">
                        <label class="fw-bold text-muted">Phương thức thanh toán:</label>
                        <p class="mb-0">
                            <i class="fas fa-credit-card text-primary"></i>
                            <?php echo htmlspecialchars($order['pay_method']); ?>
                        </p>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="info-group mb-3">
                        <label class="fw-bold text-muted">Trạng thái:</label>
                        <p class="mb-0">
                            <span class="badge bg-<?php echo $statusColor[$order['status']]; ?> fs-6">
                                <i class="<?php echo $statusIcon[$order['status']]; ?>"></i>
                                <?php echo $statusText[$order['status']]; ?>
                            </span>
                        </p>
                    </div>

                    <div class="info-group mb-3">
                        <label class="fw-bold text-muted">Tổng tiền:</label>
                        <p class="mb-0 text-danger fw-bold fs-5">
                            <i class="fas fa-money-bill-wave"></i>
                            <?php echo number_format($order['cost'] * 1000, 0, ',', '.'); ?> VNĐ
                        </p>
                    </div>

                    <div class="info-group mb-3">
                        <label class="fw-bold text-muted">Số lượng sản phẩm:</label>
                        <p class="mb-0">
                            <i class="fas fa-box text-primary"></i>
                            <?php echo count($orderDetails['items']); ?> sản phẩm
                        </p>
                    </div>
                </div>
            </div>

            <?php if (!empty($order['note'])): ?>
                <div class="info-group mt-4">
                    <label class="fw-bold text-muted">Ghi chú:</label>
                    <div class="alert alert-light mt-2">
                        <i class="fas fa-sticky-note text-warning"></i>
                        <?php echo nl2br(htmlspecialchars($order['note'])); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Order Items -->
        <div class="order-info-card">
            <h4 class="mb-4">
                <i class="fas fa-list text-primary"></i>
                Danh Sách Sản Phẩm
            </h4>

            <?php if (!empty($orderDetails['items'])): ?>
                <?php foreach ($orderDetails['items'] as $item): ?>
                    <div class="book-item">
                        <div class="row align-items-center">
                            <div class="col-md-2 col-sm-3">
                                <div class="book-image-container text-center">
                                    <?php if (!empty($item['book_image'])): ?>
                                        <img src="../../images/books/<?php echo htmlspecialchars($item['book_image']); ?>"
                                            alt="<?php echo htmlspecialchars($item['book_title']); ?>" class="book-image">
                                    <?php else: ?>
                                        <div class="book-image bg-light d-flex align-items-center justify-content-center">
                                            <i class="fas fa-book text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="col-md-6 col-sm-9">
                                <div class="book-info">
                                    <h5 class="book-title mb-2">
                                        <?php echo htmlspecialchars($item['book_title']); ?>
                                        <?php if (isset($item['is_deleted']) && $item['is_deleted']): ?>
                                            <span class="badge bg-secondary ms-2">Không còn bán</span>
                                        <?php endif; ?>
                                    </h5>

                                    <p class="book-author text-muted mb-2">
                                        <i class="fas fa-user"></i>
                                        Tác giả: <?php echo htmlspecialchars($item['book_author']); ?>
                                    </p>

                                    <?php if (!empty($item['book_category'])): ?>
                                        <p class="book-category text-muted mb-2">
                                            <i class="fas fa-tag"></i>
                                            Thể loại: <?php echo htmlspecialchars($item['book_category']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="item-pricing text-end">
                                    <div class="unit-price mb-2">
                                        <span class="text-muted">Đơn giá:</span>
                                        <span class="fw-bold">
                                            <?php echo number_format(($item['total_price'] / $item['quantity']) * 1000, 0, ',', '.'); ?>
                                            VNĐ
                                        </span>
                                    </div>

                                    <div class="quantity mb-2">
                                        <span class="text-muted">Số lượng:</span>
                                        <span class="fw-bold"><?php echo $item['quantity']; ?></span>
                                    </div>

                                    <div class="total-price">
                                        <span class="text-muted">Thành tiền:</span>
                                        <span class="fw-bold text-danger fs-5">
                                            <?php echo number_format($item['total_price'] * 1000, 0, ',', '.'); ?> VNĐ
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Order Summary -->
                <div class="summary-card mt-4">
                    <div class="row">
                        <div class="col-md-8">
                            <h5 class="mb-3">
                                <i class="fas fa-calculator text-primary"></i>
                                Tóm Tắt Đơn Hàng
                            </h5>
                            <div class="summary-details">
                                <p class="mb-2">
                                    <span class="text-muted">Tổng số sản phẩm:</span>
                                    <span class="fw-bold"><?php echo count($orderDetails['items']); ?> loại</span>
                                </p>
                                <p class="mb-2">
                                    <span class="text-muted">Tổng số lượng:</span>
                                    <span class="fw-bold">
                                        <?php
                                        $totalQuantity = array_sum(array_column($orderDetails['items'], 'quantity'));
                                        echo $totalQuantity;
                                        ?> cuốn
                                    </span>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <h5 class="text-muted mb-2">Tổng cộng:</h5>
                            <h3 class="text-danger fw-bold">
                                <?php echo number_format($order['cost'] * 1000, 0, ',', '.'); ?> VNĐ
                            </h3>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-box-open text-muted" style="font-size: 4rem;"></i>
                    <h5 class="text-muted mt-3">Không có sản phẩm nào trong đơn hàng</h5>
                </div>
            <?php endif; ?>
        </div>

        <!-- Action Buttons -->
        <div class="text-center action-buttons">
            <a href="order.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left"></i> Quay lại danh sách
            </a>

            <?php if ($order['status'] === 'pending'): ?>
                <button class="btn btn-danger" onclick="cancelOrder(<?php echo $order['order_id']; ?>)">
                    <i class="fas fa-times"></i> Hủy đơn hàng
                </button>
            <?php endif; ?>

            <button class="btn btn-info" onclick="window.print()">
                <i class="fas fa-print"></i> In đơn hàng
            </button>

            <a href="/DoAn_BookStore/" class="btn btn-success">
                <i class="fas fa-shopping-cart"></i> Tiếp tục mua sắm
            </a>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Cancel order function
        async function cancelOrder(orderId) {
            if (!confirm('Bạn có chắc chắn muốn hủy đơn hàng này không?')) {
                return;
            }

            try {
                const response = await fetch('order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=cancel_order&order_id=${orderId}`
                });

                const data = await response.json();

                if (data.success) {
                    alert('Đã hủy đơn hàng thành công!');
                    location.reload();
                } else {
                    alert('Lỗi: ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Có lỗi xảy ra khi hủy đơn hàng');
            }
        }

        // Print styles for better printing
        window.addEventListener('beforeprint', function () {
            document.body.classList.add('printing');
        });

        window.addEventListener('afterprint', function () {
            document.body.classList.remove('printing');
        });
    </script>

    <style media="print">
        .printing .action-buttons,
        .printing nav,
        .printing .order-detail-header {
            display: none !important;
        }

        .printing .container {
            width: 100% !important;
            max-width: none !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .printing .order-info-card {
            box-shadow: none !important;
            border: 1px solid #ddd !important;
            margin-bottom: 1rem !important;
        }

        .printing .book-item {
            border: 1px solid #ddd !important;
            box-shadow: none !important;
            break-inside: avoid;
        }
    </style>
</body>

</html>