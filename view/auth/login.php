<?php
session_start();
require_once __DIR__ . '/../../model/Database.php';
require_once __DIR__ . '/../../model/UserModel.php';

// Nếu người dùng đã đăng nhập, chuyển hướng đến trang chủ
if (isset($_SESSION['user_id'])) {
    header('Location: /DoAn_BookStore/');
    exit;
}

$database = new Database();
$errors = [];
$username = '';

// Xử lý form đăng nhập
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) ? true : false;

    // Validate username
    if (empty($username)) {
        $errors['username'] = 'Tên đăng nhập hoặc email không được để trống';
    }

    // Validate password
    if (empty($password)) {
        $errors['password'] = 'Mật khẩu không được để trống';
    }

    // Nếu không có lỗi, tiến hành đăng nhập
    if (empty($errors)) {
        try {
            // Sử dụng hàm authenticateUser từ Database class với SHA256
            $userData = $database->authenticateUser($username, $password);

            if ($userData) {
                // Đăng nhập thành công
                $_SESSION['user_id'] = $userData['id'];
                $_SESSION['username'] = $userData['username'];
                $_SESSION['user_name'] = $userData['name'];
                $_SESSION['user_email'] = $userData['email'];
                $_SESSION['user_permission'] = $userData['permission'];

                // Set admin session if user is admin
                if ($userData['permission'] === 'admin') {
                    $_SESSION['admin'] = true;
                }

                // Lưu cookie nếu "Remember me" được chọn
                if ($remember) {
                    setcookie(
                        'remember_user',
                        $userData['username'],
                        time() + (30 * 24 * 60 * 60),
                        '/',
                        '',
                        false, // HTTPS only in production
                        true   // HttpOnly
                    );
                }

                $_SESSION['success'] = 'Đăng nhập thành công! Chào mừng ' . $userData['name'];

                // Determine redirect location
                $redirect = '/DoAn_BookStore/';

                // Check if there's a specific redirect after login
                if (isset($_SESSION['redirect_after_login'])) {
                    $redirect = $_SESSION['redirect_after_login'];
                    unset($_SESSION['redirect_after_login']);
                } elseif ($userData['permission'] === 'admin') {
                    // Redirect admin to dashboard
                    $redirect = '/DoAn_BookStore/?controller=admin&action=dashboard';
                }

                header('Location: ' . $redirect);
                exit;
            } else {
                $errors['login'] = 'Tên đăng nhập hoặc mật khẩu không đúng';
            }
        } catch (Exception $e) {
            $errors['login'] = 'Có lỗi xảy ra: ' . $e->getMessage();
            error_log('Login error: ' . $e->getMessage());
        }
    }

    // If there are errors, store them in session
    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', $errors);
    }
}

// Check remember me cookie
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_user'])) {
    $userData = $database->fetch(
        "SELECT * FROM users WHERE username = :username",
        ['username' => $_COOKIE['remember_user']]
    );

    if ($userData) {
        // Auto login user
        $_SESSION['user_id'] = $userData['id'];
        $_SESSION['username'] = $userData['username'];
        $_SESSION['user_name'] = $userData['name'];
        $_SESSION['user_email'] = $userData['email'];
        $_SESSION['user_permission'] = $userData['permission'];

        if ($userData['permission'] === 'admin') {
            $_SESSION['admin'] = true;
        }

        header('Location: /DoAn_BookStore/');
        exit;
    } else {
        // Invalid cookie, remove it
        setcookie('remember_user', '', time() - 3600, '/', '', false, true);
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - BookStore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/DoAn_BookStore/view/auth/login.css">
</head>

<body>
    <div class="back-to-home">
        <a href="/DoAn_BookStore/">
            <i class="fas fa-arrow-left"></i>
            <span>Về trang chủ</span>
        </a>
    </div>

    <div class="main-container">
        <div class="login-container">
            <div class="login-header">
                <div class="logo">
                    <i class="fas fa-book-open"></i>
                </div>
                <h2>Đăng nhập</h2>
                <p>Chào mừng trở lại với BookStore</p>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger" id="error-alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $_SESSION['error'];
                    unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success" id="success-alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $_SESSION['success'];
                    unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm">
                <div class="form-floating">
                    <input type="text" class="form-control" id="username" name="username"
                        placeholder="Tên đăng nhập hoặc email" value="<?php echo htmlspecialchars($username); ?>"
                        required>
                    <label for="username">
                        <i class="fas fa-user me-2"></i>Tên đăng nhập hoặc Email
                    </label>
                </div>

                <!-- Fixed password field structure -->
                <div class="form-floating password-wrapper">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Mật khẩu"
                        required>
                    <label for="password">
                        <i class="fas fa-lock me-2"></i>Mật khẩu
                    </label>
                    <span class="password-toggle" onclick="togglePassword()">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </span>
                </div>

                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                    <label class="form-check-label" for="remember">
                        Ghi nhớ đăng nhập
                    </label>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-login" id="loginBtn">
                        <span class="btn-text">
                            <i class="fas fa-sign-in-alt me-2"></i>Đăng nhập
                        </span>
                        <span class="loading">
                            <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                            Đang đăng nhập...
                        </span>
                    </button>
                </div>

                <div class="divider">
                    <span>hoặc</span>
                </div>

                <div class="register-link">
                    Chưa có tài khoản?
                    <a href="/DoAn_BookStore/view/auth/register.php">
                        Đăng ký ngay
                    </a>
                </div>
            </form>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        function fillDemoAccount(username, password) {
            document.getElementById('username').value = username;
            document.getElementById('password').value = password;

            // Remove any previous validation states
            document.getElementById('username').classList.remove('is-invalid');
            document.getElementById('password').classList.remove('is-invalid');
        }

        // Form handling
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('loginForm');
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            const loginBtn = document.getElementById('loginBtn');

            // Form submission with loading state
            form.addEventListener('submit', function (e) {
                let isValid = true;

                // Remove previous error styling
                usernameInput.classList.remove('is-invalid');
                passwordInput.classList.remove('is-invalid');

                // Validate username
                if (usernameInput.value.trim() === '') {
                    usernameInput.classList.add('is-invalid');
                    isValid = false;
                }

                // Validate password
                if (passwordInput.value.trim() === '') {
                    passwordInput.classList.add('is-invalid');
                    isValid = false;
                }

                if (!isValid) {
                    e.preventDefault();
                    showAlert('Vui lòng điền đầy đủ thông tin đăng nhập!', 'danger');
                    return;
                }

                // Show loading state
                showLoading(true);
            });

            // Remove error styling when user types
            usernameInput.addEventListener('input', function () {
                this.classList.remove('is-invalid');
            });

            passwordInput.addEventListener('input', function () {
                this.classList.remove('is-invalid');
            });

            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function (alert) {
                setTimeout(function () {
                    if (alert && alert.parentNode) {
                        alert.style.opacity = '0';
                        alert.style.transition = 'opacity 0.3s ease';
                        setTimeout(function () {
                            if (alert && alert.parentNode) {
                                alert.remove();
                            }
                        }, 300);
                    }
                }, 5000);
            });

            // Handle form errors (reset loading state)
            if (<?php echo !empty($errors) ? 'true' : 'false'; ?>) {
                showLoading(false);
            }
        });

        function showLoading(show) {
            const btnText = document.querySelector('.btn-text');
            const loading = document.querySelector('.loading');
            const loginBtn = document.getElementById('loginBtn');

            if (show) {
                btnText.style.display = 'none';
                loading.style.display = 'inline-block';
                loginBtn.disabled = true;
            } else {
                btnText.style.display = 'inline-block';
                loading.style.display = 'none';
                loginBtn.disabled = false;
            }
        }

        function showAlert(message, type) {
            // Remove existing alerts
            const existingAlerts = document.querySelectorAll('.alert');
            existingAlerts.forEach(alert => alert.remove());

            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.innerHTML = `<i class="fas fa-exclamation-circle me-2"></i>${message}`;

            const form = document.getElementById('loginForm');
            form.parentNode.insertBefore(alertDiv, form);

            // Auto remove after 3 seconds
            setTimeout(() => {
                if (alertDiv && alertDiv.parentNode) {
                    alertDiv.style.opacity = '0';
                    alertDiv.style.transition = 'opacity 0.3s ease';
                    setTimeout(() => {
                        if (alertDiv && alertDiv.parentNode) {
                            if (alertDiv && alertDiv.parentNode) {
                                alertDiv.remove(); alertDiv.remove();









</html ></body >    </script> } }, 3000); } }, 300); } }
    }, 300);
    }
    }, 3000);
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function (e) {
    // Alt + D for demo admin account
    if (e.altKey && e.key.toLowerCase() === 'd') {
    e.preventDefault();
    fillDemoAccount('admin', 'admin123');
    }
    // Alt + U for demo user account
    if (e.altKey && e.key.toLowerCase() === 'u') {
    e.preventDefault();
    fillDemoAccount('user', 'user123');
    }
    });
    </script>
</body>

</html>