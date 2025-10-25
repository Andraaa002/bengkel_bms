<?php
session_start();

// Hapus semua session
session_unset();

// Hancurkan session
session_destroy();

// Arahkan kembali ke halaman login
header("Location: ../Admin/login.php");
exit();
?>
