<?php
session_start();
require_once __DIR__ . '/../../model/Database.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'Vui lòng đăng nhập để xem hồ sơ cá nhân';
    header('Location: /DoAn_BookStore/view/auth/login.php');
    exit;
}

$database = new Database();
$errors = [];
$success = '';

// Lấy thông tin user hiện tại
$userId = $_SESSION['user_id'];
$user = $database->fetch("SELECT * FROM users WHERE id = :id", ['id' => $userId]);

if (!$user) {
    $_SESSION['error'] = 'Không tìm thấy thông tin người dùng';
    header('Location: /DoAn_BookStore/');
    exit;
}

// Xử lý form cập nhật thông tin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        // Cập nhật thông tin cá nhân
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');

        // Validate
        if (empty($name)) {
            $errors['name'] = 'Họ tên không được để trống';
        }

        if (empty($email)) {
            $errors['email'] = 'Email không được để trống';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email không đúng định dạng';
        } elseif ($database->emailExists($email, $userId)) {
            $errors['email'] = 'Email này đã được sử dụng';
        }

        if (!empty($phone) && !preg_match('/^[0-9]{10,11}$/', $phone)) {
            $errors['phone'] = 'Số điện thoại phải có 10-11 chữ số';
        }

        // Cập nhật nếu không có lỗi - sử dụng method của Database class
        if (empty($errors)) {
            try {
                $updateData = [
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'address' => $address,
                ];

                $result = $database->update(
                    'users',
                    $updateData,
                    'id = :id',
                    ['id' => $userId]
                );

                if ($result) {
                    // Cập nhật session
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;

                    // Lấy lại thông tin user mới
                    $user = $database->fetch("SELECT * FROM users WHERE id = :id", ['id' => $userId]);

                    $success = 'Cập nhật thông tin thành công!';
                } else {
                    $errors['general'] = 'Có lỗi xảy ra khi cập nhật thông tin';
                }
            } catch (Exception $e) {
                $errors['general'] = 'Có lỗi xảy ra: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'change_password') {
        // Đổi mật khẩu - sử dụng method của Database class
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validate
        if (empty($currentPassword)) {
            $errors['current_password'] = 'Vui lòng nhập mật khẩu hiện tại';
        }

        if (empty($newPassword)) {
            $errors['new_password'] = 'Vui lòng nhập mật khẩu mới';
        } else {
            $passwordValidation = $database->validatePasswordStrength($newPassword);
            if (!$passwordValidation['valid']) {
                $errors['new_password'] = implode('<br>', $passwordValidation['errors']);
            }
        }

        if (empty($confirmPassword)) {
            $errors['confirm_password'] = 'Vui lòng xác nhận mật khẩu mới';
        } elseif ($newPassword !== $confirmPassword) {
            $errors['confirm_password'] = 'Mật khẩu xác nhận không khớp';
        }

        // Đổi mật khẩu nếu không có lỗi - sử dụng method của Database class
        if (empty($errors)) {
            $result = $database->changeUserPassword($userId, $currentPassword, $newPassword);

            if ($result['success']) {
                $success = $result['message'];
            } else {
                $errors['general'] = $result['message'];
            }
        }
    }
}

$stats = [
    'orders_count' => $database->count('orders', 'user_id = :user_id', ['user_id' => $userId]),
    'wishlist_count' => $database->count('wishlist', 'user_id = :user_id', ['user_id' => $userId]),
    'reviews_count' => $database->count('reviews', 'user_id = :user_id', ['user_id' => $userId]),
];
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hồ sơ cá nhân - BookStore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/DoAn_BookStore/view/profile/profile.css">
</head>

<body>
    <!-- Include Navigation -->
    <?php include_once __DIR__ . '/../navigation/navigation.php'; ?>

    <!-- Profile Header -->
    <div class="profile-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-auto">
                    <a href="/DoAn_BookStore/" class="back-link">
                        <i class="fas fa-arrow-left"></i>
                        <span>Về trang chủ</span>
                    </a>
                </div>
            </div>
            <div class="row justify-content-center text-center mt-3">
                <div class="col-md-8">
                    <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTIwIiBoZWlnaHQ9IjEyMCIgdmlld0JveD0iMCAwIDEyMCAxMjAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxjaXJjbGUgY3g9IjYwIiBjeT0iNjAiIHI9IjYwIiBmaWxsPSIjZjhmOWZhIi8+CjxwYXRoIGQ9Ik02MCAzMEM1MS43IDMwIDQ1IDM2LjcgNDUgNDVDNDUgNTMuMyA1MS43IDYwIDYwIDYwQzY4LjMgNjAgNzUgNTMuMyA3NSA0NUM3NSAzNi43IDY4LjMgMzAgNjAgMzBaTTYwIDc1QzMwIDc1IDMwIDg1IDMwIDg1VjkwSDkwVjg1QzkwIDg1IDkwIDc1IDYwIDc1WiIgZmlsbD0iIzY2N2VlYSIvPgo8L3N2Zz4K"
                        alt="Avatar" class="profile-avatar">
                    <h2 class="mb-2"><?php echo htmlspecialchars($user['name']); ?></h2>
                    <p class="mb-1">
                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?>
                    </p>
                    <p class="mb-1">
                        <i class="fas fa-user-tag"></i>
                        <span class="badge bg-light text-dark"><?php echo ucfirst($user['permission']); ?></span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="container mb-5">
        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['orders_count']; ?></div>
                    <div class="stats-label">Đơn hàng</div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['wishlist_count']; ?></div>
                    <div class="stats-label">Yêu thích</div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['reviews_count']; ?></div>
                    <div class="stats-label">Đánh giá</div>
                </div>
            </div>
        </div>

        <!-- Profile Content -->
        <div class="profile-card">
            <!-- Messages -->
            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $errors['general']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Navigation Tabs -->
            <ul class="nav nav-pills mb-4" id="profileTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="info-tab" data-bs-toggle="pill" data-bs-target="#info"
                        type="button" role="tab">
                        <i class="fas fa-user"></i> Thông tin cá nhân
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="password-tab" data-bs-toggle="pill" data-bs-target="#password"
                        type="button" role="tab">
                        <i class="fas fa-lock"></i> Đổi mật khẩu
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="activity-tab" data-bs-toggle="pill" data-bs-target="#activity"
                        type="button" role="tab">
                        <i class="fas fa-history"></i> Hoạt động
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="profileTabsContent">
                <!-- Personal Information Tab -->
                <div class="tab-pane fade show active" id="info" role="tabpanel">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-floating">
                                    <input type="text"
                                        class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>"
                                        id="name" name="name" placeholder="Họ tên"
                                        value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                    <label for="name"><i class="fas fa-user"></i> Họ tên</label>
                                    <?php if (isset($errors['name'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['name']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <div class="form-floating">
                                    <input type="email"
                                        class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                                        id="email" name="email" placeholder="Email"
                                        value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    <label for="email"><i class="fas fa-envelope"></i> Email</label>
                                    <?php if (isset($errors['email'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-floating">
                                    <input type="tel"
                                        class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>"
                                        id="phone" name="phone" placeholder="Số điện thoại"
                                        value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                    <label for="phone"><i class="fas fa-phone"></i> Số điện thoại</label>
                                    <?php if (isset($errors['phone'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['phone']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="username"
                                        value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                                    <label for="username"><i class="fas fa-at"></i> Tên đăng nhập</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-floating">
                                <textarea class="form-control" id="address" name="address" placeholder="Địa chỉ"
                                    style="height: 100px"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                                <label for="address"><i class="fas fa-map-marker-alt"></i> Địa chỉ</label>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-gradient">
                                <i class="fas fa-save"></i> Cập nhật thông tin
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Change Password Tab -->
                <div class="tab-pane fade" id="password" role="tabpanel">
                    <form method="POST" action="" id="passwordForm">
                        <input type="hidden" name="action" value="change_password">

                        <div class="mb-3">
                            <div class="form-floating">
                                <input type="password"
                                    class="form-control <?php echo isset($errors['current_password']) ? 'is-invalid' : ''; ?>"
                                    id="current_password" name="current_password" placeholder="Mật khẩu hiện tại"
                                    required>
                                <label for="current_password"><i class="fas fa-lock"></i> Mật khẩu hiện tại</label>
                                <?php if (isset($errors['current_password'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['current_password']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-floating">
                                <input type="password"
                                    class="form-control <?php echo isset($errors['new_password']) ? 'is-invalid' : ''; ?>"
                                    id="new_password" name="new_password" placeholder="Mật khẩu mới" required>
                                <label for="new_password"><i class="fas fa-key"></i> Mật khẩu mới</label>
                                <?php if (isset($errors['new_password'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['new_password']; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="password-strength">
                                <div class="strength-bar">
                                    <div class="strength-fill" id="strengthFill"></div>
                                </div>
                                <small class="text-muted" id="strengthText">Độ mạnh mật khẩu</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-floating">
                                <input type="password"
                                    class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>"
                                    id="confirm_password" name="confirm_password" placeholder="Xác nhận mật khẩu mới"
                                    required>
                                <label for="confirm_password"><i class="fas fa-check"></i> Xác nhận mật khẩu mới</label>
                                <?php if (isset($errors['confirm_password'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['confirm_password']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-gradient">
                                <i class="fas fa-shield-alt"></i> Đổi mật khẩu
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Activity Tab -->
                <div class="tab-pane fade" id="activity" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <h6 class="card-title"><i class="fas fa-calendar-plus text-primary"></i> Ngày tham
                                        gia</h6>
                                    <p class="card-text">
                                        <?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <h6 class="card-title"><i class="fas fa-sign-in-alt text-success"></i> Đăng nhập
                                        cuối</h6>
                                    <p class="card-text">
                                        <?php
                                        if ($user['last_login']) {
                                            echo date('d/m/Y H:i', strtotime($user['last_login']));
                                        } else {
                                            echo 'Chưa có thông tin';
                                        }
                                        ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <h6 class="card-title"><i class="fas fa-edit text-warning"></i> Cập nhật cuối</h6>
                                    <p class="card-text">
                                        <?php
                                        if ($user['updated_at']) {
                                            echo date('d/m/Y H:i', strtotime($user['updated_at']));
                                        } else {
                                            echo 'Chưa có cập nhật';
                                        }
                                        ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <h6 class="card-title"><i class="fas fa-user-shield text-info"></i> Quyền hạn</h6>
                                    <p class="card-text">
                                        <span
                                            class="badge bg-<?php echo $user['permission'] === 'admin' ? 'danger' : 'primary'; ?>">
                                            <?php echo ucfirst($user['permission']); ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Password strength checker
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');

            function checkPasswordStrength(password) {
                let strength = 0;
                let text = '';
                let className = '';

                if (password.length >= 6) strength++;
                if (password.match(/[a-z]/)) strength++;
                if (password.match(/[A-Z]/)) strength++;
                if (password.match(/[0-9]/)) strength++;
                if (password.match(/[^a-zA-Z0-9]/)) strength++;

                switch (strength) {
                    case 0:
                    case 1:
                        text = 'Rất yếu';
                        className = 'strength-weak';
                        break;
                    case 2:
                        text = 'Yếu';
                        className = 'strength-fair';
                        break;
                    case 3:
                        text = 'Trung bình';
                        className = 'strength-good';
                        break;
                    case 4:
                    case 5:
                        text = 'Mạnh';
                        className = 'strength-strong';
                        break;
                }

                strengthFill.className = 'strength-fill ' + className;
                strengthText.textContent = text;
            }

            newPasswordInput.addEventListener('input', function () {
                checkPasswordStrength(this.value);
            });

            // Confirm password validation
            confirmPasswordInput.addEventListener('input', function () {
                if (this.value !== newPasswordInput.value) {
                    this.setCustomValidity('Mật khẩu không khớp');
                } else {
                    this.setCustomValidity('');
                }
            });

            // Form validation
            document.getElementById('passwordForm').addEventListener('submit', function (e) {
                if (newPasswordInput.value !== confirmPasswordInput.value) {
                    e.preventDefault();
                    confirmPasswordInput.classList.add('is-invalid');

                    // Show error message
                    let feedback = confirmPasswordInput.nextElementSibling;
                    if (!feedback || !feedback.classList.contains('invalid-feedback')) {
                        feedback = document.createElement('div');
                        feedback.className = 'invalid-feedback';
                        confirmPasswordInput.parentNode.appendChild(feedback);
                    }
                    feedback.textContent = 'Mật khẩu xác nhận không khớp';
                }
            });

            // Auto-hide alerts
            setTimeout(function () {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function (alert) {
                    if (alert && bootstrap.Alert) {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }
                });
            }, 5000);

            // Handle tab switching from URL hash
            const hash = window.location.hash;
            if (hash) {
                const tabTrigger = document.querySelector(`[data-bs-target="${hash}"]`);
                if (tabTrigger) {
                    bootstrap.Tab.getOrCreateInstance(tabTrigger).show();
                }
            }

            // Update URL hash when tab changes
            document.querySelectorAll('[data-bs-toggle="pill"]').forEach(function (tabEl) {
                tabEl.addEventListener('shown.bs.tab', function (event) {
                    window.location.hash = event.target.getAttribute('data-bs-target');
                });
            });
        });
    </script>
</body>

</html>