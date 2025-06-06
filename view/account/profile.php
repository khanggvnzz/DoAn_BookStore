<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../model/UserModel.php';
require_once __DIR__ . '/../../config/helpers.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    // Lưu trang hiện tại để sau khi đăng nhập quay lại
    $_SESSION['redirect_after_login'] = viewUrl('account/profile.php');
    header('Location: ' . viewUrl('login/login.php'));
    exit;
}

$database = new Database();
$db = $database->connect();
$userModel = new UserModel($db);
$user = $userModel->getUserById($_SESSION['user_id']);

$errors = [];
$success = false;

// Xử lý form cập nhật thông tin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    // Validate name
    if (empty($name)) {
        $errors['name'] = 'Name is required';
    }
    
    // Validate phone
    if (empty($phone)) {
        $errors['phone'] = 'Phone number is required';
    } elseif (!preg_match('/^[0-9]{10,15}$/', $phone)) {
        $errors['phone'] = 'Enter a valid phone number (10-15 digits)';
    }
    
    // Validate address
    if (empty($address)) {
        $errors['address'] = 'Address is required';
    }
    
    // Nếu không có lỗi, cập nhật thông tin
    if (empty($errors)) {
        $userData = [
            'name' => $name,
            'phone' => $phone,
            'address' => $address
        ];
        
        if ($userModel->updateUser($_SESSION['user_id'], $userData)) {
            $success = true;
            // Cập nhật thông tin người dùng
            $user = $userModel->getUserById($_SESSION['user_id']);
        } else {
            $errors['general'] = 'Failed to update profile. Please try again.';
        }
    }
}

// Xử lý form đổi mật khẩu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate current password
    if (empty($currentPassword)) {
        $errors['current_password'] = 'Current password is required';
    } else {
        // Kiểm tra mật khẩu hiện tại
        $salt = $user['username'];
        $hashedPassword = hash('sha256', $currentPassword . $salt);
        
        // Lấy mật khẩu hiện tại từ database
        $currentPasswordInDb = $userModel->getUserPassword($_SESSION['user_id']);
        
        if ($hashedPassword !== $currentPasswordInDb) {
            $errors['current_password'] = 'Current password is incorrect';
        }
    }
    
    // Validate new password
    if (empty($newPassword)) {
        $errors['new_password'] = 'New password is required';
    } elseif (strlen($newPassword) < 8) {
        $errors['new_password'] = 'New password must be at least 8 characters';
    }
    
    // Validate confirm password
    if ($newPassword !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match';
    }
    
    // Nếu không có lỗi, đổi mật khẩu
    if (empty($errors)) {
        if ($userModel->changePassword($_SESSION['user_id'], $newPassword)) {
            $passwordSuccess = true;
        } else {
            $errors['general'] = 'Failed to change password. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - TheBookStore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo viewUrl('css/index.css'); ?>">
    <link rel="stylesheet" href="<?php echo viewUrl('account/profile.css'); ?>">
</head>
<body>
    <?php include __DIR__ . '/../navigation/navigation.php'; ?>
    
    <div class="container py-5">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-3 mb-4">
                <div class="profile-sidebar sticky-top" style="top: 80px;">
                    <h4 class="mb-4">My Account</h4>
                    <div class="nav flex-column nav-pills">
                        <a class="nav-link active" href="<?php echo viewUrl('account/profile.php'); ?>">
                            <i class="bi bi-person"></i> Profile
                        </a>
                        <a class="nav-link" href="<?php echo viewUrl('account/orders.php'); ?>">
                            <i class="bi bi-bag"></i> Orders
                        </a>
                        <a class="nav-link" href="<?php echo viewUrl('account/wishlist.php'); ?>">
                            <i class="bi bi-heart"></i> Wishlist
                        </a>
                        <a class="nav-link" href="<?php echo viewUrl('account/addresses.php'); ?>">
                            <i class="bi bi-geo-alt"></i> Addresses
                        </a>
                        <div class="dropdown-divider my-3"></div>
                        <a class="nav-link text-danger" href="<?php echo viewUrl('login/logout.php'); ?>">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-lg-9">
                <div class="card profile-card">
                    <div class="card-body">
                        <div class="profile-header">
                            <img src="<?php echo viewUrl('images/avatar/default-avatar.png'); ?>" alt="Profile Avatar" class="profile-avatar">
                            <div>
                                <h3><?php echo htmlspecialchars($user['name']); ?></h3>
                                <p class="text-muted mb-0"><?php echo htmlspecialchars($user['email']); ?></p>
                                <p class="mb-0">Member since: <?php echo date('F j, Y', strtotime($user['created_at'] ?? 'now')); ?></p>
                            </div>
                        </div>
                        
                        <!-- Nav tabs -->
                        <ul class="nav nav-tabs" id="profileTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab">Personal Information</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button" role="tab">Change Password</button>
                            </li>
                        </ul>
                        
                        <!-- Tab content -->
                        <div class="tab-content">
                            <!-- Profile Tab -->
                            <div class="tab-pane fade show active" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                                <?php if ($success): ?>
                                    <div class="alert alert-success">
                                        Your profile has been updated successfully!
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (isset($errors['general'])): ?>
                                    <div class="alert alert-danger">
                                        <?php echo $errors['general']; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="POST" action="<?php echo viewUrl('account/profile.php'); ?>">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" 
                                               id="name" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>">
                                        <?php if (isset($errors['name'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['name']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled>
                                        <div class="form-text">Email cannot be changed.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" disabled>
                                        <div class="form-text">Username cannot be changed.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>" 
                                               id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                        <?php if (isset($errors['phone'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['phone']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Address</label>
                                        <textarea class="form-control <?php echo isset($errors['address']) ? 'is-invalid' : ''; ?>" 
                                                  id="address" name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                                        <?php if (isset($errors['address'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['address']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Password Tab -->
                            <div class="tab-pane fade" id="password" role="tabpanel" aria-labelledby="password-tab">
                                <?php if (isset($passwordSuccess) && $passwordSuccess): ?>
                                    <div class="alert alert-success">
                                        Your password has been changed successfully!
                                    </div>
                                <?php endif; ?>
                                
                                <form method="POST" action="<?php echo viewUrl('account/profile.php'); ?>">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control <?php echo isset($errors['current_password']) ? 'is-invalid' : ''; ?>" 
                                                   id="current_password" name="current_password">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password')">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                        <?php if (isset($errors['current_password'])): ?>
                                            <div class="invalid-feedback d-block"><?php echo $errors['current_password']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control <?php echo isset($errors['new_password']) ? 'is-invalid' : ''; ?>" 
                                                   id="new_password" name="new_password">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                        <?php if (isset($errors['new_password'])): ?>
                                            <div class="invalid-feedback d-block"><?php echo $errors['new_password']; ?></div>
                                        <?php endif; ?>
                                        <div class="form-text">Password must be at least 8 characters long.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                                                   id="confirm_password" name="confirm_password">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                        <?php if (isset($errors['confirm_password'])): ?>
                                            <div class="invalid-feedback d-block"><?php echo $errors['confirm_password']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include __DIR__ . '/../footer/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword(inputId) {
            const passwordInput = document.getElementById(inputId);
            const icon = passwordInput.nextElementSibling.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
        
        // Activate tab based on URL hash
        document.addEventListener('DOMContentLoaded', function() {
            const hash = window.location.hash;
            if (hash) {
                const tab = document.querySelector(`[data-bs-target="${hash}"]`);
                if (tab) {
                    const tabInstance = new bootstrap.Tab(tab);
                    tabInstance.show();
                }
            }
        });
    </script>
</body>
</html>