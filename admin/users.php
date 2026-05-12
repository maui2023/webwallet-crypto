<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Client.php';

$isAdmin = !empty($_SESSION['user_id']) && !empty($_SESSION['user_admin']) && (int)$_SESSION['user_admin'] === 1;
if (!$isAdmin) {
    header('Location: ' . BASE_URL . '?page=auth/login');
    exit;
}

$rpc = new Client(RPC_HOST, RPC_PORT, RPC_USER, RPC_PASS);

$successData = null;
$error = '';

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$p = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($p < 1) $p = 1;
$limit = 10;
$offset = ($p - 1) * $limit;

$currentActionParams = ['page' => 'users'];
if ($q !== '') $currentActionParams['q'] = $q;
$currentActionParams['p'] = $p;
$currentAction = '?' . http_build_query($currentActionParams);

// Reset password dan hasilkan WhatsApp link
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_id'])) {
    if (!csrf_verify($_POST['_csrf'] ?? '')) {
        $error = "Invalid request.";
    } else {
        $uid = (int)($_POST['reset_id'] ?? 0);
        if ($uid <= 0) {
            $error = "Invalid user.";
        } else {

            $stmt = $pdo->prepare("SELECT username, phone FROM users u LEFT JOIN user_profiles p ON u.id = p.user_id WHERE u.id = ?");
            $stmt->execute([$uid]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $newPass = substr(str_shuffle("abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789"), 0, 12);
                $hash = password_hash($newPass, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $uid]);

                $phone = '';
                if (!empty($user['phone'])) {
                    $phone = preg_replace('/[^0-9]/', '', $user['phone']);
                }

                $successData = [
                    'username' => $user['username'],
                    'password' => $newPass,
                    'phone' => $phone,
                ];
            } else {
                $error = "User not found.";
            }
        }
    }
}

$where = '';
$params = [];
if ($q !== '') {
    $where = "WHERE (u.username LIKE :q OR p.email LIKE :q OR p.phone LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users u LEFT JOIN user_profiles p ON u.id = p.user_id $where");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$totalPages = max(1, (int)ceil($total / $limit));
if ($p > $totalPages) $p = $totalPages;
$offset = ($p - 1) * $limit;

$stmt = $pdo->prepare("SELECT u.id, u.username, p.email, p.phone FROM users u LEFT JOIN user_profiles p ON u.id = p.user_id $where ORDER BY u.id DESC LIMIT :limit OFFSET :offset");
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($users as &$u) {
    $uid = $u['id'];
    $balanceTotal = 0;
    foreach (['Semasa', 'Simpanan', 'Stake', 'Outside'] as $label) {
        try {
            $balanceTotal += $rpc->getBalance("{$label}_id_$uid");
        } catch (Exception $e) {
            $balanceTotal += 0;
        }
    }
    try {
        $balanceTotal += $rpc->getBalance((string)$u['username']);
    } catch (Exception $e) {}
    $u['balance'] = $balanceTotal;
}
unset($u);

$start = $total === 0 ? 0 : ($offset + 1);
$end = min($offset + count($users), $total);
?>

<div class="container">
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <h4 class="mb-0">👥 Users</h4>
        <div class="small text-muted">Showing <?= number_format($start) ?>-<?= number_format($end) ?> of <?= number_format($total) ?></div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($successData): ?>
        <div class="alert alert-success">
            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
                <div>
                    ✅ Password reset for <strong><?= htmlspecialchars($successData['username']) ?></strong>
                    <div class="small text-muted">New password: <span id="newPassword" class="font-monospace"><?= htmlspecialchars($successData['password']) ?></span></div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-outline-success" id="copyPassBtn">Copy Password</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="copyMsgBtn" <?= empty($successData['phone']) ? 'disabled' : '' ?>>Copy WhatsApp Message</button>
                    <button type="button" class="btn btn-sm btn-success" id="openWaBtn" <?= empty($successData['phone']) ? 'disabled' : '' ?>>Open WhatsApp</button>
                </div>
            </div>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', () => {
            const success = <?= json_encode($successData, JSON_UNESCAPED_SLASHES) ?>;
            const msg = `AyuCoin\nUsername: ${success.username}\nPassword: ${success.password}`;
            document.getElementById('copyPassBtn')?.addEventListener('click', async () => {
                await navigator.clipboard.writeText(success.password);
            });
            document.getElementById('copyMsgBtn')?.addEventListener('click', async () => {
                await navigator.clipboard.writeText(msg);
            });
            document.getElementById('openWaBtn')?.addEventListener('click', () => {
                const phone = success.phone || '';
                if (!phone) return;
                const url = `https://wa.me/${phone}?text=${encodeURIComponent(msg)}`;
                window.open(url, '_blank', 'noopener,noreferrer');
            });
        });
        </script>
    <?php endif; ?>

    <form class="row g-2 align-items-end mb-3" method="GET" action="">
        <input type="hidden" name="page" value="users">
        <div class="col-md-6">
            <label class="form-label">Search</label>
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control" placeholder="username / email / phone">
        </div>
        <div class="col-md-3">
            <button class="btn btn-primary w-100" type="submit">Search</button>
        </div>
        <div class="col-md-3">
            <a class="btn btn-outline-secondary w-100" href="?page=users">Clear</a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Total Balance</th>
                    <th>Reset Password</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td><?= htmlspecialchars($u['username']) ?></td>
                        <td><?= htmlspecialchars($u['email'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($u['phone'] ?? '-') ?></td>
                        <td><?= number_format($u['balance'], 4) ?></td>
                        <td>
                            <form method="POST" class="d-inline" action="<?= htmlspecialchars($currentAction) ?>">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="reset_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Reset password for <?= htmlspecialchars($u['username'], ENT_QUOTES) ?>?')">Reset</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <?php
        $queryBase = ['page' => 'users'];
        if ($q !== '') $queryBase['q'] = $q;

        $window = 2;
        $from = max(1, $p - $window);
        $to = min($totalPages, $p + $window);
        ?>
        <nav aria-label="Users pagination">
            <ul class="pagination">
                <?php
                $prevParams = $queryBase;
                $prevParams['p'] = max(1, $p - 1);
                $nextParams = $queryBase;
                $nextParams['p'] = min($totalPages, $p + 1);
                ?>
                <li class="page-item <?= $p <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= htmlspecialchars(http_build_query($prevParams)) ?>">Prev</a>
                </li>

                <?php if ($from > 1): ?>
                    <?php $firstParams = $queryBase; $firstParams['p'] = 1; ?>
                    <li class="page-item"><a class="page-link" href="?<?= htmlspecialchars(http_build_query($firstParams)) ?>">1</a></li>
                    <?php if ($from > 2): ?>
                        <li class="page-item disabled"><span class="page-link">…</span></li>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $from; $i <= $to; $i++): ?>
                    <?php $pageParams = $queryBase; $pageParams['p'] = $i; ?>
                    <li class="page-item <?= $i === $p ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= htmlspecialchars(http_build_query($pageParams)) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($to < $totalPages): ?>
                    <?php if ($to < $totalPages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">…</span></li>
                    <?php endif; ?>
                    <?php $lastParams = $queryBase; $lastParams['p'] = $totalPages; ?>
                    <li class="page-item"><a class="page-link" href="?<?= htmlspecialchars(http_build_query($lastParams)) ?>"><?= $totalPages ?></a></li>
                <?php endif; ?>

                <li class="page-item <?= $p >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= htmlspecialchars(http_build_query($nextParams)) ?>">Next</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>
