<?php
session_start();

// Cek apakah sudah login dan sebagai admin dengan namespace baru
if (!isset($_SESSION['admin']['logged_in']) || $_SESSION['admin']['logged_in'] !== true) {
  header("Location: ../login.php");
  exit();
}

include '../config.php'; // Pastikan path ke config.php benar

// Set default filter dates dan handle period selection
$today = date('Y-m-d');

// Filter bulan
$bulan_filter = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');

// Set date ranges berdasarkan parameter GET
$dari_tanggal = isset($_GET['dari_tanggal']) ? $_GET['dari_tanggal'] : date('Y-m-d', strtotime('-7 days'));
$sampai_tanggal = isset($_GET['sampai_tanggal']) ? $_GET['sampai_tanggal'] : $today;

// Cek apakah filter rentang tanggal aktif
$filter_by_date = isset($_GET['dari_tanggal']) && isset($_GET['sampai_tanggal']);

// Query untuk mendapatkan daftar kasbon manajer
if ($filter_by_date) {
  // Filter berdasarkan rentang tanggal
  $kasbon_query = "SELECT k.*, m.nama 
                 FROM kasbon_manajer k 
                 JOIN manajer m ON k.id_manajer = m.id_manajer
                 WHERE k.tanggal BETWEEN '$dari_tanggal' AND '$sampai_tanggal'
                 ORDER BY k.tanggal DESC";
  
  // Hitung total kasbon untuk rentang tanggal
  $total_kasbon_query = "SELECT SUM(jumlah) as total_kasbon 
                        FROM kasbon_manajer 
                        WHERE tanggal BETWEEN '$dari_tanggal' AND '$sampai_tanggal'";
  
  // Query laba bersih untuk rentang tanggal
  $laba_bersih_query = "SELECT 
                        SUM(CASE 
                            WHEN td.produk_id = 0 OR td.produk_id IS NULL THEN td.subtotal
                            ELSE td.subtotal - (td.jumlah * IFNULL(p.harga_beli, 0))
                           END) AS total_keuntungan
                        FROM transaksi_detail td 
                        LEFT JOIN produk p ON td.produk_id = p.id
                        JOIN transaksi t ON td.transaksi_id = t.id
                        WHERE t.tanggal BETWEEN '$dari_tanggal' AND '$sampai_tanggal'";
  
  // Cek apakah kolom status_kas ada di tabel jual_bekas
  $cek_status_kas = mysqli_query($conn, "SHOW COLUMNS FROM jual_bekas LIKE 'status_kas'");
  
  if (mysqli_num_rows($cek_status_kas) > 0) {
    // Pendapatan barang bekas untuk rentang tanggal (dengan status kas)
    $bekas_query = "SELECT 
                  SUM(CASE WHEN status_kas = 1 THEN total_harga ELSE 0 END) as total_pendapatan_bekas,
                  SUM(CASE WHEN status_kas = 0 OR status_kas IS NULL THEN total_harga ELSE 0 END) as total_bekas_belum_kas
                  FROM jual_bekas 
                  WHERE tanggal BETWEEN '$dari_tanggal' AND '$sampai_tanggal'";
  } else {
    // Pendapatan barang bekas untuk rentang tanggal (tanpa status kas)
    $bekas_query = "SELECT 
                  SUM(total_harga) as total_pendapatan_bekas,
                  0 as total_bekas_belum_kas
                  FROM jual_bekas 
                  WHERE tanggal BETWEEN '$dari_tanggal' AND '$sampai_tanggal'";
  }
  
  if ($filter_by_date) {
    // Biaya operasional untuk rentang tanggal (DIREVISI)
    $operasional_query = "SELECT 
                      SUM(CASE WHEN kategori NOT IN ('Kasbon Karyawan', 'Uang Makan', 'Gaji Karyawan', 'Pembelian Sparepart', 'Pembelian Barang') 
                              AND keterangan NOT LIKE '%produk:%' 
                              AND keterangan NOT LIKE '%produk baru:%'
                              AND keterangan NOT LIKE 'Penambahan stok produk:%' 
                              THEN jumlah ELSE 0 END) as total_operasional,
                      SUM(CASE WHEN kategori IN ('Kasbon Karyawan', 'Uang Makan', 'Gaji Karyawan') 
                              THEN jumlah ELSE 0 END) as total_karyawan
                      FROM pengeluaran 
                      WHERE tanggal BETWEEN '$dari_tanggal' AND '$sampai_tanggal'";
  } else {
    // Biaya operasional untuk filter bulan (DIREVISI)
    $operasional_query = "SELECT 
                      SUM(CASE WHEN kategori NOT IN ('Kasbon Karyawan', 'Uang Makan', 'Gaji Karyawan', 'Pembelian Sparepart', 'Pembelian Barang') 
                              AND keterangan NOT LIKE '%produk:%' 
                              AND keterangan NOT LIKE '%produk baru:%'
                              AND keterangan NOT LIKE 'Penambahan stok produk:%' 
                              THEN jumlah ELSE 0 END) as total_operasional,
                      SUM(CASE WHEN kategori IN ('Kasbon Karyawan', 'Uang Makan', 'Gaji Karyawan') 
                              THEN jumlah ELSE 0 END) as total_karyawan
                      FROM pengeluaran 
                      WHERE bulan = '$bulan_filter'";
  }  
} else {
  // Filter berdasarkan bulan
  $kasbon_query = "SELECT k.*, m.nama 
                 FROM kasbon_manajer k 
                 JOIN manajer m ON k.id_manajer = m.id_manajer
                 WHERE k.bulan = '$bulan_filter'
                 ORDER BY k.tanggal DESC";
  
  // Hitung total kasbon bulan ini
  $total_kasbon_query = "SELECT SUM(jumlah) as total_kasbon 
                        FROM kasbon_manajer 
                        WHERE bulan = '$bulan_filter'";
  
  // Hitung laba bersih bulan ini
  $laba_bersih_query = "SELECT 
                        SUM(CASE 
                            WHEN td.produk_id = 0 OR td.produk_id IS NULL THEN td.subtotal
                            ELSE td.subtotal - (td.jumlah * IFNULL(p.harga_beli, 0))
                           END) AS total_keuntungan
                        FROM transaksi_detail td 
                        LEFT JOIN produk p ON td.produk_id = p.id
                        JOIN transaksi t ON td.transaksi_id = t.id
                        WHERE DATE_FORMAT(t.tanggal, '%Y-%m') = '$bulan_filter'";
  
  // Cek apakah kolom status_kas ada di tabel jual_bekas
  $cek_status_kas = mysqli_query($conn, "SHOW COLUMNS FROM jual_bekas LIKE 'status_kas'");
  
  if (mysqli_num_rows($cek_status_kas) > 0) {
    // Pendapatan barang bekas dengan status kas
    $bekas_query = "SELECT 
                  SUM(CASE WHEN status_kas = 1 THEN total_harga ELSE 0 END) as total_pendapatan_bekas,
                  SUM(CASE WHEN status_kas = 0 OR status_kas IS NULL THEN total_harga ELSE 0 END) as total_bekas_belum_kas
                  FROM jual_bekas 
                  WHERE bulan = '$bulan_filter'";
  } else {
    // Pendapatan barang bekas tanpa status kas
    $bekas_query = "SELECT 
                  SUM(total_harga) as total_pendapatan_bekas,
                  0 as total_bekas_belum_kas
                  FROM jual_bekas 
                  WHERE bulan = '$bulan_filter'";
  }
  
  // Biaya operasional (tanpa kasbon manajer karena sudah dipisah ke tabel sendiri)
  $operasional_query = "SELECT 
  SUM(CASE WHEN kategori NOT IN ('Kasbon Karyawan', 'Uang Makan', 'Gaji Karyawan', 'Pembelian Sparepart', 'Pembelian Barang') 
          AND keterangan NOT LIKE '%produk:%' 
          AND keterangan NOT LIKE '%produk baru:%'
          AND keterangan NOT LIKE 'Penambahan stok produk:%' 
          THEN jumlah ELSE 0 END) as total_operasional,
  SUM(CASE WHEN kategori IN ('Kasbon Karyawan', 'Uang Makan', 'Gaji Karyawan') 
          THEN jumlah ELSE 0 END) as total_karyawan
  FROM pengeluaran 
  WHERE bulan = '$bulan_filter'";
}

$kasbon_result = mysqli_query($conn, $kasbon_query);

// Error handling for kasbon_query
if ($kasbon_result === false) {
    error_log("Kasbon query failed: " . mysqli_error($conn) . " SQL: " . $kasbon_query);
    $kasbon_data = [];
} else {
    $kasbon_data = [];
    while ($row = mysqli_fetch_assoc($kasbon_result)) {
        $kasbon_data[] = $row;
    }
}

$total_kasbon_result = mysqli_query($conn, $total_kasbon_query);
$total_kasbon = 0;

if ($total_kasbon_result && $row = mysqli_fetch_assoc($total_kasbon_result)) {
    $total_kasbon = $row['total_kasbon'] ?: 0;
}

$laba_bersih_result = mysqli_query($conn, $laba_bersih_query);
$total_keuntungan = 0;

if ($laba_bersih_result && $row = mysqli_fetch_assoc($laba_bersih_result)) {
    $total_keuntungan = $row['total_keuntungan'] ?: 0;
}

$bekas_result = mysqli_query($conn, $bekas_query);
$total_pendapatan_bekas = 0;
$total_bekas_belum_kas = 0;

if ($bekas_result && $row = mysqli_fetch_assoc($bekas_result)) {
    $total_pendapatan_bekas = $row['total_pendapatan_bekas'] ?: 0;
    $total_bekas_belum_kas = $row['total_bekas_belum_kas'] ?: 0;
}

// Total pendapatan barang bekas keseluruhan (yang sudah dan belum masuk kas)
$total_pendapatan_bekas_all = $total_pendapatan_bekas + $total_bekas_belum_kas;

$operasional_result = mysqli_query($conn, $operasional_query);
$total_operasional = 0;
$total_karyawan = 0;

if ($operasional_result && $row = mysqli_fetch_assoc($operasional_result)) {
    $total_operasional = $row['total_operasional'] ?: 0;
    $total_karyawan = $row['total_karyawan'] ?: 0;
}

$total_biaya_operasional = $total_operasional + $total_karyawan;

// Laba bersih - kasbon manajer tidak mempengaruhi perhitungan laba
// PERBAIKAN: Menggunakan total_pendapatan_bekas_all (termasuk yang belum masuk kas)
$laba_bersih = ($total_keuntungan + $total_pendapatan_bekas_all) - $total_biaya_operasional;

$bagian_manajer = $laba_bersih * 0.5;
$sisa_bagian_manajer = $bagian_manajer - $total_kasbon;
$manajer_memiliki_hutang = $sisa_bagian_manajer < 0;

// Proses form tambah kasbon
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_kasbon') {
    $id_manajer = mysqli_real_escape_string($conn, $_POST['id_manajer']);
    $jumlah = mysqli_real_escape_string($conn, $_POST['jumlah']);
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
    $tanggal = mysqli_real_escape_string($conn, $_POST['tanggal']);
    $bulan = date('Y-m', strtotime($tanggal));

    // Insert kasbon ke tabel kasbon_manajer
    $insert_query = "INSERT INTO kasbon_manajer (id_manajer, jumlah, keterangan, tanggal, bulan) 
                    VALUES ('$id_manajer', '$jumlah', '$keterangan', '$tanggal', '$bulan')";
    
    if (mysqli_query($conn, $insert_query)) {
        $_SESSION['message'] = "Kasbon manajer berhasil ditambahkan!";
        $_SESSION['alert_type'] = "success";
    } else {
        $_SESSION['message'] = "Gagal menambahkan kasbon: " . mysqli_error($conn);
        $_SESSION['alert_type'] = "danger";
    }
    
    // Redirect ke halaman sesuai filter yang aktif
    if ($filter_by_date) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?dari_tanggal=" . $dari_tanggal . "&sampai_tanggal=" . $sampai_tanggal);
    } else {
        header("Location: " . $_SERVER['PHP_SELF'] . "?bulan=" . $bulan_filter);
    }
    exit();
}

// Proses hapus kasbon
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete']);
    
    // Hapus dari tabel kasbon_manajer
    $delete_query = "DELETE FROM kasbon_manajer WHERE id = '$id'";
    if (mysqli_query($conn, $delete_query)) {
        $_SESSION['message'] = "Kasbon manajer berhasil dihapus!";
        $_SESSION['alert_type'] = "success";
    } else {
        $_SESSION['message'] = "Gagal menghapus kasbon: " . mysqli_error($conn);
        $_SESSION['alert_type'] = "danger";
    }
    
    // Redirect ke halaman sesuai filter yang aktif
    if ($filter_by_date) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?dari_tanggal=" . $dari_tanggal . "&sampai_tanggal=" . $sampai_tanggal);
    } else {
        header("Location: " . $_SERVER['PHP_SELF'] . "?bulan=" . $bulan_filter);
    }
    exit();
}

// Ambil daftar manajer
$manajer_query = "SELECT id_manajer, nama FROM manajer";
$manajer_result = mysqli_query($conn, $manajer_query);
$manajer_list = [];

if ($manajer_result) {
    while ($row = mysqli_fetch_assoc($manajer_result)) {
        $manajer_list[] = $row;
    }
}

// Get available months for filter
$months_query = "SELECT DISTINCT bulan FROM kasbon_manajer UNION SELECT DISTINCT DATE_FORMAT(NOW(), '%Y-%m') ORDER BY bulan DESC";
$months_result = mysqli_query($conn, $months_query);
$months = [];

if ($months_result) {
    while ($row = mysqli_fetch_assoc($months_result)) {
        $months[] = $row['bulan'];
    }
}

// Jika tidak ada bulan, tambahkan bulan ini
if (empty($months)) {
    $months[] = date('Y-m');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pengeluaran Manajer - BMS Bengkel</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      /* Admin purple theme */
      --primary-purple: #7E57C2;
      --secondary-purple: #5E35B1;
      --light-purple: #EDE7F6;
      --accent-purple: #4527A0;
      
      /* General colors */
      --white: #ffffff;
      --light-gray: #f8f9fa;
      --text-dark: #2C3E50;
      --border-color: #e1e7ef;
    }
    
    body {
      font-family: 'Poppins', sans-serif;
      background-color: var(--light-gray);
      margin: 0;
      padding: 0;
      color: var(--text-dark);
    }

    .content {
      margin-left: 280px;
      transition: margin-left 0.3s ease;
      min-height: 100vh;
    }

    /* Navbar Styling */
    .navbar {
      background: linear-gradient(135deg, var(--primary-purple), var(--accent-purple));
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      border: none;
      padding: 0.8rem 1.5rem;
      margin-bottom: 20px;
    }

    .navbar-brand {
      font-weight: 700;
      font-size: 1.5rem;
      color: var(--white);
      letter-spacing: 0.5px;
    }
    
    /* Page header section */
    .page-header {
      background: linear-gradient(135deg, var(--primary-purple), var(--secondary-purple));
      border-radius: 15px;
      padding: 30px;
      color: var(--white);
      margin-bottom: 25px;
      box-shadow: 0 6px 18px rgba(126, 87, 194, 0.15);
    }
    
    .page-header h1 {
      font-weight: 700;
      margin-bottom: 0.5rem;
    }
    
    /* Data card */
    .data-card, .filter-card, .table-card, .chart-container {
      border: none;
      border-radius: 15px;
      overflow: hidden;
      box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
      background-color: var(--white);
      padding: 1.5rem;
      margin-bottom: 1.5rem;
      transition: all 0.3s ease;
    }
    
    .data-card:hover, .chart-container:hover {
      transform: translateY(-5px);
      box-shadow: 0 12px 20px rgba(0, 0, 0, 0.12);
    }
    
    /* Card header with actions */
    .card-header-actions {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-bottom: 1rem;
      margin-bottom: 1.5rem;
      border-bottom: 2px solid var(--light-purple);
    }
    
    .card-title, .chart-title, .filter-title {
      font-size: 1.25rem;
      font-weight: 600;
      color: var(--primary-purple);
      margin-bottom: 0;
    }
    
    /* Button styling */
    .btn-primary {
      background: linear-gradient(135deg, var(--primary-purple), var(--secondary-purple));
      border: none;
      border-radius: 8px;
      padding: 0.75rem 1.5rem;
      font-weight: 600;
      transition: all 0.3s ease;
      box-shadow: 0 4px 10px rgba(126, 87, 194, 0.2);
    }
    
    .btn-primary:hover {
      background: linear-gradient(135deg, var(--accent-purple), var(--primary-purple));
      transform: translateY(-2px);
      box-shadow: 0 6px 15px rgba(126, 87, 194, 0.3);
    }
    
    .btn-outline-light, .btn-outline-primary, .btn-outline-secondary {
      border-radius: 8px;
      font-weight: 500;
      transition: all 0.3s ease;
    }
    
    .btn-danger {
      background: linear-gradient(135deg, #F44336, #D32F2F);
      border: none;
      border-radius: 8px;
      padding: 0.75rem 1.5rem;
      font-weight: 600;
      transition: all 0.3s ease;
      box-shadow: 0 4px 10px rgba(244, 67, 54, 0.2);
    }
    
    .btn-danger:hover {
      background: linear-gradient(135deg, #D32F2F, #B71C1C);
      transform: translateY(-2px);
      box-shadow: 0 6px 15px rgba(244, 67, 54, 0.3);
    }
    
    /* Form styling */
    .form-control, .form-select {
      border-radius: 8px;
      border: 1px solid #d1e3f0;
      padding: 0.75rem 1rem;
      transition: all 0.3s;
      background-color: var(--white);
    }

    .form-control:focus, .form-select:focus {
      border-color: var(--primary-purple);
      box-shadow: 0 0 0 0.25rem rgba(126, 87, 194, 0.25);
    }
    
    /* Summary cards */
    .summary-card {
      border-radius: 15px;
      color: var(--white);
      padding: 1.5rem;
      margin-bottom: 1.5rem;
      box-shadow: 0 6px 18px rgba(0, 0, 0, 0.15);
      height: 100%;
      transition: all 0.3s ease;
    }
    
    .summary-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 12px 20px rgba(0, 0, 0, 0.18);
    }
    
    .summary-card {
      background: linear-gradient(135deg, #9575CD, #673AB7);
    }
    
    .summary-card.profit {
      background: linear-gradient(135deg, #66BB6A, #43A047);
    }
    
    .summary-card.warning {
      background: linear-gradient(135deg, #FFA726, #FB8C00);
    }
    
    .summary-card.danger {
      background: linear-gradient(135deg, #F44336, #D32F2F);
    }
    
    .summary-card.info {
      background: linear-gradient(135deg, #29B6F6, #0288D1);
    }
    
    .summary-title {
      font-size: 1.1rem;
      font-weight: 600;
      margin-bottom: 0.5rem;
    }
    
    .summary-value {
      font-size: 1.8rem;
      font-weight: 700;
    }
    
    /* Table styling */
    .table {
      margin-bottom: 0;
    }
    
    .table thead {
      background-color: var(--light-purple);
    }
    
    .table thead th {
      color: var(--primary-purple);
      font-weight: 600;
      border-bottom: 2px solid var(--secondary-purple);
      padding: 1rem;
      vertical-align: middle;
    }
    
    .table tbody td {
      padding: 1rem;
      vertical-align: middle;
      border-color: var(--light-purple);
    }
    
    .table-striped tbody tr:nth-of-type(odd) {
      background-color: rgba(237, 231, 246, 0.3);
    }
    
    /* Month filter styling */
    .month-badge {
      background: var(--light-purple);
      color: var(--primary-purple);
      font-weight: 600;
      padding: 0.75rem 1.25rem;
      border-radius: 10px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    /* Alerts */
    .alert {
      border-radius: 10px;
      border: none;
      padding: 1rem 1.5rem;
      margin-bottom: 2rem;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }
    
    .alert-success {
      background-color: #E8F5E9;
      color: #1B5E20;
    }
    
    .alert-danger {
      background-color: #FFEBEE;
      color: #B71C1C;
    }
    
    /* Responsive styles */
    @media (max-width: 992px) {
      .content {
        margin-left: 0;
      }
    }
    
    @media (max-width: 768px) {
      .chart-container, .table-card, .filter-card, .data-card {
        padding: 1rem;
      }
      
      .page-header {
        padding: 1.5rem;
      }
      
      .table thead th, .table tbody td {
        padding: 0.75rem;
      }
      
      .summary-value {
        font-size: 1.5rem;
      }
    }

    /* Style for active nav tabs */
    .nav-tabs .nav-link {
      color: var(--text-dark);
      font-weight: 500;
      border-radius: 8px 8px 0 0;
      padding: 10px 15px;
      transition: all 0.3s ease;
    }

    .nav-tabs .nav-link.active {
      color: var(--white);
      background: linear-gradient(135deg, var(--primary-purple), var(--secondary-purple));
      border-color: transparent;
      font-weight: 600;
    }

    .nav-tabs .nav-link:hover:not(.active) {
      background-color: var(--light-purple);
      border-color: transparent;
    }
  </style>
</head>
<body>

  <!-- Sidebar -->
  <?php include 'sidebar.php'; ?>

  <!-- Content -->
  <div class="content">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
      <div class="container-fluid">
        <span class="navbar-brand">
          <i class="fas fa-wallet me-2"></i>
          Kasbon Manajer
        </span>
        <div class="d-flex align-items-center">
          <span class="text-white me-3">
            <i class="fas fa-user-circle me-1"></i>
            <?= htmlspecialchars($_SESSION['admin']['nama']) ?>
          </span>
          <a href="profile.php" class="btn btn-outline-light btn-sm me-2">
            <i class="fas fa-user-edit me-1"></i> Profil
          </a>
          <a href="logout.php" class="btn btn-outline-light btn-sm">
            <i class="fas fa-sign-out-alt me-1"></i> Logout
          </a>
        </div>
      </div>
    </nav>

    <div class="container-fluid">
      <!-- Page Header -->
      <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h1><i class="fas fa-wallet me-2"></i>Kasbon Manajer</h1>
            <p class="lead mb-0">Pantau kasbon manajer sebelum pembagian hasil</p>
          </div>
          <button type="button" class="btn btn-light btn-lg" data-bs-toggle="modal" data-bs-target="#addKasbonModal">
            <i class="fas fa-plus me-2"></i>
            Tambah Kasbon Manajer
          </button>
        </div>
      </div>
    
      <!-- Alert Messages -->
      <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['alert_type'] ?> alert-dismissible fade show" role="alert" id="autoCloseAlert">
          <i class="fas fa-<?= $_SESSION['alert_type'] == 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
          <?= $_SESSION['message'] ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php 
          unset($_SESSION['message']);
          unset($_SESSION['alert_type']);
        ?>
      <?php endif; ?>

      <!-- Filter Controls -->
      <div class="filter-card mb-4">
        <div class="card-header-actions mb-3">
          <h5 class="filter-title">
            <i class="fas fa-filter me-2"></i> Filter Kasbon Manajer
          </h5>
        </div>
        
        <!-- Filter tabs navigation -->
        <ul class="nav nav-tabs mb-3" id="filterTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link <?= !$filter_by_date ? 'active' : '' ?>" id="month-tab" data-bs-toggle="tab" data-bs-target="#month-filter" type="button" role="tab" aria-controls="month-filter" aria-selected="<?= !$filter_by_date ? 'true' : 'false' ?>">
              <i class="fas fa-calendar-alt me-1"></i> Filter Bulan
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link <?= $filter_by_date ? 'active' : '' ?>" id="date-range-tab" data-bs-toggle="tab" data-bs-target="#date-range-filter" type="button" role="tab" aria-controls="date-range-filter" aria-selected="<?= $filter_by_date ? 'true' : 'false' ?>">
              <i class="fas fa-calendar-week me-1"></i> Filter Rentang Tanggal
            </button>
          </li>
        </ul>
        
        <!-- Filter tabs content -->
        <div class="tab-content" id="filterTabsContent">
          <!-- Month Filter -->
          <div class="tab-pane fade <?= !$filter_by_date ? 'show active' : '' ?>" id="month-filter" role="tabpanel" aria-labelledby="month-tab">
            <div class="d-flex gap-2 justify-content-between align-items-center">
              <div class="month-badge">
                <i class="fas fa-calendar-alt"></i>
                <span>Periode: <?= date('F Y', strtotime($bulan_filter . '-01')) ?></span>
              </div>
              
              <div class="d-flex gap-2">
                <div class="input-group">
                  <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                  <input type="month" id="bulanTahunFilter" class="form-control" value="<?= $bulan_filter ?>" 
                        onchange="filterByMonth(this.value)">
                  <button class="btn btn-outline-secondary" onclick="setCurrentMonth()">
                    <i class="fas fa-calendar-day me-1"></i> Bulan Ini
                  </button>
                  <button class="btn btn-outline-secondary" onclick="setPrevMonth()">
                    <i class="fas fa-chevron-left"></i>
                  </button>
                  <button class="btn btn-outline-secondary" onclick="setNextMonth()">
                    <i class="fas fa-chevron-right"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Date Range Filter -->
          <div class="tab-pane fade <?= $filter_by_date ? 'show active' : '' ?>" id="date-range-filter" role="tabpanel" aria-labelledby="date-range-tab">
            <form action="" method="GET" class="row g-3" id="dateRangeForm">
              <div class="col-md-4">
                <div class="input-group">
                  <span class="input-group-text"><i class="fas fa-calendar-minus"></i></span>
                  <input type="date" class="form-control" id="dari_tanggal" name="dari_tanggal" 
                        value="<?= $dari_tanggal ?>" placeholder="Dari Tanggal">
                  <label for="dari_tanggal" class="input-group-text">Dari</label>
                </div>
              </div>
              
              <div class="col-md-4">
                <div class="input-group">
                  <span class="input-group-text"><i class="fas fa-calendar-plus"></i></span>
                  <input type="date" class="form-control" id="sampai_tanggal" name="sampai_tanggal" 
                        value="<?= $sampai_tanggal ?>" placeholder="Sampai Tanggal">
                  <label for="sampai_tanggal" class="input-group-text">Sampai</label>
                </div>
              </div>
              
              <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                  <i class="fas fa-filter me-1"></i> Terapkan Filter
                </button>
                <a href="javascript:void(0)" class="btn btn-outline-secondary" onclick="setTodayDateRangeAndSubmit()">
                  <i class="fas fa-calendar-day me-1"></i> Hari Ini Saja
                </a>
              </div>
            </form>
            
            <?php if ($filter_by_date): ?>
            <div class="mt-3">
              <div class="month-badge">
                <i class="fas fa-calendar-week"></i>
                <span>Filter aktif: <?= date('d F Y', strtotime($dari_tanggal)) ?> - <?= date('d F Y', strtotime($sampai_tanggal)) ?></span>
                <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-sm btn-outline-secondary ms-2 reset-btn">
                  <i class="fas fa-times"></i> Reset
                </a>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Summary Cards -->
      <div class="row g-4 mb-4">
        <div class="col-md-6 col-lg-3">
          <div class="summary-card">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h6 class="summary-title">Laba Bersih</h6>
                <h2 class="summary-value">Rp <?= number_format($laba_bersih, 0, ',', '.') ?></h2>
                <small class="text-white-50">Periode: <?= $filter_by_date ? date('d M Y', strtotime($dari_tanggal)) . ' - ' . date('d M Y', strtotime($sampai_tanggal)) : date('F Y', strtotime($bulan_filter . '-01')) ?></small><br>
                <small class="text-white-50">Total laba sebelum pembagian</small>
              </div>
              <i class="fas fa-chart-line fa-3x text-white-50"></i>
            </div>
          </div>
        </div>

        <!-- Card Bagian Manajer -->
        <div class="col-md-6 col-lg-3">
          <div class="summary-card profit">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h6 class="summary-title">Bagian Manajer (50%)</h6>
                <h2 class="summary-value">Rp <?= number_format($bagian_manajer, 0, ',', '.') ?></h2>
                <small class="text-white-50">50% dari laba bersih</small><br>
                <small class="text-white-50">Sebelum dikurangi kasbon</small>
              </div>
              <i class="fas fa-user-cog fa-3x text-white-50"></i>
            </div>
          </div>
        </div>

        <!-- Card Total Kasbon -->
        <div class="col-md-6 col-lg-3">
          <div class="summary-card warning">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h6 class="summary-title">Total Kasbon Manajer</h6>
                <h2 class="summary-value">Rp <?= number_format($total_kasbon, 0, ',', '.') ?></h2>
                <small class="text-white-50">Periode: <?= $filter_by_date ? date('d M Y', strtotime($dari_tanggal)) . ' - ' . date('d M Y', strtotime($sampai_tanggal)) : date('F Y', strtotime($bulan_filter . '-01')) ?></small><br>
                <small class="text-white-50">Akan dikurangkan dari bagian manajer</small>
              </div>
              <i class="fas fa-hand-holding-usd fa-3x text-white-50"></i>
            </div>
          </div>
        </div>

        <!-- Card Sisa Bagian Manajer -->
        <div class="col-md-6 col-lg-3">
          <div class="summary-card <?= $manajer_memiliki_hutang ? 'danger' : 'info' ?>">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h6 class="summary-title">Sisa Bagian Manajer</h6>
                <h2 class="summary-value">Rp <?= number_format($sisa_bagian_manajer, 0, ',', '.') ?></h2>
                <small class="text-white-50">Setelah dikurangi kasbon</small><br>
                <?php if ($manajer_memiliki_hutang): ?>
                  <small class="text-white-50"><i class="fas fa-exclamation-triangle me-1"></i> Manajer memiliki hutang kasbon</small>
                <?php else: ?>
                  <small class="text-white-50">Pendapatan bersih manajer</small>
                <?php endif; ?>
              </div>
              <i class="fas fa-coins fa-3x text-white-50"></i>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Daftar Kasbon Table -->
      <div class="data-card mb-4">
        <div class="card-header-actions">
          <h5 class="card-title">
            <i class="fas fa-list me-2"></i>
            Daftar Kasbon Manajer
          </h5>
        </div>
        
        <div class="table-responsive">
          <table class="table table-hover" id="kasbonTable">
            <thead>
              <tr>
                <th width="5%">#</th>
                <th width="15%">Tanggal</th>
                <th width="15%">Nama Manajer</th>
                <th width="15%">Jumlah</th>
                <th width="40%">Keterangan</th>
                <th width="10%">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php 
              if (!empty($kasbon_data)):
                $no = 1;
                foreach ($kasbon_data as $kasbon):
              ?>
              <tr>
                <td><?= $no++ ?></td>
                <td><?= date('d/m/Y', strtotime($kasbon['tanggal'])) ?></td>
                <td><?= htmlspecialchars($kasbon['nama']) ?></td>
                <td>Rp <?= number_format($kasbon['jumlah'], 0, ',', '.') ?></td>
                <td><?= nl2br(htmlspecialchars($kasbon['keterangan'])) ?></td>
                <td>
                  <button type="button" class="btn btn-sm btn-danger" 
                          onclick="showDeleteModal('<?= $kasbon['id'] ?>', '<?= number_format($kasbon['jumlah'], 0, ',', '.') ?>')">
                    <i class="fas fa-trash-alt"></i>
                  </button>
                </td>
              </tr>
              <?php 
                endforeach;
              else:
              ?>
              <tr>
                <td colspan="6" class="text-center py-4">Tidak ada data kasbon manajer pada periode yang dipilih</td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      
      <!-- Riwayat Pengeluaran -->
      <div class="data-card mb-4">
        <div class="card-header-actions">
          <h5 class="card-title">
            <i class="fas fa-info-circle me-2"></i>
            Informasi Pembagian Hasil
          </h5>
        </div>
        
        <div class="alert alert-info mb-4">
          <i class="fas fa-info-circle me-2"></i>
          <strong>Mekanisme Pembagian Hasil:</strong> Laba bersih bengkel dibagi 50:50 antara pemilik (admin) dan manajer. Kasbon yang diambil oleh manajer sebelum pembagian hasil akan dikurangkan dari bagian laba yang diterima manajer.
        </div>
        
        <div class="row">
          <div class="col-md-6">
            <div class="card h-100">
              <div class="card-body">
                <h5 class="card-title text-primary mb-3">Perhitungan Laba Bersih</h5>
                <table class="table">
                  <tbody>
                    <tr>
                      <td width="60%">Laba Kotor (dari penjualan produk)</td>
                      <td width="40%" class="text-end">Rp <?= number_format($total_keuntungan, 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                      <td>Pendapatan Barang Bekas (Masuk Kas)</td>
                      <td class="text-end">Rp <?= number_format($total_pendapatan_bekas, 0, ',', '.') ?></td>
                    </tr>
                    <?php if ($total_bekas_belum_kas > 0): ?>
                    <tr>
                      <td>Pendapatan Barang Bekas (Belum Masuk Kas)</td>
                      <td class="text-end">Rp <?= number_format($total_bekas_belum_kas, 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                      <td>Total Pendapatan Barang Bekas</td>
                      <td class="text-end fw-bold">Rp <?= number_format($total_pendapatan_bekas_all, 0, ',', '.') ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                      <td>Total Penerimaan</td>
                      <td class="text-end fw-bold">Rp <?= number_format($total_keuntungan + $total_pendapatan_bekas_all, 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                      <td>Biaya Operasional</td>
                      <td class="text-end text-danger">Rp <?= number_format($total_operasional, 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                      <td>Biaya Karyawan</td>
                      <td class="text-end text-danger">Rp <?= number_format($total_karyawan, 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                      <td>Total Biaya</td>
                      <td class="text-end fw-bold text-danger">Rp <?= number_format($total_biaya_operasional, 0, ',', '.') ?></td>
                    </tr>
                    <tr class="table-active">
                      <td class="fw-bold">Laba Bersih</td>
                      <td class="text-end fw-bold">Rp <?= number_format($laba_bersih, 0, ',', '.') ?></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          
          <div class="col-md-6">
            <div class="card h-100">
              <div class="card-body">
                <h5 class="card-title text-primary mb-3">Perhitungan Bagian Manajer</h5>
                <table class="table">
                  <tbody>
                    <tr>
                      <td width="60%">Laba Bersih</td>
                      <td width="40%" class="text-end">Rp <?= number_format($laba_bersih, 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                      <td>Persentase Bagian Manajer</td>
                      <td class="text-end">50%</td>
                    </tr>
                    <tr>
                      <td>Bagian Manajer (sebelum kasbon)</td>
                      <td class="text-end fw-bold">Rp <?= number_format($bagian_manajer, 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                      <td>Total Kasbon Manajer</td>
                      <td class="text-end text-danger">Rp <?= number_format($total_kasbon, 0, ',', '.') ?></td>
                    </tr>
                    <tr class="table-active">
                      <td class="fw-bold">Sisa Bagian Manajer</td>
                      <td class="text-end fw-bold <?= $sisa_bagian_manajer < 0 ? 'text-danger' : '' ?>">Rp <?= number_format($sisa_bagian_manajer, 0, ',', '.') ?></td>
                    </tr>
                    <?php if ($sisa_bagian_manajer < 0): ?>
                    <tr>
                      <td colspan="2" class="text-danger">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        Manajer memiliki hutang kasbon yang melebihi bagian laba bulan ini
                      </td>
                    </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Panduan Pengelolaan Kasbon -->
      <div class="data-card mb-4">
        <div class="card-header-actions">
          <h5 class="card-title">
            <i class="fas fa-book me-2"></i>
            Panduan Pengelolaan Kasbon Manajer
          </h5>
        </div>
        
        <div class="row g-4">
          <div class="col-md-6">
            <div class="card h-100 border-0 shadow-sm">
              <div class="card-body">
                <h5 class="card-title text-primary"><i class="fas fa-check-circle me-2"></i>Praktik Terbaik</h5>
                <ul class="list-group list-group-flush">
                  <li class="list-group-item">Tetapkan batas maksimal kasbon manajer (misalnya 50% dari perkiraan bagian laba)</li>
                  <li class="list-group-item">Catat setiap kasbon dengan tanggal, jumlah, dan keterangan yang jelas</li>
                  <li class="list-group-item">Lakukan evaluasi berkala terhadap proporsi kasbon terhadap laba</li>
                  <li class="list-group-item">Komunikasikan dengan jelas tentang jumlah kasbon dan sisa bagian manajer</li>
                  <li class="list-group-item">Buat mekanisme pelunasan kasbon jika melebihi bagian manajer</li>
                </ul>
              </div>
            </div>
          </div>
          
          <div class="col-md-6">
            <div class="card h-100 border-0 shadow-sm">
              <div class="card-body">
                <h5 class="card-title text-danger"><i class="fas fa-times-circle me-2"></i>Hal yang Dihindari</h5>
                <ul class="list-group list-group-flush">
                  <li class="list-group-item">Memberikan kasbon tanpa pencatatan yang jelas</li>
                  <li class="list-group-item">Mengizinkan kasbon yang terus menumpuk melebihi bagian laba</li>
                  <li class="list-group-item">Tidak melakukan rekonsiliasi kasbon setiap bulan</li>
                  <li class="list-group-item">Memberikan kasbon tanpa kesepakatan mengenai metode pembayaran</li>
                  <li class="list-group-item">Mengabaikan tren kasbon yang terus meningkat dari waktu ke waktu</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Add Kasbon Modal -->
  <div class="modal fade" id="addKasbonModal" tabindex="-1" aria-labelledby="addKasbonModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="addKasbonModalLabel">
            <i class="fas fa-plus-circle me-2"></i>
            Tambah Kasbon Manajer
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form action="" method="POST">
          <div class="modal-body">
            <input type="hidden" name="action" value="add_kasbon">
            
            <div class="alert alert-info mb-3">
              <i class="fas fa-info-circle me-2"></i>
              Kasbon akan dikurangkan dari bagian laba manajer periode ini, tetapi tidak mempengaruhi perhitungan laba bersih bengkel.
            </div>
            
            <div class="mb-3">
              <label for="id_manajer" class="form-label">Manajer</label>
              <select class="form-select" id="id_manajer" name="id_manajer" required>
                <option value="" selected disabled>Pilih Manajer</option>
                <?php foreach ($manajer_list as $manajer): ?>
                <option value="<?= $manajer['id_manajer'] ?>">
                  <?= htmlspecialchars($manajer['nama']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            
            <div class="mb-3">
              <label for="jumlah" class="form-label">Jumlah Kasbon (Rp)</label>
              <div class="input-group">
                <span class="input-group-text">Rp</span>
                <input type="number" class="form-control" id="jumlah" name="jumlah" placeholder="Masukkan jumlah kasbon" required>
              </div>
            </div>
            
            <div class="mb-3">
              <label for="tanggal" class="form-label">Tanggal</label>
              <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= date('Y-m-d') ?>" required>
            </div>
            
            <div class="mb-3">
              <label for="keterangan" class="form-label">Keterangan</label>
              <textarea class="form-control" id="keterangan" name="keterangan" rows="3" placeholder="Tambahkan alasan atau detail kasbon"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
              <i class="fas fa-times me-1"></i>
              Batal
            </button>
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save me-1"></i>
              Simpan Kasbon
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <!-- Delete Confirmation Modal -->
  <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="deleteModalLabel">
            <i class="fas fa-exclamation-triangle text-danger me-2"></i>
            Konfirmasi Hapus
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p>Anda yakin ingin menghapus kasbon manajer sebesar <strong>Rp <span id="deleteAmount"></span></strong>?</p>
          <p class="text-danger mb-0"><small>Tindakan ini tidak dapat dibatalkan.</small></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="fas fa-times me-1"></i>
            Batal
          </button>
          <a href="#" id="deleteConfirmButton" class="btn btn-danger">
            <i class="fas fa-trash-alt me-1"></i>
            Ya, Hapus
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  
  <script>
    $(document).ready(function() {
      // Initialize DataTable if there are rows
      if ($('#kasbonTable tbody tr').length > 1 || !$('#kasbonTable tbody tr td[colspan]').length) {
        $('#kasbonTable').DataTable({
          language: {
            url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json"
          },
          responsive: true,
          paging: true,
          searching: true,
          info: true,
          ordering: true,
          "order": [[1, "desc"]]
        });
      }
      
      // Auto close alert
      setTimeout(function() {
        $("#autoCloseAlert").alert('close');
      }, 5000);
      
      // Initialize date range filter
      initDateRangeFilter();
    });
    
    // Month-Year filter navigation functions
    function filterByMonth(value) {
      window.location.href = '?bulan=' + value;
    }
    
    function setCurrentMonth() {
      const now = new Date();
      const year = now.getFullYear();
      const month = (now.getMonth() + 1).toString().padStart(2, '0');
      const currentMonth = `${year}-${month}`;
      
      document.getElementById('bulanTahunFilter').value = currentMonth;
      window.location.href = '?bulan=' + currentMonth;
    }
    
    function setPrevMonth() {
      const currentValue = document.getElementById('bulanTahunFilter').value;
      const [year, month] = currentValue.split('-').map(num => parseInt(num));
      
      let prevMonth = month - 1;
      let prevYear = year;
      
      if (prevMonth < 1) {
        prevMonth = 12;
        prevYear -= 1;
      }
      
      const formattedMonth = prevMonth.toString().padStart(2, '0');
      const newValue = `${prevYear}-${formattedMonth}`;
      
      document.getElementById('bulanTahunFilter').value = newValue;
      window.location.href = '?bulan=' + newValue;
    }
    
    function setNextMonth() {
      const currentValue = document.getElementById('bulanTahunFilter').value;
      const [year, month] = currentValue.split('-').map(num => parseInt(num));
      
      let nextMonth = month + 1;
      let nextYear = year;
      
      if (nextMonth > 12) {
        nextMonth = 1;
        nextYear += 1;
      }
      
      const formattedMonth = nextMonth.toString().padStart(2, '0');
      const newValue = `${nextYear}-${formattedMonth}`;
      
      document.getElementById('bulanTahunFilter').value = newValue;
      window.location.href = '?bulan=' + newValue;
    }
    
    // Delete modal function
    function showDeleteModal(id, amount) {
      $('#deleteAmount').text(amount);
      
      // Set href sesuai dengan filter yang aktif
      const urlParams = new URLSearchParams(window.location.search);
      let deleteUrl = `?delete=${id}`;
      
      if (urlParams.has('dari_tanggal') && urlParams.has('sampai_tanggal')) {
        deleteUrl += `&dari_tanggal=${urlParams.get('dari_tanggal')}&sampai_tanggal=${urlParams.get('sampai_tanggal')}`;
      } else if (urlParams.has('bulan')) {
        deleteUrl += `&bulan=${urlParams.get('bulan')}`;
      }
      
      $('#deleteConfirmButton').attr('href', deleteUrl);
      
      // Show modal
      var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
      deleteModal.show();
    }
    
    // Fungsi untuk Date Range Filter
    function setTodayDateRangeAndSubmit() {
      const today = new Date();
      const year = today.getFullYear();
      const month = (today.getMonth() + 1).toString().padStart(2, '0');
      const day = today.getDate().toString().padStart(2, '0');
      const formattedDate = `${year}-${month}-${day}`;
      
      // Set both from and to dates to today
      document.getElementById('dari_tanggal').value = formattedDate;
      document.getElementById('sampai_tanggal').value = formattedDate;
      
      // Submit the form immediately
      document.getElementById('dateRangeForm').submit();
    }
    
    // Function to initialize date range filter
    function initDateRangeFilter() {
      // If date range parameters are present, activate date range tab
      const urlParams = new URLSearchParams(window.location.search);
      
      if (urlParams.has('dari_tanggal') && urlParams.has('sampai_tanggal')) {
        // Tab should be already active via PHP, this is just a backup
        if (!document.querySelector('#date-range-tab').classList.contains('active')) {
          document.querySelector('#date-range-tab').click();
        }
      }
    }
  </script>
</body>
</html>