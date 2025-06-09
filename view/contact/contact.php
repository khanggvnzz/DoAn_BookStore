<?php

require_once __DIR__ . '/../../model/Database.php';

// Khởi tạo database
$database = new Database();

try {
    // Lấy danh sách admin (permission = 'admin')
    $admins = $database->getUsersByPermission('admin');
} catch (Exception $e) {
    error_log('Error getting admin contacts: ' . $e->getMessage());
    $admins = [];
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liên Hệ Admin - BookStore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .contact-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .contact-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }

        .admin-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            margin: 0 auto 1rem;
        }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 3rem;
        }

        .contact-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .btn-contact {
            border-radius: 25px;
            padding: 0.5rem 1.5rem;
            margin: 0.25rem;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <div class="page-header">
        <div class="container">
            <div class="row">
                <div class="col-lg-12 text-center">
                    <!-- Back to Home Button -->
                    <div class="mb-3">
                        <a href="/DoAn_BookStore/index.php?controller=books&action=list"
                            class="btn btn-outline-light btn-lg">
                            <i class="fas fa-home me-2"></i>
                            Quay về trang chủ
                        </a>
                    </div>

                    <h1 class="display-4 mb-3">
                        <i class="fas fa-envelope me-3"></i>
                        Liên Hệ Admin
                    </h1>
                    <p class="lead">
                        Bạn cần hỗ trợ? Hãy liên hệ với đội ngũ quản trị viên của chúng tôi
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (empty($admins)): ?>
            <!-- No Admin Found -->
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="alert alert-warning text-center" role="alert">
                        <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                        <h4>Không tìm thấy thông tin admin</h4>
                        <p class="mb-0">Hiện tại không có thông tin liên hệ admin nào. Vui lòng thử lại sau.</p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Contact Info Section -->
            <div class="row mb-5">
                <div class="col-lg-12">
                    <div class="contact-info">
                        <h3 class="text-center mb-4">
                            <i class="fas fa-info-circle text-primary me-2"></i>
                            Thông Tin Liên Hệ
                        </h3>
                        <div class="row">
                            <div class="col-md-4 text-center mb-3">
                                <i class="fas fa-clock text-primary fs-2 mb-2"></i>
                                <h5>Giờ Làm Việc</h5>
                                <p class="text-muted mb-0">
                                    Thứ 2 - Thứ 6: 8:00 - 17:00<br>
                                    Thứ 7 - Chủ nhật: 9:00 - 16:00
                                </p>
                            </div>
                            <div class="col-md-4 text-center mb-3">
                                <i class="fas fa-reply text-success fs-2 mb-2"></i>
                                <h5>Thời Gian Phản Hồi</h5>
                                <p class="text-muted mb-0">
                                    Email: Trong vòng 24 giờ<br>
                                    Điện thoại: Ngay lập tức
                                </p>
                            </div>
                            <div class="col-md-4 text-center mb-3">
                                <i class="fas fa-language text-info fs-2 mb-2"></i>
                                <h5>Ngôn Ngữ Hỗ Trợ</h5>
                                <p class="text-muted mb-0">
                                    Tiếng Việt<br>
                                    English
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Admin Cards -->
            <div class="row">
                <div class="col-lg-12 mb-4">
                    <h2 class="text-center mb-5">
                        <i class="fas fa-users-cog text-primary me-2"></i>
                        Đội Ngũ Quản Trị Viên
                        <small class="text-muted">
                            (<?php echo count($admins); ?> admin có sẵn)
                        </small>
                    </h2>
                </div>

                <?php foreach ($admins as $index => $admin): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card contact-card h-100">
                            <div class="card-body text-center">
                                <!-- Admin Avatar -->
                                <div class="admin-avatar">
                                    <?php
                                    $initials = '';
                                    if (!empty($admin->name)) {
                                        $nameParts = explode(' ', trim($admin->name));
                                        $initials = strtoupper(substr($nameParts[0], 0, 1));
                                        if (count($nameParts) > 1) {
                                            $initials .= strtoupper(substr(end($nameParts), 0, 1));
                                        }
                                    } else {
                                        $initials = strtoupper(substr($admin->username, 0, 2));
                                    }
                                    echo $initials;
                                    ?>
                                </div>

                                <!-- Admin Info -->
                                <h5 class="card-title mb-2">
                                    <?php echo htmlspecialchars($admin->name ?: $admin->username); ?>
                                </h5>

                                <p class="text-muted mb-1">
                                    <i class="fas fa-user-shield me-1"></i>
                                    Quản trị viên
                                </p>

                                <!-- Contact Information -->
                                <div class="contact-details mb-3">
                                    <?php if (!empty($admin->email)): ?>
                                        <div class="mb-2">
                                            <i class="fas fa-envelope text-primary me-2"></i>
                                            <small class="text-break">
                                                <?php echo htmlspecialchars($admin->email); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($admin->phone)): ?>
                                        <div class="mb-2">
                                            <i class="fas fa-phone text-success me-2"></i>
                                            <small>
                                                <?php echo htmlspecialchars($admin->phone); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>

                                    <div class="mb-2">
                                        <i class="fas fa-user text-info me-2"></i>
                                        <small class="text-muted">
                                            @<?php echo htmlspecialchars($admin->username); ?>
                                        </small>
                                    </div>
                                </div>

                                <!-- Contact Buttons -->
                                <div class="mt-auto">
                                    <?php if (!empty($admin->email)): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($admin->email); ?>?subject=Liên hệ từ BookStore"
                                            class="btn btn-primary btn-contact btn-sm">
                                            <i class="fas fa-envelope me-1"></i>
                                            Gửi Email
                                        </a>
                                    <?php endif; ?>

                                    <?php if (!empty($admin->phone)): ?>
                                        <a href="tel:<?php echo htmlspecialchars($admin->phone); ?>"
                                            class="btn btn-success btn-contact btn-sm">
                                            <i class="fas fa-phone me-1"></i>
                                            Gọi điện
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Card Footer with Status -->
                            <div class="card-footer bg-light text-center">
                                <small class="text-success">
                                    <i class="fas fa-circle me-1" style="font-size: 0.5rem;"></i>
                                    Sẵn sàng hỗ trợ
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Additional Contact Options -->
            <div class="row mt-5">
                <div class="col-lg-12">
                    <div class="card border-0 bg-primary text-white">
                        <div class="card-body text-center p-4">
                            <h3 class="mb-4">
                                <i class="fas fa-question-circle me-2"></i>
                                Cần Hỗ Trợ Khẩn Cấp?
                            </h3>
                            <p class="lead mb-4">
                                Nếu bạn gặp vấn đề khẩn cấp, hãy liên hệ với chúng tôi qua các kênh sau:
                            </p>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <h5>
                                        <i class="fas fa-envelope me-2"></i>
                                        Email chung
                                    </h5>
                                    <a href="mailto:khangvu782004+support_bookstore@gmail.com"
                                        class="text-white text-decoration-none">
                                        khangvu782004+support_bookstore@gmail.com
                                    </a>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <h5>
                                        <i class="fas fa-phone me-2"></i>
                                        Hotline
                                    </h5>
                                    <a href="tel:1900-123-456" class="text-white text-decoration-none">
                                        1900-123-456
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FAQ Section -->
            <div class="row mt-5">
                <div class="col-lg-12">
                    <h3 class="text-center mb-4">
                        <i class="fas fa-question-circle text-primary me-2"></i>
                        Câu Hỏi Thường Gặp
                    </h3>
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faq1">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#collapse1">
                                    Làm thế nào để liên hệ với admin?
                                </button>
                            </h2>
                            <div id="collapse1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Bạn có thể liên hệ với admin thông qua email hoặc số điện thoại được hiển thị ở trên.
                                    Chúng tôi cam kết phản hồi trong vòng 24 giờ đối với email và ngay lập tức đối với cuộc
                                    gọi.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faq2">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#collapse2">
                                    Tôi có thể báo cáo vấn đề kỹ thuật như thế nào?
                                </button>
                            </h2>
                            <div id="collapse2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Vui lòng gửi email chi tiết về vấn đề kỹ thuật bạn gặp phải,
                                    kèm theo ảnh chụp màn hình nếu có thể. Đội ngũ kỹ thuật sẽ hỗ trợ bạn sớm nhất.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faq3">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#collapse3">
                                    Thời gian phản hồi là bao lâu?
                                </button>
                            </h2>
                            <div id="collapse3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    - Email: Trong vòng 24 giờ làm việc<br>
                                    - Điện thoại: Ngay lập tức trong giờ làm việc<br>
                                    - Các vấn đề khẩn cấp sẽ được ưu tiên xử lý
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Back to Home Section -->
            <div class="row mt-5">
                <div class="col-lg-12 text-center">
                    <div class="card border-0 bg-light">
                        <div class="card-body p-4">
                            <h4 class="mb-3">
                                <i class="fas fa-arrow-left me-2 text-primary"></i>
                                Quay lại mua sắm
                            </h4>
                            <p class="text-muted mb-4">
                                Khám phá hàng nghìn cuốn sách tại BookStore của chúng tôi
                            </p>
                            <div class="d-flex justify-content-center gap-3 flex-wrap">
                                <a href="../../index.php" class="btn btn-primary btn-lg">
                                    <i class="fas fa-home me-2"></i>
                                    Trang chủ
                                </a>
                                <a href="../products/books.php" class="btn btn-outline-primary btn-lg">
                                    <i class="fas fa-book me-2"></i>
                                    Xem sách
                                </a>
                                <a href="../cart/cart.php" class="btn btn-outline-success btn-lg">
                                    <i class="fas fa-shopping-cart me-2"></i>
                                    Giỏ hàng
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer Spacing -->
    <div style="height: 50px;"></div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function () {
            // Add smooth scroll effect for back button
            document.querySelectorAll('a[href^="../../"]').forEach(link => {
                link.addEventListener('click', function (e) {
                    // Add loading effect
                    const icon = this.querySelector('i');
                    if (icon) {
                        const originalClass = icon.className;
                        icon.className = 'fas fa-spinner fa-spin me-2';

                        setTimeout(() => {
                            icon.className = originalClass;
                        }, 1000);
                    }
                });
            });

            // Animate cards on scroll
            const cards = document.querySelectorAll('.contact-card');

            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver(function (entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '0';
                        entry.target.style.transform = 'translateY(20px)';

                        setTimeout(() => {
                            entry.target.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                            entry.target.style.opacity = '1';
                            entry.target.style.transform = 'translateY(0)';
                        }, 100);

                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            cards.forEach(card => {
                observer.observe(card);
            });

            // Copy email to clipboard
            document.querySelectorAll('[href^="mailto:"]').forEach(emailLink => {
                emailLink.addEventListener('click', function (e) {
                    e.preventDefault();
                    const email = this.getAttribute('href').replace('mailto:', '').split('?')[0];

                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(email).then(() => {
                            showToast('Email đã được sao chép: ' + email);
                        });
                    }

                    // Still open email client
                    window.location.href = this.getAttribute('href');
                });
            });
        });

        // Toast notification function
        function showToast(message) {
            const toast = document.createElement('div');
            toast.className = 'position-fixed top-0 end-0 p-3';
            toast.style.zIndex = '9999';
            toast.innerHTML = `
                <div class="toast show" role="alert">
                    <div class="toast-header">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        <strong class="me-auto">Thông báo</strong>
                        <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">${message}</div>
                </div>
            `;

            document.body.appendChild(toast);

            setTimeout(() => {
                toast.remove();
            }, 3000);
        }
    </script>
</body>

</html>