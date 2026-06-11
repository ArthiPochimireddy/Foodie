<?php
// backend/config/db.php

// Database configuration
$host = 'localhost';
$dbname = 'food_ordering_db';
$username = 'root'; // Default XAMPP username
$password = ''; // Default XAMPP password is empty

try {
    // Create a new PDO instance for secure database connection
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on error
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch associative arrays by default
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Use real prepared statements for security
    ];
    
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // For testing purposes, you can uncomment the line below to check connection
    // echo "Connected successfully to the database!";
    
} catch (PDOException $e) {
    // Handle connection errors securely without exposing sensitive details to users
    error_log("Database Connection Error: " . $e->getMessage());
    die(json_encode([
        "status" => "error",
        "message" => "Database connection failed. Please check your configuration."
    ]));
}
?>
