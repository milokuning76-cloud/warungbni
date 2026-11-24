<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$pesan_sukses = '';
$pesan_error  = '';

// Cek foto QRIS toko untuk Kasir
$qris_image_kasir = '';
if (file_exists('assets/img/qris.jpg')) $qris_image_kasir = 'assets/img/qris.jpg';
elseif (file_exists('assets/img/qris.png')) $qris_image_kasir = 'assets/img/qris.png';
elseif (file_exists('assets/img/qris.jpeg')) $qris_image_kasir = 'assets/img/qris.jpeg';

if (isset($_POST['proses_bayar'])) {
    $cart_data   = json_decode($_POST['cart_data'], true);
    $total_bayar = floatval($_POST['total_bayar']);
    $jumlah_uang = floatval($_POST['jumlah_uang']);
    $metode_pos  = mysqli_real_escape_string($koneksi, $_POST['metode_pos'] ?? 'Tunai');
    $kembalian   = $jumlah_uang - $total_bayar;
    $no_faktur   = "INV-" . date("YmdHis");
    $id_user     = $_SESSION['id_user'];

    if ($jumlah_uang < $total_bayar) {
        $pesan_error = "Uang pembayaran kurang!";
    } elseif (empty($cart_data)) {
        $pesan_error = "Keranjang transaksi masih kosong!";
    } else {
        $q_tx = "INSERT INTO transaksi (no_faktur, id_user, jenis_transaksi, total_bayar, jumlah_uang, kembalian, status_pembayaran) 
                 VALUES ('$no_faktur', '$id_user', 'sembako', '$total_bayar', '$jumlah_uang', '$kembalian', 'lunas')";
        
        if (mysqli_query($koneksi, $q_tx)) {
            $id_transaksi = mysqli_insert_id($koneksi);

            foreach ($cart_data as $item) {
                $id_p = intval($item['id']);
                $qty  = intval($item['qty']);
                $hrg  = floatval($item['harga']);
                $sub  = $qty * $hrg;

                mysqli_query($koneksi, "INSERT INTO detail_transaksi (id_transaksi, id_produk, harga_satuan, qty, subtotal) 
                                        VALUES ('$id_transaksi', '$id_p', '$hrg', '$qty', '$sub')");
                
                mysqli_query($koneksi, "UPDATE produk SET stok = stok - $qty WHERE id_produk = '$id_p'");
            }
            $pesan_sukses = "Transaksi Kasir Berhasil ($metode_pos)! No Faktur: <strong>$no_faktur</strong> | Kembalian: <strong>Rp " . number_format($kembalian, 0, ',', '.') . "</strong>";
        } else {
            $pesan_error = "Gagal memproses transaksi!";
        }
    }
}

$res_produk = mysqli_query($koneksi, "SELECT * FROM produk");
$produk_list = [];
while ($row = mysqli_fetch_assoc($res_produk)) {
    $produk_list[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasir POS & QRIS Scan - BNI Agen46</title>
    <link rel="stylesheet" href="assets/style/style.css">
    <link rel="stylesheet" href="style.css">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        /* Modal QRIS Popup Kasir */
        .modal-qris {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(4px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .modal-content-qris {
            background: white;
            padding: 24px;
            border-radius: 20px;
            max-width: 380px;
            width: 90%;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            position: relative;
        }
        .btn-close-qris {
            position: absolute;
            top: 12px; right: 16px;
            background: #f1f5f9;
            border: none;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            width: 30px; height: 30px;
            border-radius: 50%;
        }
    </style>
</head>
<body>

    <header>
        <div class="header-top">
            <div class="brand-logo">BNI <span>Agen46</span> - Modul POS Kasir</div>
            <a href="index.php" style="color:white; text-decoration:none; font-size:12px; font-weight:600;">← Beranda Utama</a>
        </div>
    </header>

    <div class="container">
        <div>
            <?php if ($pesan_sukses): ?><div class="alert-success"><?= $pesan_sukses ?></div><?php endif; ?>
            <?php if ($pesan_error): ?><div class="alert-error"><?= $pesan_error ?></div><?php endif; ?>

            <div class="card">
                <div class="card-title">🔍 Scan Barcode / Kamera / NFC</div>
                
                <div class="scanner-tools">
                    <button class="btn-scan" onclick="toggleCamera()">📷 Kamera Barcode</button>
                    <button class="btn-scan btn-nfc" onclick="startNFCScan()">💳 NFC E-Money</button>
                </div>

                <div id="nfcStatusBox" class="nfc-status">Status Reader NFC: Klik "NFC E-Money" untuk memindai kartu.</div>
                <div id="reader"></div>

                <div style="display:flex; gap:8px; margin-bottom:15px;">
                    <input type="text" id="barcodeInput" placeholder="Ketik / Scan Kode Barcode..." class="input-pay" style="font-size:12px;" onkeypress="checkEnter(event)">
                    <button onclick="addByBarcode()" class="btn-scan" style="margin-top:6px;">+ Tambah</button>
                </div>

                <div class="card-title" style="margin-top:20px;">🛒 Items Dalam Transaksi Kasir</div>
                <table>
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Harga</th>
                            <th style="width:60px;">Qty</th>
                            <th>Subtotal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="cartTableBody">
                        <tr><td colspan="5" style="text-align:center; color:#94a3b8;">Belum ada item ditambahkan.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <div class="card">
                <div class="card-title">💵 Pembayaran Kasir POS</div>
                
                <div id="cardInfoBox" style="display:none; background:#e0f7fa; padding:10px; border-radius:8px; margin-bottom:12px; font-size:11px;">
                    <strong>ID Kartu NFC Terdeteksi:</strong> <span id="nfcCardID">-</span>
                </div>

                <div class="total-box">
                    <div style="font-size:11px; color:#64748b;">TOTAL BELANJA</div>
                    <div class="total-price" id="displayTotal">Rp 0</div>
                </div>

                <!-- Tombol Cepat QRIS Kasir -->
                <button type="button" onclick="openQRISModal()" class="btn-checkout" style="background:#005e6a; margin-top:12px; margin-bottom:12px;">
                    📲 Tampilkan Barcode QRIS Pembeli
                </button>

                <form method="POST" onsubmit="return validatePay()">
                    <input type="hidden" name="cart_data" id="cartDataInput">
                    <input type="hidden" name="total_bayar" id="totalBayarInput">
                    <input type="hidden" name="metode_pos" id="metodePosInput" value="Tunai">

                    <div style="margin-top:10px;">
                        <label style="font-size:11px; font-weight:700;">Metode Pembayaran</label>
                        <select id="selectMetodeKasir" class="form-control" onchange="switchMetodeKasir()">
                            <option value="Tunai">💵 Tunai / Cash</option>
                            <option value="QRIS">📲 QRIS (BCA, BNI, GoPay, OVO, DANA, DLL)</option>
                        </select>
                    </div>

                    <div style="margin-top:12px;">
                        <label style="font-size:11px; font-weight:700;">Uang Diterima (Rp)</label>
                        <input type="number" name="jumlah_uang" id="jumlahUangInput" class="input-pay" placeholder="0" oninput="calcChange()">
                        
                        <div style="display:flex; gap:6px; margin-top:6px;">
                            <button type="button" class="preset-btn" onclick="setUangPas()">Uang Pas</button>
                        </div>
                    </div>

                    <div style="margin-top:10px; font-size:12px; font-weight:700;">
                        Kembalian: <span id="displayChange" style="color:#f26522;">Rp 0</span>
                    </div>

                    <button type="submit" name="proses_bayar" class="btn-checkout" style="margin-top:15px;">
                        🖨️ Proses Transaksi & Struk
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL POPUP QRIS KASIR -->
    <div id="modalQRISKasir" class="modal-qris">
        <div class="modal-content-qris">
            <button class="btn-close-qris" onclick="closeQRISModal()">✕</button>
            <strong style="color:#005e6a; font-size:16px;">📲 SCAN QRIS PEMBAYARAN</strong>
            <p style="font-size:11px; color:#64748b; margin-top:2px;">Tunjukkan QR Code ini kepada pembeli untuk di-scan</p>

            <div style="margin:15px 0;">
                <?php if (!empty($qris_image_kasir)): ?>
                    <img src="<?= $qris_image_kasir ?>" alt="QRIS Toko" style="max-width:220px; width:100%; border-radius:12px; border:1px solid #e2e8f0; box-shadow:0 4px 10px rgba(0,0,0,0.1);">
                <?php else: ?>
                    <div id="qrcodeCanvasKasir" style="display:flex; justify-content:center;"></div>
                <?php endif; ?>
            </div>

            <div style="background:#f8fafc; padding:10px; border-radius:10px; border:1px solid #e2e8f0;">
                <div style="font-size:11px; color:#64748b;">TOTAL TAGIHAN</div>
                <div style="font-size:18px; font-weight:800; color:#005e6a;" id="qrisModalTotal">Rp 0</div>
            </div>

            <button onclick="setQRISLunas()" class="btn-checkout" style="margin-top:12px; background:#16a34a;">
                ✅ Pembayaran QRIS Sukses Diterima
            </button>
        </div>
    </div>

    <script>
        const masterProducts = <?= json_encode($produk_list) ?>;
        let cart = [];
        let html5QrcodeScanner = null;

        function openQRISModal() {
            const total = parseFloat(document.getElementById('totalBayarInput').value) || 0;
            if (total <= 0) {
                alert("Keranjang masih kosong!");
                return;
            }
            document.getElementById('qrisModalTotal').innerText = 'Rp ' + total.toLocaleString('id-ID');
            document.getElementById('modalQRISKasir').style.display = 'flex';

            const qrCanvas = document.getElementById("qrcodeCanvasKasir");
            if (qrCanvas) {
                qrCanvas.innerHTML = "";
                const payloadQRIS = "00020101021226680014ID.LINKAJA.WWW01189360091100000001000215200828100000000303033605204599953033605802ID5925Toko Sembako BNI Agen466009Bojonegoro61056211163045A9B";
                new QRCode(qrCanvas, {
                    text: payloadQRIS,
                    width: 170,
                    height: 170,
                    colorDark : "#004d5a",
                    colorLight : "#ffffff"
                });
            }
        }

        function closeQRISModal() {
            document.getElementById('modalQRISKasir').style.display = 'none';
        }

        function setQRISLunas() {
            const total = parseFloat(document.getElementById('totalBayarInput').value) || 0;
            document.getElementById('selectMetodeKasir').value = 'QRIS';
            document.getElementById('metodePosInput').value = 'QRIS';
            document.getElementById('jumlahUangInput').value = total;
            calcChange();
            closeQRISModal();
            alert("Nominal pembayaran QRIS otomatis terisi sebesar Uang Pas!");
        }

        function setUangPas() {
            const total = parseFloat(document.getElementById('totalBayarInput').value) || 0;
            document.getElementById('jumlahUangInput').value = total;
            calcChange();
        }

        function switchMetodeKasir() {
            const met = document.getElementById('selectMetodeKasir').value;
            document.getElementById('metodePosInput').value = met;
            if (met === 'QRIS') {
                openQRISModal();
            }
        }

        async function startNFCScan() {
            const statusBox = document.getElementById('nfcStatusBox');
            if ('NDEFReader' in window) {
                try {
                    const ndef = new NDEFReader();
                    await ndef.scan();
                    statusBox.innerHTML = "✅ <strong>NFC Aktif:</strong> Tempelkan Kartu TapCash/E-Money ke HP Anda...";
                    statusBox.style.background = "#dcfce7";
                    statusBox.style.color = "#166534";

                    ndef.addEventListener("reading", ({ serialNumber }) => {
                        document.getElementById('cardInfoBox').style.display = 'block';
                        document.getElementById('nfcCardID').innerText = serialNumber;
                        alert("Kartu NFC Terdeteksi!
Serial Number: " + serialNumber);
                    });
                } catch (error) {
                    statusBox.innerHTML = "⚠️ NFC Error: " + error.message;
                    statusBox.style.background = "#fee2e2";
                    statusBox.style.color = "#991b1b";
                }
            } else {
                statusBox.innerHTML = "ℹ️ Browser/Perangkat ini belum mendukung Web NFC (Gunakan Chrome Android + HTTPS).";
                statusBox.style.background = "#fef3c7";
                statusBox.style.color = "#92400e";
            }
        }

        function toggleCamera() {
            const readerDiv = document.getElementById('reader');
            if (readerDiv.style.display === 'none' || readerDiv.style.display === '') {
                readerDiv.style.display = 'block';
                html5QrcodeScanner = new Html5QrcodeScanner("reader", { fps: 10, qrbox: 250 });
                html5QrcodeScanner.render((decodedText) => {
                    findAndAddProduct(decodedText);
                    html5QrcodeScanner.clear();
                    readerDiv.style.display = 'none';
                });
            } else {
                if (html5QrcodeScanner) html5QrcodeScanner.clear();
                readerDiv.style.display = 'none';
            }
        }

        function findAndAddProduct(barcode) {
            const p = masterProducts.find(item => item.barcode === barcode);
            if (p) {
                const existing = cart.find(item => item.id === p.id_produk);
                if (existing) {
                    existing.qty++;
                } else {
                    cart.push({ id: p.id_produk, nama: p.nama_produk, harga: parseFloat(p.harga_jual), qty: 1 });
                }
                renderCart();
            } else {
                alert("Produk dengan barcode " + barcode + " tidak ditemukan!");
            }
        }

        function addByBarcode() {
            const code = document.getElementById('barcodeInput').value.trim();
            if (code) {
                findAndAddProduct(code);
                document.getElementById('barcodeInput').value = '';
            }
        }

        function checkEnter(e) {
            if (e.key === 'Enter') {
                addByBarcode();
            }
        }

        function updateQty(index, newQty) {
            if (newQty <= 0) {
                cart.splice(index, 1);
            } else {
                cart[index].qty = parseInt(newQty);
            }
            renderCart();
        }

        function removeItem(index) {
            cart.splice(index, 1);
            renderCart();
        }

        function renderCart() {
            const tbody = document.getElementById('cartTableBody');
            tbody.innerHTML = '';
            let total = 0;

            if (cart.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; color:#94a3b8;">Belum ada item ditambahkan.</td></tr>';
            } else {
                cart.forEach((item, idx) => {
                    const subtotal = item.harga * item.qty;
                    total += subtotal;
                    tbody.innerHTML += `
                        <tr>
                            <td><strong>${item.nama}</strong></td>
                            <td>Rp ${item.harga.toLocaleString('id-ID')}</td>
                            <td>
                                <input type="number" value="${item.qty}" min="1" style="width:50px; padding:2px;" onchange="updateQty(${idx}, this.value)">
                            </td>
                            <td>Rp ${subtotal.toLocaleString('id-ID')}</td>
                            <td><button onclick="removeItem(${idx})" style="color:red; border:none; background:none; cursor:pointer;">❌</button></td>
                        </tr>
                    `;
                });
            }

            document.getElementById('displayTotal').innerText = 'Rp ' + total.toLocaleString('id-ID');
            document.getElementById('totalBayarInput').value = total;
            document.getElementById('cartDataInput').value = JSON.stringify(cart);
            calcChange();
        }

        function calcChange() {
            const total = parseFloat(document.getElementById('totalBayarInput').value) || 0;
            const pay = parseFloat(document.getElementById('jumlahUangInput').value) || 0;
            const change = pay - total;
            document.getElementById('displayChange').innerText = 'Rp ' + (change > 0 ? change.toLocaleString('id-ID') : '0');
        }

        function validatePay() {
            if (cart.length === 0) {
                alert("Keranjang masih kosong!");
                return false;
            }
            return true;
        }
    </script>
</body>
</html>
