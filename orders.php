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

// Handle "Buy Now" action for a specific product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_now'])) {
    $productId = $_POST['product_id'];

    // Check if the product exists in the cart
    if (isset($_SESSION['cart'][$productId])) {
        $quantity = $_SESSION['cart'][$productId];

        // Fetch product details
        $stmt = $pdo->prepare("
            SELECT 
                p.ProductID, 
                p.Price
            FROM Products p
            WHERE p.ProductID = ?
        ");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if ($product) {
            $totalAmount = $quantity * $product['Price'];

            // Insert the product into the Sales table
            $insertStmt = $pdo->prepare("
                INSERT INTO Sales (UserID, ProductID, QuantitySold, TotalAmount)
                VALUES (:userId, :productId, :quantitySold, :totalAmount)
            ");
            $insertStmt->execute([
                'userId' => $userId,
                'productId' => $productId,
                'quantitySold' => $quantity,
                'totalAmount' => $totalAmount,
            ]);

            // Remove the product from the cart
            unset($_SESSION['cart'][$productId]);

            // Set success message
            $_SESSION['success_message'] = "You have successfully purchased {$product['ProductID']}!";
        } else {
            $_SESSION['error_message'] = "The product you are trying to buy does not exist.";
        }
    } else {
        $_SESSION['error_message'] = "The product is not in your cart.";
    }

    // Redirect to avoid form resubmission
    header('Location: orders.php');
    exit();
}

// Handle "Delete" action for a specific product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $productId = $_POST['product_id'];

    // Check if the product exists in the cart
    if (isset($_SESSION['cart'][$productId])) {
        $quantity = $_SESSION['cart'][$productId]; // Get the quantity being removed

        // Remove the product from the cart
        unset($_SESSION['cart'][$productId]);

        // Increment the stock in the database
        $stmt = $pdo->prepare("
            UPDATE Stock
            SET QuantityAdded = QuantityAdded + :quantity
            WHERE ProductID = :productId
        ");
        $stmt->execute([
            'quantity' => $quantity,
            'productId' => $productId,
        ]);

        // Set success message
        $_SESSION['success_message'] = "Product removed from your cart successfully!";
    } else {
        $_SESSION['error_message'] = "The product you are trying to remove is not in your cart.";
    }

    // Redirect to avoid form resubmission
    header('Location: orders.php');
    exit();
}

// Fetch "orders" from the session cart
$orders = [];
if (!empty($_SESSION['cart'])) {
    $productIds = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));

    // Fetch product details for the items in the cart
    $stmt = $pdo->prepare("
        SELECT 
            p.ProductID, 
            p.Name, 
            p.Price, 
            p.Category
        FROM Products p
        WHERE p.ProductID IN ($placeholders)
    ");
    $stmt->execute($productIds);
    $products = $stmt->fetchAll();

    // Build the orders array
    foreach ($products as $product) {
        $productId = $product['ProductID'];
        $orders[] = [
            'ProductID' => $productId,
            'ProductName' => $product['Name'],
            'Quantity' => $_SESSION['cart'][$productId],
            'Price' => $product['Price'],
            'Total' => $_SESSION['cart'][$productId] * $product['Price'],
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Orders - Inventory Management System</title>
    <link rel="stylesheet" href="css styles/base.css">
    <link rel="stylesheet" href="css styles/sidebar.css">
    <link rel="stylesheet" href="css styles/alerts.css">
    <link rel="stylesheet" href="css styles/orders.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
            <div class="page-header">
                <h1>Your Orders</h1>
            </div>

            <!-- Display Success Message -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['success_message']) ?>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <!-- Display Error Message -->
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['error_message']) ?>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <p>You have no orders yet.</p>
                    <a href="shop.php" class="btn btn-primary"><i class="fas fa-store"></i> Go to Shop</a>
                </div>
            <?php else: ?>
                <div class="card">
                    <h2>Order Summary</h2>
                    <div class="table-responsive">
                        <table class="orders-table">
                            <thead>
                                <tr>
                                    <th>Product ID</th>
                                    <th>Product Name</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                    <th>Total</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($order['ProductID']) ?></td>
                                        <td><?= htmlspecialchars($order['ProductName']) ?></td>
                                        <td><?= htmlspecialchars($order['Quantity']) ?></td>
                                        <td>$<?= number_format($order['Price'], 2) ?></td>
                                        <td>$<?= number_format($order['Total'], 2) ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <form method="POST" class="buy-now-form">
                                                    <input type="hidden" name="product_id" value="<?= $order['ProductID'] ?>">
                                                    <button type="submit" name="buy_now" class="btn btn-success">
                                                        <i class="fas fa-shopping-cart"></i> Buy Now
                                                    </button>
                                                </form>
                                                <form method="POST" class="delete-form" onsubmit="return confirm('Are you sure you want to remove this product from your cart?');">
                                                    <input type="hidden" name="product_id" value="<?= $order['ProductID'] ?>">
                                                    <button type="submit" name="delete_product" class="btn btn-danger">
                                                        <i class="fas fa-trash-alt"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>