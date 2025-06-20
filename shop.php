<?php
session_start();
require 'config.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user data
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = isset($_SESSION['role']) ? strtolower($_SESSION['role']) : 'customer';

// Initialize cart if not already set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle "Add to Cart" action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    $productId = $_POST['product_id'];
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

    // Check if the product is already in the cart
    if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId] += $quantity; // Increment quantity
    } else {
        $_SESSION['cart'][$productId] = $quantity; // Add new product
    }

    // Decrement the stock in the database
    $stmt = $pdo->prepare("
        UPDATE Stock
        SET QuantityAdded = QuantityAdded - :quantity
        WHERE ProductID = :productId AND QuantityAdded >= :quantity
        ORDER BY QuantityAdded DESC
        LIMIT 1
    ");
    $stmt->execute([
        'quantity' => $quantity,
        'productId' => $productId,
    ]);

    // Check if the stock was successfully decremented
    if ($stmt->rowCount() === 0) {
        // If no rows were updated, it means there wasn't enough stock
        $_SESSION['error_message'] = "Not enough stock available for this product.";
        header('Location: shop.php');
        exit();
    }

    // Set success message
    $_SESSION['success_message'] = "Product added to cart successfully!";

    // Redirect to avoid form resubmission
    header('Location: shop.php');
    exit();
}

// Fetch products and their stock from the database
$products = $pdo->query("
    SELECT 
        p.ProductID, 
        p.Name, 
        p.Category, 
        p.Price, 
        p.Description, 
        COALESCE(SUM(s.QuantityAdded), 0) AS Stock
    FROM Products p
    LEFT JOIN Stock s ON p.ProductID = s.ProductID
    GROUP BY p.ProductID
    HAVING Stock > 0
    ORDER BY p.Name ASC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop</title>
    <!-- Link to Global CSS Files -->
    <link rel="stylesheet" href="css styles/base.css">
    <link rel="stylesheet" href="css styles/sidebar.css">
    <link rel="stylesheet" href="css styles/alerts.css">
    <!-- Link to Page-Specific CSS -->
    <link rel="stylesheet" href="css styles/shop.css">
    <!-- Link to Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <header>
        <h1>Inventory Management System</h1>
    </header>
    <div class="main-layout">
        <nav class="sidebar">
            <a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>"><i class="fas fa-home"></i> Dashboard</a>
            <a href="shop.php" class="<?= basename($_SERVER['PHP_SELF']) === 'shop.php' ? 'active' : '' ?>"><i class="fas fa-store"></i> Shop</a>
            <a href="orders.php" class="<?= basename($_SERVER['PHP_SELF']) === 'orders.php' ? 'active' : '' ?>"><i class="fas fa-shopping-cart"></i> Orders</a>
            <?php if ($role === 'admin'): ?>
                <a href="reports.php" class="<?= basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : '' ?>"><i class="fas fa-chart-line"></i> Reports</a>
            <?php endif; ?>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
        <div class="container">
            <!-- Display Success Message -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert success">
                    <?= htmlspecialchars($_SESSION['success_message']) ?>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <!-- Display Error Message -->
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert error">
                    <?= htmlspecialchars($_SESSION['error_message']) ?>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <h1>Welcome to the Shop, <?= htmlspecialchars($username) ?>!</h1>
            <div class="product-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <h2><?= htmlspecialchars($product['Name']) ?></h2>
                        <p class="category">Category: <?= htmlspecialchars($product['Category']) ?></p>
                        <p class="price">$<?= number_format($product['Price'], 2) ?></p>
                        <p class="description"><?= htmlspecialchars($product['Description']) ?></p>
                        <p class="stock">Stock: <?= htmlspecialchars($product['Stock']) ?></p>
                        <form method="POST" class="add-to-cart-form">
                            <input type="hidden" name="product_id" value="<?= $product['ProductID'] ?>">
                            <label for="quantity-<?= $product['ProductID'] ?>">Quantity:</label>
                            <input type="number" id="quantity-<?= $product['ProductID'] ?>" name="quantity" value="1" min="1" max="<?= $product['Stock'] ?>">
                            <button type="submit" class="btn">Add to Cart</button>
                        </form>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($products)): ?>
                    <p>No products available at the moment. Please check back later!</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>