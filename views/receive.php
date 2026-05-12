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
$addressList = $wallet->getAddresses();

$activeTab = $_GET['label'] ?? 'Semasa';
$amount = isset($_GET['amount']) ? floatval($_GET['amount']) : 0;
$currentLabel = "{$activeTab}_id_{$userId}";
$currentAddress = $addressList[$currentLabel] ?? null;

$outsideAddresses = [];
if ($activeTab === 'Outside') {
    try {
        $outsideAddresses = $rpc->getAddressesByAccount("Outside_id_$userId");
    } catch (Exception $e) {
        $outsideAddresses = [];
    }
    try {
        $usernameAcc = $_SESSION['user_session'] ?? '';
        if ($usernameAcc !== '') {
            $outsideAddresses = array_merge($outsideAddresses, $rpc->getAddressesByAccount($usernameAcc));
        }
    } catch (Exception $e) {}
    $outsideAddresses = array_values(array_unique(array_filter($outsideAddresses, function ($v) { return is_string($v) && $v !== ''; })));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_address'])) {
    if (!csrf_verify($_POST['_csrf'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        try {
            $wallet->getNewAddress($currentLabel);
            header('Location: ' . BASE_URL . '?page=receive&label=' . urlencode($activeTab) . '&amount=' . urlencode((string)$amount));
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<div class="card p-4 mx-auto mb-4" style="max-width: 500px;">
    <h5 class="text-center mb-3">Receive AyuCoin</h5>

    <!-- Tab menu -->
    <ul class="nav nav-tabs mb-3" role="tablist">
        <?php foreach ($labels as $key => $text): ?>
            <li class="nav-item">
                <a class="nav-link <?= $key === $activeTab ? 'active' : '' ?>" 
                   href="?page=receive&label=<?= $key ?>&amount=<?= $amount ?>">
                    <?= $text ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger text-center"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($currentAddress): ?>
        <!-- Amount input -->
        <div class="mb-3">
            <label for="amountInput" class="form-label">Enter Amount (optional)</label>
            <input type="number" step="0.0001" class="form-control" id="amountInput" 
                   value="<?= htmlspecialchars($amount) ?>" placeholder="e.g. 1.25">
        </div>

        <!-- Wallet Info -->
        <div class="bg-light p-3 text-center rounded border">
            <p><strong>Wallet Address</strong></p>
            <code id="wallet-address"><?= htmlspecialchars($currentAddress) ?></code>
            <button class="btn btn-sm btn-outline-secondary ms-2" onclick="copyAddress()">Copy</button>

            <!-- QR Area -->
            <div class="mt-4">
                <img id="qrImage" src="https://api.qrserver.com/v1/create-qr-code/?data=<?= urlencode($currentAddress . ($amount > 0 ? "?amount=$amount" : '')) ?>&size=180x180" 
                     alt="QR Code" class="img-fluid">
                <p class="mt-2" id="amountText">
                    <?= $amount > 0 ? "<strong>Amount:</strong> " . number_format($amount, 4) . " AYU" : '' ?>
                </p>
            </div>

            <?php if ($activeTab === 'Outside' && !empty($outsideAddresses)): ?>
                <div class="mt-3 text-start">
                    <div class="fw-semibold mb-2">Outside Addresses</div>
                    <div class="list-group">
                        <?php foreach ($outsideAddresses as $addr): ?>
                            <div class="list-group-item d-flex align-items-center justify-content-between gap-2">
                                <span class="font-monospace small" style="word-break: break-all;"><?= htmlspecialchars($addr) ?></span>
                                <button class="btn btn-sm btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($addr, ENT_QUOTES) ?>')">Copy</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-warning text-center">No wallet address found for label <?= $activeTab ?>.</div>
        <form method="POST" class="text-center">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <button class="btn btn-success" name="generate_address" type="submit">Generate Address</button>
        </form>
    <?php endif; ?>
</div>

<script>
function copyAddress() {
    const text = document.getElementById("wallet-address").innerText;
    navigator.clipboard.writeText(text).then(() => {
        alert("Wallet address copied to clipboard!");
    });
}

// Update QR automatically
const amountInput = document.getElementById('amountInput');
if (amountInput) {
    amountInput.addEventListener('input', function () {
        const amount = parseFloat(this.value) || '';
        const baseUrl = "<?= $currentAddress ?>";
        const newUrl = amount ? `${baseUrl}?amount=${amount}` : baseUrl;
        document.getElementById('qrImage').src = `https://api.qrserver.com/v1/create-qr-code/?data=${encodeURIComponent(newUrl)}&size=180x180`;
        document.getElementById('amountText').innerHTML = amount ? `<strong>Amount:</strong> ${parseFloat(amount).toFixed(4)} AYU` : '';
    });
}
</script>
