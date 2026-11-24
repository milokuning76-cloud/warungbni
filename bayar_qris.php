<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

$no_faktur = isset($_POST['no_faktur']) ? mysqli_real_escape_string($koneksi, trim($_POST['no_faktur'])) : '';

if (empty($no_faktur)) {
    echo json_encode(['success' => false, 'message' => 'No faktur kosong']);
    exit();
}

$q_update = mysqli_query($koneksi, "UPDATE transaksi SET status_pembayaran = 'lunas' WHERE no_faktur = '$no_faktur'");

if ($q_update) {
    echo json_encode(['success' => true, 'message' => 'Pembayaran QRIS berhasil dikonfirmasi secara otomatis!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal mengonfirmasi pembayaran: ' . mysqli_error($koneksi)]);
}
?>
