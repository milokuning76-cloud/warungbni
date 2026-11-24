<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['keranjang'])) {
    $_SESSION['keranjang'] = array();
}

$pesan_sukses = '';
$pesan_error  = '';
$invoice       = null;

// Function CRC16 CCITT
function qris_crc16($data) {
    $crc = 0xFFFF;
    for ($i = 0; $i < strlen($data); $i++) {
        $crc ^= (ord($data[$i]) << 8);
        for ($j = 0; $j < 8; $j++) {
            if ($crc & 0x8000) {
                $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
            } else {
                $crc = ($crc << 1) & 0xFFFF;
            }
        }
    }
    return sprintf('%04X', $crc);
}

// Function Generator Payload QRIS Dinamis
function get_dynamic_qris_payload($amount) {
    $base_prefix = '00020101021226570011ID.DANA.WWW011893600915303563858102090356385810303UMI51440014ID.CO.QRIS.WWW0215ID10266072617100303UMI520448145303360';
    $base_suffix = '5802ID5911WARUNG KULL6010Kab. Tuban6105623716304';
    
    if ($amount && $amount > 0) {
        $amt_str = (string)intval($amount);
        $amt_tag = '54' . sprintf('%02d', strlen($amt_str)) . $amt_str;
        $raw = $base_prefix . $amt_tag . $base_suffix;
    } else {
        $raw = '00020101021126570011ID.DANA.WWW011893600915303563858102090356385810303UMI51440014ID.CO.QRIS.WWW0215ID10266072617100303UMI520448145303360' . $base_suffix;
    }
    return $raw . qris_crc16($raw);
}

// 1. TAMBAH KE KERANJANG
if (isset($_GET['action']) && $_GET['action'] == 'add') {
    $id_produk = intval($_GET['id']);
    
    $q_p = mysqli_query($koneksi, "SELECT * FROM produk WHERE id_produk='$id_produk'");
    if ($q_p && mysqli_num_rows($q_p) > 0) {
        $p = mysqli_fetch_assoc($q_p);
        
        if (isset($_SESSION['keranjang'][$id_produk])) {
            if ($_SESSION['keranjang'][$id_produk]['qty'] < $p['stok']) {
                $_SESSION['keranjang'][$id_produk]['qty'] += 1;
            }
        } else {
            if ($p['stok'] > 0) {
                $_SESSION['keranjang'][$id_produk] = array(
                    'id_produk'   => $p['id_produk'],
                    'nama_produk' => $p['nama_produk'],
                    'harga'       => $p['harga_jual'],
                    'satuan'      => $p['satuan'],
                    'qty'         => 1
                );
            }
        }
    }
    header("Location: keranjang.php");
    exit();
}

// 2. UPDATE & HAPUS
if (isset($_POST['update_cart'])) {
    if (isset($_POST['qty']) && is_array($_POST['qty'])) {
        foreach ($_POST['qty'] as $id => $val) {
            $val = intval($val);
            if ($val <= 0) {
                unset($_SESSION['keranjang'][$id]);
            } else {
                $_SESSION['keranjang'][$id]['qty'] = $val;
            }
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'delete') {
    $id_del = intval($_GET['id']);
    unset($_SESSION['keranjang'][$id_del]);
    header("Location: keranjang.php");
    exit();
}

// Hitung total belanja
$total_belanja = 0;
if (!empty($_SESSION['keranjang'])) {
    foreach ($_SESSION['keranjang'] as $item) {
        $total_belanja += ($item['harga'] * $item['qty']);
    }
}

// Payload QRIS Dinamis Server
$qris_payload_current = get_dynamic_qris_payload($total_belanja);

// 3. CHECKOUT
if (isset($_POST['proses_checkout'])) {
    if (!isset($_SESSION['id_user'])) {
        header("Location: login.php?status=harus_login");
        exit();
    }

    if (empty($_SESSION['keranjang'])) {
        $pesan_error = "Keranjang belanja Anda masih kosong!";
    } else {
        $id_user          = $_SESSION['id_user'];
        $alamat_kirim     = mysqli_real_escape_string($koneksi, $_POST['alamat_kirim']);
        $metode_bayar     = mysqli_real_escape_string($koneksi, $_POST['metode_bayar']);
        $no_faktur        = "ONLINE-" . date("YmdHis");
        
        $total_bayar = $total_belanja;

        $q_tx = "INSERT INTO transaksi (no_faktur, id_user, jenis_transaksi, total_bayar, jumlah_uang, kembalian, status_pembayaran) 
                 VALUES ('$no_faktur', '$id_user', 'sembako', '$total_bayar', '$total_bayar', 0, 'pending')";
        
        if (mysqli_query($koneksi, $q_tx)) {
            $id_transaksi = mysqli_insert_id($koneksi);

            foreach ($_SESSION['keranjang'] as $item) {
                $id_p = $item['id_produk'];
                $qty  = $item['qty'];
                $hrg  = $item['harga'];
                $sub  = $qty * $hrg;

                mysqli_query($koneksi, "INSERT INTO detail_transaksi (id_transaksi, id_produk, harga_satuan, qty, subtotal) 
                                        VALUES ('$id_transaksi', '$id_p', '$hrg', '$qty', '$sub')");

                mysqli_query($koneksi, "UPDATE produk SET stok = stok - $qty WHERE id_produk = '$id_p'");
            }

            $invoice = array(
                'no_faktur'    => $no_faktur,
                'tgl'          => date("d-m-Y H:i:s"),
                'nama_pembeli' => $_SESSION['nama_lengkap'],
                'alamat'       => $alamat_kirim,
                'metode'       => $metode_bayar,
                'items'        => $_SESSION['keranjang'],
                'total'        => $total_bayar
            );

            $_SESSION['keranjang'] = array();
            $pesan_sukses = "Pesanan berhasil dibuat! Silakan bayar menggunakan QRIS di bawah ini.";
        } else {
            $pesan_error = "Gagal memproses checkout!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang & Pembayaran - BNI Agen46</title>
    <link rel="stylesheet" href="assets/style/style.css">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body>

    <header>
        <div class="header-top">
            <div class="brand-logo">BNI <span>Agen46</span> - Keranjang & Pembayaran</div>
            <a href="index.php" style="color:white; text-decoration:none; font-size:12px; font-weight:600;">← Katalog Sembako</a>
        </div>
    </header>

    <div class="container">
        <div>
            <?php if (!empty($pesan_sukses)): ?>
                <div class="alert-success"><?php echo htmlspecialchars($pesan_sukses); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($pesan_error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($pesan_error); ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-title">🛒 Items Dalam Keranjang Belanja</div>

                <form method="POST">
                    <table>
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>Harga</th>
                                <th style="width:70px;">Jumlah</th>
                                <th>Subtotal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if (empty($_SESSION['keranjang'])): 
                            ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; color:#94a3b8; padding:20px;">
                                        Keranjang belanja Anda kosong.<br>
                                        <a href="index.php" style="color:#005e6a; font-weight:bold; text-decoration:none; display:inline-block; margin-top:8px;">+ Beli Sembako Sekarang</a>
                                    </td>
                                </tr>
                            <?php 
                            else: 
                                foreach ($_SESSION['keranjang'] as $id => $item):
                                    $subtotal = $item['harga'] * $item['qty'];
                            ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($item['nama_produk']); ?></strong></td>
                                    <td>Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?></td>
                                    <td>
                                        <input type="number" name="qty[<?php echo $id; ?>]" value="<?php echo $item['qty']; ?>" min="1" class="input-qty">
                                    </td>
                                    <td>Rp <?php echo number_format($subtotal, 0, ',', '.'); ?></td>
                                    <td><a href="keranjang.php?action=delete&id=<?php echo $id; ?>" class="btn-del" onclick="return confirm('Hapus item?')">❌</a></td>
                                </tr>
                            <?php 
                                endforeach; 
                            endif;
                            ?>
                        </tbody>
                    </table>

                    <?php if (!empty($_SESSION['keranjang'])): ?>
                        <div style="margin-top:15px; display:flex; justify-content:space-between; align-items:center;">
                            <button type="submit" name="update_cart" class="btn-update">🔄 Update Keranjang</button>
                            <span style="font-size:14px; font-weight:800; color:#005e6a;">
                                Total: Rp <?php echo number_format($total_belanja, 0, ',', '.'); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </form>

                <?php if ($invoice): ?>
                    <div class="invoice-box" id="invoiceArea" style="margin-top:20px; border:2px solid #005e6a; border-radius:14px; padding:18px; background:#ffffff;">
                        <div class="invoice-header" style="border-bottom:2px dashed #cbd5e1; padding-bottom:10px; margin-bottom:12px; text-align:center;">
                            <h3 style="margin-bottom:4px; color:#005e6a; font-size:16px;">INVOICE PESANAN ONLINE</h3>
                            <small style="color:#64748b;">No Faktur: <strong><?php echo $invoice['no_faktur']; ?></strong> | <?php echo $invoice['tgl']; ?></small>
                        </div>
                        
                        <div id="statusBadgeContainer" style="text-align:center; margin-bottom:14px;">
                            <span id="badgeStatus" class="badge-pending" style="font-size:12px; padding:6px 14px; border-radius:20px; background:#fffbeb; color:#b45309; border:1px solid #fde68a; font-weight:700;">
                                ⏳ MENUNGGU PEMBAYARAN QRIS...
                            </span>
                        </div>

                        <p><strong>Pemesan:</strong> <?php echo htmlspecialchars($invoice['nama_pembeli']); ?></p>
                        <p><strong>Alamat Kirim:</strong> <?php echo htmlspecialchars($invoice['alamat']); ?></p>
                        <p><strong>Metode Bayar:</strong> <?php echo htmlspecialchars($invoice['metode']); ?></p>
                        <hr style="margin:10px 0; border-top:1px dashed #cbd5e1;">
                        <p><strong>Rincian Pesanan:</strong></p>
                        <ul style="padding-left: 20px; margin-bottom: 12px; font-size:12px;">
                            <?php foreach ($invoice['items'] as $inv_item): ?>
                                <li><?php echo htmlspecialchars($inv_item['nama_produk']); ?> x <?php echo $inv_item['qty']; ?> = Rp <?php echo number_format($inv_item['harga'] * $inv_item['qty'], 0, ',', '.'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <p style="font-size:15px; font-weight:800; color:#005e6a; border-top:1px solid #e2e8f0; padding-top:8px;">
                            TOTAL TAGIHAN: Rp <?php echo number_format($invoice['total'], 0, ',', '.'); ?>
                        </p>

                        <div style="margin-top:14px; background:#f8fafc; padding:12px; border-radius:10px; border:1px solid #e2e8f0; text-align:center;">
                            <small style="font-size:11px; color:#64748b; display:block; margin-bottom:8px;">
                                ⚡ Status pembayaran diperiksa secara otomatis. Klik tombol di bawah jika sudah mentransfer:
                            </small>
                            <a href="bayar_qris.php?faktur=<?php echo $invoice['no_faktur']; ?>" class="btn-checkout" style="background:#16a34a; text-decoration:none; display:inline-block; width:auto; padding:8px 16px; font-size:12px;">
                                ✅ Simulasi Pembayaran QRIS Lunas (Otomatis Terkonfirmasi)
                            </a>
                        </div>
                    </div>

                    <script>
                        const noFaktur = "<?php echo $invoice['no_faktur']; ?>";
                        function checkAutoPayment() {
                            fetch("cek_pembayaran.php?faktur=" + noFaktur)
                                .then(response => response.json())
                                .then(data => {
                                    if (data.status === 'lunas') {
                                        const badge = document.getElementById('badgeStatus');
                                        if (badge) {
                                            badge.className = 'badge-lunas';
                                            badge.style.background = '#dcfce7';
                                            badge.style.color = '#15803d';
                                            badge.style.border = '1px solid #86efac';
                                            badge.innerHTML = '✅ STATUS: PEMBAYARAN LUNAS / TERKONFIRMASI OTOMATIS';
                                        }
                                    }
                                })
                                .catch(err => console.log(err));
                        }
                        setInterval(checkAutoPayment, 3000);
                    </script>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <div class="card">
                <div class="card-title">💳 Metode Pembayaran & Checkout</div>

                <?php if (!isset($_SESSION['id_user'])): ?>
                    <div class="alert-error" style="font-size:11px;">
                        Anda harus <strong>Login sebagai Pembeli</strong> untuk checkout.
                        <a href="login.php" style="color:#005e6a; font-weight:bold; display:block; margin-top:4px;">Login di sini</a>
                    </div>
                <?php else: ?>
                    <form method="POST">
                        <div class="form-group">
                            <label>Nama Pemesan</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION['nama_lengkap']); ?>" readonly style="background:#f1f5f9;">
                        </div>

                        <div class="form-group">
                            <label>Alamat Pengiriman</label>
                            <textarea name="alamat_kirim" class="form-control" rows="2" placeholder="Alamat lengkap pengiriman..." required></textarea>
                        </div>

                        <div class="form-group">
                            <label>Pilih Metode Pembayaran</label>
                            <select name="metode_bayar" id="selectMetode" class="form-control" onchange="tampilkanRekening()" required>
                                <option value="QRIS Dinamis Warung Kull (BCA, BNI, BRI, GoPay, OVO, DANA, ShopeePay)" selected>📲 QRIS Dinamis Warung Kull (BCA, BNI, BRI, GoPay, OVO, DANA, ShopeePay)</option>
                                
                                <optgroup label="--- Transfer Bank ---">
                                    <option value="Transfer Bank BNI (0123-4567-89)">Bank BNI (0123-4567-89)</option>
                                    <option value="Transfer Bank BRI (8801-0100-2345-534)">Bank BRI (8801-0100-2345-534)</option>
                                    <option value="Transfer Bank Mandiri (137-00-1234567-8)">Bank Mandiri (137-00-1234567-8)</option>
                                    <option value="Transfer Bank BCA (8830-1234-56)">Bank BCA (8830-1234-56)</option>
                                    <option value="Transfer Bank BSI (7123-4567-89)">Bank BSI Syariah (7123-4567-89)</option>
                                    <option value="Transfer Bank CIMB Niaga (8001-2345-6789)">Bank CIMB Niaga (8001-2345-6789)</option>
                                </optgroup>

                                <optgroup label="--- E-Money / Dompet Digital ---">
                                    <option value="GoPay (898-081234567890)">GoPay (898-081234567890)</option>
                                    <option value="OVO (8088-081234567890)">OVO (8088-081234567890)</option>
                                    <option value="DANA (3901-081234567890)">DANA (3901-081234567890)</option>
                                    <option value="ShopeePay (122-081234567890)">ShopeePay (122-081234567890)</option>
                                </optgroup>

                                <option value="Bayar Tunai di Toko (COD)">💵 Bayar Tunai di Toko / COD</option>
                            </select>
                        </div>

                        <!-- Box QRIS Dinamis WARUNG KULL -->
                        <div id="qrisBox" class="qris-box" style="display:block; text-align:center; background:#ffffff; border:2px dashed #f26522; padding:18px; border-radius:14px; margin-top:12px;">
                            <strong style="color:#005e6a; font-size:14px; text-transform:uppercase;">📲 SCAN QRIS DINAMIS WARUNG KULL</strong><br>
                            
                            <div style="position:relative; display:inline-block; margin:12px auto; padding:12px; background:#ffffff; border-radius:16px; border:1px solid #cbd5e1; box-shadow:0 4px 12px rgba(0,0,0,0.06);">
                                <?php if (empty($_SESSION['keranjang'])): ?>
                                    <!-- Bila Keranjang Kosong: QR Blurred dengan Tanda Silang ❌ -->
                                    <div style="position:relative; width:220px; height:220px;">
                                        <div id="qrcodeCanvas" style="opacity:0.25; filter:grayscale(100%) blur(1px);"></div>
                                        <div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); z-index:10; background:#ef4444; color:#ffffff; font-size:42px; font-weight:900; width:72px; height:72px; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 6px 20px rgba(239, 68, 68, 0.45); border:4px solid #ffffff;">
                                            ❌
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <!-- Bila Keranjang Terisi: QR Code Sharp Clear -->
                                    <div id="qrcodeCanvas"></div>
                                <?php endif; ?>
                            </div>

                            <small style="display:block; color:#64748b; font-size:11px; margin-bottom:4px;">
                                Nominal tagihan otomatis terhitung &amp; terkonfirmasi otomatis saat lunas
                            </small>

                            <small style="display:block; font-size:13px; font-weight:800; color:#005e6a; margin-bottom:2px;">
                                Nominal Tagihan: <span style="color:#005e6a;">Rp <?php echo number_format($total_belanja, 0, ',', '.'); ?></span>
                            </small>

                            <small style="color:#475569; font-size:11px; display:block;">
                                Atas Nama: <strong>WARUNG KULL (NMID: ID1026607261710)</strong>
                            </small>
                        </div>

                        <div id="rekBox" class="rekening-box" style="display:none; padding:14px; background:#e0f2fe; border:1px solid #0284c7; border-radius:10px; margin-top:10px; font-size:12px;">
                            <strong>Petunjuk Transfer Pembayaran:</strong>
                            <div id="rekDetail" style="margin-top:4px; font-size:13px; font-weight:bold; color:#005e6a;">-</div>
                            <span style="font-size:11px; color:#475569;">Atas Nama: <strong>Toko Sembako BNI Agen46</strong></span>
                        </div>

                        <button type="submit" name="proses_checkout" class="btn-checkout" style="margin-top:16px; width:100%; background:linear-gradient(135deg, #f26522, #ea580c); color:white; border:none; padding:12px; border-radius:10px; font-size:14px; font-weight:700; cursor:pointer;" <?php echo empty($_SESSION['keranjang']) ? 'disabled style="background:#cbd5e1; cursor:not-allowed;"' : ''; ?>>
                            🛍️ Konfirmasi Checkout Pesanan
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Dynamic CRC16 CCITT Generator in JS
        function jsCrc16(str) {
            let crc = 0xFFFF;
            for (let i = 0; i < str.length; i++) {
                crc ^= (str.charCodeAt(i) << 8);
                for (let j = 0; j < 8; j++) {
                    if (crc & 0x8000) {
                        crc = ((crc << 1) ^ 0x1021) & 0xFFFF;
                    } else {
                        crc = (crc << 1) & 0xFFFF;
                    }
                }
            }
            let hex = (crc & 0xFFFF).toString(16).toUpperCase();
            while (hex.length < 4) hex = '0' + hex;
            return hex;
        }

        function generateQRISPayload(amount) {
            const basePrefix = "00020101021226570011ID.DANA.WWW011893600915303563858102090356385810303UMI51440014ID.CO.QRIS.WWW0215ID10266072617100303UMI520448145303360";
            const baseSuffix = "5802ID5911WARUNG KULL6010Kab. Tuban6105623716304";
            
            let raw = "";
            if (amount && amount > 0) {
                let amtStr = Math.round(amount).toString();
                let amtLen = amtStr.length < 10 ? '0' + amtStr.length : amtStr.length.toString();
                let amtTag = '54' + amtLen + amtStr;
                raw = basePrefix + amtTag + baseSuffix;
            } else {
                raw = "00020101021126570011ID.DANA.WWW011893600915303563858102090356385810303UMI51440014ID.CO.QRIS.WWW0215ID10266072617100303UMI520448145303360" + baseSuffix;
            }
            return raw + jsCrc16(raw);
        }

        function renderQR() {
            const qrContainer = document.getElementById("qrcodeCanvas");
            if (qrContainer) {
                qrContainer.innerHTML = "";
                const currentAmount = <?php echo floatval($total_belanja); ?>;
                const payload = generateQRISPayload(currentAmount);
                
                new QRCode(qrContainer, {
                    text: payload,
                    width: 220,
                    height: 220,
                    colorDark : "#000000",
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.M
                });
            }
        }

        function tampilkanRekening() {
            const val = document.getElementById('selectMetode').value;
            const rekBox = document.getElementById('rekBox');
            const qrisBox = document.getElementById('qrisBox');
            const rekDetail = document.getElementById('rekDetail');

            if (rekBox) rekBox.style.display = 'none';
            if (qrisBox) qrisBox.style.display = 'none';

            if (val.includes('QRIS')) {
                if (qrisBox) qrisBox.style.display = 'block';
                renderQR();
            } else if (val !== '' && !val.includes('COD')) {
                if (rekBox) {
                    rekBox.style.display = 'block';
                    if (rekDetail) rekDetail.innerText = val;
                }
            }
        }

        // Auto render QR Code on DOM loaded
        document.addEventListener("DOMContentLoaded", function() {
            renderQR();
        });
    </script>
</body>
</html>
