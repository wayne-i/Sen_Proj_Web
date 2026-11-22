<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$db   = 'aling_mameng_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$db};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed. Check config.php and make sure MySQL is running.');
}

// Escape HTML
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Currency format
function money($amount): string {
    return '₱' . number_format((float)$amount, 2);
}

// Check login
function logged_in(): bool {
    return isset($_SESSION['user_id']);
}

// Check administrator
function is_admin(): bool {
    return logged_in() && ($_SESSION['role'] ?? '') === 'admin';
}

// Check employee
function is_employee(): bool {
    return logged_in() && ($_SESSION['role'] ?? '') === 'employee';
}

// Check admin or employee
function is_staff(): bool {
    return is_admin() || is_employee();
}

// Require login
function require_login(): void {
    if (!logged_in()) {
        header('Location: ../login.php');
        exit;
    }
}

// Require administrator only
function require_admin(): void {
    if (!is_admin()) {
        header('Location: ../login.php');
        exit;
    }
}

// Allow administrator and employee
function require_staff(): void {
    if (!is_staff()) {
        header('Location: ../login.php');
        exit;
    }
}
