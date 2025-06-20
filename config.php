<?php
// Prevent multiple inclusions
if (!defined('CONFIG_INCLUDED')) {
    define('CONFIG_INCLUDED', true);
    
    $host = 'localhost';
    $dbname = 'inventorydb';
    $username = 'root';  // Replace with your database username if needed
    $password = '';      // Replace with your database password if needed
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("Could not connect to the database $dbname :" . $e->getMessage());
    }
    
    // Check if the user is logged in - for redirecting
    function requireLogin() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: login.php');
            exit();
        }
    }
    
    // Check if user is logged in - for conditional display
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    // Check if the user is an admin
    function requireAdmin() {
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
            header('Location: dashboard.php');
            exit();
        }
    }
}
?>