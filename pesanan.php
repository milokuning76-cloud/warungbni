<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$pesan_sukses = '';
$pesan_error  = '';

if (isset($_POST['setujui_pesanan'])) {
    $id_transaksi = intval($_POST['id_transaksi']);
    $q_update = mysqli_query($koneksi, "UPDATE transaksi SET status_pembayaran = 'disetujui' WHERE id_transaksi = '$id_transaksi'");
    if ($q_update) {
        $pesan_sukses = "Pesanan berhasil DISETUJUI & siap diproses!";
    } else {
        $pesan_error = "Gagal menyetujui pesanan!";
    }
}

if (isset($_POST['konfirmasi_lunas'])) {
    $id_transaksi = intval($_POST['id_transaksi']);
    $q_update = mysqli_query($koneksi, "UPDATE transaksi SET status_pembayaran = 'lunas' WHERE id_transaksi = '$id_transaksi'");
    if ($q_update) {
        $pesan_sukses = "Pesanan berhasil dikonfirmasi sebagai LUNAS & Selesai!";
    } else {
        $pesan_error = "Gagal memperbarui status pesanan!";
    }
}

if (isset($_POST['batalkan_pesanan'])) {
    $id_transaksi = intval($_POST['id_transaksi']);
    
    $q_items = mysqli_query($koneksi, "SELECT * FROM detail_transaksi WHERE id_transaksi = '$id_transaksi'");
    while ($item = mysqli_fetch_assoc($q_items)) {
        $id_p = $item['id_produk'];
        $qty  = $item['jumlah'];
        mysqli_query($koneksi, "UPDATE produk SET stok = stok + $qty WHERE id_produk = '$id_p'");
    }

    $q_batal = mysqli_query($koneksi, "UPDATE transaksi SET status_pembayaran = 'dibatalkan' WHERE id_transaksi = '$id_transaksi'");
    if ($q_batal) {
        $pesan_sukses = "Pesanan berhasil dibatalkan dan stok produk telah dikembalikan!";
    } else {
        $pesan_error = "Gagal membatalkan pesanan!";
    }
}

$filter_status = isset($_GET['status']) ? $_GET['status'] : 'pending';
$sql_filter = "";
if ($filter_status != 'semua') {
    $sql_filter = "WHERE t.status_pembayaran = '$filter_status'";
}

$q_orders = mysqli_query($koneksi, "
    SELECT t.*, u.nama_lengkap, u.no_hp 
    FROM transaksi t
    JOIN users u ON t.id_user = u.id_user
    $sql_filter AND t.jenis_transaksi = 'sembako'
    ORDER BY t.tgl_transaksi DESC
");

$c_pending   = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM transaksi WHERE status_pembayaran = 'pending' AND jenis_transaksi = 'sembako'"))['total'];
$c_disetujui = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM transaksi WHERE status_pembayaran = 'disetujui' AND jenis_transaksi = 'sembako'"))['total'];
$c_lunas     = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM transaksi WHERE status_pembayaran = 'lunas' AND jenis_transaksi = 'sembako'"))['total'];
$c_dibatalkan= mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM transaksi WHERE status_pembayaran = 'dibatalkan' AND jenis_transaksi = 'sembako'"))['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Konfirmasi Pesanan Online - BNI Agen46</title>
    <link rel="stylesheet" href="assets/style/style.css">
</head>
<body>

    <header>
        <div class="logo">BNI <span>Agen46</span> - Panel Konfirmasi Pesanan</div>
        <a href="index.php" style="color:white; text-decoration:none; font-size:12px; font-weight:600;">← Beranda Juragan</a>
    </header>

    <div class="container">

        <?php if ($pesan_sukses): ?><div class="alert-success"><?= $pesan_sukses ?></div><?php endif; ?>
        <?php if ($pesan_error): ?><div class="alert-error"><?= $pesan_error ?></div><?php endif; ?>

        <div class="filter-bar">
            <a href="pesanan.php?status=pending" class="filter-btn <?= ($filter_status == 'pending') ? 'active' : '' ?>">
                ⏳ Pending
                <span class="badge amber"><?= $c_pending ?></span>
            </a>
            <a href="pesanan.php?status=disetujui" class="filter-btn <?= ($filter_status == 'disetujui') ? 'active' : '' ?>">
                👍 Disetujui
                <span class="badge blue"><?= $c_disetujui ?></span>
            </a>
            <a href="pesanan.php?status=lunas" class="filter-btn <?= ($filter_status == 'lunas') ? 'active' : '' ?>">
                ✅ Lunas
                <span class="badge green"><?= $c_lunas ?></span>
            </a>
            <a href="pesanan.php?status=dibatalkan" class="filter-btn <?= ($filter_status == 'dibatalkan') ? 'active' : '' ?>">
                ❌ Dibatalkan
                <span class="badge red"><?= $c_dibatalkan ?></span>
            </a>
            <a href="pesanan.php?status=semua" class="filter-btn <?= ($filter_status == 'semua') ? 'active' : '' ?>">
                📑 Semua Pesanan
            </a>
        </div>

        <?php if (mysqli_num_rows($q_orders) == 0): ?>
            <div style="background:white; border-radius:16px; padding:40px; text-align:center; color:#94a3b8; font-size:13px;">
                Belum ada pesanan online dengan status ini.
            </div>
        <?php else: ?>
            <?php while ($ord = mysqli_fetch_assoc($q_orders)): 
                $id_tx = $ord['id_transaksi'];
                $q_detail = mysqli_query($koneksi, "
                    SELECT d.*, p.nama_produk, p.satuan 
                    FROM detail_transaksi d
                    JOIN produk p ON d.id_produk = p.id_produk
                    WHERE d.id_transaksi = '$id_tx'
                ");
            ?>
                <div class="order-card <?= strtolower($ord['status_pembayaran']) ?>">
                    <div class="order-header">
                        <div>
                            <span class="order-faktur"><?= $ord['no_faktur'] ?></span>
                            <span class="order-date"> • <?= date("d M Y H:i", strtotime($ord['tgl_transaksi'])) ?></span>
                        </div>
                        <div>
                            <?php if ($ord['status_pembayaran'] == 'pending'): ?>
                                <span class="badge amber" style="font-size:11px; padding:4px 10px;">MENUNGGU KONFIRMASI</span>
                            <?php elseif ($ord['status_pembayaran'] == 'disetujui'): ?>
                                <span class="badge blue" style="font-size:11px; padding:4px 10px;">DISETUJUI / DIPROSES</span>
                            <?php elseif ($ord['status_pembayaran'] == 'lunas'): ?>
                                <span class="badge green" style="font-size:11px; padding:4px 10px;">LUNAS / SELESAI</span>
                            <?php else: ?>
                                <span class="badge red" style="font-size:11px; padding:4px 10px;">DIBATALKAN</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="order-body">
                        <div class="info-group">
                            <label>Nama Pemesan & Kontak</label>
                            <p>👤 <?= htmlspecialchars($ord['nama_lengkap']) ?> (<?= htmlspecialchars($ord['no_hp']) ?>)</p>

                            <label>Total Pembayaran</label>
                            <p style="font-size:15px; font-weight:800; color:#005e6a;">
                                Rp <?= number_format($ord['total_bayar'], 0, ',', '.') ?>
                            </p>
                        </div>

                        <div>
                            <label style="font-size:10px; font-weight:700; color:#94a3b8; text-transform:uppercase; display:block; margin-bottom:2px;">Item Yang Dibeli</label>
                            <div class="item-list">
                                <?php while ($item = mysqli_fetch_assoc($q_detail)): ?>
                                    <div class="item-row">
                                        <span><?= htmlspecialchars($item['nama_produk']) ?> x <?= $item['jumlah'] ?> <?= $item['satuan'] ?></span>
                                        <strong>Rp <?= number_format($item['subtotal'], 0, ',', '.') ?></strong>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>

                        <div class="action-box">
                            <?php if ($ord['status_pembayaran'] == 'pending'): ?>
                                <form method="POST">
                                    <input type="hidden" name="id_transaksi" value="<?= $id_tx ?>">
                                    <button type="submit" name="setujui_pesanan" class="btn-act btn-approve" onclick="return confirm('Setujui pesanan ini untuk segera diproses?')">
                                        👍 Setujui Pesanan
                                    </button>
                                </form>
                                <form method="POST">
                                    <input type="hidden" name="id_transaksi" value="<?= $id_tx ?>">
                                    <button type="submit" name="konfirmasi_lunas" class="btn-act btn-confirm" onclick="return confirm('Konfirmasi pembayaran LUNAS?')">
                                        ✅ Setujui & Lunas
                                    </button>
                                </form>
                                <form method="POST">
                                    <input type="hidden" name="id_transaksi" value="<?= $id_tx ?>">
                                    <button type="submit" name="batalkan_pesanan" class="btn-act btn-cancel" onclick="return confirm('Tolak/Batalkan pesanan dan kembalikan stok?')">
                                        ❌ Batalkan Pesanan
                                    </button>
                                </form>
                            <?php elseif ($ord['status_pembayaran'] == 'disetujui'): ?>
                                <form method="POST">
                                    <input type="hidden" name="id_transaksi" value="<?= $id_tx ?>">
                                    <button type="submit" name="konfirmasi_lunas" class="btn-act btn-confirm" onclick="return confirm('Selesaikan & tandai sebagai LUNAS?')">
                                        ✅ Tandai Lunas & Selesai
                                    </button>
                                </form>
                                <form method="POST">
                                    <input type="hidden" name="id_transaksi" value="<?= $id_tx ?>">
                                    <button type="submit" name="batalkan_pesanan" class="btn-act btn-cancel" onclick="return confirm('Batalkan pesanan ini?')">
                                        ❌ Batalkan Pesanan
                                    </button>
                                </form>
                            <?php else: ?>
                                <div style="font-size:11px; color:#64748b; text-align:center;">
                                    Status transaksi selesai.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>

    </div>

</body>
</html>
