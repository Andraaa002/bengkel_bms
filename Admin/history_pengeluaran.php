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

// Get active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'semua';

// Build where clause for filters (pengeluaran table)
$where_clause = " WHERE 1=1 ";

// Filter berdasarkan rentang tanggal (pengeluaran table)
if (!empty($tgl_awal) && !empty($tgl_akhir)) {
    $where_clause .= " AND DATE(p.tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir' ";
}

// Filter berdasarkan tab aktif (pengeluaran table)
if ($active_tab == 'operasional') {
    $where_clause .= " AND p.kategori NOT IN ('Kasbon Karyawan', 'Uang Makan', 'Gaji Karyawan', 'Pembelian Sparepart', 'Pembelian Barang', 'Bayar Hutang Produk') 
                        AND p.keterangan NOT LIKE '%produk:%' 
                        AND p.keterangan NOT LIKE '%produk baru:%' 
                        AND p.keterangan NOT LIKE 'Penambahan stok produk:%' ";
} elseif ($active_tab == 'karyawan') {
    $where_clause .= " AND p.kategori IN ('Kasbon Karyawan', 'Uang Makan', 'Gaji Karyawan') ";
} elseif ($active_tab == 'produk') {
    $where_clause .= " AND (
                            p.kategori IN ('Pembelian Sparepart', 'Pembelian Barang') OR 
                            p.keterangan LIKE '%produk:%' OR 
                            p.keterangan LIKE '%produk baru:%' OR 
                            p.keterangan LIKE 'Penambahan stok produk:%'
                        ) ";
} elseif ($active_tab == 'bayar_hutang_produk') {
    $where_clause .= " AND p.kategori = 'Bayar Hutang Produk' ";
}

// Build where clause for piutang_cair table
$pc_where_clause = " WHERE pc.transaksi_id = '-1'"; // Only include product debt payments (not transaction debts)

// Filter berdasarkan rentang tanggal (piutang_cair table)
if (!empty($tgl_awal) && !empty($tgl_akhir)) {
    $pc_where_clause .= " AND DATE(pc.tanggal_bayar) BETWEEN '$tgl_awal' AND '$tgl_akhir' ";
}

// Only include Bayar Hutang Produk tab for piutang_cair
$include_piutang_cair = ($active_tab == 'semua' || $active_tab == 'bayar_hutang_produk');

// Pagination
$limit = 5; // Jumlah data per halaman
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Get available month filter - gunakan kode yang sama dengan riwayat_transaksi.php
$months_query = "SELECT DISTINCT DATE_FORMAT(tanggal, '%Y-%m') AS bulan FROM pengeluaran 
                UNION 
                SELECT DISTINCT DATE_FORMAT(tanggal_bayar, '%Y-%m') AS bulan FROM piutang_cair 
                WHERE transaksi_id = '-1'
                ORDER BY bulan DESC";
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

// Query to get expenses from pengeluaran table
$query = "SELECT p.id, p.tanggal, p.kategori, p.jumlah, p.keterangan, p.created_at,
           CASE 
             WHEN p.keterangan LIKE 'Pembelian produk baru:%' THEN 'Pembelian Sparepart Baru'
             WHEN p.keterangan LIKE '%produk baru:%' AND p.keterangan LIKE '%pembayaran awal%' THEN 'Pembelian Sparepart Baru'
             WHEN p.keterangan LIKE 'Penambahan stok produk:%' THEN 'Tambah Stok'
             WHEN p.kategori = 'Pembelian Sparepart' THEN 'Pembelian Sparepart Baru'
             WHEN p.kategori = 'Pembelian Barang' THEN 'Pembelian Sparepart Baru'
             WHEN p.kategori = 'Bayar Hutang Produk' THEN 'Bayar Hutang Produk'
             ELSE p.kategori
           END as kategori_transaksi,
           'pengeluaran' as source_table
          FROM pengeluaran p
          $where_clause";

// Query to get payments from piutang_cair table
if ($include_piutang_cair) {
    $pc_query = "SELECT 
                pc.id, 
                pc.tanggal_bayar as tanggal, 
                'Bayar Hutang Produk' as kategori, 
                pc.jumlah_bayar as jumlah, 
                pc.keterangan, 
                pc.created_at,
                'Bayar Hutang Produk' as kategori_transaksi,
                'piutang_cair' as source_table
              FROM piutang_cair pc
              $pc_where_clause";
    
    // Combine the two queries with UNION
    $query = "($query) UNION ($pc_query) ORDER BY tanggal DESC, id DESC LIMIT $start, $limit";
} else {
    // Add ordering and limit if only using pengeluaran table
    $query .= " ORDER BY p.tanggal DESC, p.id DESC LIMIT $start, $limit";
}

$result = mysqli_query($conn, $query);

// Error handling for main query
if ($result === false) {
    error_log("Query failed: " . mysqli_error($conn) . " SQL: " . $query);
    $result = [];
}

// Hitung total pengeluaran untuk pagination
$count_query = "SELECT COUNT(*) AS total_records
                FROM pengeluaran p
                $where_clause";

// Add piutang_cair records to count if needed
if ($include_piutang_cair) {
    $pc_count_query = "SELECT COUNT(*) AS pc_records
                      FROM piutang_cair pc
                      $pc_where_clause";
                      
    // First get the count from pengeluaran table
    $pengeluaran_count_result = mysqli_query($conn, $count_query);
    $pengeluaran_count = 0;
    
    if ($pengeluaran_count_result) {
        $pengeluaran_count_row = mysqli_fetch_assoc($pengeluaran_count_result);
        $pengeluaran_count = $pengeluaran_count_row['total_records'];
    }
    
    // Then get the count from piutang_cair table
    $pc_count_result = mysqli_query($conn, $pc_count_query);
    $pc_count = 0;
    
    if ($pc_count_result) {
        $pc_count_row = mysqli_fetch_assoc($pc_count_result);
        $pc_count = $pc_count_row['pc_records'];
    }
    
    // Combine the counts
    $total_records = $pengeluaran_count + $pc_count;
} else {
    // Just use the pengeluaran count
    $count_result = mysqli_query($conn, $count_query);
    
    if ($count_result === false) {
        error_log("Count query failed: " . mysqli_error($conn) . " SQL: " . $count_query);
        $total_records = 0;
    } else {
        $count_row = mysqli_fetch_assoc($count_result);
        $total_records = $count_row['total_records'];
    }
}

$total_pages = ceil($total_records / $limit);

// Modify the summary query section to better handle NULL values and ensure proper formatting
$summary_query = "SELECT 
                  COALESCE((SELECT SUM(p.jumlah) FROM pengeluaran p WHERE DATE(p.tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir'), 0) +
                  COALESCE((SELECT SUM(pc.jumlah_bayar) FROM piutang_cair pc WHERE pc.transaksi_id = '-1' AND DATE(pc.tanggal_bayar) BETWEEN '$tgl_awal' AND '$tgl_akhir'), 0) 
                  as total_pengeluaran,

                  COALESCE((SELECT SUM(CASE WHEN p.kategori IN ('Kasbon Karyawan', 'Uang Makan', 'Gaji Karyawan') THEN p.jumlah ELSE 0 END) 
                   FROM pengeluaran p WHERE DATE(p.tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir'), 0)
                  as total_karyawan,

                  COALESCE((SELECT SUM(CASE WHEN p.kategori IN ('Pembelian Sparepart', 'Pembelian Barang') OR 
                              p.keterangan LIKE '%produk:%' OR p.keterangan LIKE '%produk baru:%' OR 
                              p.keterangan LIKE 'Penambahan stok produk:%' THEN p.jumlah ELSE 0 END) 
                   FROM pengeluaran p WHERE DATE(p.tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir'), 0)
                  as total_produk,

                  COALESCE((SELECT SUM(CASE WHEN p.kategori = 'Bayar Hutang Produk' THEN p.jumlah ELSE 0 END)
                   FROM pengeluaran p WHERE DATE(p.tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir'), 0) +
                  COALESCE((SELECT SUM(pc.jumlah_bayar) 
                   FROM piutang_cair pc WHERE pc.transaksi_id = '-1' AND DATE(pc.tanggal_bayar) BETWEEN '$tgl_awal' AND '$tgl_akhir'), 0)
                  as total_bayar_hutang,

                  COALESCE((SELECT SUM(CASE WHEN p.kategori NOT IN ('Kasbon Karyawan', 'Uang Makan', 'Gaji Karyawan', 'Pembelian Sparepart', 'Pembelian Barang', 'Bayar Hutang Produk') 
                       AND p.keterangan NOT LIKE '%produk:%' 
                       AND p.keterangan NOT LIKE '%produk baru:%' 
                       AND p.keterangan NOT LIKE 'Penambahan stok produk:%' 
                       THEN p.jumlah ELSE 0 END)
                   FROM pengeluaran p WHERE DATE(p.tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir'), 0)
                  as total_operasional,

                  COALESCE((SELECT COUNT(*) FROM pengeluaran p WHERE DATE(p.tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir'), 0) +
                  COALESCE((SELECT COUNT(*) FROM piutang_cair pc WHERE pc.transaksi_id = '-1' AND DATE(pc.tanggal_bayar) BETWEEN '$tgl_awal' AND '$tgl_akhir'), 0)
                  as jumlah_transaksi";

$summary_result = mysqli_query($conn, $summary_query);

// Error handling for summary_query
if ($summary_result === false) {
    error_log("Summary query failed: " . mysqli_error($conn) . " SQL: " . $summary_query);
    $total_pengeluaran = 0;
    $total_karyawan = 0;
    $total_produk = 0;
    $total_bayar_hutang = 0;
    $total_operasional = 0;
    $jumlah_transaksi = 0;
} else {
    $summary_data = mysqli_fetch_assoc($summary_result);
    $total_pengeluaran = $summary_data['total_pengeluaran'] ?: 0;
    $total_karyawan = $summary_data['total_karyawan'] ?: 0;
    $total_produk = $summary_data['total_produk'] ?: 0;
    $total_bayar_hutang = $summary_data['total_bayar_hutang'] ?: 0;
    $total_operasional = $summary_data['total_operasional'] ?: 0;
    $jumlah_transaksi = $summary_data['jumlah_transaksi'] ?: 0;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>History Pengeluaran - BMS Bengkel</title>
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
    
    .summary-card.primary {
      background: linear-gradient(135deg, #7E57C2, #5E35B1);
    }
    
    .summary-card.danger {
      background: linear-gradient(135deg, #F44336, #D32F2F);
    }
    
    .summary-card.success {
      background: linear-gradient(135deg, #66BB6A, #43A047);
    }
    
    .summary-card.warning {
      background: linear-gradient(135deg, #FFA726, #FB8C00);
    }
    
    .summary-card.info {
      background: linear-gradient(135deg, #26C6DA, #00ACC1);
    }

    .summary-card.purple {
      background: linear-gradient(135deg, #9C27B0, #7B1FA2);
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
    
    /* Category badges */
    .badge-kategori {
      padding: 0.5rem 0.75rem;
      border-radius: 6px;
      font-weight: 500;
      display: inline-block;
      min-width: 120px;
      text-align: center;
    }
    
    .badge-operasional {
      background-color: rgba(126, 87, 194, 0.15);
      color: #5E35B1;
    }
    
    .badge-gaji {
      background-color: rgba(76, 175, 80, 0.15);
      color: #43A047;
    }
    
    .badge-kasbon {
      background-color: rgba(255, 152, 0, 0.15);
      color: #F57C00;
    }
    
    .badge-makan {
      background-color: rgba(3, 169, 244, 0.15);
      color: #0288D1;
    }
    
    .badge-produk {
      background-color: rgba(233, 30, 99, 0.15);
      color: #C2185B;
    }

    .badge-tambah-stok {
      background-color: rgba(0, 188, 212, 0.15);
      color: #00838F;
    }

    .badge-hutang-produk {
      background-color: rgba(156, 39, 176, 0.15);
      color: #7B1FA2;
    }
    
    .badge-lainnya {
      background-color: rgba(158, 158, 158, 0.15);
      color: #616161;
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
          <i class="fas fa-money-bill-wave me-2"></i>
          History Pengeluaran
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
            <h1><i class="fas fa-money-bill-wave me-2"></i>History Pengeluaran</h1>
            <p class="lead mb-0">Riwayat semua pengeluaran kas - operasional, karyawan, produk</p>
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
      
      <!-- Filter Controls -->
      <div class="filter-card mb-4">
        <div class="card-header-actions mb-3">
          <h5 class="filter-title">
            <i class="fas fa-filter me-2"></i> Filter Pengeluaran
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
              <input type="hidden" name="tab" value="<?= $active_tab ?>">
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
              <div class="month-badge"><i class="fas fa-calendar-week"></i>
                <span>Filter aktif: <?= date('d F Y', strtotime($dari_tanggal)) ?> - <?= date('d F Y', strtotime($sampai_tanggal)) ?></span>
                <a href="<?= $_SERVER['PHP_SELF'] ?>?tab=<?= $active_tab ?>" class="btn btn-sm btn-outline-secondary ms-2 reset-btn">
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
    <div class="summary-card danger">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h6 class="summary-title">Uang Kas Keluar</h6>
          <h2 class="summary-value">Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?></h2>
          <small class="text-white-50"><?= number_format($jumlah_transaksi) ?> transaksi</small>
        </div>
        <i class="fas fa-money-bill-wave fa-3x text-white-50"></i>
      </div>
    </div>
  </div>
  
  <div class="col-md-6 col-lg-2">
    <div class="summary-card info">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h6 class="summary-title">Operasional</h6>
          <h2 class="summary-value">Rp <?= number_format($total_operasional, 0, ',', '.') ?></h2>
          <small class="text-white-50">Listrik, sewa, dll</small>
        </div>
        <i class="fas fa-tools fa-3x text-white-50"></i>
      </div>
    </div>
  </div>
  
  <div class="col-md-6 col-lg-2">
    <div class="summary-card success">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h6 class="summary-title">Karyawan</h6>
          <h2 class="summary-value">Rp <?= number_format($total_karyawan, 0, ',', '.') ?></h2>
          <small class="text-white-50">Gaji, kasbon, makan</small>
        </div>
        <i class="fas fa-users fa-3x text-white-50"></i>
      </div>
    </div>
  </div>
  
  <div class="col-md-6 col-lg-2">
    <div class="summary-card" style="background: linear-gradient(135deg, #FF9800, #E65100);">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h6 class="summary-title">Produk</h6>
          <h2 class="summary-value">Rp <?= number_format($total_produk, 0, ',', '.') ?></h2>
          <small class="text-white-50">Pembelian & stok</small>
        </div>
        <i class="fas fa-shopping-cart fa-3x text-white-50"></i>
      </div>
    </div>
  </div>

  <div class="col-md-6 col-lg-3">
    <div class="summary-card purple">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h6 class="summary-title">Bayar Hutang</h6>
          <h2 class="summary-value">Rp <?= number_format($total_bayar_hutang, 0, ',', '.') ?></h2>
          <small class="text-white-50">Pembayaran hutang produk</small>
        </div>
        <i class="fas fa-hand-holding-usd fa-3x text-white-50"></i>
      </div>
    </div>
  </div>
</div>
      
      <!-- Tabs Navigation -->
      <ul class="nav nav-tabs mb-4" id="pengeluaranTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <a class="nav-link <?= $active_tab == 'semua' ? 'active' : '' ?>" 
             href="<?= $filter_by_date ? '?dari_tanggal='.$dari_tanggal.'&sampai_tanggal='.$sampai_tanggal.'&tab=semua' : '?bulan='.$bulan_filter.'&tab=semua' ?>">
            <i class="fas fa-list-ul me-1"></i> Semua Pengeluaran
          </a>
        </li>
        <li class="nav-item" role="presentation">
          <a class="nav-link <?= $active_tab == 'operasional' ? 'active' : '' ?>" 
             href="<?= $filter_by_date ? '?dari_tanggal='.$dari_tanggal.'&sampai_tanggal='.$sampai_tanggal.'&tab=operasional' : '?bulan='.$bulan_filter.'&tab=operasional' ?>">
            <i class="fas fa-tools me-1"></i> Operasional
          </a>
        </li>
        <li class="nav-item" role="presentation">
          <a class="nav-link <?= $active_tab == 'karyawan' ? 'active' : '' ?>" 
             href="<?= $filter_by_date ? '?dari_tanggal='.$dari_tanggal.'&sampai_tanggal='.$sampai_tanggal.'&tab=karyawan' : '?bulan='.$bulan_filter.'&tab=karyawan' ?>">
            <i class="fas fa-users me-1"></i> Karyawan
          </a>
        </li>
        <li class="nav-item" role="presentation">
          <a class="nav-link <?= $active_tab == 'produk' ? 'active' : '' ?>" 
             href="<?= $filter_by_date ? '?dari_tanggal='.$dari_tanggal.'&sampai_tanggal='.$sampai_tanggal.'&tab=produk' : '?bulan='.$bulan_filter.'&tab=produk' ?>">
            <i class="fas fa-shopping-cart me-1"></i> Produk & Sparepart
          </a>
        </li>
        <li class="nav-item" role="presentation">
          <a class="nav-link <?= $active_tab == 'bayar_hutang_produk' ? 'active' : '' ?>" 
             href="<?= $filter_by_date ? '?dari_tanggal='.$dari_tanggal.'&sampai_tanggal='.$sampai_tanggal.'&tab=bayar_hutang_produk' : '?bulan='.$bulan_filter.'&tab=bayar_hutang_produk' ?>">
            <i class="fas fa-hand-holding-usd me-1"></i> Bayar Hutang Produk
          </a>
        </li>
      </ul>
      
      <!-- Daftar Pengeluaran Card -->
      <div class="data-card mb-4">
        <div class="card-header-actions">
          <h5 class="card-title">
            <i class="fas fa-list-alt me-2"></i>
            Daftar Pengeluaran <?= ucfirst($active_tab) != 'Semua' ? ucfirst(str_replace('_', ' ', $active_tab)) : '' ?>
          </h5>
          <div>
            <a href="export_pengeluaran_pdf.php?tgl_awal=<?= $tgl_awal ?>&tgl_akhir=<?= $tgl_akhir ?>&tab=<?= $active_tab ?>" class="btn btn-danger btn-sm">
              <i class="fas fa-file-pdf me-2"></i> Export PDF
            </a>
            <a href="export_pengeluaran_excel.php?tgl_awal=<?= $tgl_awal ?>&tgl_akhir=<?= $tgl_akhir ?>&tab=<?= $active_tab ?>&format=excel" class="btn btn-success btn-sm ms-2">
              <i class="fas fa-file-excel me-2"></i> Export Excel
            </a>
          </div>
        </div>
        
        <div class="table-responsive">
          <table class="table table-hover table-striped" id="pengeluaranTable">
            <thead>
              <tr>
                <th width="5%">#</th>
                <th width="10%">Tanggal</th>
                <th width="15%">Kategori</th>
                <th width="12%">Jumlah</th>
                <th width="48%">Keterangan</th>
                <th width="10%">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php 
              $no = $start + 1;
              if (mysqli_num_rows($result) > 0):
                while ($row = mysqli_fetch_assoc($result)):
                  // Determine badge style based on kategori
                  $badge_class = "badge-lainnya";
                  
                  if (in_array($row['kategori'], ['Sewa Lahan', 'Token Listrik', 'Air', 'Internet', 'Lainnya'])) {
                    $badge_class = "badge-operasional";
                  } elseif ($row['kategori'] == 'Gaji Karyawan' || $row['kategori_transaksi'] == 'Gaji Karyawan') {
                    $badge_class = "badge-gaji";
                  } elseif ($row['kategori'] == 'Kasbon Karyawan' || $row['kategori_transaksi'] == 'Kasbon Karyawan') {
                    $badge_class = "badge-kasbon";
                  } elseif ($row['kategori'] == 'Uang Makan' || $row['kategori_transaksi'] == 'Uang Makan') {
                    $badge_class = "badge-makan";
                  } elseif ($row['kategori_transaksi'] == 'Pembelian Sparepart Baru' || 
                            $row['kategori'] == 'Pembelian Sparepart' || 
                            $row['kategori'] == 'Pembelian Barang') {
                    $badge_class = "badge-produk";
                  } elseif ($row['kategori_transaksi'] == 'Tambah Stok') {
                    $badge_class = "badge-tambah-stok";
                  } elseif ($row['kategori'] == 'Bayar Hutang Produk' || $row['kategori_transaksi'] == 'Bayar Hutang Produk') {
                    $badge_class = "badge-hutang-produk";
                  }
              ?>
              <tr>
                <td><?= $no++ ?></td>
                <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                <td>
                  <span class="badge-kategori <?= $badge_class ?>">
                    <?= htmlspecialchars($row['kategori_transaksi']) ?>
                  </span>
                </td>
                <td class="fw-medium text-danger">
                  Rp <?= number_format($row['jumlah'], 0, ',', '.') ?>
                </td>
                <td>
                  <?php if (strpos($row['keterangan'], 'Gaji Asli') !== false): ?>
                    <?php
                      preg_match('/Gaji Asli: Rp ([0-9.,]+) - Kasbon: Rp ([0-9.,]+)/', $row['keterangan'], $matches);
                      if (count($matches) >= 3):
                        $keterangan_parts = explode(':', $row['keterangan'], 2);
                        $main_keterangan = trim($keterangan_parts[0]);
                    ?>
                      <div class="fw-medium"><?= htmlspecialchars($main_keterangan) ?></div>
                      <div class="small mt-1 bg-light p-2 rounded">
                        <div><i class="fas fa-money-bill-wave text-success me-1"></i> Gaji Asli: Rp <?= $matches[1] ?></div>
                        <div><i class="fas fa-hand-holding-usd text-warning me-1"></i> Kasbon: Rp <?= $matches[2] ?></div>
                        <div><i class="fas fa-wallet text-primary me-1"></i> Dibayarkan: Rp <?= number_format((int)str_replace('.', '', $matches[1]) - (int)str_replace('.', '', $matches[2]), 0, ',', '.') ?></div>
                        
                      </div>
                    <?php else: ?>
                      <?= nl2br(htmlspecialchars($row['keterangan'])) ?>
                    <?php endif; ?>
                  <?php else: ?>
                    <?= nl2br(htmlspecialchars($row['keterangan'])) ?>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                    <a href="detail_pengeluaran.php?id=<?= $row['id'] ?>&source=<?= $row['source_table'] ?>" class="btn btn-sm btn-primary rounded-pill px-3">
                        <i class="fas fa-eye me-1"></i> Detail
                    </a>
                </td>
              </tr>
              <?php 
                endwhile;
              else:
              ?>
              <tr>
                <td colspan="6" class="text-center py-5">
                  <img src="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/icons/cash-coin.svg" alt="No Data" width="64" height="64" class="mb-3 opacity-50">
                  <p class="text-muted mb-0 fw-medium">Tidak ada data pengeluaran pada periode yang dipilih.</p>
                  <p class="text-muted small">Silakan ubah filter tanggal untuk melihat data lainnya.</p>
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
          <div class="card-footer bg-white py-3">
            <nav aria-label="Halaman pengeluaran">
              <ul class="pagination justify-content-center mb-0">
                <!-- Previous Button -->
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                  <a class="page-link" href="?<?= $filter_by_date ? 'dari_tanggal='.$dari_tanggal.'&sampai_tanggal='.$sampai_tanggal : 'bulan='.$bulan_filter ?>&tab=<?= $active_tab ?>&page=<?= $page - 1 ?>">
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
                    echo '<li class="page-item"><a class="page-link" href="?'.($filter_by_date ? 'dari_tanggal='.$dari_tanggal.'&sampai_tanggal='.$sampai_tanggal : 'bulan='.$bulan_filter).'&tab='.$active_tab.'&page=1">1</a></li>';
                    if ($start_page > 2) {
                      echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                    }
                  }
                  
                  // Page links
                  for ($i = $start_page; $i <= $end_page; $i++) {
                    echo '<li class="page-item ' . (($page == $i) ? 'active' : '') . '">
                            <a class="page-link" href="?'.($filter_by_date ? 'dari_tanggal='.$dari_tanggal.'&sampai_tanggal='.$sampai_tanggal : 'bulan='.$bulan_filter).'&tab='.$active_tab.'&page=' . $i . '">' . $i . '</a>
                          </li>';
                  }
                  
                  // Show last page if not in range
                  if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                      echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                    }
                    echo '<li class="page-item"><a class="page-link" href="?'.($filter_by_date ? 'dari_tanggal='.$dari_tanggal.'&sampai_tanggal='.$sampai_tanggal : 'bulan='.$bulan_filter).'&tab='.$active_tab.'&page=' . $total_pages . '">' . $total_pages . '</a></li>';
                  }
                ?>
                
                <!-- Next Button -->
                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                  <a class="page-link" href="?<?= $filter_by_date ? 'dari_tanggal='.$dari_tanggal.'&sampai_tanggal='.$sampai_tanggal : 'bulan='.$bulan_filter ?>&tab=<?= $active_tab ?>&page=<?= $page + 1 ?>">
                    <i class="fas fa-chevron-right"></i>
                  </a>
                </li>
              </ul>
            </nav>
          </div>
        <?php endif; ?>
        
        <div class="d-flex justify-content-between align-items-center mt-3 px-3">
          <div class="text-muted small">
            <i class="fas fa-info-circle me-1"></i> Menampilkan <?= min($limit, mysqli_num_rows($result)) ?> dari <?= number_format($total_records) ?> pengeluaran <?= $active_tab != 'semua' ? ucfirst(str_replace('_', ' ', $active_tab)) : '' ?>
          </div>
        </div>
      </div>
      
      <!-- Informasi dan Tips Section -->
      <div class="row">
        <div class="col-md-6">
          <div class="data-card mb-4">
            <div class="card-header-actions">
              <h5 class="card-title">
                <i class="fas fa-info-circle me-2"></i>
                Informasi Pengeluaran
              </h5>
            </div>
            
            <div class="alert alert-info mb-4">
              <i class="fas fa-info-circle me-2"></i>
              <strong>Pengeluaran Bengkel:</strong> Halaman ini menampilkan semua riwayat pengeluaran bengkel, termasuk pengeluaran operasional, karyawan, pembelian produk/sparepart, dan pembayaran hutang produk.
            </div>
            
            <div class="row">
              <div class="col-md-12">
                <div class="card h-100 border-0 shadow-sm">
                  <div class="card-body">
                    <h5 class="card-title text-primary mb-3">Kategori Pengeluaran</h5>
                    <table class="table">
                      <tbody>
                        <tr>
                          <td width="40%"><strong>Operasional</strong></td>
                          <td width="60%">Pengeluaran untuk kebutuhan operasional bengkel seperti sewa lahan, listrik, air, dll.</td>
                        </tr>
                        <tr>
                          <td><strong>Karyawan</strong></td>
                          <td>Pengeluaran terkait karyawan seperti gaji, kasbon, dan uang makan.</td>
                        </tr>
                        <tr>
                          <td><strong>Pembelian Sparepart Baru</strong></td>
                          <td>Pengeluaran untuk membeli produk/sparepart baru.</td>
                        </tr>
                        <tr>
                          <td><strong>Tambah Stok</strong></td>
                          <td>Pengeluaran untuk menambah stok produk/sparepart yang sudah ada.</td>
                        </tr>
                        <tr>
                          <td><strong>Bayar Hutang Produk</strong></td>
                          <td>Pengeluaran untuk pembayaran hutang pembelian produk/sparepart.</td>
                        </tr>
                        <tr>
                          <td><strong>Lainnya</strong></td>
                          <td>Pengeluaran lain yang tidak termasuk dalam kategori di atas.</td>
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
                    <h5 class="card-title text-primary"><i class="fas fa-filter me-2"></i>Filter</h5>
                    <ul class="list-group list-group-flush">
                      <li class="list-group-item">Gunakan tab untuk memfilter berdasarkan kategori</li>
                      <li class="list-group-item">Gunakan filter bulan untuk melihat pengeluaran bulanan</li>
                      <li class="list-group-item">Gunakan filter rentang tanggal untuk periode spesifik</li>
                      <li class="list-group-item">Tombol "Hari Ini Saja" untuk melihat pengeluaran hari ini</li>
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
      if ($('#pengeluaranTable tbody tr').length > 1 || !$('#pengeluaranTable tbody tr td[colspan]').length) {
        $('#pengeluaranTable').DataTable({
          language: {
            url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json"
          },
          responsive: true,
          paging: false, // Pagination is handled by our custom code
          searching: false,
          info: false,
          ordering: true,
          "order": [[1, "desc"]]
        });
      }
      
      // Initialize date range filter
      initDateRangeFilter();
    });
    
    // Filter functions - synchronized with riwayat_transaksi.php
    function filterByMonth(value) {
      window.location.href = '?bulan=' + value + '&tab=<?= $active_tab ?>';
    }
    
    function setCurrentMonth() {
      const now = new Date();
      const year = now.getFullYear();
      const month = (now.getMonth() + 1).toString().padStart(2, '0');
      const currentMonth = `${year}-${month}`;
      
      document.getElementById('bulanTahunFilter').value = currentMonth;
      window.location.href = '?bulan=' + currentMonth + '&tab=<?= $active_tab ?>';
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
      window.location.href = '?bulan=' + newValue + '&tab=<?= $active_tab ?>';
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
      window.location.href = '?bulan=' + newValue + '&tab=<?= $active_tab ?>';
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