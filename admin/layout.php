<?php
require_once __DIR__ . '/../config/config.php';

$isAdmin = !empty($_SESSION['user_id']) && !empty($_SESSION['user_admin']) && (int)$_SESSION['user_admin'] === 1;
if (!$isAdmin) {
    header('Location: ' . BASE_URL . '?page=auth/login');
    exit;
}

header('X-Frame-Options: DENY');
header("Content-Security-Policy: frame-ancestors 'none'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

$page = $page ?? ($_GET['page'] ?? 'home');
$page_path = $page_path ?? null;
$username = $_SESSION['user_session'] ?? 'admin';

$navItems = [
    'home' => ['label' => 'Home', 'icon' => '🏠'],
    'users' => ['label' => 'Users', 'icon' => '👥'],
    'all_wallet' => ['label' => 'All Wallet', 'icon' => '👛'],
    'tx' => ['label' => 'Transactions', 'icon' => '📊'],
    'api' => ['label' => 'API Keys', 'icon' => '🔑'],
    'settings' => ['label' => 'Security', 'icon' => '🔐'],
];
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <title>Admin | AyuCoin Wallet</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <style>
        body {
            background: var(--bs-body-bg);
        }
        .admin-shell {
            padding-top: 56px;
        }
        .admin-sidebar {
            width: 260px;
        }
        @media (min-width: 992px) {
            .admin-sidebar {
                position: fixed;
                top: 56px;
                left: 0;
                height: calc(100vh - 56px);
                overflow-y: auto;
                border-right: 1px solid var(--bs-border-color);
                background: var(--bs-body-bg);
            }
            .admin-content {
                margin-left: 260px;
            }
        }
        .nav-pills .nav-link.active {
            font-weight: 600;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top border-bottom border-dark">
    <div class="container-fluid">
        <button class="btn btn-outline-light d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminOffcanvas" aria-controls="adminOffcanvas">☰</button>
        <a class="navbar-brand fw-semibold" href="?page=home">AyuCoin Admin</a>
        <div class="ms-auto d-flex gap-2 align-items-center">
            <button id="themeToggle" class="btn btn-sm btn-outline-light" type="button">Theme</button>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <?= htmlspecialchars($username) ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="?page=settings">Security</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>auth/logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<div class="admin-shell">
    <div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="adminOffcanvas" aria-labelledby="adminOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="adminOffcanvasLabel">Menu</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body p-2">
            <div class="nav nav-pills flex-column gap-1">
                <?php foreach ($navItems as $key => $item): ?>
                    <a class="nav-link <?= $page === $key ? 'active' : '' ?>" href="?page=<?= urlencode($key) ?>">
                        <span class="me-2"><?= $item['icon'] ?></span><?= htmlspecialchars($item['label']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <aside class="admin-sidebar d-none d-lg-block p-3">
        <div class="nav nav-pills flex-column gap-1">
            <?php foreach ($navItems as $key => $item): ?>
                <a class="nav-link <?= $page === $key ? 'active' : '' ?>" href="?page=<?= urlencode($key) ?>">
                    <span class="me-2"><?= $item['icon'] ?></span><?= htmlspecialchars($item['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="mt-4 small text-muted">
            Signed in as <span class="fw-semibold"><?= htmlspecialchars($username) ?></span>
        </div>
    </aside>

    <main class="admin-content">
        <div class="container-fluid p-3 p-lg-4">
            <?php
            if (isset($page_path) && file_exists($page_path)) {
                require $page_path;
            } else {
                echo "<div class='alert alert-danger'>Page not found</div>";
            }
            ?>
            <div class="border-top pt-3 mt-4 small text-muted">
                Powered by <strong>Sabily Enterprise</strong> (2018 ~ <?= date('Y') ?>)
            </div>
        </div>
    </main>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js"></script>
<script>
const themeKey = 'ayu_admin_theme';
function applyTheme(theme) {
    document.documentElement.setAttribute('data-bs-theme', theme);
    localStorage.setItem(themeKey, theme);
}
document.addEventListener('DOMContentLoaded', () => {
    const saved = localStorage.getItem(themeKey);
    if (saved === 'dark' || saved === 'light') {
        applyTheme(saved);
    }
    document.getElementById('themeToggle')?.addEventListener('click', () => {
        const current = document.documentElement.getAttribute('data-bs-theme') || 'light';
        applyTheme(current === 'dark' ? 'light' : 'dark');
    });
});
</script>
</body>
</html>
