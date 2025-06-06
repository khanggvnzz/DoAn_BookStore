<?php

// Include required models
require_once BASE_PATH . '/model/Database.php';
require_once BASE_PATH . '/model/BookModel.php';

// Get controller and action from URL parameters
$controller = isset($_GET['controller']) ? $_GET['controller'] : 'home';
$action = isset($_GET['action']) ? $_GET['action'] : 'index';
var_dump($controller, $action); // Debugging line to check controller and action

// Route handling
try {
    switch ($controller) {
        case 'home':
            handleHomeController($action);
            break;

        case 'books':
            handleBooksController($action);
            break;

        case 'admin':
            handleAdminController($action);
            break;

        case 'auth':
            handleAuthController($action);
            break;

        default:
            // Handle unknown controller - redirect to home
            header('Location: /');
            exit;
    }
} catch (Exception $e) {
    // Handle controller errors
    showErrorPage($e->getMessage());
}

// Home controller functions
function handleHomeController($action)
{
    switch ($action) {
        case 'index':
        default:
            // Show home page with featured books
            showHomePage();
            break;
    }
}

// Books controller functions
function handleBooksController($action)
{
    switch ($action) {
        case 'index':
        case 'list':
            showBooksList();
            break;

        case 'view':
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            showBookDetail($id);
            break;
        case 'search':
            $query = isset($_GET['q']) ? $_GET['q'] : '';
            showBooksSearch($query);
            break;

        default:
            showBooksList();
            break;
    }
}

// Admin controller functions
function handleAdminController($action)
{
    // Check if user is admin (add authentication check here)
    if (!isAdmin()) {
        header('Location: /auth/login');
        exit;
    }

    switch ($action) {
        case 'index':
        case 'dashboard':
            showAdminDashboard();
            break;

        case 'books':
            showAdminBooks();
            break;

        case 'add-book':
            handleAddBook();
            break;

        case 'edit-book':
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            handleEditBook($id);
            break;

        case 'delete-book':
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            handleDeleteBook($id);
            break;

        default:
            showAdminDashboard();
            break;
    }
}

// Auth controller functions
function handleAuthController($action)
{
    switch ($action) {
        case 'login':
            handleLogin();
            break;

        case 'logout':
            handleLogout();
            break;

        case 'register':
            handleRegister();
            break;

        default:
            handleLogin();
            break;
    }
}

// View functions
function showHomePage()
{
    // Get featured books for home page
    $database = new Database();
    $featuredBooks = $database->fetchAll("SELECT * FROM books ORDER BY created_at DESC LIMIT 8");

    // Include home view
    include BASE_PATH . '/view/view_list/view_list.php';
}

function showBooksList()
{
    // Include books list view
    include BASE_PATH . '/view/view_list/view_list.php';
}


function showBookDetail($id)
{
    if ($id <= 0) {
        header('Location: /books');
        exit;
    }

    $database = new Database();
    $book = $database->fetch("SELECT * FROM books WHERE id = :id", ['id' => $id]);

    if (!$book) {
        showErrorPage("Không tìm thấy sách");
        return;
    }

    // Include book detail view
    include BASE_PATH . '/view/book_detail/book_detail.php';
}

function showBooksSearch($query)
{
    $database = new Database();
    $books = [];

    if (!empty(trim($query))) {
        $searchTerm = '%' . trim($query) . '%';
        $books = $database->fetchAll(
            "SELECT * FROM books WHERE title LIKE :search OR author LIKE :search  OR category LIKE :search ORDER BY created_at DESC",
            ['search' => $searchTerm]
        );
    }

    // Include search results view
    include BASE_PATH . '/view/search/search.php';
}

function showAdminDashboard()
{
    $database = new Database();
    $stats = [
        'total_books' => $database->count('books'),
        'total_categories' => $database->count('categories'),
        'recent_books' => $database->fetchAll("SELECT * FROM books ORDER BY created_at DESC LIMIT 5")
    ];

    // Include admin dashboard view
    include BASE_PATH . '/view/admin/dashboard.php';
}

function showAdminBooks()
{
    $database = new Database();
    $books = $database->fetchAll("SELECT * FROM books ORDER BY created_at DESC");

    // Include admin books view
    include BASE_PATH . '/view/admin/books.php';
}

function handleAddBook()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Process form submission
        $database = new Database();

        $bookData = [
            'title' => $_POST['title'] ?? '',
            'author' => $_POST['author'] ?? '',
            'price' => $_POST['price'] ?? 0,
            'description' => $_POST['description'] ?? '',
            'category' => $_POST['category'] ?? '',
            'image' => $_POST['image'] ?? '',
            'publisher' => $_POST['publisher'] ?? '',
            'category_id' => $_POST['category_id'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $result = $database->insert('books', $bookData);

        if ($result) {
            $_SESSION['success'] = "Thêm sách thành công!";
            header('Location: /admin/books');
            exit;
        } else {
            $_SESSION['error'] = "Có lỗi xảy ra khi thêm sách!";
        }
    }

    // Include add book form view
    include BASE_PATH . '/view/admin/add_book.php';
}

function handleEditBook($id)
{
    $database = new Database();
    $book = $database->fetch("SELECT * FROM books WHERE id = :id", ['id' => $id]);

    if (!$book) {
        $_SESSION['error'] = "Không tìm thấy sách!";
        header('Location: /admin/books');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Process form submission
        $bookData = [
            'title' => $_POST['title'] ?? '',
            'author' => $_POST['author'] ?? '',
            'price' => $_POST['price'] ?? 0,
            'description' => $_POST['description'] ?? '',
            'category' => $_POST['category'] ?? '',
            'image' => $_POST['image'] ?? '',
            'publisher' => $_POST['publisher'] ?? '',
            'category_id' => $_POST['category_id'] ?? null
        ];

        $result = $database->update('books', $bookData, 'id = :id', ['id' => $id]);

        if ($result) {
            $_SESSION['success'] = "Cập nhật sách thành công!";
            header('Location: /admin/books');
            exit;
        } else {
            $_SESSION['error'] = "Có lỗi xảy ra khi cập nhật sách!";
        }
    }

    // Include edit book form view
    include BASE_PATH . '/view/admin/edit_book.php';
}

function handleDeleteBook($id)
{
    $database = new Database();
    $result = $database->delete('books', 'id = :id', ['id' => $id]);

    if ($result) {
        $_SESSION['success'] = "Xóa sách thành công!";
    } else {
        $_SESSION['error'] = "Có lỗi xảy ra khi xóa sách!";
    }

    header('Location: /admin/books');
    exit;
}

function handleLogin()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        // Validate input
        if (empty($username)) {
            $_SESSION['error'] = "Vui lòng nhập tên đăng nhập hoặc email!";
        } elseif (empty($password)) {
            $_SESSION['error'] = "Vui lòng nhập mật khẩu!";
        } else {
            try {
                $database = new Database();

                // Authenticate user using SHA256 method from Database class
                $userData = $database->authenticateUser($username, $password);

                if ($userData) {
                    // Login successful - Set session variables
                    $_SESSION['user_id'] = $userData['id'];
                    $_SESSION['username'] = $userData['username'];
                    $_SESSION['user_name'] = $userData['name'];
                    $_SESSION['user_email'] = $userData['email'];
                    $_SESSION['user_permission'] = $userData['permission'];

                    // Set admin session if user is admin
                    if ($userData['permission'] === 'admin') {
                        $_SESSION['admin'] = true;
                    }

                    // Set remember me cookie if checked
                    if ($remember) {
                        $token = bin2hex(random_bytes(32));
                        // Set secure cookie for 30 days
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

                    $_SESSION['success'] = "Đăng nhập thành công! Chào mừng " . $userData['name'];

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
                    $_SESSION['error'] = "Tên đăng nhập hoặc mật khẩu không đúng!";
                }
            } catch (Exception $e) {
                $_SESSION['error'] = "Có lỗi xảy ra khi đăng nhập: " . $e->getMessage();
                error_log('Login error: ' . $e->getMessage());
            }
        }

        // If there's an error, redirect back to login page
        header('Location: /DoAn_BookStore/?controller=auth&action=login');
        exit;
    }

    // Show login form
    include BASE_PATH . '/view/login/login.php';
}

function handleRegister()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');

        $errors = [];
        $database = new Database();

        // Validate input
        if (empty($name) || strlen($name) < 2) {
            $errors[] = 'Tên phải có ít nhất 2 ký tự';
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email không hợp lệ';
        } elseif ($database->emailExists($email)) {
            $errors[] = 'Email đã tồn tại';
        }

        if (empty($username) || strlen($username) < 3) {
            $errors[] = 'Tên đăng nhập phải có ít nhất 3 ký tự';
        } elseif ($database->usernameExists($username)) {
            $errors[] = 'Tên đăng nhập đã tồn tại';
        }

        // Validate password using Database method
        $passwordValidation = $database->validatePasswordStrength($password);
        if (!$passwordValidation['valid']) {
            $errors = array_merge($errors, $passwordValidation['errors']);
        }

        if ($password !== $confirmPassword) {
            $errors[] = 'Mật khẩu xác nhận không khớp';
        }

        if (!empty($phone) && !preg_match('/^[0-9+\-\s()]{10,15}$/', $phone)) {
            $errors[] = 'Số điện thoại không hợp lệ';
        }

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
                    'permission' => 'user',
                    'status' => 'active'
                ];

                // Use Database method to create user with SHA256 hash
                $result = $database->createUserWithSHA256($userData);

                if ($result) {
                    $_SESSION['success'] = "Đăng ký thành công! Vui lòng đăng nhập.";
                    header('Location: /DoAn_BookStore/?controller=auth&action=login');
                    exit;
                } else {
                    $_SESSION['error'] = "Có lỗi xảy ra khi đăng ký!";
                }
            } catch (Exception $e) {
                $_SESSION['error'] = "Có lỗi xảy ra: " . $e->getMessage();
                error_log('Registration error: ' . $e->getMessage());
            }
        } else {
            $_SESSION['error'] = implode('<br>', $errors);
        }
    }

    // Include register view
    include BASE_PATH . '/view/auth/register.php';
}

function handleLogout()
{
    // Clear all session data
    session_unset();

    // Delete session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }

    // Delete remember me cookie
    if (isset($_COOKIE['remember_user'])) {
        setcookie('remember_user', '', time() - 3600, '/', '', false, true);
    }

    // Destroy session
    session_destroy();

    // Start new session for success message
    session_start();
    $_SESSION['success'] = "Đăng xuất thành công!";

    header('Location: /DoAn_BookStore/');
    exit;
}

function handleChangePassword()
{
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error'] = "Vui lòng đăng nhập để thay đổi mật khẩu!";
        header('Location: /DoAn_BookStore/?controller=auth&action=login');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $errors = [];
        $database = new Database();

        if (empty($currentPassword)) {
            $errors[] = 'Vui lòng nhập mật khẩu hiện tại';
        }

        // Validate new password using Database method
        $passwordValidation = $database->validatePasswordStrength($newPassword);
        if (!$passwordValidation['valid']) {
            $errors = array_merge($errors, $passwordValidation['errors']);
        }

        if ($newPassword !== $confirmPassword) {
            $errors[] = 'Mật khẩu mới và xác nhận không khớp';
        }

        if (empty($errors)) {
            // Use Database method to change password
            $result = $database->changeUserPassword($_SESSION['user_id'], $currentPassword, $newPassword);

            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
                header('Location: /DoAn_BookStore/?controller=user&action=profile');
                exit;
            } else {
                $_SESSION['error'] = $result['message'];
            }
        } else {
            $_SESSION['error'] = implode('<br>', $errors);
        }
    }

    // Include change password view
    include BASE_PATH . '/view/user/change_password.php';
}

// Check remember me cookie on page load
function checkRememberMe()
{
    if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_user'])) {
        $database = new Database();
        $userData = $database->fetch(
            "SELECT * FROM users WHERE username = :username AND status = 'active'",
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
        } else {
            // Invalid cookie, remove it
            setcookie('remember_user', '', time() - 3600, '/', '', false, true);
        }
    }
}

// Helper function to check authentication with redirect
function requireAuth($redirectTo = null)
{
    if (!isset($_SESSION['user_id'])) {
        if ($redirectTo) {
            $_SESSION['redirect_after_login'] = $redirectTo;
        }
        $_SESSION['error'] = "Vui lòng đăng nhập để tiếp tục!";
        header('Location: /DoAn_BookStore/?controller=auth&action=login');
        exit;
    }
}

// Helper function to check admin permission
function requireAdmin()
{
    requireAuth();

    if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
        $_SESSION['error'] = "Bạn không có quyền truy cập trang này!";
        header('Location: /DoAn_BookStore/');
        exit;
    }
}

// Update isAdmin function to be more robust
function isAdmin()
{
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true &&
        isset($_SESSION['user_permission']) && $_SESSION['user_permission'] === 'admin';
}

// ...existing code...
?>