<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user role (default to staff if not set)
$role = isset($_SESSION['role']) ? strtolower($_SESSION['role']) : 'staff';

// Check if product ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid product ID.";
    header('Location: products.php');
    exit();
}

$product_id = $_GET['id'];

// Initialize variables to prevent undefined errors
$productSuppliers = [];
$availableSuppliers = [];
$product = null;

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

// Fetch product suppliers
try {
    $stmt = $pdo->prepare("
        SELECT s.*
        FROM Suppliers s
        JOIN SupplierProducts sp ON s.SupplierID = sp.SupplierID
        WHERE sp.ProductID = ?
        ORDER BY s.Name ASC
    ");
    $stmt->execute([$product_id]);
    $productSuppliers = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching product suppliers: " . $e->getMessage();
    $productSuppliers = [];
}

// Fetch all suppliers for dropdown
try {
    $stmt = $pdo->prepare("
        SELECT s.* 
        FROM Suppliers s
        WHERE s.SupplierID NOT IN (
            SELECT SupplierID FROM SupplierProducts WHERE ProductID = ?
        )
        ORDER BY s.Name
    ");
    $stmt->execute([$product_id]);
    $availableSuppliers = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching available suppliers: " . $e->getMessage();
    $availableSuppliers = [];
}

// Only allow add/remove if admin
$canEdit = ($role === 'admin');

// Handle adding a supplier to product
if ($canEdit && isset($_POST['add_supplier_to_product'])) {
    $supplier_id = $_POST['supplier_id'];
    if (empty($supplier_id)) {
        $error = "Please select a supplier.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO SupplierProducts (SupplierID, ProductID) VALUES (?, ?)");
            $stmt->execute([$supplier_id, $product_id]);
            $success = "Supplier added to product successfully!";
            // Refresh data
            $stmt = $pdo->prepare("
                SELECT s.*
                FROM Suppliers s
                JOIN SupplierProducts sp ON s.SupplierID = sp.SupplierID
                WHERE sp.ProductID = ?
                ORDER BY s.Name ASC
            ");
            $stmt->execute([$product_id]);
            $productSuppliers = $stmt->fetchAll();
            $stmt = $pdo->prepare("
                SELECT s.* 
                FROM Suppliers s
                WHERE s.SupplierID NOT IN (
                    SELECT SupplierID FROM SupplierProducts WHERE ProductID = ?
                )
                ORDER BY s.Name
            ");
            $stmt->execute([$product_id]);
            $availableSuppliers = $stmt->fetchAll();
        } catch (PDOException $e) {
            $error = "Error adding supplier to product: " . $e->getMessage();
            $productSuppliers = $productSuppliers ?: [];
            $availableSuppliers = $availableSuppliers ?: [];
        }
    }
}

// Handle removing a supplier from product
if ($canEdit && isset($_POST['remove_supplier'])) {
    $supplier_id = $_POST['supplier_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM SupplierProducts WHERE SupplierID = ? AND ProductID = ?");
        $stmt->execute([$supplier_id, $product_id]);
        $success = "Supplier removed from product successfully!";
        // Refresh data
        $stmt = $pdo->prepare("
            SELECT s.*
            FROM Suppliers s
            JOIN SupplierProducts sp ON s.SupplierID = sp.SupplierID
            WHERE sp.ProductID = ?
            ORDER BY s.Name ASC
        ");
        $stmt->execute([$product_id]);
        $productSuppliers = $stmt->fetchAll();
        $stmt = $pdo->prepare("
            SELECT s.* 
            FROM Suppliers s
            WHERE s.SupplierID NOT IN (
                SELECT SupplierID FROM SupplierProducts WHERE ProductID = ?
            )
            ORDER BY s.Name
        ");
        $stmt->execute([$product_id]);
        $availableSuppliers = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = "Error removing supplier from product: " . $e->getMessage();
        $productSuppliers = $productSuppliers ?: [];
        $availableSuppliers = $availableSuppliers ?: [];
    }
}

// Handle adding a new supplier directly from this page
if ($canEdit && isset($_POST['add_new_supplier'])) {
    $name = trim($_POST['name']);
    $contact = trim($_POST['contact_info']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    if (empty($name)) {
        $error = "Supplier name is required.";
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO Suppliers (Name, ContactInfo, Email, Phone) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $contact, $email, $phone]);
            $new_supplier_id = $pdo->lastInsertId();
            if (isset($_POST['add_to_product']) && $_POST['add_to_product'] == 'yes') {
                $stmt = $pdo->prepare("INSERT INTO SupplierProducts (SupplierID, ProductID) VALUES (?, ?)");
                $stmt->execute([$new_supplier_id, $product_id]);
            }
            $pdo->commit();
            $success = "New supplier added successfully!";
            $stmt = $pdo->prepare("
                SELECT s.*
                FROM Suppliers s
                JOIN SupplierProducts sp ON s.SupplierID = sp.SupplierID
                WHERE sp.ProductID = ?
                ORDER BY s.Name ASC
            ");
            $stmt->execute([$product_id]);
            $productSuppliers = $stmt->fetchAll();
            $stmt = $pdo->prepare("
                SELECT s.* 
                FROM Suppliers s
                WHERE s.SupplierID NOT IN (
                    SELECT SupplierID FROM SupplierProducts WHERE ProductID = ?
                )
                ORDER BY s.Name
            ");
            $stmt->execute([$product_id]);
            $availableSuppliers = $stmt->fetchAll();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error adding new supplier: " . $e->getMessage();
            $productSuppliers = $productSuppliers ?: [];
            $availableSuppliers = $availableSuppliers ?: [];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Suppliers - Inventory Management System</title>
    <link rel="stylesheet" href="css styles/base.css">
    <link rel="stylesheet" href="css styles/alerts.css">
    <link rel="stylesheet" href="css styles/product_suppliers.css">
    <link rel="stylesheet" href="css styles/sidebar.css">
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
                <h1>Manage Suppliers for <?= htmlspecialchars($product['Name']) ?></h1>
                <div class="header-buttons">
                    <?php if ($canEdit): ?>
                        <button id="toggleNewSupplierBtn" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Supplier</button>
                    <?php endif; ?>
                    <a href="products.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Products</a>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $success ?>
                </div>
            <?php endif; ?>

            <!-- Product Information -->
            <div class="card">
                <h2>Product Information</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Product ID:</span>
                        <span class="info-value"><?= htmlspecialchars($product['ProductID']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Product Name:</span>
                        <span class="info-value"><?= htmlspecialchars($product['Name']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Category:</span>
                        <span class="info-value"><?= htmlspecialchars($product['Category']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Price:</span>
                        <span class="info-value">$<?= number_format($product['Price'], 2) ?></span>
                    </div>
                    <div class="info-item full-width">
                        <span class="info-label">Description:</span>
                        <span class="info-value"><?= htmlspecialchars($product['Description'] ?: 'No description available.') ?></span>
                    </div>
                </div>
            </div>

            <!-- Current Suppliers -->
            <div class="card">
                <h2>Current Suppliers</h2>
                <?php if (count($productSuppliers) > 0): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Supplier Name</th>
                                    <th>Contact Person</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <?php if ($canEdit): ?>
                                    <th>Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($productSuppliers as $supplier): ?>
                                <tr>
                                    <td><?= htmlspecialchars($supplier['Name']) ?></td>
                                    <td><?= htmlspecialchars($supplier['ContactInfo'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars($supplier['Email'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars($supplier['Phone'] ?: '-') ?></td>
                                    <?php if ($canEdit): ?>
                                    <td class="actions">
                                        <form method="POST" class="delete-form" onsubmit="return confirm('Are you sure you want to remove this supplier from the product?');">
                                            <input type="hidden" name="supplier_id" value="<?= $supplier['SupplierID'] ?>">
                                            <button type="submit" name="remove_supplier" class="btn btn-danger">
                                                <i class="fas fa-trash-alt"></i> Remove
                                            </button>
                                        </form>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No suppliers found for this product.</p>
                        <p>Add suppliers to track purchasing options.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Add Existing Supplier Form -->
            <div class="card">
                <h2>Link Existing Supplier</h2>
                <?php if ($canEdit && count($availableSuppliers) > 0): ?>
                    <form method="POST" action="">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="supplier_id">Select Supplier</label>
                                <select id="supplier_id" name="supplier_id" required>
                                    <option value="">-- Select a Supplier --</option>
                                    <?php foreach ($availableSuppliers as $supplier): ?>
                                        <option value="<?= $supplier['SupplierID'] ?>"><?= htmlspecialchars($supplier['Name']) ?> (<?= htmlspecialchars($supplier['Email'] ?: 'No email') ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="add_supplier_to_product" class="btn btn-success">
                                <i class="fas fa-link"></i> Link Supplier
                            </button>
                        </div>
                    </form>
                <?php elseif (!$canEdit): ?>
                    <div class="notice">
                        <p>You do not have permission to link suppliers. Please contact an admin.</p>
                    </div>
                <?php else: ?>
                    <div class="notice">
                        <p>All available suppliers are already linked to this product.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="js/product_suppliers.js"></script>
</body>
</html>