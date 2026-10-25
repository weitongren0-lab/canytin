<?php
$db_host = 'sql306.infinityfree.com';
$db_user = 'if0_43270706';
$db_pass = 'WdxCUikuWBSL1GU';
$db_name = 'if0_43270706_diancan';

function getDB() {
    global $db_host, $db_user, $db_pass, $db_name;
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) { die("数据库连接失败: " . $conn->connect_error); }
    $conn->set_charset("utf8mb4");
    return $conn;
}

function initUserTable() {
    $conn = getDB();
    $conn->query("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        phone VARCHAR(20) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $conn->close();
}

function isLoggedIn() {
    return isset($_COOKIE['user_phone']) && !empty($_COOKIE['user_phone']);
}

function getCurrentPhone() {
    return $_COOKIE['user_phone'] ?? '';
}

function requireLogin() {
    if (!isLoggedIn()) {
        $table = $_GET['table'] ?? '';
        $redirect = 'xiaochengxu.php' . ($table ? '?table=' . urlencode($table) : '');
        header('Location: login2.php?redirect=' . urlencode($redirect));
        exit;
    }
}
?>