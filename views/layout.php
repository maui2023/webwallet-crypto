<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AyuCoin Wallet</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            display: flex;
            flex-direction: column;
        }

        .navbar {
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        main {
            flex: 1 0 auto;
            padding-bottom: 120px; /* to avoid overlap with bottom nav */
        }

        .nav-bottom {
            position: fixed;
            bottom: 40px;
            left: 0;
            width: 100%;
            background: white;
            border-top: 1px solid #ddd;
            display: flex;
            justify-content: space-around;
            align-items: center;
            padding: 10px 0;
            z-index: 1000;
        }

        .nav-bottom a {
            text-decoration: none;
            color: #333;
            font-size: 14px;
            text-align: center;
        }

        .nav-bottom span {
            display: block;
            font-size: 24px;
        }

        footer {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            font-size: 0.9rem;
            color: #999;
            text-align: center;
            padding: 10px 0;
            background: #f8f9fa;
            z-index: 999;
        }
    </style>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#1e88e5">
    <script>
      if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('service-worker.js')
          .then(reg => console.log("SW registered"))
          .catch(err => console.error("SW failed", err));
      }
    </script>
</head>
<body>

<!-- Static Header -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container d-flex justify-content-between align-items-center">
        <a class="navbar-brand text-white" href="<?= BASE_URL ?>">AyuCoin Wallet</a>
        <?php if (isset($_SESSION['user_session'])): ?>
            <a href="<?= BASE_URL ?>auth/logout.php" class="btn btn-sm btn-light">Logout</a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>?page=auth/login" class="btn btn-sm btn-light">Login</a>
        <?php endif; ?>
    </div>
</nav>

<!-- Main Section -->
<main class="container my-4">
    <?php
    $page_path = "views/{$page}.php";
    if (file_exists($page_path)) {
        require_once $page_path;
    } else {
        echo "<div class='alert alert-danger'>Page not found: {$page}</div>";
    }
    ?>
</main>

<!-- Emoji Bottom Nav -->
<div class="nav-bottom">
    <a href="<?= BASE_URL ?>?page=home">
        <span>🏠</span> Home
    </a>
    <a href="<?= BASE_URL ?>?page=send">
        <span>📤</span> Send
    </a>
    <a href="<?= BASE_URL ?>?page=receive">
        <span>📥</span> Receive
    </a>
    <a href="<?= BASE_URL ?>?page=setting">
        <span>⚙️</span> Setting
    </a>
</div>

<!-- Fixed Footer -->
<footer>
    Powered by <strong>Sabily Enterprise</strong> (2025 ~ <?= date('Y') ?>)
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/script.js"></script>
</body>
</html>
