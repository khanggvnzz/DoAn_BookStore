<?php

session_start();
require_once '../../model/Database.php';

// Khởi tạo database
$db = new Database();

// Lấy tất cả voucher đang hoạt động và chưa hết hạn
$activeVouchers = $db->getAllActiveVouchers();

// Tìm voucher có % giảm giá lớn nhất để làm banner (chỉ trong các voucher còn hiệu lực)
$maxDiscountVoucher = null;
$maxDiscount = 0;

foreach ($activeVouchers as $voucher) {
    // Double check expiry (additional safety)
    if (strtotime($voucher['expires_at']) > time() && $voucher['discount_percent'] > $maxDiscount) {
        $maxDiscount = $voucher['discount_percent'];
        $maxDiscountVoucher = $voucher;
    }
}

// Lấy thông tin người dùng nếu đã đăng nhập
$user = null;
if (isset($_SESSION['user_id'])) {
    $user = $db->getUserById($_SESSION['user_id']);
}

// Xử lý AJAX request cho check voucher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'check_voucher':
                $voucherCode = trim($_POST['voucher_code'] ?? '');
                $orderAmount = floatval($_POST['order_amount'] ?? 0);
                
                if (empty($voucherCode)) {
                    throw new Exception('Vui lòng nhập mã voucher');
                }
                
                $result = $db->canApplyVoucher($voucherCode, $orderAmount);
                echo json_encode($result);
                break;
                
            case 'get_voucher_info':
                $voucherCode = trim($_POST['voucher_code'] ?? '');
                
                if (empty($voucherCode)) {
                    throw new Exception('Mã voucher không hợp lệ');
                }
                
                $voucher = $db->getVoucherByCode($voucherCode);
                if (!$voucher) {
                    throw new Exception('Voucher không tồn tại');
                }
                
                echo json_encode([
                    'success' => true,
                    'voucher' => $voucher
                ]);
                break;
                
            default:
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
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Khuyến Mãi & Voucher - BookStore</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/DoAn_BookStore/view/promotion/promotion.css">
</head>
<body>
    <?php include '../navigation/navigation.php'; ?>
    
    <div class="container-fluid">
        <!-- Hero Banner với voucher giảm giá cao nhất -->
        <?php if ($maxDiscountVoucher): ?>
        <div class="hero-banner">
            <div class="banner-content">
                <div class="container">
                    <div class="row align-items-center">
                        <div class="col-lg-6">
                            <div class="banner-text">
                                <h1 class="banner-title">
                                    <span class="highlight">SALE KHỦNG</span>
                                    <br>Giảm đến <?php echo $maxDiscountVoucher['discount_percent']; ?>%
                                </h1>
                                <p class="banner-subtitle">
                                    <?php echo htmlspecialchars($maxDiscountVoucher['name']); ?>
                                </p>
                                <p class="banner-description">
                                    <?php echo htmlspecialchars($maxDiscountVoucher['description']); ?>
                                </p>
                                
                                <div class="voucher-code-display">
                                    <span class="code-label">Mã giảm giá:</span>
                                    <span class="voucher-code" onclick="copyVoucherCode('<?php echo $maxDiscountVoucher['code']; ?>')">
                                        <?php echo $maxDiscountVoucher['code']; ?>
                                        <i class="fas fa-copy"></i>
                                    </span>
                                </div>
                                
                                <?php if ($maxDiscountVoucher['min_order_amount'] > 0): ?>
                                <p class="min-order">
                                    <i class="fas fa-info-circle"></i>
                                    Áp dụng cho đơn hàng từ <?php echo number_format($maxDiscountVoucher['min_order_amount'] * 1000, 0, ',', '.'); ?> VNĐ
                                </p>
                                <?php endif; ?>
                                
                                <div class="banner-actions">
                                    <a href="/DoAn_BookStore/view/books/books.php" class="btn btn-primary btn-lg">
                                        <i class="fas fa-shopping-cart"></i> Mua Ngay
                                    </a>
                                    <button class="btn btn-outline-light btn-lg" onclick="scrollToVouchers()">
                                        <i class="fas fa-gift"></i> Xem Tất Cả Voucher
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-6">
                            <div class="banner-image">
                                <div class="discount-badge">
                                    <span class="discount-percent"><?php echo $maxDiscountVoucher['discount_percent']; ?>%</span>
                                    <span class="discount-text">GIẢM</span>
                                </div>
                                <div class="floating-elements">
                                    <div class="floating-icon"><i class="fas fa-book"></i></div>
                                    <div class="floating-icon"><i class="fas fa-star"></i></div>
                                    <div class="floating-icon"><i class="fas fa-heart"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Countdown Timer -->
        <?php if ($maxDiscountVoucher && $maxDiscountVoucher['expires_at']): ?>
        <div class="countdown-section">
            <div class="container">
                <div class="countdown-container">
                    <h3><i class="fas fa-clock"></i> Ưu đãi kết thúc trong:</h3>
                    <div class="countdown-timer" data-end-date="<?php echo $maxDiscountVoucher['expires_at']; ?>">
                        <div class="time-unit">
                            <span class="time-value" id="days">00</span>
                            <span class="time-label">Ngày</span>
                        </div>
                        <div class="time-unit">
                            <span class="time-value" id="hours">00</span>
                            <span class="time-label">Giờ</span>
                        </div>
                        <div class="time-unit">
                            <span class="time-value" id="minutes">00</span>
                            <span class="time-label">Phút</span>
                        </div>
                        <div class="time-unit">
                            <span class="time-value" id="seconds">00</span>
                            <span class="time-label">Giây</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Main Content -->
        <div class="container my-5">
            <!-- Voucher Checker Section -->
            <div class="voucher-checker-section mb-5">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="voucher-checker-card">
                            <h3 class="text-center mb-4">
                                <i class="fas fa-search"></i> Kiểm Tra Voucher
                            </h3>
                            <form id="voucherCheckerForm">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="voucherCode" class="form-label">Mã Voucher</label>
                                        <input type="text" class="form-control" id="voucherCode" 
                                               placeholder="Nhập mã voucher..." required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="orderAmount" class="form-label">Giá trị đơn hàng (nghìn VNĐ)</label>
                                        <input type="number" class="form-control" id="orderAmount" 
                                               placeholder="Ví dụ: 100" min="0" step="0.1">
                                    </div>
                                </div>
                                <div class="text-center">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-check"></i> Kiểm Tra
                                    </button>
                                </div>
                            </form>
                            
                            <div id="voucherResult" class="voucher-result mt-4" style="display: none;">
                                <!-- Kết quả sẽ được hiển thị ở đây -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- All Vouchers Section -->
            <div id="vouchersSection" class="vouchers-section">
                <h2 class="section-title text-center mb-5">
                    <i class="fas fa-gift"></i> Tất Cả Voucher Khuyến Mãi
                </h2>
                
                <?php if (!empty($activeVouchers)): ?>
                <div class="row">
                    <?php foreach ($activeVouchers as $voucher): ?>
                        <?php
                        // Kiểm tra thời gian còn lại
                        $expiryTime = strtotime($voucher['expires_at']);
                        $currentTime = time();
                        $timeLeft = $expiryTime - $currentTime;
                        $isExpiringSoon = $timeLeft <= (7 * 24 * 60 * 60); // 7 days
                        $isExpiringToday = $timeLeft <= (24 * 60 * 60); // 1 day
                        
                        // Skip expired vouchers (additional safety check)
                        if ($timeLeft <= 0) {
                            continue;
                        }
                        ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="voucher-card <?php echo $isExpiringToday ? 'expiring-today' : ($isExpiringSoon ? 'expiring-soon' : ''); ?>">
                                <?php if ($isExpiringSoon): ?>
                                <div class="urgency-badge">
                                    <?php if ($isExpiringToday): ?>
                                        <i class="fas fa-exclamation-triangle"></i> Hết hạn hôm nay!
                                    <?php else: ?>
                                        <i class="fas fa-clock"></i> Sắp hết hạn
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="voucher-header">
                                    <div class="voucher-discount">
                                        <?php echo $voucher['discount_percent']; ?>%
                                    </div>
                                    <div class="voucher-type">
                                        <i class="fas fa-tag"></i>
                                        Giảm giá
                                    </div>
                                </div>
                                
                                <div class="voucher-body">
                                    <h5 class="voucher-name"><?php echo htmlspecialchars($voucher['name']); ?></h5>
                                    <p class="voucher-description"><?php echo htmlspecialchars($voucher['description']); ?></p>
                                    
                                    <div class="voucher-details">
                                        <?php if ($voucher['min_order_amount'] > 0): ?>
                                        <div class="detail-item">
                                            <i class="fas fa-shopping-cart"></i>
                                            Đơn tối thiểu: <?php echo number_format($voucher['min_order_amount'] * 1000, 0, ',', '.'); ?> VNĐ
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="detail-item">
                                            <i class="fas fa-clock"></i>
                                            HSD: <?php echo date('d/m/Y H:i', strtotime($voucher['expires_at'])); ?>
                                            <?php if ($isExpiringSoon): ?>
                                                <span class="time-left">
                                                    (Còn <?php echo $timeLeft < 86400 ? ceil($timeLeft/3600) . ' giờ' : ceil($timeLeft/86400) . ' ngày'; ?>)
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="detail-item">
                                            <i class="fas fa-users"></i>
                                            Còn lại: <?php echo ($voucher['quantity'] - $voucher['used_count']); ?> lượt
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="voucher-footer">
                                    <div class="voucher-code-container">
                                        <span class="voucher-code-text"><?php echo $voucher['code']; ?></span>
                                        <button class="btn btn-copy" onclick="copyVoucherCode('<?php echo $voucher['code']; ?>')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                    
                                    <div class="voucher-actions">
                                        <button class="btn btn-primary btn-sm" onclick="useVoucherNow('<?php echo $voucher['code']; ?>')">
                                            <i class="fas fa-shopping-cart"></i> Dùng Ngay
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Progress bar showing usage -->
                                <div class="usage-progress">
                                    <?php 
                                    $usagePercent = ($voucher['used_count'] / $voucher['quantity']) * 100;
                                    ?>
                                    <div class="progress">
                                        <div class="progress-bar" style="width: <?php echo $usagePercent; ?>%"></div>
                                    </div>
                                    <small class="usage-text">
                                        Đã sử dụng: <?php echo $voucher['used_count']; ?>/<?php echo $voucher['quantity']; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="no-vouchers text-center py-5">
                    <i class="fas fa-ticket-alt fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">Hiện tại chưa có voucher nào</h4>
                    <p class="text-muted">Hãy quay lại sau để không bỏ lỡ các ưu đãi hấp dẫn!</p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Newsletter Subscription -->
            <div class="newsletter-section mt-5">
                <div class="newsletter-card">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <h3><i class="fas fa-bell"></i> Đăng Ký Nhận Thông Báo Khuyến Mãi</h3>
                            <p>Nhận ngay thông tin về các chương trình khuyến mãi mới nhất và voucher độc quyền!</p>
                        </div>
                        <div class="col-lg-4">
                            <?php if ($user): ?>
                            <div class="subscribed-status">
                                <i class="fas fa-check-circle text-success"></i>
                                <span>Đã đăng ký với email: <?php echo htmlspecialchars($user->email); ?></span>
                            </div>
                            <?php else: ?>
                            <a href="/DoAn_BookStore/view/auth/login.php" class="btn btn-warning btn-lg">
                                <i class="fas fa-sign-in-alt"></i> Đăng Nhập Để Nhận Thông Báo
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
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
            <div class="toast-body">
                <!-- Message will be inserted here -->
            </div>
        </div>
        
        <div id="errorToast" class="toast" role="alert">
            <div class="toast-header">
                <i class="fas fa-exclamation-circle text-danger me-2"></i>
                <strong class="me-auto">Lỗi</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">
                <!-- Message will be inserted here -->
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Countdown Timer
        function initCountdown() {
            const countdownElement = document.querySelector('.countdown-timer');
            if (!countdownElement) return;
            
            const endDate = new Date(countdownElement.dataset.endDate).getTime();
            
            function updateCountdown() {
                const now = new Date().getTime();
                const distance = endDate - now;
                
                if (distance < 0) {
                    countdownElement.innerHTML = '<span class="expired">Đã hết hạn</span>';
                    return;
                }
                
                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                document.getElementById('days').textContent = days.toString().padStart(2, '0');
                document.getElementById('hours').textContent = hours.toString().padStart(2, '0');
                document.getElementById('minutes').textContent = minutes.toString().padStart(2, '0');
                document.getElementById('seconds').textContent = seconds.toString().padStart(2, '0');
            }
            
            updateCountdown();
            setInterval(updateCountdown, 1000);
        }
        
        // Copy voucher code to clipboard
        function copyVoucherCode(code) {
            navigator.clipboard.writeText(code).then(() => {
                showToast('success', `Đã sao chép mã voucher: ${code}`);
            }).catch(() => {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = code;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                showToast('success', `Đã sao chép mã voucher: ${code}`);
            });
        }
        
        // Use voucher now - redirect to shopping page with voucher code
        function useVoucherNow(code) {
            // Store voucher code in session storage
            sessionStorage.setItem('selectedVoucher', code);
            showToast('success', `Đã chọn voucher: ${code}. Chuyển đến trang mua sắm...`);
            
            // Redirect to books page after short delay
            setTimeout(() => {
                window.location.href = '/DoAn_BookStore/view/books/books.php?voucher=' + encodeURIComponent(code);
            }, 1500);
        }
        
        // Scroll to vouchers section
        function scrollToVouchers() {
            document.getElementById('vouchersSection').scrollIntoView({
                behavior: 'smooth'
            });
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
        
        // Voucher checker form
        document.getElementById('voucherCheckerForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const voucherCode = document.getElementById('voucherCode').value.trim();
            const orderAmount = parseFloat(document.getElementById('orderAmount').value) || 0;
            
            if (!voucherCode) {
                showToast('error', 'Vui lòng nhập mã voucher');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'check_voucher');
            formData.append('voucher_code', voucherCode);
            formData.append('order_amount', orderAmount);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const resultDiv = document.getElementById('voucherResult');
                resultDiv.style.display = 'block';
                
                if (data.valid) {
                    const discountAmount = data.discount_amount || 0;
                    const finalAmount = Math.max(0, orderAmount - discountAmount);
                    
                    resultDiv.innerHTML = `
                        <div class="alert alert-success">
                            <h5><i class="fas fa-check-circle"></i> ${data.message}</h5>
                            <div class="voucher-result-details mt-3">
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Thông tin voucher:</strong><br>
                                        <span class="text-primary">${data.voucher.name}</span><br>
                                        <small class="text-muted">${data.voucher.description}</small>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Chi tiết giảm giá:</strong><br>
                                        Giá trị đơn hàng: ${orderAmount.toLocaleString()} VNĐ<br>
                                        Giảm giá: ${discountAmount.toLocaleString()} VNĐ<br>
                                        <span class="text-success"><strong>Còn lại: ${finalAmount.toLocaleString()} VNĐ</strong></span>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <button class="btn btn-success" onclick="useVoucherNow('${voucherCode}')">
                                        <i class="fas fa-shopping-cart"></i> Sử dụng voucher này
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <h5><i class="fas fa-times-circle"></i> ${data.message}</h5>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', 'Có lỗi xảy ra khi kiểm tra voucher');
            });
        });
        
        // Initialize countdown when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initCountdown();
        });
    </script>
</body>
</html>