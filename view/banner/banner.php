<!-- Promotion Banner -->

<head>
    <link rel="stylesheet" href="/DoAn_BookStore/view/banner/banner.css">
</head>
<div class="promo-banner">
    <div class="container-fluid p-0">
        <!-- Full-width Carousel -->
        <div id="promotionCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="4000">
            <div class="carousel-inner">
                <?php
                // Get all ad images from the ads directory
                $adsDir = __DIR__ . '/../../images/ads/';
                $adFiles = glob($adsDir . "*.{jpg,jpeg,png,gif}", GLOB_BRACE);

                if (!empty($adFiles)) {
                    foreach ($adFiles as $index => $adFile) {
                        $adImage = basename($adFile);
                        $isActive = $index === 0 ? 'active' : '';
                        echo "<div class='carousel-item $isActive'>
                                        <div class='promotion-image-container'>
                                            <img src='images/ads/" . htmlspecialchars($adImage) . "' 
                                                alt='Book promotion' class='promotion-image'>
                                        </div>
                                      </div>";
                    }
                } else {
                    echo "<div class='carousel-item active'>
                                    <div class='promotion-image-container'>
                                        <img src='images/default-ad.jpg' alt='Book promotion' 
                                            class='promotion-image'>
                                    </div>
                                  </div>";
                }
                ?>
            </div>
            <!-- Add carousel controls -->
            <button class="carousel-control-prev" type="button" data-bs-target="#promotionCarousel"
                data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#promotionCarousel"
                data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
        </div>
    </div>
</div>