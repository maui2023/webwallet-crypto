<?php
require_once __DIR__ . '/../config/config.php';

$isAdmin = !empty($_SESSION['user_id']) && !empty($_SESSION['user_admin']) && (int)$_SESSION['user_admin'] === 1;
if (!$isAdmin) {
    header('Location: ' . BASE_URL . '?page=auth/login');
    exit;
}

header('Location: ' . BASE_URL . 'admin/?page=users');
exit;

$pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$search = $_GET['search'] ?? '';
$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int) $_GET['limit'] : 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$where = '';
$params = [];

if (!empty($search)) {
    $where = "WHERE u.username LIKE :search OR up.email LIKE :search";
    $params[':search'] = "%$search%";
}

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u LEFT JOIN user_profiles up ON u.id = up.user_id $where");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();

// Get users
$sql = "SELECT u.id, u.username, up.email FROM users u 
        LEFT JOIN user_profiles up ON u.id = up.user_id 
        $where ORDER BY u.id DESC LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
$client = new Client(RPC_HOST, RPC_PORT, RPC_USER, RPC_PASS);
?>

<div class="container">
    <h4 class="my-3">👥 Manage Users</h4>

    <form class="d-flex gap-2 mb-3" method="GET">
        <input type="text" name="search" class="form-control w-50" placeholder="Search username/email" value="<?= htmlspecialchars($search) ?>">
        <select name="limit" class="form-select w-auto">
            <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
            <option value="20" <?= $limit == 20 ? 'selected' : '' ?>>20</option>
            <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
        </select>
        <button class="btn btn-primary">Search</button>
    </form>

    <table class="table table-bordered table-hover bg-white">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Wallet Balance</th>
                <th>Reset Password</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <?php
                    $balance = 0.0;
                    try {
                        $balance = $client->getBalance($user['username']);;
                    } catch (Exception $e) {
                        $balance = 0.0;
                    }
                ?>
                <tr>
                    <td><?= $user['id'] ?></td>
                    <td><?= htmlspecialchars($user['username']) ?></td>
                    <td><?= htmlspecialchars($user['email'] ?? '-') ?></td>
                    <td><?= number_format($balance, 4) ?> AYU</td>
                    <td>
                        <form method="POST" action="reset_password.php" onsubmit="return confirm('Reset password for <?= $user['username'] ?>?')">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <button class="btn btn-sm btn-warning">Reset</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>

    <?php if ($total > $limit): ?>
        <nav>
            <ul class="pagination">
                <?php for ($i = 1; $i <= ceil($total / $limit); $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                    </li>
                <?php endfor ?>
            </ul>
        </nav>
    <?php endif ?>
</div>
