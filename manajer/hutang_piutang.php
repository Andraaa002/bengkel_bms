<?php
session_start();

// Check if logged in and manajer
if (!isset($_SESSION['manajer']['logged_in']) || $_SESSION['manajer']['logged_in'] !== true) {
  header("Location: ../login.php");
  exit();
}

include '../config.php'; // Database connection

// Process product debt payment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'bayar_hutang_produk') {
    $produk_id = mysqli_real_escape_string($conn, $_POST['produk_id']);
    $jumlah_bayar = mysqli_real_escape_string($conn, $_POST['jumlah_bayar']);
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
    $tanggal_bayar = mysqli_real_escape_string($conn, $_POST['tanggal_bayar']);
    $created_by = $_SESSION['manajer']['id_manajer'];
    
    // Get product data
    $produk_query = "SELECT * FROM produk WHERE id = '$produk_id'";
    $produk_result = mysqli_query($conn, $produk_query);
    $produk = mysqli_fetch_assoc($produk_result);
    
    if ($produk) {
        // Calculate remaining debt
        $sisa_hutang = $produk['nominal_hutang'] - $jumlah_bayar;
        
        // Start transaction
        mysqli_autocommit($conn, false);
        $error = false;
        
        // Update product status
        $hutang_status = ($sisa_hutang <= 0) ? 'Cash' : 'Hutang';
        $update_query = "UPDATE produk SET 
                        nominal_hutang = '$sisa_hutang', 
                        hutang_sparepart = '$hutang_status' 
                        WHERE id = '$produk_id'";
                        
        if (!mysqli_query($conn, $update_query)) {
            $error = true;
        }
        
        // Insert payment record to piutang_cair with special note
        $nama_produk = $produk['nama'];
        $keterangan_lengkap = "Pembayaran hutang produk: $nama_produk - $keterangan";
        
        // Use -1 as a marker for product debt payments in transaksi_id field
        $insert_query = "INSERT INTO piutang_cair (
                        transaksi_id, 
                        jumlah_bayar, 
                        tanggal_bayar, 
                        keterangan, 
                        created_by) 
                        VALUES 
                        ('-1', '$jumlah_bayar', '$tanggal_bayar', '$keterangan_lengkap', '$created_by')";
        
        if (!mysqli_query($conn, $insert_query)) {
            $error = true;
        }
        
        // Commit or rollback transaction
        if ($error) {
            mysqli_rollback($conn);
            $_SESSION['message'] = "Gagal memproses pembayaran: " . mysqli_error($conn);
            $_SESSION['alert_type'] = "danger";
        } else {
            mysqli_commit($conn);
            $_SESSION['message'] = "Pembayaran hutang produk berhasil diproses!";
            $_SESSION['alert_type'] = "success";
        }
    } else {
        $_SESSION['message'] = "Produk tidak ditemukan!";
        $_SESSION['alert_type'] = "danger";
    }
    
    header("Location: piutang.php");
    exit();
}

// Process update transaction debt status (set to lunas)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_piutang_transaksi') {
  $transaksi_id = mysqli_real_escape_string($conn, $_POST['transaksi_id']);
  $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
  $tanggal_bayar = mysqli_real_escape_string($conn, $_POST['tanggal_bayar']);
  $created_by = $_SESSION['manajer']['id_manajer'];
  
  // Get transaction data
  $transaksi_query = "SELECT * FROM transaksi WHERE id = '$transaksi_id'";
  $transaksi_result = mysqli_query($conn, $transaksi_query);
  $transaksi = mysqli_fetch_assoc($transaksi_result);
  
  if ($transaksi && $transaksi['status_hutang'] == 1 && $transaksi['hutang'] > 0) {
      // Start transaction
      mysqli_autocommit($conn, false);
      $error = false;
      
      // Insert payment record
      $jumlah_bayar = $transaksi['hutang'];
      $insert_query = "INSERT INTO piutang_cair (
                      transaksi_id, 
                      jumlah_bayar, 
                      tanggal_bayar, 
                      keterangan, 
                      created_by) 
                      VALUES 
                      ('$transaksi_id', '$jumlah_bayar', '$tanggal_bayar', '$keterangan', '$created_by')";
      
      if (!mysqli_query($conn, $insert_query)) {
          $error = true;
      }
      
      // Update transaction status AND pendapatan (add the hutang amount to pendapatan)
      $update_query = "UPDATE transaksi SET 
                      hutang = 0, 
                      status_hutang = 0,
                      pendapatan = pendapatan + $jumlah_bayar
                      WHERE id = '$transaksi_id'";
      
      if (!mysqli_query($conn, $update_query)) {
          $error = true;
      }
      
      // Commit or rollback transaction
      if ($error) {
          mysqli_rollback($conn);
          $_SESSION['message'] = "Gagal memproses pembayaran: " . mysqli_error($conn);
          $_SESSION['alert_type'] = "danger";
      } else {
          mysqli_commit($conn);
          $_SESSION['message'] = "Status piutang transaksi berhasil diupdate menjadi LUNAS!";
          $_SESSION['alert_type'] = "success";
      }
  } else {
      $_SESSION['message'] = "Transaksi tidak ditemukan atau tidak memiliki piutang!";
      $_SESSION['alert_type'] = "danger";
  }
  
  header("Location: piutang.php");
  exit();
}

// Default bulan adalah bulan ini
$bulan_ini = date('Y-m');
$bulan_filter = isset($_GET['bulan']) ? $_GET['bulan'] : $bulan_ini;

// Get all products with debt
$hutang_produk_query = "SELECT * FROM produk WHERE hutang_sparepart = 'Hutang' AND nominal_hutang > 0 ORDER BY nominal_hutang DESC";
$hutang_produk_result = mysqli_query($conn, $hutang_produk_query);

// Get all transactions with debt regardless of month
$piutang_transaksi_query = "SELECT t.*, 
                          COUNT(td.id) as jumlah_item
                          FROM transaksi t
                          LEFT JOIN transaksi_detail td ON t.id = td.transaksi_id
                          WHERE t.status_hutang = 1 AND t.hutang > 0
                          GROUP BY t.id
                          ORDER BY t.tanggal DESC, t.id DESC";
$piutang_transaksi_result = mysqli_query($conn, $piutang_transaksi_query);

// Get summary data - remove month filter from piutang transaksi count
$summary_query = "SELECT 
                  (SELECT COUNT(*) FROM produk WHERE hutang_sparepart = 'Hutang' AND nominal_hutang > 0) as total_produk_hutang,
                  (SELECT SUM(nominal_hutang) FROM produk WHERE hutang_sparepart = 'Hutang' AND nominal_hutang > 0) as total_hutang_produk,
                  (SELECT COUNT(*) FROM transaksi WHERE status_hutang = 1 AND hutang > 0) as total_transaksi_piutang,
                  (SELECT SUM(hutang) FROM transaksi WHERE status_hutang = 1 AND hutang > 0) as total_piutang_transaksi";
$summary_result = mysqli_query($conn, $summary_query);
$summary_data = mysqli_fetch_assoc($summary_result);

// Total all debts
$total_semua_hutang = 
    ($summary_data['total_hutang_produk'] ?? 0) + 
    ($summary_data['total_piutang_transaksi'] ?? 0);
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hutang & Piutang - bengkel BMS</title>
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
      margin-bottom: 1.5rem;
    }
    
    .data-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 12px 20px rgba(0, 0, 0, 0.12);
    }
    
    /* Stats cards - horizontal layout from produk.php */
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
    
    .stats-card-blue {
      border-left-color: #2196F3;
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
    
    .stats-icon-money {
      background-color: #E8F5E9;
      color: #4CAF50;
    }
    
    .stats-icon-receipt {
      background-color: #FFF9C4;
      color: #FFC107;
    }
    
    .stats-icon-total {
      background-color: #FFEBEE;
      color: #F44336;
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
    
    .btn-light {
      border-radius: 8px;
      font-weight: 500;
      transition: all 0.3s ease;
    }
    
    .btn-light:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }
    
    .btn-action {
      padding: 0.4rem 0.7rem;
      border-radius: 6px;
      margin-right: 0.25rem;
    }
    
    /* Tab styling - copied from piutang.php */
    .nav-tabs {
      border-bottom: 2px solid var(--light-orange);
      margin-bottom: 1.5rem;
    }

    .nav-tabs .nav-link {
      border: none;
      color: #6c757d;
      font-weight: 500;
      padding: 0.75rem 1.5rem;
      border-radius: 8px 8px 0 0;
      transition: all 0.3s ease;
    }

    .nav-tabs .nav-link.active {
      color: var(--white); 
      background: linear-gradient(135deg, var(--primary-orange), var(--secondary-orange)); 
      font-weight: 600;
    }

    .nav-tabs .nav-link:hover:not(.active) {
      background-color: #f8f9fa;
      color: var(--primary-orange);
    }
    
    /* Alert styling */
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
    
    /* Responsive adjustments */
    @media (max-width: 992px) {
      .content {
        margin-left: 0;
      }
    }
    
    @media (max-width: 768px) {
      .page-header {
        padding: 1.5rem;
        margin-bottom: 1.5rem;
      }
      
      .data-card {
        padding: 1rem;
      }
      
      .card-header-actions {
        flex-direction: column;
        align-items: flex-start;
      }
      
      .stats-card {
        flex-direction: column;
        text-align: center;
      }
      
      .stats-icon-container {
        margin-right: 0;
        margin-bottom: 10px;
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
          <i class="fas fa-hand-holding-usd me-2"></i>
          Hutang & Piutang
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
            <h1><i class="fas fa-hand-holding-usd me-2"></i>Hutang & Piutang</h1>
            <p class="lead mb-0">Manajemen data hutang produk dan piutang transaksi</p>
          </div>
          <a href="riwayat_hutang_piutang.php" class="btn btn-light btn-lg">
            <i class="fas fa-history me-2"></i>
            Riwayat Pembayaran
          </a>
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
      
      <!-- Filter Controls - Month-Year Picker (kept but no actual filtering) -->
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
        <div class="alert alert-info mt-2 mb-0">
          <i class="fas fa-info-circle me-2"></i>
          <span>Semua data hutang dan piutang akan ditampilkan hingga terbayarkan, terlepas dari bulan yang dipilih</span>
        </div>
      </div>
    
      <!-- Summary Cards - Horizontal Layout -->
      <div class="row g-4 mb-4">
        <div class="col-md-3">
          <div class="stats-card-wrapper stats-card-blue">
            <div class="stats-card">
              <div class="stats-icon-container">
                <i class="fas fa-box stats-icon stats-icon-products"></i>
              </div>
              <div class="stats-content">
                <h3><?= number_format($summary_data['total_produk_hutang'] ?? 0) ?></h3>
                <p>Total Transaksi Hutang Produk</p>
              </div>
            </div>
          </div>
        </div>
        
        <div class="col-md-3">
          <div class="stats-card-wrapper stats-card-green">
            <div class="stats-card">
              <div class="stats-icon-container stats-icon-money">
                <i class="fas fa-money-bill-wave stats-icon"></i>
              </div>
              <div class="stats-content">
                <h3>Rp <?= number_format($summary_data['total_hutang_produk'] ?? 0, 0, ',', '.') ?></h3>
                <p>Total Hutang Produk</p>
              </div>
            </div>
          </div>
        </div>
        
        <div class="col-md-3">
          <div class="stats-card-wrapper stats-card-orange">
            <div class="stats-card">
              <div class="stats-icon-container stats-icon-receipt">
                <i class="fas fa-receipt stats-icon"></i>
              </div>
              <div class="stats-content">
                <h3><?= number_format($summary_data['total_transaksi_piutang'] ?? 0) ?></h3>
                <p>Total Transaksi Piutang</p>
              </div>
            </div>
          </div>
        </div>
        
        <div class="col-md-3">
          <div class="stats-card-wrapper stats-card-red">
            <div class="stats-card">
              <div class="stats-icon-container stats-icon-total">
                <i class="fas fa-hand-holding-usd stats-icon"></i>
              </div>
              <div class="stats-content">
                <h3>Rp <?= number_format($summary_data['total_piutang_transaksi'] ?? 0, 0, ',', '.') ?></h3>
                <p>Total Piutang</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    
      <!-- Tabs for Hutang Produk and Piutang Transaksi -->
      <ul class="nav nav-tabs" id="piutangTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="hutang-produk-tab" data-bs-toggle="tab" data-bs-target="#hutang-produk" type="button" role="tab">
            <i class="fas fa-box me-2"></i> Hutang Produk
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="piutang-transaksi-tab" data-bs-toggle="tab" data-bs-target="#piutang-transaksi" type="button" role="tab">
            <i class="fas fa-receipt me-2"></i> Piutang Transaksi
          </button>
        </li>
      </ul>
    
      <div class="tab-content" id="piutangTabContent">
        <!-- Tab Hutang Produk -->
        <div class="tab-pane fade show active" id="hutang-produk" role="tabpanel" tabindex="0">
          <div class="data-card">
            <div class="card-header-actions">
              <h5 class="card-title">
                <i class="fas fa-box me-2"></i>
                Daftar Produk dengan Hutang
              </h5>
            </div>
          
            <div class="table-responsive">
              <table class="table table-hover" id="hutangProdukTable">
                <thead>
                  <tr>
                    <th width="5%">No</th>
                    <th width="25%">Nama Produk</th>
                    <th width="15%">Kategori</th>
                    <th width="15%">Harga Beli</th>
                    <th width="15%">Nominal Hutang</th>
                    <th width="25%">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                  $no = 1;
                  if (mysqli_num_rows($hutang_produk_result) > 0):
                    while ($row = mysqli_fetch_assoc($hutang_produk_result)):
                      // Get category name
                      $kategori_id = $row['kategori_id'];
                      $kategori_query = "SELECT nama_kategori FROM kategori WHERE id = '$kategori_id'";
                      $kategori_result = mysqli_query($conn, $kategori_query);
                      $kategori_name = mysqli_fetch_assoc($kategori_result)['nama_kategori'] ?? 'Tidak Ada Kategori';
                  ?>
                  <tr>
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($row['nama']) ?></td>
                    <td><?= htmlspecialchars($kategori_name) ?></td>
                    <td>Rp <?= number_format($row['harga_beli'], 0, ',', '.') ?></td>
                    <td>Rp <?= number_format($row['nominal_hutang'], 0, ',', '.') ?></td>
                    <td>
                      <a href="hutang_detail.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-light btn-action">
                        <i class="fas fa-eye me-1"></i> Detail
                      </a>
                    </td>
                  </tr>
                  <?php 
                    endwhile;
                  else:
                  ?>
                  <tr>
                    <td colspan="6" class="text-center py-4">Tidak ada produk dengan hutang</td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      
        <!-- Tab Piutang Transaksi -->
        <div class="tab-pane fade" id="piutang-transaksi" role="tabpanel" tabindex="0">
          <div class="data-card">
            <div class="card-header-actions">
              <h5 class="card-title">
                <i class="fas fa-receipt me-2"></i>
                Daftar Transaksi dengan Piutang Customer
              </h5>
            </div>
          
            <div class="table-responsive">
              <table class="table table-hover" id="piutangTransaksiTable">
                <thead>
                  <tr>
                    <th width="5%">No</th>
                    <th width="10%">Tanggal</th>
                    <th width="20%">Customer</th>
                    <th width="15%">Total Transaksi</th>
                    <th width="15%">Jumlah Piutang</th>
                    <th width="10%">Metode</th>
                    <th width="25%">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                  $no = 1;
                  if (mysqli_num_rows($piutang_transaksi_result) > 0):
                    while ($row = mysqli_fetch_assoc($piutang_transaksi_result)):
                  ?>
                  <tr>
                    <td><?= $no++ ?></td>
                    <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                    <td>
                      <div class="d-flex flex-column">
                        <span class="fw-semibold"><?= htmlspecialchars($row['nama_customer']) ?></span>
                        <small class="text-muted"><?= $row['plat_nomor_motor'] ? htmlspecialchars($row['plat_nomor_motor']) : '-' ?></small>
                      </div>
                    </td>
                    <td>Rp <?= number_format($row['total'], 0, ',', '.') ?></td>
                    <td>Rp <?= number_format($row['hutang'], 0, ',', '.') ?></td>
                    <td>
                      <span class="badge bg-warning text-dark">
                        <i class="fas fa-credit-card me-1"></i> <?= $row['metode_pembayaran'] ?>
                      </span>
                    </td>
                    <td>
                      <a href="transaksi_detail.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-light btn-action">
                        <i class="fas fa-eye me-1"></i> Detail
                      </a>
                    </td>
                  </tr>
                  <?php 
                    endwhile;
                  else:
                  ?>
                  <tr>
                    <td colspan="7" class="text-center py-4">Tidak ada transaksi dengan piutang</td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  <script>
    // Global variable to track current active tab
    let currentTab = 'hutang-produk';
    
    $(document).ready(function() {
      // Check if hutangProdukTable is empty
      if ($('#hutangProdukTable tbody tr').length === 1 && $('#hutangProdukTable tbody tr td[colspan]').length > 0) {
        // For empty tables, don't initialize DataTables at all
        // Just leave the default HTML table with the "no data" message
      } else {
        // Only initialize DataTables if there's actual data
        $('#hutangProdukTable').DataTable({
          language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json',
          },
          pageLength: 10,
          lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Semua"]],
          responsive: true,
          searching: true,
          info: true,
          ordering: true,
          order: [[4, "desc"]] // Order by amount
        });
      }
      
      // Check if piutangTransaksiTable is empty
      if ($('#piutangTransaksiTable tbody tr').length === 1 && $('#piutangTransaksiTable tbody tr td[colspan]').length > 0) {
        // For empty tables, don't initialize DataTables at all
        // Just leave the default HTML table with the "no data" message
      } else {
        // Only initialize DataTables if there's actual data
        $('#piutangTransaksiTable').DataTable({
          language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json',
          },
          pageLength: 10,
          lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Semua"]],
          responsive: true,
          searching: true,
          info: true,
          ordering: true,
          order: [[1, "desc"]] // Order by date
        });
      }
      
      // Auto close alerts after 5 seconds
      setTimeout(function() {
        $("#autoCloseAlert").alert('close');
      }, 5000);
      
      // Set active tab based on URL parameter
      const urlParams = new URLSearchParams(window.location.search);
      const tabParam = urlParams.get('tab');
      
      if (tabParam === 'piutang-transaksi') {
        $('#piutangTabs button[data-bs-target="#piutang-transaksi"]').tab('show');
        currentTab = 'piutang-transaksi';
      }
      
      // Store active tab when changing tabs
      $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        const targetId = $(e.target).attr('data-bs-target').substring(1);
        currentTab = targetId;
        
        const currentUrlParams = new URLSearchParams(window.location.search);
        currentUrlParams.set('tab', targetId);
        
        const newUrl = window.location.pathname + '?' + currentUrlParams.toString();
        history.replaceState(null, '', newUrl);
      });
    });
    
    // Month-Year filter navigation functions
    function filterByMonth(value) {
      // Keep the tab parameter when changing months
      const urlParams = new URLSearchParams(window.location.search);
      const tabParam = urlParams.get('tab') || 'hutang-produk';
      
      window.location.href = '?bulan=' + value + '&tab=' + tabParam;
    }
    
    function setCurrentMonth() {
      const now = new Date();
      const year = now.getFullYear();
      const month = (now.getMonth() + 1).toString().padStart(2, '0');
      const currentMonth = `${year}-${month}`;
      
      filterByMonth(currentMonth);
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
      
      filterByMonth(newValue);
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
      
      filterByMonth(newValue);
    }
  </script>
</body>
</html>