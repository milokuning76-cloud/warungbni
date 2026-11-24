<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

$no_faktur = isset($_GET['no_faktur']) ? mysqli_real_escape_string($koneksi, trim($_GET['no_faktur'])) : '';

if (empty($no_faktur)) {
    echo json_encode(['status' => 'error', 'message' => 'No faktur tidak valid']);
    exit();
}

$q_chk = mysqli_query($koneksi, "SELECT status_pembayaran FROM transaksi WHERE no_faktur = '$no_faktur'");

if ($q_chk && mysqli_num_rows($q_chk) > 0) {
    $data = mysqli_fetch_assoc($q_chk);
    echo json_encode([
        'status' => strtolower($data['status_pembayaran']),
        'no_faktur' => $no_faktur
    ]);
} else {
    echo json_encode(['status' => 'not_found']);
}
?>
