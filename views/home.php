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
$username = $_SESSION['user_session'];
$addressList = $wallet->getAddresses();

$labels = [
    'Semasa' => 'Current',
    'Simpanan' => 'Saving',
    'Stake'   => 'Stake',
    'Outside' => 'Outside'
];

// Dapatkan address pilihan user
$selectedLabel = $_GET['label'] ?? 'Semasa';
$labelKey = "{$selectedLabel}_id_{$userId}";
$selectedAddress = $addressList[$labelKey] ?? null;
$selectedBalance = 0.00;
if ($selectedAddress) {
    if ($selectedLabel === 'Outside') {
        $selectedBalance = $rpc->getBalance($labelKey) + $rpc->getBalance($username);
    } else {
        $selectedBalance = $rpc->getBalance($labelKey);
    }
}

// Jumlah semua balance address
$totalBalance = 0.00;
foreach (['Semasa', 'Simpanan', 'Stake', 'Outside'] as $label) {
    $fullLabel = "{$label}_id_{$userId}";
    $totalBalance += $rpc->getBalance($fullLabel);
    if ($label === 'Outside') {
        $totalBalance += $rpc->getBalance($username);
    }
}

// Handle cipta address jika belum cukup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_address']) && count($addressList) < 4) {
    foreach (array_keys($labels) as $label) {
        $checkLabel = "{$label}_id_{$userId}";
        if (!isset($addressList[$checkLabel])) {
            $wallet->getNewAddress($checkLabel);
            break;
        }
    }
    header("Location: " . BASE_URL);
    exit;
}
?>

<div class="card p-4 mx-auto mb-4" style="max-width: 500px;">
    <h5 class="text-center">Total Wallet Balance</h5>
    <h2 class="text-center text-primary"><?= number_format($totalBalance - 0.01, 2) ?> AYU</h2>
</div>

<div class="card p-4 mx-auto mb-4" style="max-width: 500px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Wallet Address</h5>
        <form method="get">
            <select name="label" onchange="this.form.submit()" class="form-select form-select-sm">
                <?php foreach ($labels as $key => $labelText): ?>
                    <option value="<?= $key ?>" <?= $selectedLabel === $key ? 'selected' : '' ?>>
                        <?= $labelText ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if ($selectedAddress): ?>
        <div class="bg-light border rounded p-3 text-center">
            <strong><?= $labels[$selectedLabel] ?></strong><br>
            <code id="wallet-address" style="color: #d63384;"><?= $selectedAddress ?></code>
            <button onclick="copyAddress()" class="btn btn-sm btn-outline-secondary ms-2">Copy</button>
            <br><br>
            <!--img src="https://api.qrserver.com/v1/create-qr-code/?data=<?= urlencode($selectedAddress) ?>&size=160x160" class="my-3" alt="QR Code"-->
            <p><strong>Balance:</strong> <?= number_format($selectedBalance, 4) ?> AYU</p>
        </div>
    <?php else: ?>
        <p class="text-muted">Address not created yet for <?= $labels[$selectedLabel] ?>.</p>
    <?php endif; ?>

    <form method="POST" class="text-center mt-3">
        <button type="submit" name="create_address" class="btn btn-success w-100" <?= count($addressList) >= 4 ? 'disabled' : '' ?>>
            Create My Address
        </button>
    </form>
</div>

<div class="card p-4 mx-auto mb-4" style="max-width: 500px;">
    <a href="<?= BASE_URL ?>?page=history" class="btn btn-success w-100" style="max-width: 500px;">Transactions</a>
</div>

<script>
function copyAddress() {
    const address = document.getElementById("wallet-address").innerText;
    navigator.clipboard.writeText(address).then(() => {
        alert("Wallet address copied to clipboard!");
    });
}
</script>
