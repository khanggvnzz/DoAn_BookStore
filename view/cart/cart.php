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

// Voucher functions
function getVouchers()
{
    $configFile = __DIR__ . '/../../config/vouchers.json';
    if (file_exists($configFile)) {
        $content = file_get_contents($configFile);
        return json_decode($content, true) ?: [];
    }
    return [];
}

function validateVoucher($voucherCode, $orderAmount)
{
    $vouchers = getVouchers();

    if (!isset($vouchers[$voucherCode])) {
        return ['valid' => false, 'message' => 'Mã voucher không tồn tại'];
    }

    $voucher = $vouchers[$voucherCode];

    // Check if voucher is active
    if (!$voucher['is_active']) {
        return ['valid' => false, 'message' => 'Voucher đã bị vô hiệu hóa'];
    }

    // Check if voucher is expired
    if ($voucher['expires_at'] && new DateTime($voucher['expires_at']) < new DateTime()) {
        return ['valid' => false, 'message' => 'Voucher đã hết hạn'];
    }

    // Check if voucher quantity is available
    if ($voucher['used_count'] >= $voucher['quantity']) {
        return ['valid' => false, 'message' => 'Voucher đã hết lượt sử dụng'];
    }

    // Check minimum order amount
    if ($orderAmount < $voucher['min_order_amount']) {
        $minAmount = number_format($voucher['min_order_amount'] * 1000, 0, ',', '.');
        return ['valid' => false, 'message' => "Đơn hàng tối thiểu {$minAmount} VNĐ để sử dụng voucher này"];
    }

    return [
        'valid' => true,
        'voucher' => $voucher,
        'discount_amount' => ($orderAmount * $voucher['discount_percent']) / 100
    ];
}

// Initialize voucher session
if (!isset($_SESSION['applied_voucher'])) {
    $_SESSION['applied_voucher'] = null;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        case 'apply_voucher':
            $voucherCode = strtoupper(trim($_POST['voucher_code'] ?? ''));

            if (empty($voucherCode)) {
                echo json_encode(['success' => false, 'message' => 'Vui lòng chọn mã voucher']);
                exit();
            }

            // Get current cart total
            $cartSummary = $database->getCartSummary($userId);
            $orderAmount = $cartSummary['total_amount'];

            $validation = validateVoucher($voucherCode, $orderAmount);

            if ($validation['valid']) {
                $_SESSION['applied_voucher'] = [
                    'code' => $voucherCode,
                    'name' => $validation['voucher']['name'],
                    'discount_percent' => $validation['voucher']['discount_percent'],
                    'discount_amount' => $validation['discount_amount']
                ];

                $finalTotal = $orderAmount - $validation['discount_amount'];

                echo json_encode([
                    'success' => true,
                    'message' => 'Áp dụng voucher thành công!',
                    'voucher_name' => $validation['voucher']['name'],
                    'discount_percent' => $validation['voucher']['discount_percent'],
                    'discount_amount' => number_format($validation['discount_amount'] * 1000, 0, ',', '.'),
                    'final_total' => number_format($finalTotal * 1000, 0, ',', '.')
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => $validation['message']]);
            }
            exit();

        case 'remove_voucher':
            $_SESSION['applied_voucher'] = null;

            $cartSummary = $database->getCartSummary($userId);
            echo json_encode([
                'success' => true,
                'message' => 'Đã bỏ voucher',
                'final_total' => number_format($cartSummary['total_amount'] * 1000, 0, ',', '.')
            ]);
            exit();

        case 'update_quantity':
            $cartId = $_POST['cart_id'] ?? 0;
            $quantity = max(1, intval($_POST['quantity'] ?? 1));

            $result = $database->updateCartQuantity($cartId, $quantity);
            if ($result) {
                $cartSummary = $database->getCartSummary($userId);

                // Recalculate voucher discount if applied
                $discountAmount = 0;
                $finalTotal = $cartSummary['total_amount'];

                if ($_SESSION['applied_voucher']) {
                    $validation = validateVoucher($_SESSION['applied_voucher']['code'], $cartSummary['total_amount']);
                    if ($validation['valid']) {
                        $discountAmount = $validation['discount_amount'];
                        $finalTotal = $cartSummary['total_amount'] - $discountAmount;
                    } else {
                        $_SESSION['applied_voucher'] = null; // Remove invalid voucher
                    }
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Cập nhật số lượng thành công',
                    'total_amount' => number_format($cartSummary['total_amount'] * 1000, 0, ',', '.'),
                    'total_items' => $cartSummary['total_items'],
                    'discount_amount' => number_format($discountAmount * 1000, 0, ',', '.'),
                    'final_total' => number_format($finalTotal * 1000, 0, ',', '.'),
                    'voucher_valid' => $_SESSION['applied_voucher'] !== null
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Không thể cập nhật số lượng']);
            }
            exit();

        case 'remove_item':
            $cartId = $_POST['cart_id'] ?? 0;

            $result = $database->removeFromCart($cartId);
            if ($result) {
                $cartSummary = $database->getCartSummary($userId);

                // Recalculate voucher discount if applied
                $discountAmount = 0;
                $finalTotal = $cartSummary['total_amount'];

                if ($_SESSION['applied_voucher']) {
                    $validation = validateVoucher($_SESSION['applied_voucher']['code'], $cartSummary['total_amount']);
                    if ($validation['valid']) {
                        $discountAmount = $validation['discount_amount'];
                        $finalTotal = $cartSummary['total_amount'] - $discountAmount;
                    } else {
                        $_SESSION['applied_voucher'] = null; // Remove invalid voucher
                    }
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Đã xóa sản phẩm khỏi giỏ hàng',
                    'total_amount' => number_format($cartSummary['total_amount'] * 1000, 0, ',', '.'),
                    'total_items' => $cartSummary['total_items'],
                    'item_count' => $cartSummary['item_count'],
                    'discount_amount' => number_format($discountAmount * 1000, 0, ',', '.'),
                    'final_total' => number_format($finalTotal * 1000, 0, ',', '.'),
                    'voucher_valid' => $_SESSION['applied_voucher'] !== null
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Không thể xóa sản phẩm']);
            }
            exit();

        case 'clear_cart':
            $result = $database->clearCart($userId);
            if ($result) {
                $_SESSION['applied_voucher'] = null; // Clear voucher when cart is cleared
                echo json_encode([
                    'success' => true,
                    'message' => 'Đã xóa tất cả sản phẩm khỏi giỏ hàng'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Không thể xóa giỏ hàng']);
            }
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
                $validation = validateVoucher($_SESSION['applied_voucher']['code'], $cartSummary['total_amount']);
                if ($validation['valid']) {
                    $_SESSION['checkout_data']['discount_amount'] = $validation['discount_amount'];
                    $_SESSION['checkout_data']['final_total'] = $cartSummary['total_amount'] - $validation['discount_amount'];
                } else {
                    $_SESSION['applied_voucher'] = null; // Remove invalid voucher
                    $_SESSION['checkout_data']['applied_voucher'] = null;
                }
            }

            echo json_encode(['success' => true, 'message' => 'Chuẩn bị thanh toán thành công']);
            exit();
    }
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
    $validation = validateVoucher($appliedVoucher['code'], $totalAmount);
    if ($validation['valid']) {
        $discountAmount = $validation['discount_amount'];
        $finalTotal = $totalAmount - $discountAmount;
    } else {
        $_SESSION['applied_voucher'] = null; // Remove invalid voucher
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
    <style>
        .cart-item {
            border-bottom: 1px solid #eee;
            padding: 20px 0;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .book-image {
            width: 80px;
            height: 120px;
            object-fit: cover;
            border-radius: 5px;
        }

        .quantity-input {
            width: 70px;
            text-align: center;
        }

        .cart-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            position: sticky;
            top: 20px;
        }

        .empty-cart {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-cart i {
            font-size: 4rem;
            margin-bottom: 20px;
        }

        .stock-warning {
            color: #dc3545;
            font-size: 0.875rem;
        }

        .voucher-section .form-select {
            font-size: 0.9rem;
        }

        .voucher-section .form-select option {
            padding: 8px;
        }

        #voucherDetails {
            transition: all 0.3s ease;
        }

        #voucherDetails .alert {
            font-size: 0.85rem;
        }

        .applied-voucher {
            transition: all 0.3s ease;
        }

        .voucher-input .input-group {
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
        }
    </style>
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
        <div id="alertContainer"></div>

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
                            <div class="cart-item" data-cart-id="<?php echo $item['cart_id']; ?>">
                                <div class="row align-items-center">
                                    <div class="col-md-2 text-center">
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
                                                Chỉ còn <?php echo $item['stock_quantity']; ?> cuốn
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="d-flex align-items-center justify-content-center">
                                            <button class="btn btn-outline-secondary btn-sm me-2 quantity-btn"
                                                data-action="decrease" data-cart-id="<?php echo $item['cart_id']; ?>">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                            <input type="number" class="form-control quantity-input"
                                                value="<?php echo $item['quantity']; ?>" min="1"
                                                max="<?php echo $item['stock']; ?>"
                                                data-cart-id="<?php echo $item['cart_id']; ?>">
                                            <button class="btn btn-outline-secondary btn-sm ms-2 quantity-btn"
                                                data-action="increase" data-cart-id="<?php echo $item['cart_id']; ?>">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
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
                                        <button class="btn btn-outline-danger btn-sm remove-item"
                                            data-cart-id="<?php echo $item['cart_id']; ?>" title="Xóa sản phẩm">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Cart Actions -->
                    <div class="d-flex justify-content-between mt-4">
                        <a href="/DoAn_BookStore/" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left"></i> Tiếp tục mua sắm
                        </a>
                        <button class="btn btn-outline-danger" id="clearCartBtn">
                            <i class="fas fa-trash"></i> Xóa tất cả
                        </button>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Cart Summary -->
                    <div class="cart-summary">
                        <h5 class="mb-3">Tóm tắt đơn hàng</h5>

                        <div class="d-flex justify-content-between mb-2">
                            <span>Số lượng sản phẩm:</span>
                            <span id="summary-items"><?php echo $totalItems; ?></span>
                        </div>

                        <div class="d-flex justify-content-between mb-3">
                            <span>Tạm tính:</span>
                            <span id="summary-subtotal">
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
                                                Giảm <?php echo $appliedVoucher['discount_percent']; ?>%
                                            </small>
                                        </div>
                                        <button class="btn btn-sm btn-outline-danger" id="removeVoucherBtn">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- Voucher Dropdown -->
                                <div class="voucher-input">
                                    <?php
                                    $availableVouchers = getVouchers();
                                    $validVouchers = [];

                                    // Filter valid vouchers for current cart
                                    foreach ($availableVouchers as $code => $voucher) {
                                        if (
                                            $voucher['is_active'] &&
                                            $voucher['used_count'] < $voucher['quantity'] &&
                                            (!$voucher['expires_at'] || new DateTime($voucher['expires_at']) >= new DateTime()) &&
                                            $totalAmount >= $voucher['min_order_amount']
                                        ) {
                                            $validVouchers[$code] = $voucher;
                                        }
                                    }
                                    ?>

                                    <?php if (!empty($validVouchers)): ?>
                                        <div class="input-group">
                                            <select class="form-select" id="voucherSelect">
                                                <option value="">Chọn mã giảm giá</option>
                                                <?php foreach ($validVouchers as $code => $voucher): ?>
                                                    <option value="<?php echo $code; ?>"
                                                        data-discount="<?php echo $voucher['discount_percent']; ?>"
                                                        data-min-amount="<?php echo $voucher['min_order_amount']; ?>">
                                                        <?php echo $code; ?> -
                                                        Giảm <?php echo $voucher['discount_percent']; ?>%
                                                        (Tối thiểu
                                                        <?php echo number_format($voucher['min_order_amount'] * 1000, 0, ',', '.'); ?>
                                                        VNĐ)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button class="btn btn-outline-primary" type="button" id="applyVoucherBtn">
                                                <i class="fas fa-tag"></i> Áp dụng
                                            </button>
                                        </div>

                                        <!-- Show voucher details -->
                                        <div id="voucherDetails" class="mt-2" style="display: none;">
                                            <div class="alert alert-info py-2 mb-0">
                                                <small id="voucherDescription"></small>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-muted text-center py-2">
                                            <i class="fas fa-info-circle"></i>
                                            <?php if (empty($availableVouchers)): ?>
                                                Hiện tại không có voucher nào.
                                            <?php else: ?>
                                                Không có voucher phù hợp với đơn hàng hiện tại.
                                                <br><small>Đơn hàng tối thiểu để sử dụng voucher:
                                                    <?php
                                                    $minAmount = min(array_column($availableVouchers, 'min_order_amount'));
                                                    echo number_format($minAmount * 1000, 0, ',', '.');
                                                    ?> VNĐ</small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
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
                                <span id="discount-amount">
                                    -<?php echo number_format($discountAmount * 1000, 0, ',', '.'); ?> VNĐ
                                </span>
                            </div>
                        <?php endif; ?>

                        <hr>

                        <div class="d-flex justify-content-between mb-4">
                            <strong>Tổng cộng:</strong>
                            <strong class="text-primary" id="summary-total">
                                <?php
                                $displayFinalTotal = $finalTotal * 1000;
                                echo number_format($displayFinalTotal, 0, ',', '.');
                                ?> VNĐ
                            </strong>
                        </div>

                        <button class="btn btn-primary w-100 mb-3" id="checkoutBtn" <?php echo !$validation['valid'] ? 'disabled' : ''; ?>>
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

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Show alert function
            function showAlert(message, type = 'success') {
                const alertContainer = document.getElementById('alertContainer');
                const alertHtml = `
                    <div class="alert alert-${type} alert-dismissible fade show" role="alert">
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

            // Update summary
            function updateSummary(data) {
                document.getElementById('summary-items').textContent = data.total_items;
                document.getElementById('summary-subtotal').textContent = data.total_amount + ' VNĐ';

                // Update discount and final total
                const discountElement = document.getElementById('discount-amount');
                if (data.discount_amount && parseFloat(data.discount_amount.replace(/[^\d]/g, '')) > 0) {
                    if (discountElement) {
                        discountElement.textContent = '-' + data.discount_amount + ' VNĐ';
                    }
                } else {
                    if (discountElement && discountElement.parentElement) {
                        discountElement.parentElement.style.display = 'none';
                    }
                }

                document.getElementById('summary-total').textContent = data.final_total + ' VNĐ';

                // Handle voucher validity
                if (data.voucher_valid === false) {
                    location.reload(); // Reload to show voucher input again
                }
            }

            // Handle voucher selection
            document.getElementById('voucherSelect')?.addEventListener('change', function () {
                const selectedOption = this.options[this.selectedIndex];
                const voucherDetails = document.getElementById('voucherDetails');
                const voucherDescription = document.getElementById('voucherDescription');

                if (this.value) {
                    const discount = selectedOption.dataset.discount;
                    const minAmount = selectedOption.dataset.minAmount;
                    const formattedMinAmount = new Intl.NumberFormat('vi-VN').format(minAmount * 1000);

                    voucherDescription.innerHTML = `
                        <strong>${this.value}</strong><br>
                        <i class="fas fa-percentage"></i> Giảm giá: ${discount}%<br>
                        <i class="fas fa-shopping-cart"></i> Đơn tối thiểu: ${formattedMinAmount} VNĐ
                    `;
                    voucherDetails.style.display = 'block';
                } else {
                    voucherDetails.style.display = 'none';
                }
            });

            // Apply voucher
            document.getElementById('applyVoucherBtn')?.addEventListener('click', function () {
                const voucherSelect = document.getElementById('voucherSelect');
                const voucherCode = voucherSelect?.value;

                if (!voucherCode) {
                    showAlert('Vui lòng chọn mã voucher', 'warning');
                    return;
                }

                const btn = this;
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xử lý...';
                btn.disabled = true;
                voucherSelect.disabled = true;

                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=apply_voucher&voucher_code=${encodeURIComponent(voucherCode)}`
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showAlert(data.message, 'success');
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showAlert(data.message, 'danger');
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                            voucherSelect.disabled = false;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showAlert('Có lỗi xảy ra', 'danger');
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                        voucherSelect.disabled = false;
                    });
            });

            // Remove voucher
            document.getElementById('removeVoucherBtn')?.addEventListener('click', function () {
                if (confirm('Bạn có chắc muốn bỏ mã giảm giá?')) {
                    const btn = this;
                    btn.disabled = true;

                    fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=remove_voucher'
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showAlert(data.message, 'info');
                                setTimeout(() => location.reload(), 1000);
                            } else {
                                showAlert('Có lỗi xảy ra', 'danger');
                                btn.disabled = false;
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showAlert('Có lỗi xảy ra', 'danger');
                            btn.disabled = false;
                        });
                }
            });

            // Quick apply voucher (double click on select)
            document.getElementById('voucherSelect')?.addEventListener('dblclick', function () {
                if (this.value) {
                    document.getElementById('applyVoucherBtn').click();
                }
            });

            // Update quantity
            function updateQuantity(cartId, quantity) {
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=update_quantity&cart_id=${cartId}&quantity=${quantity}`
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update summary
                            updateSummary(data);

                            // Update item total
                            const cartItem = document.querySelector(`[data-cart-id="${cartId}"]`);
                            const priceText = cartItem.querySelector('.text-primary').textContent;
                            const price = parseFloat(priceText.replace(/[^\d]/g, ''));
                            const itemTotal = cartItem.querySelector('.item-total');
                            const newTotal = price * quantity;
                            itemTotal.textContent = new Intl.NumberFormat('vi-VN').format(newTotal) + ' VNĐ';

                            showAlert(data.message);

                            // Check if voucher is still valid after quantity change
                            if (data.voucher_valid === false) {
                                showAlert('Voucher không còn phù hợp với đơn hàng hiện tại', 'warning');
                            }
                        } else {
                            showAlert(data.message, 'danger');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showAlert('Có lỗi xảy ra', 'danger');
                    });
            }

            // Remove item
            document.querySelectorAll('.remove-item').forEach(btn => {
                btn.addEventListener('click', function () {
                    if (confirm('Bạn có chắc muốn xóa sản phẩm này?')) {
                        const cartId = this.dataset.cartId;

                        fetch(window.location.href, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `action=remove_item&cart_id=${cartId}`
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    // Remove item from DOM
                                    document.querySelector(`[data-cart-id="${cartId}"]`).remove();

                                    // Update summary
                                    updateSummary(data);

                                    showAlert(data.message);

                                    // Check if cart is empty
                                    if (data.item_count === 0) {
                                        location.reload();
                                    }
                                } else {
                                    showAlert(data.message, 'danger');
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                showAlert('Có lỗi xảy ra', 'danger');
                            });
                    }
                });
            });

            // Quantity buttons
            document.querySelectorAll('.quantity-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    const cartId = this.dataset.cartId;
                    const action = this.dataset.action;
                    const input = document.querySelector(`input[data-cart-id="${cartId}"]`);
                    let quantity = parseInt(input.value);

                    if (action === 'increase') {
                        quantity++;
                    } else if (action === 'decrease' && quantity > 1) {
                        quantity--;
                    }

                    input.value = quantity;
                    updateQuantity(cartId, quantity);
                });
            });

            // Quantity input change
            document.querySelectorAll('.quantity-input').forEach(input => {
                input.addEventListener('change', function () {
                    const cartId = this.dataset.cartId;
                    const quantity = Math.max(1, parseInt(this.value) || 1);
                    this.value = quantity;
                    updateQuantity(cartId, quantity);
                });
            });

            // Clear cart
            document.getElementById('clearCartBtn')?.addEventListener('click', function () {
                if (confirm('Bạn có chắc muốn xóa tất cả sản phẩm trong giỏ hàng?')) {
                    fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=clear_cart'
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showAlert(data.message);
                                setTimeout(() => {
                                    location.reload();
                                }, 1000);
                            } else {
                                showAlert(data.message, 'danger');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showAlert('Có lỗi xảy ra', 'danger');
                        });
                }
            });

            // Checkout button
            document.getElementById('checkoutBtn')?.addEventListener('click', function () {
                if (this.disabled) {
                    showAlert('Vui lòng cập nhật số lượng trước khi thanh toán', 'warning');
                    return;
                }

                // Prepare checkout data
                const checkoutData = {
                    action: 'prepare_checkout'
                };

                // Show loading
                const btn = this;
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xử lý...';
                btn.disabled = true;

                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=prepare_checkout`
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Redirect to payment page
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