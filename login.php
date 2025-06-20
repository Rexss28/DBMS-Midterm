<?php
session_start();
require 'config.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Join Users and Roles tables to get the role name
    $stmt = $pdo->prepare("
        SELECT u.*, r.RoleName 
        FROM Users u
        LEFT JOIN Roles r ON u.RoleID = r.RoleID
        WHERE u.Username = ?
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // Debug: Show raw role value for troubleshooting
    if ($user) {
        echo "<!-- RAW ROLE: '" . $user['RoleName'] . "' -->";
        $role = isset($user['RoleName']) ? $user['RoleName'] : '';
        echo "<!-- BEFORE CLEAN: '" . $role . "' -->";
        $role = strtolower(trim($role));
        echo "<!-- AFTER TRIM/LOWER: '" . $role . "' -->";
        $role = preg_replace('/\s+/', '', $role);
        echo "<!-- AFTER PREG_REPLACE: '" . $role . "' -->";
    }

    if ($user && $user['Password'] === $password) {
        $_SESSION['user_id'] = $user['UserID'];
        $_SESSION['username'] = $user['Username'];
        // Set session role for admin, staff, or customer
        if ($role === 'admin') {
            $_SESSION['role'] = 'admin';
        } elseif ($role === 'customer') {
            $_SESSION['role'] = 'customer';
        } else {
            $_SESSION['role'] = 'staff';
        }
        echo "<!-- SESSION ROLE SET TO: " . $_SESSION['role'] . " -->";
        header('Location: dashboard.php');
        exit();
    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management System - Login</title>
    <link rel="stylesheet" href="css styles/base.css">
    <link rel="stylesheet" href="css styles/alerts.css">
    <link rel="stylesheet" href="css styles/login.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-header">
            <div class="logo-container">
                <div class="system-logo"></div>
            </div>
            <h1>Inventory Management System</h1>
        </div>
        
        <div class="login-form-container">
            <h2>Sign In</h2>
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-with-icon username-icon">
                        <input type="text" name="username" id="username" placeholder="Enter your username" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-with-icon password-icon">
                        <input type="password" name="password" id="password" placeholder="Enter your password" required>
                    </div>
                </div>
                
                <button type="submit" class="login-btn">Sign In</button>
            </form>
        </div>
    </div>
</body>
</html>