<?php
// Simple JSON API for AyuCoin Wallet integration with EMS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-UUID, X-API-TS, X-API-NONCE, X-API-SIGN, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { echo json_encode(['ok' => true]); exit; }

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Client.php';
require_once __DIR__ . '/../controllers/WalletController.php';
require_once __DIR__ . '/../models/User.php';

function json_ok($data){ echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_SLASHES); exit; }
function json_err($msg, $code=400){ http_response_code($code); echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_SLASHES); exit; }

function get_header_value($name) {
  $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
  if (isset($_SERVER[$key])) return trim((string)$_SERVER[$key]);
  if (function_exists('getallheaders')) {
    $headers = getallheaders();
    foreach ($headers as $k => $v) {
      if (strcasecmp($k, $name) === 0) return trim((string)$v);
    }
  }
  return '';
}

function canonical_query_string() {
  $queryString = $_SERVER['QUERY_STRING'] ?? '';
  if ($queryString === '') return '';
  parse_str($queryString, $arr);
  ksort($arr);
  return http_build_query($arr, '', '&', PHP_QUERY_RFC3986);
}

function request_path_only() {
  $uri = $_SERVER['REQUEST_URI'] ?? '';
  $path = parse_url($uri, PHP_URL_PATH);
  return $path ? $path : '/';
}

try {
  $client = new Client(RPC_HOST, RPC_PORT, RPC_USER, RPC_PASS);
  $wallet = new WalletController($client);
} catch (Exception $e) {
  json_err('RPC init error: ' . $e->getMessage(), 500);
}

// Parse input
$inputRaw = file_get_contents('php://input');
$input = [];
if ($inputRaw) { try { $parsed = json_decode($inputRaw, true); if (is_array($parsed)) { $input = $parsed; } } catch(Exception $e){} }

$pathAction = '';
if (!empty($_SERVER['PATH_INFO'])) {
  $pathAction = trim($_SERVER['PATH_INFO'], '/');
}
$action = $_GET['action'] ?? ($input['action'] ?? $pathAction);

$label = $_GET['label'] ?? ($input['label'] ?? null);
$count = isset($_GET['count']) ? intval($_GET['count']) : (isset($input['count']) ? intval($input['count']) : 10);
$fromLabel = $_GET['fromLabel'] ?? ($input['fromLabel'] ?? null);
$toAddress = $_GET['toAddress'] ?? ($input['toAddress'] ?? null);
$amount = $_GET['amount'] ?? ($input['amount'] ?? null);
$email = $_GET['email'] ?? ($input['email'] ?? null);
$name = $_GET['name'] ?? ($input['name'] ?? null);
$photo = $_GET['photo'] ?? ($input['photo'] ?? null);

try {
  $pdo->exec("
  CREATE TABLE IF NOT EXISTS api_clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(100) NULL,
    secret_enc TEXT NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
  $pdo->exec("
  CREATE TABLE IF NOT EXISTS api_nonces (
    client_uuid VARCHAR(64) NOT NULL,
    nonce VARCHAR(128) NOT NULL,
    ts INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (client_uuid, nonce)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
} catch (Exception $e) {
  json_err('API storage error', 500);
}

$uuid = get_header_value('X-API-UUID');
$ts = (int)get_header_value('X-API-TS');
$nonce = get_header_value('X-API-NONCE');
$sign = get_header_value('X-API-SIGN');

if ($uuid === '' || $ts <= 0 || $nonce === '' || $sign === '') {
  json_err('missing api auth headers', 401);
}

if (strlen($nonce) > 128) {
  json_err('invalid nonce', 401);
}

$now = time();
if (abs($now - $ts) > 300) {
  json_err('timestamp expired', 401);
}

$stmt = $pdo->prepare("SELECT uuid, secret_enc, active FROM api_clients WHERE uuid=? LIMIT 1");
$stmt->execute([$uuid]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row || (int)$row['active'] !== 1) {
  json_err('invalid api credentials', 401);
}

$secret = api_decrypt_secret((string)$row['secret_enc']);
if (!is_string($secret) || $secret === '') {
  json_err('invalid api credentials', 401);
}

try {
  $stmt = $pdo->prepare("DELETE FROM api_nonces WHERE ts < ?");
  $stmt->execute([$now - 600]);

  $stmt = $pdo->prepare("INSERT INTO api_nonces (client_uuid, nonce, ts) VALUES (?, ?, ?)");
  $stmt->execute([$uuid, $nonce, $ts]);
} catch (Exception $e) {
  json_err('replay detected', 409);
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path = request_path_only();
$canonicalQuery = canonical_query_string();
$bodyHash = hash('sha256', $inputRaw ? $inputRaw : '');
$canonical = $ts . "\n" . $nonce . "\n" . $method . "\n" . $path . "\n" . $canonicalQuery . "\n" . $bodyHash;
$expected = hash_hmac('sha256', $canonical, $secret);

if (!hash_equals($expected, $sign)) {
  json_err('invalid signature', 401);
}

try {
  $stmt = $pdo->prepare("UPDATE api_clients SET last_used_at = NOW() WHERE uuid=?");
  $stmt->execute([$uuid]);
} catch (Exception $e) {}

function resolve_label_for_api($label, $pdo) {
  if (!is_string($label) || $label === '') return $label;
  if (preg_match('/_id_\\d+$/', $label)) return $label;
  $stmt = $pdo->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
  $stmt->execute([$label]);
  $id = $stmt->fetchColumn();
  if ($id) return "Outside_id_" . (int)$id;
  return $label;
}

try {
  switch ($action) {
    case 'user_exists':
      if (!$email) json_err('email is required');
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_err('invalid email');
      $stmt = $pdo->prepare("
        SELECT u.id, u.username
        FROM users u
        JOIN user_profiles p ON u.id = p.user_id
        WHERE p.email = ?
        LIMIT 1
      ");
      $stmt->execute([(string)$email]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      json_ok([
        'exists' => (bool) $row,
        'user_id' => $row ? (int) $row['id'] : null,
        'username' => $row ? (string) $row['username'] : null,
      ]);

    case 'sync_user':
      if (!$email) json_err('email is required');
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_err('invalid email');
      $userModel = new User($pdo);
      $userModel->findOrCreateGoogleUser([
        'email' => (string)$email,
        'name' => (string)($name ?: $email),
        'picture' => (string)($photo ?: ''),
      ]);

      $stmt = $pdo->prepare("
        SELECT u.id, u.username
        FROM users u
        JOIN user_profiles p ON u.id = p.user_id
        WHERE p.email = ?
        LIMIT 1
      ");
      $stmt->execute([(string)$email]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!$row) {
        json_err('user sync failed', 500);
      }
      json_ok(['user_id' => (int)$row['id'], 'username' => (string)$row['username']]);

    case 'balance':
      if (!$label) json_err('label is required');
      $mapped = resolve_label_for_api($label, $pdo);
      $bal = $wallet->getBalanceByLabel($mapped);
      if ($mapped !== $label) {
        $bal += $client->getBalance($label);
      }
      json_ok(['label' => $mapped, 'balance' => $bal]);

    case 'transactions':
      if (!$label) json_err('label is required');
      $mapped = resolve_label_for_api($label, $pdo);
      $list = $client->getTransactionList($mapped, $count);
      json_ok(['label' => $mapped, 'count' => $count, 'transactions' => $list]);

    case 'new_address':
      if (!$label) json_err('label is required');
      $mapped = resolve_label_for_api($label, $pdo);
      $addr = $wallet->getNewAddress($mapped);
      json_ok(['label' => $mapped, 'address' => $addr]);

    case 'send':
      if (!$fromLabel || !$toAddress || !$amount) json_err('fromLabel, toAddress, amount are required');
      $amt = floatval($amount);
      if ($amt <= 0) json_err('amount must be greater than 0');
      $mappedFrom = resolve_label_for_api($fromLabel, $pdo);
      $txid = $wallet->sendFromUser($mappedFrom, $toAddress, $amt);
      json_ok(['txid' => $txid, 'from' => $mappedFrom, 'to' => $toAddress, 'amount' => $amt]);

    default:
      json_err('unknown action', 404);
  }
} catch (Exception $e) {
  json_err($e->getMessage(), 500);
}
