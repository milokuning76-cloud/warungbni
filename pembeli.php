<?php
session_start();
require_once 'config.php';

// Keamanan: Hanya Juragan (Admin) yang boleh mengakses data pembeli
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$pesan_sukses = '';
$pesan_error  = '';

// PROSES HAPUS PEMBELI
if (isset($_POST['hapus_pembeli'])) {
    $id_del = intval($_POST['id_pembeli']);
    
    // Pastikan yang dihapus adalah role pembeli
    $q_del = mysqli_query($koneksi, "DELETE FROM users WHERE id_user = '$id_del' AND role = 'pembeli'");
    if ($q_del) {
        $pesan_sukses = "Data pembeli berhasil dihapus!";
    } else {
        $pesan_error = "Gagal menghapus data pembeli!";
    }
}

// FILTER PENCARIAN
$keyword = isset($_GET['q']) ? mysqli_real_escape_string($koneksi, trim($_GET['q'])) : '';
$sql_search = "";
if (!empty($keyword)) {
    $sql_search = "AND (u.nama_lengkap LIKE '%$keyword%' OR u.username LIKE '%$keyword%' OR u.email LIKE '%$keyword%' OR u.no_hp LIKE '%$keyword%')";
}

// QUERY DATA PEMBELI YANG TERDAFTAR SAAT REGISTER
$q_pembeli = mysqli_query($koneksi, "
    SELECT u.*, 
           COUNT(t.id_transaksi) AS total_pesanan,
           COALESCE(SUM(CASE WHEN t.status_pembayaran = 'lunas' THEN t.total_bayar ELSE 0 END), 0) AS total_belanja
    FROM users u
    LEFT JOIN transaksi t ON u.id_user = t.id_user
    WHERE u.role = 'pembeli' $sql_search
    GROUP BY u.id_user
    ORDER BY u.id_user DESC
");

// STATISTIK RINGKAS
$c_total_pembeli = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM users WHERE role = 'pembeli'"))['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pembeli & Pelanggan - BNI Agen46</title>
    <link rel="stylesheet" href="assets/style/style.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header>
        <div class="logo">BNI <span>Agen46</span> - Data Pembeli Terdaftar</div>
        <a href="index.php" style="color:white; text-decoration:none; font-size:12px; font-weight:600;">← Kembali ke Beranda</a>
    </header>

    <div class="container">

        <?php if (!empty($pesan_sukses)): ?>
            <div class="alert-success"><?= htmlspecialchars($pesan_sukses) ?></div>
        <?php endif; ?>

        <?php if (!empty($pesan_error)): ?>
            <div class="alert-error"><?= htmlspecialchars($pesan_error) ?></div>
        <?php endif; ?>

        <!-- BARIS FILTER & CARI PEMBELI -->
        <div class="card-filter">
            <form method="GET" class="filter-form" style="width: 100%; display: flex; gap: 10px;">
                <input type="text" name="q" value="<?= htmlspecialchars($keyword) ?>" class="form-control" placeholder="Cari nama, username, email, atau no HP pembeli..." style="flex: 1;">
                <button type="submit" class="btn-filter">🔍 Cari Pembeli</button>
                <?php if (!empty($keyword)): ?>
                    <a href="pembeli.php" class="btn-filter" style="background:#64748b; text-decoration:none; display:inline-flex; align-items:center;">🔄 Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- RINGKASAN STATISTIK PEMBELI -->
        <div class="stat-grid" style="grid-template-columns: repeat(2, 1fr);">
            <div class="stat-card">
                <div class="stat-label">Total Pembeli Terdaftar</div>
                <div class="stat-value"><?= $c_total_pembeli ?> Pelanggan</div>
                <div class="stat-sub">Akun konsumen terdaftar dari halaman register</div>
            </div>

            <div class="stat-card blue">
                <div class="stat-label">Status Akses Juragan</div>
                <div class="stat-value">Akses Penuh (CRUD)</div>
                <div class="stat-sub">Dapat melihat rincian pendaftaran & kontak WA</div>
            </div>
        </div>

        <!-- TABEL DATA PEMBELI TERDAFTAR -->
        <div class="card" style="box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px;">
            <div class="card-title">
                <span>👥 Daftar Akun Pembeli (Input Registrasi Pelanggan)</span>
                <small style="font-size:11px; color:#64748b;">(Data yang dimasukkan pembeli saat register)</small>
            </div>

            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Lengkap</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>No. WhatsApp / HP</th>
                            <th>Total Pesanan</th>
                            <th>Total Belanja</th>
                            <th>Aksi Juragan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($q_pembeli) == 0): ?>
                            <tr>
                                <td colspan="8" style="text-align:center; color:#94a3b8; padding:20px;">
                                    Belum ada data pembeli terdaftar<?= !empty($keyword) ? ' yang sesuai pencarian.' : '.' ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            $no = 1;
                            while ($p = mysqli_fetch_assoc($q_pembeli)): 
                                $no_wa = preg_replace('/[^0-9]/', '', $p['no_hp']);
                                if (substr($no_wa, 0, 1) === '0') {
                                    $no_wa = '62' . substr($no_wa, 1);
                                }
                            ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td>
                                        <strong>👤 <?= htmlspecialchars($p['nama_lengkap']) ?></strong>
                                    </td>
                                    <td><code><?= htmlspecialchars($p['username']) ?></code></td>
                                    <td>📧 <?= htmlspecialchars($p['email']) ?></td>
                                    <td>
                                        <?php if (!empty($p['no_hp'])): ?>
                                            <a href="https://wa.me/<?= $no_wa ?>" target="_blank" style="color:#16a34a; font-weight:700; text-decoration:none;">
                                                📱 <?= htmlspecialchars($p['no_hp']) ?> (WA)
                                            </a>
                                        <?php else: ?>
                                            <span style="color:#94a3b8;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge blue"><?= $p['total_pesanan'] ?> Order</span>
                                    </td>
                                    <td>
                                        <strong>Rp <?= number_format($p['total_belanja'], 0, ',', '.') ?></strong>
                                    </td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="id_pembeli" value="<?= $p['id_user'] ?>">
                                            <button type="submit" name="hapus_pembeli" style="background:#fee2e2; color:#991b1b; border:none; padding:5px 10px; border-radius:6px; font-size:11px; font-weight:700; cursor:pointer;" onclick="return confirm('Yakin ingin menghapus akun pembeli <?= htmlspecialchars($p['nama_lengkap']) ?>?')">
                                                🗑️ Hapus
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</body>
</html>
