<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <title>Inventory System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <h1>Inventory Management System</h1>
        <nav>
            <?php if (isLoggedIn()): ?>
                <a href="index.php">Dashboard</a>
                <a href="products.php">Products</a>
                <a href="suppliers.php">Suppliers</a>
                <a href="stock.php">Stock</a>
                <a href="sales.php">Sales</a>
                <a href="reports.php">Reports</a>
                <span>Welcome, <?= $_SESSION['username'] ?> (<?= $_SESSION['role'] ?>)</span>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
            <?php endif; ?>
        </nav>
    </header>
    <div class="container">