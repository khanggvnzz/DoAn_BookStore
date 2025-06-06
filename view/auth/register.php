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
$formData = [
    'name' => '',
    'email' => '',
    'username' => '',
    'phone' => '',
    'address' => ''
];

// Xử lý form đăng ký
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    // Lưu dữ liệu form để hiển thị lại khi có lỗi
    $formData = [
        'name' => $name,
        'email' => $email,
        'username' => $username,
        'phone' => $phone,
        'address' => $address
    ];

    // Validate name
    if (empty($name)) {
        $errors['name'] = 'Tên không được để trống';
    } elseif (strlen($name) < 2) {
        $errors['name'] = 'Tên phải có ít nhất 2 ký tự';
    } elseif (strlen($name) > 100) {
        $errors['name'] = 'Tên không được vượt quá 100 ký tự';
    }

    // Validate email
    if (empty($email)) {
        $errors['email'] = 'Email không được để trống';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email không hợp lệ';
    } elseif (strlen($email) > 255) {
        $errors['email'] = 'Email không được vượt quá 255 ký tự';
    } elseif ($database->emailExists($email)) {
        $errors['email'] = 'Email đã được sử dụng';
    }

    // Validate username
    if (empty($username)) {
        $errors['username'] = 'Tên đăng nhập không được để trống';
    } elseif (strlen($username) < 3) {
        $errors['username'] = 'Tên đăng nhập phải có ít nhất 3 ký tự';
    } elseif (strlen($username) > 50) {
        $errors['username'] = 'Tên đăng nhập không được vượt quá 50 ký tự';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors['username'] = 'Tên đăng nhập chỉ được chứa chữ cái, số và dấu gạch dưới';
    } elseif ($database->usernameExists($username)) {
        $errors['username'] = 'Tên đăng nhập đã được sử dụng';
    }

    // Validate password using Database method
    if (empty($password)) {
        $errors['password'] = 'Mật khẩu không được để trống';
    } else {
        $passwordValidation = $database->validatePasswordStrength($password);
        if (!$passwordValidation['valid']) {
            $errors['password'] = implode(', ', $passwordValidation['errors']);
        }
    }

    // Validate confirm password
    if (empty($confirmPassword)) {
        $errors['confirm_password'] = 'Vui lòng xác nhận mật khẩu';
    } elseif ($password !== $confirmPassword) {
        $errors['confirm_password'] = 'Mật khẩu xác nhận không khớp';
    }

    // Validate phone (optional)
    if (!empty($phone)) {
        if (!preg_match('/^[0-9+\-\s()]{10,15}$/', $phone)) {
            $errors['phone'] = 'Số điện thoại không hợp lệ';
        } elseif (strlen($phone) > 20) {
            $errors['phone'] = 'Số điện thoại không được vượt quá 20 ký tự';
        }
    }

    // Validate address (optional)
    if (!empty($address) && strlen($address) > 500) {
        $errors['address'] = 'Địa chỉ không được vượt quá 500 ký tự';
    }

    // Nếu không có lỗi, tiến hành đăng ký
    if (empty($errors)) {
        try {
            // Create user data
            $userData = [
                'name' => $name,
                'email' => $email,
                'username' => $username,
                'password' => $password, // Will be hashed in createUserWithSHA256
                'phone' => $phone,
                'address' => $address,
                'permission' => 'user'
            ];

            // Use Database method to create user with SHA256 hash
            $result = $database->createUserWithSHA256($userData);
            var_dump($result); // Debugging line to check the result

            if ($result == 0 or $result) {
                $_SESSION['success'] = 'Đăng ký thành công! Vui lòng đăng nhập để tiếp tục.';
                header('Location: /DoAn_BookStore/view/auth/login.php');
                exit;
            } else {
                $_SESSION['error'] = 'Có lỗi xảy ra khi đăng ký. Vui lòng thử lại!';
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'Có lỗi xảy ra: ' . $e->getMessage();
            error_log('Registration error: ' . $e->getMessage());
        }
    }

    // If there are errors, store them in session
    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', $errors);
    }
}
?>

<?php include __DIR__ . '/../navigation/navigation.php'; ?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký - BookStore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/DoAn_BookStore/view/auth/register.css">
</head>

<body>
    <div class="back-to-home">
        <a href="/DoAn_BookStore/">
            <i class="fas fa-arrow-left"></i> Về trang chủ
        </a>
    </div>

    <div class="main-content">
        <div class="container">
            <div class="register-container">
                <div class="register-header">
                    <i class="fas fa-user-plus"></i>
                    <h2>Đăng ký tài khoản</h2>
                    <p>Tạo tài khoản mới để mua sách</p>
                </div>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger" id="error-alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $_SESSION['error'];
                        unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success" id="success-alert">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $_SESSION['success'];
                        unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="registerForm">
                    <!-- Name -->
                    <div class="mb-3">
                        <label for="name" class="form-label">
                            <i class="fas fa-user"></i> Họ và tên <span class="required">*</span>
                        </label>
                        <input type="text" class="form-control" id="name" name="name" placeholder="Nhập họ và tên"
                            value="<?php echo htmlspecialchars($formData['name']); ?>" required>
                        <div class="invalid-feedback" id="name-error"></div>
                    </div>

                    <!-- Email and Username -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope"></i> Email <span class="required">*</span>
                            </label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="Nhập email"
                                value="<?php echo htmlspecialchars($formData['email']); ?>" required>
                            <div class="invalid-feedback" id="email-error"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="username" class="form-label">
                                <i class="fas fa-at"></i> Tên đăng nhập <span class="required">*</span>
                            </label>
                            <input type="text" class="form-control" id="username" name="username"
                                placeholder="Nhập tên đăng nhập"
                                value="<?php echo htmlspecialchars($formData['username']); ?>" required>
                            <div class="invalid-feedback" id="username-error"></div>
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="mb-3">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock"></i> Mật khẩu <span class="required">*</span>
                        </label>
                        <div class="password-container">
                            <input type="password" class="form-control" id="password" name="password"
                                placeholder="Nhập mật khẩu" required>
                            <span class="password-toggle" onclick="togglePassword('password')">
                                <i class="fas fa-eye" id="toggleIcon1"></i>
                            </span>
                        </div>
                        <div class="password-strength">
                            <div class="strength-meter">
                                <div class="strength-meter-fill" id="strengthMeter"></div>
                            </div>
                            <small id="strengthText" class="text-muted">Nhập mật khẩu để kiểm tra độ mạnh</small>
                        </div>
                        <div class="invalid-feedback" id="password-error"></div>
                    </div>

                    <!-- Confirm Password -->
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">
                            <i class="fas fa-lock"></i> Xác nhận mật khẩu <span class="required">*</span>
                        </label>
                        <div class="password-container">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                placeholder="Nhập lại mật khẩu" required>
                            <span class="password-toggle" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye" id="toggleIcon2"></i>
                            </span>
                        </div>
                        <div class="invalid-feedback" id="confirm-password-error"></div>
                    </div>

                    <!-- Phone and Address -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="phone" class="form-label">
                                <i class="fas fa-phone"></i> Số điện thoại
                            </label>
                            <input type="tel" class="form-control" id="phone" name="phone"
                                placeholder="Nhập số điện thoại"
                                value="<?php echo htmlspecialchars($formData['phone']); ?>">
                            <div class="invalid-feedback" id="phone-error"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="address" class="form-label">
                                <i class="fas fa-map-marker-alt"></i> Địa chỉ
                            </label>
                            <input type="text" class="form-control" id="address" name="address"
                                placeholder="Nhập địa chỉ"
                                value="<?php echo htmlspecialchars($formData['address']); ?>">
                            <div class="invalid-feedback" id="address-error"></div>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-register">
                            <i class="fas fa-user-plus"></i> Đăng ký
                        </button>
                    </div>

                    <div class="login-link text-center">
                        Đã có tài khoản?
                        <a href="/DoAn_BookStore/view/auth/login.php">
                            Đăng nhập ngay
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword(inputId) {
            const passwordInput = document.getElementById(inputId);
            const iconNumber = inputId === 'password' ? '1' : '2';
            const toggleIcon = document.getElementById('toggleIcon' + iconNumber);

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

        // Password strength checker
        function checkPasswordStrength(password) {
            let score = 0;
            const checks = {
                length: password.length >= 6,
                letter: /[A-Za-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[^A-Za-z0-9]/.test(password)
            };

            score += checks.length ? 1 : 0;
            score += checks.letter ? 1 : 0;
            score += checks.number ? 1 : 0;
            score += checks.special ? 1 : 0;

            const strengthMeter = document.getElementById('strengthMeter');
            const strengthText = document.getElementById('strengthText');

            if (password.length === 0) {
                strengthMeter.className = 'strength-meter-fill';
                strengthText.textContent = 'Nhập mật khẩu để kiểm tra độ mạnh';
                strengthText.className = 'text-muted';
                return;
            }

            if (score <= 2) {
                strengthMeter.className = 'strength-meter-fill weak';
                strengthText.textContent = 'Mật khẩu yếu';
                strengthText.className = 'text-danger';
            } else if (score === 3) {
                strengthMeter.className = 'strength-meter-fill medium';
                strengthText.textContent = 'Mật khẩu trung bình';
                strengthText.className = 'text-warning';
            } else {
                strengthMeter.className = 'strength-meter-fill strong';
                strengthText.textContent = 'Mật khẩu mạnh';
                strengthText.className = 'text-success';
            }
        }

        // Real-time validation
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('registerForm');
            const fields = ['name', 'email', 'username', 'password', 'confirm_password', 'phone'];

            // Password strength checking
            document.getElementById('password').addEventListener('input', function () {
                checkPasswordStrength(this.value);
                validateField('password');
            });

            // Confirm password validation
            document.getElementById('confirm_password').addEventListener('input', function () {
                validateField('confirm_password');
            });

            // Add validation to other fields
            fields.forEach(field => {
                const input = document.getElementById(field);
                if (input) {
                    input.addEventListener('blur', () => validateField(field));
                    input.addEventListener('input', () => {
                        if (input.classList.contains('is-invalid')) {
                            validateField(field);
                        }
                    });
                }
            });

            // Form submission
            form.addEventListener('submit', function (e) {
                let isValid = true;

                fields.forEach(field => {
                    if (!validateField(field)) {
                        isValid = false;
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                    showAlert('Vui lòng kiểm tra và sửa các lỗi trong form!', 'danger');
                }
            });

            // Auto-hide alerts
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
        });

        function validateField(fieldName) {
            const input = document.getElementById(fieldName);
            const errorDiv = document.getElementById(fieldName + '-error');
            let isValid = true;
            let errorMessage = '';

            // Clear previous validation state
            input.classList.remove('is-invalid', 'is-valid');

            switch (fieldName) {
                case 'name':
                    if (!input.value.trim()) {
                        errorMessage = 'Tên không được để trống';
                        isValid = false;
                    } else if (input.value.trim().length < 2) {
                        errorMessage = 'Tên phải có ít nhất 2 ký tự';
                        isValid = false;
                    }
                    break;

                case 'email':
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!input.value.trim()) {
                        errorMessage = 'Email không được để trống';
                        isValid = false;
                    } else if (!emailRegex.test(input.value)) {
                        errorMessage = 'Email không hợp lệ';
                        isValid = false;
                    }
                    break;

                case 'username':
                    const usernameRegex = /^[a-zA-Z0-9_]+$/;
                    if (!input.value.trim()) {
                        errorMessage = 'Tên đăng nhập không được để trống';
                        isValid = false;
                    } else if (input.value.length < 3) {
                        errorMessage = 'Tên đăng nhập phải có ít nhất 3 ký tự';
                        isValid = false;
                    } else if (!usernameRegex.test(input.value)) {
                        errorMessage = 'Tên đăng nhập chỉ được chứa chữ cái, số và dấu gạch dưới';
                        isValid = false;
                    }
                    break;

                case 'password':
                    if (!input.value) {
                        errorMessage = 'Mật khẩu không được để trống';
                        isValid = false;
                    } else if (input.value.length < 6) {
                        errorMessage = 'Mật khẩu phải có ít nhất 6 ký tự';
                        isValid = false;
                    } else if (!/[A-Za-z]/.test(input.value)) {
                        errorMessage = 'Mật khẩu phải chứa ít nhất 1 chữ cái';
                        isValid = false;
                    } else if (!/[0-9]/.test(input.value)) {
                        errorMessage = 'Mật khẩu phải chứa ít nhất 1 số';
                        isValid = false;
                    }
                    break;

                case 'confirm_password':
                    const password = document.getElementById('password').value;
                    if (!input.value) {
                        errorMessage = 'Vui lòng xác nhận mật khẩu';
                        isValid = false;
                    } else if (input.value !== password) {
                        errorMessage = 'Mật khẩu xác nhận không khớp';
                        isValid = false;
                    }
                    break;

                case 'phone':
                    if (input.value && !/^[0-9+\-\s()]{10,15}$/.test(input.value)) {
                        errorMessage = 'Số điện thoại không hợp lệ';
                        isValid = false;
                    }
                    break;
            }

            // Apply validation styling
            if (input.value && fieldName !== 'confirm_password') {
                input.classList.add(isValid ? 'is-valid' : 'is-invalid');
            } else if (fieldName === 'confirm_password' && input.value) {
                input.classList.add(isValid ? 'is-valid' : 'is-invalid');
            } else if (!isValid) {
                input.classList.add('is-invalid');
            }

            // Show error message
            if (errorDiv) {
                errorDiv.textContent = errorMessage;
            }

            return isValid;
        }

        function showAlert(message, type) {
            // Remove existing alerts
            const existingAlerts = document.querySelectorAll('.alert');
            existingAlerts.forEach(alert => alert.remove());

            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;

            const form = document.getElementById('registerForm');
            form.parentNode.insertBefore(alertDiv, form);

            // Auto remove after 3 seconds
            setTimeout(() => {
                if (alertDiv && alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 3000);
        }
    </script>
</body>

</html>