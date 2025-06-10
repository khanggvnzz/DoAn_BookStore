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
function getBannerFiles()
{
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

function getActiveBanners()
{
    $configFile = __DIR__ . '/../../config/active_banners.json';
    if (file_exists($configFile)) {
        $content = file_get_contents($configFile);
        return json_decode($content, true) ?: [];
    }
    return [];
}

function saveActiveBanners($banners)
{
    $configDir = __DIR__ . '/../../config/';
    if (!is_dir($configDir)) {
        mkdir($configDir, 0755, true);
    }

    $configFile = $configDir . 'active_banners.json';
    return file_put_contents($configFile, json_encode($banners, JSON_PRETTY_PRINT));
}

// Voucher management functions
function generateVoucherCode($length = 8)
{
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_book':
            try {
                $imageName = '';

                // Handle image upload
                if (isset($_FILES['book_image']) && $_FILES['book_image']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = __DIR__ . '/../../images/books/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $fileInfo = pathinfo($_FILES['book_image']['name']);
                    $extension = strtolower($fileInfo['extension']);
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                    if (in_array($extension, $allowedExtensions)) {
                        // Generate unique filename
                        $imageName = uniqid('book_') . '_' . time() . '.' . $extension;
                        $uploadPath = $uploadDir . $imageName;

                        if (!move_uploaded_file($_FILES['book_image']['tmp_name'], $uploadPath)) {
                            $error = "Lỗi khi upload ảnh";
                            break;
                        }
                    } else {
                        $error = "Chỉ cho phép upload file: jpg, jpeg, png, gif, webp";
                        break;
                    }
                } else {
                    $error = "Vui lòng chọn ảnh cho sách";
                    break;
                }

                $category_id = $database->getCategoryIdbyName($_POST['category']);

                $bookData = [
                    'title' => trim($_POST['title']),
                    'author' => trim($_POST['author']),
                    'category' => trim($_POST['category']),
                    'price' => floatval($_POST['price']),
                    'stock' => intval($_POST['stock']),
                    'description' => trim($_POST['description']),
                    'category_id' => $category_id['id'],
                    'image' => $imageName,
                    'created_at' => date('Y-m-d H:i:s')
                ];

                $result = $database->insert('books', $bookData);
                if ($result) {
                    $message = "Thêm sách thành công!";
                } else {
                    $error = "Lỗi khi thêm sách";
                    // Delete uploaded image if database insert failed
                    if ($imageName && file_exists($uploadDir . $imageName)) {
                        unlink($uploadDir . $imageName);
                    }
                }
            } catch (Exception $e) {
                $error = "Lỗi khi thêm sách: " . $e->getMessage();
                // Delete uploaded image if error occurred
                if (isset($imageName) && $imageName && file_exists($uploadDir . $imageName)) {
                    unlink($uploadDir . $imageName);
                }
            }
            break;

        case 'update_book':
            try {
                $id = intval($_POST['book_id']);
                $imageName = $_POST['current_image']; // Keep current image by default

                // Handle image upload if new image is provided
                if (isset($_FILES['book_image']) && $_FILES['book_image']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = __DIR__ . '/../../images/books/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $fileInfo = pathinfo($_FILES['book_image']['name']);
                    $extension = strtolower($fileInfo['extension']);
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                    if (in_array($extension, $allowedExtensions)) {
                        // Generate unique filename
                        $newImageName = uniqid('book_') . '_' . time() . '.' . $extension;
                        $uploadPath = $uploadDir . $newImageName;

                        if (move_uploaded_file($_FILES['book_image']['tmp_name'], $uploadPath)) {
                            // Delete old image if exists
                            if ($imageName && file_exists($uploadDir . $imageName)) {
                                unlink($uploadDir . $imageName);
                            }
                            $imageName = $newImageName;
                        } else {
                            $error = "Lỗi khi upload ảnh mới";
                            break;
                        }
                    } else {
                        $error = "Chỉ cho phép upload file: jpg, jpeg, png, gif, webp";
                        break;
                    }
                }

                $bookData = [
                    'title' => trim($_POST['title']),
                    'author' => trim($_POST['author']),
                    'category' => trim($_POST['category']),
                    'price' => floatval($_POST['price']),
                    'stock' => intval($_POST['stock']),
                    'description' => trim($_POST['description']),
                    'image' => $imageName,
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
                $book_Id = intval($_POST['book_id']);

                // Get book info to delete image
                $book = $database->fetch("SELECT * FROM books WHERE id = :id", ['id' => $book_Id]);

                $result = $database->delete('books', 'id = :id', ['id' => $book_Id]);
                if ($result) {
                    // Delete image file if exists
                    if ($book && $book['image']) {
                        $imagePath = __DIR__ . '/../../images/books/' . $book['image'];
                        if (file_exists($imagePath)) {
                            unlink($imagePath);
                        }
                    }
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

                $result = $database->update('users', $userData, 'user_id = :user_id', ['user_id' => $id]);
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
                    $result = $database->delete('users', 'user_id = :user_id', ['user_id' => $id]);
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
                    $activeBanners = array_filter($activeBanners, function ($banner) use ($fileName) {
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

        case 'add_voucher':
            try {
                // Generate unique voucher code
                do {
                    $code = generateVoucherCode();
                } while ($database->voucherCodeExists($code));

                $voucherData = [
                    'code' => $code,
                    'name' => trim($_POST['name']),
                    'description' => trim($_POST['description']),
                    'min_order_amount' => floatval($_POST['min_order_amount']),
                    'discount_percent' => floatval($_POST['discount_percent']),
                    'quantity' => intval($_POST['quantity']),
                    'used_count' => 0,
                    'is_active' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                    'expires_at' => !empty($_POST['expires_at']) ? $_POST['expires_at'] : null
                ];

                $result = $database->createVoucher($voucherData);

                if ($result) {
                    $message = "Tạo voucher thành công! Mã voucher: " . $code;
                } else {
                    $error = "Lỗi khi tạo voucher";
                }
            } catch (Exception $e) {
                $error = "Lỗi khi tạo voucher: " . $e->getMessage();
            }
            break;

        case 'update_voucher':
            try {
                $voucherId = intval($_POST['voucher_id']);

                $voucherData = [
                    'name' => trim($_POST['name']),
                    'description' => trim($_POST['description']),
                    'min_order_amount' => floatval($_POST['min_order_amount']),
                    'discount_percent' => floatval($_POST['discount_percent']),
                    'quantity' => intval($_POST['quantity']),
                    'is_active' => isset($_POST['is_active']) ? 1 : 0
                ];

                $result = $database->updateVoucher($voucherId, $voucherData);

                if ($result) {
                    $message = "Cập nhật voucher thành công!";
                } else {
                    $error = "Lỗi khi cập nhật voucher";
                }
            } catch (Exception $e) {
                $error = "Lỗi khi cập nhật voucher: " . $e->getMessage();
            }
            break;

        case 'delete_voucher':
            try {
                $voucherId = intval($_POST['voucher_id']);

                $result = $database->deleteVoucher($voucherId);

                if ($result) {
                    $message = "Xóa voucher thành công!";
                } else {
                    $error = "Lỗi khi xóa voucher";
                }
            } catch (Exception $e) {
                $error = "Lỗi khi xóa voucher: " . $e->getMessage();
            }
            break;

        case 'toggle_voucher_status':
            try {
                $voucherId = intval($_POST['voucher_id']);

                $result = $database->toggleVoucherStatus($voucherId);

                if ($result) {
                    $message = "Cập nhật trạng thái voucher thành công!";
                } else {
                    $error = "Lỗi khi cập nhật trạng thái voucher";
                }
            } catch (Exception $e) {
                $error = "Lỗi khi cập nhật trạng thái voucher: " . $e->getMessage();
            }
            break;

        case 'approve_order':
            try {
                $orderId = intval($_POST['order_id']);
                $result = $database->updateOrderStatus($orderId, 'confirmed');
                if ($result) {
                    $message = "Duyệt đơn hàng thành công!";
                } else {
                    $error = "Lỗi khi duyệt đơn hàng";
                }
            } catch (Exception $e) {
                $error = "Lỗi khi duyệt đơn hàng: " . $e->getMessage();
            }
            break;

        case 'cancel_order':
            try {
                $orderId = intval($_POST['order_id']);
                $result = $database->updateOrderStatus($orderId, 'cancelled');
                if ($result) {
                    $message = "Hủy đơn hàng thành công!";
                } else {
                    $error = "Lỗi khi hủy đơn hàng";
                }
            } catch (Exception $e) {
                $error = "Lỗi khi hủy đơn hàng: " . $e->getMessage();
            }
            break;

        case 'update_order_status':
            try {
                $orderId = intval($_POST['order_id']);
                $newStatus = $_POST['status'];

                // Validate status
                $validStatuses = ['pending', 'confirmed', 'cancelled'];
                if (!in_array($newStatus, $validStatuses)) {
                    $error = "Trạng thái không hợp lệ";
                    break;
                }

                $result = $database->updateOrderStatus($orderId, $newStatus);
                if ($result) {
                    $message = "Cập nhật trạng thái đơn hàng thành công!";
                } else {
                    $error = "Lỗi khi cập nhật trạng thái đơn hàng";
                }
            } catch (Exception $e) {
                $error = "Lỗi khi cập nhật trạng thái: " . $e->getMessage();
            }
            break;
    }
}

// Get data for display
$books = $database->fetchAll("SELECT * FROM books ORDER BY title");
$users = $database->fetchAll("SELECT user_id, username, name, email, permission FROM users ORDER BY username");
$categories = $database->fetchAll("SELECT DISTINCT category FROM books WHERE category IS NOT NULL AND category != '' ORDER BY category");
$categoriesFromTable = $database->fetchAll("SELECT id, name FROM categories ORDER BY name");


// Get banner data
$allBanners = getBannerFiles();
$activeBanners = getActiveBanners();

// Get voucher data from database
$vouchers = $database->getAllActiveVouchers();
$allVouchers = $database->fetchAll("SELECT * FROM vouchers ORDER BY created_at DESC");

// Get order data
$pendingOrders = $database->getOrdersByStatus('pending');
$confirmedOrders = $database->getOrdersByStatus('confirmed');
$cancelledOrders = $database->getOrdersByStatus('cancelled');
$allOrdersData = $database->getAllOrders();
$allOrders = $allOrdersData;
$total_orders = $database->count('orders');
$total_pending = count($pendingOrders['orders']);

// Get statistics
$total_books = $database->count('books');
$total_users = $database->count('users');
$total_categories = count($categories);
$total_vouchers = $database->count('vouchers');
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
    <link rel="stylesheet" href="/view/admin/admin.css">
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
                            <a class="nav-link" href="#vouchers" data-bs-toggle="tab">
                                <i class="fas fa-ticket-alt"></i> Quản lý Voucher
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#inventory" data-bs-toggle="tab">
                                <i class="fas fa-warehouse"></i> Kho hàng
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#orders" data-bs-toggle="tab">
                                <i class="fas fa-shopping-cart"></i> Quản lý Đơn hàng
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
                                                    <h5 class="card-title">Voucher</h5>
                                                    <h2><?php echo count($vouchers); ?></h2>
                                                </div>
                                                <div class="align-self-center">
                                                    <i class="fas fa-ticket-alt fa-2x"></i>
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
                                                        <td><?php echo $user['user_id']; ?></td>
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
                                                                <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                                                    <button class="btn btn-outline-danger"
                                                                        onclick="deleteUser(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
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
                                <button class="btn btn-primary" data-bs-toggle="modal"
                                    data-bs-target="#uploadBannerModal">
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
                                                                    class="card-img-top" alt="Banner"
                                                                    style="height: 150px; object-fit: cover;">
                                                                <div class="card-body p-2">
                                                                    <small
                                                                        class="text-muted"><?php echo htmlspecialchars($banner); ?></small>
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
                                    <h5><i class="fas fa-images"></i> Tất Cả Banner (<?php echo count($allBanners); ?>)
                                    </h5>
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
                                                                    class="card-img-top" alt="Banner"
                                                                    style="height: 200px; object-fit: cover;">

                                                                <!-- Delete button -->
                                                                <button type="button"
                                                                    class="btn btn-danger btn-sm position-absolute top-0 end-0 m-2"
                                                                    onclick="deleteBanner('<?php echo htmlspecialchars($banner); ?>')">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>

                                                                <!-- Active indicator -->
                                                                <?php if (in_array($banner, $activeBanners)): ?>
                                                                    <span
                                                                        class="badge bg-success position-absolute top-0 start-0 m-2">
                                                                        <i class="fas fa-star"></i> Đang hoạt động
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>

                                                            <div class="card-body">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox"
                                                                        name="active_banners[]"
                                                                        value="<?php echo htmlspecialchars($banner); ?>"
                                                                        id="banner_<?php echo md5($banner); ?>" <?php echo in_array($banner, $activeBanners) ? 'checked' : ''; ?>>
                                                                    <label class="form-check-label"
                                                                        for="banner_<?php echo md5($banner); ?>">
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

                        <!-- Voucher Management Tab -->
                        <div class="tab-pane fade" id="vouchers">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h2><i class="fas fa-ticket-alt"></i> Quản lý Voucher</h2>
                                <button class="btn btn-primary" data-bs-toggle="modal"
                                    data-bs-target="#addVoucherModal">
                                    <i class="fas fa-plus"></i> Tạo Voucher Mới
                                </button>
                            </div>

                            <!-- Active Vouchers Section -->
                            <div class="card mb-4">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-check-circle"></i> Voucher Đang Hoạt Động
                                        <span class="badge bg-light text-success"><?php echo count($vouchers); ?></span>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($vouchers)): ?>
                                        <div class="text-center py-3">
                                            <i class="fas fa-ticket-alt fa-2x text-muted mb-2"></i>
                                            <p class="text-muted mb-0">Chưa có voucher nào đang hoạt động</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Mã</th>
                                                        <th>Tên</th>
                                                        <th>Giảm giá</th>
                                                        <th>Đã dùng/Tổng</th>
                                                        <th>Hạn sử dụng</th>
                                                        <th>Thao tác</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($vouchers as $voucher): ?>
                                                        <tr>
                                                            <td>
                                                                <code class="bg-success bg-opacity-10 text-success p-1 rounded">
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    <?php echo htmlspecialchars($voucher['code']); ?>
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                </code>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($voucher['name']); ?></td>
                                                            <td>
                                                                <span class="badge bg-success">
                                                                    <?php echo $voucher['discount_percent']; ?>%
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span
                                                                    class="badge <?php echo $voucher['used_count'] >= $voucher['quantity'] ? 'bg-danger' : 'bg-info'; ?>">
                                                                    <?php echo $voucher['used_count']; ?>/<?php echo $voucher['quantity']; ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <?php if ($voucher['expires_at']): ?>
                                                                    <?php
                                                                    $expiry = new DateTime($voucher['expires_at']);
                                                                    $now = new DateTime();
                                                                    $isExpired = $expiry < $now;
                                                                    ?>
                                                                    <small
                                                                        class="<?php echo $isExpired ? 'text-danger' : 'text-success'; ?>">
                                                                        <?php echo $expiry->format('d/m/Y'); ?>
                                                                    </small>
                                                                <?php else: ?>
                                                                    <small class="text-muted">Vô hạn</small>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <div class="btn-group btn-group-sm">
                                                                    <button class="btn btn-outline-primary"
                                                                        onclick="editVoucher(<?php echo htmlspecialchars(json_encode($voucher)); ?>)"
                                                                        title="Chỉnh sửa">
                                                                        <i class="fas fa-edit"></i>
                                                                    </button>
                                                                    <button class="btn btn-outline-warning"
                                                                        onclick="toggleVoucherStatus(<?php echo $voucher['voucher_id']; ?>, '<?php echo htmlspecialchars($voucher['name']); ?>', true)"
                                                                        title="Tạm dừng">
                                                                        <i class="fas fa-pause"></i>
                                                                    </button>
                                                                    <button class="btn btn-outline-danger"
                                                                        onclick="deleteVoucher(<?php echo $voucher['voucher_id']; ?>, '<?php echo htmlspecialchars($voucher['name']); ?>')"
                                                                        title="Xóa">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Inactive Vouchers Section -->
                            <?php
                            $inactiveVouchers = $database->fetchAll("SELECT * FROM vouchers WHERE is_active = 0 ORDER BY created_at DESC");
                            ?>
                            <div class="card mb-4">
                                <div class="card-header bg-warning text-dark">
                                    <h5 class="mb-0">
                                        <i class="fas fa-pause-circle"></i> Voucher Tạm Dừng
                                        <span class="badge bg-dark"><?php echo count($inactiveVouchers); ?></span>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($inactiveVouchers)): ?>
                                        <div class="text-center py-3">
                                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                            <p class="text-muted mb-0">Tất cả voucher đang hoạt động!</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Mã</th>
                                                        <th>Tên</th>
                                                        <th>Giảm giá</th>
                                                        <th>Đã dùng/Tổng</th>
                                                        <th>Hạn sử dụng</th>
                                                        <th>Lý do tạm dừng</th>
                                                        <th>Thao tác</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($inactiveVouchers as $voucher): ?>
                                                        <?php
                                                        // Determine why voucher is inactive
                                                        $now = new DateTime();
                                                        $isExpired = $voucher['expires_at'] && new DateTime($voucher['expires_at']) < $now;
                                                        $isOutOfStock = $voucher['used_count'] >= $voucher['quantity'];
                                                        $isManuallyDisabled = !$isExpired && !$isOutOfStock;

                                                        if ($isExpired) {
                                                            $inactiveReason = 'Hết hạn';
                                                            $reasonClass = 'text-danger';
                                                            $reasonIcon = 'fas fa-calendar-times';
                                                        } elseif ($isOutOfStock) {
                                                            $inactiveReason = 'Hết số lượng';
                                                            $reasonClass = 'text-warning';
                                                            $reasonIcon = 'fas fa-ban';
                                                        } else {
                                                            $inactiveReason = 'Tạm dừng thủ công';
                                                            $reasonClass = 'text-info';
                                                            $reasonIcon = 'fas fa-pause';
                                                        }
                                                        ?>
                                                        <tr>
                                                            <td>
                                                                <code class="bg-warning bg-opacity-10 text-warning p-1 rounded">
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    <?php echo htmlspecialchars($voucher['code']); ?>
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                </code>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($voucher['name']); ?></td>
                                                            <td>
                                                                <span class="badge bg-secondary">
                                                                    <?php echo $voucher['discount_percent']; ?>%
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span
                                                                    class="badge <?php echo $voucher['used_count'] >= $voucher['quantity'] ? 'bg-danger' : 'bg-secondary'; ?>">
                                                                    <?php echo $voucher['used_count']; ?>/<?php echo $voucher['quantity']; ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <?php if ($voucher['expires_at']): ?>
                                                                    <small
                                                                        class="<?php echo $isExpired ? 'text-danger' : 'text-muted'; ?>">
                                                                        <?php echo (new DateTime($voucher['expires_at']))->format('d/m/Y'); ?>
                                                                    </small>
                                                                <?php else: ?>
                                                                    <small class="text-muted">Vô hạn</small>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <span class="<?php echo $reasonClass; ?>">
                                                                    <i class="<?php echo $reasonIcon; ?>"></i>
                                                                    <?php echo $inactiveReason; ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <div class="btn-group btn-group-sm">
                                                                    <button class="btn btn-outline-primary"
                                                                        onclick="editVoucher(<?php echo htmlspecialchars(json_encode($voucher)); ?>)"
                                                                        title="Chỉnh sửa">
                                                                        <i class="fas fa-edit"></i>
                                                                    </button>

                                                                    <?php if ($isManuallyDisabled): ?>
                                                                        <button class="btn btn-outline-success"
                                                                            onclick="toggleVoucherStatus(<?php echo $voucher['voucher_id']; ?>, '<?php echo htmlspecialchars($voucher['name']); ?>', false)"
                                                                            title="Kích hoạt lại">
                                                                            <i class="fas fa-play"></i>
                                                                        </button>
                                                                    <?php else: ?>
                                                                        <button class="btn btn-outline-secondary" disabled
                                                                            title="<?php echo $isExpired ? 'Không thể kích hoạt voucher đã hết hạn' : 'Không thể kích hoạt voucher đã hết số lượng'; ?>">
                                                                            <i class="fas fa-ban"></i>
                                                                        </button>
                                                                    <?php endif; ?>

                                                                    <button class="btn btn-outline-danger"
                                                                        onclick="deleteVoucher(<?php echo $voucher['voucher_id']; ?>, '<?php echo htmlspecialchars($voucher['name']); ?>')"
                                                                        title="Xóa">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- AllVouchers Overview -->
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-list"></i> Tổng Quan Tất Cả Voucher
                                        <span class="badge bg-primary"><?php echo $total_vouchers; ?></span>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Mã Voucher</th>
                                                    <th>Tên</th>
                                                    <th>Đơn tối thiểu</th>
                                                    <th>Giảm giá</th>
                                                    <th>Số lượng</th>
                                                    <th>Đã dùng</th>
                                                    <th>Hạn sử dụng</th>
                                                    <th>Trạng thái</th>
                                                    <th>Thao tác</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($allVouchers as $voucher): ?>
                                                    <tr
                                                        class="<?php echo !$voucher['is_active'] ? 'table-warning' : ''; ?>">
                                                        <td><?php echo $voucher['voucher_id']; ?></td>
                                                        <td>
                                                            <code
                                                                class="<?php echo $voucher['is_active'] ? 'bg-light' : 'bg-warning bg-opacity-10'; ?> p-1 rounded">
                                                                                                                                                                                                                                                                                                                                                                                                                                                                        <?php echo htmlspecialchars($voucher['code']); ?>
                                                                                                                                                                                                                                                                                                                                                                                                                                                                    </code>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($voucher['name']); ?></td>
                                                        <td>
                                                            <?php echo number_format($voucher['min_order_amount'] * 1000, 0, ',', '.'); ?>
                                                            VNĐ
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-success">
                                                                <?php echo $voucher['discount_percent']; ?>%
                                                            </span>
                                                        </td>
                                                        <td><?php echo $voucher['quantity']; ?></td>
                                                        <td>
                                                            <span
                                                                class="badge <?php echo $voucher['used_count'] >= $voucher['quantity'] ? 'bg-danger' : 'bg-info'; ?>">
                                                                <?php echo $voucher['used_count']; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if ($voucher['expires_at']): ?>
                                                                <?php
                                                                $expiry = new DateTime($voucher['expires_at']);
                                                                $now = new DateTime();
                                                                $isExpired = $expiry < $now;
                                                                ?>
                                                                <span
                                                                    class="<?php echo $isExpired ? 'text-danger' : 'text-success'; ?>">
                                                                    <?php echo $expiry->format('d/m/Y'); ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="text-muted">Không giới hạn</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $isActive = $voucher['is_active'] &&
                                                                $voucher['used_count'] < $voucher['quantity'] &&
                                                                (!$voucher['expires_at'] || new DateTime($voucher['expires_at']) >= new DateTime());
                                                            ?>
                                                            <span
                                                                class="badge <?php echo $isActive ? 'bg-success' : 'bg-secondary'; ?>">
                                                                <?php echo $isActive ? 'Hoạt động' : 'Không hoạt động'; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm">
                                                                <button class="btn btn-outline-primary"
                                                                    onclick="editVoucher(<?php echo htmlspecialchars(json_encode($voucher)); ?>)"
                                                                    title="Chỉnh sửa">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <button
                                                                    class="btn btn-outline-<?php echo $voucher['is_active'] ? 'warning' : 'success'; ?>"
                                                                    onclick="toggleVoucherStatus(<?php echo $voucher['voucher_id']; ?>, '<?php echo htmlspecialchars($voucher['name']); ?>', <?php echo $voucher['is_active'] ? 'true' : 'false'; ?>)"
                                                                    title="<?php echo $voucher['is_active'] ? 'Tạm dừng' : 'Kích hoạt'; ?>">
                                                                    <i
                                                                        class="fas fa-<?php echo $voucher['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                                </button>
                                                                <button class="btn btn-outline-danger"
                                                                    onclick="deleteVoucher(<?php echo $voucher['voucher_id']; ?>, '<?php echo htmlspecialchars($voucher['name']); ?>')"
                                                                    title="Xóa">
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

                        <!-- Orders Management Tab -->
                        <div class="tab-pane fade" id="orders">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h2><i class="fas fa-shopping-cart"></i> Quản lý Đơn hàng</h2>
                                <div class="btn-group">
                                    <button class="btn btn-outline-primary active" onclick="showOrdersByStatus('all')">
                                        Tất cả (<?php echo $total_orders; ?>)
                                    </button>
                                    <button class="btn btn-outline-warning" onclick="showOrdersByStatus('pending')">
                                        Chờ duyệt (<?php echo count($pendingOrders['orders']); ?>)
                                    </button>
                                    <button class="btn btn-outline-success" onclick="showOrdersByStatus('confirmed')">
                                        Đã duyệt (<?php echo count($confirmedOrders['orders']); ?>)
                                    </button>
                                    <button class="btn btn-outline-danger" onclick="showOrdersByStatus('cancelled')">
                                        Đã hủy (<?php echo count($cancelledOrders['orders']); ?>)
                                    </button>
                                </div>
                            </div>

                            <!-- All Orders Section -->
                            <div id="all-orders" class="order-section">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">
                                            <i class="fas fa-list"></i> Tất cả đơn hàng
                                            <span class="badge bg-primary"><?php echo $total_orders; ?></span>
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Mã đơn</th>
                                                        <th>User ID</th>
                                                        <th>Sản phẩm</th>
                                                        <th>Tổng tiền</th>
                                                        <th>Phương thức</th>
                                                        <th>Voucher</th>
                                                        <th>Ghi chú</th>
                                                        <th>Ngày tạo</th>
                                                        <th>Trạng thái</th>
                                                        <th>Thao tác</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($allOrders as $order): ?>
                                                        <tr
                                                            class="<?php echo $order['status'] === 'cancelled' ? 'table-danger' : ''; ?>">
                                                            <td>
                                                                <code class="p-1 rounded <?php
                                                                echo $order['status'] === 'pending' ? 'bg-warning bg-opacity-10 text-warning' :
                                                                    ($order['status'] === 'confirmed' ? 'bg-success bg-opacity-10 text-success' :
                                                                        'bg-danger bg-opacity-10 text-danger');
                                                                ?>">
                                                                                                                                                                                                                                                                                                                                                                                        #<?php echo $order['order_id']; ?>
                                                                                                                                                                                                                                                                                                                                                                                    </code>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-secondary">
                                                                    <?php echo $order['user_id']; ?>
                                                                </span>
                                                                <br>
                                                                <small class="text-muted">
                                                                    <?php
                                                                    $user = $database->getUserById($order['user_id']);
                                                                    echo $user ? htmlspecialchars($user->username) : 'N/A';
                                                                    ?>
                                                                </small>
                                                            </td>
                                                            <td>
                                                                <small class="text-muted">
                                                                    <?php
                                                                    $productItems = $database->parseProductString($order['product']);
                                                                    echo count($productItems) . ' sản phẩm';
                                                                    ?>
                                                                </small>
                                                            </td>
                                                            <td>
                                                                <strong><?php echo number_format($order['cost'], 2); ?>
                                                                    VNĐ</strong>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-info">
                                                                    <?php echo ucfirst($order['pay_method']); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <?php if ($order['voucher_id']): ?>
                                                                    <?php
                                                                    $voucher = $database->getVoucherById($order['voucher_id']);
                                                                    if ($voucher):
                                                                        ?>
                                                                        <span class="badge bg-success"
                                                                            title="<?php echo htmlspecialchars($voucher['name']); ?>">
                                                                            <?php echo htmlspecialchars($voucher['code']); ?>
                                                                        </span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-secondary">Voucher
                                                                            #<?php echo $order['voucher_id']; ?></span>
                                                                    <?php endif; ?>
                                                                <?php else: ?>
                                                                    <span class="text-muted">-</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if (!empty($order['note'])): ?>
                                                                    <span class="text-truncate d-inline-block"
                                                                        style="max-width: 100px;"
                                                                        title="<?php echo htmlspecialchars($order['note']); ?>">
                                                                        <?php echo htmlspecialchars(substr($order['note'], 0, 30)) . (strlen($order['note']) > 30 ? '...' : ''); ?>
                                                                    </span>
                                                                <?php else: ?>
                                                                    <span class="text-muted">-</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
                                                            </td>
                                                            <td>
                                                                <?php
                                                                $statusText = '';
                                                                $statusClass = '';
                                                                switch ($order['status']) {
                                                                    case 'pending':
                                                                        $statusText = 'Chờ duyệt';
                                                                        $statusClass = 'bg-warning';
                                                                        break;
                                                                    case 'confirmed':
                                                                        $statusText = 'Đã duyệt';
                                                                        $statusClass = 'bg-success';
                                                                        break;
                                                                    case 'cancelled':
                                                                        $statusText = 'Đã hủy';
                                                                        $statusClass = 'bg-danger';
                                                                        break;
                                                                    default:
                                                                        $statusText = ucfirst($order['status']);
                                                                        $statusClass = 'bg-secondary';
                                                                }
                                                                ?>
                                                                <span class="badge <?php echo $statusClass; ?>">
                                                                    <?php echo $statusText; ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <div class="btn-group btn-group-sm">
                                                                    <button class="btn btn-outline-info"
                                                                        onclick="viewOrderDetails(<?php echo $order['order_id']; ?>)"
                                                                        title="Xem chi tiết">
                                                                        <i class="fas fa-eye"></i>
                                                                    </button>
                                                                    <?php if ($order['status'] === 'pending'): ?>
                                                                        <button class="btn btn-outline-success"
                                                                            onclick="approveOrder(<?php echo $order['order_id']; ?>)"
                                                                            title="Duyệt đơn">
                                                                            <i class="fas fa-check"></i>
                                                                        </button>
                                                                        <button class="btn btn-outline-danger"
                                                                            onclick="cancelOrder(<?php echo $order['order_id']; ?>)"
                                                                            title="Hủy đơn">
                                                                            <i class="fas fa-times"></i>
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

                            <!-- Pending Orders Section -->
                            <div id="pending-orders" class="order-section" style="display: none;">
                                <div class="card mb-4">
                                    <div class="card-header bg-warning text-dark">
                                        <h5 class="mb-0">
                                            <i class="fas fa-clock"></i> Đơn hàng chờ duyệt
                                            <span
                                                class="badge bg-dark"><?php echo count($pendingOrders['orders']); ?></span>
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($pendingOrders['orders'])): ?>
                                            <div class="text-center py-3">
                                                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                                <p class="text-muted mb-0">Không có đơn hàng nào cần duyệt!</p>
                                            </div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-striped">
                                                    <thead>
                                                        <tr>
                                                            <th>Mã đơn</th>
                                                            <th>User ID</th>
                                                            <th>Sản phẩm</th>
                                                            <th>Tổng tiền</th>
                                                            <th>Phương thức</th>
                                                            <th>Voucher</th>
                                                            <th>Ghi chú</th>
                                                            <th>Ngày tạo</th>
                                                            <th>Thao tác</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($pendingOrders['orders'] as $order): ?>
                                                            <tr>
                                                                <td>
                                                                    <code
                                                                        class="bg-warning bg-opacity-10 text-warning p-1 rounded">
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                #<?php echo $order['order_id']; ?>
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            </code>
                                                                </td>
                                                                <td>
                                                                    <span class="badge bg-secondary">
                                                                        <?php echo $order['user_id']; ?>
                                                                    </span>
                                                                    <br>
                                                                    <small class="text-muted">
                                                                        <?php
                                                                        $user = $database->getUserById($order['user_id']);
                                                                        echo $user ? htmlspecialchars($user->username) : 'N/A';
                                                                        ?>
                                                                    </small>
                                                                </td>
                                                                <td>
                                                                    <small class="text-muted">
                                                                        <?php
                                                                        $productItems = $database->parseProductString($order['product']);
                                                                        echo count($productItems) . ' sản phẩm';
                                                                        ?>
                                                                    </small>
                                                                    <br>
                                                                    <button class="btn btn-link btn-sm p-0"
                                                                        onclick="showProductDetails('<?php echo htmlspecialchars($order['product']); ?>')">
                                                                        <small>Xem chi tiết</small>
                                                                    </button>
                                                                </td>
                                                                <td>
                                                                    <strong><?php echo number_format($order['cost'], 2); ?>
                                                                        VNĐ</strong>
                                                                </td>
                                                                <td>
                                                                    <span class="badge bg-info">
                                                                        <?php echo ucfirst($order['pay_method']); ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <?php if ($order['voucher_id']): ?>
                                                                        <?php
                                                                        $voucher = $database->getVoucherById($order['voucher_id']);
                                                                        if ($voucher):
                                                                            ?>
                                                                            <span class="badge bg-success"
                                                                                title="<?php echo htmlspecialchars($voucher['name']); ?>">
                                                                                <?php echo htmlspecialchars($voucher['code']); ?>
                                                                            </span>
                                                                        <?php else: ?>
                                                                            <span class="badge bg-secondary">Voucher
                                                                                #<?php echo $order['voucher_id']; ?></span>
                                                                        <?php endif; ?>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">-</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <?php if (!empty($order['note'])): ?>
                                                                        <span class="text-truncate d-inline-block"
                                                                            style="max-width: 100px;"
                                                                            title="<?php echo htmlspecialchars($order['note']); ?>">
                                                                            <?php echo htmlspecialchars(substr($order['note'], 0, 30)) . (strlen($order['note']) > 30 ? '...' : ''); ?>
                                                                        </span>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">-</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
                                                                </td>
                                                                <td>
                                                                    <div class="btn-group btn-group-sm">
                                                                        <button class="btn btn-outline-info"
                                                                            onclick="viewOrderDetails(<?php echo $order['order_id']; ?>)"
                                                                            title="Xem chi tiết">
                                                                            <i class="fas fa-eye"></i>
                                                                        </button>
                                                                        <button class="btn btn-outline-success"
                                                                            onclick="approveOrder(<?php echo $order['order_id']; ?>)"
                                                                            title="Duyệt đơn">
                                                                            <i class="fas fa-check"></i>
                                                                        </button>
                                                                        <button class="btn btn-outline-danger"
                                                                            onclick="cancelOrder(<?php echo $order['order_id']; ?>)"
                                                                            title="Hủy đơn">
                                                                            <i class="fas fa-times"></i>
                                                                        </button>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Confirmed Orders Section -->
                            <div id="confirmed-orders" class="order-section" style="display: none;">
                                <div class="card">
                                    <div class="card-header bg-success text-white">
                                        <h5 class="mb-0">
                                            <i class="fas fa-check-circle"></i> Đơn hàng đã duyệt
                                            <span
                                                class="badge bg-light text-success"><?php echo count($confirmedOrders['orders']); ?></span>
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($confirmedOrders['orders'])): ?>
                                            <div class="text-center py-3">
                                                <i class="fas fa-info-circle fa-2x text-muted mb-2"></i>
                                                <p class="text-muted mb-0">Chưa có đơn hàng nào được duyệt!</p>
                                            </div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-striped">
                                                    <thead>
                                                        <tr>
                                                            <th>Mã đơn</th>
                                                            <th>User ID</th>
                                                            <th>Sản phẩm</th>
                                                            <th>Tổng tiền</th>
                                                            <th>Phương thức</th>
                                                            <th>Voucher</th>
                                                            <th>Ghi chú</th>
                                                            <th>Ngày duyệt</th>
                                                            <th>Thao tác</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($confirmedOrders['orders'] as $order): ?>
                                                            <tr>
                                                                <td>
                                                                    <code
                                                                        class="bg-success bg-opacity-10 text-success p-1 rounded">
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                #<?php echo $order['order_id']; ?>
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            </code>
                                                                </td>
                                                                <td>
                                                                    <span class="badge bg-secondary">
                                                                        <?php echo $order['user_id']; ?>
                                                                    </span>
                                                                    <br>
                                                                    <small class="text-muted">
                                                                        <?php
                                                                        $user = $database->getUserById($order['user_id']);
                                                                        echo $user ? htmlspecialchars($user->username) : 'N/A';
                                                                        ?>
                                                                    </small>
                                                                </td>
                                                                <td>
                                                                    <small class="text-muted">
                                                                        <?php
                                                                        $productItems = $database->parseProductString($order['product']);
                                                                        echo count($productItems) . ' sản phẩm';
                                                                        ?>
                                                                    </small>
                                                                    <br>
                                                                    <button class="btn btn-link btn-sm p-0"
                                                                        onclick="showProductDetails('<?php echo htmlspecialchars($order['product']); ?>')">
                                                                        <small>Xem chi tiết</small>
                                                                    </button>
                                                                </td>
                                                                <td>
                                                                    <strong><?php echo number_format($order['cost'], 2); ?>
                                                                        VNĐ</strong>
                                                                </td>
                                                                <td>
                                                                    <span class="badge bg-info">
                                                                        <?php echo ucfirst($order['pay_method']); ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <?php if ($order['voucher_id']): ?>
                                                                        <?php
                                                                        $voucher = $database->getVoucherById($order['voucher_id']);
                                                                        if ($voucher):
                                                                            ?>
                                                                            <span class="badge bg-success"
                                                                                title="<?php echo htmlspecialchars($voucher['name']); ?>">
                                                                                <?php echo htmlspecialchars($voucher['code']); ?>
                                                                            </span>
                                                                        <?php else: ?>
                                                                            <span class="badge bg-secondary">Voucher
                                                                                #<?php echo $order['voucher_id']; ?></span>
                                                                        <?php endif; ?>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">-</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <?php if (!empty($order['note'])): ?>
                                                                        <span class="text-truncate d-inline-block"
                                                                            style="max-width: 100px;"
                                                                            title="<?php echo htmlspecialchars($order['note']); ?>">
                                                                            <?php echo htmlspecialchars(substr($order['note'], 0, 30)) . (strlen($order['note']) > 30 ? '...' : ''); ?>
                                                                        </span>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">-</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
                                                                </td>
                                                                <td>
                                                                    <div class="btn-group btn-group-sm">
                                                                        <button class="btn btn-outline-info"
                                                                            onclick="viewOrderDetails(<?php echo $order['order_id']; ?>)"
                                                                            title="Xem chi tiết">
                                                                            <i class="fas fa-eye"></i>
                                                                        </button>
                                                                        <button class="btn btn-outline-danger"
                                                                            onclick="cancelOrder(<?php echo $order['order_id']; ?>)"
                                                                            title="Hủy đơn">
                                                                            <i class="fas fa-times"></i>
                                                                        </button>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Cancelled Orders Section -->
                            <div id="cancelled-orders" class="order-section" style="display: none;">
                                <div class="card">
                                    <div class="card-header bg-danger text-white">
                                        <h5 class="mb-0">
                                            <i class="fas fa-times-circle"></i> Đơn hàng đã hủy
                                            <span
                                                class="badge bg-light text-danger"><?php echo count($cancelledOrders['orders']); ?></span>
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($cancelledOrders['orders'])): ?>
                                            <div class="text-center py-3">
                                                <i class="fas fa-smile fa-2x text-success mb-2"></i>
                                                <p class="text-muted mb-0">Tuyệt vời! Không có đơn hàng nào bị hủy!</p>
                                            </div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-striped">
                                                    <thead>
                                                        <tr>
                                                            <th>Mã đơn</th>
                                                            <th>User ID</th>
                                                            <th>Sản phẩm</th>
                                                            <th>Tổng tiền</th>
                                                            <th>Phương thức</th>
                                                            <th>Voucher</th>
                                                            <th>Ghi chú</th>
                                                            <th>Ngày hủy</th>
                                                            <th>Thao tác</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($cancelledOrders['orders'] as $order): ?>
                                                            <tr class="table-danger">
                                                                <td>
                                                                    <code
                                                                        class="bg-danger bg-opacity-10 text-danger p-1 rounded">
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                #<?php echo $order['order_id']; ?>
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            </code>
                                                                </td>
                                                                <td>
                                                                    <span class="badge bg-secondary">
                                                                        <?php echo $order['user_id']; ?>
                                                                    </span>
                                                                    <br>
                                                                    <small class="text-muted">
                                                                        <?php
                                                                        $user = $database->getUserById($order['user_id']);
                                                                        echo $user ? htmlspecialchars($user->username) : 'N/A';
                                                                        ?>
                                                                    </small>
                                                                </td>
                                                                <td>
                                                                    <small class="text-muted">
                                                                        <?php
                                                                        $productItems = $database->parseProductString($order['product']);
                                                                        echo count($productItems) . ' sản phẩm';
                                                                        ?>
                                                                    </small>
                                                                    <br>
                                                                    <button class="btn btn-link btn-sm p-0"
                                                                        onclick="showProductDetails('<?php echo htmlspecialchars($order['product']); ?>')">
                                                                        <small>Xem chi tiết</small>
                                                                    </button>
                                                                </td>
                                                                <td>
                                                                    <strong><?php echo number_format($order['cost'], 2); ?>
                                                                        VNĐ</strong>
                                                                </td>
                                                                <td>
                                                                    <span class="badge bg-secondary">
                                                                        <?php echo ucfirst($order['pay_method']); ?>
                                                                    </span>
                                                                </td>
                                                                <?php if ($order['voucher_id']): ?>
                                                                    <?php
                                                                    $voucher = $database->getVoucherById($order['voucher_id']);
                                                                    if ($voucher):
                                                                        ?>
                                                                        <span class="badge bg-secondary"
                                                                            title="<?php echo htmlspecialchars($voucher['name']); ?>">
                                                                            <?php echo htmlspecialchars($voucher['code']); ?>
                                                                        </span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-secondary">Voucher
                                                                            #<?php echo $order['voucher_id']; ?></span>
                                                                    <?php endif; ?>
                                                                <?php else: ?>
                                                                    <span class="text-muted">-</span>
                                                                <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <?php if (!empty($order['note'])): ?>
                                                                        <span class="text-truncate d-inline-block"
                                                                            style="max-width: 100px;"
                                                                            title="<?php echo htmlspecialchars($order['note']); ?>">
                                                                            <?php echo htmlspecialchars(substr($order['note'], 0, 30)) . (strlen($order['note']) > 30 ? '...' : ''); ?>
                                                                        </span>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">-</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
                                                                </td>
                                                            <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
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
                <form method="POST" enctype="multipart/form-data">
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
                                    <select class="form-select" name="category" required>
                                        <option value="">-- Chọn danh mục --</option>
                                        <?php foreach ($categoriesFromTable as $category): ?>
                                            <option value="<?php echo htmlspecialchars($category['name']); ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
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
                            <label class="form-label">Ảnh sách</label>
                            <input type="file" class="form-control" name="book_image" accept="image/*" required>
                            <div class="form-text">
                                Chỉ chấp nhận file: JPG, JPEG, PNG, GIF, WEBP<br>
                                Khuyến nghị kích thước: 300x400px hoặc tỷ lệ 3:4
                            </div>
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
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_book">
                        <input type="hidden" name="book_id" id="edit_book_id">
                        <input type="hidden" name="current_image" id="edit_current_image">

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
                                    <select class="form-select" name="category" id="edit_category" required>
                                        <option value="">-- Chọn danh mục --</option>
                                        <?php foreach ($categoriesFromTable as $category): ?>
                                            <option value="<?php echo htmlspecialchars($category['name']); ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
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

                        <!-- Current Image Display -->
                        <div class="mb-3">
                            <label class="form-label">Ảnh hiện tại</label>
                            <div id="current_image_preview" class="mb-2">
                                <!-- Current image will be displayed here -->
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Thay đổi ảnh sách (tùy chọn)</label>
                            <input type="file" class="form-control" name="book_image" accept="image/*">
                            <div class="form-text">
                                Để trống nếu không muốn thay đổi ảnh hiện tại<br>
                                Chỉ chấp nhận file: JPG, JPEG, PNG, GIF, WEBP<br>
                                Khuyến nghị kích thước: 300x400px hoặc tỷ lệ 3:4
                            </div>
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
                                <input class="form-check-input" type="checkbox" name="activate_immediately"
                                    id="activateImmediately">
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

    <!-- Add Voucher Modal -->
    <div class="modal fade" id="addVoucherModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tạo Voucher Mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_voucher">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tên Voucher</label>
                                    <input type="text" class="form-control" name="name" required
                                        placeholder="Ví dụ: Giảm giá mùa hè">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Hạn sử dụng (tùy chọn)</label>
                                    <input type="date" class="form-control" name="expires_at">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Mô tả</label>
                            <textarea class="form-control" name="description" rows="2"
                                placeholder="Mô tả về voucher này..."></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Đơn giá tối thiểu (nghìn VNĐ)</label>
                                    <input type="number" step="0.01" class="form-control" name="min_order_amount"
                                        required placeholder="Ví dụ: 100 = 100,000 VNĐ">
                                    <small class="form-text text-muted">Đơn hàng tối thiểu để áp dụng voucher</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Phần trăm giảm (%)</label>
                                    <input type="number" step="0.01" min="0" max="100" class="form-control"
                                        name="discount_percent" required placeholder="Ví dụ: 10">
                                    <small class="form-text text-muted">Từ 0% đến 100%</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Số lượng voucher</label>
                                    <input type="number" min="1" class="form-control" name="quantity" required
                                        placeholder="Ví dụ: 100">
                                    <small class="form-text text-muted">Tổng số voucher có thể sử dụng</small>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Lưu ý:</strong> Mã voucher sẽ được tạo tự động khi bạn tạo voucher.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Tạo Voucher
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Voucher Modal -->
    <div class="modal fade" id="editVoucherModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Sửa Voucher</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_voucher">
                        <input type="hidden" name="voucher_id" id="edit_voucher_id">

                        <div class="mb-3">
                            <label class="form-label">Mã Voucher</label>
                            <input type="text" class="form-control" id="edit_voucher_code_display" readonly>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tên Voucher</label>
                                    <input type="text" class="form-control" name="name" id="edit_voucher_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Hạn sử dụng (tùy chọn)</label>
                                    <input type="date" class="form-control" name="expires_at" id="edit_voucher_expires">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Mô tả</label>
                            <textarea class="form-control" name="description" id="edit_voucher_description"
                                rows="2"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Đơn giá tối thiểu (nghìn VNĐ)</label>
                                    <input type="number" step="0.01" class="form-control" name="min_order_amount"
                                        id="edit_voucher_min_amount" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Phần trăm giảm (%)</label>
                                    <input type="number" step="0.01" min="0" max="100" class="form-control"
                                        name="discount_percent" id="edit_voucher_discount" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Số lượng voucher</label>
                                    <input type="number" min="1" class="form-control" name="quantity"
                                        id="edit_voucher_quantity" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active"
                                    id="edit_voucher_active">
                                <label class="form-check-label" for="edit_voucher_active">
                                    Kích hoạt voucher
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Cập nhật
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

    <!-- Delete Voucher Form -->
    <form id="deleteVoucherForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_voucher">
        <input type="hidden" name="voucher_id" id="delete_voucher_id">
    </form>

    <!-- Toggle Voucher Status Form -->
    <form id="toggleVoucherForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="toggle_voucher_status">
        <input type="hidden" name="voucher_id" id="toggle_voucher_id">
    </form>

    <!-- Approve Order Form -->
    <form id="approveOrderForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="approve_order">
        <input type="hidden" name="order_id" id="approve_order_id">
    </form>

    <!-- Cancel Order Form -->
    <form id="cancelOrderForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="cancel_order">
        <input type="hidden" name="order_id" id="cancel_order_id">
    </form>

    <!-- Update Order Status Form -->
    <form id="updateOrderStatusForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="update_order_status">
        <input type="hidden" name="order_id" id="update_order_id">
        <input type="hidden" name="status" id="update_order_status">
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
            saveActiveTab('books');

            document.getElementById('edit_book_id').value = book.id;
            document.getElementById('edit_title').value = book.title;
            document.getElementById('edit_author').value = book.author;
            document.getElementById('edit_category').value = book.category;
            document.getElementById('edit_price').value = book.price;
            document.getElementById('edit_stock').value = book.stock;
            document.getElementById('edit_description').value = book.description || '';
            document.getElementById('edit_current_image').value = book.image || '';

            // Display current image
            const imagePreview = document.getElementById('current_image_preview');
            if (book.image) {
                imagePreview.innerHTML = `
                    <img src="/DoAn_BookStore/images/books/${book.image}" 
                         alt="Ảnh hiện tại" 
                         class="img-thumbnail" 
                         style="max-width: 150px; max-height: 200px;">
                    <p class="text-muted mt-1 mb-0">
                        <small>Tên file: ${book.image}</small>
                    </p>
                `;
            } else {
                imagePreview.innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Không có ảnh hiện tại
                    </div>
                `;
            }

            const modal = new bootstrap.Modal(document.getElementById('editBookModal'));
            modal.show();
        }

        function deleteBook(id, title) {
            if (confirm(`Bạn có chắc muốn xóa sách "${title}"?`)) {
                saveActiveTab('books');

                // Create delete form if not exists
                let deleteForm = document.getElementById('deleteBookForm');
                if (!deleteForm) {
                    deleteForm = document.createElement('form');
                    deleteForm.id = 'deleteBookForm';
                    deleteForm.method = 'POST';
                    deleteForm.style.display = 'none';

                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'delete_book';

                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'book_id';
                    idInput.id = 'delete_book_id';

                    deleteForm.appendChild(actionInput);
                    deleteForm.appendChild(idInput);
                    document.body.appendChild(deleteForm);
                }

                document.getElementById('delete_book_id').value = id;
                deleteForm.submit();
            }
        }

        // User functions
        function editUser(user) {
            saveActiveTab('users');

            document.getElementById('edit_user_id').value = user.user_id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_permission').value = user.permission;

            const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
            modal.show();
        }

        function deleteUser(id, username) {
            if (confirm(`Bạn có chắc muốn xóa người dùng "${username}"?`)) {
                saveActiveTab('users');

                // Create delete form if not exists
                let deleteForm = document.getElementById('deleteUserForm');
                if (!deleteForm) {
                    deleteForm = document.createElement('form');
                    deleteForm.id = 'deleteUserForm';
                    deleteForm.method = 'POST';
                    deleteForm.style.display = 'none';

                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'delete_user';

                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'user_id';
                    idInput.id = 'delete_user_id';

                    deleteForm.appendChild(actionInput);
                    deleteForm.appendChild(idInput);
                    document.body.appendChild(deleteForm);
                }

                document.getElementById('delete_user_id').value = id;
                deleteForm.submit();
            }
        }

        // Banner management functions
        function deleteBanner(fileName) {
            if (confirm(`Bạn có chắc muốn xóa banner "${fileName}"?`)) {
                saveActiveTab('banners');
                document.getElementById('delete_banner_file').value = fileName;
                document.getElementById('deleteBannerForm').submit();
            }
        }

        // Voucher functions
        function editVoucher(voucher) {
            saveActiveTab('vouchers');

            document.getElementById('edit_voucher_id').value = voucher.voucher_id;
            document.getElementById('edit_voucher_code_display').value = voucher.code;
            document.getElementById('edit_voucher_name').value = voucher.name;
            document.getElementById('edit_voucher_description').value = voucher.description || '';
            document.getElementById('edit_voucher_min_amount').value = voucher.min_order_amount;
            document.getElementById('edit_voucher_discount').value = voucher.discount_percent;
            document.getElementById('edit_voucher_quantity').value = voucher.quantity;
            document.getElementById('edit_voucher_active').checked = voucher.is_active == 1;
            document.getElementById('edit_voucher_expires').value = voucher.expires_at || '';

            const modal = new bootstrap.Modal(document.getElementById('editVoucherModal'));
            modal.show();
        }

        function deleteVoucher(voucherId, name) {
            if (confirm(`Bạn có chắc muốn xóa voucher "${name}"?`)) {
                saveActiveTab('vouchers');
                document.getElementById('delete_voucher_id').value = voucherId;
                document.getElementById('deleteVoucherForm').submit();
            }
        }

        function toggleVoucherStatus(voucherId, name, currentStatus) {
            const action = currentStatus ? 'vô hiệu hóa' : 'kích hoạt';
            if (confirm(`Bạn có chắc muốn ${action} voucher "${name}"?`)) {
                saveActiveTab('vouchers');
                document.getElementById('toggle_voucher_id').value = voucherId;
                document.getElementById('toggleVoucherForm').submit();
            }
        }

        // Order functions
        function viewOrderDetails(orderId) {
            saveActiveTab('orders');
            // Redirect to order details page
            window.location.href = '/DoAn_BookStore/view/admin/order_details.php?order_id=' + orderId;
        }

        function approveOrder(orderId) {
            if (confirm('Bạn có chắc muốn duyệt đơn hàng này?')) {
                saveActiveTab('orders');
                document.getElementById('approve_order_id').value = orderId;
                document.getElementById('approveOrderForm').submit();
            }
        }

        function cancelOrder(orderId) {
            if (confirm('Bạn có chắc muốn hủy đơn hàng này?')) {
                saveActiveTab('orders');
                document.getElementById('cancel_order_id').value = orderId;
                document.getElementById('cancelOrderForm').submit();
            }
        }

        // Save and restore active tab
        function saveActiveTab(tabId) {
            localStorage.setItem('adminActiveTab', tabId);
        }

        function restoreActiveTab() {
            const savedTab = localStorage.getItem('adminActiveTab');
            if (savedTab) {
                const tabElement = document.querySelector(`[href="#${savedTab}"]`);
                if (tabElement) {
                    const tab = new bootstrap.Tab(tabElement);
                    tab.show();

                    // Special handling for orders tab
                    if (savedTab === 'orders') {
                        const savedOrderSection = localStorage.getItem('adminActiveOrderSection') || 'all';
                        setTimeout(() => {
                            showOrdersByStatus(savedOrderSection);
                        }, 100);
                    }
                }
            }
        }

        // Save order section state
        function saveOrderSection(section) {
            localStorage.setItem('adminActiveOrderSection', section);
        }

        // Update showOrdersByStatus function to save state
        function showOrdersByStatus(status) {
            // Save current section
            saveOrderSection(status);

            // Hide all order sections
            document.querySelectorAll('.order-section').forEach(section => {
                section.style.display = 'none';
            });

            // Show selected section
            const sectionId = status + '-orders';
            const section = document.getElementById(sectionId);
            if (section) {
                section.style.display = 'block';
            }

            // Update button states
            document.querySelectorAll('.btn-group .btn').forEach(btn => {
                btn.classList.remove('active');
            });

            // Find and activate the correct button
            const buttons = document.querySelectorAll('.btn-group .btn');
            buttons.forEach(btn => {
                if (btn.textContent.includes('Tất cả') && status === 'all') btn.classList.add('active');
                else if (btn.textContent.includes('Chờ duyệt') && status === 'pending') btn.classList.add('active');
                else if (btn.textContent.includes('Đã duyệt') && status === 'confirmed') btn.classList.add('active');
                else if (btn.textContent.includes('Đã hủy') && status === 'cancelled') btn.classList.add('active');
            });
        }

        // Update tab event listeners
        document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
            tab.addEventListener('shown.bs.tab', function (e) {
                const tabId = this.getAttribute('href').substring(1);
                saveActiveTab(tabId);

                // Update active state
                document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
                this.classList.add('active');

                // Special handling for orders tab
                if (tabId === 'orders') {
                    setTimeout(() => {
                        const savedOrderSection = localStorage.getItem('adminActiveOrderSection') || 'all';
                        showOrdersByStatus(savedOrderSection);
                    }, 100);
                }
            });
        });

        // Update order button event listeners
        document.addEventListener('DOMContentLoaded', function () {
            // Restore active tab on page load
            restoreActiveTab();

            // Add event listeners to order status buttons
            const orderButtons = document.querySelectorAll('.btn-group .btn');
            orderButtons.forEach(btn => {
                btn.addEventListener('click', function () {
                    if (this.textContent.includes('Tất cả')) saveOrderSection('all');
                    else if (this.textContent.includes('Chờ duyệt')) saveOrderSection('pending');
                    else if (this.textContent.includes('Đã duyệt')) saveOrderSection('confirmed');
                    else if (this.textContent.includes('Đã hủy')) saveOrderSection('cancelled');
                });
            });

            // Add event listeners to form submissions to save current tab
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function () {
                    const currentTab = document.querySelector('.nav-link.active');
                    if (currentTab) {
                        const tabId = currentTab.getAttribute('href').substring(1);
                        saveActiveTab(tabId);
                    }
                });
            });
        });

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