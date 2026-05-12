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
    'Stake' => 'Stake',
    'Outside' => 'Outside'
];

$activeTab = $_GET['label'] ?? 'Semasa';
$labelKey = "{$activeTab}_id_{$userId}";

try {
    $txList = $rpc->getTransactionList($labelKey, 20); // Ambil 20 transaksi terkini
} catch (Exception $e) {
    $txList = [];
    $error = $e->getMessage();
}
?>

<div class="card p-4 mx-auto mb-4" style="max-width: 700px;">
    <h5 class="text-center mb-3">Transaction History</h5>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3" role="tablist">
        <?php foreach ($labels as $key => $text): ?>
            <li class="nav-item">
                <a class="nav-link <?= $key === $activeTab ? 'active' : '' ?>" 
                   href="?page=history&label=<?= $key ?>">
                    <?= $text ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if (!empty($txList)): ?>
        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Type</th>
                        <th>Address</th>
                        <th>Amount (AYU)</th>
                        <th>Date</th>
                        <th>TXID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($txList as $tx): ?>
                        <tr class="<?= $tx['category'] === 'receive' ? 'table-success' : 'table-danger' ?>">
                            <td><?= ucfirst($tx['category']) ?></td>
                            <td><?= htmlspecialchars($tx['address'] ?? '-') ?></td>
                            <td><?= number_format($tx['amount'], 4) ?></td>
                            <td><?= date('Y-m-d H:i', $tx['time']) ?></td>
                            <td style="word-break: break-all; font-size: 0.75rem;"><?= htmlspecialchars($tx['txid']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info text-center">
            <?= isset($error) ? '⚠️ ' . $error : 'No transactions found for this wallet.' ?>
        </div>
    <?php endif; ?>
</div>
