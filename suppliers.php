<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin')) {
    header('Location: dashboard.php');
    exit();
}

// Handle form submissions (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_supplier'])) {
        $name = $_POST['name'];
        $contact = $_POST['contact'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        
        $stmt = $pdo->prepare("INSERT INTO Suppliers (Name, ContactInfo, Email, Phone) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $contact, $email, $phone]);
        $success = "Supplier added successfully!";
    }
    
    if (isset($_POST['delete_supplier'])) {
        $supplierId = $_POST['supplier_id'];
        $pdo->beginTransaction();
        try {
            $stmtStock = $pdo->prepare("DELETE FROM Stock WHERE SupplierID = ?");
            $stmtStock->execute([$supplierId]);
            $stmtSP = $pdo->prepare("DELETE FROM SupplierProducts WHERE SupplierID = ?");
            $stmtSP->execute([$supplierId]);
            $stmtSupplier = $pdo->prepare("DELETE FROM Suppliers WHERE SupplierID = ?");
            $stmtSupplier->execute([$supplierId]);
            $pdo->commit();
            $success = "Supplier deleted successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

$suppliers = $pdo->query("SELECT * FROM Suppliers")->fetchAll();

if (count($suppliers) == 0) {
    $pdo->query("ALTER TABLE Suppliers AUTO_INCREMENT = 1");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Management - Inventory Management System</title>
    <link rel="stylesheet" href="css styles/base.css">
    <link rel="stylesheet" href="css styles/sidebar.css">
    <link rel="stylesheet" href="css styles/alerts.css">
    <link rel="stylesheet" href="css styles/suppliers.css">
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
            <a href="suppliers.php" class="active"><i class="fas fa-truck"></i> Suppliers</a>
            <a href="stock.php"><i class="fas fa-warehouse"></i> Stock</a>
            <a href="sales.php"><i class="fas fa-cash-register"></i> Sales</a>
            <a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>

        <div class="container">
            <div class="page-header">
                <h1>Supplier Management</h1>
                <button id="toggleFormBtn" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Supplier</button>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= $success ?>
                </div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <!-- Add New Supplier Form -->
            <div id="addSupplierForm" class="form-container" style="display: none;">
                <div class="card">
                    <h2>Add New Supplier</h2>
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="name">Supplier Name</label>
                                <input type="text" id="name" name="name" required placeholder="Supplier Name">
                            </div>
                            <div class="form-group">
                                <label for="contact">Contact Person</label>
                                <input type="text" id="contact" name="contact" required placeholder="Contact Person">
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" placeholder="Email">
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <input type="text" id="phone" name="phone" placeholder="Phone">
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="add_supplier" class="btn btn-success"><i class="fas fa-save"></i> Save Supplier</button>
                            <button type="button" id="cancelAddBtn" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Supplier List -->
            <div class="card table-card">
                <h2>Supplier List</h2>
                <?php if (count($suppliers) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($suppliers as $supplier): ?>
                            <tr>
                                <td><?= $supplier['SupplierID'] ?></td>
                                <td><?= htmlspecialchars($supplier['Name']) ?></td>
                                <td><?= htmlspecialchars($supplier['ContactInfo']) ?></td>
                                <td><?= htmlspecialchars($supplier['Email']) ?></td>
                                <td><?= htmlspecialchars($supplier['Phone']) ?></td>
                                <td class="actions">
                                    <form method="POST" class="delete-form" onsubmit="return confirm('Are you sure you want to delete this supplier?');" style="display:inline;">
                                        <input type="hidden" name="supplier_id" value="<?= $supplier['SupplierID'] ?>">
                                        <button type="submit" name="delete_supplier" class="action-btn delete-btn" title="Delete"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-truck"></i></div>
                        <p>No suppliers found. Add your first supplier to get started.</p>
                        <button id="emptyAddBtn" class="btn btn-primary"><i class="fas fa-plus"></i> Add Supplier</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    // Toggle add supplier form
    document.getElementById('toggleFormBtn')?.addEventListener('click', function() {
        document.getElementById('addSupplierForm').style.display = 'block';
        this.style.display = 'none';
    });
    document.getElementById('cancelAddBtn')?.addEventListener('click', function() {
        document.getElementById('addSupplierForm').style.display = 'none';
        document.getElementById('toggleFormBtn').style.display = 'inline-block';
    });
    document.getElementById('emptyAddBtn')?.addEventListener('click', function() {
        document.getElementById('addSupplierForm').style.display = 'block';
        document.getElementById('toggleFormBtn').style.display = 'none';
    });
    </script>
</body>
</html>