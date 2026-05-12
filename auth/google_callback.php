<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../controllers/AuthController.php';

$client = new Google_Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);

if (isset($_GET['code'])) {
    $client->fetchAccessTokenWithAuthCode($_GET['code']);
    $google_service = new Google_Service_Oauth2($client);
    $userInfo = $google_service->userinfo->get();

    $googleUser = [
        'id' => $userInfo->id,
        'email' => $userInfo->email,
        'name' => $userInfo->name,
        'picture' => $userInfo->picture
    ];

    $auth = new AuthController($pdo);
    $auth->googleLogin($googleUser);

    header('Location: ' . BASE_URL);
    exit;
} else {
    header('Location: ' . BASE_URL . '?page=auth/login');
    exit;
}
