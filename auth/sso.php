<?php

require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../models/User.php';
require_once __DIR__.'/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$token = isset($_GET['token']) ? (string) $_GET['token'] : '';

if ($token === '' || SSO_SECRET === '') {
    header('Location: '.BASE_URL.'?page=auth/login');
    exit;
}

try {
    $payload = (array) JWT::decode($token, new Key(SSO_SECRET, 'HS256'));
} catch (Throwable) {
    header('Location: '.BASE_URL.'?page=auth/login');
    exit;
}

$now = time();
$exp = isset($payload['exp']) ? (int) $payload['exp'] : 0;
if ($exp <= 0 || $exp < $now) {
    header('Location: '.BASE_URL.'?page=auth/login');
    exit;
}

$email = isset($payload['email']) ? trim((string) $payload['email']) : '';
$name = isset($payload['name']) ? trim((string) $payload['name']) : '';

if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: '.BASE_URL.'?page=auth/login');
    exit;
}

try {
    $userModel = new User($pdo);
    $userModel->findOrCreateGoogleUser([
        'email' => $email,
        'name' => $name !== '' ? $name : $email,
        'picture' => '',
    ]);
} catch (Throwable) {
    header('Location: '.BASE_URL.'?page=auth/login');
    exit;
}

header('Location: '.BASE_URL.'?page=home');
exit;
