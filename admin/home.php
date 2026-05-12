<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/AdminClient.php';

$isAdmin = !empty($_SESSION['user_id']) && !empty($_SESSION['user_admin']) && (int)$_SESSION['user_admin'] === 1;
if (!$isAdmin) {
    header('Location: ' . BASE_URL . '?page=auth/login');
    exit;
}

$rpc = new AdminClient(RPC_HOST, RPC_PORT, RPC_USER, RPC_PASS);
$info = $rpc->getInfo();

$totalSupply = 720000;
$moneysupply = $info['moneysupply'] ?? 0;
$thisWallet = $info['balance'] ?? 0;
$outside = $moneysupply - $thisWallet;
$unmined = $totalSupply - $moneysupply;

// Peratus
$p_wallet_m = $moneysupply > 0 ? ($thisWallet / $moneysupply) * 100 : 0;
$p_wallet_t = ($thisWallet / $totalSupply) * 100;
$p_outside_m = $moneysupply > 0 ? ($outside / $moneysupply) * 100 : 0;
$p_outside_t = ($outside / $totalSupply) * 100;
?>

<div class="container">
    <h4 class="mb-4">📊 AyuCoin Node Status</h4>

    <!-- Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title">Money Supply / Total Supply</h6>
                    <p class="card-text fs-5">
                        <?= number_format($moneysupply, 4) ?> / <?= number_format($totalSupply) ?> AYU
                        <br><small><?= number_format(($moneysupply / $totalSupply) * 100, 2) ?>%</small>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Wallet Balance (Admin)</h6>
                    <p class="card-text fs-5">
                        <?= number_format($thisWallet, 4) ?> AYU
                        <br><small><?= number_format($p_wallet_m, 2) ?>% / MS | <?= number_format($p_wallet_t, 2) ?>% / TS</small>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h6 class="card-title">Outside Wallet (Moneysupply - Wallet)</h6>
                    <p class="card-text fs-5">
                        <?= number_format($outside, 4) ?> AYU
                        <br><small><?= number_format($p_outside_m, 2) ?>% / MS | <?= number_format($p_outside_t, 2) ?>% / TS</small>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 align-items-stretch mb-4">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header">Node Info</div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-6"><strong>Version:</strong> <?= htmlspecialchars((string)($info['version'] ?? '-')) ?></div>
                        <div class="col-md-6"><strong>Block Height:</strong> <?= htmlspecialchars((string)($info['blocks'] ?? '-')) ?></div>
                        <div class="col-md-6"><strong>Balance:</strong> <?= number_format((float)($info['balance'] ?? 0), 4) ?> AYU</div>
                        <div class="col-md-6"><strong>Stake:</strong> <?= number_format((float)($info['stake'] ?? 0), 4) ?> AYU</div>
                        <div class="col-md-6"><strong>Connections:</strong> <?= htmlspecialchars((string)($info['connections'] ?? '-')) ?></div>
                        <div class="col-md-6"><strong>Proof-of-Stake:</strong> <?= htmlspecialchars((string)($info['difficulty']['proof-of-stake'] ?? '-')) ?></div>
                        <div class="col-12"><strong>Errors:</strong> <span class="font-monospace"><?= htmlspecialchars((string)($info['errors'] ?: 'None')) ?></span></div>
                    </div>

                    <form method="POST" class="mt-3">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <button name="repairwallet" class="btn btn-danger">🔧 Repair Wallet</button>
                    </form>

                    <?php
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['repairwallet'])) {
                        if (!csrf_verify($_POST['_csrf'] ?? '')) {
                            echo "<div class='alert alert-danger mt-2'>❌ Invalid request</div>";
                        } else {
                        try {
                            $repair = $rpc->repairWallet();
                            echo "<div class='alert alert-success mt-2'>✅ Wallet repaired: " . json_encode($repair) . "</div>";
                        } catch (Exception $e) {
                            echo "<div class='alert alert-danger mt-2'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
                        }
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header">Supply Distribution</div>
                <div class="card-body">
                    <div style="height: 240px;">
                        <canvas id="supplyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ChartJS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('supplyChart').getContext('2d');
const supplyChart = new Chart(ctx, {
    type: 'pie',
    data: {
        labels: ['Wallet (Admin)', 'Outside Wallet', 'Unmined'],
        datasets: [{
            data: [
                <?= $thisWallet ?>,
                <?= $outside ?>,
                <?= $unmined ?>
            ],
            backgroundColor: ['#28a745', '#ffc107', '#dc3545']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.label || '';
                        let val = context.raw || 0;
                        let percent = (val / <?= $totalSupply ?>) * 100;
                        return `${label}: ${val.toFixed(4)} AYU (${percent.toFixed(2)}%)`;
                    }
                }
            }
        }
    }
});
</script>
