<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Re-engineered configuration to route exclusively to V3 isolated DB Cluster
$host = 'localhost';
$db   = 'secure_suite_v3';
$user = 'root';
$pass = ''; 
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database Connection Engine Failed [Instance V3]: " . $e->getMessage());
}

function check_session_guard() {
    if (!isset($_SESSION['v3_admin_session']) || $_SESSION['v3_admin_session'] !== 'authenticated') {
        header("Location: login.php");
        exit;
    }
}
?>