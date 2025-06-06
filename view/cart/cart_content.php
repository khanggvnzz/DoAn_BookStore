<div class="cart-popup">
    <div class="cart-popup-header">
        <h5>Your Shopping Cart</h5>
        <button type="button" class="btn-close close-cart-popup"></button>
    </div>

    <?php if (empty($cartItems)): ?>
        <div class="cart-empty">
            <p>Your cart is empty.</p>
            <button class="btn btn-primary close-cart-popup">Continue Shopping</button>
        </div>
    <?php else: ?>
        <div class="cart-items-container">
            <?php foreach ($cartItems as $item): ?>
                <div class="cart-popup-item">
                    <?php
                    $imagePath = 'images/books/' . ($item['image'] ?: 'default-book.jpg');
                    $fullImagePath = file_exists(__DIR__ . '/../' . $imagePath) ? $imagePath : 'images/default-book.jpg';
                    ?>
                    <img src="<?php echo htmlspecialchars($fullImagePath); ?>" class="cart-popup-item-image">
                    <div class="cart-popup-item-details">
                        <h6><?php echo htmlspecialchars($item['title']); ?></h6>
                        <div class="d-flex justify-content-between">
                            <span>$<?php echo number_format($item['price'], 2); ?> × <?php echo $item['quantity']; ?></span>
                            <span class="fw-bold">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="cart-popup-footer">
            <div class="d-flex justify-content-between mb-2">
                <span>Total:</span>
                <span class="fw-bold">$<?php echo number_format($totalAmount, 2); ?></span>
            </div>
            <div class="d-grid gap-2">
                <a href="cart.php" class="btn btn-primary">View Cart</a>
                <a href="checkout.php" class="btn btn-success">Checkout</a>
            </div>
        </div>
    <?php endif; ?>
</div>