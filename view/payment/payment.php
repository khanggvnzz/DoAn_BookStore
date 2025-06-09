<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /DoAn_BookStore/view/auth/login.php');
    exit();
}

// Check if checkout data exists
if (!isset($_SESSION['checkout_data']) || empty($_SESSION['checkout_data']['items'])) {
    header('Location: /DoAn_BookStore/view/cart/cart.php');
    exit();
}

require_once __DIR__ . '/../../model/Database.php';

$database = new Database();
$userId = $_SESSION['user_id'];
$checkoutData = $_SESSION['checkout_data'];

// Get user information
$user = $database->getUserById($userId);

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'place_order') {
    try {
        $database->beginTransaction();

        // Validate form data
        $customerName = trim($_POST['customer_name'] ?? '');
        $customerPhone = trim($_POST['customer_phone'] ?? '');
        $customerEmail = trim($_POST['customer_email'] ?? '');
        $shippingAddress = trim($_POST['shipping_address'] ?? '');
        $paymentMethod = $_POST['payment_method'] ?? '';
        $notes = trim($_POST['notes'] ?? '');

        if (empty($customerName) || empty($customerPhone) || empty($shippingAddress) || empty($paymentMethod)) {
            throw new Exception('Vui lòng điền đầy đủ thông tin bắt buộc');
        }

        // Validate phone number format
        if (!preg_match('/^[0-9]{10,11}$/', $customerPhone)) {
            throw new Exception('Số điện thoại không hợp lệ');
        }

        // Get cart items for order processing
        $cartItems = $database->getCartItems($userId);
        if (empty($cartItems)) {
            throw new Exception('Giỏ hàng trống');
        }

        // Validate cart items and stock
        $cartValidation = $database->validateCartItems($userId);
        if (!$cartValidation['valid']) {
            $errorMessages = [];
            foreach ($cartValidation['errors'] as $error) {
                $errorMessages[] = $error['message'];
            }
            throw new Exception('Có lỗi với giỏ hàng: ' . implode(', ', $errorMessages));
        }

        // Create product list string and calculate total cost
        $productList = [];
        $totalCost = 0;

        foreach ($cartItems as $item) {
            $itemTotal = $item['price'] * $item['quantity'];
            $totalCost += $itemTotal;
            $productList[] = $item['id'] . ' (x' . $item['quantity'] . ')';
        }

        $productString = implode(', ', $productList);

        // Apply voucher discount if exists
        $voucherId = null;
        $finalCost = $totalCost;

        if (isset($_SESSION['applied_voucher']) && $_SESSION['applied_voucher']) {
            $voucher = $_SESSION['applied_voucher'];
            $voucherId = $voucher['voucher_id'];

            // Use the already calculated final cost from checkout data
            $finalCost = $checkoutData['final_total'];
        }

        // Prepare order data for insertion
        $orderData = [
            'user_id' => $userId,
            'product' => $productString,
            'cost' => $finalCost,
            'created_at' => date('Y-m-d H:i:s'),
            'pay_method' => $paymentMethod,
            'note' => $notes,
            'voucher_id' => $voucherId,
            'status' => 'pending'
        ];

        // Insert order into database
        $orderId = $database->createOrder($orderData);


        if (!$orderId) {
            throw new Exception('Không thể tạo đơn hàng');
        }

        // Update book stock
        foreach ($cartItems as $item) {
            $database->updateBookStock($item['id'], -$item['quantity']);
        }

        // Update voucher usage if applied
        if ($voucherId) {
            $database->updateVoucherUsage($voucherId);
        }

        // Clear user's cart
        $database->clearUserCart($userId);

        // Clear checkout data from session
        unset($_SESSION['checkout_data']);
        unset($_SESSION['applied_voucher']);

        $database->commit();

        // Redirect to order success page
        header("Location: /DoAn_BookStore/view/payment/order_success.php?order_id=" . $orderId);
        exit();

    } catch (Exception $e) {
        $database->rollback();
        $message = $e->getMessage();
        $messageType = 'danger';

        // Log error for debugging
        error_log('Order creation error: ' . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán - BookStore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/DoAn_BookStore/view/payment/payment.css">
</head>

<body>
    <?php include_once __DIR__ . '/../navigation/navigation.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/DoAn_BookStore/">Trang chủ</a></li>
                        <li class="breadcrumb-item"><a href="/DoAn_BookStore/view/cart/cart.php">Giỏ hàng</a></li>
                        <li class="breadcrumb-item active">Thanh toán</li>
                    </ol>
                </nav>

                <h2><i class="fas fa-credit-card"></i> Thanh toán</h2>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="place_order">

            <div class="row">
                <div class="col-lg-8">
                    <!-- Customer Information -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5><i class="fas fa-user"></i> Thông tin khách hàng</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="customer_name" name="customer_name"
                                            value="<?php echo htmlspecialchars($user->name ?? ''); ?>" required>
                                        <label for="customer_name">Họ và tên *</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="tel" class="form-control" id="customer_phone" name="customer_phone"
                                            value="<?php echo htmlspecialchars($user->phone ?? ''); ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-floating">
                                <input type="email" class="form-control" id="customer_email" name="customer_email"
                                    value="<?php echo htmlspecialchars($user->email ?? ''); ?>">
                            </div>
                            <div class="form-floating">
                                <textarea class="form-control" id="shipping_address" name="shipping_address"
                                    style="height: 100px"
                                    required><?php echo htmlspecialchars($user->address ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5><i class="fas fa-wallet"></i> Phương thức thanh toán</h5>
                        </div>
                        <div class="card-body">
                            <div class="payment-method" onclick="selectPaymentMethod('cod')">
                                <input type="radio" name="payment_method" value="cod" id="cod" required>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-money-bill-wave fa-2x text-success me-3"></i>
                                    <div>
                                        <h6 class="mb-1">Thanh toán khi nhận hàng (COD)</h6>
                                        <small class="text-muted">Thanh toán bằng tiền mặt khi nhận hàng</small>
                                    </div>
                                </div>
                            </div>

                            <div class="payment-method" onclick="selectPaymentMethod('bank_transfer')">
                                <input type="radio" name="payment_method" value="bank_transfer" id="bank_transfer">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-university fa-2x text-primary me-3"></i>
                                    <div>
                                        <h6 class="mb-1">Chuyển khoản ngân hàng</h6>
                                        <small class="text-muted">Chuyển khoản qua ngân hàng - Quét mã QR</small>
                                    </div>
                                </div>
                            </div>

                            <div class="payment-method" onclick="selectPaymentMethod('momo')">
                                <input type="radio" name="payment_method" value="momo" id="momo">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-mobile-alt fa-2x text-danger me-3"></i>
                                    <div>
                                        <h6 class="mb-1">Ví điện tử MoMo</h6>
                                        <small class="text-muted">Thanh toán qua ví MoMo</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Order Notes -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5><i class="fas fa-sticky-note"></i> Ghi chú đơn hàng</h5>
                        </div>
                        <div class="card-body">
                            <div class="form-floating">
                                <textarea class="form-control" id="notes" name="notes" style="height: 100px"
                                    placeholder="Ghi chú về đơn hàng (tùy chọn)"></textarea>
                                <label for="notes">Ghi chú (tùy chọn)</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Order Summary -->
                    <div class="payment-summary">
                        <h5 class="mb-3">Thông tin đơn hàng</h5>

                        <!-- Order Items -->
                        <div class="order-items mb-3">
                            <?php foreach ($checkoutData['items'] as $item): ?>
                                <div class="order-item">
                                    <div class="d-flex">
                                        <img src="/DoAn_BookStore/images/books/<?php echo htmlspecialchars($item['image']); ?>"
                                            alt="<?php echo htmlspecialchars($item['title']); ?>" class="book-image me-3">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($item['title']); ?></h6>
                                            <small class="text-muted">x<?php echo $item['quantity']; ?></small>
                                            <div class="text-end">
                                                <strong>
                                                    <?php
                                                    $itemTotal = ($item['price'] * 1000) * $item['quantity'];
                                                    echo number_format($itemTotal, 0, ',', '.');
                                                    ?> VNĐ
                                                </strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <hr>

                        <!-- Summary Details -->
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tạm tính:</span>
                            <span>
                                <?php
                                echo number_format($checkoutData['total_amount'] * 1000, 0, ',', '.');
                                ?> VNĐ
                            </span>
                        </div>

                        <div class="d-flex justify-content-between mb-2">
                            <span>Phí vận chuyển:</span>
                            <span class="text-success">Miễn phí</span>
                        </div>

                        <?php if ($checkoutData['applied_voucher'] && $checkoutData['discount_amount'] > 0): ?>
                            <div class="d-flex justify-content-between mb-2 text-success">
                                <span>
                                    <i class="fas fa-ticket-alt"></i>
                                    Giảm giá (<?php echo $checkoutData['applied_voucher']['code']; ?>):
                                </span>
                                <span>
                                    -<?php echo number_format($checkoutData['discount_amount'] * 1000, 0, ',', '.'); ?> VNĐ
                                </span>
                            </div>
                        <?php endif; ?>

                        <hr>

                        <div class="d-flex justify-content-between mb-4">
                            <strong>Tổng cộng:</strong>
                            <strong class="text-primary fs-5">
                                <?php
                                echo number_format($checkoutData['final_total'] * 1000, 0, ',', '.');
                                ?> VNĐ
                            </strong>
                        </div>

                        <!-- Action Buttons -->
                        <button type="submit" class="btn btn-primary w-100 mb-3">
                            <i class="fas fa-check"></i> Đặt hàng
                        </button>

                        <a href="/DoAn_BookStore/view/cart/cart.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-arrow-left"></i> Quay lại giỏ hàng
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- QR Code Modal -->
    <div class="modal fade" id="qrCodeModal" tabindex="-1" aria-labelledby="qrCodeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="qrCodeModalLabel">
                        <i class="fas fa-qrcode"></i> Quét mã QR để chuyển khoản
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div id="qr-loading" class="d-none">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Đang tạo mã QR...</span>
                        </div>
                        <p class="mt-2">Đang tạo mã QR...</p>
                    </div>

                    <div id="qr-content" class="d-none">
                        <div class="mb-3">
                            <img id="qr-code-img" src="" alt="QR Code" class="img-fluid" style="max-width: 250px;">
                        </div>

                        <div id="bank-info" class="text-start">
                            <!-- Bank info will be populated here -->
                        </div>

                        <div class="alert alert-info mt-3">
                            <small>
                                <i class="fas fa-info-circle"></i>
                                Vui lòng chuyển khoản theo đúng nội dung để đơn hàng được xử lý nhanh nhất.
                            </small>
                        </div>
                    </div>

                    <div id="qr-error" class="d-none">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span id="qr-error-message">Không thể tạo mã QR</span>
                        </div>
                        <button type="button" class="btn btn-primary" onclick="retryGenerateQR()">
                            <i class="fas fa-redo"></i> Thử lại
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentAmount = <?php echo $checkoutData['final_total'] * 1000; ?>;
        let qrModal;

        function selectPaymentMethod(method) {
            // Remove selected class from all payment methods
            document.querySelectorAll('.payment-method').forEach(el => {
                el.classList.remove('selected');
            });

            // Add selected class to clicked method
            document.querySelector(`[onclick="selectPaymentMethod('${method}')"]`).classList.add('selected');

            // Check the radio button
            document.getElementById(method).checked = true;

            // Handle bank transfer or momo selection
            if (method === 'bank_transfer') {
                showQRModal('bank');
            } else if (method === 'momo') {
                showQRModal('momo');
            }
        }

        function showQRModal(type) {
            qrModal = new bootstrap.Modal(document.getElementById('qrCodeModal'));
            qrModal.show();
            loadStaticQRCode(type);
        }

        // Set default payment method
        document.addEventListener('DOMContentLoaded', function () {
            selectPaymentMethod('cod');
        });

        function loadStaticQRCode(type) {
            // Show loading
            document.getElementById('qr-loading').classList.remove('d-none');
            document.getElementById('qr-content').classList.add('d-none');
            document.getElementById('qr-error').classList.add('d-none');

            // Simulate loading time
            setTimeout(() => {
                try {
                    let qrImageSrc, bankInfo, modalTitle;

                    if (type === 'bank') {
                        qrImageSrc = '/DoAn_BookStore/images/qr_pay/bank_qr.jpg';
                        modalTitle = '<i class="fas fa-qrcode"></i> Quét mã QR để chuyển khoản ngân hàng';
                        bankInfo = `
                            <h6 class="text-primary mb-2">
                                <i class="fas fa-university"></i> Thông tin chuyển khoản
                            </h6>
                            <p><strong>Ngân hàng:</strong> Vietcombank</p>
                            <p><strong>Số tài khoản:</strong> <code>1030382538</code></p>
                            <p><strong>Chủ tài khoản:</strong> VU BA NHAT KHANG</p>
                            <p><strong>Số tiền:</strong> <span class="text-danger fw-bold">${currentAmount.toLocaleString()} VNĐ</span></p>
                            <p><strong>Nội dung:</strong> <code>BookStore - Don hang - ${currentAmount.toLocaleString()} VND</code></p>
                        `;
                    } else if (type === 'momo') {
                        qrImageSrc = '/DoAn_BookStore/images/qr_pay/momo_qr.jpg';
                        modalTitle = '<i class="fas fa-mobile-alt"></i> Quét mã QR để thanh toán MoMo';
                        bankInfo = `
                            <h6 class="text-danger mb-2">
                                <i class="fas fa-mobile-alt"></i> Thông tin thanh toán MoMo
                            </h6>
                            <p><strong>Ví MoMo:</strong> BookStore Official</p>
                            <p><strong>Số tiền:</strong> <span class="text-danger fw-bold">${currentAmount.toLocaleString()} VNĐ</span></p>
                            <p><strong>Nội dung:</strong> <code>BookStore - Don hang - ${currentAmount.toLocaleString()} VND</code></p>
                            <div class="alert alert-warning mt-2">
                                <small>
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Vui lòng nhập đúng nội dung chuyển khoản để đơn hàng được xử lý tự động.
                                </small>
                            </div>
                        `;
                    }

                    // Update modal title
                    document.getElementById('qrCodeModalLabel').innerHTML = modalTitle;

                    // Hide loading
                    document.getElementById('qr-loading').classList.add('d-none');

                    // Show QR code
                    document.getElementById('qr-code-img').src = qrImageSrc;
                    document.getElementById('qr-code-img').onload = function () {
                        // Image loaded successfully
                        document.getElementById('bank-info').innerHTML = bankInfo;
                        document.getElementById('qr-content').classList.remove('d-none');
                    };

                    document.getElementById('qr-code-img').onerror = function () {
                        // Image failed to load
                        document.getElementById('qr-error-message').textContent = `Không thể tải ảnh QR ${type === 'bank' ? 'ngân hàng' : 'MoMo'}. Vui lòng thử lại.`;
                        document.getElementById('qr-error').classList.remove('d-none');
                    };

                } catch (error) {
                    console.error('Error loading QR code:', error);

                    // Hide loading
                    document.getElementById('qr-loading').classList.add('d-none');

                    // Show error
                    document.getElementById('qr-error-message').textContent = 'Lỗi: ' + error.message;
                    document.getElementById('qr-error').classList.remove('d-none');
                }
            }, 500); // 500ms delay để simulate loading
        }

        function retryGenerateQR() {
            const selectedPayment = document.querySelector('input[name="payment_method"]:checked').value;
            if (selectedPayment === 'bank_transfer') {
                loadStaticQRCode('bank');
            } else if (selectedPayment === 'momo') {
                loadStaticQRCode('momo');
            }
        }

        function confirmBankTransfer() {
            // Close modal
            qrModal.hide();

            // Show processing message
            const alertHtml = `
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <div class="d-flex align-items-center">
                        <div class="spinner-border spinner-border-sm me-2" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <span>Đang xử lý đơn hàng...</span>
                    </div>
                </div>
            `;

            // Insert alert before the form
            const form = document.querySelector('form');
            form.insertAdjacentHTML('beforebegin', alertHtml);

            // Validate and submit immediately
            setTimeout(() => {
                // Validate required fields
                const customerName = document.getElementById('customer_name').value.trim();
                const customerPhone = document.getElementById('customer_phone').value.trim();
                const shippingAddress = document.getElementById('shipping_address').value.trim();

                if (!customerName || !customerPhone || !shippingAddress) {
                    // Remove processing alert
                    document.querySelector('.alert-info').remove();

                    // Show error
                    const errorHtml = `
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle"></i>
                            Vui lòng điền đầy đủ thông tin bắt buộc (Họ tên, Số điện thoại, Địa chỉ).
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `;
                    form.insertAdjacentHTML('beforebegin', errorHtml);
                    return;
                }

                // Mark form as confirmed and submit
                form.setAttribute('data-confirmed', 'true');
                form.submit();
            }, 500);
        }

        // Update the form submission handler
        document.querySelector('form').addEventListener('submit', function (e) {
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;

            // If form is already confirmed (from confirmBankTransfer), allow submission
            if (this.hasAttribute('data-confirmed')) {
                return true;
            }

            // Handle different payment methods
            if (paymentMethod === 'cod') {
                if (!confirm('Xác nhận đặt hàng với phương thức thanh toán khi nhận hàng?')) {
                    e.preventDefault();
                    return false;
                }
            } else if (paymentMethod === 'bank_transfer' || paymentMethod === 'momo') {
                // Prevent direct submission for electronic payments
                e.preventDefault();

                const paymentName = paymentMethod === 'bank_transfer' ? 'chuyển khoản ngân hàng' : 'MoMo';

                if (confirm(`Bạn chưa hoàn tất thanh toán ${paymentName}. Bạn có muốn tiếp tục đặt hàng không?\n\nĐơn hàng sẽ được tạo và chờ xác nhận thanh toán.`)) {
                    // Mark as confirmed and submit
                    this.setAttribute('data-confirmed', 'true');
                    this.submit();
                }

                return false;
            }
        });
    </script>
</body>

</html>