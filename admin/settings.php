<?php
require_once __DIR__ . '/../config/config.php';
$isAdmin = !empty($_SESSION['user_id']) && !empty($_SESSION['user_admin']) && (int)$_SESSION['user_admin'] === 1;
if (!$isAdmin) {
    header('Location: ' . BASE_URL . '?page=auth/login');
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$success = '';
$error = '';

$stmt = $pdo->prepare("SELECT u.username, u.password, u.admin, p.email, p.phone FROM users u LEFT JOIN user_profiles p ON u.id = p.user_id WHERE u.id = ?");
$stmt->execute([$userId]);
$me = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        if (isset($_POST['rotate_session'])) {
            session_regenerate_id(true);
            $success = 'Session rotated.';
        }

        if (isset($_POST['change_password'])) {
            $old = (string)($_POST['old_password'] ?? '');
            $new = (string)($_POST['new_password'] ?? '');
            $confirm = (string)($_POST['confirm_password'] ?? '');

            if (strlen($new) < 10) {
                $error = 'New password must be at least 10 characters.';
            } elseif ($new !== $confirm) {
                $error = 'New passwords do not match.';
            } elseif (!empty($me['password']) && !password_verify($old, $me['password'])) {
                $error = 'Old password incorrect.';
            } else {
                $newHash = password_hash($new, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
                $stmt->execute([$newHash, $userId]);
                session_regenerate_id(true);
                $success = 'Password updated.';

                $stmt = $pdo->prepare("SELECT u.username, u.password, u.admin, p.email, p.phone FROM users u LEFT JOIN user_profiles p ON u.id = p.user_id WHERE u.id = ?");
                $stmt->execute([$userId]);
                $me = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
    }
}
?>

<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">🔐 Security</h4>
    <div class="text-muted small">Admin access</div>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">Account</div>
            <div class="card-body">
                <div class="mb-2"><span class="text-muted">Username:</span> <span class="fw-semibold"><?= htmlspecialchars($me['username'] ?? '-') ?></span></div>
                <div class="mb-2"><span class="text-muted">Email:</span> <span class="fw-semibold"><?= htmlspecialchars($me['email'] ?? '-') ?></span></div>
                <div class="mb-2"><span class="text-muted">Phone:</span> <span class="fw-semibold"><?= htmlspecialchars($me['phone'] ?? '-') ?></span></div>
                <div class="mb-0"><span class="text-muted">Role:</span> <span class="fw-semibold"><?= !empty($me['admin']) ? 'Admin' : 'User' ?></span></div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">Session</div>
            <div class="card-body">
                <div class="mb-3 small text-muted">If you suspect login/session hijacking, rotate your session.</div>
                <form method="POST" class="d-flex gap-2">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <button class="btn btn-outline-primary" name="rotate_session" value="1" type="submit">Rotate Session</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">Change Password</div>
    <div class="card-body">
        <form method="POST" class="row g-2">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <div class="col-md-4">
                <input type="password" name="old_password" class="form-control" placeholder="Old password" <?= !empty($me['password']) ? 'required' : '' ?>>
            </div>
            <div class="col-md-4">
                <input type="password" name="new_password" class="form-control" placeholder="New password (min 10)" required>
            </div>
            <div class="col-md-4">
                <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password" required>
            </div>
            <div class="col-12">
                <button class="btn btn-warning" name="change_password" value="1" type="submit">Update Password</button>
            </div>
        </form>
    </div>
</div>
