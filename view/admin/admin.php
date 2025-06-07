<?php
session_start();

// Check if user is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: /DoAn_BookStore/view/auth/login.php');
    exit();
}

require_once __DIR__ . '/../../model/Database.php';

$database = new Database();
$message = '';
$error = '';

// Banner management functions
function getBannerFiles() {
    $adsDir = __DIR__ . '/../../images/ads/';
    $bannerFiles = [];
    
    if (is_dir($adsDir)) {
        $files = scandir($adsDir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..' && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $bannerFiles[] = $file;
            }
        }
    }
    
    return $bannerFiles;
}

function getActiveBanners() {
    $configFile = __DIR__ . '/../../config/active_banners.json';
    if (file_exists($configFile)) {
        $content = file_get_contents($configFile);
        return json_decode($content, true) ?: [];
    }
    return [];
}

function saveActiveBanners($banners) {
    $configDir = __DIR__ . '/../../config/';
    if (!is_dir($configDir)) {
        mkdir($configDir, 0755, true);
    }
    
    $configFile = $configDir . 'active_banners.json';
    return file_put_contents($configFile, json_encode($banners, JSON_PRETTY_PRINT));
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_book':
            try {
                $bookData = [
                    'title' => trim($_POST['title']),
                    'author' => trim($_POST['author']),
                    'category' => trim($_POST['category']),
                    'price' => floatval($_POST['price']), // Giữ nguyên giá nhập vào
                    'stock' => intval($_POST['stock']),
                    'description' => trim($_POST['description']),
                    'image' => trim($_POST['image_url']),
                    'created_at' => date('Y-m-d H:i:s')
                ];

                $result = $database->insert('books', $bookData);
                if ($result) {
                    $message = "Thêm sách thành công!";
                } else {
                    $error = "Lỗi khi thêm sách";
                }
            } catch (Exception $e) {
                $error = "Lỗi khi thêm sách: " . $e->getMessage();
            }
            break;

        case 'update_book':
            try {
                $id = intval($_POST['book_id']);
                $bookData = [
                    'title' => trim($_POST['title']),
                    'author' => trim($_POST['author']),
                    'category' => trim($_POST['category']),
                    'price' => floatval($_POST['price']), // Giữ nguyên giá nhập vào
                    'stock' => intval($_POST['stock']),
                    'description' => trim($_POST['description']),
                    'image' => trim($_POST['image_url']),
                ];

                $result = $database->update('books', $bookData, 'id = :id', ['id' => $id]);
                if ($result) {
                    $message = "Cập nhật sách thành công!";
                } else {
                    $error = "Lỗi khi cập nhật sách";
                }
            } catch (Exception $e) {
                $error = "Lỗi khi cập nhật sách: " . $e->getMessage();
            }
            break;

        case 'delete_book':
            try {
                $id = intval($_POST['book_id']);
                $result = $database->delete('books', 'id = :id', ['id' => $id]);
                if ($result) {
                    $message = "Xóa sách thành công!";
                } else {
                    $error = "Lỗi khi xóa sách";
                }
            } catch (Exception $e) {
                $error = "Lỗi khi xóa sách: " . $e->getMessage();
            }
            break;

        case 'add_user':
            try {
                $username = trim($_POST['username']);
                $email = trim($_POST['email']);
                $password = trim($_POST['password']);
                $permission = $_POST['permission'];

                // Validate input
                if (empty($username) || empty($email) || empty($password)) {
                    $error = "Vui lòng điền đầy đủ thông tin";
                    break;
                }

                // Check if username or email exists
                if ($database->usernameExists($username)) {
                    $error = "Tên đăng nhập đã tồn tại";
                    break;
                }

                if ($database->emailExists($email)) {
                    $error = "Email đã được sử dụng";
                    break;
                }

                // Create user using Database method
                $userData = [
                    'username' => $username,
                    'name' => $username, // Default name = username
                    'email' => $email,
                    'permission' => $permission,
                ];

                $result = $database->createUserWithSHA256(array_merge($userData, ['password' => $password]));
                if ($result == 0 || $result) {
                    $message = "Thêm người dùng thành công!";
                } else {
                    $error = "Lỗi khi thêm người dùng";
                }
            } catch (Exception $e) {
                $error = "Lỗi khi thêm người dùng: " . $e->getMessage();
            }
            break;

        case 'update_user':
            try {
                $id = intval($_POST['user_id']);
                $username = trim($_POST['username']);
                $email = trim($_POST['email']);
                $permission = $_POST['permission'];

                // Validate input
                if (empty($username) || empty($email)) {
                    $error = "Vui lòng điền đầy đủ thông tin";
                    break;
                }

                // Check if username or email exists (excluding current user)
                if ($database->usernameExists($username, $id)) {
                    $error = "Tên đăng nhập đã tồn tại";
                    break;
                }

                if ($database->emailExists($email, $id)) {
                    $error = "Email đã được sử dụng";
                    break;
                }

                $userData = [
                    'username' => $username,
                    'name' => $username, // Update name = username
                    'email' => $email,
                    'permission' => $permission,
                ];

                $result = $database->update('users', $userData, 'id = :id', ['id' => $id]);
                if ($result) {
                    $message = "Cập nhật người dùng thành công!";
                } else {
                    $error = "Lỗi khi cập nhật người dùng";
                }
            } catch (Exception $e) {
                $error = "Lỗi khi cập nhật người dùng: " . $e->getMessage();
            }
            break;

        case 'delete_user':
            try {
                $id = intval($_POST['user_id']);
                if ($id == $_SESSION['user_id']) {
                    $error = "Không thể xóa tài khoản của chính mình!";
                } else {
                    $result = $database->delete('users', 'id = :id', ['id' => $id]);
                    if ($result) {
                        $message = "Xóa người dùng thành công!";
                    } else {
                        $error = "Lỗi khi xóa người dùng";
                    }
                }
            } catch (Exception $e) {
                $error = "Lỗi khi xóa người dùng: " . $e->getMessage();
            }
            break;

        case 'update_banners':
            try {
                $activeBanners = $_POST['active_banners'] ?? [];
                if (saveActiveBanners($activeBanners)) {
                    $message = "Cập nhật banner thành công!";
                } else {
                    $error = "Lỗi khi cập nhật banner";
                }
            } catch (Exception $e) {
                $error = "Lỗi khi cập nhật banner: " . $e->getMessage();
            }
            break;

        case 'upload_banner':
            try {
                if (isset($_FILES['banner_file']) && $_FILES['banner_file']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = __DIR__ . '/../../images/ads/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $fileInfo = pathinfo($_FILES['banner_file']['name']);
                    $extension = strtolower($fileInfo['extension']);
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                    if (in_array($extension, $allowedExtensions)) {
                        $fileName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $fileInfo['filename']) . '.' . $extension;
                        $uploadPath = $uploadDir . $fileName;

                        if (move_uploaded_file($_FILES['banner_file']['tmp_name'], $uploadPath)) {
                            $message = "Upload banner thành công!";
                        } else {
                            $error = "Lỗi khi upload file";
                        }
                    } else {
                        $error = "Chỉ cho phép upload file: jpg, jpeg, png, gif, webp";
                    }
                } else {
                    $error = "Vui lòng chọn file để upload";
                }
            } catch (Exception $e) {
                $error = "Lỗi khi upload banner: " . $e->getMessage();
            }
            break;

        case 'delete_banner':
            try {
                $fileName = $_POST['banner_file'] ?? '';
                $filePath = __DIR__ . '/../../images/ads/' . $fileName;
                
                if (file_exists($filePath) && unlink($filePath)) {
                    // Remove from active banners if exists
                    $activeBanners = getActiveBanners();
                    $activeBanners = array_filter($activeBanners, function($banner) use ($fileName) {
                        return $banner !== $fileName;
                    });
                    saveActiveBanners($activeBanners);
                    
                    $message = "Xóa banner thành công!";
                } else {
                    $error = "Không thể xóa file banner";
                }
            } catch (Exception $e) {
                $error = "Lỗi khi xóa banner: " . $e->getMessage();
            }
            break;
    }
}

// Get data for display
$books = $database->fetchAll("SELECT * FROM books ORDER BY title");
$users = $database->fetchAll("SELECT id, username, name, email, permission FROM users ORDER BY username");
$categories = $database->fetchAll("SELECT DISTINCT category FROM books WHERE category IS NOT NULL AND category != '' ORDER BY category");

// Get banner data
$allBanners = getBannerFiles();
$activeBanners = getActiveBanners();

// Get statistics
$total_books = $database->count('books');
$total_users = $database->count('users');
$total_categories = count($categories);
$low_stock_books = $database->fetchAll("SELECT * FROM books WHERE stock < 5 ORDER BY stock ASC");
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản trị hệ thống - BookStore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/DoAn_BookStore/view/admin/admin.css">
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h4 class="text-white">
                            <i class="fas fa-tachometer-alt"></i> Admin Panel
                        </h4>
                        <small class="text-muted">Xin chào,
                            <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></small>
                    </div>

                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="#dashboard" data-bs-toggle="tab">
                                <i class="fas fa-chart-bar"></i> Tổng quan
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#books" data-bs-toggle="tab">
                                <i class="fas fa-book"></i> Quản lý sách
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#users" data-bs-toggle="tab">
                                <i class="fas fa-users"></i> Quản lý người dùng
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#banners" data-bs-toggle="tab">
                                <i class="fas fa-images"></i> Quản lý Banner
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#inventory" data-bs-toggle="tab">
                                <i class="fas fa-warehouse"></i> Kho hàng
                            </a>
                        </li>
                        <li class="nav-item mt-3">
                            <a class="nav-link text-danger" href="/DoAn_BookStore/">
                                <i class="fas fa-arrow-left"></i> Về trang chủ
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 content">
                <div class="pt-3 pb-2 mb-3">

                    <!-- Messages -->
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

                    <!-- Tab Content -->
                    <div class="tab-content">
                        <!-- Dashboard Tab -->
                        <div class="tab-pane fade show active" id="dashboard">
                            <h2 class="mb-4">
                                <i class="fas fa-chart-bar"></i> Tổng quan hệ thống
                            </h2>

                            <!-- Statistics Cards -->
                            <div class="row mb-4">
                                <div class="col-md-3 mb-3">
                                    <div class="card card-stats">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h5 class="card-title">Tổng sách</h5>
                                                    <h2><?php echo $total_books; ?></h2>
                                                </div>
                                                <div class="align-self-center">
                                                    <i class="fas fa-book fa-2x"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <div class="card card-stats">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h5 class="card-title">Người dùng</h5>
                                                    <h2><?php echo $total_users; ?></h2>
                                                </div>
                                                <div class="align-self-center">
                                                    <i class="fas fa-users fa-2x"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <div class="card card-stats">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h5 class="card-title">Danh mục</h5>
                                                    <h2><?php echo $total_categories; ?></h2>
                                                </div>
                                                <div class="align-self-center">
                                                    <i class="fas fa-tags fa-2x"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <div class="card card-stats">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h5 class="card-title">Sắp hết hàng</h5>
                                                    <h2><?php echo count($low_stock_books); ?></h2>
                                                </div>
                                                <div class="align-self-center">
                                                    <i class="fas fa-exclamation-triangle fa-2x"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Low Stock Alert -->
                            <?php if (!empty($low_stock_books)): ?>
                                <div class="card stock-warning mb-4">
                                    <div class="card-header">
                                        <h5><i class="fas fa-exclamation-triangle"></i> Cảnh báo: Sách sắp hết hàng</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Tên sách</th>
                                                        <th>Tác giả</th>
                                                        <th>Số lượng còn</th>
                                                        <th>Thao tác</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($low_stock_books as $book): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($book['title']); ?></td>
                                                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                                                            <td><span
                                                                    class="badge bg-warning"><?php echo $book['stock']; ?></span>
                                                            </td>
                                                            <td>
                                                                <button class="btn btn-sm btn-primary"
                                                                    onclick="editBook(<?php echo htmlspecialchars(json_encode($book)); ?>)">
                                                                    <i class="fas fa-edit"></i> Sửa
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Books Management Tab -->
                        <div class="tab-pane fade" id="books">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h2><i class="fas fa-book"></i> Quản lý sách</h2>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBookModal">
                                    <i class="fas fa-plus"></i> Thêm sách mới
                                </button>
                            </div>

                            <div class="card">
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Tên sách</th>
                                                    <th>Tác giả</th>
                                                    <th>Danh mục</th>
                                                    <th>Giá</th>
                                                    <th>Kho</th>
                                                    <th>Thao tác</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($books as $book): ?>
                                                    <tr>
                                                        <td><?php echo $book['id']; ?></td>
                                                        <td><?php echo htmlspecialchars($book['title']); ?></td>
                                                        <td><?php echo htmlspecialchars($book['author']); ?></td>
                                                        <td><?php echo htmlspecialchars($book['category']); ?></td>
                                                        <td><?php echo number_format($book['price'] * 1000, 0, ',', '.'); ?>
                                                            VNĐ</td>
                                                        <td>
                                                            <span
                                                                class="badge <?php echo $book['stock'] < 5 ? 'bg-warning' : 'bg-success'; ?>">
                                                                <?php echo $book['stock']; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm">
                                                                <button class="btn btn-outline-primary"
                                                                    onclick="editBook(<?php echo htmlspecialchars(json_encode($book)); ?>)">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <button class="btn btn-outline-danger"
                                                                    onclick="deleteBook(<?php echo $book['id']; ?>, '<?php echo htmlspecialchars($book['title']); ?>')">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Users Management Tab -->
                        <div class="tab-pane fade" id="users">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h2><i class="fas fa-users"></i> Quản lý người dùng</h2>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                    <i class="fas fa-plus"></i> Thêm người dùng
                                </button>
                            </div>

                            <div class="card">
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Tên người dùng</th>
                                                    <th>Email</th>
                                                    <th>Quyền</th>
                                                    <th>Ngày tạo</th>
                                                    <th>Thao tác</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($users as $user): ?>
                                                    <tr>
                                                        <td><?php echo $user['id']; ?></td>
                                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                        <td>
                                                            <span
                                                                class="badge <?php echo $user['permission'] === 'admin' ? 'bg-danger' : 'bg-primary'; ?>">
                                                                <?php echo ucfirst($user['permission']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm">
                                                                <button class="btn btn-outline-primary"
                                                                    onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                                    <button class="btn btn-outline-danger"
                                                                        onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Banner Management Tab -->
                        <div class="tab-pane fade" id="banners">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h2><i class="fas fa-images"></i> Quản lý Banner Quảng Cáo</h2>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadBannerModal">
                                    <i class="fas fa-upload"></i> Upload Banner
                                </button>
                            </div>

                            <!-- Active Banners Section -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5><i class="fas fa-star"></i> Banner Đang Hoạt Động</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="update_banners">
                                        <div class="row">
                                            <?php if (empty($activeBanners)): ?>
                                                <div class="col-12">
                                                    <p class="text-muted">Chưa có banner nào được kích hoạt</p>
                                                </div>
                                            <?php else: ?>
                                                <?php foreach ($activeBanners as $banner): ?>
                                                    <?php if (file_exists(__DIR__ . '/../../images/ads/' . $banner)): ?>
                                                        <div class="col-md-4 mb-3">
                                                            <div class="card">
                                                                <img src="/DoAn_BookStore/images/ads/<?php echo htmlspecialchars($banner); ?>" 
                                                                     class="card-img-top" alt="Banner" style="height: 150px; object-fit: cover;">
                                                                <div class="card-body p-2">
                                                                    <small class="text-muted"><?php echo htmlspecialchars($banner); ?></small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- All Banners Section -->
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-images"></i> Tất Cả Banner (<?php echo count($allBanners); ?>)</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($allBanners)): ?>
                                        <p class="text-muted">Chưa có banner nào. Hãy upload banner đầu tiên!</p>
                                    <?php else: ?>
                                        <form method="POST" id="bannersForm">
                                            <input type="hidden" name="action" value="update_banners">
                                            <div class="row">
                                                <?php foreach ($allBanners as $banner): ?>
                                                    <div class="col-md-4 mb-3">
                                                        <div class="card banner-card">
                                                            <div class="position-relative">
                                                                <img src="/DoAn_BookStore/images/ads/<?php echo htmlspecialchars($banner); ?>" 
                                                                     class="card-img-top" alt="Banner" style="height: 200px; object-fit: cover;">
                                                                
                                                                <!-- Delete button -->
                                                                <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 m-2"
                                                                        onclick="deleteBanner('<?php echo htmlspecialchars($banner); ?>')">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                                
                                                                <!-- Active indicator -->
                                                                <?php if (in_array($banner, $activeBanners)): ?>
                                                                    <span class="badge bg-success position-absolute top-0 start-0 m-2">
                                                                        <i class="fas fa-star"></i> Đang hoạt động
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                            
                                                            <div class="card-body">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" 
                                                                        name="active_banners[]" 
                                                                        value="<?php echo htmlspecialchars($banner); ?>"
                                                                        id="banner_<?php echo md5($banner); ?>"
                                                                        <?php echo in_array($banner, $activeBanners) ? 'checked' : ''; ?>>
                                                                    <label class="form-check-label" for="banner_<?php echo md5($banner); ?>">
                                                                        <strong>Kích hoạt banner</strong>
                                                                    </label>
                                                                </div>
                                                                <small class="text-muted d-block mt-2">
                                                                    <?php echo htmlspecialchars($banner); ?>
                                                                </small>
                                                                <small class="text-muted">
                                                                    Kích thước: 
                                                                    <?php
                                                                    $imageInfo = getimagesize(__DIR__ . '/../../images/ads/' . $banner);
                                                                    echo $imageInfo[0] . 'x' . $imageInfo[1] . 'px';
                                                                    ?>
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            
                                            <div class="mt-3">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-save"></i> Cập Nhật Banner Hoạt Động
                                                </button>
                                            </div>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Inventory Tab -->
                        <div class="tab-pane fade" id="inventory">
                            <h2 class="mb-4"><i class="fas fa-warehouse"></i> Quản lý kho hàng</h2>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5>Danh mục sách</h5>
                                        </div>
                                        <div class="card-body">
                                            <?php foreach ($categories as $category): ?>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span><?php echo htmlspecialchars($category['category']); ?></span>
                                                    <span class="badge bg-info">
                                                        <?php
                                                        $count = $database->count('books', 'category = :category', ['category' => $category['category']]);
                                                        echo $count;
                                                        ?> sách
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5>Thống kê nhanh</h5>
                                        </div>
                                        <div class="card-body">
                                            <p><strong>Tổng số sách:</strong> <?php echo $total_books; ?></p>
                                            <p><strong>Sách có sẵn:</strong>
                                                <?php echo $database->count('books', 'stock > 0'); ?>
                                            </p>
                                            <p><strong>Sách hết hàng:</strong>
                                                <?php echo $database->count('books', 'stock = 0'); ?>
                                            </p>
                                            <p><strong>Sách sắp hết:</strong> <?php echo count($low_stock_books); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Book Modal -->
    <div class="modal fade" id="addBookModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Thêm sách mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_book">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tên sách</label>
                                    <input type="text" class="form-control" name="title" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tác giả</label>
                                    <input type="text" class="form-control" name="author" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Danh mục</label>
                                    <input type="text" class="form-control" name="category" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Giá (nghìn VNĐ)</label>
                                    <input type="number" step="0.01" class="form-control" name="price"
                                        placeholder="Ví dụ: 25.5 = 25,500 VNĐ" required>
                                    <small class="form-text text-muted">Nhập giá tính theo nghìn VNĐ (ví dụ: 25.5 =
                                        25,500 VNĐ)</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Số lượng</label>
                                    <input type="number" class="form-control" name="stock" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">URL hình ảnh</label>
                            <input type="url" class="form-control" name="image_url">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mô tả</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary">Thêm sách</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Book Modal -->
    <div class="modal fade" id="editBookModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Sửa thông tin sách</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_book">
                        <input type="hidden" name="book_id" id="edit_book_id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tên sách</label>
                                    <input type="text" class="form-control" name="title" id="edit_title" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tác giả</label>
                                    <input type="text" class="form-control" name="author" id="edit_author" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Danh mục</label>
                                    <input type="text" class="form-control" name="category" id="edit_category" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Giá (nghìn VNĐ)</label>
                                    <input type="number" step="0.01" class="form-control" name="price" id="edit_price"
                                        placeholder="Ví dụ: 25.5 = 25,500 VNĐ" required>
                                    <small class="form-text text-muted">Nhập giá tính theo nghìn VNĐ (ví dụ: 25.5 =
                                        25,500 VNĐ)</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Số lượng</label>
                                    <input type="number" class="form-control" name="stock" id="edit_stock" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">URL hình ảnh</label>
                            <input type="url" class="form-control" name="image_url" id="edit_image_url">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mô tả</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary">Cập nhật</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Thêm người dùng mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_user">
                        <div class="mb-3">
                            <label class="form-label">Tên người dùng</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mật khẩu</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Quyền</label>
                            <select class="form-select" name="permission" required>
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary">Thêm người dùng</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Sửa thông tin người dùng</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_user">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="mb-3">
                            <label class="form-label">Tên người dùng</label>
                            <input type="text" class="form-control" name="username" id="edit_username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="edit_email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Quyền</label>
                            <select class="form-select" name="permission" id="edit_permission" required>
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary">Cập nhật</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Upload Banner Modal -->
    <div class="modal fade" id="uploadBannerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Banner Mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="upload_banner">
                        <div class="mb-3">
                            <label class="form-label">Chọn file banner</label>
                            <input type="file" class="form-control" name="banner_file" accept="image/*" required>
                            <div class="form-text">
                                Chỉ chấp nhận file: JPG, JPEG, PNG, GIF, WEBP<br>
                                Khuyến nghị kích thước: 1200x400px hoặc tỷ lệ 3:1
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="activate_immediately" id="activateImmediately">
                                <label class="form-check-label" for="activateImmediately">
                                    Kích hoạt ngay sau khi upload
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Upload
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Banner Form -->
    <form id="deleteBannerForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_banner">
        <input type="hidden" name="banner_file" id="delete_banner_file">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Tab navigation
        document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
            tab.addEventListener('shown.bs.tab', function (e) {
                // Update active state
                document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
                this.classList.add('active');
            });
        });

        // Book functions
        function editBook(book) {
            document.getElementById('edit_book_id').value = book.id;
            document.getElementById('edit_title').value = book.title;
            document.getElementById('edit_author').value = book.author;
            document.getElementById('edit_category').value = book.category;
            document.getElementById('edit_price').value = book.price;
            document.getElementById('edit_stock').value = book.stock;
            document.getElementById('edit_image_url').value = book.image_url || '';
            document.getElementById('edit_description').value = book.description || '';

            // Switch to books tab and show modal
            const booksTab = document.querySelector('[href="#books"]');
            const tab = new bootstrap.Tab(booksTab);
            tab.show();

            const modal = new bootstrap.Modal(document.getElementById('editBookModal'));
            modal.show();
        }

        function deleteBook(id, title) {
            if (confirm(`Bạn có chắc muốn xóa sách "${title}"?`)) {
                document.getElementById('delete_book_id').value = id;
                document.getElementById('deleteBookForm').submit();
            }
        }

        // User functions
        function editUser(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_permission').value = user.permission;

            // Switch to users tab and show modal
            const usersTab = document.querySelector('[href="#users"]');
            const tab = new bootstrap.Tab(usersTab);
            tab.show();

            const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
            modal.show();
        }

        function deleteUser(id, username) {
            if (confirm(`Bạn có chắc muốn xóa người dùng "${username}"?`)) {
                document.getElementById('delete_user_id').value = id;
                document.getElementById('deleteUserForm').submit();
            }
        }

        // Banner management functions
        function deleteBanner(fileName) {
            if (confirm(`Bạn có chắc muốn xóa banner "${fileName}"?`)) {
                document.getElementById('delete_banner_file').value = fileName;
                document.getElementById('deleteBannerForm').submit();
            }
        }

        // Auto-hide alerts
        setTimeout(function () {
            document.querySelectorAll('.alert').forEach(function (alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 3000);
    </script>
</body>
</html>