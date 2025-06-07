<?php
// Định nghĩa đường dẫn gốc
define('BASE_PATH', __DIR__);

// Lấy URI và loại bỏ phần gốc
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base_path = dirname($_SERVER['SCRIPT_NAME']);
$request_uri = ltrim(str_replace($base_path, '', $request_uri), '/');

// Ưu tiên lấy controller và action từ $_GET nếu có
$controller = isset($_GET['controller']) && !empty($_GET['controller']) ? $_GET['controller'] : null;
$action = isset($_GET['action']) && !empty($_GET['action']) ? $_GET['action'] : null;

// Nếu không có trong $_GET, phân tích từ URI
if (!$controller || !$action) {
    $route_parts = explode('/', $request_uri ?: 'home');
    $controller = $route_parts[0] ?: 'home';
    $action = $route_parts[1] ?? 'index';
}

// Gán vào $_GET để sử dụng ở các tệp khác
$_GET['controller'] = $controller;
$_GET['action'] = $action;

// Nạp controller
try {
    $controller_file = BASE_PATH . '/controller/controller.php';
    if (file_exists($controller_file)) {
        require_once $controller_file;
    } else {
        throw new Exception('Không tìm thấy tệp controller');
    }
} catch (Exception $e) {
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html lang="vi">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Lỗi - BookStore</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>

    <body>
        <div class="container mt-5 text-center">
            <div class="card">
                <div class="card-body">
                    <h1 class="text-danger">Lỗi hệ thống</h1>
                    <p class="text-muted">Xin lỗi, đã có lỗi xảy ra.</p>
                    <p class="text-danger"><?php echo htmlspecialchars($e->getMessage()); ?></p>
                    <a href="/" class="btn btn-primary">Về trang chủ</a>
                </div>
            </div>
        </div>
    </body>

    </html>
    <?php
    exit;
}