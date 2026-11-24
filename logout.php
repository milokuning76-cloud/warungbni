<?php
// ========================================================
// FILE LOGOUT SISTEM: logout.php
// Aplikasi Toko Sembako & BNI Agen46
// ========================================================

session_start();
session_unset();
session_destroy();

header("Location: login.php");
exit();
?>
