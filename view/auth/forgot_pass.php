<?php
session_start();
require_once __DIR__ . '/../../model/Database.php';
require_once __DIR__ . '/../../vendor/autoload.php'; // Nếu sử dụng PHPMailer qua Composer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$database = new Database();
$message = '';
$messageType = '';
$step = isset($_GET['step']) ? $_GET['step'] : 1;

// Load environment variables
function loadEnv()
{
    $envFile = __DIR__ . '/../../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                list($key, $value) = explode('=', $line, 2);
                $_ENV[trim($key)] = trim($value);
            }
        }
    }
}

// Send email using SMTP
function sendOTPEmail($toEmail, $otp, $isResend = false)
{
    loadEnv();

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['MAIL_USERNAME'] ?? '';
        $mail->Password = $_ENV['MAIL_PASSWORD'] ?? '';
        $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'] ?? PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $_ENV['MAIL_PORT'] ?? 587;
        $mail->CharSet = 'UTF-8';

        // Recipients
        $mail->setFrom(
            $_ENV['MAIL_FROM_ADDRESS'] ?? 'bookstore.sp1@gmail.com',
            $_ENV['MAIL_FROM_NAME'] ?? 'BookStore'
        );
        $mail->addAddress($toEmail);
        $mail->addReplyTo(
            $_ENV['MAIL_FROM_ADDRESS'] ?? 'bookstore.sp1@gmail.com',
            $_ENV['MAIL_FROM_NAME'] ?? 'BookStore'
        );

        // Content
        $mail->isHTML(true);
        $mail->Subject = $isResend ? 'Mã OTP mới - Đặt lại mật khẩu BookStore' : 'Mã OTP đặt lại mật khẩu - BookStore';

        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .otp-box { background: white; border: 2px dashed #007bff; padding: 20px; margin: 20px 0; text-align: center; border-radius: 10px; }
                .otp-code { font-size: 32px; font-weight: bold; color: #007bff; letter-spacing: 5px; margin: 10px 0; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>📚 BookStore</h1>
                    <h2>" . ($isResend ? 'Mã OTP Mới' : 'Đặt Lại Mật Khẩu') . "</h2>
                </div>
                
                <div class='content'>
                    <p>Xin chào,</p>
                    
                    <p>Bạn đã yêu cầu " . ($isResend ? 'gửi lại mã OTP' : 'đặt lại mật khẩu') . " cho tài khoản BookStore của mình.</p>
                    
                    <div class='otp-box'>
                        <p><strong>Mã OTP của bạn là:</strong></p>
                        <div class='otp-code'>{$otp}</div>
                        <p><small>Vui lòng nhập mã này để tiếp tục</small></p>
                    </div>
                    
                    <div class='warning'>
                        <p><strong>⚠️ Lưu ý quan trọng:</strong></p>
                        <ul>
                            <li>Mã OTP này chỉ có hiệu lực trong <strong>5 phút</strong></li>
                            <li>Không chia sẻ mã này với bất kỳ ai</li>
                            <li>Nếu bạn không yêu cầu đặt lại mật khẩu, vui lòng bỏ qua email này</li>
                        </ul>
                    </div>
                    
                    <p>Nếu bạn gặp khó khăn, vui lòng liên hệ với chúng tôi qua email này hoặc hotline: <strong>1900-123-456</strong></p>
                </div>
                
                <div class='footer'>
                    <p>Trân trọng,<br><strong>Đội ngũ BookStore</strong></p>
                    <p><small>Email này được gửi tự động, vui lòng không trả lời.</small></p>
                </div>
            </div>
        </body>
        </html>";

        $mail->AltBody = "Mã OTP đặt lại mật khẩu BookStore: {$otp}. Mã này có hiệu lực trong 5 phút.";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Email sending failed: {$mail->ErrorInfo}");
        return false;
    }
}

// Xử lý form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_otp'])) {
        // Bước 1: Gửi OTP
        $email = trim($_POST['email']);

        if (empty($email)) {
            $message = 'Vui lòng nhập địa chỉ email';
            $messageType = 'danger';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Địa chỉ email không hợp lệ';
            $messageType = 'danger';
        } else {
            // Kiểm tra email có tồn tại trong hệ thống không
            $user = $database->getUserByUsernameOrEmail($email);

            if ($user && $user->email === $email) {
                // Tạo OTP 5 số
                $otp = sprintf('%05d', mt_rand(0, 99999));

                // Lưu OTP vào session với thời gian hết hạn
                $_SESSION['reset_otp'] = $otp;
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_user_id'] = $user->user_id;
                $_SESSION['otp_expires'] = time() + 300; // 5 phút

                // Gửi email OTP qua SMTP
                if (sendOTPEmail($email, $otp)) {
                    $message = 'Mã OTP đã được gửi đến email của bạn. Vui lòng kiểm tra hộp thư (kể cả thư rác).';
                    $messageType = 'success';
                    $step = 2;
                } else {
                    $message = 'Không thể gửi email. Vui lòng kiểm tra địa chỉ email và thử lại sau.';
                    $messageType = 'danger';
                }
            } else {
                $message = 'Email không tồn tại trong hệ thống';
                $messageType = 'danger';
            }
        }
    } elseif (isset($_POST['verify_otp'])) {
        // Bước 2: Xác thực OTP
        $inputOtp = trim($_POST['otp']);

        if (empty($inputOtp)) {
            $message = 'Vui lòng nhập mã OTP';
            $messageType = 'danger';
            $step = 2;
        } elseif (!isset($_SESSION['reset_otp']) || !isset($_SESSION['otp_expires'])) {
            $message = 'Phiên làm việc đã hết hạn. Vui lòng thử lại.';
            $messageType = 'danger';
            $step = 1;
        } elseif (time() > $_SESSION['otp_expires']) {
            $message = 'Mã OTP đã hết hạn. Vui lòng yêu cầu mã mới.';
            $messageType = 'danger';
            unset($_SESSION['reset_otp'], $_SESSION['otp_expires']);
            $step = 1;
        } elseif ($inputOtp !== $_SESSION['reset_otp']) {
            $message = 'Mã OTP không chính xác';
            $messageType = 'danger';
            $step = 2;
        } else {
            $message = 'Mã OTP chính xác. Vui lòng đặt mật khẩu mới.';
            $messageType = 'success';
            $step = 3;
        }
    } elseif (isset($_POST['reset_password'])) {
        // Bước 3: Đặt lại mật khẩu
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];

        if (empty($newPassword) || empty($confirmPassword)) {
            $message = 'Vui lòng điền đầy đủ thông tin';
            $messageType = 'danger';
            $step = 3;
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'Mật khẩu xác nhận không khớp';
            $messageType = 'danger';
            $step = 3;
        } elseif (strlen($newPassword) < 6) {
            $message = 'Mật khẩu phải có ít nhất 6 ký tự';
            $messageType = 'danger';
            $step = 3;
        } elseif (!isset($_SESSION['reset_user_id']) || !isset($_SESSION['reset_email'])) {
            $message = 'Phiên làm việc đã hết hạn. Vui lòng thử lại.';
            $messageType = 'danger';
            $step = 1;
        } else {
            // Cập nhật mật khẩu
            $userId = $_SESSION['reset_user_id'];
            $user = $database->getUserById($userId);

            if ($user) {
                $result = $database->updateUserPassword($userId, $newPassword, $user->username);

                if ($result) {
                    $message = 'Đặt lại mật khẩu thành công! Đang chuyển hướng đến trang đăng nhập...';
                    $messageType = 'success';

                    // Xóa session
                    unset($_SESSION['reset_otp'], $_SESSION['reset_email'], $_SESSION['reset_user_id'], $_SESSION['otp_expires']);

                    // Chuyển hướng sau 3 giây
                    echo "<meta http-equiv='refresh' content='3;url=login.php'>";
                } else {
                    $message = 'Có lỗi xảy ra khi đặt lại mật khẩu. Vui lòng thử lại.';
                    $messageType = 'danger';
                    $step = 3;
                }
            } else {
                $message = 'Không tìm thấy thông tin người dùng';
                $messageType = 'danger';
                $step = 1;
            }
        }
    }
}

// Xử lý yêu cầu gửi lại OTP
if (isset($_GET['resend_otp']) && isset($_SESSION['reset_email'])) {
    $email = $_SESSION['reset_email'];
    $user = $database->getUserByUsernameOrEmail($email);

    if ($user) {
        $otp = sprintf('%05d', mt_rand(0, 99999));
        $_SESSION['reset_otp'] = $otp;
        $_SESSION['otp_expires'] = time() + 300;

        if (sendOTPEmail($email, $otp, true)) {
            $message = 'Mã OTP mới đã được gửi đến email của bạn';
            $messageType = 'success';
        } else {
            $message = 'Không thể gửi email. Vui lòng thử lại sau.';
            $messageType = 'danger';
        }
        $step = 2;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quên Mật Khẩu - BookStore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .forgot-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 0;
        }

        .forgot-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
        }

        .forgot-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .forgot-body {
            padding: 2rem;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }

        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
            position: relative;
        }

        .step.active {
            background: #007bff;
            color: white;
        }

        .step.completed {
            background: #28a745;
            color: white;
        }

        .step::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 100%;
            width: 20px;
            height: 2px;
            background: #e9ecef;
            margin-left: 10px;
        }

        .step:last-child::after {
            display: none;
        }

        .step.completed::after {
            background: #28a745;
        }

        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 16px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .otp-input {
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 0.5em;
        }

        .countdown {
            font-size: 14px;
            color: #6c757d;
        }

        .resend-link {
            color: #007bff;
            text-decoration: none;
            cursor: pointer;
        }

        .resend-link:hover {
            text-decoration: underline;
        }

        .password-strength {
            font-size: 12px;
            margin-top: 5px;
        }

        .strength-weak {
            color: #dc3545;
        }

        .strength-medium {
            color: #ffc107;
        }

        .strength-strong {
            color: #28a745;
        }

        .email-info {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
    </style>
</head>

<body>
    <div class="forgot-container">
        <div class="forgot-card">
            <div class="forgot-header">
                <h3 class="mb-0">
                    <i class="fas fa-key me-2"></i>
                    Quên Mật Khẩu
                </h3>
                <p class="mb-0 mt-2 opacity-75">Đặt lại mật khẩu của bạn</p>
            </div>

            <div class="forgot-body">
                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step <?php echo $step >= 1 ? ($step > 1 ? 'completed' : 'active') : ''; ?>">1</div>
                    <div class="step <?php echo $step >= 2 ? ($step > 2 ? 'completed' : 'active') : ''; ?>">2</div>
                    <div class="step <?php echo $step == 3 ? 'active' : ''; ?>">3</div>
                </div>

                <!-- Alert Messages -->
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <i
                            class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($step == 1): ?>
                    <!-- Step 1: Nhập Email -->
                    <div class="text-center mb-4">
                        <h5>Nhập địa chỉ email của bạn</h5>
                        <p class="text-muted small">Chúng tôi sẽ gửi mã OTP đến email này qua hệ thống SMTP</p>
                    </div>

                    <div class="email-info">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-info-circle text-primary me-2"></i>
                            <small>
                                <strong>Lưu ý:</strong> Email sẽ được gửi từ hệ thống SMTP Gmail.
                                Vui lòng kiểm tra cả hộp thư rác nếu không thấy email.
                            </small>
                        </div>
                    </div>

                    <form method="POST">
                        <div class="mb-3">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope me-1"></i>
                                Địa chỉ Email
                            </label>
                            <input type="email" class="form-control" id="email" name="email"
                                placeholder="Nhập email đã đăng ký" required>
                        </div>

                        <div class="d-grid mb-3">
                            <button type="submit" name="send_otp" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>
                                Gửi mã OTP
                            </button>
                        </div>
                    </form>

                <?php elseif ($step == 2): ?>
                    <!-- Step 2: Nhập OTP -->
                    <div class="text-center mb-4">
                        <h5>Nhập mã OTP</h5>
                        <p class="text-muted small">
                            <i class="fas fa-envelope me-1"></i>
                            Mã OTP đã được gửi đến:
                            <strong><?php echo isset($_SESSION['reset_email']) ? $_SESSION['reset_email'] : ''; ?></strong>
                        </p>
                    </div>

                    <div class="email-info">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-lightbulb text-warning me-2 mt-1"></i>
                            <small>
                                <strong>Mẹo:</strong> Nếu không thấy email trong hộp thư chính,
                                hãy kiểm tra thư mục "Spam" hoặc "Thư rác".
                                Email được gửi từ bookstore.sp1@gmail.com.
                            </small>
                        </div>
                    </div>

                    <form method="POST">
                        <div class="mb-3">
                            <label for="otp" class="form-label">
                                <i class="fas fa-shield-alt me-1"></i>
                                Mã OTP (5 số)
                            </label>
                            <input type="text" class="form-control otp-input" id="otp" name="otp" placeholder="00000"
                                maxlength="5" pattern="[0-9]{5}" required>
                        </div>

                        <div class="text-center mb-3">
                            <div class="countdown" id="countdown"></div>
                            <div id="resend-section" style="display: none;">
                                <span class="text-muted">Không nhận được mã? </span>
                                <a href="?resend_otp=1" class="resend-link">
                                    <i class="fas fa-redo me-1"></i>
                                    Gửi lại
                                </a>
                            </div>
                        </div>

                        <div class="d-grid mb-3">
                            <button type="submit" name="verify_otp" class="btn btn-primary">
                                <i class="fas fa-check me-2"></i>
                                Xác thực OTP
                            </button>
                        </div>
                    </form>

                <?php elseif ($step == 3): ?>
                    <!-- Step 3: Đặt lại mật khẩu -->
                    <div class="text-center mb-4">
                        <h5>Đặt mật khẩu mới</h5>
                        <p class="text-muted small">Tạo mật khẩu mạnh để bảo vệ tài khoản</p>
                    </div>

                    <form method="POST">
                        <div class="mb-3">
                            <label for="new_password" class="form-label">
                                <i class="fas fa-lock me-1"></i>
                                Mật khẩu mới
                            </label>
                            <div class="position-relative">
                                <input type="password" class="form-control" id="new_password" name="new_password"
                                    placeholder="Nhập mật khẩu mới" required minlength="6">
                                <button type="button" class="btn btn-outline-secondary position-absolute top-0 end-0"
                                    style="border-top-left-radius: 0; border-bottom-left-radius: 0;"
                                    onclick="togglePassword('new_password', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="password-strength" class="password-strength"></div>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">
                                <i class="fas fa-lock me-1"></i>
                                Xác nhận mật khẩu
                            </label>
                            <div class="position-relative">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                    placeholder="Nhập lại mật khẩu" required>
                                <button type="button" class="btn btn-outline-secondary position-absolute top-0 end-0"
                                    style="border-top-left-radius: 0; border-bottom-left-radius: 0;"
                                    onclick="togglePassword('confirm_password', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="password-match" class="password-strength"></div>
                        </div>

                        <div class="d-grid mb-3">
                            <button type="submit" name="reset_password" class="btn btn-primary">
                                <i class="fas fa-key me-2"></i>
                                Đặt lại mật khẩu
                            </button>
                        </div>
                    </form>
                <?php endif; ?>

                <!-- Back to Login -->
                <div class="text-center">
                    <a href="login.php" class="text-decoration-none">
                        <i class="fas fa-arrow-left me-1"></i>
                        Quay lại đăng nhập
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Countdown timer for OTP
        <?php if ($step == 2 && isset($_SESSION['otp_expires'])): ?>
            let expiryTime = <?php echo $_SESSION['otp_expires']; ?>;

            function updateCountdown() {
                let now = Math.floor(Date.now() / 1000);
                let remaining = expiryTime - now;

                if (remaining <= 0) {
                    document.getElementById('countdown').style.display = 'none';
                    document.getElementById('resend-section').style.display = 'block';
                    return;
                }

                let minutes = Math.floor(remaining / 60);
                let seconds = remaining % 60;

                document.getElementById('countdown').innerHTML =
                    `<i class="fas fa-clock me-1"></i>Mã sẽ hết hạn sau: <strong>${minutes}:${seconds.toString().padStart(2, '0')}</strong>`;
            }

            updateCountdown();
            setInterval(updateCountdown, 1000);
        <?php endif; ?>

        // OTP input formatting
        document.addEventListener('DOMContentLoaded', function () {
            const otpInput = document.getElementById('otp');
            if (otpInput) {
                otpInput.addEventListener('input', function (e) {
                    this.value = this.value.replace(/[^0-9]/g, '');
                });

                otpInput.addEventListener('paste', function (e) {
                    e.preventDefault();
                    let paste = (e.clipboardData || window.clipboardData).getData('text');
                    paste = paste.replace(/[^0-9]/g, '').substring(0, 5);
                    this.value = paste;
                });
            }
        });

        // Password strength checker
        function checkPasswordStrength(password) {
            let strength = 0;
            let feedback = [];

            if (password.length >= 8) strength += 1;
            else feedback.push('Ít nhất 8 ký tự');

            if (/[a-z]/.test(password)) strength += 1;
            else feedback.push('Chữ thường');

            if (/[A-Z]/.test(password)) strength += 1;
            else feedback.push('Chữ hoa');

            if (/[0-9]/.test(password)) strength += 1;
            else feedback.push('Số');

            if (/[^A-Za-z0-9]/.test(password)) strength += 1;
            else feedback.push('Ký tự đặc biệt');

            return { strength, feedback };
        }

        document.addEventListener('DOMContentLoaded', function () {
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const strengthDiv = document.getElementById('password-strength');
            const matchDiv = document.getElementById('password-match');

            if (newPasswordInput) {
                newPasswordInput.addEventListener('input', function () {
                    const result = checkPasswordStrength(this.value);
                    let className, text;

                    if (result.strength <= 2) {
                        className = 'strength-weak';
                        text = 'Yếu: ' + result.feedback.join(', ');
                    } else if (result.strength <= 3) {
                        className = 'strength-medium';
                        text = 'Trung bình: ' + result.feedback.join(', ');
                    } else {
                        className = 'strength-strong';
                        text = 'Mạnh';
                    }

                    strengthDiv.className = 'password-strength ' + className;
                    strengthDiv.textContent = text;
                });
            }

            if (confirmPasswordInput) {
                confirmPasswordInput.addEventListener('input', function () {
                    if (this.value === '') {
                        matchDiv.textContent = '';
                        return;
                    }

                    if (this.value === newPasswordInput.value) {
                        matchDiv.className = 'password-strength strength-strong';
                        matchDiv.textContent = '✓ Mật khẩu khớp';
                    } else {
                        matchDiv.className = 'password-strength strength-weak';
                        matchDiv.textContent = '✗ Mật khẩu không khớp';
                    }
                });
            }
        });

        // Toggle password visibility
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }
    </script>
</body>

</html>