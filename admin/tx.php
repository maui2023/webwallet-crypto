<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/AdminClient.php';

$isAdmin = !empty($_SESSION['user_id']) && !empty($_SESSION['user_admin']) && (int)$_SESSION['user_admin'] === 1;
if (!$isAdmin) {
    header('Location: ' . BASE_URL . '?page=auth/login');
    exit;
}

$rpc = new AdminClient(RPC_HOST, RPC_PORT, RPC_USER, RPC_PASS);
$txList = $rpc->listSinceBlock();
$txs = $txList['transactions'] ?? [];

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$p = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($p < 1) $p = 1;
$limit = 25;

usort($txs, function ($a, $b) {
    $ta = isset($a['time']) ? (int)$a['time'] : 0;
    $tb = isset($b['time']) ? (int)$b['time'] : 0;
    return $tb <=> $ta;
});

if ($q !== '') {
    $qLower = mb_strtolower($q);
    $txs = array_values(array_filter($txs, function ($tx) use ($qLower) {
        $txid = (string)($tx['txid'] ?? '');
        $address = (string)($tx['address'] ?? '');
        $category = (string)($tx['category'] ?? '');
        $amount = (string)($tx['amount'] ?? '');
        $haystack = mb_strtolower($txid . ' ' . $address . ' ' . $category . ' ' . $amount);
        return mb_strpos($haystack, $qLower) !== false;
    }));
}

$total = count($txs);
$totalPages = max(1, (int)ceil($total / $limit));
if ($p > $totalPages) $p = $totalPages;
$offset = ($p - 1) * $limit;
$pageTxs = array_slice($txs, $offset, $limit);

$start = $total === 0 ? 0 : ($offset + 1);
$end = min($offset + count($pageTxs), $total);
?>

<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="mb-0">📊 Transactions</h4>
  <div class="text-muted small">Showing <?= number_format($start) ?>-<?= number_format($end) ?> of <?= number_format($total) ?></div>
</div>

<form class="row g-2 align-items-end mb-3" method="GET" action="">
  <input type="hidden" name="page" value="tx">
  <div class="col-md-6">
    <label class="form-label">Search</label>
    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control" placeholder="txid / address / type / amount">
  </div>
  <div class="col-md-3">
    <button class="btn btn-primary w-100" type="submit">Search</button>
  </div>
  <div class="col-md-3">
    <a class="btn btn-outline-secondary w-100" href="?page=tx">Clear</a>
  </div>
</form>

<div class="card">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-bordered table-striped align-middle mb-0">
        <thead class="table-dark">
          <tr>
            <th>TXID</th>
            <th>Type</th>
            <th class="text-end">Amount</th>
            <th>Address</th>
            <th>Time</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($pageTxs)): ?>
            <tr>
              <td colspan="5" class="text-center text-muted">No transactions found</td>
            </tr>
          <?php endif; ?>
          <?php foreach ($pageTxs as $tx): ?>
            <?php
              $amount = floatval($tx['amount'] ?? 0);
              $type = (string)($tx['category'] ?? '');
              $date = isset($tx['time']) ? date('d M Y H:i', (int)$tx['time']) : '-';
              $color = $amount < 0 ? 'text-danger' : 'text-success';
              $txidShort = isset($tx['txid']) ? substr((string)$tx['txid'], 0, 12) : '-';
              $address = $tx['address'] ?? '-';
            ?>
            <tr>
              <td class="font-monospace"><?= htmlspecialchars($txidShort) ?></td>
              <td><?= htmlspecialchars(ucfirst($type)) ?></td>
              <td class="text-end <?= $color ?>"><?= number_format($amount, 6) ?></td>
              <td class="font-monospace"><?= htmlspecialchars((string)$address) ?></td>
              <td><?= htmlspecialchars($date) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php if ($totalPages > 1): ?>
  <?php
  $queryBase = ['page' => 'tx'];
  if ($q !== '') $queryBase['q'] = $q;

  $window = 2;
  $from = max(1, $p - $window);
  $to = min($totalPages, $p + $window);

  $prevParams = $queryBase;
  $prevParams['p'] = max(1, $p - 1);
  $nextParams = $queryBase;
  $nextParams['p'] = min($totalPages, $p + 1);
  ?>
  <nav class="mt-3" aria-label="Transactions pagination">
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
