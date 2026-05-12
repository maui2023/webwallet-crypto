<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Client.php';

if (!isset($_SESSION['user_session'])) {
    header('Location: ' . BASE_URL . '?page=auth/login');
    exit;
}

$userId = $_SESSION['user_id'];
$pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Fetch profile info
$stmt = $pdo->prepare("SELECT u.username, u.password, p.* FROM users u JOIN user_profiles p ON u.id = p.user_id WHERE u.id = ?");
$stmt->execute([$userId]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

$success = $error = '';

// Update profile info
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_profile'])) {
        $stmt = $pdo->prepare("UPDATE user_profiles SET full_name=?, phone=?, address=? WHERE user_id=?");
        $stmt->execute([
            trim($_POST['full_name']),
            trim($_POST['phone']),
            trim($_POST['address']),
            $userId
        ]);
        $success = "Profile updated successfully.";
    }

    // Update password
    if (isset($_POST['change_password'])) {
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];
        $old = $_POST['old_password'] ?? '';
    
        if ($profile['password'] && !password_verify($old, $profile['password'])) {
            $error = "Old password incorrect.";
        } elseif ($new !== $confirm) {
            $error = "New passwords do not match.";
        } else {
            $newHash = password_hash($new, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
            $stmt->execute([$newHash, $userId]);
            $success = "Password set successfully.";
        }
    }

    // Update photo
    if (isset($_FILES['photo']) && $_FILES['photo']['size'] > 0 && $_FILES['photo']['size'] < 1048576) {
        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $newFile = "avatar_" . $userId . "_" . time() . "." . $ext;
        $uploadPath = "assets/uploads/" . $newFile;
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadPath)) {
            $stmt = $pdo->prepare("UPDATE user_profiles SET photo=? WHERE user_id=?");
            $stmt->execute([$newFile, $userId]);
            $success = "Photo updated.";
        } else {
            $error = "Photo upload failed.";
        }
    }

    // Toggle dark mode
    if (isset($_POST['toggle_dark'])) {
        $stmt = $pdo->prepare("UPDATE user_profiles SET dark_mode = NOT dark_mode WHERE user_id = ?");
        $stmt->execute([$userId]);
        header("Location: " . BASE_URL . "?page=setting");
        exit;
    }

    // Reload profile
    $stmt = $pdo->prepare("SELECT u.username, u.password, p.* FROM users u JOIN user_profiles p ON u.id = p.user_id WHERE u.id = ?");
    $stmt->execute([$userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<div class="card p-4 mx-auto" style="max-width: 600px;">
    <h4 class="mb-3 text-center">Settings</h4>

    <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

    <!-- Profile Info -->
    <form method="POST">
        <label class="form-label">Username</label>
        <input type="text" class="form-control mb-2" value="<?= htmlspecialchars($profile['username'] ?? '') ?>" readonly>

        <label class="form-label">Full Name</label>
        <input type="text" name="full_name" class="form-control mb-2" value="<?= htmlspecialchars($profile['full_name'] ?? '') ?>">

        <label class="form-label">Phone Number</label>
        <input type="text" name="phone" class="form-control mb-2" value="<?= htmlspecialchars($profile['phone'] ?? '') ?>">

        <label class="form-label">Address</label>
        <textarea name="address" class="form-control mb-3"><?= htmlspecialchars($profile['address'] ?? '') ?></textarea>

        <button name="save_profile" class="btn btn-primary w-100">Save Profile</button>
    </form>

    <hr>

    <!-- Password -->
    <form method="POST">
        <label class="form-label">Change Password</label>
    
        <?php if (!empty($profile['password'])): ?>
            <input type="password" name="old_password" class="form-control mb-2" placeholder="Old password" required>
        <?php else: ?>
            <div class="alert alert-info">You registered using Google. Please set a password for local login.</div>
        <?php endif; ?>
    
        <input type="password" name="new_password" class="form-control mb-2" placeholder="New password" required>
        <input type="password" name="confirm_password" class="form-control mb-3" placeholder="Confirm new password" required>
        <button name="change_password" class="btn btn-warning w-100">Set Password</button>
    </form>

    <hr>

    <!-- Profile Picture -->
    <form method="POST" enctype="multipart/form-data">
        <label class="form-label">Profile Photo (Max 1MB)</label><br>
        <?php if (!empty($profile['photo'])): ?>
            <?php
                $isUrl = (strpos($profile['photo'], 'http') === 0);
                $photoUrl = $isUrl ? $profile['photo'] : 'assets/uploads/' . $profile['photo'];
            ?>
            <img src="<?= $photoUrl ?>" class="img-thumbnail mb-2" style="max-width: 150px;">
        <?php endif; ?>
        <input type="file" name="photo" accept="image/*" class="form-control mb-2">
        <button class="btn btn-secondary w-100">Upload New Photo</button>
    </form>

    <hr>

    <!-- Dark Mode -->
    <form method="POST">
        <button name="toggle_dark" class="btn btn-outline-dark w-100">
            <?= $profile['dark_mode'] ? '🌙 Disable Dark Mode' : '🌙 Enable Dark Mode' ?>
        </button>
    </form>

    <hr>

    <!-- Google Account -->
    <div class="text-center">
        <label class="form-label">Google Account</label><br>
        <?php if (!empty($profile['email']) && strpos($profile['email'], '@gmail.com') !== false): ?>
            ✅ Linked: <strong><?= htmlspecialchars($profile['email']) ?></strong>
        <?php else: ?>
            <a href="<?= BASE_URL ?>auth/google_login.php" class="btn btn-danger btn-sm">Link Google Account</a>
        <?php endif; ?>
    </div>
</div>
