<?php
session_start();
require_once 'config.php';

// Keamanan: Hanya Juragan (Admin) yang boleh mengakses laporan keuangan
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$pesan_sukses = '';
$pesan_error  = '';

// 1. PROSES AKSI JURAGAN: KONFIRMASI PEMBAYARAN (LUNAS)
if (isset($_POST['konfirmasi_lunas'])) {
    $id_transaksi = intval($_POST['id_transaksi']);
    $q_update = mysqli_query($koneksi, "UPDATE transaksi SET status_pembayaran = 'lunas' WHERE id_transaksi = '$id_transaksi'");
    if ($q_update) {
        $pesan_sukses = "Pesanan berhasil dikonfirmasi sebagai LUNAS!";
    } else {
        $pesan_error = "Gagal memperbarui status pesanan!";
    }
}

// 2. PROSES AKSI JURAGAN: BATALKAN PESANAN & KEMBALIKAN STOK
if (isset($_POST['batalkan_pesanan'])) {
    $id_transaksi = intval($_POST['id_transaksi']);
    
    // Ambil detail item pesanan untuk mengembalikan stok ke database
    $q_items = mysqli_query($koneksi, "SELECT * FROM detail_transaksi WHERE id_transaksi = '$id_transaksi'");
    while ($item = mysqli_fetch_assoc($q_items)) {
        $id_p = $item['id_produk'];
        $qty  = $item['jumlah'];
        mysqli_query($koneksi, "UPDATE produk SET stok = stok + $qty WHERE id_produk = '$id_p'");
    }

    // Ubah status pembayaran menjadi dibatalkan
    $q_batal = mysqli_query($koneksi, "UPDATE transaksi SET status_pembayaran = 'dibatalkan' WHERE id_transaksi = '$id_transaksi'");
    if ($q_batal) {
        $pesan_sukses = "Pesanan berhasil dibatalkan dan stok produk telah dikembalikan!";
    } else {
        $pesan_error = "Gagal membatalkan pesanan!";
    }
}

// Default filter tanggal: Bulan ini
$tgl_mulai   = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-01');
$tgl_selesai = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');

// 1. HITUNG OMZET PENJUALAN SEMBAKO (POS + ONLINE)
$q_omzet_sembako = mysqli_query($koneksi, "
    SELECT SUM(total_bayar) AS total_sembako, COUNT(id_transaksi) AS jml_tx_sembako 
    FROM transaksi 
    WHERE jenis_transaksi = 'sembako' 
      AND status_pembayaran = 'lunas' 
      AND DATE(tgl_transaksi) BETWEEN '$tgl_mulai' AND '$tgl_selesai'
");
$data_sembako   = mysqli_fetch_assoc($q_omzet_sembako);
$total_sembako  = floatval($data_sembako['total_sembako'] ?? 0);
$jml_tx_sembako = intval($data_sembako['jml_tx_sembako'] ?? 0);

// 2. HITUNG VOLUME & ADMIN BNI AGEN46 / PPOB
$q_agen46 = mysqli_query($koneksi, "
    SELECT SUM(a.nominal_transaksi) AS vol_agen46, SUM(a.biaya_admin) AS admin_agen46, COUNT(t.id_transaksi) AS jml_tx_agen46
    FROM transaksi t
    JOIN transaksi_agen46 a ON t.id_transaksi = a.id_transaksi
    WHERE t.jenis_transaksi = 'agen46' 
      AND DATE(t.tgl_transaksi) BETWEEN '$tgl_mulai' AND '$tgl_selesai'
");
$data_agen46   = mysqli_fetch_assoc($q_agen46);
$vol_agen46    = floatval($data_agen46['vol_agen46'] ?? 0);
$admin_agen46  = floatval($data_agen46['admin_agen46'] ?? 0);
$jml_tx_agen46 = intval($data_agen46['jml_tx_agen46'] ?? 0);

// 3. ESTIMASI LABA KASAR SEMBAKO (ASUMSI MARGIN ~15%) + BIAYA ADMIN AGEN46
$laba_sembako  = $total_sembako * 0.15; 
$total_laba_estimasi = $laba_sembako + $admin_agen46;

// 4. AMBIL DETAIL RIWAYAT TRANSAKSI SEMBAKO
$q_list_sembako = mysqli_query($koneksi, "
    SELECT t.*, u.nama_lengkap 
    FROM transaksi t
    JOIN users u ON t.id_user = u.id_user
    WHERE t.jenis_transaksi = 'sembako' 
      AND DATE(t.tgl_transaksi) BETWEEN '$tgl_mulai' AND '$tgl_selesai'
    ORDER BY t.tgl_transaksi DESC
");

// 5. AMBIL DETAIL RIWAYAT TRANSAKSI AGEN46
$q_list_agen46 = mysqli_query($koneksi, "
    SELECT t.no_faktur, t.tgl_transaksi, a.* 
    FROM transaksi t
    JOIN transaksi_agen46 a ON t.id_transaksi = a.id_transaksi
    WHERE DATE(t.tgl_transaksi) BETWEEN '$tgl_mulai' AND '$tgl_selesai'
    ORDER BY t.tgl_transaksi DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Keuangan & Penjualan - BNI Agen46</title>
    <link rel="stylesheet" href="assets/style/style.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header>
        <div class="logo">BNI <span>Agen46</span> - Laporan Keuangan</div>
        <a href="index.php" style="color:white; text-decoration:none; font-size:12px; font-weight:600;">← Kembali ke Beranda</a>
    </header>

    <div class="container">

        <!-- FILTER TANGGAL & CETAK LAPORAN -->
        <div class="card-filter">
            <form method="GET" class="filter-form">
                <label style="font-size:12px; font-weight:700;">Periode:</label>
                <input type="date" name="tgl_mulai" value="<?= $tgl_mulai ?>" class="form-control">
                <span style="font-size:12px;">s/d</span>
                <input type="date" name="tgl_selesai" value="<?= $tgl_selesai ?>" class="form-control">
                <button type="submit" class="btn-filter">🔍 Tampilkan</button>
            </form>

            <button onclick="window.print()" class="btn-print">🖨️ Cetak Laporan (PDF)</button>
        </div>

        <?php if (!empty($pesan_sukses)): ?>
            <div class="alert-success"><?= htmlspecialchars($pesan_sukses) ?></div>
        <?php endif; ?>

        <?php if (!empty($pesan_error)): ?>
            <div class="alert-error"><?= htmlspecialchars($pesan_error) ?></div>
        <?php endif; ?>

        <!-- STATISTIK KEUANGAN -->
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-label">Omzet Penjualan Sembako</div>
                <div class="stat-value">Rp <?= number_format($total_sembako, 0, ',', '.') ?></div>
                <div class="stat-sub">Total <?= $jml_tx_sembako ?> Transaksi Lunas</div>
            </div>

            <div class="stat-card blue">
                <div class="stat-label">Volume BNI Agen46 / PPOB</div>
                <div class="stat-value">Rp <?= number_format($vol_agen46, 0, ',', '.') ?></div>
                <div class="stat-sub">Total <?= $jml_tx_agen46 ?> Transaksi Perbankan</div>
            </div>

            <div class="stat-card orange">
                <div class="stat-label">Pendapatan Admin Agen46</div>
                <div class="stat-value">Rp <?= number_format($admin_agen46, 0, ',', '.') ?></div>
                <div class="stat-sub">Komisi Fee Layanan</div>
            </div>

            <div class="stat-card green">
                <div class="stat-label">Estimasi Total Laba Bersih</div>
                <div class="stat-value">Rp <?= number_format($total_laba_estimasi, 0, ',', '.') ?></div>
                <div class="stat-sub">Margin Sembako + Fee Admin</div>
            </div>
        </div>

        <!-- TABEL 1: RIWAYAT TRANSAKSI SEMBAKO -->
        <div class="card" style="box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px;">
            <div class="card-title">
                <span>🛒 Riwayat Transaksi Penjualan Sembako</span>
                <small style="font-size:11px; color:#64748b;">(POS Kasir & Pesanan Online)</small>
            </div>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>No Faktur</th>
                            <th>Tanggal & Waktu</th>
                            <th>Pelanggan / Kasir</th>
                            <th>Total Bayar</th>
                            <th>Uang Diterima</th>
                            <th>Kembalian</th>
                            <th>Status & Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($q_list_sembako) == 0): ?>
                            <tr><td colspan="7" style="text-align:center; color:#94a3b8; padding:15px;">Belum ada transaksi sembako pada periode ini.</td></tr>
                        <?php else: ?>
                            <?php while ($row = mysqli_fetch_assoc($q_list_sembako)): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($row['no_faktur']) ?></code></td>
                                    <td><?= date("d/m/Y H:i", strtotime($row['tgl_transaksi'])) ?></td>
                                    <td><strong><?= htmlspecialchars($row['nama_lengkap']) ?></strong></td>
                                    <td>Rp <?= number_format($row['total_bayar'], 0, ',', '.') ?></td>
                                    <td>Rp <?= number_format($row['jumlah_uang'], 0, ',', '.') ?></td>
                                    <td>Rp <?= number_format($row['kembalian'], 0, ',', '.') ?></td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 6px; flex-wrap: nowrap;">
                                            <?php if ($row['status_pembayaran'] == 'lunas'): ?>
                                                <span class="badge-lunas">LUNAS</span>
                                            <?php elseif ($row['status_pembayaran'] == 'dibatalkan'): ?>
                                                <span class="badge-red" style="background:#fee2e2; color:#991b1b; padding:2px 8px; border-radius:12px; font-size:10px; font-weight:700;">DIBATALKAN</span>
                                            <?php else: ?>
                                                <span class="badge-pending"><?= strtoupper($row['status_pembayaran']) ?></span>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="id_transaksi" value="<?= $row['id_transaksi'] ?>">
                                                    <button type="submit" name="konfirmasi_lunas" style="background:#16a34a; color:white; border:none; padding:4px 8px; border-radius:6px; font-size:10px; font-weight:700; cursor:pointer; white-space:nowrap; transition:0.2s;" onclick="return confirm('Konfirmasi pesanan <?= htmlspecialchars($row['no_faktur']) ?> sebagai LUNAS?')">
                                                        ✅ Konfirmasi
                                                    </button>
                                                </form>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="id_transaksi" value="<?= $row['id_transaksi'] ?>">
                                                    <button type="submit" name="batalkan_pesanan" style="background:#ef4444; color:white; border:none; padding:4px 8px; border-radius:6px; font-size:10px; font-weight:700; cursor:pointer; white-space:nowrap; transition:0.2s;" onclick="return confirm('Batalkan pesanan <?= htmlspecialchars($row['no_faktur']) ?> dan kembalikan stok?')">
                                                        ❌ Batalkan
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TABEL 2: RIWAYAT TRANSAKSI BNI AGEN46 -->
        <div class="card" style="box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px;">
            <div class="card-title">
                <span>🏦 Riwayat Transaksi Layanan BNI Agen46 & PPOB</span>
                <small style="font-size:11px; color:#64748b;">(Transfer, Setor/Tarik, Token, E-Money)</small>
            </div>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>No Faktur</th>
                            <th>Tanggal</th>
                            <th>Jenis Layanan</th>
                            <th>Provider / Bank</th>
                            <th>ID Tujuan / Rekening</th>
                            <th>Nama Penerima</th>
                            <th>Nominal</th>
                            <th>Biaya Admin</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($q_list_agen46) == 0): ?>
                            <tr><td colspan="9" style="text-align:center; color:#94a3b8; padding:15px;">Belum ada transaksi Agen46 pada periode ini.</td></tr>
                        <?php else: ?>
                            <?php while ($ag = mysqli_fetch_assoc($q_list_agen46)): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($ag['no_faktur']) ?></code></td>
                                    <td><?= date("d/m/Y H:i", strtotime($ag['tgl_transaksi'])) ?></td>
                                    <td><strong><?= strtoupper(str_replace('_', ' ', $ag['jenis_layanan'])) ?></strong></td>
                                    <td><?= htmlspecialchars($ag['bank_tujuan']) ?></td>
                                    <td><?= htmlspecialchars($ag['no_rekening_tujuan']) ?></td>
                                    <td><?= htmlspecialchars($ag['nama_penerima']) ?></td>
                                    <td>Rp <?= number_format($ag['nominal_transaksi'], 0, ',', '.') ?></td>
                                    <td style="color:#f26522; font-weight:bold;">Rp <?= number_format($ag['biaya_admin'], 0, ',', '.') ?></td>
                                    <td><strong>Rp <?= number_format($ag['total_transaksi'], 0, ',', '.') ?></strong></td>
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
