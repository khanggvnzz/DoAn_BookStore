<?php
/**
 * Tạo URL tương đối đến thư mục gốc của ứng dụng
 * 
 * @param string $path Đường dẫn tương đối từ thư mục gốc
 * @return string URL hoàn chỉnh
 */
function baseUrl($path = '')
{
    // Xác định thư mục gốc so với document root
    $rootDir = 'BookStore';
    // Loại bỏ dấu / ở đầu nếu có
    $path = ltrim($path, '/');
    // Trả về đường dẫn đầy đủ
    return '/' . $rootDir . ($path ? '/' . $path : '');
}

/**
 * Tạo URL tuyệt đối đến thư mục gốc của ứng dụng
 * 
 * @param string $path Đường dẫn tương đối từ thư mục gốc
 * @return string URL hoàn chỉnh bao gồm domain
 */
function absoluteUrl($path = '')
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domain = $_SERVER['HTTP_HOST'];
    return $protocol . $domain . baseUrl($path);
}

/**
 * Tạo đường dẫn tương đối đến thư mục view
 * 
 * @param string $path Đường dẫn tương đối từ thư mục view
 * @return string Đường dẫn hoàn chỉnh
 */
function viewUrl($path = '')
{
    return baseUrl('view/' . ltrim($path, '/'));
}

/**
 * Tạo đường dẫn tương đối đến thư mục assets
 * 
 * @param string $path Đường dẫn tương đối từ thư mục assets
 * @return string Đường dẫn hoàn chỉnh
 */
function assetUrl($path = '')
{
    return baseUrl('view/' . ltrim($path, '/'));
}

/**
 * Kiểm tra nếu đường dẫn hiện tại khớp với đường dẫn được chỉ định
 * 
 * @param string $path Đường dẫn cần so sánh
 * @return bool True nếu đường dẫn hiện tại khớp
 */
function isCurrentUrl($path)
{
    $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $basePath = baseUrl($path);
    return $currentPath === $basePath;
}