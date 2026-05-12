<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/AdminClient.php';

$isAdmin = !empty($_SESSION['user_id']) && !empty($_SESSION['user_admin']) && (int)$_SESSION['user_admin'] === 1;
if (!$isAdmin) {
    header('Location: ' . BASE_URL . '?page=auth/login');
    exit;
}

$rpc = new AdminClient(RPC_HOST, RPC_PORT, RPC_USER, RPC_PASS);

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$p = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($p < 1) $p = 1;
$limit = 50;

$error = '';
$success = '';

function is_locked_account_label($account) {
    if (!is_string($account) || $account === '') return false;
    return (bool) preg_match('/^(Semasa|Simpanan|Stake|Outside)_id_\\d+$/', $account);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['relate_outside']) || isset($_POST['assign_outside']))) {
    if (!csrf_verify($_POST['_csrf'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $address = trim((string)($_POST['address'] ?? ''));
        $userId = 0;

        if (isset($_POST['relate_outside'])) {
            $userId = (int)($_POST['user_id'] ?? 0);
        } else {
            $userRef = trim((string)($_POST['user_ref'] ?? ''));
            if ($userRef !== '' && ctype_digit($userRef)) {
                $userId = (int)$userRef;
            } elseif ($userRef !== '') {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
                $stmt->execute([$userRef]);
                $userId = (int)($stmt->fetchColumn() ?: 0);
            }
        }

        if ($address === '' || $userId <= 0) {
            $error = 'Invalid data.';
        } else {
            $label = "Outside_id_$userId";
            try {
                $currentAccount = '';
                try {
                    $currentAccount = (string)$rpc->getAccount($address);
                } catch (Exception $e) {}
                if (is_locked_account_label($currentAccount)) {
                    $error = "Wallet already assigned/locked ($currentAccount).";
                } else {
                $rpc->setAccount($address, $label);
                $success = "Updated: $address -> $label";
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$userRows = $pdo->query("SELECT id, username FROM users")->fetchAll(PDO::FETCH_ASSOC);
$knownUsersById = [];
$knownUsersByUsername = [];
foreach ($userRows as $u) {
    $id = (int)$u['id'];
    $uname = (string)$u['username'];
    $knownUsersById[$id] = $uname;
    $knownUsersByUsername[strtolower($uname)] = $id;
}

$knownLabels = [];
foreach (array_keys($knownUsersById) as $uid) {
    $knownLabels["Semasa_id_$uid"] = ['type' => 'Semasa', 'user_id' => $uid];
    $knownLabels["Simpanan_id_$uid"] = ['type' => 'Simpanan', 'user_id' => $uid];
    $knownLabels["Stake_id_$uid"] = ['type' => 'Stake', 'user_id' => $uid];
    $knownLabels["Outside_id_$uid"] = ['type' => 'Outside', 'user_id' => $uid];
}

$rows = [];
$totalBalance = 0.0;

try {
    $groupings = $rpc->listAddressGroupings();
    if (is_array($groupings)) {
        foreach ($groupings as $groupIndex => $group) {
            if (!is_array($group)) continue;
            foreach ($group as $entry) {
                if (!is_array($entry) || empty($entry[0])) continue;
                $address = (string)$entry[0];
                $balance = isset($entry[1]) ? (float)$entry[1] : 0.0;
                $account = isset($entry[2]) ? (string)$entry[2] : '';

                $related = '-';
                if ($account !== '' && isset($knownLabels[$account])) {
                    $meta = $knownLabels[$account];
                    $uname = $knownUsersById[(int)$meta['user_id']] ?? null;
                    $related = $uname ? ($meta['type'] . ' / ' . $uname . ' (#' . (int)$meta['user_id'] . ')') : ($meta['type'] . ' / user #' . (int)$meta['user_id']);
                } elseif ($account !== '') {
                    $accLower = strtolower($account);
                    if (isset($knownUsersByUsername[$accLower])) {
                        $uid = (int)$knownUsersByUsername[$accLower];
                        $uname = $knownUsersById[$uid] ?? $account;
                        $related = $uname . ' (#' . $uid . ')';
                    }
                }

                $rows[] = [
                    'address' => $address,
                    'balance' => $balance,
                    'account' => $account !== '' ? $account : '-',
                    'related' => $related,
                    'group' => (int)$groupIndex,
                ];
                $totalBalance += $balance;
            }
        }
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

usort($rows, function ($a, $b) {
    $cmp = ($b['balance'] <=> $a['balance']);
    if ($cmp !== 0) return $cmp;
    return strcmp($a['address'], $b['address']);
});

if ($q !== '') {
    $qLower = mb_strtolower($q);
    $rows = array_values(array_filter($rows, function ($r) use ($qLower) {
        $hay = mb_strtolower($r['address'] . ' ' . $r['account'] . ' ' . $r['related']);
        return mb_strpos($hay, $qLower) !== false;
    }));
}

$total = count($rows);
$totalPages = max(1, (int)ceil($total / $limit));
if ($p > $totalPages) $p = $totalPages;
$offset = ($p - 1) * $limit;
$pageRows = array_slice($rows, $offset, $limit);

$start = $total === 0 ? 0 : ($offset + 1);
$end = min($offset + count($pageRows), $total);
?>

<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
    <h4 class="mb-0">👛 All Wallet</h4>
    <div class="small text-muted">
        Showing <?= number_format($start) ?>-<?= number_format($end) ?> of <?= number_format($total) ?> | Total balance: <?= number_format($totalBalance, 6) ?>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-header">Assign Wallet to Outside</div>
    <div class="card-body">
        <form method="POST" class="row g-2 align-items-end">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="assign_outside" value="1">
            <div class="col-lg-6">
                <label class="form-label">Wallet Address</label>
                <input type="text" name="address" class="form-control" placeholder="AMy6iagfH5mvjMQTHtBewJm2jj2MGetLEM" required>
            </div>
            <div class="col-lg-4">
                <label class="form-label">User (id or username)</label>
                <input type="text" name="user_ref" class="form-control" list="userList" placeholder="4 or soirem08" required>
                <datalist id="userList">
                    <?php foreach ($knownUsersById as $uid => $uname): ?>
                        <option value="<?= htmlspecialchars((string)$uid) ?>"><?= htmlspecialchars($uname) ?></option>
                        <option value="<?= htmlspecialchars($uname) ?>"><?= htmlspecialchars((string)$uid) ?></option>
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="col-lg-2">
                <button class="btn btn-primary w-100" type="submit">Assign</button>
            </div>
        </form>
    </div>
</div>

<form class="row g-2 align-items-end mb-3" method="GET" action="">
    <input type="hidden" name="page" value="all_wallet">
    <div class="col-md-6">
        <label class="form-label">Search</label>
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control" placeholder="address / account / username">
    </div>
    <div class="col-md-3">
        <button class="btn btn-primary w-100" type="submit">Search</button>
    </div>
    <div class="col-md-3">
        <a class="btn btn-outline-secondary w-100" href="?page=all_wallet">Clear</a>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Address</th>
                        <th class="text-end">Balance</th>
                        <th>Account</th>
                        <th>Related</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pageRows)): ?>
                        <tr><td colspan="5" class="text-center text-muted">No wallet found</td></tr>
                    <?php endif; ?>
                    <?php foreach ($pageRows as $r): ?>
                        <tr>
                            <td class="font-monospace"><?= htmlspecialchars($r['address']) ?></td>
                            <td class="text-end"><?= number_format((float)$r['balance'], 6) ?></td>
                            <td class="font-monospace"><?= htmlspecialchars($r['account']) ?></td>
                            <td><?= htmlspecialchars($r['related']) ?></td>
                            <td>
                                <?php
                                $targetUserId = 0;
                                if ($r['account'] !== '-' && isset($knownUsersByUsername[strtolower($r['account'])])) {
                                    $targetUserId = (int)$knownUsersByUsername[strtolower($r['account'])];
                                }
                                $isAlreadyOutside = ($targetUserId > 0) && ($r['account'] === "Outside_id_$targetUserId");
                                $isLocked = is_locked_account_label($r['account']);
                                ?>
                                <?php if ($isLocked): ?>
                                    <span class="badge text-bg-secondary">Locked</span>
                                <?php elseif ($targetUserId > 0 && !$isAlreadyOutside): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                        <input type="hidden" name="relate_outside" value="1">
                                        <input type="hidden" name="address" value="<?= htmlspecialchars($r['address'], ENT_QUOTES) ?>">
                                        <input type="hidden" name="user_id" value="<?= $targetUserId ?>">
                                        <button class="btn btn-sm btn-outline-primary" type="submit">Move to Outside</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                        <input type="hidden" name="assign_outside" value="1">
                                        <input type="hidden" name="address" value="<?= htmlspecialchars($r['address'], ENT_QUOTES) ?>">
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control" name="user_ref" list="userList" placeholder="id/username">
                                            <button class="btn btn-outline-primary" type="submit">Assign</button>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($totalPages > 1): ?>
    <?php
    $queryBase = ['page' => 'all_wallet'];
    if ($q !== '') $queryBase['q'] = $q;

    $window = 2;
    $from = max(1, $p - $window);
    $to = min($totalPages, $p + $window);

    $prevParams = $queryBase;
    $prevParams['p'] = max(1, $p - 1);
    $nextParams = $queryBase;
    $nextParams['p'] = min($totalPages, $p + 1);
    ?>
    <nav class="mt-3" aria-label="All Wallet pagination">
        <ul class="pagination">
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
