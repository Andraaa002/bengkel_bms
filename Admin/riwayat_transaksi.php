<?php
session_start();

// Cek apakah sudah login dan sebagai admin dengan namespace baru
if (!isset($_SESSION['admin']['logged_in']) || $_SESSION['admin']['logged_in'] !== true) {
  header("Location: ../login.php");
  exit();
}

include '../config.php'; // Pastikan path ke config.php benar

// Set default date filters
$today = date('Y-m-d');
$current_month = date('Y-m');
$first_day_of_month = date('Y-m-01');
$last_day_of_month = date('Y-m-t');

// Get bulan from params or use current month
$bulan_filter = isset($_GET['bulan']) ? $_GET['bulan'] : $current_month;

// Set date ranges berdasarkan parameter GET
$dari_tanggal = isset($_GET['dari_tanggal']) ? $_GET['dari_tanggal'] : $first_day_of_month;
$sampai_tanggal = isset($_GET['sampai_tanggal']) ? $_GET['sampai_tanggal'] : $last_day_of_month;

// Cek apakah filter rentang tanggal aktif
$filter_by_date = isset($_GET['dari_tanggal']) && isset($_GET['sampai_tanggal']);

// Use the filter to set date range for queries
if ($filter_by_date) {
  $tgl_awal = $dari_tanggal;
  $tgl_akhir = $sampai_tanggal;
} else {
  $first_day = date('Y-m-01', strtotime($bulan_filter . '-01'));
  $last_day = date('Y-m-t', strtotime($bulan_filter . '-01'));
  $tgl_awal = $first_day;
  $tgl_akhir = $last_day;
}

// Build where clause for filters
$where_clause = " WHERE 1=1 ";

// Filter berdasarkan rentang tanggal
if (!empty($tgl_awal) && !empty($tgl_akhir)) {
    $where_clause .= " AND DATE(t.tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir' ";
}

// Pagination
$limit = 10; // Jumlah data per halaman
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Query untuk mendapatkan data transaksi dengan harga beli dan harga jual
$query = "SELECT t.id, t.tanggal, t.total, t.pendapatan, t.hutang, u.nama AS kasir,
          SUM(td.jumlah * IFNULL(p.harga_beli, 0)) AS total_harga_beli,
          SUM(td.subtotal) AS total_harga_jual,
          (SUM(td.subtotal) - SUM(td.jumlah * IFNULL(p.harga_beli, 0))) AS keuntungan
          FROM transaksi t
          JOIN karyawan u ON t.kasir = u.username
          JOIN transaksi_detail td ON t.id = td.transaksi_id
          LEFT JOIN produk p ON td.produk_id = p.id
          $where_clause
          GROUP BY t.id, t.tanggal, t.total, u.nama
          ORDER BY t.tanggal DESC
          LIMIT $start, $limit";
$result = mysqli_query($conn, $query);

// Error handling for main query
if ($result === false) {
    error_log("Query failed: " . mysqli_error($conn) . " SQL: " . $query);
    $result = [];
}

// Hitung total transaksi untuk pagination
$count_query = "SELECT COUNT(DISTINCT t.id) AS total_records
                FROM transaksi t
                JOIN karyawan u ON t.kasir = u.username
                JOIN transaksi_detail td ON t.id = td.transaksi_id
                LEFT JOIN produk p ON td.produk_id = p.id
                $where_clause";
$count_result = mysqli_query($conn, $count_query);

// Error handling for count_query
if ($count_result === false) {
    error_log("Count query failed: " . mysqli_error($conn) . " SQL: " . $count_query);
    $total_records = 0;
} else {
    $count_row = mysqli_fetch_assoc($count_result);
    $total_records = $count_row['total_records'];
}

$total_pages = ceil($total_records / $limit);

// Hitung total pendapatan dan laba dengan filter
$summary_query = "SELECT 
                SUM(t.total) AS total_pendapatan,
                SUM(t.pendapatan) AS total_kas,
                SUM(t.hutang) AS total_hutang,
                SUM((SELECT SUM(td.subtotal) - SUM(td.jumlah * IFNULL(p.harga_beli, 0)) 
                     FROM transaksi_detail td 
                     LEFT JOIN produk p ON td.produk_id = p.id 
                     WHERE td.transaksi_id = t.id)) AS total_laba
                FROM transaksi t
                $where_clause";
$summary_result = mysqli_query($conn, $summary_query);

// Error handling for summary_query
if ($summary_result === false) {
    error_log("Summary query failed: " . mysqli_error($conn) . " SQL: " . $summary_query);
    $total_pendapatan = 0;
    $total_kas = 0;
    $total_hutang = 0;
    $total_laba = 0;
} else {
    $summary_row = mysqli_fetch_assoc($summary_result);
    $total_pendapatan = $summary_row['total_pendapatan'] ?: 0;
    $total_kas = $summary_row['total_kas'] ?: 0;
    $total_hutang = $summary_row['total_hutang'] ?: 0;
    $total_laba = $summary_row['total_laba'] ?: 0;
}

// Get available months for filter
$months_query = "SELECT DISTINCT DATE_FORMAT(tanggal, '%Y-%m') AS bulan FROM transaksi ORDER BY bulan DESC";
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
  <title>Riwayat Transaksi - BMS Bengkel</title>
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
    
    .btn-outline-primary {
      color: var(--primary-purple);
      border-color: var(--primary-purple);
      border-radius: 8px;
      font-weight: 500;
      transition: all 0.3s ease;
    }
    
    .btn-outline-primary:hover {
      background-color: var(--primary-purple);
      color: var(--white);
      transform: translateY(-2px);
      box-shadow: 0 4px 10px rgba(126, 87, 194, 0.2);
    }
    
    .btn-outline-light, .btn-outline-primary, .btn-outline-secondary {
      border-radius: 8px;
      font-weight: 500;
      transition: all 0.3s ease;
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
    
    /* Pagination styling */
    .pagination {
      margin-bottom: 0;
    }
    
    .pagination .page-item .page-link {
      color: var(--primary-purple);
      padding: 0.5rem 1rem;
      border-radius: 8px;
      margin: 0 3px;
    }
    
    .pagination .page-item.active .page-link {
      background: linear-gradient(135deg, var(--primary-purple), var(--secondary-purple));
      border-color: var(--primary-purple);
      color: white;
      box-shadow: 0 2px 5px rgba(126, 87, 194, 0.3);
    }
    
    .pagination .page-item .page-link:hover {
      background-color: var(--light-purple);
    }
    
    .pagination .page-item.active .page-link:hover {
      background: linear-gradient(135deg, var(--accent-purple), var(--primary-purple));
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
    
    /* Nav tabs styling */
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
    
    /* Responsive media queries */
    @media (max-width: 992px) {
      .content {
        margin-left: 0;
      }
    }
    
    @media (max-width: 768px) {
      .data-card, .filter-card, .table-card {
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
          <i class="fas fa-history me-2"></i>
          Riwayat Transaksi
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
            <h1><i class="fas fa-history me-2"></i>Riwayat Transaksi</h1>
            <p class="lead mb-0">Lihat dan cari histori semua transaksi penjualan di sistem</p>
          </div>
          <?php if ($filter_by_date): ?>
            <span class="badge bg-light text-primary p-3 fs-6">
              <i class="fas fa-calendar-alt me-1"></i> 
              <?= date('d M Y', strtotime($dari_tanggal)) ?> - <?= date('d M Y', strtotime($sampai_tanggal)) ?>
            </span>
          <?php else: ?>
            <span class="badge bg-light text-primary p-3 fs-6">
              <i class="fas fa-calendar-alt me-1"></i> 
              <?= date('F Y', strtotime($bulan_filter . '-01')) ?>
            </span>
          <?php endif; ?>
        </div>
      </div>
      
      <!-- Filter Controls - synchronized with manajer_pengeluaran.php -->
      <div class="filter-card mb-4">
        <div class="card-header-actions mb-3">
          <h5 class="filter-title">
            <i class="fas fa-filter me-2"></i> Filter Transaksi
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
                <h6 class="summary-title">Total Transaksi</h6>
                <h2 class="summary-value"><?= number_format($total_records) ?></h2>
                <small class="text-white-50">Jumlah transaksi</small>
              </div>
              <i class="fas fa-receipt fa-3x text-white-50"></i>
            </div>
          </div>
        </div>
        
        <div class="col-md-6 col-lg-3">
          <div class="summary-card info">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h6 class="summary-title">Total Penjualan Produk</h6>
                <h2 class="summary-value">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></h2>
                <small class="text-white-50">Total nilai transaksi</small>
              </div>
              <i class="fas fa-money-bill-wave fa-3x text-white-50"></i>
            </div>
          </div>
        </div>
        
        <div class="col-md-6 col-lg-3">
          <div class="summary-card warning">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h6 class="summary-title">Total Piutang</h6>
                <h2 class="summary-value">Rp <?= number_format($total_hutang, 0, ',', '.') ?></h2>
                <small class="text-white-50">Belum dibayarkan</small>
              </div>
              <i class="fas fa-exclamation-circle fa-3x text-white-50"></i>
            </div>
          </div>
        </div>
        
        <div class="col-md-6 col-lg-3">
          <div class="summary-card profit">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h6 class="summary-title">Total Laba Kotor</h6>
                <h2 class="summary-value">Rp <?= number_format($total_laba, 0, ',', '.') ?></h2>
                <small class="text-white-50">Estimasi keuntungan</small>
              </div>
              <i class="fas fa-chart-line fa-3x text-white-50"></i>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Transaction History Table -->
      <div class="data-card mb-4">
        <div class="card-header-actions">
          <h5 class="card-title">
            <i class="fas fa-list me-2"></i>
            Daftar Transaksi
          </h5>
          <div>
            <a href="export_transaksi_pdf.php?tgl_awal=<?= $tgl_awal ?>&tgl_akhir=<?= $tgl_akhir ?>" class="btn btn-danger btn-sm">
              <i class="fas fa-file-pdf me-2"></i> Export PDF
            </a>
            <a href="export_transaksi_excel.php?tgl_awal=<?= $tgl_awal ?>&tgl_akhir=<?= $tgl_akhir ?>&format=excel" class="btn btn-success btn-sm ms-2">
              <i class="fas fa-file-excel me-2"></i> Export Excel
            </a>
          </div>
        </div>
        
        <div class="table-responsive">
          <table class="table table-hover table-striped" id="transaksiTable">
            <thead>
              <tr>
                <th>ID Transaksi</th>
                <th>Tanggal</th>
                <th>Kasir</th>
                <th>Total Pendapatan</th>
                <th>Uang Masuk</th>
                <th>Piutang</th>
                <th>Harga Beli</th>
                <th>Laba Kotor</th>
                <th class="text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($result && mysqli_num_rows($result) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                  <td><?= $row['id'] ?></td>
                  <td><?= date('d/m/Y H:i', strtotime($row['tanggal'])) ?></td>
                  <td><?= htmlspecialchars($row['kasir']) ?></td>
                  <td class="fw-medium text-primary">Rp <?= number_format($row['total'], 0, ',', '.') ?></td>
                  <td class="text-success">Rp <?= number_format($row['pendapatan'], 0, ',', '.') ?></td>
                  <td>
                    <?php if ($row['hutang'] > 0): ?>
                      <span class="fw-bold text-danger" style="background-color: #ffecec; padding: 5px 10px; border-radius: 6px; display: inline-block;">
                        <i class="fas fa-exclamation-circle me-1"></i>
                        Rp <?= number_format($row['hutang'], 0, ',', '.') ?>
                      </span>
                    <?php else: ?>
                      <span class="text-success">Rp <?= number_format($row['hutang'], 0, ',', '.') ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="text-muted">Rp <?= number_format($row['total_harga_beli'], 0, ',', '.') ?></td>
                  <td class="fw-medium text-success">Rp <?= number_format($row['keuntungan'], 0, ',', '.') ?></td>
                  <td class="text-center">
                    <a href="detail_transaksi.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">
                      <i class="fas fa-eye me-1"></i> Detail
                    </a>
                  </td>
                </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="9" class="text-center py-4">
                    <img src="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/icons/receipt.svg" alt="No Data" width="64" height="64" class="mb-3 opacity-50">
                    <p class="text-muted mb-0 fw-medium">Tidak ada data transaksi pada periode yang dipilih.</p>
                    <p class="text-muted small">Silakan ubah filter tanggal untuk melihat data lainnya.</p>
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        
        <?php if ($total_pages > 1): ?>
          <div class="card-footer bg-white py-3">
            <nav aria-label="Halaman transaksi">
              <ul class="pagination justify-content-center mb-0">
                <!-- Previous Button -->
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                  <a class="page-link" href="?<?= $filter_by_date ? 'dari_tanggal='.$dari_tanggal.'&sampai_tanggal='.$sampai_tanggal : 'bulan='.$bulan_filter ?>&page=<?= $page - 1 ?>">
                    <i class="fas fa-chevron-left"></i>
                  </a>
                </li>
                
                <!-- Page Numbers -->
                <?php
                  // Determine start and end page numbers to show
                  $start_page = max(1, $page - 2);
                  $end_page = min($total_pages, $page + 2);
                  
                  // Show first page if not in range
                  if ($start_page > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?'.($filter_by_date ? 'dari_tanggal='.$dari_tanggal.'&sampai_tanggal='.$sampai_tanggal : 'bulan='.$bulan_filter).'&page=1">1</a></li>';
                    if ($start_page > 2) {
                      echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                    }
                  }
                  
                  // Page links
                  for ($i = $start_page; $i <= $end_page; $i++) {
                    echo '<li class="page-item ' . (($page == $i) ? 'active' : '') . '">
                            <a class="page-link" href="?'.($filter_by_date ? 'dari_tanggal='.$dari_tanggal.'&sampai_tanggal='.$sampai_tanggal : 'bulan='.$bulan_filter).'&page=' . $i . '">' . $i . '</a>
                          </li>';
                  }
                  
                  // Show last page if not in range
                  if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                      echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                    }
                    echo '<li class="page-item"><a class="page-link" href="?'.($filter_by_date ? 'dari_tanggal='.$dari_tanggal.'&sampai_tanggal='.$sampai_tanggal : 'bulan='.$bulan_filter).'&page=' . $total_pages . '">' . $total_pages . '</a></li>';
                  }
                ?>
                
                <!-- Next Button -->
                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                  <a class="page-link" href="?<?= $filter_by_date ? 'dari_tanggal='.$dari_tanggal.'&sampai_tanggal='.$sampai_tanggal : 'bulan='.$bulan_filter ?>&page=<?= $page + 1 ?>">
                    <i class="fas fa-chevron-right"></i>
                  </a>
                </li>
              </ul>
            </nav>
          </div>
        <?php endif; ?>
        
        <div class="d-flex justify-content-between align-items-center mt-3 px-3">
          <div class="text-muted small">
            <i class="fas fa-info-circle me-1"></i> Menampilkan <?= min($limit, mysqli_num_rows($result)) ?> dari <?= number_format($total_records) ?> transaksi
          </div>
        </div>
      </div>
      
      <!-- Informasi Pembagian dan Petunjuk -->
      <div class="row">
        <div class="col-md-6">
          <div class="data-card mb-4">
            <div class="card-header-actions">
              <h5 class="card-title">
                <i class="fas fa-info-circle me-2"></i>
                Informasi Transaksi
              </h5>
            </div>
            
            <div class="alert alert-info mb-4">
              <i class="fas fa-info-circle me-2"></i>
              <strong>Transaksi Bengkel:</strong> Halaman ini menampilkan semua transaksi penjualan yang tercatat di sistem kasir bengkel. Anda dapat melihat detail setiap transaksi dengan mengklik tombol "Detail" pada setiap baris transaksi.
            </div>
            
            <div class="row">
              <div class="col-md-12">
                <div class="card h-100 border-0 shadow-sm">
                  <div class="card-body">
                    <h5 class="card-title text-primary mb-3">Keterangan Kolom</h5>
                    <table class="table">
                      <tbody>
                        <tr>
                          <td width="40%"><strong>Total Pendapatan</strong></td>
                          <td width="60%">Nilai total transaksi termasuk piutang</td>
                        </tr>
                        <tr>
                          <td><strong>Uang Masuk</strong></td>
                          <td>Jumlah uang yang benar-benar masuk ke kas</td>
                        </tr>
                        <tr>
                          <td><strong>Piutang</strong></td>
                          <td>Jumlah yang belum dibayarkan oleh pelanggan</td>
                        </tr>
                        <tr>
                          <td><strong>Harga Beli</strong></td>
                          <td>Total modal pembelian produk</td>
                        </tr>
                        <tr>
                          <td><strong>Laba Kotor</strong></td>
                          <td>Keuntungan kotor sebelum dikurangi biaya operasional</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="col-md-6">
          <div class="data-card mb-4">
            <div class="card-header-actions">
              <h5 class="card-title">
                <i class="fas fa-search me-2"></i>
                Tips Pencarian
              </h5>
            </div>
            
            <div class="row g-4">
              <div class="col-md-6">
                <div class="card h-100 border-0 shadow-sm">
                  <div class="card-body">
                    <h5 class="card-title text-primary"><i class="fas fa-calendar-alt me-2"></i>Periode</h5>
                    <ul class="list-group list-group-flush">
                      <li class="list-group-item">Gunakan filter bulan untuk melihat transaksi bulanan</li>
                      <li class="list-group-item">Gunakan filter rentang tanggal untuk periode spesifik</li>
                      <li class="list-group-item">Tombol "Bulan Ini" untuk melihat data bulan berjalan</li>
                      <li class="list-group-item">Tombol "Hari Ini" untuk melihat transaksi hari ini saja</li>
                    </ul>
                  </div>
                </div>
              </div>
              
              <div class="col-md-6">
                <div class="card h-100 border-0 shadow-sm">
                  <div class="card-body">
                    <h5 class="card-title text-primary"><i class="fas fa-file-export me-2"></i>Export Data</h5>
                    <ul class="list-group list-group-flush">
                      <li class="list-group-item">Gunakan tombol "Export PDF" untuk laporan dalam format PDF</li>
                      <li class="list-group-item">Gunakan tombol "Export Excel" untuk analisis data lebih lanjut</li>
                      <li class="list-group-item">Data yang diexport sesuai dengan filter yang aktif</li>
                      <li class="list-group-item">Semua kolom data termasuk dalam file export</li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
          </div>
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
      if ($('#transaksiTable tbody tr').length > 1 || !$('#transaksiTable tbody tr td[colspan]').length) {
        $('#transaksiTable').DataTable({
          language: {
            url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json"
          },
          responsive: true,
          paging: false, // Pagination is handled by our custom code
          searching: true,
          info: true,
          ordering: true,
          "order": [[1, "desc"]]
        });
      }
      
      // Initialize date range filter
      initDateRangeFilter();
    });
    
    // Filter functions - synchronized with manajer_pengeluaran.php
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
    
    // Date range filter functions
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
    
    // When document is loaded
    document.addEventListener('DOMContentLoaded', function() {
      // Initialize date range filter
      initDateRangeFilter();
      
      // Auto close alerts after 5 seconds if present
      setTimeout(function() {
        const alerts = document.querySelectorAll('.alert.alert-dismissible');
        alerts.forEach(function(alert) {
          const closeBtn = alert.querySelector('.btn-close');
          if (closeBtn) {
            closeBtn.click();
          }
        });
      }, 5000);
    });
  </script>
</body>
</html>