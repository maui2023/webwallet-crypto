<?php
require_once __DIR__ . '/../config/config.php';
$isAdmin = !empty($_SESSION['user_id']) && !empty($_SESSION['user_admin']) && (int)$_SESSION['user_admin'] === 1;
if (!$isAdmin) {
    header('Location: ' . BASE_URL . '?page=auth/login');
    exit;
}

header('Location: ' . BASE_URL . 'admin/?page=tx');
exit;
