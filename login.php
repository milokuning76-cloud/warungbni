<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['id_user'])) {
    header("Location: index.php");
    exit();
}

$pesan_error = '';
$pesan_sukses = '';

if (isset($_GET['status']) && $_GET['status'] == 'harus_login') {
    $pesan_error = "Silakan login terlebih dahulu untuk melanjutkan checkout!";
} elseif (isset($_GET['status']) && $_GET['status'] == 'registered') {
    $pesan_sukses = "Registrasi berhasil! Silakan login dengan akun baru Anda.";
}

if (isset($_POST['login'])) {
    $username = mysqli_real_escape_string($koneksi, trim($_POST['username']));
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $pesan_error = "Username dan Password wajib diisi!";
    } else {
        // Safe Query Check
        $q_user = @mysqli_query($koneksi, "SELECT * FROM users WHERE username = '$username' OR email = '$username'");
        if (!$q_user) {
            $q_user = @mysqli_query($koneksi, "SELECT * FROM users WHERE username = '$username'");
        }

        if ($q_user && mysqli_num_rows($q_user) > 0) {
            $user = mysqli_fetch_assoc($q_user);
            
            $password_valid = false;
            if ($password === $user['password']) {
                $password_valid = true;
            } elseif (md5($password) === $user['password']) {
                $password_valid = true;
            } elseif (function_exists('password_verify') && password_verify($password, $user['password'])) {
                $password_valid = true;
            }

            if ($password_valid) {
                $_SESSION['id_user']      = $user['id_user'];
                $_SESSION['username']     = $user['username'];
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                $_SESSION['role']         = isset($user['role']) ? $user['role'] : 'pembeli';

                header("Location: index.php");
                exit();
            } else {
                $pesan_error = "Password yang Anda masukkan salah!";
            }
        } else {
            // Fallback Akun Demo Cepat
            if ($username === 'juragan' && $password === 'admin123') {
                $_SESSION['id_user']      = 1;
                $_SESSION['username']     = 'juragan';
                $_SESSION['nama_lengkap'] = 'Juragan Sembako (Admin)';
                $_SESSION['role']         = 'admin';
                header("Location: index.php");
                exit();
            } elseif ($username === 'pembeli' && $password === 'pembeli123') {
                $_SESSION['id_user']      = 2;
                $_SESSION['username']     = 'pembeli';
                $_SESSION['nama_lengkap'] = 'Pelanggan Setia';
                $_SESSION['role']         = 'pembeli';
                header("Location: index.php");
                exit();
            } else {
                $pesan_error = "Username atau Email tidak ditemukan!";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk Akun - BNI Agen46 & Toko Sembako</title>
    <link rel="stylesheet" href="assets/style/style.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <div class="auth-wrapper">
        <a href="index.php" class="back-home">← Kembali ke Beranda Utama</a>

        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-brand">BNI <span>Agen46</span></div>
                <div class="auth-subtitle">Sistem Informasi Toko Sembako & Layanan Perbankan</div>
            </div>

            <?php if (!empty($pesan_sukses)): ?>
                <div class="alert-success"><?= htmlspecialchars($pesan_sukses) ?></div>
            <?php endif; ?>

            <?php if (!empty($pesan_error)): ?>
                <div class="alert-error"><?= htmlspecialchars($pesan_error) ?></div>
            <?php endif; ?>

            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label>Username / Email</label>
                    <div class="input-with-icon">
                        <span class="input-icon">👤</span>
                        <input type="text" name="username" placeholder="Masukkan username atau email..." required value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="input-with-icon">
                        <span class="input-icon">🔒</span>
                        <input type="password" name="password" id="passInput" placeholder="Masukkan password..." required>
                        <button type="button" class="toggle-password" onclick="togglePass()">👁️</button>
                    </div>
                </div>

                <button type="submit" name="login" class="btn-auth-submit">
                    🔑 Masuk Sekarang
                </button>
            </form>

            <div class="auth-footer">
                Belum memiliki akun? <a href="register.php">Daftar Sekarang</a>
            </div>
        </div>
    </div>

    <script>
        function togglePass() {
            const passInput = document.getElementById('passInput');
            passInput.type = (passInput.type === 'password') ? 'text' : 'password';
        }
    </script>
</body>
</html>
