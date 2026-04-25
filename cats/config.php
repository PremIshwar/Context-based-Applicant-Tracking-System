<?php
// CATS System Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cats_db');

define('BASE_URL', 'http://localhost/cats/');
define('UPLOAD_DIR', __DIR__ . '/uploads/');

function db_connect() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}
session_start();
function is_logged_in($type = null) {
    if (!isset($_SESSION['user_id'])) return false;
    if ($type && $_SESSION['user_type'] !== $type) return false;
    return true;
}

function require_login($type = null) {
    if (!is_logged_in($type)) {
        $redirect = $type === 'employer' ? 'employer/login.php' : 'applicant/login.php';
        header('Location: ' . BASE_URL . $redirect);
        exit;
    }
}
?>
