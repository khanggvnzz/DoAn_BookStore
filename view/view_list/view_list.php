<?php

require_once __DIR__ . '/../../model/Database.php';
require_once __DIR__ . '/../../model/BookModel.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$perPage = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 18;
$allowedPerPage = [10, 15, 30, 50, 70, 100];
if (!in_array($perPage, $allowedPerPage)) {
    $perPage = 18; // Default fallback
}

// Get books with pagination (15 books per page)
try {
    $result = $database->getBooksWithPagination($page, $perPage, $search, $category);
    $books = $result['books'];
    $pagination = $result['pagination'];
} catch (Exception $e) {
    $books = [];
    $pagination = [];
    $error_message = "Error loading books: " . $e->getMessage();
}

$baseUrl = '/DoAn_BookStore';
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh Sách - BookStore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/DoAn_BookStore/view/view_list/view_list.css">
</head>

<body>
    <?php include 'view/navigation/navigation.php'; ?>
    <?php include 'view/banner/banner.php'; ?>
    <div class="container-fluid py-3">
        <!-- Reduced padding -->
        <div class="row mb-3">
            <!-- Reduced margin -->
            <div class="col-12">
                <h1 class="text-center mb-3">
                    <!-- Reduced margin -->
                    <i class="fas fa-book"></i> Danh Sách
                </h1>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <?php if (!empty($books)): ?>
                <?php foreach ($books as $book): ?>
                    <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                        <!-- Changed to col-xl-2 for smaller cards -->
                        <div class="card book-card h-100">
                            <div class="card-img-top position-relative">
                                <?php if (!empty($book['image'])): ?>
                                    <img src="images/books/<?php echo htmlspecialchars($book['image']); ?>"
                                        class="img-fluid book-image" alt="<?php echo htmlspecialchars($book['title']); ?>">
                                <?php else: ?>
                                    <div class="bg-light d-flex align-items-center justify-content-center book-image">
                                        <i class="fas fa-book fa-2x text-muted"></i>
                                        <!-- Smaller icon -->
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($book['category'])): ?>
                                    <span class="position-absolute top-0 end-0 m-1 book-category">
                                        <!-- Smaller margin -->
                                        <?php echo htmlspecialchars($book['category']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="card-body d-flex flex-column">
                                <h6 class="card-title book-title mb-2">
                                    <!-- Changed to h6 -->
                                    <?php echo htmlspecialchars($book['title']); ?>
                                </h6>

                                <p class="book-author mb-1">
                                    <!-- Reduced margin -->
                                    <i class="fas fa-user"></i>
                                    <?php echo htmlspecialchars($book['author']); ?>
                                </p>

                                <?php if (!empty($book['publisher'])): ?>
                                    <p class="text-muted mb-1 book-meta">
                                        <!-- Added book-meta class -->
                                        <i class="fas fa-building"></i>
                                        <?php echo htmlspecialchars(substr($book['publisher'], 0, 20)) . (strlen($book['publisher']) > 20 ? '...' : ''); ?>
                                    </p>
                                <?php endif; ?>

                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <!-- Reduced margin -->
                                    <small class="text-muted book-meta">
                                        <i class="fas fa-calendar-alt"></i>
                                        <?php echo date('d/m/Y', strtotime($book['created_at'])); ?>
                                    </small>
                                </div>

                                <?php if (!empty($book['description'])): ?>
                                    <p class="book-description mb-2">
                                        <?php echo htmlspecialchars(substr($book['description'], 0, 80)) . '...'; ?>
                                    </p>
                                <?php endif; ?>

                                <div class="mt-auto">
                                    <!-- Beautiful Price Container -->
                                    <div class="price-container">
                                        <p class="book-price">
                                            <i class="fas fa-tags price-icon"></i>
                                            <?php
                                            $finalPrice = $book['price'] * 1000;
                                            echo number_format($finalPrice, 0, ',', '.');
                                            ?>
                                            <span class="currency">VNĐ</span>
                                        </p>
                                    </div>

                                    <div class="d-grid gap-1 btn-group-custom">
                                        <!-- Reduced gap -->
                                        <button class="btn btn-primary-custom btn-sm btn-custom">
                                            <i class="fas fa-shopping-cart"></i> Thêm
                                        </button>
                                        <button class="btn btn-outline-custom btn-sm btn-custom">
                                            <i class="fas fa-eye"></i> Chi tiết
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info text-center" role="alert">
                        <i class="fas fa-info-circle fa-3x mb-3"></i>
                        <h4>Không có sách nào</h4>
                        <p>Hiện tại chưa có sách nào trong cơ sở dữ liệu.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($pagination) && $pagination['total_pages'] > 1): ?>
            <div class="row mt-3">
                <!-- Reduced margin -->
                <div class="col-12">
                    <?php echo $database->generatePaginationHTML($pagination, $baseUrl); ?>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'view/footer/footer.php'; ?>
</body>

</html>