<?php
session_start();
require_once 'config.php';

// Hitung total item yang ada di keranjang belanja
$total_item_keranjang = 0;
if (isset($_SESSION['keranjang']) && is_array($_SESSION['keranjang'])) {
    foreach ($_SESSION['keranjang'] as $item) {
        $total_item_keranjang += $item['qty'];
    }
}

// DATA STATISTIK RINGKAS UNTUK BAR LAPORAN & FITUR JURAGAN
$omzet_hari_ini     = 0;
$admin_hari_ini     = 0;
$stok_menipis_count = 0;
$total_tx_hari_ini  = 0;
$order_pending_count= 0;

if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
    $tgl_today = date('Y-m-d');

    // 1. Omzet Sembako Hari Ini
    $q_omzet = mysqli_query($koneksi, "SELECT SUM(total_bayar) AS total FROM transaksi WHERE jenis_transaksi = 'sembako' AND status_pembayaran = 'lunas' AND DATE(tgl_transaksi) = '$tgl_today'");
    if ($q_omzet) {
        $d_omzet = mysqli_fetch_assoc($q_omzet);
        $omzet_hari_ini = floatval($d_omzet['total'] ?? 0);
    }

    // 2. Admin Agen46 Hari Ini
    $q_admin_a46 = mysqli_query($koneksi, "SELECT SUM(a.biaya_admin) AS total_admin FROM transaksi t JOIN transaksi_agen46 a ON t.id_transaksi = a.id_transaksi WHERE DATE(t.tgl_transaksi) = '$tgl_today'");
    if ($q_admin_a46) {
        $d_admin = mysqli_fetch_assoc($q_admin_a46);
        $admin_hari_ini = floatval($d_admin['total_admin'] ?? 0);
    }

    // 3. Stok Menipis
    $q_stok = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM produk WHERE stok <= stok_minimum");
    if ($q_stok) {
        $d_stok = mysqli_fetch_assoc($q_stok);
        $stok_menipis_count = intval($d_stok['total'] ?? 0);
    }

    // 4. Total Transaksi Hari Ini
    $q_tx_count = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM transaksi WHERE DATE(tgl_transaksi) = '$tgl_today'");
    if ($q_tx_count) {
        $d_tx = mysqli_fetch_assoc($q_tx_count);
        $total_tx_hari_ini = intval($d_tx['total'] ?? 0);
    }

    // 5. Order Pending
    $q_pending = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM transaksi WHERE status_pembayaran = 'pending'");
    if ($q_pending) {
        $d_pending = mysqli_fetch_assoc($q_pending);
        $order_pending_count = intval($d_pending['total'] ?? 0);
    }
}

// FILTER PENCARIAN & KATEGORI PRODUK
$search_q    = isset($_GET['q']) ? mysqli_real_escape_string($koneksi, trim($_GET['q'])) : '';
$kategori_id = isset($_GET['kategori']) ? intval($_GET['kategori']) : 0;

$where_clauses = [];
if (!empty($search_q)) {
    $where_clauses[] = "(p.nama_produk LIKE '%$search_q%' OR p.barcode LIKE '%$search_q%')";
}
if ($kategori_id > 0) {
    $where_clauses[] = "p.id_kategori = '$kategori_id'";
}

$sql_where = "";
if (count($where_clauses) > 0) {
    $sql_where = "WHERE " . implode(" AND ", $where_clauses);
}

// Ambil data produk sembako dari database MySQL
$query_produk = mysqli_query($koneksi, "SELECT p.*, k.nama_kategori FROM produk p JOIN kategori k ON p.id_kategori = k.id_kategori $sql_where ORDER BY p.id_produk ASC");

// Ambil daftar kategori untuk filter pills
$query_kat = mysqli_query($koneksi, "SELECT * FROM kategori ORDER BY id_kategori ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BNI Agen46 - Toko Sembako & Layanan Perbankan</title>
    <link rel="stylesheet" href="assets/style/style.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .search-box {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
            width: 100%;
        }
        .search-input {
            flex: 1;
            padding: 10px 14px;
            border: 1.5px solid #cbd5e1;
            border-radius: 10px;
            font-size: 13px;
            outline: none;
            transition: all 0.2s;
        }
        .search-input:focus {
            border-color: #005e6a;
            box-shadow: 0 0 0 3px rgba(0, 94, 106, 0.15);
        }
        .btn-search {
            background: #005e6a;
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-search:hover { background: #004d5a; }
        
        .category-pills {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 8px;
            margin-bottom: 15px;
            scrollbar-width: thin;
        }
        .pill-item {
            text-decoration: none;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            color: #475569;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            white-space: nowrap;
            transition: all 0.2s;
        }
        .pill-item.active, .pill-item:hover {
            background: #005e6a;
            color: #ffffff;
            border-color: #005e6a;
        }
    </style>
</head>
<body>

    <header>
        <div class="header-top">
            <div>
                <div class="brand-logo">BNI <span>Agen46</span></div>
                <div class="brand-sub">Solusi Keuangan & Toko Sembako Terpadu</div>
            </div>

            <div>
                <?php if(isset($_SESSION['id_user'])): ?>
                    <span class="user-badge">
                        <?= ($_SESSION['role'] == 'admin') ? '👨‍💼 Juragan' : '🛒 Pembeli'; ?>
                    </span>
                    <a href="logout.php" class="btn-auth btn-logout">Keluar</a>
                <?php else: ?>
                    <a href="login.php" class="btn-auth btn-login">Masuk</a>
                    <a href="register.php" class="btn-auth btn-register">Daftar</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="main-container">
        <!-- Status Pengguna -->
        <div class="card-info">
            <div>
                <div class="info-title">Status Pengguna</div>
                <div class="info-status">
                    <?= isset($_SESSION['nama_lengkap']) ? htmlspecialchars($_SESSION['nama_lengkap']) : 'Pelanggan Tamu'; ?>
                </div>
            </div>
            <span class="badge-role">
                <?= (isset($_SESSION['role']) && $_SESSION['role'] == 'admin') ? 'Akses Pengelola (Juragan)' : 'Layanan Aktif'; ?>
            </span>
        </div>

        <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
            <!-- 1. PANEL QUICK REPORT & ANALITIK JURAGAN -->
            <div class="juragan-bar">
                <div class="juragan-bar-header">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 18px;">📊</span>
                        <div>
                            <div style="font-size: 13px; font-weight: 800; color: #005e6a;">Panel Quick Report & Analitik Juragan</div>
                            <div style="font-size: 10px; color: #64748b;">Ringkasan performa toko & transaksi hari ini (<?= date('d M Y') ?>)</div>
                        </div>
                    </div>
                    <a href="laporan.php" class="btn-laporan-full">📈 Laporan Lengkap →</a>
                </div>

                <!-- Metrics Grid -->
                <div class="juragan-metrics">
                    <div class="metric-item">
                        <div class="metric-label">Omzet Sembako</div>
                        <div class="metric-value">Rp <?= number_format($omzet_hari_ini, 0, ',', '.') ?></div>
                    </div>
                    <div class="metric-item orange">
                        <div class="metric-label">Komisi Agen46</div>
                        <div class="metric-value">Rp <?= number_format($admin_hari_ini, 0, ',', '.') ?></div>
                    </div>
                    <div class="metric-item blue">
                        <div class="metric-label">Total Transaksi</div>
                        <div class="metric-value"><?= $total_tx_hari_ini ?> Tx</div>
                    </div>
                    <div class="metric-item amber">
                        <div class="metric-label">Order Pending</div>
                        <div class="metric-value"><?= $order_pending_count ?> Order</div>
                    </div>
                    <div class="metric-item red">
                        <div class="metric-label">Stok Menipis</div>
                        <div class="metric-value"><?= $stok_menipis_count ?> Produk</div>
                    </div>
                </div>

                <!-- Shortcuts Laporan -->
                <div class="report-shortcuts">
                    <a href="laporan.php" class="btn-shortcut">🛒 Laporan Sembako</a>
                    <a href="laporan.php" class="btn-shortcut">🏦 Laporan BNI Agen46</a>
                    <a href="laporan.php" class="btn-shortcut">🖨️ Cetak PDF Laporan</a>
                </div>
            </div>

            <!-- 2. PANEL FITUR & PENGELOLAAN KHUSUS JURAGAN -->
            <div class="juragan-tools-card">
                <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px; margin-bottom: 10px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 18px;">⚙️</span>
                        <div>
                            <div style="font-size: 13px; font-weight: 800; color: #0f172a;">Fitur & Pengelolaan Khusus Juragan</div>
                            <div style="font-size: 10px; color: #64748b;">Akses cepat alat operasional toko & kasir</div>
                        </div>
                    </div>
                </div>

                <div class="tools-grid">
                    <a href="pesanan.php" class="tool-item">
                        <div class="tool-icon">🛍️</div>
                        <div>
                            <div class="tool-title">Order Pending</div>
                            <div class="tool-desc">Konfirmasi pesanan masuk</div>
                        </div>
                        <?php if($order_pending_count > 0): ?>
                            <span class="badge-count amber"><?= $order_pending_count ?></span>
                        <?php endif; ?>
                    </a>

                    <a href="kasir.php" class="tool-item">
                        <div class="tool-icon">💻</div>
                        <div>
                            <div class="tool-title">POS Kasir</div>
                            <div class="tool-desc">Scan Barcode & NFC</div>
                        </div>
                    </a>

                    <a href="produk.php" class="tool-item">
                        <div class="tool-icon">📦</div>
                        <div>
                            <div class="tool-title">Kelola & Restok</div>
                            <div class="tool-desc">Tambah/edit produk</div>
                        </div>
                        <?php if($stok_menipis_count > 0): ?>
                            <span class="badge-count"><?= $stok_menipis_count ?></span>
                        <?php endif; ?>
                    </a>

                    <a href="agen46.php" class="tool-item">
                        <div class="tool-icon">🏦</div>
                        <div>
                            <div class="tool-title">Loket Agen46</div>
                            <div class="tool-desc">Transfer, PLN & PPOB</div>
                        </div>
                    </a>

                    <a href="laporan.php" class="tool-item">
                        <div class="tool-icon">📈</div>
                        <div>
                            <div class="tool-title">Rekap Keuangan</div>
                            <div class="tool-desc">Cetak & ekspor laporan</div>
                        </div>
                    </a>

                    <a href="pembeli.php" class="tool-item">
                        <div class="tool-icon">👥</div>
                        <div>
                            <div class="tool-title">Data Pembeli</div>
                            <div class="tool-desc">Kelola akun pelanggan</div>
                        </div>
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- LAYANAN PERBANKAN & AGEN46 (12 FITUR AKTIF) -->
        <div class="section-header">
            <span>Layanan Perbankan & PPOB</span>
            <span class="tag">BNI Agen46</span>
        </div>

        <div class="service-grid">
            <a href="agen46.php?layanan=buka_rekening" class="service-item">
                <div class="icon-box bg-teal">💳</div>
                <div class="service-title">Buka Rekening</div>
            </a>
            <a href="agen46.php?layanan=setor_tunai" class="service-item">
                <div class="icon-box bg-green">💵</div>
                <div class="service-title">Setor Tunai</div>
            </a>
            <a href="agen46.php?layanan=tarik_tunai" class="service-item">
                <div class="icon-box bg-orange">🏧</div>
                <div class="service-title">Tarik Tunai</div>
            </a>
            <a href="agen46.php?layanan=transfer" class="service-item">
                <div class="icon-box bg-blue">🔄</div>
                <div class="service-title">Transfer Bank</div>
            </a>
            <a href="agen46.php?layanan=pulsa_pdam" class="service-item">
                <div class="icon-box bg-purple">📱</div>
                <div class="service-title">Pulsa Data</div>
            </a>
            <a href="agen46.php?layanan=token_pln" class="service-item">
                <div class="icon-box bg-yellow">⚡</div>
                <div class="service-title">Token PLN</div>
            </a>
            <a href="agen46.php?layanan=tagihan_pln" class="service-item">
                <div class="icon-box bg-orange">💡</div>
                <div class="service-title">Tagihan PLN</div>
            </a>
            <a href="agen46.php?layanan=telepon_telkom" class="service-item">
                <div class="icon-box bg-red">📞</div>
                <div class="service-title">Telepon / Telkom</div>
            </a>
            <a href="agen46.php?layanan=emoney" class="service-item">
                <div class="icon-box bg-blue">👛</div>
                <div class="service-title">Top Up E-Money</div>
            </a>
            <a href="agen46.php?layanan=kur" class="service-item">
                <div class="icon-box bg-teal">🏢</div>
                <div class="service-title">Kredit KUR</div>
            </a>
            <a href="agen46.php?layanan=pkh" class="service-item">
                <div class="icon-box bg-purple">🤝</div>
                <div class="service-title">Bansos PKH</div>
            </a>
            <a href="agen46.php?layanan=bpnt" class="service-item">
                <div class="icon-box bg-green">🌾</div>
                <div class="service-title">Bansos BPNT</div>
            </a>
        </div>

        <div class="promo-banner">
            <div class="promo-text">
                <h4>Pengajuan Kredit Usaha Rakyat (KUR) BNI</h4>
                <p>Modal usaha tambahan hingga Rp 50 Juta dengan bunga ringan.</p>
            </div>
            <a href="agen46.php?layanan=kur" class="btn-promo">Ajukan</a>
        </div>

        <!-- KATALOG SEMBAKO MURAH (DENGAN SEARCH & FILTER KATEGORI) -->
        <div class="section-header">
            <span>Katalog Sembako Murah</span>
            <div style="display: flex; gap: 8px; align-items: center;">
                <span class="tag">Stok Toko</span>
                <a href="keranjang.php" class="btn-cart-header">
                    🛒 Lihat Keranjang (<?= $total_item_keranjang ?>)
                </a>
            </div>
        </div>

        <!-- Form Pencarian Produk -->
        <form method="GET" class="search-box">
            <?php if ($kategori_id > 0): ?>
                <input type="hidden" name="kategori" value="<?= $kategori_id ?>">
            <?php endif; ?>
            <input type="text" name="q" value="<?= htmlspecialchars($search_q) ?>" class="search-input" placeholder="🔍 Cari nama produk atau scan barcode...">
            <button type="submit" class="btn-search">Cari</button>
            <?php if (!empty($search_q) || $kategori_id > 0): ?>
                <a href="index.php" class="btn-search" style="background:#64748b; text-decoration:none; display:inline-flex; align-items:center;">Reset</a>
            <?php endif; ?>
        </form>

        <!-- Category Pills Filter -->
        <div class="category-pills">
            <a href="index.php<?= !empty($search_q) ? '?q='.urlencode($search_q) : '' ?>" class="pill-item <?= ($kategori_id == 0) ? 'active' : '' ?>">
                Semua Produk
            </a>
            <?php if ($query_kat && mysqli_num_rows($query_kat) > 0): ?>
                <?php while($kat = mysqli_fetch_assoc($query_kat)): ?>
                    <a href="index.php?kategori=<?= $kat['id_kategori'] ?><?= !empty($search_q) ? '&q='.urlencode($search_q) : '' ?>" class="pill-item <?= ($kategori_id == $kat['id_kategori']) ? 'active' : '' ?>">
                        <?= htmlspecialchars($kat['nama_kategori']) ?>
                    </a>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

        <!-- Grid Produk -->
        <div class="grid-sembako">
            <?php if (!$query_produk || mysqli_num_rows($query_produk) == 0): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 30px; background: white; border-radius: 16px; color: #64748b;">
                    📦 Tidak ada produk ditemukan<?= !empty($search_q) ? ' untuk kata kunci "<strong>'.htmlspecialchars($search_q).'</strong>"' : '' ?>.
                    <br><a href="index.php" style="color:#005e6a; font-weight:bold; display:inline-block; margin-top:8px;">Tampilkan Semua Produk</a>
                </div>
            <?php else: ?>
                <?php while($p = mysqli_fetch_assoc($query_produk)): ?>
                    <div class="card-sembako">
                        <div>
                            <div class="img-sembako">
                                <?php 
                                    $img_file = !empty($p['gambar']) ? $p['gambar'] : 'default.jpg';
                                    $img_path = "assets/img/" . $img_file;
                                    
                                    // Fallback emoji
                                    $nama = strtolower($p['nama_produk']);
                                    $emoji_fallback = '📦';
                                    if (strpos($nama, 'beras') !== false) $emoji_fallback = '🌾';
                                    elseif (strpos($nama, 'minyak') !== false) $emoji_fallback = '🧴';
                                    elseif (strpos($nama, 'gula') !== false) $emoji_fallback = '🍬';
                                    elseif (strpos($nama, 'telur') !== false) $emoji_fallback = '🥚';
                                    elseif (strpos($nama, 'mie') !== false || strpos($nama, 'indomie') !== false) $emoji_fallback = '🍜';
                                    elseif (strpos($nama, 'kopi') !== false || strpos($nama, 'teh') !== false) $emoji_fallback = '☕';
                                    elseif (strpos($nama, 'sabun') !== false || strpos($nama, 'rinso') !== false) $emoji_fallback = '🧼';
                                    elseif (strpos($nama, 'gas') !== false || strpos($nama, 'lpg') !== false) $emoji_fallback = '🔥';
                                ?>
                                <img src="<?= $img_path ?>" alt="<?= htmlspecialchars($p['nama_produk']) ?>" style="width:100%; height:100%; object-fit:cover; border-radius:10px;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <span style="display:none; width:100%; height:100%; align-items:center; justify-content:center; font-size:36px;"><?= $emoji_fallback ?></span>
                            </div>
                            <span class="kat-badge"><?= htmlspecialchars($p['nama_kategori']) ?></span>
                            <div class="title-sembako"><?= htmlspecialchars($p['nama_produk']) ?></div>
                            <div class="price-sembako">Rp <?= number_format($p['harga_jual'], 0, ',', '.') ?></div>
                            <div class="stok-sembako">Tersedia: <strong><?= $p['stok'] ?></strong> <?= $p['satuan'] ?></div>
                        </div>

                        <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
                            <a href="produk.php" class="btn-admin-act">✏️ Edit Stok</a>
                        <?php else: ?>
                            <a href="keranjang.php?action=add&id=<?= $p['id_produk'] ?>" class="btn-buy">🛒 + Beli Sembako</a>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bottom Navigation Bar Mobile -->
    <nav class="bottom-nav">
        <a href="index.php" class="nav-link active">
            <span class="nav-icon">🏠</span>
            <span>Beranda</span>
        </a>
        <a href="agen46.php" class="nav-link">
            <span class="nav-icon">💸</span>
            <span>Finansial</span>
        </a>
        <a href="keranjang.php" class="nav-link">
            <span class="nav-icon">🛒</span>
            <span>Keranjang (<?= $total_item_keranjang ?>)</span>
        </a>
        <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
            <a href="kasir.php" class="nav-link" style="color: #f26522;">
                <span class="nav-icon">📊</span>
                <span>POS Kasir</span>
            </a>
            <a href="laporan.php" class="nav-link">
                <span class="nav-icon">📈</span>
                <span>Laporan</span>
            </a>
        <?php endif; ?>
        <a href="<?= isset($_SESSION['id_user']) ? 'logout.php' : 'login.php' ?>" class="nav-link">
            <span class="nav-icon">👤</span>
            <span><?= isset($_SESSION['id_user']) ? 'Akun' : 'Masuk' ?></span>
        </a>
    </nav>

</body>
</html>
