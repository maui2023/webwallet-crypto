<?php
if (!isset($_SESSION['user_session'])) {
    header('Location: ' . BASE_URL . '?page=auth/login');
    exit;
}

require_once __DIR__ . '/../classes/Client.php';
require_once __DIR__ . '/../controllers/WalletController.php';

$rpc = new Client(RPC_HOST, RPC_PORT, RPC_USER, RPC_PASS);
$wallet = new WalletController($rpc);

$userId = $_SESSION['user_id'];
$labels = [
    'Semasa' => 'Current',
    'Simpanan' => 'Saving',
    'Stake'   => 'Stake',
    'Outside' => 'Outside'
];
$activeTab = $_GET['label'] ?? 'Semasa';
$currentLabel = "{$activeTab}_id_{$userId}";

// Ambil senarai transaksi
try {
    $transactions = $rpc->getTransactionList($currentLabel, 25);
} catch (Exception $e) {
    $transactions = [];
    $error = $e->getMessage();
}
?>

<div class="card p-4 mx-auto mb-4" style="max-width: 900px;">
    <h5 class="text-center mb-3">Transaction History</h5>

    <!-- Tab menu -->
    <ul class="nav nav-tabs mb-3">
        <?php foreach ($labels as $key => $text): ?>
            <li class="nav-item">
                <a class="nav-link <?= $key === $activeTab ? 'active' : '' ?>" 
                   href="?page=history&label=<?= $key ?>">
                    <?= $text ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($transactions)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead>
                    <tr class="table-light text-center">
                        <th>Time</th>
                        <th>TXID</th>
                        <th>Category</th>
                        <th class="text-end">Amount (AYU)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $tx): ?>
                        <tr class="text-center">
                            <td><?= date('Y-m-d H:i', $tx['time']) ?></td>
                            <td style="font-size: 0.85em; word-break: break-all;">
                                <?= htmlspecialchars($tx['txid']) ?>
                            </td>
                            <td>
                                <?= ucfirst($tx['category']) ?>
                            </td>
                            <td class="text-end <?= $tx['amount'] < 0 ? 'text-danger' : 'text-success' ?>">
                                <?= number_format($tx['amount'], 4) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="text-center text-muted">No transactions found.</div>
    <?php endif; ?>
</div>
