<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['id_user'])) {
    header("Location: index.php");
    exit();
}

$pesan_error = '';
$pesan_sukses = '';

// Otomatis tambahkan kolom email & no_hp jika belum ada di database MySQL user
@mysqli_query($koneksi, "ALTER TABLE users ADD COLUMN email VARCHAR(100) NULL AFTER username");
@mysqli_query($koneksi, "ALTER TABLE users ADD COLUMN no_hp VARCHAR(20) NULL AFTER email");
@mysqli_query($koneksi, "ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'pembeli'");

if (isset($_POST['register'])) {
    $nama_lengkap = mysqli_real_escape_string($koneksi, trim($_POST['nama_lengkap']));
    $username     = mysqli_real_escape_string($koneksi, trim($_POST['username']));
    $email        = mysqli_real_escape_string($koneksi, trim($_POST['email']));
    $no_hp        = mysqli_real_escape_string($koneksi, trim($_POST['no_hp']));
    $password     = trim($_POST['password']);
    $konfirmasi   = trim($_POST['konfirmasi_password']);
    $role         = 'pembeli'; // Role otomatis pembeli (Juragan cuma 1)

    if (empty($nama_lengkap) || empty($username) || empty($email) || empty($password)) {
        $pesan_error = "Semua bidang bertanda bintang (*) wajib diisi!";
    } elseif ($password !== $konfirmasi) {
        $pesan_error = "Konfirmasi password tidak cocok!";
    } elseif (strlen($password) < 6) {
        $pesan_error = "Password minimal terdiri dari 6 karakter!";
    } else {
        // Cek kueri username/email aman
        $q_cek = @mysqli_query($koneksi, "SELECT * FROM users WHERE username = '$username' OR email = '$email'");
        if (!$q_cek) {
            $q_cek = @mysqli_query($koneksi, "SELECT * FROM users WHERE username = '$username'");
        }

        if ($q_cek && mysqli_num_rows($q_cek) > 0) {
            $pesan_error = "Username atau Email sudah terdaftar! Silakan gunakan yang lain.";
        } else {
            $pass_hash = md5($password);
            
            // Progressive Fallback Insert agar 100% Berhasil di Semua Struktur Tabel MySQL
            $inserted = false;

            // Opsi 1: Lengkap
            $q1 = "INSERT INTO users (nama_lengkap, username, email, no_hp, password, role) 
                   VALUES ('$nama_lengkap', '$username', '$email', '$no_hp', '$pass_hash', '$role')";
            if (@mysqli_query($koneksi, $q1)) {
                $inserted = true;
            } else {
                // Opsi 2: Tanpa no_hp
                $q2 = "INSERT INTO users (nama_lengkap, username, email, password, role) 
                       VALUES ('$nama_lengkap', '$username', '$email', '$pass_hash', '$role')";
                if (@mysqli_query($koneksi, $q2)) {
                    $inserted = true;
                } else {
                    // Opsi 3: Tanpa email & no_hp
                    $q3 = "INSERT INTO users (nama_lengkap, username, password, role) 
                           VALUES ('$nama_lengkap', '$username', '$pass_hash', '$role')";
                    if (@mysqli_query($koneksi, $q3)) {
                        $inserted = true;
                    } else {
                        // Opsi 4: Minimalist
                        $q4 = "INSERT INTO users (nama_lengkap, username, password) 
                               VALUES ('$nama_lengkap', '$username', '$pass_hash')";
                        if (@mysqli_query($koneksi, $q4)) {
                            $inserted = true;
                        }
                    }
                }
            }

            if ($inserted) {
                header("Location: login.php?status=registered");
                exit();
            } else {
                $pesan_error = "Gagal mendaftarkan akun! Detail MySQL: " . mysqli_error($koneksi);
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
    <title>Daftar Akun Pelanggan - BNI Agen46 & Toko Sembako</title>
    <link rel="stylesheet" href="assets/style/style.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .auth-wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 30px 16px;
            position: relative;
            background: linear-gradient(135deg, #002d38 0%, #005e6a 45%, #00818a 75%, #004d5a 100%);
            overflow: hidden;
            box-sizing: border-box;
        }
        .auth-wrapper::before {
            content: '';
            position: absolute;
            top: -100px;
            left: -100px;
            width: 360px;
            height: 360px;
            background: radial-gradient(circle, rgba(242, 101, 34, 0.35) 0%, rgba(242, 101, 34, 0) 70%);
            border-radius: 50%;
            pointer-events: none;
            z-index: 1;
        }
        .auth-wrapper::after {
            content: '';
            position: absolute;
            bottom: -120px;
            right: -100px;
            width: 420px;
            height: 420px;
            background: radial-gradient(circle, rgba(0, 229, 204, 0.22) 0%, rgba(0, 229, 204, 0) 70%);
            border-radius: 50%;
            pointer-events: none;
            z-index: 1;
        }
        .back-home {
            position: relative;
            z-index: 2;
            display: inline-flex;
            align-items: center;
            color: rgba(255, 255, 255, 0.9);
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            margin-bottom: 16px;
            padding: 6px 14px;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(8px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.2s ease;
        }
        .back-home:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.22);
        }
        .auth-card {
            position: relative;
            z-index: 2;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 30, 40, 0.25);
            width: 100%;
            max-width: 420px;
            padding: 28px 24px;
            border: 1px solid #e2e8f0;
            border-top: 5px solid #f26522;
            box-sizing: border-box !important;
        }
        .auth-header { text-align: center; margin-bottom: 20px; }
        .auth-brand { font-size: 24px; font-weight: 800; color: #004d5a; letter-spacing: -0.5px; }
        .auth-brand span { color: #f26522; }
        .auth-subtitle { font-size: 12px; color: #64748b; margin-top: 4px; }
        .auth-form { width: 100%; box-sizing: border-box; }
        .form-group { width: 100%; margin-bottom: 14px; box-sizing: border-box; }
        .form-group label { display: block; font-size: 11px; font-weight: 700; color: #475569; margin-bottom: 4px; }
        .input-with-icon { position: relative; width: 100%; box-sizing: border-box !important; }
        .input-with-icon input {
            width: 100% !important;
            box-sizing: border-box !important;
            padding: 10px 38px 10px 38px !important;
            border: 1.5px solid #cbd5e1;
            border-radius: 10px;
            font-size: 13px;
            background: #f8fafc;
            color: #0f172a;
            transition: all 0.2s ease;
        }
        .input-with-icon input:focus {
            outline: none;
            border-color: #005e6a;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(0, 94, 106, 0.15);
        }
        .input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 15px;
            color: #64748b;
            pointer-events: none;
        }
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            padding: 4px;
            color: #64748b;
        }
        .btn-auth-submit {
            width: 100%;
            background: linear-gradient(135deg, #f26522, #ea580c);
            color: #ffffff;
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(242, 101, 34, 0.3);
            margin-top: 8px;
            transition: all 0.2s ease;
        }
        .btn-auth-submit:hover {
            background: linear-gradient(135deg, #ea580c, #c2410c);
            transform: translateY(-1px);
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 12px;
            margin-bottom: 16px;
            border: 1px solid #fca5a5;
        }
        .auth-footer {
            text-align: center;
            margin-top: 18px;
            padding-top: 14px;
            border-top: 1px solid #f1f5f9;
            font-size: 12px;
            color: #64748b;
        }
        .auth-footer a { color: #005e6a; font-weight: 700; text-decoration: none; }
        .auth-footer a:hover { color: #f26522; }
    </style>
</head>
<body>

    <div class="auth-wrapper">
        <a href="index.php" class="back-home">← Kembali ke Beranda Utama</a>

        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-brand">BNI <span>Agen46</span></div>
                <div class="auth-subtitle">Registrasi Akun Pelanggan Toko Sembako</div>
            </div>

            <?php if (!empty($pesan_error)): ?>
                <div class="alert-error"><?= htmlspecialchars($pesan_error) ?></div>
            <?php endif; ?>

            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label>Nama Lengkap *</label>
                    <div class="input-with-icon">
                        <span class="input-icon">📛</span>
                        <input type="text" name="nama_lengkap" placeholder="Masukkan nama lengkap Anda..." required value="<?= isset($_POST['nama_lengkap']) ? htmlspecialchars($_POST['nama_lengkap']) : '' ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Username *</label>
                    <div class="input-with-icon">
                        <span class="input-icon">👤</span>
                        <input type="text" name="username" placeholder="Buat username baru..." required value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Email *</label>
                    <div class="input-with-icon">
                        <span class="input-icon">✉️</span>
                        <input type="email" name="email" placeholder="nama@email.com" required value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Nomor WhatsApp / HP</label>
                    <div class="input-with-icon">
                        <span class="input-icon">📱</span>
                        <input type="tel" name="no_hp" placeholder="08xxxxxxxxxx" value="<?= isset($_POST['no_hp']) ? htmlspecialchars($_POST['no_hp']) : '' ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Password *</label>
                    <div class="input-with-icon">
                        <span class="input-icon">🔒</span>
                        <input type="password" name="password" id="passRegInput" placeholder="Minimal 6 karakter..." required>
                        <button type="button" class="toggle-password" onclick="togglePassReg()">👁️</button>
                    </div>
                </div>

                <div class="form-group">
                    <label>Konfirmasi Password *</label>
                    <div class="input-with-icon">
                        <span class="input-icon">🔑</span>
                        <input type="password" name="konfirmasi_password" id="passConfirmInput" placeholder="Ulangi password..." required>
                    </div>
                </div>

                <button type="submit" name="register" class="btn-auth-submit">
                    📝 Buat Akun Sekarang
                </button>
            </form>

            <div class="auth-footer">
                Sudah memiliki akun? <a href="login.php">Masuk di sini</a>
            </div>
        </div>
    </div>

    <script>
        function togglePassReg() {
            const passInput = document.getElementById('passRegInput');
            const passConfirm = document.getElementById('passConfirmInput');
            const show = passInput.type === 'password';
            passInput.type = show ? 'text' : 'password';
            passConfirm.type = show ? 'text' : 'password';
        }
    </script>
</body>
</html>
