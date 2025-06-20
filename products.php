<?php
session_start();
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'staff';

// Fetch all products
try {
    $products = $pdo->query("SELECT * FROM Products ORDER BY ProductID ASC")->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching products: " . $e->getMessage();
}

// Reset auto_increment if there are no products
if (count($products) == 0) {
    try {
        $pdo->query("ALTER TABLE Products AUTO_INCREMENT = 1");
    } catch (PDOException $e) {
        $error = "Error resetting auto increment: " . $e->getMessage();
    }
}

// Get unique categories for the filter dropdown
try {
    $categories = $pdo->query("SELECT DISTINCT Category FROM Products ORDER BY Category")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $error = "Error fetching categories: " . $e->getMessage();
}

// Handle adding a new product (admin only)
if ($role === 'admin' && isset($_POST['add_product'])) {
    $product_name = trim($_POST['product_name']);
    $category = trim($_POST['category']);
    $price = floatval($_POST['price']);
    $description = trim($_POST['description']);

    // Basic validation
    if (empty($product_name) || empty($category) || $price <= 0) {
        $error = "Please fill all required fields with valid data.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO Products (Name, Category, Price, Description) VALUES (?, ?, ?, ?)");
            $stmt->execute([$product_name, $category, $price, $description]);
            $success = "Product added successfully!";
            
            // Refresh products list
            $products = $pdo->query("SELECT * FROM Products ORDER BY ProductID DESC")->fetchAll();
            // Refresh categories list
            $categories = $pdo->query("SELECT DISTINCT Category FROM Products ORDER BY Category")->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            $error = "Error adding product: " . $e->getMessage();
        }
    }
}

// Handle deleting a product (admin only)
if ($role === 'admin' && isset($_POST['delete_product'])) {
    $product_id = $_POST['product_id'];

    try {
        // Delete associated records in the Stock table
        $stmt = $pdo->prepare("DELETE FROM Stock WHERE ProductID = ?");
        $stmt->execute([$product_id]);

        // Delete associated records in the Sales table
        $stmt = $pdo->prepare("DELETE FROM Sales WHERE ProductID = ?");
        $stmt->execute([$product_id]);

        // Delete from SupplierProducts junction table
        $stmt = $pdo->prepare("DELETE FROM SupplierProducts WHERE ProductID = ?");
        $stmt->execute([$product_id]);

        // Finally, delete the product
        $stmt = $pdo->prepare("DELETE FROM Products WHERE ProductID = ?");
        $stmt->execute([$product_id]);

        $success = "Product and all associated records deleted successfully!";

        // Refresh products list
        $products = $pdo->query("SELECT * FROM Products ORDER BY ProductID DESC")->fetchAll();
        // Refresh categories list
        $categories = $pdo->query("SELECT DISTINCT Category FROM Products ORDER BY Category")->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        $error = "Error deleting product: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - Inventory Management System</title>
    <link rel="stylesheet" href="css styles/base.css">
    <link rel="stylesheet" href="css styles/sidebar.css">
    <link rel="stylesheet" href="css styles/alerts.css">
    <link rel="stylesheet" href="css styles/products.css">
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
                <h1>Manage Products</h1>
                <?php if ($role === 'admin'): ?>
                    <button id="toggleFormBtn" class="btn btn-primary">Add New Product</button>
                <?php endif; ?>
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

            <!-- Add New Product Form (admin only) -->
            <?php if ($role === 'admin'): ?>
            <div id="addProductForm" class="form-container" style="display: none;">
                <div class="card">
                    <h2>Add New Product</h2>
                    <form method="POST" action="products.php">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="product_name">Product Name</label>
                                <input type="text" id="product_name" name="product_name" required placeholder="Enter product name">
                            </div>
                            
                            <div class="form-group">
                                <label for="category">Category</label>
                                <input type="text" id="category" name="category" required placeholder="Enter category" list="existing-categories">
                                <datalist id="existing-categories">
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat) ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            
                            <div class="form-group">
                                <label for="price">Price ($)</label>
                                <input type="number" id="price" name="price" step="0.01" min="0" required placeholder="0.00">
                            </div>
                            
                            <div class="form-group full-width">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" rows="3" placeholder="Enter product description"></textarea>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="add_product" class="btn btn-success">Save Product</button>
                            <button type="button" id="cancelAddBtn" class="btn btn-secondary">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Product Search and Filter -->
            <div class="data-controls">
                <div class="search-container">
                    <input type="text" id="productSearch" placeholder="Search products...">
                    <label for="categoryFilter">Filter by:</label>
                    <select id="categoryFilter">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Product List -->
            <div class="card table-card">
                <h2>Product List</h2>
                <?php if (isset($products) && count($products) > 0): ?>
                    <div class="table-responsive">
                        <table id="productsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Product Name</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?= htmlspecialchars($product['ProductID']) ?></td>
                                    <td>
                                        <a href="view_product.php?id=<?= $product['ProductID'] ?>" class="product-name">
                                            <?= htmlspecialchars($product['Name']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="category-badge"><?= htmlspecialchars($product['Category']) ?></span>
                                    </td>
                                    <td>$<?= number_format($product['Price'], 2) ?></td>
                                    <td class="actions">
                                        <a href="edit_product.php?id=<?= $product['ProductID'] ?>" class="action-btn edit-btn">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <?php if ($role === 'admin'): ?>
                                        <form method="POST" class="delete-form" onsubmit="return confirm('Are you sure you want to delete this product? This cannot be undone.');">
                                            <input type="hidden" name="product_id" value="<?= $product['ProductID'] ?>">
                                            <button type="submit" name="delete_product" class="action-btn delete-btn">
                                                <i class="fas fa-trash-alt"></i> Delete
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        <a href="product_suppliers.php?id=<?= $product['ProductID'] ?>" class="action-btn view-btn">
                                            <i class="fas fa-truck"></i> Suppliers
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-footer">
                        <div class="pagination">
                            <span class="showing-text">Showing <?= count($products) ?> products</span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon"></div>
                        <p>No products found. Add your first product to get started.</p>
                        <?php if ($role === 'admin'): ?>
                        <button id="emptyAddBtn" class="btn btn-primary">Add Product</button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Toggle add product form
        <?php if ($role === 'admin'): ?>
        const toggleFormBtn = document.getElementById('toggleFormBtn');
        const addProductForm = document.getElementById('addProductForm');
        const cancelAddBtn = document.getElementById('cancelAddBtn');
        
        toggleFormBtn.addEventListener('click', function() {
            addProductForm.style.display = 'block';
            document.getElementById('product_name').focus();
        });
        
        cancelAddBtn.addEventListener('click', function() {
            addProductForm.style.display = 'none';
        });
        
        // Handle empty state add button
        const emptyAddBtn = document.getElementById('emptyAddBtn');
        if (emptyAddBtn) {
            emptyAddBtn.addEventListener('click', function() {
                addProductForm.style.display = 'block';
                document.getElementById('product_name').focus();
            });
        }
        <?php endif; ?>
        
        // Product search functionality
        const productSearch = document.getElementById('productSearch');
        const productsTable = document.getElementById('productsTable');
        
        if (productSearch && productsTable) {
            productSearch.addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = productsTable.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
                
                for (let i = 0; i < rows.length; i++) {
                    const productName = rows[i].getElementsByTagName('td')[1].textContent.toLowerCase();
                    const category = rows[i].getElementsByTagName('td')[2].textContent.toLowerCase();
                    
                    if (productName.includes(searchTerm) || category.includes(searchTerm)) {
                        rows[i].style.display = '';
                    } else {
                        rows[i].style.display = 'none';
                    }
                }
                
                updateShowingCount();
            });
        }
        
        // Category filter
        const categoryFilter = document.getElementById('categoryFilter');
        
        if (categoryFilter && productsTable) {
            categoryFilter.addEventListener('change', function() {
                const selectedCategory = this.value.toLowerCase();
                const rows = productsTable.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
                
                for (let i = 0; i < rows.length; i++) {
                    const category = rows[i].getElementsByTagName('td')[2].textContent.toLowerCase();
                    
                    if (selectedCategory === '' || category === selectedCategory) {
                        rows[i].style.display = '';
                    } else {
                        rows[i].style.display = 'none';
                    }
                }
                
                updateShowingCount();
            });
        }
        
        // Update "Showing X products" text
        function updateShowingCount() {
            const showingText = document.querySelector('.showing-text');
            if (showingText && productsTable) {
                const rows = productsTable.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
                let visibleCount = 0;
                
                for (let i = 0; i < rows.length; i++) {
                    if (rows[i].style.display !== 'none') {
                        visibleCount++;
                    }
                }
                
                showingText.textContent = `Showing ${visibleCount} products`;
            }
        }
        
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