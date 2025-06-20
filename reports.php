<?php 
session_start();
require 'config.php';

// Check if the user is logged in and is admin
if (!isset($_SESSION['user_id']) || (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin')) {
    header('Location: dashboard.php');
    exit();
}

// Get user data
$username = $_SESSION['username'];

// Set default date range (current month)
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Calculate date difference to limit chart data points
$dateDiff = ceil((strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24));
$interval = 1; // Default daily interval

// If date range is too large, increase interval to weekly or monthly
if ($dateDiff > 30) {
    $interval = 7; // Weekly interval
} elseif ($dateDiff > 90) {
    $interval = 30; // Monthly interval
}

// Prepare date filtering for queries
$dateFilter = "WHERE SaleDate BETWEEN :start_date AND :end_date";
$dateParams = [
    ':start_date' => $startDate . ' 00:00:00',
    ':end_date' => $endDate . ' 23:59:59'
];

// Get sales summary
$stmt = $pdo->prepare("SELECT SUM(TotalAmount) FROM Sales $dateFilter");
$stmt->execute($dateParams);
$totalSales = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT SUM(QuantitySold) FROM Sales $dateFilter");
$stmt->execute($dateParams);
$totalItems = $stmt->fetchColumn();

// Top products by revenue
$stmt = $pdo->prepare("
    SELECT p.Name, SUM(s.QuantitySold) as TotalSold, SUM(s.TotalAmount) as Revenue,
           (SUM(s.TotalAmount) / (SELECT SUM(TotalAmount) FROM Sales $dateFilter)) * 100 as Percentage
    FROM Sales s
    JOIN Products p ON s.ProductID = p.ProductID
    $dateFilter
    GROUP BY p.ProductID
    ORDER BY Revenue DESC
    LIMIT 5
");
$stmt->execute($dateParams);
$topProducts = $stmt->fetchAll();

// Top suppliers by product volume
$stmt = $pdo->prepare("
    SELECT sup.Name as SupplierName, COUNT(DISTINCT s.ProductID) as ProductCount, 
           SUM(st.QuantityAdded) as TotalSupplied
    FROM Suppliers sup
    JOIN Stock st ON sup.SupplierID = st.SupplierID
    JOIN Sales s ON s.ProductID = st.ProductID
    $dateFilter
    GROUP BY sup.SupplierID
    ORDER BY TotalSupplied DESC
    LIMIT 5
");
$stmt->execute($dateParams);
$topSuppliers = $stmt->fetchAll();

// Recent stock additions
$recentStock = $pdo->query("
    SELECT s.*, p.Name as ProductName, sp.Name as SupplierName
    FROM Stock s
    JOIN Products p ON s.ProductID = p.ProductID
    JOIN Suppliers sp ON s.SupplierID = sp.SupplierID
    ORDER BY s.DateAdded DESC
    LIMIT 10
")->fetchAll();

// Calculate product category breakdown
$stmt = $pdo->prepare("
    SELECT p.Category, SUM(s.TotalAmount) as Revenue
    FROM Sales s
    JOIN Products p ON s.ProductID = p.ProductID
    $dateFilter
    GROUP BY p.Category
    ORDER BY Revenue DESC
");
$stmt->execute($dateParams);
$categoryBreakdown = $stmt->fetchAll();

// Prepare category chart data
$categories = [];
$categoryRevenue = [];
foreach ($categoryBreakdown as $category) {
    $categories[] = $category['Category'];
    $categoryRevenue[] = $category['Revenue'];
}

// Get fixed number of data points for sales chart (maximum 10)
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(SaleDate, '%Y-%m-%d') as SaleDay, 
        SUM(TotalAmount) as DailyTotal 
    FROM Sales 
    $dateFilter 
    GROUP BY DATE_FORMAT(SaleDate, '%Y-%m-%d') 
    ORDER BY SaleDay ASC
");
$stmt->execute($dateParams);
$rawSalesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Limit to max 10 data points regardless of date range
$displaySalesData = [];
if (count($rawSalesData) > 10) {
    $chunkSize = ceil(count($rawSalesData) / 10);
    $chunks = array_chunk($rawSalesData, $chunkSize);
    
    foreach ($chunks as $i => $chunk) {
        if ($i < 10) { // Only take first 10 chunks
            $total = 0;
            foreach ($chunk as $day) {
                $total += $day['DailyTotal'];
            }
            
            $firstDay = reset($chunk)['SaleDay'];
            $lastDay = end($chunk)['SaleDay'];
            
            if ($firstDay == $lastDay) {
                $label = date('M d', strtotime($firstDay));
            } else {
                $label = date('M d', strtotime($firstDay)) . ' - ' . date('M d', strtotime($lastDay));
            }
            
            $displaySalesData[] = [
                'label' => $label,
                'value' => $total
            ];
        }
    }
} else {
    foreach ($rawSalesData as $day) {
        $displaySalesData[] = [
            'label' => date('M d', strtotime($day['SaleDay'])),
            'value' => $day['DailyTotal']
        ];
    }
}

// Extract data for chart
$chartLabels = array_column($displaySalesData, 'label');
$chartData = array_column($displaySalesData, 'value');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics</title>
    <link rel="stylesheet" href="css styles/base.css">
    <link rel="stylesheet" href="css styles/sidebar.css">
    <link rel="stylesheet" href="css styles/alerts.css">
    <link rel="stylesheet" href="css styles/reports.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <header>
        <h1>Inventory Management System</h1>
    </header>
    <div class="main-layout">
        <nav class="sidebar">
            <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="products.php"><i class="fas fa-box"></i> Products</a>
            <a href="suppliers.php"><i class="fas fa-truck"></i> Suppliers</a>
            <a href="stock.php"><i class="fas fa-warehouse"></i> Stock</a>
            <a href="sales.php"><i class="fas fa-cash-register"></i> Sales</a>
            <a href="reports.php" class="active"><i class="fas fa-chart-line"></i> Reports</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
        <div class="container">
            <div class="reports-header">
                <h1>Sales & Inventory Reports</h1>
                <span style="color:#4f8cff;font-weight:500;">Welcome, <?= htmlspecialchars($username); ?>!</span>
            </div>
            
            <!-- Date Range Filter -->
            <form method="GET" action="reports.php" class="reports-filters">
                <div class="filter-group">
                    <label for="start_date">Start Date:</label>
                    <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
                </div>
                <div class="filter-group">
                    <label for="end_date">End Date:</label>
                    <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
                </div>
                <button type="submit">Apply Filter</button>
                <a href="reports.php" class="btn btn-secondary">Reset</a>
            </form>
            
            <!-- Key Metrics Summary -->
            <div class="stats-row">
                <div class="stat-box">
                    <h3>Total Sales</h3>
                    <div class="stat-value">$<?= number_format($totalSales ?? 0, 2) ?></div>
                    <div style="font-size:0.95em;color:#7f8c8d;">
                        <?= date('M d', strtotime($startDate)) ?> - <?= date('M d', strtotime($endDate)) ?>
                    </div>
                </div>
                <div class="stat-box">
                    <h3>Items Sold</h3>
                    <div class="stat-value"><?= number_format($totalItems ?? 0) ?></div>
                    <div style="font-size:0.95em;color:#7f8c8d;">
                        <?= date('M d', strtotime($startDate)) ?> - <?= date('M d', strtotime($endDate)) ?>
                    </div>
                </div>
                <div class="stat-box">
                    <h3>Daily Average</h3>
                    <?php
                        $days = (strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24) + 1;
                        $dailyAvg = ($totalSales ?? 0) / ($days > 0 ? $days : 1);
                    ?>
                    <div class="stat-value">$<?= number_format($dailyAvg, 2) ?></div>
                    <div style="font-size:0.95em;color:#7f8c8d;">Per day average</div>
                </div>
            </div>
            
            <!-- Sales Trend Chart -->
            <div class="chart-container">
                <h2>Sales Trend</h2>
                <canvas id="salesTrendChart" style="max-height: 400px;"></canvas>
                <?php if (count($chartLabels) === 0): ?>
                    <p style="text-align:center;color:#888;">No sales data available for the selected period.</p>
                <?php endif; ?>
            </div>

            <div class="reports-grid" style="display:flex;gap:32px;flex-wrap:wrap;">
                <!-- Top Products Section -->
                <div class="reports-card" style="flex:1 1 350px;min-width:320px;">
                    <h2>Top Products by Revenue</h2>
                    <?php if (count($topProducts) > 0): ?>
                    <div class="table-responsive">
                        <table class="reports-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Units Sold</th>
                                    <th>Revenue</th>
                                    <th>% of Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topProducts as $product): ?>
                                <tr>
                                    <td><?= htmlspecialchars($product['Name']) ?></td>
                                    <td><?= number_format($product['TotalSold']) ?></td>
                                    <td>$<?= number_format($product['Revenue'], 2) ?></td>
                                    <td><?= number_format($product['Percentage'], 1) ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p>No sales data available for the selected period.</p>
                    <?php endif; ?>
                </div>
                
                <!-- Top Suppliers Section -->
                <div class="reports-card" style="flex:1 1 350px;min-width:320px;">
                    <h2>Top Suppliers</h2>
                    <?php if (count($topSuppliers) > 0): ?>
                    <div class="table-responsive">
                        <table class="reports-table">
                            <thead>
                                <tr>
                                    <th>Supplier</th>
                                    <th>Products</th>
                                    <th>Units Supplied</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topSuppliers as $supplier): ?>
                                <tr>
                                    <td><?= htmlspecialchars($supplier['SupplierName']) ?></td>
                                    <td><?= $supplier['ProductCount'] ?></td>
                                    <td><?= number_format($supplier['TotalSupplied']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p>No supplier data available for the selected period.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Category Breakdown Chart -->
            <div class="chart-container">
                <h2>Sales by Product Category</h2>
                <canvas id="categoryChart" style="max-height: 400px;"></canvas>
                <?php if (count($categories) === 0): ?>
                    <p style="text-align:center;color:#888;">No category data available for the selected period.</p>
                <?php endif; ?>
            </div>
            
            <!-- Recent Stock Section -->
            <div class="reports-card" style="margin-bottom:32px;">
                <h2>Recent Inventory Activity</h2>
                <?php if (count($recentStock) > 0): ?>
                <div class="table-responsive">
                    <table class="reports-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Supplier</th>
                                <th>Quantity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentStock as $item): ?>
                            <tr>
                                <td><?= date('M d, Y', strtotime($item['DateAdded'])) ?></td>
                                <td><?= htmlspecialchars($item['ProductName']) ?></td>
                                <td><?= htmlspecialchars($item['SupplierName']) ?></td>
                                <td><?= number_format($item['QuantityAdded']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p>No recent stock activity.</p>
                <?php endif; ?>
            </div>
            
            <!-- Export Options -->
            <div class="reports-card" style="text-align:center;">
                <h3>Export Reports</h3>
                <p>Download this report in your preferred format:</p>
                <a href="export.php?type=pdf&start=<?= urlencode($startDate) ?>&end=<?= urlencode($endDate) ?>" class="btn">Export as PDF</a>
                <a href="export.php?type=csv&start=<?= urlencode($startDate) ?>&end=<?= urlencode($endDate) ?>" class="btn">Export as CSV</a>
                <a href="export.php?type=excel&start=<?= urlencode($startDate) ?>&end=<?= urlencode($endDate) ?>" class="btn">Export as Excel</a>
            </div>
        </div>
    </div>
    <?php if (count($chartLabels) > 0): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sales Trend Chart
        const salesCtx = document.getElementById('salesTrendChart').getContext('2d');
        const salesLabels = <?= json_encode($chartLabels) ?>;
        const salesData = <?= json_encode($chartData) ?>;

        // Create gradient background for line chart
        const gradient = salesCtx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(54, 162, 235, 0.8)');
        gradient.addColorStop(1, 'rgba(54, 162, 235, 0.1)');

        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: salesLabels.length ? salesLabels : ['No Data'],
                datasets: [{
                    label: 'Sales Revenue',
                    data: salesData.length ? salesData : [0],
                    backgroundColor: gradient,
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(54, 162, 235, 1)',
                    pointBorderColor: '#fff',
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: {
                                size: 14
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleFont: {
                            size: 16
                        },
                        bodyFont: {
                            size: 14
                        },
                        callbacks: {
                            label: function(context) {
                                return '$' + context.parsed.y.toLocaleString('en-US', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                            });
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString('en-US');
                            },
                            font: {
                                size: 12
                            }
                        },
                        title: {
                            display: true,
                            text: 'Revenue ($)',
                            font: {
                                size: 14,
                                weight: 'bold'
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45,
                            font: {
                                size: 12
                            }
                        },
                        title: {
                            display: true,
                            text: 'Date Period',
                            font: {
                                size: 14,
                                weight: 'bold'
                            }
                        }
                    }
                }
            }
        });

        // Category Pie Chart
        const catCtx = document.getElementById('categoryChart').getContext('2d');
        const catLabels = <?= json_encode($categories) ?>;
        const catData = <?= json_encode($categoryRevenue) ?>;

        new Chart(catCtx, {
            type: 'pie',
            data: {
                labels: catLabels.length ? catLabels : ['No Data'],
                datasets: [{
                    data: catData.length ? catData : [1],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)',
                        'rgba(255, 159, 64, 0.7)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            font: {
                                size: 14
                            }
                        }
                    }
                }
            }
        });
    });
    </script>
    <?php endif; ?>
    <footer>
        <p style="text-align:center;color:#7f8c8d;margin:32px 0 0 0;">&copy; <?= date('Y') ?> Inventory Management System</p>
    </footer>
</body>
</html>