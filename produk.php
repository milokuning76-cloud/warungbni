<?php
session_start();
require_once 'config.php';

// Keamanan: Hanya Juragan (Admin) yang boleh mengakses halaman ini
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$pesan_sukses = '';
$pesan_error = '';

// 1. TAMBAH PRODUK BARU
if (isset($_POST['tambah_produk'])) {
    $barcode      = mysqli_real_escape_string($koneksi, $_POST['barcode']);
    $nama_produk  = mysqli_real_escape_string($koneksi, $_POST['nama_produk']);
    $id_kategori  = intval($_POST['id_kategori']);
    $harga_beli   = floatval($_POST['harga_beli']);
    $harga_jual   = floatval($_POST['harga_jual']);
    $stok         = intval($_POST['stok']);
    $stok_minimum = intval($_POST['stok_minimum']);
    $satuan       = mysqli_real_escape_string($koneksi, $_POST['satuan']);

    // Cek apakah barcode sudah pernah dipakai
    $cek_barcode = mysqli_query($koneksi, "SELECT * FROM produk WHERE barcode='$barcode'");
    if (mysqli_num_rows($cek_barcode) > 0) {
        $pesan_error = "Kode Barcode sudah terdaftar untuk produk lain!";
    } else {
        $query = "INSERT INTO produk (barcode, nama_produk, id_kategori, harga_beli, harga_jual, stok, stok_minimum, satuan)
                  VALUES ('$barcode', '$nama_produk', '$id_kategori', '$harga_beli', '$harga_jual', '$stok', '$stok_minimum', '$satuan')";
        if (mysqli_query($koneksi, $query)) {
            $pesan_sukses = "Produk baru berhasil ditambahkan!";
        } else {
            $pesan_error = "Gagal menambah produk!";
        }
    }
}

// 2. UPDATE STOK PRODUK
if (isset($_POST['update_stok'])) {
    $id_produk = intval($_POST['id_produk']);
    $stok_baru = intval($_POST['stok_baru']);

    $q_update = "UPDATE produk SET stok='$stok_baru' WHERE id_produk='$id_produk'";
    if (mysqli_query($koneksi, $q_update)) {
        $pesan_sukses = "Stok produk berhasil diperbarui!";
    } else {
        $pesan_error = "Gagal memperbarui stok!";
    }
}

// 3. HAPUS PRODUK
if (isset($_GET['hapus'])) {
    $id_hapus = intval($_GET['hapus']);
    mysqli_query($koneksi, "DELETE FROM produk WHERE id_produk='$id_hapus'");
    header("Location: produk.php?status=terhapus");
    exit();
}

// Ambil Data Kategori
$query_kat = mysqli_query($koneksi, "SELECT * FROM kategori");

// Ambil Notifikasi Stok Menipis (Stok <= Stok Minimum)
$query_stok_low = mysqli_query($koneksi, "SELECT p.*, k.nama_kategori FROM produk p JOIN kategori k ON p.id_kategori = k.id_kategori WHERE p.stok <= p.stok_minimum");

// Ambil Semua Data Produk
$query_all_produk = mysqli_query($koneksi, "SELECT p.*, k.nama_kategori FROM produk p JOIN kategori k ON p.id_kategori = k.id_kategori ORDER BY p.id_produk DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Produk & Stok - BNI Agen46</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        body {
            background-color: #f0f4f7;
            color: #1e293b;
            padding-bottom: 40px;
        }

        header {
            background: linear-gradient(135deg, #004d5a, #007a8c);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 18px;
            font-weight: bold;
        }

        .logo span {
            color: #f26522;
        }

        .container {
            max-width: 1100px;
            margin: 20px auto;
            padding: 0 15px;
        }

        .card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        .card-title {
            font-size: 16px;
            font-weight: 700;
            color: #005e6a;
            margin-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 8px;
        }

        /* Banner Stok Menipis */
        .alert-low-stock {
            background: #fff3cd;
            border-left: 5px solid #ffc107;
            color: #856404;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .alert-low-stock strong {
            color: #d39e00;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 12px;
            margin-bottom: 15px;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 12px;
            margin-bottom: 15px;
        }

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }

        @media (min-width: 640px) {
            .form-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 11px;
            font-weight: 700;
            color: #475569;
            margin-bottom: 4px;
        }

        .form-control {
            padding: 8px 10px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 12px;
        }

        .btn-submit {
            background: #005e6a;
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 10px;
        }

        .btn-submit:hover {
            background: #004d5a;
        }

        /* Tabel Data */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-top: 10px;
        }

        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background: #f8fafc;
            color: #334155;
            font-weight: 700;
        }

        .badge-warning {
            background: #fee2e2;
            color: #991b1b;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 700;
        }

        .badge-safe {
            background: #dcfce7;
            color: #166534;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 700;
        }

        .btn-del {
            color: #ef4444;
            text-decoration: none;
            font-weight: 700;
            margin-left: 8px;
        }

        .btn-del:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <header>
        <div class="logo">BNI <span>Agen46</span> - Kelola Produk & Stok</div>
        <a href="index.php" style="color:white; text-decoration:none; font-size:12px; font-weight:600;">← Kembali ke Beranda</a>
    </header>

    <div class="container">

        <?php if ($pesan_sukses): ?><div class="alert-success"><?= $pesan_sukses ?></div><?php endif; ?>
        <?php if ($pesan_error): ?><div class="alert-error"><?= $pesan_error ?></div><?php endif; ?>
        <?php if (isset($_GET['status']) && $_GET['status'] == 'terhapus'): ?>
            <div class="alert-success">Produk berhasil dihapus!</div>
        <?php endif; ?>

        <!-- NOTIFIKASI OTOMATIS STOK MENIPIS -->
        <?php if (mysqli_num_rows($query_stok_low) > 0): ?>
            <div class="alert-low-stock">
                ⚠️ <strong>PERINGATAN STOK MENIPIS:</strong> Ada <strong><?= mysqli_num_rows($query_stok_low) ?> produk</strong> yang stoknya hampir habis! Silakan lakukan re-stok.
                <ul style="margin-top: 6px; padding-left: 20px;">
                    <?php while ($low = mysqli_fetch_assoc($query_stok_low)): ?>
                        <li><strong><?= htmlspecialchars($low['nama_produk']) ?></strong> — Sisa Stok: <span style="color:red; font-weight:bold;"><?= $low['stok'] ?> <?= $low['satuan'] ?></span> (Batas Min: <?= $low['stok_minimum'] ?>)</li>
                    <?php endwhile; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- CARD 1: FORM TAMBAH PRODUK BARU -->
        <div class="card">
            <div class="card-title">➕ Tambah Produk Sembako Baru</div>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Kode Barcode</label>
                        <input type="text" name="barcode" class="form-control" placeholder="Contoh: 8991001005" required>
                    </div>
                    <div class="form-group">
                        <label>Nama Produk</label>
                        <input type="text" name="nama_produk" class="form-control" placeholder="Contoh: Tepung Terigu 1kg" required>
                    </div>
                    <div class="form-group">
                        <label>Kategori</label>
                        <select name="id_kategori" class="form-control" required>
                            <?php
                            mysqli_data_seek($query_kat, 0);
                            while ($k = mysqli_fetch_assoc($query_kat)):
                            ?>
                                <option value="<?= $k['id_kategori'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Harga Beli (Modal Rp)</label>
                        <input type="number" name="harga_beli" class="form-control" placeholder="10000" required>
                    </div>
                    <div class="form-group">
                        <label>Harga Jual (Rp)</label>
                        <input type="number" name="harga_jual" class="form-control" placeholder="12000" required>
                    </div>
                    <div class="form-group">
                        <label>Satuan</label>
                        <select name="satuan" class="form-control" required>
                            <option value="pcs">pcs</option>
                            <option value="kg">kg</option>
                            <option value="sak">sak</option>
                            <option value="pouch">pouch</option>
                            <option value="dus">dus</option>
                            <option value="liter">liter</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Stok Awal</label>
                        <input type="number" name="stok" class="form-control" value="10" required>
                    </div>
                    <div class="form-group">
                        <label>Batas Stok Minimum (Notifikasi)</label>
                        <input type="number" name="stok_minimum" class="form-control" value="5" required>
                    </div>
                </div>
                <button type="submit" name="tambah_produk" class="btn-submit">+ Simpan Produk</button>
            </form>
        </div>

        <!-- CARD 2: DAFTAR SELURUH PRODUK & UPDATE STOK -->
        <div class="card">
            <div class="card-title">📦 Daftar Inventaris Produk Sembako</div>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Barcode</th>
                            <th>Nama Produk</th>
                            <th>Kategori</th>
                            <th>Harga Jual</th>
                            <th>Stok Saat Ini</th>
                            <th>Status Stok</th>
                            <th>Aksi Fast-Update Stok</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($prod = mysqli_fetch_assoc($query_all_produk)): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($prod['barcode']) ?></code></td>
                                <td><strong><?= htmlspecialchars($prod['nama_produk']) ?></strong></td>
                                <td><?= htmlspecialchars($prod['nama_kategori']) ?></td>
                                <td>Rp <?= number_format($prod['harga_jual'], 0, ',', '.') ?></td>
                                <td><strong><?= $prod['stok'] ?></strong> <?= $prod['satuan'] ?></td>
                                <td>
                                    <?php if ($prod['stok'] <= $prod['stok_minimum']): ?>
                                        <span class="badge-warning">⚠️ Menipis</span>
                                    <?php else: ?>
                                        <span class="badge-safe">✅ Aman</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <!-- Form Update Stok Cepat -->
                                    <form method="POST" style="display:inline-flex; gap:4px; align-items:center;">
                                        <input type="hidden" name="id_produk" value="<?= $prod['id_produk'] ?>">
                                        <input type="number" name="stok_baru" value="<?= $prod['stok'] ?>" style="width:60px; padding:4px; border:1px solid #cbd5e1; border-radius:4px; font-size:11px;">
                                        <button type="submit" name="update_stok" style="background:#0284c7; color:white; border:none; padding:4px 8px; border-radius:4px; font-size:10px; cursor:pointer;">Set</button>
                                    </form>
                                    <a href="produk.php?hapus=<?= $prod['id_produk'] ?>" class="btn-del" onclick="return confirm('Yakin ingin menghapus produk ini?')">Hapus</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</body>
</html>