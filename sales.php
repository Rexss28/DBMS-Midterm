<?php
session_start();
require 'config.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'staff';

// Function to check stock availability
function checkStockAvailability($pdo, $productId) {
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(s.QuantityAdded), 0) as TotalAdded,
            COALESCE((SELECT SUM(QuantitySold) FROM Sales WHERE ProductID = ?), 0) as TotalSold
        FROM 
            Stock s
        WHERE 
            s.ProductID = ?
    ");
    $stmt->execute([$productId, $productId]);
    $result = $stmt->fetch();
    
    return [
        'available' => $result['TotalAdded'] - $result['TotalSold'],
        'total_added' => $result['TotalAdded'],
        'total_sold' => $result['TotalSold']
    ];
}

// Handle new sale (both admin and staff can record sales)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_sale'])) {
    try {
        $pdo->beginTransaction();
        
        $productId = $_POST['product_id'];
        $quantity = $_POST['quantity'];
        $userId = $_SESSION['user_id'];
        
        // Validate inputs
        if (!is_numeric($quantity) || $quantity <= 0) {
            throw new Exception("Invalid quantity specified");
        }
        
        // Get product price and check if product exists
        $stmt = $pdo->prepare("SELECT ProductID, Name, Price FROM Products WHERE ProductID = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if (!$product) {
            throw new Exception("Product not found");
        }
        
        // Check stock availability
        $stockInfo = checkStockAvailability($pdo, $productId);
        
        if ($stockInfo['available'] < $quantity) {
            throw new Exception("Insufficient stock. Only {$stockInfo['available']} available.");
        }
        
        // Calculate total amount
        $totalAmount = $product['Price'] * $quantity;
        
        // Apply discount if any
        $discount = isset($_POST['discount']) && is_numeric($_POST['discount']) ? $_POST['discount'] : 0;
        $discountAmount = ($totalAmount * $discount) / 100;
        $finalAmount = $totalAmount - $discountAmount;
        
        // Record sale (only columns that exist in your DB)
        $stmt = $pdo->prepare("
            INSERT INTO Sales (
                ProductID, QuantitySold, SaleDate, TotalAmount, UserID
            ) VALUES (?, ?, NOW(), ?, ?)
        ");
        $stmt->execute([
            $productId, $quantity, $finalAmount, $userId
        ]);
        $saleId = $pdo->lastInsertId();
        
        $pdo->commit();
        $success = "Sale #$saleId recorded successfully!";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Get products for dropdown
$stmt = $pdo->prepare("
    SELECT 
        p.*, 
        COALESCE(SUM(s.QuantityAdded), 0) - COALESCE((SELECT SUM(QuantitySold) FROM Sales WHERE ProductID = p.ProductID), 0) as StockAvailable
    FROM 
        Products p
    LEFT JOIN 
        Stock s ON p.ProductID = s.ProductID
    GROUP BY 
        p.ProductID
    HAVING 
        StockAvailable > 0
    ORDER BY 
        p.Category, p.Name
");
$stmt->execute();
$products = $stmt->fetchAll();

// Get sales history with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Get total sales count for pagination
$totalSales = $pdo->query("SELECT COUNT(*) FROM Sales")->fetchColumn();
$totalPages = ceil($totalSales / $perPage);

// Get filtered sales history
$filterConditions = [];
$params = [];

if (isset($_GET['filter_date_from']) && $_GET['filter_date_from']) {
    $filterConditions[] = "s.SaleDate >= ?";
    $params[] = $_GET['filter_date_from'] . ' 00:00:00';
}

if (isset($_GET['filter_date_to']) && $_GET['filter_date_to']) {
    $filterConditions[] = "s.SaleDate <= ?";
    $params[] = $_GET['filter_date_to'] . ' 23:59:59';
}

if (isset($_GET['filter_product']) && $_GET['filter_product']) {
    $filterConditions[] = "s.ProductID = ?";
    $params[] = $_GET['filter_product'];
}

$whereClause = '';
if (!empty($filterConditions)) {
    $whereClause = "WHERE " . implode(" AND ", $filterConditions);
}

$stmt = $pdo->prepare("
    SELECT 
        s.*, 
        p.Name as ProductName, 
        u.Username,
        p.Price as UnitPrice
    FROM 
        Sales s
    JOIN 
        Products p ON s.ProductID = p.ProductID
    JOIN 
        Users u ON s.UserID = u.UserID
    $whereClause
    ORDER BY 
        s.SaleDate DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$sales = $stmt->fetchAll();

// Get all products for filter dropdown
$allProducts = $pdo->query("SELECT ProductID, Name FROM Products ORDER BY Name")->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Sales Management</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css styles/base.css">
    <link rel="stylesheet" href="css styles/sidebar.css">
    <link rel="stylesheet" href="css styles/alerts.css">
    <link rel="stylesheet" href="css styles/sales.css">
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
            <a href="stock.php"><i class="fas fa-warehouse"></i> Stock</a>
            <a href="sales.php" class="active"><i class="fas fa-cash-register"></i> Sales</a>
            <?php if ($role === 'admin'): ?>
                <a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a>
            <?php endif; ?>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
        <div class="container">
            <h1>Sales Management</h1>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php
            // Get sales statistics
            $todaySales = $pdo->query("SELECT COUNT(*) as count, SUM(TotalAmount) as total FROM Sales WHERE DATE(SaleDate) = CURDATE()")->fetch();
            $weekSales = $pdo->query("SELECT COUNT(*) as count, SUM(TotalAmount) as total FROM Sales WHERE SaleDate >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetch();
            $monthSales = $pdo->query("SELECT COUNT(*) as count, SUM(TotalAmount) as total FROM Sales WHERE MONTH(SaleDate) = MONTH(CURDATE()) AND YEAR(SaleDate) = YEAR(CURDATE())")->fetch();
            ?>
            
            <div class="stats-container">
                <div class="stat-card">
                    <h3>Today's Sales</h3>
                    <div class="stat-value"><?= $todaySales['count'] ?></div>
                    <div>$<?= number_format($todaySales['total'] ?? 0, 2) ?></div>
                </div>
                <div class="stat-card">
                    <h3>This Week</h3>
                    <div class="stat-value"><?= $weekSales['count'] ?></div>
                    <div>$<?= number_format($weekSales['total'] ?? 0, 2) ?></div>
                </div>
                <div class="stat-card">
                    <h3>This Month</h3>
                    <div class="stat-value"><?= $monthSales['count'] ?></div>
                    <div>$<?= number_format($monthSales['total'] ?? 0, 2) ?></div>
                </div>
            </div>
            
            <div class="sales-container">
                <div class="sales-form">
                    <h2>Record New Sale</h2>
                    <form method="POST" id="saleForm">
                        <div class="form-row">
                            <label for="product_id">Select Product:</label>
                            <select name="product_id" id="product_id" required>
                                <option value="">-- Select Product --</option>
                                <?php foreach ($products as $product): ?>
                                    <?php 
                                        $stockClass = '';
                                        $stockText = '';
                                        if ($product['StockAvailable'] < 5) {
                                            $stockClass = 'low-stock';
                                            $stockText = 'Low Stock';
                                        } else {
                                            $stockClass = 'in-stock';
                                            $stockText = 'In Stock';
                                        }
                                    ?>
                                    <option value="<?= $product['ProductID'] ?>" data-price="<?= $product['Price'] ?>" data-stock="<?= $product['StockAvailable'] ?>">
                                        <?= htmlspecialchars($product['Name']) ?> - $<?= number_format($product['Price'], 2) ?> 
                                        (<?= $product['StockAvailable'] ?> available)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-row">
                            <label for="quantity">Quantity:</label>
                            <input type="number" name="quantity" id="quantity" min="1" value="1" required>
                            <span id="stockWarning" style="color: red; display: none;">Warning: Not enough stock available!</span>
                        </div>
                        
                        <div class="form-row">
                            <label for="payment_method">Payment Method:</label>
                            <select name="payment_method" id="payment_method" required>
                                <option value="Cash">Cash</option>
                                <option value="Credit Card">Credit Card</option>
                                <option value="Debit Card">Debit Card</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                            </select>
                        </div>
                        
                        <div class="form-row">
                            <label for="customer_name">Customer Name (Optional):</label>
                            <input type="text" name="customer_name" id="customer_name" placeholder="Walk-in Customer">
                        </div>
                        
                        <div class="form-row">
                            <label for="discount">Discount (%):</label>
                            <input type="number" name="discount" id="discount" min="0" max="100" value="0">
                        </div>
                        
                        <div class="form-row">
                            <button type="submit" name="record_sale" id="submitSale">Record Sale</button>
                        </div>
                    </form>
                </div>
                
                <div class="sales-summary">
                    <h2>Sale Summary</h2>
                    <div id="productDetails">
                        <p>Select a product to see details</p>
                    </div>
                    
                    <div class="summary-section" style="margin-top: 1.5rem;">
                        <div class="form-row">
                            <label>Subtotal:</label>
                            <div id="saleTotal">$0.00</div>
                        </div>
                        <div class="form-row">
                            <label>Discount Amount:</label>
                            <div id="discountAmount">$0.00</div>
                        </div>
                        <div class="form-row">
                            <label>Final Total:</label>
                            <div id="finalTotal">$0.00</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <h2>Sales History</h2>
            
            <div class="filters">
                <form method="GET" id="filterForm">
                    <div class="filter-group">
                        <label for="filter_date_from">From Date:</label>
                        <input type="date" id="filter_date_from" name="filter_date_from" value="<?= isset($_GET['filter_date_from']) ? htmlspecialchars($_GET['filter_date_from']) : '' ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="filter_date_to">To Date:</label>
                        <input type="date" id="filter_date_to" name="filter_date_to" value="<?= isset($_GET['filter_date_to']) ? htmlspecialchars($_GET['filter_date_to']) : '' ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="filter_product">Product:</label>
                        <select id="filter_product" name="filter_product">
                            <option value="">All Products</option>
                            <?php foreach ($allProducts as $product): ?>
                                <option value="<?= $product['ProductID'] ?>" <?= (isset($_GET['filter_product']) && $_GET['filter_product'] == $product['ProductID']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($product['Name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <button type="submit">Apply Filters</button>
                        <button type="button" id="resetFilters">Reset</button>
                    </div>
                </form>
            </div>
            
            <table class="sales-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Product</th>
                        <th>Unit Price</th>
                        <th>Quantity</th>
                        <th>Total Amount</th>
                        <th>Sold By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($sales) > 0): ?>
                        <?php foreach ($sales as $sale): ?>
                        <tr>
                            <td><?= $sale['SaleID'] ?></td>
                            <td><?= date('M d, Y H:i', strtotime($sale['SaleDate'])) ?></td>
                            <td><?= htmlspecialchars($sale['ProductName']) ?></td>
                            <td>$<?= number_format($sale['UnitPrice'], 2) ?></td>
                            <td><?= $sale['QuantitySold'] ?></td>
                            <td>$<?= number_format($sale['TotalAmount'], 2) ?></td>
                            <td><?= htmlspecialchars($sale['Username']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">No sales found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= isset($_GET['filter_date_from']) ? '&filter_date_from=' . htmlspecialchars($_GET['filter_date_from']) : '' ?><?= isset($_GET['filter_date_to']) ? '&filter_date_to=' . htmlspecialchars($_GET['filter_date_to']) : '' ?><?= isset($_GET['filter_product']) ? '&filter_product=' . htmlspecialchars($_GET['filter_product']) : '' ?>">Previous</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i ?><?= isset($_GET['filter_date_from']) ? '&filter_date_from=' . htmlspecialchars($_GET['filter_date_from']) : '' ?><?= isset($_GET['filter_date_to']) ? '&filter_date_to=' . htmlspecialchars($_GET['filter_date_to']) : '' ?><?= isset($_GET['filter_product']) ? '&filter_product=' . htmlspecialchars($_GET['filter_product']) : '' ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?><?= isset($_GET['filter_date_from']) ? '&filter_date_from=' . htmlspecialchars($_GET['filter_date_from']) : '' ?><?= isset($_GET['filter_date_to']) ? '&filter_date_to=' . htmlspecialchars($_GET['filter_date_to']) : '' ?><?= isset($_GET['filter_product']) ? '&filter_product=' . htmlspecialchars($_GET['filter_product']) : '' ?>">Next</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const productSelect = document.getElementById('product_id');
        const quantityInput = document.getElementById('quantity');
        const discountInput = document.getElementById('discount');
        const saleTotal = document.getElementById('saleTotal');
        const discountAmount = document.getElementById('discountAmount');
        const finalTotal = document.getElementById('finalTotal');
        const productDetails = document.getElementById('productDetails');
        const stockWarning = document.getElementById('stockWarning');
        const resetFilters = document.getElementById('resetFilters');
        
        // Function to update sale totals
        function updateSaleTotals() {
            if (productSelect.value) {
                const selectedOption = productSelect.options[productSelect.selectedIndex];
                const price = parseFloat(selectedOption.dataset.price);
                const stockAvailable = parseInt(selectedOption.dataset.stock);
                const quantity = parseInt(quantityInput.value) || 0;
                const discount = parseInt(discountInput.value) || 0;
                
                // Check stock availability
                if (quantity > stockAvailable) {
                    stockWarning.style.display = 'block';
                    document.getElementById('submitSale').disabled = true;
                } else {
                    stockWarning.style.display = 'none';
                    document.getElementById('submitSale').disabled = false;
                }
                
                // Calculate totals
                const subtotal = price * quantity;
                const discountValue = (subtotal * discount) / 100;
                const total = subtotal - discountValue;
                
                // Update display
                saleTotal.textContent = '$' + subtotal.toFixed(2);
                discountAmount.textContent = '$' + discountValue.toFixed(2);
                finalTotal.textContent = '$' + total.toFixed(2);
                
                // Update product details
                productDetails.innerHTML = `
                    <div class="product-detail-box">
                        <h3>${selectedOption.textContent.split('-')[0].trim()}</h3>
                        <p><strong>Unit Price:</strong> $${price.toFixed(2)}</p>
                        <p><strong>Stock Available:</strong> ${stockAvailable}</p>
                    </div>
                `;
            } else {
                saleTotal.textContent = '$0.00';
                discountAmount.textContent = '$0.00';
                finalTotal.textContent = '$0.00';
                productDetails.innerHTML = '<p>Select a product to see details</p>';
            }
        }
        
        // Event listeners
        productSelect.addEventListener('change', updateSaleTotals);
        quantityInput.addEventListener('input', updateSaleTotals);
        discountInput.addEventListener('input', updateSaleTotals);
        
        // Reset filters
        resetFilters.addEventListener('click', function() {
            document.getElementById('filter_date_from').value = '';
            document.getElementById('filter_date_to').value = '';
            document.getElementById('filter_product').value = '';
            document.getElementById('filterForm').submit();
        });
        
        // Initialize totals on page load
        updateSaleTotals();
    });
    </script>
</body>
</html>