<?php
require 'config.php';
requireLogin();
?>

<?php include 'header.php'; ?>

<h1>Dashboard</h1>

<div class="dashboard-cards">
    <div class="card">
        <h3>Total Products</h3>
        <?php
        $count = $pdo->query("SELECT COUNT(*) FROM Products")->fetchColumn();
        echo "<p>$count</p>";
        ?>
    </div>
    
    <div class="card">
        <h3>Low Stock Items</h3>
        <?php
        $count = $pdo->query("SELECT COUNT(*) FROM Stock WHERE QuantityAdded < 10")->fetchColumn();
        echo "<p>$count</p>";
        ?>
    </div>
    
    <div class="card">
        <h3>Today's Sales</h3>
        <?php
        $total = $pdo->query("SELECT SUM(TotalAmount) FROM Sales WHERE DATE(SaleDate) = CURDATE()")->fetchColumn();
        echo "<p>$" . number_format($total ?: 0, 2) . "</p>";
        ?>
    </div>
</div>

<h2>Recent Activity</h2>
<?php
$activity = $pdo->query("
    (SELECT 'Sale' as type, SaleDate as date, CONCAT('Sold ', QuantitySold, ' of product #', ProductID) as details FROM Sales ORDER BY SaleDate DESC LIMIT 3)
    UNION
    (SELECT 'Stock' as type, DateAdded as date, CONCAT('Added ', QuantityAdded, ' to product #', ProductID) as details FROM Stock ORDER BY DateAdded DESC LIMIT 3)
    ORDER BY date DESC LIMIT 5
")->fetchAll();
?>

<ul class="activity-list">
    <?php foreach ($activity as $item): ?>
    <li>
        <strong><?= $item['type'] ?></strong> on <?= date('m/d/Y H:i', strtotime($item['date'])) ?>:
        <?= $item['details'] ?>
    </li>
    <?php endforeach; ?>
</ul>

<?php include 'footer.php'; ?>