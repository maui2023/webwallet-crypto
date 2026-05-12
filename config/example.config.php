<?php
$__isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $__isHttps ? '1' : '0');
    ini_set('session.cookie_samesite', 'Lax');
    session_name('AYU_SESSION');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/AYU/',
        'secure' => $__isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$localConfigPath = __DIR__ . '/config.local.php';
if (is_file($localConfigPath)) {
    require_once $localConfigPath;
}

if (!defined('DB_HOST')) define('DB_HOST', (string)(getenv('AYU_DB_HOST') ?: 'localhost'));
if (!defined('DB_USER')) define('DB_USER', (string)(getenv('AYU_DB_USER') ?: 'change_me'));
if (!defined('DB_PASS')) define('DB_PASS', (string)(getenv('AYU_DB_PASS') ?: 'change_me'));
if (!defined('DB_NAME')) define('DB_NAME', (string)(getenv('AYU_DB_NAME') ?: 'change_me'));

if (!defined('BASE_URL')) define('BASE_URL', (string)(getenv('AYU_BASE_URL') ?: 'http://localhost/AYU/'));

if (!defined('SSO_SECRET')) define('SSO_SECRET', (string) (getenv('AYU_WALLET_SSO_SECRET') ?: ''));

if (!defined('GOOGLE_CLIENT_ID')) define('GOOGLE_CLIENT_ID', (string)(getenv('AYU_GOOGLE_CLIENT_ID') ?: 'change_me'));
if (!defined('GOOGLE_CLIENT_SECRET')) define('GOOGLE_CLIENT_SECRET', (string)(getenv('AYU_GOOGLE_CLIENT_SECRET') ?: 'change_me'));
if (!defined('GOOGLE_REDIRECT_URI')) define('GOOGLE_REDIRECT_URI', BASE_URL . 'auth/google_callback.php');

if (!defined('RPC_USER')) define('RPC_USER', (string)(getenv('AYU_RPC_USER') ?: 'change_me'));
if (!defined('RPC_PASS')) define('RPC_PASS', (string)(getenv('AYU_RPC_PASS') ?: 'change_me'));
if (!defined('RPC_HOST')) define('RPC_HOST', (string)(getenv('AYU_RPC_HOST') ?: '127.0.0.1'));
if (!defined('RPC_PORT')) define('RPC_PORT', (string)(getenv('AYU_RPC_PORT') ?: '32720'));

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

function csrf_token() {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_verify($token) {
    return is_string($token) && isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
}

function api_crypto_key() {
    return hash('sha256', DB_PASS . '|' . DB_NAME . '|' . BASE_URL, true);
}

function api_encrypt_secret($plaintext) {
    $key = api_crypto_key();
    $ivLen = openssl_cipher_iv_length('aes-256-cbc');
    $iv = random_bytes($ivLen);
    $cipher = openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) return null;
    return base64_encode($iv . $cipher);
}

function api_decrypt_secret($ciphertextB64) {
    $raw = base64_decode($ciphertextB64, true);
    if ($raw === false) return null;
    $key = api_crypto_key();
    $ivLen = openssl_cipher_iv_length('aes-256-cbc');
    if (strlen($raw) <= $ivLen) return null;
    $iv = substr($raw, 0, $ivLen);
    $cipher = substr($raw, $ivLen);
    $plain = openssl_decrypt($cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    return $plain === false ? null : $plain;
}
