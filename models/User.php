<?php

class User {
    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    /**
     * Login standard menggunakan username dan password
     */
    public function login($username, $password) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            unset($user['password']);
            session_regenerate_id(true);
            $_SESSION['user_session'] = $user['username'];
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_admin'] = $user['admin'];
            $_SESSION['user_supportpin'] = $user['supportpin'];
            return true;
        }

        return false;
    }

    /**
     * Google OAuth login atau cipta akaun baru
     */
    public function findOrCreateGoogleUser($googleUser) {
        // Semak jika user telah wujud berdasarkan email di user_profiles
        $stmt = $this->db->prepare("
            SELECT u.* FROM users u
            JOIN user_profiles p ON u.id = p.user_id
            WHERE p.email = ?
        ");
        $stmt->execute([$googleUser['email']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            // Username akan dihasilkan dari prefix email
            $baseUsername = strtolower(explode('@', $googleUser['email'])[0]);
            $baseUsername = preg_replace('/[^a-z0-9_\\-]/', '', $baseUsername);
            if ($baseUsername === '') {
                $baseUsername = 'user';
            }

            $username = $baseUsername;
            $i = 1;
            while (true) {
                $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
                $stmt->execute([$username]);
                $exists = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$exists) {
                    break;
                }
                $username = $baseUsername . '_' . $i;
                $i++;
            }

            // Simpan ke dalam table `users`
            $stmt = $this->db->prepare("INSERT INTO users (username, password, date, ip) VALUES (?, '', NOW(), ?)");
            $stmt->execute([
                $username,
                $_SERVER['REMOTE_ADDR']
            ]);

            $userId = $this->db->lastInsertId();

            // Simpan profil pengguna ke dalam table `user_profiles`
            $stmt = $this->db->prepare("
                INSERT INTO user_profiles (user_id, full_name, email, photo)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $googleUser['name'],
                $googleUser['email'],
                $googleUser['picture']
            ]);

            // Ambil semula data pengguna
            $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // Set session login
        session_regenerate_id(true);
        $_SESSION['user_session'] = $user['username'];
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_admin'] = $user['admin'];
        $_SESSION['user_supportpin'] = $user['supportpin'] ?? null;

        return true;
    }

    /**
     * Kemaskini atau tambah maklumat profil pengguna
     */
    public function updateUserProfile($user_id, $data) {
        $stmt = $this->db->prepare("
            REPLACE INTO user_profiles (user_id, full_name, email, phone, address, photo)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $user_id,
            $data['full_name'],
            $data['email'],
            $data['phone'],
            $data['address'],
            $data['photo']
        ]);
    }

    /**
     * Dapatkan profil pengguna dari table user_profiles
     */
    public function getUserProfile($user_id) {
        $stmt = $this->db->prepare("SELECT * FROM user_profiles WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
