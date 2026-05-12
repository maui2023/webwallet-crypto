<?php
require_once __DIR__ . '/../config/config.php';

$isAdmin = !empty($_SESSION['user_id']) && !empty($_SESSION['user_admin']) && (int)$_SESSION['user_admin'] === 1;
if (!$isAdmin) {
    header('Location: ' . BASE_URL . '?page=auth/login');
    exit;
}

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

$success = null;
$error = '';

function uuid_v4() {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    $hex = bin2hex($data);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
}

function secret_key() {
    return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $op = (string)($_POST['op'] ?? '');

        if ($op === 'create') {
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '') $name = null;
            $uuid = uuid_v4();
            $secret = secret_key();
            $checksum = hash('sha256', $uuid . ':' . $secret);
            $enc = api_encrypt_secret($secret);

            if ($enc === null) {
                $error = 'Failed to generate secret.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO api_clients (uuid, name, secret_enc, active) VALUES (?, ?, ?, 1)");
                $stmt->execute([$uuid, $name, $enc]);
                $success = [
                    'uuid' => $uuid,
                    'secret' => $secret,
                    'checksum' => $checksum,
                ];
            }
        }

        if ($op === 'toggle') {
            $uuid = (string)($_POST['uuid'] ?? '');
            $active = isset($_POST['active']) ? (int)$_POST['active'] : null;
            if ($uuid !== '' && ($active === 0 || $active === 1)) {
                $stmt = $pdo->prepare("UPDATE api_clients SET active=? WHERE uuid=?");
                $stmt->execute([$active, $uuid]);
            }
        }

        if ($op === 'rotate') {
            $uuid = (string)($_POST['uuid'] ?? '');
            if ($uuid !== '') {
                $secret = secret_key();
                $checksum = hash('sha256', $uuid . ':' . $secret);
                $enc = api_encrypt_secret($secret);
                if ($enc === null) {
                    $error = 'Failed to rotate secret.';
                } else {
                    $stmt = $pdo->prepare("UPDATE api_clients SET secret_enc=? WHERE uuid=?");
                    $stmt->execute([$enc, $uuid]);
                    $success = [
                        'uuid' => $uuid,
                        'secret' => $secret,
                        'checksum' => $checksum,
                    ];
                }
            }
        }
    }
}

$stmt = $pdo->query("SELECT uuid, name, active, created_at, last_used_at FROM api_clients ORDER BY created_at DESC");
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

$apiBase = rtrim(BASE_URL, '/') . '/api/';
$exampleUrl = $apiBase . '?action=balance&label=Semasa_id_4';
?>

<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
  <h4 class="mb-0">🔑 API Keys</h4>
  <div class="text-muted small">Secure API access</div>
</div>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
  <div class="alert alert-success">
    <div class="fw-semibold mb-2">New credentials (save now, secret will not be shown again)</div>
    <div class="row g-2">
      <div class="col-lg-4">
        <div class="small text-muted">UUID</div>
        <div class="font-monospace" id="apiUuid"><?= htmlspecialchars($success['uuid']) ?></div>
      </div>
      <div class="col-lg-4">
        <div class="small text-muted">Secret Key</div>
        <div class="font-monospace" id="apiSecret"><?= htmlspecialchars($success['secret']) ?></div>
      </div>
      <div class="col-lg-4">
        <div class="small text-muted">Checksum (sha256(uuid:secret))</div>
        <div class="font-monospace" id="apiChecksum"><?= htmlspecialchars($success['checksum']) ?></div>
      </div>
    </div>
    <div class="mt-3 d-flex flex-wrap gap-2">
      <button type="button" class="btn btn-sm btn-outline-success" id="copyAllBtn">Copy All</button>
      <button type="button" class="btn btn-sm btn-outline-primary" id="copyUrlBtn">Copy Example URL</button>
    </div>
  </div>
  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const uuid = document.getElementById('apiUuid')?.innerText || '';
    const secret = document.getElementById('apiSecret')?.innerText || '';
    const checksum = document.getElementById('apiChecksum')?.innerText || '';
    document.getElementById('copyAllBtn')?.addEventListener('click', async () => {
      await navigator.clipboard.writeText(`UUID=${uuid}\nSECRET=${secret}\nCHECKSUM=${checksum}`);
    });
    document.getElementById('copyUrlBtn')?.addEventListener('click', async () => {
      await navigator.clipboard.writeText(<?= json_encode($exampleUrl, JSON_UNESCAPED_SLASHES) ?>);
    });
  });
  </script>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header">Create API Key</div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="op" value="create">
          <label class="form-label">Name (optional)</label>
          <input type="text" name="name" class="form-control mb-3" placeholder="EMS / Partner / App">
          <button class="btn btn-primary w-100" type="submit">Generate</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card">
      <div class="card-header">Existing Keys</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-striped table-bordered align-middle mb-0">
            <thead class="table-dark">
              <tr>
                <th>UUID</th>
                <th>Name</th>
                <th>Status</th>
                <th>Created</th>
                <th>Last Used</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($clients)): ?>
                <tr><td colspan="6" class="text-center text-muted">No API keys yet</td></tr>
              <?php endif; ?>
              <?php foreach ($clients as $c): ?>
                <tr>
                  <td class="font-monospace"><?= htmlspecialchars($c['uuid']) ?></td>
                  <td><?= htmlspecialchars($c['name'] ?? '-') ?></td>
                  <td>
                    <?php if ((int)$c['active'] === 1): ?>
                      <span class="badge text-bg-success">Active</span>
                    <?php else: ?>
                      <span class="badge text-bg-secondary">Disabled</span>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars((string)$c['created_at']) ?></td>
                  <td><?= htmlspecialchars((string)($c['last_used_at'] ?? '-')) ?></td>
                  <td>
                    <div class="d-flex flex-wrap gap-1">
                      <form method="POST" action="?page=api" class="d-inline">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="op" value="rotate">
                        <input type="hidden" name="uuid" value="<?= htmlspecialchars($c['uuid'], ENT_QUOTES) ?>">
                        <button class="btn btn-sm btn-warning" type="submit" onclick="return confirm('Rotate secret for this UUID?')">Rotate</button>
                      </form>
                      <?php if ((int)$c['active'] === 1): ?>
                        <form method="POST" action="?page=api" class="d-inline">
                          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                          <input type="hidden" name="op" value="toggle">
                          <input type="hidden" name="uuid" value="<?= htmlspecialchars($c['uuid'], ENT_QUOTES) ?>">
                          <input type="hidden" name="active" value="0">
                          <button class="btn btn-sm btn-outline-secondary" type="submit">Disable</button>
                        </form>
                      <?php else: ?>
                        <form method="POST" action="?page=api" class="d-inline">
                          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                          <input type="hidden" name="op" value="toggle">
                          <input type="hidden" name="uuid" value="<?= htmlspecialchars($c['uuid'], ENT_QUOTES) ?>">
                          <input type="hidden" name="active" value="1">
                          <button class="btn btn-sm btn-outline-success" type="submit">Enable</button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card mt-3">
  <div class="card-header">How to Connect (Documentation)</div>
  <div class="card-body">
    <div class="mb-2"><strong>Base URL:</strong> <span class="font-monospace"><?= htmlspecialchars($apiBase) ?></span></div>
    <div class="mb-3"><strong>Example endpoint:</strong> <span class="font-monospace"><?= htmlspecialchars($exampleUrl) ?></span></div>

    <div class="mb-2 fw-semibold">Headers</div>
    <ul class="mb-3">
      <li><span class="font-monospace">X-API-UUID</span> = your UUID</li>
      <li><span class="font-monospace">X-API-TS</span> = unix timestamp (seconds)</li>
      <li><span class="font-monospace">X-API-NONCE</span> = random string (unique per request)</li>
      <li><span class="font-monospace">X-API-SIGN</span> = HMAC-SHA256 signature</li>
    </ul>

    <div class="mb-2 fw-semibold">Signature Format</div>
    <div class="font-monospace p-2 border rounded bg-body-tertiary mb-3">
      ts + "\n" + nonce + "\n" + METHOD + "\n" + PATH + "\n" + CANONICAL_QUERY + "\n" + SHA256(BODY)
    </div>

    <div class="mb-2 fw-semibold">Sample PHP Client</div>
    <pre class="mb-0"><code class="language-php">&lt;?php
$uuid = 'YOUR_UUID';
$secret = 'YOUR_SECRET';
$url = <?= json_encode($exampleUrl, JSON_UNESCAPED_SLASHES) ?>;

$ts = time();
$nonce = bin2hex(random_bytes(16));
$method = 'GET';
$parts = parse_url($url);
$path = $parts['path'] ?? '/';
$query = $parts['query'] ?? '';

parse_str($query, $queryArr);
ksort($queryArr);
$canonicalQuery = http_build_query($queryArr, '', '&', PHP_QUERY_RFC3986);

$body = '';
$bodyHash = hash('sha256', $body);

$canonical = $ts . &quot;\n&quot; . $nonce . &quot;\n&quot; . $method . &quot;\n&quot; . $path . &quot;\n&quot; . $canonicalQuery . &quot;\n&quot; . $bodyHash;
$sign = hash_hmac('sha256', $canonical, $secret);

$headers = [
  'X-API-UUID: ' . $uuid,
  'X-API-TS: ' . $ts,
  'X-API-NONCE: ' . $nonce,
  'X-API-SIGN: ' . $sign,
  'Accept: application/json',
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$resp = curl_exec($ch);
if ($resp === false) {
  throw new Exception(curl_error($ch));
}
curl_close($ch);

echo $resp;
</code></pre>
  </div>
</div>
