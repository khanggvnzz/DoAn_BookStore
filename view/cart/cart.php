<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /DoAn_BookStore/view/auth/login.php');
    exit();
}

require_once __DIR__ . '/../../model/Database.php';

$database = new Database();
$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Voucher functions - Updated to use database
function getVouchers($database, $orderAmount = 0)
{
    return $database->getAllActiveVouchers($orderAmount);
}

function validateVoucher($database, $voucherCode, $orderAmount, $userId = null)
{
    return $database->canApplyVoucher($voucherCode, $orderAmount, $userId);
}

// Initialize voucher session
if (!isset($_SESSION['applied_voucher'])) {
    $_SESSION['applied_voucher'] = null;
}

// Handle form submissions (POST and AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Set JSON header for AJAX responses
    if (in_array($_POST['action'], ['prepare_checkout'])) {
        header('Content-Type: application/json');
    }

    switch ($_POST['action']) {
        case 'remove_item':
            $cartId = isset($_POST['cart_id']) ? intval($_POST['cart_id']) : 0;

            if ($cartId > 0) {
                $result = $database->removeFromCart($cartId);

                if ($result) {
                    $message = 'Đã xóa sản phẩm khỏi giỏ hàng thành công!';
                    $messageType = 'success';

                    // Revalidate voucher after removing item
                    if ($_SESSION['applied_voucher']) {
                        $cartSummary = $database->getCartSummary($userId);
                        $validation = validateVoucher($database, $_SESSION['applied_voucher']['code'], $cartSummary['total_amount'], $userId);

                        if (!$validation['valid']) {
                            $_SESSION['applied_voucher'] = null;
                            $message .= ' Voucher đã được gỡ bỏ do không còn phù hợp.';
                        }
                    }
                } else {
                    $message = 'Không thể xóa sản phẩm. Vui lòng thử lại.';
                    $messageType = 'danger';
                }
            }

            header('Location: ' . $_SERVER['PHP_SELF'] . ($message ? '?msg=' . urlencode($message) . '&type=' . $messageType : ''));
            exit();

        case 'update_quantity':
            $cartId = isset($_POST['cart_id']) ? intval($_POST['cart_id']) : 0;
            $quantity = max(1, intval($_POST['quantity'] ?? 1));

            if ($cartId > 0) {
                $result = $database->updateCartQuantity($cartId, $quantity);

                if ($result) {
                    $message = 'Cập nhật số lượng thành công!';
                    $messageType = 'success';

                    // Revalidate voucher after quantity change
                    if ($_SESSION['applied_voucher']) {
                        $cartSummary = $database->getCartSummary($userId);
                        $validation = validateVoucher($database, $_SESSION['applied_voucher']['code'], $cartSummary['total_amount'], $userId);

                        if (!$validation['valid']) {
                            $_SESSION['applied_voucher'] = null;
                            $message .= ' Voucher đã được gỡ bỏ do không còn phù hợp.';
                        }
                    }
                } else {
                    $message = 'Không thể cập nhật số lượng. Vui lòng thử lại.';
                    $messageType = 'danger';
                }
            }

            header('Location: ' . $_SERVER['PHP_SELF'] . ($message ? '?msg=' . urlencode($message) . '&type=' . $messageType : ''));
            exit();

        case 'clear_cart':
            $result = $database->clearCart($userId);

            if ($result) {
                $_SESSION['applied_voucher'] = null;
                $message = 'Đã xóa tất cả sản phẩm khỏi giỏ hàng!';
                $messageType = 'success';
            } else {
                $message = 'Không thể xóa giỏ hàng. Vui lòng thử lại.';
                $messageType = 'danger';
            }

            header('Location: ' . $_SERVER['PHP_SELF'] . ($message ? '?msg=' . urlencode($message) . '&type=' . $messageType : ''));
            exit();

        case 'apply_voucher':
            $voucherCode = strtoupper(trim($_POST['voucher_code'] ?? ''));

            if (!empty($voucherCode)) {
                $cartSummary = $database->getCartSummary($userId);
                $orderAmount = $cartSummary['total_amount'];

                $validation = validateVoucher($database, $voucherCode, $orderAmount, $userId);

                if ($validation['valid']) {
                    $_SESSION['applied_voucher'] = [
                        'code' => $voucherCode,
                        'name' => $validation['voucher']['name'],
                        'discount_percent' => $validation['voucher']['discount_percent'],
                        'discount_amount' => $validation['discount_amount'],
                        'voucher_id' => $validation['voucher']['voucher_id']
                    ];

                    $message = 'Áp dụng voucher thành công!';
                    $messageType = 'success';
                } else {
                    $message = $validation['message'];
                    $messageType = 'danger';
                }
            } else {
                $message = 'Vui lòng chọn mã voucher!';
                $messageType = 'warning';
            }

            header('Location: ' . $_SERVER['PHP_SELF'] . ($message ? '?msg=' . urlencode($message) . '&type=' . $messageType : ''));
            exit();

        case 'remove_voucher':
            $_SESSION['applied_voucher'] = null;
            $message = 'Đã bỏ mã voucher!';
            $messageType = 'info';

            header('Location: ' . $_SERVER['PHP_SELF'] . ($message ? '?msg=' . urlencode($message) . '&type=' . $messageType : ''));
            exit();

        case 'prepare_checkout':
            // Validate cart before checkout
            $cartSummary = $database->getCartSummary($userId);
            $validation = $database->validateCartItems($userId);

            if (empty($cartSummary['items'])) {
                echo json_encode(['success' => false, 'message' => 'Giỏ hàng trống']);
                exit();
            }

            if (!$validation['valid']) {
                echo json_encode(['success' => false, 'message' => 'Vui lòng cập nhật số lượng sản phẩm trước khi thanh toán']);
                exit();
            }

            // Store checkout data in session
            $_SESSION['checkout_data'] = [
                'items' => $cartSummary['items'],
                'total_amount' => $cartSummary['total_amount'],
                'total_items' => $cartSummary['total_items'],
                'applied_voucher' => $_SESSION['applied_voucher'],
                'discount_amount' => 0,
                'final_total' => $cartSummary['total_amount']
            ];

            // Calculate final total with voucher if applied
            if ($_SESSION['applied_voucher']) {
                $validation = validateVoucher($database, $_SESSION['applied_voucher']['code'], $cartSummary['total_amount'], $userId);
                if ($validation['valid']) {
                    $_SESSION['checkout_data']['discount_amount'] = $validation['discount_amount'];
                    $_SESSION['checkout_data']['final_total'] = $cartSummary['total_amount'] - $validation['discount_amount'];
                } else {
                    $_SESSION['applied_voucher'] = null;
                    $_SESSION['checkout_data']['applied_voucher'] = null;
                }
            }

            echo json_encode(['success' => true, 'message' => 'Chuẩn bị thanh toán thành công']);
            exit();
    }
}

// Get message from URL parameters (after redirect)
if (isset($_GET['msg']) && isset($_GET['type'])) {
    $message = $_GET['msg'];
    $messageType = $_GET['type'];
}

// Get cart data
$cartSummary = $database->getCartSummary($userId);
$cartItems = $cartSummary['items'];
$totalAmount = $cartSummary['total_amount'];
$totalItems = $cartSummary['total_items'];

// Calculate final total with voucher
$appliedVoucher = $_SESSION['applied_voucher'];
$discountAmount = 0;
$finalTotal = $totalAmount;

if ($appliedVoucher) {
    $validation = validateVoucher($database, $appliedVoucher['code'], $totalAmount, $userId);
    if ($validation['valid']) {
        $discountAmount = $validation['discount_amount'];
        $finalTotal = $totalAmount - $discountAmount;
    } else {
        $_SESSION['applied_voucher'] = null;
        $appliedVoucher = null;
    }
}

// Validate cart items (check stock)
$validation = $database->validateCartItems($userId);
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giỏ hàng - BookStore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/DoAn_BookStore/view/cart/cart.css">
</head>

<body>
    <?php include_once __DIR__ . '/../navigation/navigation.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/DoAn_BookStore/">Trang chủ</a></li>
                        <li class="breadcrumb-item active">Giỏ hàng</li>
                    </ol>
                </nav>

                <h2><i class="fas fa-shopping-cart"></i> Giỏ hàng của bạn</h2>
            </div>
        </div>

        <!-- Alert Messages -->
        <div id="alertContainer">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <i
                        class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'danger' ? 'exclamation-circle' : 'info-circle'); ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!$validation['valid']): ?>
            <div class="alert alert-warning">
                <h6><i class="fas fa-exclamation-triangle"></i> Cảnh báo tồn kho:</h6>
                <ul class="mb-0">
                    <?php foreach ($validation['errors'] as $error): ?>
                        <li><?php echo htmlspecialchars($error['message']); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (empty($cartItems)): ?>
            <!-- Empty Cart -->
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <h4>Giỏ hàng của bạn đang trống</h4>
                <p>Hãy thêm một số sản phẩm vào giỏ hàng để tiếp tục mua sắm.</p>
                <a href="/DoAn_BookStore/" class="btn btn-primary">
                    <i class="fas fa-book"></i> Tiếp tục mua sắm
                </a>
            </div>
        <?php else: ?>
            <!-- Cart Content -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="cart-items">
                        <?php foreach ($cartItems as $item): ?>
                            <div class="cart-item">
                                <div class="row align-items-center">
                                    <div class="col-md-2">
                                        <img src="/DoAn_BookStore/images/books/<?php echo htmlspecialchars($item['image']); ?>"
                                            alt="<?php echo htmlspecialchars($item['title']); ?>" class="book-image">
                                    </div>
                                    <div class="col-md-4">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($item['title']); ?></h6>
                                        <p class="text-muted mb-1">
                                            <small>Tác giả: <?php echo htmlspecialchars($item['author']); ?></small>
                                        </p>
                                        <p class="text-primary fw-bold mb-0">
                                            <?php
                                            $displayPrice = $item['price'] * 1000;
                                            echo number_format($displayPrice, 0, ',', '.');
                                            ?> VNĐ
                                        </p>
                                        <?php if ($item['quantity'] > $item['stock']): ?>
                                            <small class="stock-warning">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                Chỉ còn <?php echo $item['stock']; ?> cuốn
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-3">
                                        <form method="POST" class="d-flex align-items-center justify-content-center">
                                            <input type="hidden" name="action" value="update_quantity">
                                            <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                            <button type="submit" name="quantity"
                                                value="<?php echo max(1, $item['quantity'] - 1); ?>"
                                                class="btn btn-outline-secondary btn-sm me-2 btn-action" <?php echo $item['quantity'] <= 1 ? 'disabled' : ''; ?>>
                                                <i class="fas fa-minus"></i>
                                            </button>
                                            <span class="fw-bold mx-2"><?php echo $item['quantity']; ?></span>
                                            <button type="submit" name="quantity" value="<?php echo $item['quantity'] + 1; ?>"
                                                class="btn btn-outline-secondary btn-sm ms-2 btn-action" <?php echo $item['quantity'] >= $item['stock'] ? 'disabled' : ''; ?>>
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </form>
                                    </div>
                                    <div class="col-md-2 text-center">
                                        <strong class="item-total">
                                            <?php
                                            $itemTotal = ($item['price'] * 1000) * $item['quantity'];
                                            echo number_format($itemTotal, 0, ',', '.');
                                            ?> VNĐ
                                        </strong>
                                    </div>
                                    <div class="col-md-1 text-center">
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="remove_item">
                                            <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm btn-action"
                                                onclick="return confirm('Bạn có chắc muốn xóa sản phẩm này?')"
                                                title="Xóa sản phẩm">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Cart Actions -->
                    <div class="d-flex justify-content-between mt-4">
                        <a href="/DoAn_BookStore/" class="btn btn-outline-primary btn-action">
                            <i class="fas fa-arrow-left"></i> Tiếp tục mua sắm
                        </a>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="clear_cart">
                            <button type="submit" class="btn btn-outline-danger btn-action"
                                onclick="return confirm('Bạn có chắc muốn xóa tất cả sản phẩm?')">
                                <i class="fas fa-trash"></i> Xóa tất cả
                            </button>
                        </form>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Cart Summary -->
                    <div class="cart-summary">
                        <h5 class="mb-3">Tóm tắt đơn hàng</h5>

                        <div class="d-flex justify-content-between mb-2">
                            <span>Số lượng sản phẩm:</span>
                            <span><?php echo $totalItems; ?></span>
                        </div>

                        <div class="d-flex justify-content-between mb-3">
                            <span>Tạm tính:</span>
                            <span>
                                <?php
                                $displayTotal = $totalAmount * 1000;
                                echo number_format($displayTotal, 0, ',', '.');
                                ?> VNĐ
                            </span>
                        </div>

                        <!-- Voucher Section -->
                        <div class="voucher-section mb-3">
                            <h6 class="mb-2">
                                <i class="fas fa-ticket-alt"></i> Mã giảm giá
                            </h6>

                            <?php if ($appliedVoucher): ?>
                                <!-- Applied Voucher -->
                                <div class="applied-voucher p-2 bg-success bg-opacity-10 border border-success rounded mb-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <code class="text-success fw-bold"><?php echo $appliedVoucher['code']; ?></code>
                                            <small class="d-block text-muted">
                                                <?php echo htmlspecialchars($appliedVoucher['name']); ?>
                                            </small>
                                        </div>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="remove_voucher">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- Voucher Selection -->
                                <form method="POST">
                                    <input type="hidden" name="action" value="apply_voucher">
                                    <?php
                                    $availableVouchers = getVouchers($database, $totalAmount);
                                    ?>

                                    <?php if (!empty($availableVouchers)): ?>
                                        <div class="input-group">
                                            <select class="form-select" name="voucher_code">
                                                <option value="">Chọn mã giảm giá</option>
                                                <?php foreach ($availableVouchers as $voucher): ?>
                                                    <option value="<?php echo $voucher['code']; ?>">
                                                        <?php echo $voucher['code']; ?> -
                                                        <?php echo htmlspecialchars($voucher['name']); ?>
                                                        (Giảm <?php echo $voucher['discount_percent']; ?>%)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button class="btn btn-outline-primary" type="submit">
                                                <i class="fas fa-tag"></i> Áp dụng
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-muted text-center py-2">
                                            <i class="fas fa-info-circle"></i>
                                            Không có voucher phù hợp với đơn hàng hiện tại.
                                        </div>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex justify-content-between mb-2">
                            <span>Phí vận chuyển:</span>
                            <span class="text-success">Miễn phí</span>
                        </div>

                        <?php if ($appliedVoucher && $discountAmount > 0): ?>
                            <div class="d-flex justify-content-between mb-2 text-success">
                                <span>
                                    <i class="fas fa-ticket-alt"></i> Giảm giá:
                                </span>
                                <span>
                                    -<?php echo number_format($discountAmount * 1000, 0, ',', '.'); ?> VNĐ
                                </span>
                            </div>
                        <?php endif; ?>

                        <hr>

                        <div class="d-flex justify-content-between mb-4">
                            <strong>Tổng cộng:</strong>
                            <strong class="text-primary">
                                <?php
                                $displayFinalTotal = $finalTotal * 1000;
                                echo number_format($displayFinalTotal, 0, ',', '.');
                                ?> VNĐ
                            </strong>
                        </div>

                        <button class="btn btn-primary w-100 mb-3 btn-action" id="checkoutBtn" <?php echo !$validation['valid'] ? 'disabled' : ''; ?>>
                            <i class="fas fa-credit-card"></i> Thanh toán
                        </button>

                        <?php if (!$validation['valid']): ?>
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i>
                                Vui lòng cập nhật số lượng trước khi thanh toán
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Show alert function
            function showAlert(message, type = 'success') {
                const alertContainer = document.getElementById('alertContainer');
                const alertHtml = `
                    <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : (type === 'danger' ? 'exclamation-circle' : 'info-circle')}"></i>
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
                alertContainer.innerHTML = alertHtml;

                // Auto hide after 3 seconds
                setTimeout(() => {
                    const alert = alertContainer.querySelector('.alert');
                    if (alert) {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }
                }, 3000);
            }

            // Auto hide existing alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function (alert) {
                setTimeout(function () {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });

            // Checkout button
            document.getElementById('checkoutBtn')?.addEventListener('click', function () {
                if (this.disabled) {
                    showAlert('Vui lòng cập nhật số lượng trước khi thanh toán', 'warning');
                    return;
                }

                const btn = this;
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xử lý...';
                btn.disabled = true;

                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=prepare_checkout'
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = '/DoAn_BookStore/view/payment/payment.php';
                        } else {
                            showAlert(data.message, 'danger');
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showAlert('Có lỗi xảy ra', 'danger');
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    });
            });
        });
    </script>
</body>

</html>