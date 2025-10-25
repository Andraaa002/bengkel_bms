<?php
session_start();

// Hapus semua data session
session_unset();
session_destroy();

// Redirect ke halaman login (bisa ke login admin atau halaman utama)
header("Location: ../karyawan/login.php"); // atau ubah ke login_karyawan.php jika perlu
exit();
