<?php
// ========================================================
// FILE KONEKSI DATABASE MYSQL: config.php
// Aplikasi Toko Sembako & BNI Agen46
// ========================================================

$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_sembako_agen46";

// Membuat koneksi ke database MySQL
$koneksi = mysqli_connect($host, $user, $pass, $db);

// Cek koneksi
if (!$koneksi) {
    die("Koneksi Database Gagal: " . mysqli_connect_error());
}
?>
