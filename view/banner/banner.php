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
                // Function to get active banners from config
                function getActiveBanners() {
                    $configFile = __DIR__ . '/../../config/active_banners.json';
                    if (file_exists($configFile)) {
                        $content = file_get_contents($configFile);
                        return json_decode($content, true) ?: [];
                    }
                    return [];
                }

                // Get active banners list
                $activeBanners = getActiveBanners();
                $adsDir = __DIR__ . '/../../images/ads/';
                $validBanners = [];

                // Filter and validate active banners
                foreach ($activeBanners as $banner) {
                    $bannerPath = $adsDir . $banner;
                    if (file_exists($bannerPath)) {
                        $validBanners[] = $banner;
                    }
                }

                if (!empty($validBanners)) {
                    foreach ($validBanners as $index => $bannerFile) {
                        $isActive = $index === 0 ? 'active' : '';
                        echo "<div class='carousel-item $isActive'>
                                <div class='promotion-image-container'>
                                    <img src='images/ads/" . htmlspecialchars($bannerFile) . "' 
                                        alt='Book promotion' class='promotion-image'>
                                </div>
                              </div>";
                    }
                } else {
                    // Fallback when no active banners are available
                    echo "<div class='carousel-item active'>
                            <div class='promotion-image-container'>
                                <img src='images/default-ad.jpg' alt='Book promotion' 
                                    class='promotion-image'>
                            </div>
                          </div>";
                }
                ?>
            </div>
            
            <!-- Add carousel indicators if more than one banner -->
            <?php if (count($validBanners) > 1): ?>
            <div class="carousel-indicators">
                <?php for ($i = 0; $i < count($validBanners); $i++): ?>
                    <button type="button" data-bs-target="#promotionCarousel" 
                            data-bs-slide-to="<?php echo $i; ?>" 
                            <?php echo $i === 0 ? 'class="active" aria-current="true"' : ''; ?> 
                            aria-label="Slide <?php echo $i + 1; ?>"></button>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
            
            <!-- Add carousel controls if more than one banner -->
            <?php if (count($validBanners) > 1): ?>
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
            <?php endif; ?>
        </div>
    </div>
</div>