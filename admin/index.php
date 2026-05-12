<?php
require_once __DIR__ . '/../config/config.php';

$isAdmin = !empty($_SESSION['user_id']) && !empty($_SESSION['user_admin']) && (int)$_SESSION['user_admin'] === 1;
if (!$isAdmin) {
    header('Location: ' . BASE_URL . '?page=auth/login');
    exit;
}

csrf_token();

$page = $_GET['page'] ?? 'home';
$allowed = ['home', 'users', 'all_wallet', 'tx', 'settings', 'api'];

if (!in_array($page, $allowed)) {
    $page = 'home';
}
$page_path = __DIR__ . "/$page.php";

require_once __DIR__ . '/layout.php';
