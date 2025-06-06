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
    }
}

// Get data for display using Database methods
$books = $database->fetchAll("SELECT * FROM books ORDER BY title");
$users = $database->fetchAll("SELECT id, username, name, email, permission FROM users ORDER BY username");
$categories = $database->fetchAll("SELECT DISTINCT category FROM books WHERE category IS NOT NULL AND category != '' ORDER BY category");

// Get statistics using Database count method
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

    <!-- Delete Forms -->
    <form id="deleteBookForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_book">
        <input type="hidden" name="book_id" id="delete_book_id">
    </form>

    <form id="deleteUserForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_user">
        <input type="hidden" name="user_id" id="delete_user_id">
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