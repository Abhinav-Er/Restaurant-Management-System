<?php
// One place for the database connection. Every page or action that needs data starts with:
//   require_once __DIR__ . '/../config/db.php';      (from the action/ folder)
//   require_once __DIR__ . '/../../config/db.php';   (from action/admin/ or public/ subfolders, adjust the ../)
// and then uses the $pdo variable.

$db_host = 'localhost';
$db_name = 'pawan_restaurant';
$db_user = 'root';     // XAMPP default
$db_pass = '';         // XAMPP default is empty. Use a real password on a real server.

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // show errors as exceptions
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // rows come back as arrays
            PDO::ATTR_EMULATE_PREPARES   => false,                    // real prepared statements
        ]
    );
} catch (PDOException $e) {
    // Never print $e->getMessage() to visitors: it can reveal the database name and user.
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Sorry, something went wrong. Please try again later.');
}