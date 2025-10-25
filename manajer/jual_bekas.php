<?php
session_start();

// Check if logged in and manajer
if (!isset($_SESSION['manajer']['logged_in']) || $_SESSION['manajer']['logged_in'] !== true) {
  header("Location: ../login.php");
  exit();
}

include '../config.php'; // Database connection

// Konfigurasi pagination
$per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $per_page;

// Default bulan adalah bulan ini
$bulan_ini = date('Y-m');
$bulan_filter = isset($_GET['bulan']) ? $_GET['bulan'] : $bulan_ini;

// Process add jual bekas form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $jenis = mysqli_real_escape_string($conn, $_POST['jenis']);
    // Langsung simpan total harga
    $total_harga = mysqli_real_escape_string($conn, $_POST['total_harga']);
    $pembeli = mysqli_real_escape_string($conn, $_POST['pembeli']);
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
    $tanggal = mysqli_real_escape_string($conn, $_POST['tanggal']);
    $bulan = date('Y-m', strtotime($tanggal));
    $created_by = $_SESSION['manajer']['id']; // Changed from id_manajer to id
    
    // Status kas: 1 = sudah masuk kas, 0 = belum masuk kas
    $status_kas = isset($_POST['status_kas']) ? 1 : 0;
    
    // Tanggal masuk kas (jika sudah masuk kas)
    $tanggal_masuk_kas = $status_kas ? $tanggal : NULL;
    
    // Insert jual bekas with kas status - hapus kolom berat dan harga_per_kg dari query
    $query = "INSERT INTO jual_bekas (jenis, total_harga, pembeli, keterangan, tanggal, bulan, created_by, status_kas, tanggal_masuk_kas) 
              VALUES ('$jenis', '$total_harga', '$pembeli', '$keterangan', '$tanggal', '$bulan', '$created_by', '$status_kas', " . ($tanggal_masuk_kas ? "'$tanggal_masuk_kas'" : "NULL") . ")";
    
    if (mysqli_query($conn, $query)) {
        // Success message
        $_SESSION['message'] = "Penjualan besi/oli bekas berhasil ditambahkan!";
        $_SESSION['alert_type'] = "success";
    } else {
        $_SESSION['message'] = "Gagal menambahkan penjualan: " . mysqli_error($conn);
        $_SESSION['alert_type'] = "danger";
    }
    
    header("Location: " . $_SERVER['PHP_SELF'] . "?bulan=" . $bulan_filter);
    exit();
}

// Process update kas status
if (isset($_GET['update_kas']) && !empty($_GET['update_kas'])) {
    $id = mysqli_real_escape_string($conn, $_GET['update_kas']);
    $tanggal_masuk_kas = date('Y-m-d'); // Today's date
    
    // Update status_kas and tanggal_masuk_kas
    $update_query = "UPDATE jual_bekas SET status_kas = 1, tanggal_masuk_kas = '$tanggal_masuk_kas' WHERE id = '$id'";
    if (mysqli_query($conn, $update_query)) {
        $_SESSION['message'] = "Status kas berhasil diperbarui!";
        $_SESSION['alert_type'] = "success";
    } else {
        $_SESSION['message'] = "Gagal memperbarui status kas: " . mysqli_error($conn);
        $_SESSION['alert_type'] = "danger";
    }
    
    header("Location: " . $_SERVER['PHP_SELF'] . "?bulan=" . $bulan_filter);
    exit();
}

// Process delete jual bekas
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete']);
    
    // Delete jual_bekas
    $delete_query = "DELETE FROM jual_bekas WHERE id = '$id'";
    if (mysqli_query($conn, $delete_query)) {
        $_SESSION['message'] = "Data penjualan berhasil dihapus!";
        $_SESSION['alert_type'] = "success";
    } else {
        $_SESSION['message'] = "Gagal menghapus data penjualan: " . mysqli_error($conn);
        $_SESSION['alert_type'] = "danger";
    }
    
    header("Location: " . $_SERVER['PHP_SELF'] . "?bulan=" . $bulan_filter);
    exit();
}

// Get all data for overall summary
$all_summary_query = "SELECT 
                      COUNT(*) as total_transaksi,
                      SUM(total_harga) as total_pendapatan,
                      SUM(CASE WHEN status_kas = 1 THEN total_harga ELSE 0 END) as total_masuk_kas,
                      SUM(CASE WHEN status_kas = 0 THEN total_harga ELSE 0 END) as total_belum_masuk_kas
                      FROM jual_bekas";
$all_summary_result = mysqli_query($conn, $all_summary_query);
$all_summary_data = mysqli_fetch_assoc($all_summary_result);

// Get jual bekas data with filter
$query = "SELECT j.*, 
          CASE 
            WHEN j.created_by IS NOT NULL THEN m.nama 
            ELSE 'Unknown' 
          END as created_by_name
          FROM jual_bekas j
          LEFT JOIN manajer m ON j.created_by = m.id_manajer
          WHERE j.bulan = '$bulan_filter'
          ORDER BY j.tanggal DESC, j.created_at DESC
          LIMIT $start, $per_page";
$result = mysqli_query($conn, $query);

// Count total rows for pagination
$count_query = "SELECT COUNT(*) as total FROM jual_bekas WHERE bulan = '$bulan_filter'";
$count_result = mysqli_query($conn, $count_query);
$count_data = mysqli_fetch_assoc($count_result);
$total_pages = ceil($count_data['total'] / $per_page);

// Get summary data for current filter month
$summary_query = "SELECT 
                  COUNT(*) as total_transaksi,
                  SUM(total_harga) as total_pendapatan,
                  SUM(CASE WHEN status_kas = 1 THEN total_harga ELSE 0 END) as total_masuk_kas,
                  SUM(CASE WHEN status_kas = 0 THEN total_harga ELSE 0 END) as total_belum_masuk_kas,
                  SUM(CASE WHEN jenis = 'Besi' THEN total_harga ELSE 0 END) as total_pendapatan_besi,
                  SUM(CASE WHEN jenis = 'Oli' THEN total_harga ELSE 0 END) as total_pendapatan_oli,
                  SUM(CASE WHEN jenis = 'Lainnya' THEN total_harga ELSE 0 END) as total_pendapatan_lainnya
                  FROM jual_bekas
                  WHERE bulan = '$bulan_filter'";
$summary_result = mysqli_query($conn, $summary_query);
$summary_data = mysqli_fetch_assoc($summary_result);

// Get summary data for all unpaid kas
$unpaid_query = "SELECT 
                COUNT(*) as count_belum_masuk_kas,
                SUM(total_harga) as total_belum_masuk_kas
                FROM jual_bekas
                WHERE status_kas = 0";
$unpaid_result = mysqli_query($conn, $unpaid_query);
$unpaid_data = mysqli_fetch_assoc($unpaid_result);

// Get available months for filter
$months_query = "SELECT DISTINCT bulan FROM jual_bekas ORDER BY bulan DESC";
$months_result = mysqli_query($conn, $months_query);
$months = [];
while ($row = mysqli_fetch_assoc($months_result)) {
    $months[] = $row['bulan'];
}

// If no months found, add current month
if (empty($months)) {
    $months[] = $bulan_ini;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Jual Besi/Oli Bekas - BMS Bengkel</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      /* Manajer orange theme - matched from dashboard.php */
      --primary-orange: #EF6C00;
      --secondary-orange: #F59E0B;
      --light-orange: #FFF3E0;
      --accent-orange: #D84315;
      
      /* General colors */
      --white: #ffffff;
      --light-gray: #f8f9fa;
      --text-dark: #2C3E50;
      --border-color: #e1e7ef;
      --success-color: #4CAF50;
      --danger-color: #F44336;
    }
    
    body {
      font-family: 'Poppins', sans-serif;
      background-color: var(--light-gray);
      margin: 0;
      padding: 0;
      color: var(--text-dark);
    }
    
    /* Content area - matched from dashboard.php */
    .content {
      margin-left: 280px;
      transition: margin-left 0.3s ease;
      min-height: 100vh;
    }
    
    /* Navbar Styling - matched from dashboard.php */
    .navbar {
      background: linear-gradient(135deg, var(--primary-orange), var(--accent-orange));
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
    
    /* Page header section - matched from dashboard.php */
    .page-header {
      background: linear-gradient(135deg, var(--primary-orange), var(--secondary-orange));
      border-radius: 15px;
      padding: 30px;
      color: var(--white);
      margin-bottom: 25px;
      box-shadow: 0 6px 18px rgba(239, 108, 0, 0.15);
    }
    
    .page-header h1 {
      font-weight: 700;
      margin-bottom: 0.5rem;
    }
    
    /* Data card - matched from dashboard.php */
    .data-card {
      border: none;
      border-radius: 15px;
      overflow: hidden;
      box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
      background-color: var(--white);
      padding: 1.5rem;
      height: 100%;
      transition: all 0.3s ease;
    }
    
    .data-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 12px 20px rgba(0, 0, 0, 0.12);
    }
    
    /* Table styling - matched and enhanced */
    .table {
      margin-bottom: 0;
      border-collapse: separate;
      border-spacing: 0;
    }

    .table th {
      background-color: var(--light-orange);
      color: var(--primary-orange);
      font-weight: 600;
      border: none;
      vertical-align: middle;
    }
    
    .table td {
      vertical-align: middle;
      border-color: #e9ecef;
    }
    
    /* Card header with actions - matched from dashboard.php */
    .card-header-actions {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-bottom: 1rem;
      margin-bottom: 1.5rem;
      border-bottom: 2px solid var(--light-orange);
    }
    
    .card-title {
      font-size: 1.25rem;
      font-weight: 600;
      color: var(--primary-orange);
      margin-bottom: 0;
    }
    
    /* Button styling - matched from dashboard.php */
    .btn-primary {
      background: linear-gradient(135deg, var(--primary-orange), var(--secondary-orange));
      border: none;
      border-radius: 8px;
      padding: 0.75rem 1.5rem;
      font-weight: 600;
      transition: all 0.3s ease;
      box-shadow: 0 4px 10px rgba(239, 108, 0, 0.2);
    }
    
    .btn-primary:hover {
      background: linear-gradient(135deg, var(--accent-orange), var(--primary-orange));
      transform: translateY(-2px);
      box-shadow: 0 6px 15px rgba(239, 108, 0, 0.3);
    }
    
    .btn-outline-light {
      border-radius: 8px;
      font-weight: 500;
      transition: all 0.3s ease;
    }
    
    .btn-danger {
      border-radius: 8px;
      padding: 0.375rem 0.75rem;
      transition: all 0.3s ease;
    }
    
    .btn-danger:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 10px rgba(220, 53, 69, 0.2);
    }
    
    .btn-success {
      background-color: var(--success-color);
      border: none;
      border-radius: 8px;
      padding: 0.375rem 0.75rem;
      transition: all 0.3s ease;
    }
    
    .btn-success:hover {
      background-color: #388E3C;
      transform: translateY(-2px);
      box-shadow: 0 4px 10px rgba(76, 175, 80, 0.2);
    }
    
    .btn-action {
      padding: 0.4rem 0.7rem;
      border-radius: 6px;
      margin-right: 0.25rem;
    }

    .btn-group {
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
      border-radius: 8px;
    }
    
    /* Form switch for kas status */
    .form-switch .form-check-input {
      width: 3em;
      height: 1.5em;
      cursor: pointer;
    }
    
    .form-switch .form-check-input:checked {
      background-color: var(--success-color);
      border-color: var(--success-color);
    }
    
    /* Stats cards - horizontal layout (from pengeluaran.php) */
.stats-card-wrapper {
  position: relative;
  border-radius: 15px;
  overflow: hidden;
  height: 100%;
  background-color: white;
  border-left: 4px solid;
  box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
  transition: all 0.3s ease;
  padding: 1.5rem;
}

.stats-card-wrapper:hover {
  transform: translateY(-5px);
  box-shadow: 0 12px 20px rgba(0, 0, 0, 0.12);
}

.stats-card-orange {
  border-left-color: #EF6C00;
}

.stats-card-green {
  border-left-color: #4CAF50;
}

.stats-card-red {
  border-left-color: #F44336;
}
    
.stats-card {
  display: flex;
  align-items: center;
}

.stats-icon-container {
  width: 60px;
  height: 60px;
  border-radius: 15px;
  margin-right: 15px;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: #FFF3E0;
}

.stats-icon-products {
  color: #EF6C00;
}

.stats-icon {
  font-size: 1.8rem;
}

.stats-content h3 {
  font-size: 1.5rem;
  font-weight: 700;
  margin-bottom: 0;
  color: #333;
}

.stats-content p {
  font-size: 0.9rem;
  color: #6c757d;
  margin-bottom: 0;
}
    
    /* Filter dropdown */
    .filter-dropdown {
      border-radius: 8px;
      border: 1px solid #e9ecef;
      padding: 0.5rem 1rem;
      width: 200px;
    }
    
    /* Filter controls styling */
    .filter-controls {
      margin-bottom: 1.5rem;
    }
    
    .month-badge {
      background: var(--light-orange);
      color: var(--primary-orange);
      font-weight: 600;
      padding: 0.75rem 1.25rem;
      border-radius: 10px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    /* Alerts - matched from dashboard.php */
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
    
    .alert-info {
      background-color: #E3F2FD;
      color: #0D47A1;
    }
    
    /* Modal styling */
    .modal-content {
      border-radius: 15px;
      border: none;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }
    
    .modal-header {
      background-color: var(--light-orange);
      border-bottom: none;
      border-top-left-radius: 15px;
      border-top-right-radius: 15px;
      padding: 1.5rem;
    }
    
    .modal-title {
      color: var(--primary-orange);
      font-weight: 600;
    }
    
    .modal-body {
      padding: 1.5rem;
    }
    
    .modal-footer {
      border-top: none;
      padding: 1.5rem;
    }

    /* Status Badges */
    .badge-kas-masuk {
      background-color: var(--success-color);
      color: white;
      font-weight: 500;
      padding: 0.35em 0.65em;
      border-radius: 6px;
    }
    
    .badge-kas-belum {
      background-color: var(--danger-color);
      color: white;
      font-weight: 500;
      padding: 0.35em 0.65em;
      border-radius: 6px;
    }
    
    /* Responsive styling */
    @media (max-width: 992px) {
      .content {
        margin-left: 0;
      }
    }
    
    @media (max-width: 768px) {
      .page-header {
        padding: 1.5rem;
      }
      
      .card-header-actions {
        flex-direction: column;
        align-items: flex-start;
      }
      
      .stats-card {
        flex-direction: column;
        text-align: center;
      }
      
      .stats-icon {
        margin-right: 0;
        margin-bottom: 1rem;
      }
    }
    
    /* Fix for container-fluid to ensure proper padding */
    .container-fluid {
      padding-left: 15px;
      padding-right: 15px;
    }
    
    /* Fix for row margins */
    .row {
      margin-right: 0;
      margin-left: 0;
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
          <i class="fas fa-recycle me-2"></i>
          Jual Besi/Oli Bekas
        </span>
        <div class="d-flex align-items-center">
          <span class="text-white me-3">
            <i class="fas fa-user-circle me-1"></i>
            <?= htmlspecialchars($_SESSION['manajer']['nama']) ?>
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
      <h1><i class="fas fa-recycle me-2"></i>Jual Besi/Oli Bekas</h1>
      <p class="lead mb-0">Manajemen data penjualan besi dan oli bekas</p>
    </div>
    <button type="button" class="btn btn-light btn-lg" data-bs-toggle="modal" data-bs-target="#addJualBekasModal">
  <i class="fas fa-plus me-2"></i>
  Catat Penjualan
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
      
      <!-- Filter Controls - Month-Year Picker (similar to pengeluaran.php) -->
      <div class="filter-controls">
        <div class="d-flex gap-2 justify-content-between align-items-center">
          <div class="month-badge">
            <i class="fas fa-calendar-alt"></i>
            <span>Filter Bulan: <?= date('F Y', strtotime($bulan_filter . '-01')) ?></span>
          </div>
          
          <div class="d-flex gap-2">
            <div class="input-group" style="max-width: 400px;">
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
        <?php if (!empty($unpaid_data) && $unpaid_data['count_belum_masuk_kas'] > 0): ?>
        <div class="alert alert-info mt-2 mb-0">
          <i class="fas fa-info-circle me-2"></i>
          <span>Terdapat <?= $unpaid_data['count_belum_masuk_kas'] ?> penjualan (Rp <?= number_format($unpaid_data['total_belum_masuk_kas'], 0, ',', '.') ?>) yang belum masuk kas secara keseluruhan</span>
        </div>
        <?php endif; ?>
      </div>
      
      <!-- Summary Cards -->
<div class="row g-4 mb-4">
  <div class="col-md-4">
    <div class="stats-card-wrapper stats-card-orange">
      <div class="stats-card">
        <div class="stats-icon-container">
          <i class="fas fa-coins stats-icon"></i>
        </div>
        <div class="stats-content">
          <h3>Rp <?= number_format($summary_data['total_pendapatan'] ?? 0, 0, ',', '.') ?></h3>
          <p>Total Pendapatan</p>
        </div>
      </div>
    </div>
  </div>
  
  <div class="col-md-4">
    <div class="stats-card-wrapper stats-card-green">
      <div class="stats-card">
        <div class="stats-icon-container">
          <i class="fas fa-cash-register stats-icon text-success"></i>
        </div>
        <div class="stats-content">
          <h3>Rp <?= number_format($summary_data['total_masuk_kas'] ?? 0, 0, ',', '.') ?></h3>
          <p>Total Masuk Kas</p>
        </div>
      </div>
    </div>
  </div>
  
  <div class="col-md-4">
    <div class="stats-card-wrapper stats-card-red">
      <div class="stats-card">
        <div class="stats-icon-container">
          <i class="fas fa-exclamation-circle stats-icon text-danger"></i>
        </div>
        <div class="stats-content">
          <h3>Rp <?= number_format($summary_data['total_belum_masuk_kas'] ?? 0, 0, ',', '.') ?></h3>
          <p>Belum Masuk Kas</p>
        </div>
      </div>
    </div>
  </div>
</div>
      
      <!-- Main Content -->
      <div class="row g-4 mb-4">
        <div class="col-12">
          <div class="data-card">
            <div class="card-header-actions">
              <h5 class="card-title">
                <i class="fas fa-list me-2"></i>
                Data Penjualan Besi/Oli Bekas
              </h5>
            </div>
            
            <div class="table-responsive">
              <table class="table table-hover" id="jualBekasTable">
                <thead>
                  <tr>
                    <th width="5%">#</th>
                    <th width="15%">Tanggal</th>
                    <th width="15%">Jenis</th>
                    <th width="15%">Total Harga</th>
                    <th width="15%">Pembeli</th>
                    <th width="15%">Status Kas</th>
                    <th width="10%">Keterangan</th>
                    <th width="10%">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                  $no = $start + 1;
                  if (mysqli_num_rows($result) > 0):
                    while ($row = mysqli_fetch_assoc($result)):
                  ?>
                  <tr>
                    <td><?= $no++ ?></td>
                    <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                    <td>
                      <?php if ($row['jenis'] == 'Besi'): ?>
                        <span class="badge bg-secondary">Besi</span>
                      <?php elseif ($row['jenis'] == 'Oli'): ?>
                        <span class="badge bg-warning">Oli</span>
                      <?php else: ?>
                        <span class="badge bg-info">Lainnya</span>
                      <?php endif; ?>
                    </td>
                    <td>Rp <?= number_format($row['total_harga'], 0, ',', '.') ?></td>
                    <td><?= htmlspecialchars($row['pembeli']) ?></td>
                    <td>
                      <?php if (isset($row['status_kas']) && $row['status_kas'] == 1): ?>
                        <span class="badge-kas-masuk">
                          <i class="fas fa-check-circle me-1"></i> Masuk Kas
                        </span>
                        <?php if (isset($row['tanggal_masuk_kas']) && $row['tanggal_masuk_kas']): ?>
                          <div class="small text-muted mt-1"><?= date('d/m/Y', strtotime($row['tanggal_masuk_kas'])) ?></div>
                        <?php endif; ?>
                      <?php else: ?>
                        <span class="badge-kas-belum">
                          <i class="fas fa-times-circle me-1"></i> Belum Masuk
                        </span>
                      <?php endif; ?>
                    </td>
                    <td><?= nl2br(htmlspecialchars($row['keterangan'])) ?></td>
                    <td>
                    <?php if (!isset($row['status_kas']) || $row['status_kas'] == 0): ?>
  <button type="button" class="btn btn-sm btn-success btn-action" 
    onclick="updateKasStatus(
      '<?= $row['id'] ?>', 
      '<?= date('d/m/Y', strtotime($row['tanggal'])) ?>', 
      '<?= $row['jenis'] ?>', 
      '<?= number_format($row['total_harga'], 0, ',', '.') ?>', 
      '<?= htmlspecialchars($row['pembeli']) ?>'
    )">
    <i class="fas fa-cash-register"></i>
  </button>
<?php endif; ?>
<button type="button" class="btn btn-sm btn-danger btn-action" 
  onclick="confirmDelete(
    '<?= $row['id'] ?>', 
    '<?= date('d/m/Y', strtotime($row['tanggal'])) ?>', 
    '<?= $row['jenis'] ?>', 
    '<?= number_format($row['total_harga'], 0, ',', '.') ?>', 
    '<?= htmlspecialchars($row['pembeli']) ?>'
  )">
  <i class="fas fa-trash-alt"></i>
</button>
                    </td>
                  </tr>
                  <?php 
                    endwhile;
                  else:
                  ?>
                  <tr>
                    <td colspan="8" class="text-center py-4">Tidak ada data penjualan pada bulan ini</td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
              <ul class="pagination">
                <?php if ($page > 1): ?>
                <li class="page-item">
                  <a class="page-link" href="?page=<?= $page-1 ?>&bulan=<?= $bulan_filter ?>" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                  </a>
                </li>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                  <a class="page-link" href="?page=<?= $i ?>&bulan=<?= $bulan_filter ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                <li class="page-item">
                  <a class="page-link" href="?page=<?= $page+1 ?>&bulan=<?= $bulan_filter ?>" aria-label="Next">
                    <span aria-hidden="true">&raquo;</span>
                  </a>
                </li>
                <?php endif; ?>
              </ul>
            </nav>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Add Jual Bekas Modal -->
  <div class="modal fade" id="addJualBekasModal" tabindex="-1" aria-labelledby="addJualBekasModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="addJualBekasModalLabel">
            <i class="fas fa-plus-circle me-2"></i>Tambah Penjualan Besi/Oli Bekas
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form action="" method="POST">
          <div class="modal-body">
            <input type="hidden" name="action" value="add">
            
            <div class="row mb-3">
              <div class="col-md-6">
                <label for="tanggal" class="form-label">Tanggal <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= date('Y-m-d') ?>" required>
              </div>
              <div class="col-md-6">
                <label for="jenis" class="form-label">Jenis <span class="text-danger">*</span></label>
                <select class="form-select" id="jenis" name="jenis" required>
                  <option value="Besi">Besi</option>
                  <option value="Oli">Oli</option>
                  <option value="Lainnya">Lainnya</option>
                </select>
              </div>
            </div>
            
            <!-- Ganti input berat dan harga per kg dengan total harga langsung -->
            <div class="mb-3">
              <label for="total_harga" class="form-label">Total Harga (Rp) <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text">Rp</span>
                <input type="number" class="form-control" id="total_harga" name="total_harga" min="1000" required>
              </div>
            </div>
            
            <div class="mb-3">
              <label for="pembeli" class="form-label">Pembeli</label>
              <input type="text" class="form-control" id="pembeli" name="pembeli">
            </div>
            
            <div class="mb-3">
              <label for="keterangan" class="form-label">Keterangan</label>
              <textarea class="form-control" id="keterangan" name="keterangan" rows="3"></textarea>
            </div>
            
            <div class="mb-3">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="status_kas" name="status_kas" checked>
                <label class="form-check-label" for="status_kas">
                  <strong>Uang langsung masuk kas</strong>
                  <div class="text-muted small">Centang jika pembayaran sudah diterima dan dimasukkan ke kas</div>
                </label>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save me-1"></i> Simpan
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Confirmation Modal for Masuk Kas -->
<div class="modal fade" id="updateKasModal" tabindex="-1" aria-labelledby="updateKasModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="updateKasModalLabel">
          <i class="fas fa-cash-register me-2"></i>Konfirmasi Masuk Kas
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Anda akan mengonfirmasi bahwa uang dari penjualan ini sudah masuk ke kas BMS Bengkel.</p>
        <p class="mb-0">Detail penjualan:</p>
        <ul>
          <li>Tanggal: <span id="kasDetailTanggal"></span></li>
          <li>Jenis: <span id="kasDetailJenis"></span></li>
          <li>Total: <strong id="kasDetailHarga"></strong></li>
          <li>Pembeli: <span id="kasDetailPembeli"></span></li>
        </ul>
        <div class="alert alert-info">
          <i class="fas fa-info-circle me-2"></i>
          Tindakan ini tidak dapat dibatalkan. Pastikan uang sudah benar-benar masuk ke kas.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <a href="#" id="confirmKasBtn" class="btn btn-success">
          <i class="fas fa-check-circle me-1"></i> Konfirmasi Masuk Kas
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Confirmation Modal for Delete -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteConfirmModalLabel">
          <i class="fas fa-trash-alt me-2"></i>Konfirmasi Hapus Data
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger">
          <i class="fas fa-exclamation-triangle me-2"></i>
          Peringatan: Tindakan ini akan menghapus data secara permanen dan tidak dapat dikembalikan!
        </div>
        <p>Anda akan menghapus data penjualan berikut:</p>
        <ul>
          <li>Tanggal: <span id="deleteDetailTanggal"></span></li>
          <li>Jenis: <span id="deleteDetailJenis"></span></li>
          <li>Total: <strong id="deleteDetailHarga"></strong></li>
          <li>Pembeli: <span id="deleteDetailPembeli"></span></li>
        </ul>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <a href="#" id="confirmDeleteBtn" class="btn btn-danger">
          <i class="fas fa-trash-alt me-1"></i> Hapus Data
        </a>
      </div>
    </div>
  </div>
</div>

  <!-- JavaScript Libraries -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  
  <script>
  // Initialize DataTable
  $(document).ready(function() {
    // Check if table is actually empty (has the "no data" message)
    if ($('#jualBekasTable tbody tr').length === 1 && $('#jualBekasTable tbody tr td[colspan]').length > 0) {
      // For empty tables, don't initialize DataTables at all
      // Just leave the default HTML table with the "no data" message
      console.log("Table is empty, skipping DataTable initialization");
    } else {
      // Only initialize DataTables if there's actual data
      const table = $('#jualBekasTable').DataTable({
        "searching": true,
        "ordering": true,
        "info": false,
        "autoWidth": false,
        "responsive": true,
        "language": {
          "search": "Cari:",
          "zeroRecords": "Tidak ada data yang cocok",
          "emptyTable": "Tidak ada data yang tersedia",
        }
      });
      
      // Export to Excel functionality
      $('#exportBtn').on('click', function() {
        // Kode export Excel tetap sama
        // ...
      });
    }
    
    // Auto dismiss alerts
    setTimeout(function() {
      $('.alert').alert('close');
    }, 5000);
  });
  // Function to handle the kas status update
function updateKasStatus(id, tanggal, jenis, harga, pembeli) {
  // Set the details in the modal
  document.getElementById('kasDetailTanggal').textContent = tanggal;
  document.getElementById('kasDetailJenis').textContent = jenis;
  document.getElementById('kasDetailHarga').textContent = 'Rp ' + harga;
  document.getElementById('kasDetailPembeli').textContent = pembeli;
  
  // Update the confirmation button link
  const confirmBtn = document.getElementById('confirmKasBtn');
  confirmBtn.href = `?update_kas=${id}&bulan=<?= $bulan_filter ?>`;
  
  // Show the modal
  const modal = new bootstrap.Modal(document.getElementById('updateKasModal'));
  modal.show();
}

// Function to handle the delete confirmation
function confirmDelete(id, tanggal, jenis, harga, pembeli) {
  // Set the details in the modal
  document.getElementById('deleteDetailTanggal').textContent = tanggal;
  document.getElementById('deleteDetailJenis').textContent = jenis;
  document.getElementById('deleteDetailHarga').textContent = 'Rp ' + harga;
  document.getElementById('deleteDetailPembeli').textContent = pembeli;
  
  // Update the confirmation button link
  const confirmBtn = document.getElementById('confirmDeleteBtn');
  confirmBtn.href = `?delete=${id}&bulan=<?= $bulan_filter ?>`;
  
  // Show the modal
  const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
  modal.show();
}
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
</script>
</body>
</html>