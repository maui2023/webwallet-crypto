<?php
require_once __DIR__ . '/../models/User.php';

class AuthController {
    private $userModel;

    public function __construct($pdo) {
        $this->userModel = new User($pdo);
    }

    /**
     * Handle standard login with username and password
     */
    public function login($username, $password) {
        return $this->userModel->login($username, $password);
    }

    /**
     * Handle login/register with Google OAuth
     */
    public function googleLogin($googleUser) {
        return $this->userModel->findOrCreateGoogleUser($googleUser);
    }
}
