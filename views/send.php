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
$success = $error = '';

$label_map = [
    'Semasa' => 'Current',
    'Simpanan' => 'Saving',
    'Stake' => 'Stake',
    'Outside' => 'Outside'
];

// Dapatkan baki setiap wallet label
$balances = [];
foreach ($label_map as $key => $label) {
    $labelKey = "{$key}_id_{$userId}";
    $balances[$key] = number_format($rpc->getBalance($labelKey), 4);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $label = $_POST['from_wallet'] ?? 'Semasa';
    $toAddress = trim($_POST['wallet_address']);
    $amount = floatval($_POST['amount']);
    $notes = trim($_POST['notes']);
    $fromAccount = "{$label}_id_{$userId}";

    try {
        $txid = $wallet->sendFromUser($fromAccount, $toAddress, $amount, $notes);
        $success = "Transaction successful! TXID: $txid";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="card p-4 mx-auto mb-4" style="max-width: 500px;">
    <h5 class="text-center mb-3">Send AyuCoin</h5>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger">❌ <?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <label class="form-label">From Wallet</label>
        <select name="from_wallet" class="form-select mb-3" required>
            <?php foreach ($label_map as $key => $label): ?>
                <option value="<?= $key ?>">
                    <?= $label ?> (<?= $balances[$key] ?? '0.0000' ?> AYU)
                </option>
            <?php endforeach; ?>
        </select>

        <label class="form-label">Wallet Address</label>
        <input type="text" class="form-control mb-3" id="walletAddress" name="wallet_address" required>

        <label class="form-label">Scan QR from Camera</label>
        <div id="reader" style="width:100%; height:100%; border:1px solid #ccc; border-radius:6px; margin-bottom:15px;"></div>

        <label class="form-label">Amount (AYU)</label>
        <input type="number" class="form-control mb-3" name="amount" id="amount" step="0.0001" min="0.01" value="0.01" required>

        <label class="form-label">Notes</label>
        <textarea name="notes" class="form-control mb-3" rows="2" placeholder="Optional notes..."></textarea>

        <button type="submit" class="btn btn-primary w-100">Send Now</button>
    </form>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
function onScanSuccess(decodedText) {
    if (decodedText.includes("?amount=")) {
        const parts = decodedText.split("?amount=");
        document.getElementById("walletAddress").value = parts[0];
        document.getElementById("amount").value = parts[1];
    } else {
        document.getElementById("walletAddress").value = decodedText;
    }

    html5QrCode.stop().then(() => {
        document.getElementById("reader").innerHTML = "<p class='text-success'>✅ QR Captured!</p>";
    });
}

const html5QrCode = new Html5Qrcode("reader");
Html5Qrcode.getCameras().then(cameras => {
    if (cameras && cameras.length) {
        html5QrCode.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: 250 },
            onScanSuccess,
            error => { console.log("QR scan error", error); }
        );
    }
}).catch(err => {
    document.getElementById("reader").innerHTML = `<p class='text-danger'>Camera access failed: ${err}</p>`;
});
</script>
