<?php
session_start();
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'staff';

// Check if product ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid product ID.";
    header('Location: products.php');
    exit();
}

$product_id = $_GET['id'];

// Fetch product details
try {
    $stmt = $pdo->prepare("SELECT * FROM Products WHERE ProductID = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        $_SESSION['error'] = "Product not found.";
        header('Location: products.php');
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching product: " . $e->getMessage();
    header('Location: products.php');
    exit();
}

// Get all categories for dropdown
try {
    $categories = $pdo->query("SELECT DISTINCT Category FROM Products ORDER BY Category")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $error = "Error fetching categories: " . $e->getMessage();
}

// Handle form submission (only admin can update)
if ($role === 'admin' && isset($_POST['update_product'])) {
    $product_name = trim($_POST['product_name']);
    $category = trim($_POST['category']);
    $price = floatval($_POST['price']);
    $description = trim($_POST['description']);

    // Basic validation
    if (empty($product_name) || empty($category) || $price <= 0) {
        $error = "Please fill all required fields with valid data.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE Products SET Name = ?, Category = ?, Price = ?, Description = ? WHERE ProductID = ?");
            $stmt->execute([$product_name, $category, $price, $description, $product_id]);
            $success = "Product updated successfully!";
            
            // Refresh product data
            $stmt = $pdo->prepare("SELECT * FROM Products WHERE ProductID = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
        } catch (PDOException $e) {
            $error = "Error updating product: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - Inventory Management System</title>
    <link rel="stylesheet" href="css styles/base.css">
    <link rel="stylesheet" href="css styles/sidebar.css">
    <link rel="stylesheet" href="css styles/alerts.css">
    <link rel="stylesheet" href="css styles/edit_product.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <header>
        <h1>Inventory Management System</h1>
    </header>
    <div class="main-layout">
        <nav class="sidebar">
            <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="products.php" class="active"><i class="fas fa-box"></i> Products</a>
            <?php if ($role === 'admin'): ?>
                <a href="suppliers.php"><i class="fas fa-truck"></i> Suppliers</a>
            <?php endif; ?>
            <a href="stock.php"><i class="fas fa-warehouse"></i> Stock</a>
            <a href="sales.php"><i class="fas fa-cash-register"></i> Sales</a>
            <?php if ($role === 'admin'): ?>
                <a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a>
            <?php endif; ?>
            <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
        <div class="container">
            <div class="page-header">
                <h1>Edit Product</h1>
                <a href="products.php" class="btn btn-secondary">Back to Products</a>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="alert-icon error-icon"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <i class="alert-icon success-icon"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <div class="edit-product-card">
                <h2>Edit Product Details</h2>
                <form method="POST" action="">
                    <div class="form-row">
                        <label for="product_name">Product Name</label>
                        <input type="text" id="product_name" name="product_name" required 
                               value="<?= htmlspecialchars($product['Name']) ?>" <?= $role !== 'admin' ? 'readonly' : '' ?>>
                    </div>
                    
                    <div class="form-row">
                        <label for="category">Category</label>
                        <input type="text" id="category" name="category" required 
                               value="<?= htmlspecialchars($product['Category']) ?>" list="existing-categories" <?= $role !== 'admin' ? 'readonly' : '' ?>>
                        <datalist id="existing-categories">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    
                    <div class="form-row">
                        <label for="price">Price ($)</label>
                        <input type="number" id="price" name="price" step="0.01" min="0" required 
                               value="<?= number_format($product['Price'], 2, '.', '') ?>" <?= $role !== 'admin' ? 'readonly' : '' ?>>
                    </div>
                    
                    <div class="form-row">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3" <?= $role !== 'admin' ? 'readonly' : '' ?>><?= htmlspecialchars($product['Description']) ?></textarea>
                    </div>
                    
                    <div class="product-info-section">
                        <div class="info-group">
                            <label>Product ID:</label>
                            <span><?= htmlspecialchars($product['ProductID']) ?></span>
                        </div>
                        <div class="info-group">
                            <label>In Stock:</label>
                            <?php
                            $stockStmt = $pdo->prepare("SELECT SUM(QuantityAdded) AS TotalStock FROM Stock WHERE ProductID = ?");
                            $stockStmt->execute([$product_id]);
                            $stockInfo = $stockStmt->fetch();
                            $totalStock = $stockInfo['TotalStock'] ?: 0;
                            ?>
                            <span><?= $totalStock ?> units</span>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <?php if ($role === 'admin'): ?>
                        <button type="submit" name="update_product" class="btn btn-primary btn-lg">
                            <i class="icon save-icon"></i> Save Changes
                        </button>
                        <?php endif; ?>
                        <a href="products.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>

            <div class="quick-links">
                <a href="view_product.php?id=<?= $product_id ?>" class="card-link">
                    <div class="card-link-content">
                        <i class="icon view-icon"></i>
                        <span>View Details</span>
                    </div>
                </a>
                <a href="product_suppliers.php?id=<?= $product_id ?>" class="card-link">
                    <div class="card-link-content">
                        <i class="icon suppliers-icon"></i>
                        <span>Manage Suppliers</span>
                    </div>
                </a>
                <a href="stock.php?product_id=<?= $product_id ?>" class="card-link">
                    <div class="card-link-content">
                        <i class="icon stock-icon"></i>
                        <span>View Stock</span>
                    </div>
                </a>
            </div>
        </div>
    </div>
    <script>
        // Auto-hide alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 500);
            }, 5000);
        });
    </script>
</body>
</html>