<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit();
}

$pesan_sukses = '';
$pesan_error  = '';
$struk        = null;

$layanan_aktif = isset($_GET['layanan']) ? $_GET['layanan'] : 'transfer';

// Config default tiap layanan BNI Agen46
$config_layanan = [
    'transfer' => [
        'judul' => 'Transfer Bank (Antar Bank / BNI)',
        'icon' => '🔄',
        'admin_default' => 6500,
        'label_id' => 'No. Rekening Tujuan',
        'label_nama' => 'Nama Pemilik Rekening',
        'placeholder_id' => 'Contoh: 0123456789'
    ],
    'setor_tunai' => [
        'judul' => 'Setor Tunai BNI',
        'icon' => '💵',
        'admin_default' => 2500,
        'label_id' => 'No. Rekening BNI Tujuan',
        'label_nama' => 'Nama Pemilik Rekening BNI',
        'placeholder_id' => 'Contoh: 0987654321'
    ],
    'tarik_tunai' => [
        'judul' => 'Tarik Tunai BNI',
        'icon' => '🏧',
        'admin_default' => 5000,
        'label_id' => 'No. Kartu Debit / Rekening BNI',
        'label_nama' => 'Nama Pemilik Rekening',
        'placeholder_id' => 'Contoh: 1234567890'
    ],
    'buka_rekening' => [
        'judul' => 'Buka Rekening BNI Baru',
        'icon' => '💳',
        'admin_default' => 0,
        'label_id' => 'NIK KTP Calon Nasabah',
        'label_nama' => 'Nama Lengkap Nasabah',
        'placeholder_id' => '35xxxxxxxxxxxxxx'
    ],
    'pulsa_pdam' => [
        'judul' => 'Isi Pulsa, Paket Data & Tagihan PDAM',
        'icon' => '📱',
        'admin_default' => 2000,
        'label_id' => 'Nomor HP / ID Pelanggan PDAM',
        'label_nama' => 'Nama Pelanggan / Pemilik Nomor',
        'placeholder_id' => '08xxxxxxxxxx'
    ],
    'token_pln' => [
        'judul' => 'Pembelian Token PLN Listrik Prabayar',
        'icon' => '⚡',
        'admin_default' => 3000,
        'label_id' => 'ID Pelanggan PLN / No. Meter (11-12 digit)',
        'label_nama' => 'Nama Pelanggan PLN',
        'placeholder_id' => 'Contoh: 54123456789'
    ],
    'tagihan_pln' => [
        'judul' => 'Pembayaran Tagihan PLN Pascapayar',
        'icon' => '💡',
        'admin_default' => 3000,
        'label_id' => 'ID Pelanggan PLN Pascapayar',
        'label_nama' => 'Nama Pelanggan PLN',
        'placeholder_id' => 'Contoh: 53123456789'
    ],
    'telepon_telkom' => [
        'judul' => 'Tagihan Telkom / Indihome / Speedy',
        'icon' => '📞',
        'admin_default' => 2500,
        'label_id' => 'ID Pelanggan Indihome / No. Telepon Telkom',
        'label_nama' => 'Nama Pelanggan Indihome',
        'placeholder_id' => 'Contoh: 12234567890'
    ],
    'emoney' => [
        'judul' => 'Top Up E-Money, e-Wallet & QRIS All Payment',
        'icon' => '👛',
        'admin_default' => 2000,
        'label_id' => 'Nomor HP / No. Kartu E-Money',
        'label_nama' => 'Nama Pemilik Akun E-Wallet',
        'placeholder_id' => '08xxxxxxxxxx'
    ],
    'kur' => [
        'judul' => 'Pengajuan Kredit Usaha Rakyat (KUR) BNI',
        'icon' => '🏢',
        'admin_default' => 0,
        'label_id' => 'NIK KTP Pemohon KUR',
        'label_nama' => 'Nama Pemohon / Usaha',
        'placeholder_id' => '35xxxxxxxxxxxxxx'
    ],
    'pkh' => [
        'judul' => 'Pencairan Bansos PKH (Program Keluarga Harapan)',
        'icon' => '🤝',
        'admin_default' => 0,
        'label_id' => 'No. Kartu KKS / NIK KPM PKH',
        'label_nama' => 'Nama Penerima Manfaat PKH',
        'placeholder_id' => '35xxxxxxxxxxxxxx'
    ],
    'bpnt' => [
        'judul' => 'Pencairan Bansos BPNT (Bantuan Pangan Non Tunai)',
        'icon' => '🌾',
        'admin_default' => 0,
        'label_id' => 'No. Kartu Sembako / NIK KPM BPNT',
        'label_nama' => 'Nama Penerima Manfaat BPNT',
        'placeholder_id' => '35xxxxxxxxxxxxxx'
    ],
];

$info_curr = isset($config_layanan[$layanan_aktif]) ? $config_layanan[$layanan_aktif] : $config_layanan['transfer'];

if (isset($_POST['proses_transaksi'])) {
    $jenis_layanan      = mysqli_real_escape_string($koneksi, $_POST['jenis_layanan']);
    $no_rekening_tujuan = mysqli_real_escape_string($koneksi, $_POST['no_rekening_tujuan']);
    $bank_tujuan        = mysqli_real_escape_string($koneksi, $_POST['bank_tujuan']);
    $nama_penerima      = mysqli_real_escape_string($koneksi, $_POST['nama_penerima']);
    $nominal_transaksi  = floatval($_POST['nominal_transaksi']);
    $biaya_admin        = floatval($_POST['biaya_admin']);
    $total_transaksi    = $nominal_transaksi + $biaya_admin;
    $no_faktur          = "AG46-" . date("YmdHis");
    $id_user            = $_SESSION['id_user'];

    if ($nominal_transaksi <= 0 && !in_array($jenis_layanan, ['buka_rekening', 'kur'])) {
        $pesan_error = "Nominal transaksi harus lebih dari Rp 0!";
    } else {
        $q_transaksi = "INSERT INTO transaksi (no_faktur, id_user, jenis_transaksi, total_bayar, jumlah_uang, kembalian, status_pembayaran)
                        VALUES ('$no_faktur', '$id_user', 'agen46', '$total_transaksi', '$total_transaksi', 0, 'lunas')";

        if (mysqli_query($koneksi, $q_transaksi)) {
            $id_transaksi = mysqli_insert_id($koneksi);

            $q_agen46 = "INSERT INTO transaksi_agen46 (id_transaksi, jenis_layanan, no_rekening_tujuan, bank_tujuan, nama_penerima, nominal_transaksi, biaya_admin, total_transaksi)
                         VALUES ('$id_transaksi', '$jenis_layanan', '$no_rekening_tujuan', '$bank_tujuan', '$nama_penerima', '$nominal_transaksi', '$biaya_admin', '$total_transaksi')";

            if (mysqli_query($koneksi, $q_agen46)) {
                $pesan_sukses = "Transaksi BNI Agen46 (" . strtoupper(str_replace('_', ' ', $jenis_layanan)) . ") Berhasil Diproses!";
                $struk = [
                    'no_faktur'   => $no_faktur,
                    'tgl'         => date("d-m-Y H:i:s"),
                    'layanan'     => strtoupper(str_replace('_', ' ', $jenis_layanan)),
                    'tujuan'      => $no_rekening_tujuan,
                    'bank'        => $bank_tujuan,
                    'penerima'    => $nama_penerima,
                    'nominal'     => $nominal_transaksi,
                    'admin'       => $biaya_admin,
                    'total'       => $total_transaksi
                ];
            } else {
                $pesan_error = "Gagal menyimpan detail transaksi Agen46: " . mysqli_error($koneksi);
            }
        } else {
            $pesan_error = "Gagal memproses transaksi utama: " . mysqli_error($koneksi);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loket Transaksi BNI Agen46 & PPOB</title>
    <link rel="stylesheet" href="assets/style/style.css">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background-color: #f8fafc;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
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
            max-width: 1000px;
            margin: 20px auto;
            padding: 0 15px;
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }

        @media (min-width: 768px) {
            .container {
                grid-template-columns: 320px 1fr;
            }
        }

        .card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .card-title {
            font-size: 15px;
            font-weight: 700;
            color: #005e6a;
            margin-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 8px;
        }

        /* Navigation List Layanan */
        .layanan-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .layanan-item {
            text-decoration: none;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
            color: #334155;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .layanan-item:hover, .layanan-item.active {
            background: #005e6a;
            color: white;
            border-color: #005e6a;
        }

        /* Form Controls */
        .form-group {
            margin-bottom: 14px;
        }

        .form-group label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: #475569;
            margin-bottom: 4px;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
            box-sizing: border-box;
        }

        .btn-checkout {
            width: 100%;
            background: #f26522;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-checkout:hover {
            background: #ea580c;
        }

        .preset-btn {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            color: #334155;
            cursor: pointer;
            transition: 0.2s;
        }

        .preset-btn:hover {
            background: #005e6a;
            color: white;
            border-color: #005e6a;
        }

        /* Struk Bukti Transaksi */
        .struk-box {
            background: #fef8f0;
            border: 2px dashed #f26522;
            border-radius: 12px;
            padding: 16px;
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            margin-top: 20px;
        }

        .struk-header {
            text-align: center;
            border-bottom: 1px dashed #cbd5e1;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }

        .struk-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
        }

        .badge-lunas {
            background: #dcfce7;
            color: #15803d;
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: bold;
            font-size: 11px;
        }

        .btn-print {
            background: #0284c7;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }

        .btn-print:hover {
            background: #0369a1;
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
    </style>
</head>
<body>

    <header>
        <div class="logo">BNI <span>Agen46</span> - Loket Transaksi & PPOB</div>
        <a href="index.php" style="color:white; text-decoration:none; font-size:12px; font-weight:600;">← Beranda Utama</a>
    </header>

    <div class="container">

        <!-- Sidebar Layanan -->
        <div class="card">
            <div class="card-title">📌 Pilih Layanan Agen46 (12 Layanan Aktif)</div>
            <div class="layanan-list">
                <a href="agen46.php?layanan=transfer" class="layanan-item <?= ($layanan_aktif == 'transfer') ? 'active' : '' ?>">🔄 Transfer Bank</a>
                <a href="agen46.php?layanan=setor_tunai" class="layanan-item <?= ($layanan_aktif == 'setor_tunai') ? 'active' : '' ?>">💵 Setor Tunai BNI</a>
                <a href="agen46.php?layanan=tarik_tunai" class="layanan-item <?= ($layanan_aktif == 'tarik_tunai') ? 'active' : '' ?>">🏧 Tarik Tunai BNI</a>
                <a href="agen46.php?layanan=buka_rekening" class="layanan-item <?= ($layanan_aktif == 'buka_rekening') ? 'active' : '' ?>">💳 Buka Rekening BNI</a>
                <a href="agen46.php?layanan=pulsa_pdam" class="layanan-item <?= ($layanan_aktif == 'pulsa_pdam') ? 'active' : '' ?>">📱 Pulsa & Paket Data</a>
                <a href="agen46.php?layanan=token_pln" class="layanan-item <?= ($layanan_aktif == 'token_pln') ? 'active' : '' ?>">⚡ Token PLN Listrik</a>
                <a href="agen46.php?layanan=tagihan_pln" class="layanan-item <?= ($layanan_aktif == 'tagihan_pln') ? 'active' : '' ?>">💡 Tagihan PLN Pascapayar</a>
                <a href="agen46.php?layanan=telepon_telkom" class="layanan-item <?= ($layanan_aktif == 'telepon_telkom') ? 'active' : '' ?>">📞 Telepon / Telkom Indihome</a>
                <a href="agen46.php?layanan=emoney" class="layanan-item <?= ($layanan_aktif == 'emoney') ? 'active' : '' ?>">👛 Top Up E-Money / QRIS</a>
                <a href="agen46.php?layanan=kur" class="layanan-item <?= ($layanan_aktif == 'kur') ? 'active' : '' ?>">🏢 Pengajuan KUR BNI</a>
                <a href="agen46.php?layanan=pkh" class="layanan-item <?= ($layanan_aktif == 'pkh') ? 'active' : '' ?>">🤝 Penarikan Bansos PKH</a>
                <a href="agen46.php?layanan=bpnt" class="layanan-item <?= ($layanan_aktif == 'bpnt') ? 'active' : '' ?>">🌾 Penarikan Bansos BPNT</a>
            </div>
        </div>

        <!-- Form Dinamis Sesuai Layanan Aktif -->
        <div>
            <?php if (!empty($pesan_sukses)): ?><div class="alert-success"><?= htmlspecialchars($pesan_sukses) ?></div><?php endif; ?>
            <?php if (!empty($pesan_error)): ?><div class="alert-error"><?= htmlspecialchars($pesan_error) ?></div><?php endif; ?>

            <div class="card">
                <div class="card-title">
                    <span><?= $info_curr['icon'] ?> Formulir: <?= htmlspecialchars($info_curr['judul']) ?></span>
                </div>

                <form method="POST">
                    <input type="hidden" name="jenis_layanan" value="<?= htmlspecialchars($layanan_aktif) ?>">

                    <?php if ($layanan_aktif == 'transfer'): ?>
                        <div class="form-group">
                            <label>Pilih Bank Tujuan</label>
                            <select name="bank_tujuan" class="form-control">
                                <option value="BNI">BNI (Bank Negara Indonesia)</option>
                                <option value="BRI">BRI (Bank Rakyat Indonesia)</option>
                                <option value="Mandiri">Bank Mandiri</option>
                                <option value="BCA">BCA (Bank Central Asia)</option>
                                <option value="BSI">BSI (Bank Syariah Indonesia)</option>
                                <option value="CIMB Niaga">CIMB Niaga</option>
                                <option value="Permata">Permata Bank</option>
                                <option value="Danamon">Bank Danamon</option>
                                <option value="BTN">Bank BTN</option>
                                <option value="Bank Jatim">Bank Jatim / Bank Daerah</option>
                            </select>
                        </div>

                    <?php elseif (in_array($layanan_aktif, ['setor_tunai', 'tarik_tunai'])): ?>
                        <div class="form-group">
                            <label>Bank Operasional</label>
                            <select name="bank_tujuan" class="form-control">
                                <option value="BNI (Bank Negara Indonesia)">BNI (Bank Negara Indonesia)</option>
                                <option value="BNI Syariah / BSI">BNI Syariah / BSI</option>
                            </select>
                        </div>

                    <?php elseif ($layanan_aktif == 'buka_rekening'): ?>
                        <div class="form-group">
                            <label>Pilih Jenis Tabungan BNI</label>
                            <select name="bank_tujuan" class="form-control">
                                <option value="BNI Taplus Utama">BNI Taplus Utama</option>
                                <option value="BNI Taplus Muda (Generasi Z)">BNI Taplus Muda (Generasi Z)</option>
                                <option value="BNI Pandai (Inklusi Keuangan)">BNI Pandai (Inklusi Keuangan)</option>
                                <option value="BNI Taplus Bisnis">BNI Taplus Bisnis</option>
                            </select>
                        </div>

                    <?php elseif ($layanan_aktif == 'pulsa_pdam'): ?>
                        <div class="form-group">
                            <label>Pilih Operator / Provider Layanan</label>
                            <select name="bank_tujuan" class="form-control">
                                <option value="Telkomsel">Telkomsel (Simpati / Loop / By.U)</option>
                                <option value="Indosat Ooredoo">Indosat Ooredoo (IM3)</option>
                                <option value="XL Axiata">XL Axiata</option>
                                <option value="AXIS">AXIS</option>
                                <option value="Tri Indonesia">Tri (3)</option>
                                <option value="Smartfren">Smartfren</option>
                                <option value="PDAM Tirta / BUMD Air">PDAM Tirta / BUMD Daerah</option>
                            </select>
                        </div>

                    <?php elseif ($layanan_aktif == 'telepon_telkom'): ?>
                        <div class="form-group">
                            <label>Layanan Telkom</label>
                            <select name="bank_tujuan" class="form-control">
                                <option value="Telkom Indihome Internet + TV">Telkom Indihome Internet + TV</option>
                                <option value="Telepon Rumah Telkom">Telepon Rumah Telkom</option>
                                <option value="Telkom Speedy / Wifi ID">Telkom Speedy / Wifi ID</option>
                            </select>
                        </div>

                    <?php elseif ($layanan_aktif == 'emoney'): ?>
                        <div class="form-group">
                            <label>Pilih E-Money / QRIS Provider</label>
                            <select name="bank_tujuan" class="form-control">
                                <option value="QRIS All Payment">📲 QRIS All Payment (Semua e-Wallet)</option>
                                <option value="BNI TapCash">💳 BNI TapCash (Kartu E-Money BNI)</option>
                                <option value="GoPay">💚 GoPay</option>
                                <option value="OVO">💜 OVO</option>
                                <option value="DANA">💙 DANA</option>
                                <option value="ShopeePay">🧡 ShopeePay</option>
                                <option value="LinkAja">🔴 LinkAja</option>
                                <option value="AstraPay">🔹 AstraPay</option>
                                <option value="iSAKU">💛 iSAKU Indomaret</option>
                                <option value="Maxim Driver/Customer">🚕 Maxim Driver / Customer</option>
                            </select>
                        </div>

                    <?php elseif ($layanan_aktif == 'kur'): ?>
                        <div class="form-group">
                            <label>Pilih Jenis Kredit KUR BNI</label>
                            <select name="bank_tujuan" class="form-control">
                                <option value="KUR Mikro BNI (s.d. Rp 50 Juta)">KUR Mikro BNI (Plafond s.d. Rp 50 Juta)</option>
                                <option value="KUR Kecil BNI (Rp 50jt - 500jt)">KUR Kecil BNI (Plafond Rp 50jt - 500jt)</option>
                                <option value="KUR Sektor Sembako / Pertanian">KUR Sektor Sembako / Pertanian</option>
                            </select>
                        </div>

                    <?php elseif (in_array($layanan_aktif, ['pkh', 'bpnt'])): ?>
                        <div class="form-group">
                            <label>Program Kementrian Sosial</label>
                            <select name="bank_tujuan" class="form-control">
                                <option value="Bansos Kemsos - BNI Agen46">Bansos Kemsos - BNI Agen46 Official</option>
                            </select>
                        </div>

                    <?php else: ?>
                        <input type="hidden" name="bank_tujuan" value="BNI Agen46 Official">
                    <?php endif; ?>

                    <div class="form-group">
                        <label><?= $info_curr['label_id'] ?> *</label>
                        <input type="text" name="no_rekening_tujuan" class="form-control" placeholder="<?= $info_curr['placeholder_id'] ?>" required>
                    </div>

                    <div class="form-group">
                        <label><?= $info_curr['label_nama'] ?> *</label>
                        <input type="text" name="nama_penerima" class="form-control" placeholder="Masukkan nama penerima/pelanggan" required>
                    </div>

                    <div class="form-group">
                        <label>Nominal Transaksi (Rp) *</label>
                        <input type="number" name="nominal_transaksi" id="inputNominal" class="form-control" placeholder="0" required>

                        <?php if (in_array($layanan_aktif, ['token_pln', 'pulsa_pdam', 'emoney'])): ?>
                            <div style="display:flex; gap:6px; flex-wrap:wrap; margin-top:8px;">
                                <span style="font-size:11px; font-weight:700; color:#64748b; width:100%;">Pilihan Cepat Nominal:</span>
                                <button type="button" class="preset-btn" onclick="setNominal(20000)">20rb</button>
                                <button type="button" class="preset-btn" onclick="setNominal(50000)">50rb</button>
                                <button type="button" class="preset-btn" onclick="setNominal(100000)">100rb</button>
                                <button type="button" class="preset-btn" onclick="setNominal(200000)">200rb</button>
                                <button type="button" class="preset-btn" onclick="setNominal(500000)">500rb</button>
                                <button type="button" class="preset-btn" onclick="setNominal(1000000)">1 Juta</button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label>Biaya Admin Agen46 (Rp) *</label>
                        <input type="number" name="biaya_admin" class="form-control" value="<?= $info_curr['admin_default'] ?>" required>
                        <small style="font-size:10px; color:#64748b;">* Bebas disesuaikan atau diubah oleh pengelola loket Agen46.</small>
                    </div>

                    <button type="submit" name="proses_transaksi" class="btn-checkout" style="background:#005e6a;">
                        🚀 Proses Transaksi <?= strtoupper(str_replace('_', ' ', $layanan_aktif)) ?>
                    </button>
                </form>

                <?php if ($struk): ?>
                    <div class="struk-box">
                        <div class="struk-header">
                            <strong>STRUK BUKTI TRANSAKSI BNI AGEN46</strong><br>
                            <small>Toko Sembako & Agen Resmi BNI Agen46</small>
                        </div>
                        <div class="struk-row"><span>No Faktur:</span> <strong><?= $struk['no_faktur'] ?></strong></div>
                        <div class="struk-row"><span>Waktu:</span> <span><?= $struk['tgl'] ?></span></div>
                        <div class="struk-row"><span>Jenis Layanan:</span> <strong><?= $struk['layanan'] ?></strong></div>
                        <div class="struk-row"><span>Layanan/Bank:</span> <span><?= $struk['bank'] ?></span></div>
                        <div class="struk-row"><span>ID / Tujuan:</span> <span><?= $struk['tujuan'] ?></span></div>
                        <div class="struk-row"><span>Penerima/Pelanggan:</span> <span><?= $struk['penerima'] ?></span></div>
                        <hr style="border-top:1px dashed #cbd5e1; margin:8px 0;">
                        <div class="struk-row"><span>Nominal:</span> <span>Rp <?= number_format($struk['nominal'], 0, ',', '.') ?></span></div>
                        <div class="struk-row"><span>Biaya Admin:</span> <span>Rp <?= number_format($struk['admin'], 0, ',', '.') ?></span></div>
                        <div class="struk-row" style="font-weight:bold; font-size:13px; color:#005e6a;">
                            <span>TOTAL BAYAR:</span> <span>Rp <?= number_format($struk['total'], 0, ',', '.') ?></span>
                        </div>
                        <div style="text-align:center; margin-top:10px;">
                            <span class="badge-lunas">✅ STATUS: LUNAS / BERHASIL</span>
                        </div>
                        <button onclick="window.print()" class="btn-print" style="width:100%; justify-content:center; margin-top:10px;">🖨️ Cetak Struk Bukti Transaksi</button>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <script>
        function setNominal(val) {
            document.getElementById('inputNominal').value = val;
        }
    </script>
</body>
</html>