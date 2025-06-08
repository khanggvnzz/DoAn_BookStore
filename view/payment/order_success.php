<?php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /DoAn_BookStore/view/auth/login.php');
    exit();
}

// Check if order_id is provided
if (!isset($_GET['order_id'])) {
    header('Location: /DoAn_BookStore/');
    exit();
}

require_once __DIR__ . '/../../model/Database.php';

$database = new Database();
$userId = $_SESSION['user_id'];
$orderId = $_GET['order_id'];

// Verify order belongs to user
if (!$database->isOrderOwnedByUser($orderId, $userId)) {
    header('Location: /DoAn_BookStore/');
    exit();
}

// Get order details with book information
$order = $database->getOrderWithBooks($orderId);

if (!$order) {
    header('Location: /DoAn_BookStore/');
    exit();
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt hàng thành công - BookStore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .success-icon {
            font-size: 4rem;
            color: #28a745;
        }

        .order-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }

        .book-item {
            border-bottom: 1px solid #eee;
            padding: 10px 0;
        }

        .book-item:last-child {
            border-bottom: none;
        }
    </style>
</head>

<body>
    <?php include_once __DIR__ . '/../navigation/navigation.php'; ?>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="text-center mb-4">
                    <i class="fas fa-check-circle success-icon"></i>
                    <h2 class="mt-3">Đặt hàng thành công!</h2>
                    <p class="text-muted">Cảm ơn bạn đã mua hàng tại BookStore</p>
                </div>

                <div class="order-summary">
                    <h4 class="mb-3">
                        <i class="fas fa-receipt"></i>
                        Thông tin đơn hàng #<?php echo $orderId; ?>
                    </h4>

                    <div class="row mb-3">
                        <div class="col-sm-6">
                            <strong>Ngày đặt:</strong><br>
                            <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
                        </div>
                        <div class="col-sm-6">
                            <strong>Phương thức thanh toán:</strong><br>
                            <?php
                            switch ($order['pay_method']) {
                                case 'cod':
                                    echo '<span class="badge bg-warning">Thanh toán khi nhận hàng</span>';
                                    break;
                                case 'bank_transfer':
                                    echo '<span class="badge bg-primary">Chuyển khoản ngân hàng</span>';
                                    break;
                                case 'momo':
                                    echo '<span class="badge bg-danger">Ví MoMo</span>';
                                    break;
                                default:
                                    echo $order['pay_method'];
                            }
                            ?>
                        </div>
                    </div>

                    <?php if ($order['note']): ?>
                        <div class="mb-3">
                            <strong>Ghi chú:</strong><br>
                            <?php echo htmlspecialchars($order['note']); ?>
                        </div>
                    <?php endif; ?>

                    <h5 class="mb-3">Sản phẩm đã đặt:</h5>

                    <?php foreach ($order['items'] as $item): ?>
                        <div class="book-item">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($item['book_title']); ?></h6>
                                    <small class="text-muted">
                                        Tác giả: <?php echo htmlspecialchars($item['book_author']); ?>
                                    </small><br>
                                    <small class="text-muted">
                                        Số lượng: <?php echo $item['quantity']; ?> x
                                        <?php echo number_format($item['book_price'] * 1000, 0, ',', '.'); ?> VNĐ
                                    </small>
                                </div>
                                <div class="col-md-4 text-end">
                                    <strong>
                                        <?php echo number_format($item['total_price'] * 1000, 0, ',', '.'); ?> VNĐ
                                    </strong>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <hr>
                    <div class="row">
                        <div class="col-md-8">
                            <strong>Tổng cộng:</strong>
                        </div>
                        <div class="col-md-4 text-end">
                            <h4 class="text-primary">
                                <?php echo number_format($order['cost'] * 1000, 0, ',', '.'); ?> VNĐ
                            </h4>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <a href="/DoAn_BookStore/" class="btn btn-primary me-3">
                        <i class="fas fa-home"></i> Về trang chủ
                    </a>
                    <a href="/DoAn_BookStore/view/user/orders.php" class="btn btn-outline-secondary">
                        <i class="fas fa-list"></i> Xem đơn hàng của tôi
                    </a>
                </div>

                <?php if ($order['pay_method'] !== 'cod'): ?>
                    <div class="alert alert-info mt-4">
                        <i class="fas fa-info-circle"></i>
                        <strong>Lưu ý:</strong>
                        <?php if ($order['pay_method'] === 'bank_transfer'): ?>
                            Vui lòng chuyển khoản theo thông tin đã cung cấp để hoàn tất đơn hàng.
                        <?php elseif ($order['pay_method'] === 'momo'): ?>
                            Vui lòng thanh toán qua MoMo theo thông tin đã cung cấp để hoàn tất đơn hàng.
                        <?php endif; ?>
                        Đơn hàng sẽ được xử lý sau khi chúng tôi xác nhận thanh toán.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>