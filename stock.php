<?php
session_start();
require 'config.php';

// Check if user is logged in
requireLogin();

$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'staff';

// Initialize variables
$success_message = $error_message = '';
$suppliers = $products = [];

// Fetch all suppliers
try {
    $stmt = $pdo->query("SELECT SupplierID, Name FROM Suppliers ORDER BY Name");
    $suppliers = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error fetching suppliers: " . $e->getMessage();
}

// Fetch all products
try {
    $stmt = $pdo->query("SELECT ProductID, Name FROM Products ORDER BY Name");
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error fetching products: " . $e->getMessage();
}

// Process form submission for adding new stock (admin only)
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) && $_POST['action'] === 'add_stock' &&
    $role === 'admin'
) {
    $product_id = $_POST['product_id'] ?? 0;
    $supplier_id = $_POST['supplier_id'] ?? 0;
    $quantity = $_POST['quantity'] ?? 0;
    $date_added = $_POST['date_added'] ?? date('Y-m-d H:i:s');
    
    // Validate inputs
    if (!$product_id || !$supplier_id || $quantity <= 0) {
        $error_message = "Please fill in all fields with valid values.";
    } else {
        // Check if supplier provides this product
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM SupplierProducts WHERE SupplierID = ? AND ProductID = ?");
            $stmt->execute([$supplier_id, $product_id]);
            $supplier_provides_product = $stmt->fetchColumn() > 0;
            
            if (!$supplier_provides_product) {
                // Add the relationship if it doesn't exist
                $stmt = $pdo->prepare("INSERT INTO SupplierProducts (SupplierID, ProductID) VALUES (?, ?)");
                $stmt->execute([$supplier_id, $product_id]);
            }
            
            // Add stock entry
            $stmt = $pdo->prepare("INSERT INTO stock (ProductID, SupplierID, QuantityAdded, DateAdded) VALUES (?, ?, ?, ?)");
            $stmt->execute([$product_id, $supplier_id, $quantity, $date_added]);
            
            $success_message = "Stock added successfully!";
        } catch (PDOException $e) {
            $error_message = "Error adding stock: " . $e->getMessage();
        }
    }
}

// Get current stock levels by calculating total additions minus total sales
try {
    $current_stock = [];
    $stmt = $pdo->query("
        SELECT 
            p.ProductID,
            p.Name AS ProductName,
            p.Category,
            COALESCE(SUM(s.QuantityAdded), 0) AS TotalAdded,
            COALESCE((
                SELECT SUM(sl.QuantitySold) 
                FROM Sales sl 
                WHERE sl.ProductID = p.ProductID
            ), 0) AS TotalSold,
            COALESCE(SUM(s.QuantityAdded), 0) - 
            COALESCE((
                SELECT SUM(sl.QuantitySold) 
                FROM Sales sl 
                WHERE sl.ProductID = p.ProductID
            ), 0) AS CurrentStock
        FROM 
            Products p
        LEFT JOIN 
            stock s ON p.ProductID = s.ProductID
        GROUP BY 
            p.ProductID, p.Name, p.Category
        ORDER BY 
            p.Name
    ");
    $current_stock = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching current stock: " . $e->getMessage();
}

// Get recent stock history
try {
    $stock_history = [];
    $stmt = $pdo->query("
        SELECT 
            s.StockID,
            p.Name AS ProductName,
            sup.Name AS SupplierName,
            s.QuantityAdded,
            s.DateAdded
        FROM 
            stock s
        JOIN 
            Products p ON s.ProductID = p.ProductID
        JOIN 
            Suppliers sup ON s.SupplierID = sup.SupplierID
        ORDER BY 
            s.DateAdded DESC
        LIMIT 10
    ");
    $stock_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching stock history: " . $e->getMessage();
}

// Get stock levels by supplier
try {
    $stock_by_supplier = [];
    $stmt = $pdo->query("
        SELECT 
            sup.Name AS SupplierName,
            p.Name AS ProductName,
            SUM(s.QuantityAdded) AS TotalSupplied
        FROM 
            stock s
        JOIN 
            Products p ON s.ProductID = p.ProductID
        JOIN 
            Suppliers sup ON s.SupplierID = sup.SupplierID
        GROUP BY 
            sup.SupplierID, p.ProductID
        ORDER BY 
            sup.Name, p.Name
    ");
    $stock_by_supplier = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching stock by supplier: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Management</title>
    <link rel="stylesheet" href="css styles/base.css">
    <link rel="stylesheet" href="css styles/sidebar.css">
    <link rel="stylesheet" href="css styles/alerts.css">
    <link rel="stylesheet" href="css styles/stock.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<header>
    <h1>Inventory Management System</h1>
</header>
<div class="main-layout">
    <nav class="sidebar">
        <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="products.php"><i class="fas fa-box"></i> Products</a>
        <?php if ($role === 'admin'): ?>
            <a href="suppliers.php"><i class="fas fa-truck"></i> Suppliers</a>
        <?php endif; ?>
        <a href="stock.php" class="active"><i class="fas fa-warehouse"></i> Stock</a>
        <a href="sales.php"><i class="fas fa-cash-register"></i> Sales</a>
        <?php if ($role === 'admin'): ?>
            <a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a>
        <?php endif; ?>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
    <div class="container">
        <header class="page-header">
            <h1>Stock Management</h1>
        </header>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <span class="alert-icon">✓</span>
                <?php echo htmlspecialchars($success_message); ?>
                <button class="alert-close" onclick="this.parentElement.style.display='none';">×</button>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <span class="alert-icon">!</span>
                <?php echo htmlspecialchars($error_message); ?>
                <button class="alert-close" onclick="this.parentElement.style.display='none';">×</button>
            </div>
        <?php endif; ?>
        
        <div class="tabs">
            <div class="tab-list">
                <button class="tab active" data-section="current-stock">Current Stock</button>
                <?php if ($role === 'admin'): ?>
                    <button class="tab" data-section="add-stock">Add Stock</button>
                <?php endif; ?>
                <button class="tab" data-section="stock-history">Stock History</button>
                <button class="tab" data-section="supplier-stock">Supplier Stock</button>
            </div>

            <!-- Current Stock Section -->
            <div id="current-stock" class="tab-content active" style="display:block;">
                <div class="section-header">
                    <h2>Current Stock Levels</h2>
                    <div class="controls">
                        <input type="text" id="stockSearch" placeholder="Search products..." class="search-input">
                        <button id="exportCurrent" class="btn-secondary">Export CSV</button>
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Total Added</th>
                                <th>Total Sold</th>
                                <th>Current Stock</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($current_stock)): ?>
                                <?php foreach ($current_stock as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['ProductName']); ?></td>
                                        <td><?php echo htmlspecialchars($item['Category']); ?></td>
                                        <td><?php echo htmlspecialchars($item['TotalAdded']); ?></td>
                                        <td><?php echo htmlspecialchars($item['TotalSold']); ?></td>
                                        <td><?php echo htmlspecialchars($item['CurrentStock']); ?></td>
                                        <td>
                                            <?php if ($item['CurrentStock'] <= 0): ?>
                                                <span class="status-badge status-danger">Out of Stock</span>
                                            <?php elseif ($item['CurrentStock'] < 10): ?>
                                                <span class="status-badge status-warning">Low Stock</span>
                                            <?php else: ?>
                                                <span class="status-badge status-success">In Stock</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="updateStock">
                                            <input type="nube"
                                            <button class="updateBTN">update</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="empty-table">No stock information available.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Add Stock Section (admin only) -->
            <?php if ($role === 'admin'): ?>
            <div id="add-stock" class="tab-content" style="display:none;">
                <div class="section-header">
                    <h2>Add New Stock</h2>
                </div>
                
                <div class="card">
                    <form method="POST" class="form">
                        <input type="hidden" name="action" value="add_stock">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="product_id">Product:</label>
                                <select name="product_id" id="product_id" required>
                                    <option value="">Select Product</option>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?php echo $product['ProductID']; ?>">
                                            <?php echo htmlspecialchars($product['Name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="supplier_id">Supplier:</label>
                                <select name="supplier_id" id="supplier_id" required>
                                    <option value="">Select Supplier</option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <option value="<?php echo $supplier['SupplierID']; ?>">
                                            <?php echo htmlspecialchars($supplier['Name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="quantity">Quantity:</label>
                                <input type="number" name="quantity" id="quantity" min="1" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="date_added">Date Added:</label>
                                <input type="datetime-local" name="date_added" id="date_added" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-primary">Add Stock</button>
                            <button type="reset" class="btn-secondary">Reset</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Stock History Section -->
            <div id="stock-history" class="tab-content" style="display:none;">
                <div class="section-header">
                    <h2>Recent Stock History</h2>
                    <div class="controls">
                        <input type="text" id="historySearch" placeholder="Search history..." class="search-input">
                        <button id="exportHistory" class="btn-secondary">Export CSV</button>
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Stock ID</th>
                                <th>Product</th>
                                <th>Supplier</th>
                                <th>Quantity Added</th>
                                <th>Date Added</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($stock_history)): ?>
                                <?php foreach ($stock_history as $history): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($history['StockID']); ?></td>
                                        <td><?php echo htmlspecialchars($history['ProductName']); ?></td>
                                        <td><?php echo htmlspecialchars($history['SupplierName']); ?></td>
                                        <td><?php echo htmlspecialchars($history['QuantityAdded']); ?></td>
                                        <td><?php echo htmlspecialchars($history['DateAdded']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="empty-table">No stock history available.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Supplier Stock Section -->
            <div id="supplier-stock" class="tab-content" style="display:none;">
                <div class="section-header">
                    <h2>Stock by Supplier</h2>
                    <div class="controls">
                        <input type="text" id="supplierSearch" placeholder="Search suppliers..." class="search-input">
                        <button id="exportSupplier" class="btn-secondary">Export CSV</button>
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Supplier</th>
                                <th>Product</th>
                                <th>Total Supplied</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($stock_by_supplier)): ?>
                                <?php foreach ($stock_by_supplier as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['SupplierName']); ?></td>
                                        <td><?php echo htmlspecialchars($item['ProductName']); ?></td>
                                        <td><?php echo htmlspecialchars($item['TotalSupplied']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="empty-table">No supplier stock information available.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="main-footer">
    <div class="container">
        <p>&copy; <?php echo date('Y'); ?> Inventory Management System</p>
    </div>
</footer>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching: show only one section at a time
    document.querySelectorAll('.tab-list .tab').forEach(function(tabBtn) {
        tabBtn.addEventListener('click', function() {
            document.querySelectorAll('.tab-list .tab').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
                tab.style.display = 'none';
            });
            tabBtn.classList.add('active');
            const section = document.getElementById(tabBtn.getAttribute('data-section'));
            section.classList.add('active');
            section.style.display = 'block';
        });
    });

    // Search filter for current stock
    const stockSearch = document.getElementById('stockSearch');
    if (stockSearch) {
        stockSearch.addEventListener('input', function() {
            const value = stockSearch.value.toLowerCase();
            document.querySelectorAll('#current-stock tbody tr').forEach(function(row) {
                row.style.display = row.textContent.toLowerCase().includes(value) ? '' : 'none';
            });
        });
    }

    // Search filter for stock history
    const historySearch = document.getElementById('historySearch');
    if (historySearch) {
        historySearch.addEventListener('input', function() {
            const value = historySearch.value.toLowerCase();
            document.querySelectorAll('#stock-history tbody tr').forEach(function(row) {
                row.style.display = row.textContent.toLowerCase().includes(value) ? '' : 'none';
            });
        });
    }

    // Search filter for supplier stock
    const supplierSearch = document.getElementById('supplierSearch');
    if (supplierSearch) {
        supplierSearch.addEventListener('input', function() {
            const value = supplierSearch.value.toLowerCase();
            document.querySelectorAll('#supplier-stock tbody tr').forEach(function(row) {
                row.style.display = row.textContent.toLowerCase().includes(value) ? '' : 'none';
            });
        });
    }

    // Export CSV for tables
    function exportTableToCSV(tableSelector, filename) {
        const rows = document.querySelectorAll(tableSelector + " tr");
        let csv = [];
        rows.forEach(row => {
            let cols = row.querySelectorAll("th, td");
            let rowData = [];
            cols.forEach(col => {
                // Escape double quotes
                rowData.push('"' + col.innerText.replace(/"/g, '""') + '"');
            });
            csv.push(rowData.join(","));
        });
        // Download CSV
        const csvFile = new Blob([csv.join("\n")], { type: "text/csv" });
        const downloadLink = document.createElement("a");
        downloadLink.download = filename;
        downloadLink.href = window.URL.createObjectURL(csvFile);
        downloadLink.style.display = "none";
        document.body.appendChild(downloadLink);
        downloadLink.click();
        document.body.removeChild(downloadLink);
    }

    const exportCurrent = document.getElementById('exportCurrent');
    if (exportCurrent) {
        exportCurrent.addEventListener('click', function() {
            exportTableToCSV('#current-stock table', 'current_stock.csv');
        });
    }
    const exportHistory = document.getElementById('exportHistory');
    if (exportHistory) {
        exportHistory.addEventListener('click', function() {
            exportTableToCSV('#stock-history table', 'stock_history.csv');
        });
    }
    const exportSupplier = document.getElementById('exportSupplier');
    if (exportSupplier) {
        exportSupplier.addEventListener('click', function() {
            exportTableToCSV('#supplier-stock table', 'supplier_stock.csv');
        });
    }
});
</script>
</body>
</html>