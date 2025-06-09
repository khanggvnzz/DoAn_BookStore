<?php

session_start();

// Check if user is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: /DoAn_BookStore/view/auth/login.php');
    exit();
}

require_once __DIR__ . '/../../model/Database.php';

$database = new Database();
$orderId = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if (!$orderId) {
    header('Location: /DoAn_BookStore/view/admin/admin.php');
    exit();
}

// Get order details
$order = $database->getOrderById($orderId);
if (!$order) {
    $_SESSION['error'] = "Đơn hàng không tồn tại";
    header('Location: /DoAn_BookStore/view/admin/admin.php');
    exit();
}

// Get customer information
$customer = $database->getUserById($order['user_id']);

// Get voucher information if used
$voucher = null;
if ($order['voucher_id']) {
    $voucher = $database->getVoucherById($order['voucher_id']);
}

// Parse product string to get book details
$productItems = $database->parseProductString($order['product']);
$books = [];
$totalItems = 0;
$totalAmount = 0;

foreach ($productItems as $item) {
    $book = $database->fetch("SELECT * FROM books WHERE id = :id", ['id' => $item['book_id']]);
    if ($book) {
        $book['quantity'] = $item['quantity'];
        $book['subtotal'] = $book['price'] * $item['quantity'];
        $totalItems += $item['quantity'];
        $totalAmount += $book['subtotal'];
        $books[] = $book;
    }
}

// Handle status update
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'update_status':
            $newStatus = $_POST['status'];
            $validStatuses = ['pending', 'confirmed', 'cancelled'];

            if (in_array($newStatus, $validStatuses)) {
                $result = $database->updateOrderStatus($orderId, $newStatus);
                if ($result) {
                    $message = "Cập nhật trạng thái đơn hàng thành công!";
                    $order['status'] = $newStatus; // Update local data
                } else {
                    $error = "Lỗi khi cập nhật trạng thái đơn hàng";
                }
            } else {
                $error = "Trạng thái không hợp lệ";
            }
            break;
    }
}

// Get status info
function getStatusInfo($status)
{
    switch ($status) {
        case 'pending':
            return ['text' => 'Chờ duyệt', 'class' => 'warning', 'icon' => 'clock'];
        case 'confirmed':
            return ['text' => 'Đã duyệt', 'class' => 'success', 'icon' => 'check-circle'];
        case 'cancelled':
            return ['text' => 'Đã hủy', 'class' => 'danger', 'icon' => 'times-circle'];
        default:
            return ['text' => ucfirst($status), 'class' => 'secondary', 'icon' => 'info-circle'];
    }
}

$statusInfo = getStatusInfo($order['status']);
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết đơn hàng #<?php echo $order['order_id']; ?> - BookStore Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .order-detail-card {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: none;
            border-radius: 10px;
        }

        .status-badge {
            font-size: 1.1em;
            padding: 0.5rem 1rem;
        }

        .book-item {
            border-bottom: 1px solid #eee;
            padding: 1rem 0;
        }

        .book-item:last-child {
            border-bottom: none;
        }

        .book-image {
            width: 80px;
            height: 100px;
            object-fit: cover;
            border-radius: 5px;
        }

        .info-row {
            margin-bottom: 0.8rem;
        }

        .info-label {
            font-weight: 600;
            color: #495057;
        }

        .back-btn {
            position: sticky;
            top: 20px;
            z-index: 100;
        }
    </style>
</head>

<body class="bg-light">
    <div class="container my-4">
        <!-- Back Button -->
        <div class="back-btn mb-4">
            <a href="/DoAn_BookStore/view/admin/admin.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Quay lại quản trị
            </a>
        </div>

        <!-- Alert Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Order Header -->
        <div class="card order-detail-card mb-4">
            <div class="card-header bg-primary text-white">
                <div class="row align-items-center">
                    <div class="col">
                        <h3 class="mb-0">
                            <i class="fas fa-receipt"></i>
                            Chi tiết đơn hàng #<?php echo $order['order_id']; ?>
                        </h3>
                    </div>
                    <div class="col-auto">
                        <span class="badge bg-<?php echo $statusInfo['class']; ?> status-badge">
                            <i class="fas fa-<?php echo $statusInfo['icon']; ?>"></i>
                            <?php echo $statusInfo['text']; ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5><i class="fas fa-info-circle text-primary"></i> Thông tin đơn hàng</h5>
                        <div class="info-row">
                            <span class="info-label">Mã đơn hàng:</span>
                            <code class="ms-2">#<?php echo $order['order_id']; ?></code>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Ngày đặt:</span>
                            <span
                                class="ms-2"><?php echo date('d/m/Y H:i:s', strtotime($order['created_at'])); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Phương thức thanh toán:</span>
                            <span class="badge bg-info ms-2"><?php echo ucfirst($order['pay_method']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Tổng tiền:</span>
                            <strong class="ms-2 text-success"><?php echo number_format($order['cost'], 2); ?>
                                VNĐ</strong>
                        </div>
                        <?php if (!empty($order['note'])): ?>
                            <div class="info-row">
                                <span class="info-label">Ghi chú:</span>
                                <p class="ms-2 mb-0 text-muted"><?php echo htmlspecialchars($order['note']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <h5><i class="fas fa-user text-primary"></i> Thông tin khách hàng</h5>
                        <?php if ($customer): ?>
                            <div class="info-row">
                                <span class="info-label">ID:</span>
                                <span class="ms-2"><?php echo $customer->user_id; ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Tên đăng nhập:</span>
                                <span class="ms-2"><?php echo htmlspecialchars($customer->username); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Họ tên:</span>
                                <span class="ms-2"><?php echo htmlspecialchars($customer->name); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Email:</span>
                                <span class="ms-2"><?php echo htmlspecialchars($customer->email); ?></span>
                            </div>
                        <?php else: ?>
                            <div class="text-muted">Không tìm thấy thông tin khách hàng</div>
                        <?php endif; ?>

                        <?php if ($voucher): ?>
                            <h5 class="mt-4"><i class="fas fa-ticket-alt text-primary"></i> Voucher sử dụng</h5>
                            <div class="info-row">
                                <span class="info-label">Mã voucher:</span>
                                <span class="badge bg-success ms-2"><?php echo htmlspecialchars($voucher['code']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Tên:</span>
                                <span class="ms-2"><?php echo htmlspecialchars($voucher['name']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Giảm giá:</span>
                                <span class="ms-2 text-success"><?php echo $voucher['discount_percent']; ?>%</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products List -->
        <div class="card order-detail-card mb-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-book"></i>
                    Danh sách sản phẩm (<?php echo count($books); ?> loại, <?php echo $totalItems; ?> cuốn)
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($books)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                        <p class="text-muted">Không thể tải thông tin sản phẩm</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($books as $book): ?>
                        <div class="book-item">
                            <div class="row align-items-center">
                                <div class="col-md-2">
                                    <?php if (!empty($book['image'])): ?>
                                        <img src="/DoAn_BookStore/images/books/<?php echo htmlspecialchars($book['image']); ?>"
                                            alt="<?php echo htmlspecialchars($book['title']); ?>" class="book-image">
                                    <?php else: ?>
                                        <div class="book-image bg-light d-flex align-items-center justify-content-center">
                                            <i class="fas fa-book fa-2x text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($book['title']); ?></h6>
                                    <p class="text-muted mb-1">
                                        <small>
                                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($book['author']); ?>
                                            <span class="ms-2">
                                                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($book['category']); ?>
                                            </span>
                                        </small>
                                    </p>
                                    <p class="text-muted mb-0">
                                        <small>ID: <?php echo $book['id']; ?></small>
                                    </p>
                                </div>
                                <div class="col-md-2 text-center">
                                    <div class="info-label">Đơn giá</div>
                                    <div class="text-primary fw-bold">
                                        <?php echo number_format($book['price'], 2); ?> VNĐ
                                    </div>
                                </div>
                                <div class="col-md-1 text-center">
                                    <div class="info-label">Số lượng</div>
                                    <div class="badge bg-primary fs-6">
                                        <?php echo $book['quantity']; ?>
                                    </div>
                                </div>
                                <div class="col-md-1 text-end">
                                    <div class="info-label">Thành tiền</div>
                                    <div class="text-success fw-bold">
                                        <?php echo number_format($book['subtotal'], 2); ?> VNĐ
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Order Summary -->
                    <div class="border-top pt-3 mt-3">
                        <div class="row">
                            <div class="col-md-8"></div>
                            <div class="col-md-4">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Tạm tính:</span>
                                    <span><?php echo number_format($totalAmount, 2); ?> VNĐ</span>
                                </div>
                                <?php if ($voucher): ?>
                                    <div class="d-flex justify-content-between mb-2 text-success">
                                        <span>Giảm giá (<?php echo $voucher['discount_percent']; ?>%):</span>
                                        <span>-<?php echo number_format($totalAmount * $voucher['discount_percent'] / 100, 2); ?>
                                            VNĐ</span>
                                    </div>
                                <?php endif; ?>
                                <div class="d-flex justify-content-between border-top pt-2">
                                    <strong>Tổng cộng:</strong>
                                    <strong class="text-success"><?php echo number_format($order['cost'], 2); ?>
                                        VNĐ</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Order Actions -->
        <?php if ($order['status'] !== 'cancelled'): ?>
            <div class="card order-detail-card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-cogs"></i>
                        Thao tác đơn hàng
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <label class="form-label">Trạng thái đơn hàng:</label>
                            <div class="mt-2">
                                <span class="badge bg-<?php echo $statusInfo['class']; ?> fs-6 px-3 py-2">
                                    <i class="fas fa-<?php echo $statusInfo['icon']; ?>"></i>
                                    <?php echo $statusInfo['text']; ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6 text-end">
                            <?php if ($order['status'] === 'pending'): ?>
                                <button type="button" class="btn btn-success me-2" onclick="quickUpdateStatus('confirmed')">
                                    <i class="fas fa-check"></i> Duyệt đơn
                                </button>
                                <button type="button" class="btn btn-danger" onclick="quickUpdateStatus('cancelled')">
                                    <i class="fas fa-times"></i> Hủy đơn
                                </button>
                            <?php elseif ($order['status'] === 'confirmed'): ?>
                                <button type="button" class="btn btn-danger" onclick="quickUpdateStatus('cancelled')">
                                    <i class="fas fa-times"></i> Hủy đơn
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Cancelled order - read only -->
            <div class="card order-detail-card">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-times-circle"></i>
                        Đơn hàng đã hủy
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <label class="form-label">Trạng thái đơn hàng:</label>
                            <div class="mt-2">
                                <span class="badge bg-danger fs-6 px-3 py-2">
                                    <i class="fas fa-times-circle"></i>
                                    Đã hủy
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6 text-end">
                            <p class="text-muted mb-0">
                                <i class="fas fa-info-circle"></i>
                                Đơn hàng này đã bị hủy và không thể thay đổi trạng thái
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Hidden forms for quick actions -->
    <form id="quickUpdateForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="status" id="quickStatus">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function quickUpdateStatus(status) {
            const statusNames = {
                'pending': 'Chờ duyệt',
                'confirmed': 'Đã duyệt',
                'cancelled': 'Đã hủy'
            };

            if (confirm(`Bạn có chắc muốn thay đổi trạng thái thành "${statusNames[status]}"?`)) {
                document.getElementById('quickStatus').value = status;
                document.getElementById('quickUpdateForm').submit();
            }
        }

        // Auto-hide alerts
        setTimeout(function () {
            document.querySelectorAll('.alert').forEach(function (alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Go back to orders tab when returning to admin
        document.querySelector('.back-btn a').addEventListener('click', function (e) {
            localStorage.setItem('adminActiveTab', 'orders');
        });
    </script>
</body>

</html>