<?php
require_once 'config/config.php';
require_once 'classes/Client.php';
require_once 'controllers/WalletController.php';
require_once 'controllers/AuthController.php';

$page = $_GET['page'] ?? 'home';

$allowed_pages = [
    'home', 'send', 'receive', 'history', 'trans', 'setting',
    'auth/login', 'auth/register',
    'auth/google_callback'
];

$page_path = "views/{$page}.php";

if (in_array($page, $allowed_pages) && file_exists($page_path)) {
    require_once 'views/layout.php';
} else {
    http_response_code(404);
    echo "404 - Page not found!";
}
