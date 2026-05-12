<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth = new AuthController($pdo);
    $success = $auth->login($_POST['username'], $_POST['password']);

    if ($success) {
        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid username or password!";
    }
}
?>

<div class="card p-4 mx-auto" style="max-width: 400px;">
    <h4 class="text-center mb-4">Login to AyuCoin Wallet</h4>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <div class="input-group">
                <input type="text" name="username" id="username" class="form-control" required>
                <span class="input-group-text"><i class="bi bi-key"></i></span>
            </div>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <div class="input-group">
                <input type="password" name="password" id="password" class="form-control" required>
                <span class="input-group-text"><i class="bi bi-key"></i></span>
            </div>
        </div>
        <button class="btn btn-primary w-100">Login</button>
    </form>

    <div class="text-center mt-4">
        <a href="<?= BASE_URL ?>auth/google_login.php" class="btn btn-danger w-100">Login with Google</a>
    </div>
</div>
