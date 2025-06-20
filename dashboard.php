<?php 
session_start(); 
require 'config.php'; 

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user data
$username = $_SESSION['username'];
$role = isset($_SESSION['role']) ? strtolower($_SESSION['role']) : 'staff';

// DEBUG: Show current role in HTML comment (remove after testing)
echo "<!-- SESSION ROLE: " . htmlspecialchars($role) . " -->";

// Only fetch and show sales/stock data for admin and staff
if ($role !== 'customer') {
    // Get sales summary data
    $todaySales = $pdo->query("SELECT COUNT(*) as count, SUM(TotalAmount) as total FROM Sales WHERE DATE(SaleDate) = CURDATE()")->fetch();
    $monthlySales = $pdo->query("SELECT COUNT(*) as count, SUM(TotalAmount) as total FROM Sales WHERE MONTH(SaleDate) = MONTH(CURDATE()) AND YEAR(SaleDate) = YEAR(CURDATE())")->fetch();

    // Get top selling products
    $topProducts = $pdo->query("
        SELECT p.Name, SUM(s.QuantitySold) as TotalQuantity, SUM(s.TotalAmount) as TotalRevenue
        FROM Sales s
        JOIN Products p ON s.ProductID = p.ProductID
        GROUP BY s.ProductID
        ORDER BY TotalQuantity DESC
        LIMIT 5
    ")->fetchAll();

    // Get low stock alerts
    $lowStockProducts = $pdo->query("
        WITH ProductStock AS (
            SELECT p.ProductID, p.Name, COALESCE(SUM(s.QuantityAdded), 0) as TotalStock
            FROM Products p
            LEFT JOIN Stock s ON p.ProductID = s.ProductID
            GROUP BY p.ProductID
        )
        SELECT * FROM ProductStock WHERE TotalStock < 10
        LIMIT 5
    ")->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="css styles/base.css">
    <link rel="stylesheet" href="css styles/sidebar.css">
    <link rel="stylesheet" href="css styles/alerts.css">
    <link rel="stylesheet" href="css styles/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <header>
        <h1>Inventory Management System</h1>
    </header>
    
    <div class="main-layout">
        <nav class="sidebar">
            <?php if ($role === 'customer'): ?>
                <a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
                <a href="shop.php"><i class="fas fa-shopping-cart"></i> Shop</a>
                <a href="orders.php"><i class="fas fa-receipt"></i> My Orders</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            <?php else: ?>
                <a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
                <a href="products.php"><i class="fas fa-box"></i> Products</a>
                <?php if ($role === 'admin'): ?>
                    <a href="suppliers.php"><i class="fas fa-truck"></i> Suppliers</a>
                <?php endif; ?>
                <a href="stock.php"><i class="fas fa-warehouse"></i> Stock</a>
                <a href="sales.php"><i class="fas fa-cash-register"></i> Sales</a>
                <?php if ($role === 'admin'): ?>
                    <a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a>
                <?php endif; ?>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            <?php endif; ?>
        </nav>
        <div class="container">
            <!-- <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1> -->

            <?php if ($role === 'customer'): ?>
                <div class="customer-dashboard">
                    <!-- Big Welcome Section -->
                    <div class="welcome-section">
                        <h1 class="welcome-title">Welcome, <?= htmlspecialchars($username) ?>!</h1>
                        <p class="welcome-subtitle">Thank you for being a valued customer. Explore our shop and manage your orders with ease.</p>
                    </div>
                </div>
            <?php else: ?>
                <!-- Metrics Cards -->
                <div class="metrics-container">
                    <div class="metric-card">
                        <h3>Today's Sales</h3>
                        <div class="metric-value">$<?= number_format($todaySales['total'] ?? 0, 2) ?></div>
                        <div class="metric-subtext"><?= $todaySales['count'] ?? 0 ?> sales</div>
                    </div>
                    <div class="metric-card">
                        <h3>This Month's Sales</h3>
                        <div class="metric-value">$<?= number_format($monthlySales['total'] ?? 0, 2) ?></div>
                        <div class="metric-subtext"><?= $monthlySales['count'] ?? 0 ?> sales</div>
                    </div>
                </div>

                <!-- Dashboard Summary -->
                <div class="dashboard-summary">
                    <div class="summary-section">
                        <h2>Top Selling Products</h2>
                        <table class="mini-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Sold</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topProducts as $prod): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($prod['Name']) ?></td>
                                        <td><?= $prod['TotalQuantity'] ?></td>
                                        <td>$<?= number_format($prod['TotalRevenue'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($topProducts)): ?>
                                    <tr><td colspan="3">No data</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="summary-section">
                        <h2>Low Stock Alerts</h2>
                        <table class="mini-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Stock</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lowStockProducts as $prod): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($prod['Name']) ?></td>
                                        <td><?= $prod['TotalStock'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($lowStockProducts)): ?>
                                    <tr><td colspan="2">No low stock</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Sales Chart -->
                <div class="chart-container">
                    <h2>Sales (Last 7 Days)</h2>
                    <canvas id="salesChart" height="80"></canvas>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($role !== 'customer'): ?>
    <script>
    // Get sales data for the past 7 days
    <?php
    $salesData = $pdo->query("
        SELECT DATE(SaleDate) as date, SUM(TotalAmount) as total
        FROM Sales
        WHERE SaleDate >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(SaleDate)
        ORDER BY date
    ")->fetchAll(PDO::FETCH_ASSOC);

    $labels = [];
    $data = [];

    // Create arrays for chart
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $labels[] = date('M d', strtotime($date));

        // Find if we have sales for this date
        $found = false;
        foreach ($salesData as $sale) {
            if ($sale['date'] == $date) {
                $data[] = $sale['total'];
                $found = true;
                break;
            }
        }

        if (!$found) {
            $data[] = 0;
        }
    }
    ?>
    // Create the sales chart
    const ctx = document.getElementById('salesChart').getContext('2d');
    const salesChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [{
                label: 'Daily Sales ($)',
                data: <?= json_encode($data) ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    </script>
    <?php endif; ?>
</body>
</html>