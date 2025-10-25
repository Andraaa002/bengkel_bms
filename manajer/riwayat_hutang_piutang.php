<?php
session_start();

// Check if logged in and manajer
if (!isset($_SESSION['manajer']['logged_in']) || $_SESSION['manajer']['logged_in'] !== true) {
  header("Location: ../login.php");
  exit();
}

include '../config.php'; // Database connection

// Default bulan adalah bulan ini
$bulan_ini = date('Y-m');
$bulan_filter = isset($_GET['bulan']) ? $_GET['bulan'] : $bulan_ini;

// Adjust query to filter by month if specified
$filter_clause = "";
if (!empty($bulan_filter)) {
  $filter_clause = "WHERE DATE_FORMAT(pc.tanggal_bayar, '%Y-%m') = '$bulan_filter'";
}

// Get all payment records (both product debts and transaction debts)
$piutang_cair_query = "SELECT pc.*, 
                       CASE 
                         WHEN pc.transaksi_id = '-1' THEN 'Pembayaran Hutang Produk' 
                         ELSE CONCAT('Transaksi #', pc.transaksi_id) 
                       END as sumber,
                       CASE 
                         WHEN pc.transaksi_id = '-1' THEN 'Produk' 
                         ELSE 'Transaksi' 
                       END as tipe,
                       COALESCE(m.nama, a.nama, k.nama, 'System') as nama_user,
                       CASE 
                         WHEN pc.transaksi_id = '-1' THEN '-' 
                         ELSE t.nama_customer
                       END as nama_customer,
                       CASE 
                         WHEN pc.transaksi_id = '-1' THEN 'LUNAS'
                         WHEN t.status_hutang = 0 THEN 'LUNAS' 
                         ELSE 'HUTANG'
                       END as status_pembayaran
                       FROM piutang_cair pc
                       LEFT JOIN manajer m ON pc.created_by = m.id_manajer
                       LEFT JOIN admin a ON pc.created_by = a.id_admin
                       LEFT JOIN karyawan k ON pc.created_by = k.id_karyawan
                       LEFT JOIN transaksi t ON pc.transaksi_id = t.id
                       $filter_clause
                       ORDER BY pc.tanggal_bayar DESC, pc.id DESC";
$piutang_cair_result = mysqli_query($conn, $piutang_cair_query);

// Check if query was successful
if (!$piutang_cair_result) {
  error_log("MySQL Error: " . mysqli_error($conn));
  $piutang_cair_result = [];
  $has_results = false;
} else {
  $has_results = mysqli_num_rows($piutang_cair_result) > 0;
}

// Get statistics based on month filter
$stats_query = "SELECT 
               COUNT(id) as total_pembayaran,
               SUM(jumlah_bayar) as total_bayar,
               COUNT(CASE WHEN transaksi_id = '-1' THEN 1 END) as total_bayar_produk,
               COUNT(CASE WHEN transaksi_id != '-1' THEN 1 END) as total_bayar_transaksi,
               SUM(CASE WHEN transaksi_id = '-1' THEN jumlah_bayar ELSE 0 END) as total_nominal_produk,
               SUM(CASE WHEN transaksi_id != '-1' THEN jumlah_bayar ELSE 0 END) as total_nominal_transaksi
               FROM piutang_cair
               " . ($filter_clause ? str_replace('pc.', '', $filter_clause) : "");
$stats_result = mysqli_query($conn, $stats_query);

if (!$stats_result) {
  error_log("MySQL Stats Error: " . mysqli_error($conn));
  $stats_data = [
    'total_pembayaran' => 0,
    'total_bayar' => 0,
    'total_bayar_produk' => 0,
    'total_bayar_transaksi' => 0,
    'total_nominal_produk' => 0,
    'total_nominal_transaksi' => 0
  ];
} else {
  $stats_data = mysqli_fetch_assoc($stats_result);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Riwayat Pembayaran Piutang - BMS Bengkel</title>
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
    
    .stats-icon-total {
      color: #EF6C00;
    }
    
    .stats-icon-money {
      background-color: #E8F5E9;
      color: #4CAF50;
    }
    
    .stats-icon-products {
      background-color: #E8F5E9;
      color: #4CAF50;
    }
    
    .stats-icon-transactions {
      background-color: #E3F2FD;
      color: #1976D2;
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
    
    /* Badge styling */
    .badge-produk {
      background-color: rgba(25, 135, 84, 0.15);
      color: #198754;
      font-weight: 500;
      padding: 0.5rem 0.75rem;
      border-radius: 6px;
      white-space: nowrap;
      display: inline-block;
    }
    
    .badge-transaksi {
      background-color: rgba(13, 110, 253, 0.15);
      color: #0d6efd;
      font-weight: 500;
      padding: 0.5rem 0.75rem;
      border-radius: 6px;
      white-space: nowrap;
      display: inline-block;
    }
    
    /* Status badge styling */
    .badge-lunas {
      background-color: rgba(25, 135, 84, 0.15);
      color: #198754;
      font-weight: 500;
      padding: 0.4rem 0.6rem;
      border-radius: 6px;
      white-space: nowrap;
      display: inline-block;
    }
    
    .badge-hutang {
      background-color: rgba(255, 193, 7, 0.15);
      color: #fd7e14;
      font-weight: 500;
      padding: 0.4rem 0.6rem;
      border-radius: 6px;
      white-space: nowrap;
      display: inline-block;
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
          <i class="fas fa-history me-2"></i>
          Riwayat Pembayaran
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
            <h1><i class="fas fa-history me-2"></i>Riwayat Pembayaran</h1>
            <p class="lead mb-0">Catatan pembayaran hutang produk dan piutang transaksi</p>
          </div>
          <a href="hutang_piutang.php" class="btn btn-light btn-lg">
            <i class="fas fa-hand-holding-usd me-2"></i>
            Kembali ke Hutang & Piutang
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
      
      <!-- Filter Controls - Month-Year Picker -->
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
      </div>
    
      <!-- Summary Cards - Horizontal Layout -->
      <div class="row g-4 mb-4">
        <div class="col-md-3">
          <div class="stats-card-wrapper stats-card-orange">
            <div class="stats-card">
              <div class="stats-icon-container">
                <i class="fas fa-money-bill-wave stats-icon stats-icon-total"></i>
              </div>
              <div class="stats-content">
                <h3><?= number_format($stats_data['total_pembayaran'] ?? 0) ?></h3>
                <p>Total Pembayaran</p>
              </div>
            </div>
          </div>
        </div>
        
        <div class="col-md-3">
          <div class="stats-card-wrapper stats-card-orange">
            <div class="stats-card">
              <div class="stats-icon-container">
                <i class="fas fa-hand-holding-usd stats-icon stats-icon-total"></i>
              </div>
              <div class="stats-content">
                <h3>Rp <?= number_format($stats_data['total_bayar'] ?? 0, 0, ',', '.') ?></h3>
                <p>Total Nominal</p>
              </div>
            </div>
          </div>
        </div>
        
        <div class="col-md-3">
          <div class="stats-card-wrapper stats-card-green">
            <div class="stats-card">
              <div class="stats-icon-container stats-icon-products">
                <i class="fas fa-box stats-icon"></i>
              </div>
              <div class="stats-content">
                <h3>Rp <?= number_format($stats_data['total_nominal_produk'] ?? 0, 0, ',', '.') ?></h3>
                <p>Pembayaran Hutang Produk</p>
                <small class="text-muted"><?= $stats_data['total_bayar_produk'] ?? 0 ?> transaksi</small>
              </div>
            </div>
          </div>
        </div>
        
        <div class="col-md-3">
          <div class="stats-card-wrapper stats-card-blue">
            <div class="stats-card">
              <div class="stats-icon-container stats-icon-transactions">
                <i class="fas fa-receipt stats-icon"></i>
              </div>
              <div class="stats-content">
                <h3>Rp <?= number_format($stats_data['total_nominal_transaksi'] ?? 0, 0, ',', '.') ?></h3>
                <p>Pembayaran Piutang Transaksi</p>
                <small class="text-muted"><?= $stats_data['total_bayar_transaksi'] ?? 0 ?> transaksi</small>
              </div>
            </div>
          </div>
        </div>
      </div>
    
      <!-- Payment History Table -->
      <div class="data-card">
        <div class="card-header-actions">
          <h5 class="card-title">
            <i class="fas fa-history me-2"></i> 
            Riwayat Pembayaran
            <span class="text-muted fs-6 ms-2">
              (Bulan: <?= date('F Y', strtotime($bulan_filter)) ?>)
            </span>
          </h5>
          
          <?php if ($has_results): ?>
            <div>
              <a href="export_riwayat_pdf.php?bulan=<?= $bulan_filter ?>" class="btn btn-danger btn-sm">
                <i class="fas fa-file-pdf me-2"></i> Export PDF
              </a>
              <a href="export_riwayat_excel.php?bulan=<?= $bulan_filter ?>" class="btn btn-success btn-sm ms-2">
                <i class="fas fa-file-excel me-2"></i> Export Excel
              </a>
            </div>
          <?php endif; ?>
        </div>
        
        <div class="table-responsive">
          <table class="table table-hover" id="riwayatTable">
            <thead>
              <tr>
                <th width="4%">No</th>
                <th width="10%">Tanggal Bayar</th>
                <th width="12%">Tipe</th>
                <th width="15%">Customer</th>
                <th width="10%">Status</th>
                <th width="13%">Keterangan</th>
                <th width="12%">Jumlah</th>
                <th width="12%">Dibuat Oleh</th>
                <th width="12%">Detail</th>
              </tr>
            </thead>
            <tbody>
              <?php 
              $no = 1;
              if ($has_results):
                while ($row = mysqli_fetch_assoc($piutang_cair_result)):
              ?>
              <tr>
                <td><?= $no++ ?></td>
                <td><?= date('d/m/Y', strtotime($row['tanggal_bayar'])) ?></td>
                <td class="text-center">
                  <?php if ($row['tipe'] == 'Produk'): ?>
                    <span class="badge-produk">
                      <i class="fas fa-box me-1"></i> Hutang Produk
                    </span>
                  <?php else: ?>
                    <span class="badge-transaksi">
                      <i class="fas fa-receipt me-1"></i> Piutang Transaksi
                    </span>
                  <?php endif; ?>
                </td>
                <td>
                  <?= htmlspecialchars($row['nama_customer'] ?? '-') ?>
                </td>
                <td class="text-center">
                  <?php if ($row['status_pembayaran'] == 'LUNAS'): ?>
                    <span class="badge-lunas">
                      <i class="fas fa-check-circle me-1"></i> LUNAS
                    </span>
                  <?php elseif ($row['status_pembayaran'] == 'HUTANG'): ?>
                    <span class="badge-hutang">
                      <i class="fas fa-exclamation-circle me-1"></i> HUTANG
                    </span>
                  <?php else: ?>
                    <span>-</span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($row['keterangan']) ?></td>
                <td>Rp <?= number_format($row['jumlah_bayar'], 0, ',', '.') ?></td>
                <td><?= htmlspecialchars($row['nama_user'] ?? 'System') ?></td>
                <td>
                  <?php if ($row['transaksi_id'] != '-1'): ?>
                    <a href="transaksi_detail.php?id=<?= $row['transaksi_id'] ?>" class="btn btn-sm btn-light btn-action">
                      <i class="fas fa-search me-1"></i> Detail
                    </a>
                  <?php else: ?>
                    <?php
                      // Ekstrak nama produk dari keterangan
                      preg_match('/Pembayaran hutang produk: (.*?) -/', $row['keterangan'], $matches);
                      $product_name = isset($matches[1]) ? $matches[1] : '';
                      
                      // Cari ID produk berdasarkan nama
                      $product_query = "SELECT id FROM produk WHERE nama = '" . mysqli_real_escape_string($conn, $product_name) . "' LIMIT 1";
                      $product_result = mysqli_query($conn, $product_query);
                      $product_id = mysqli_num_rows($product_result) > 0 ? mysqli_fetch_assoc($product_result)['id'] : '';
                    ?>
                    <?php if (!empty($product_id)): ?>
                    <a href="hutang_detail.php?id=<?= $product_id ?>" class="btn btn-sm btn-light btn-action">
                      <i class="fas fa-search me-1"></i> Detail
                    </a>
                    <?php else: ?>
                    <button class="btn btn-sm btn-light btn-action" disabled>
                      <i class="fas fa-box me-1"></i> Produk
                    </button>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
              </tr>
              <?php 
                endwhile;
              else:
              ?>
              <tr>
                <td colspan="9" class="text-center py-4">
                  Tidak ada data pembayaran pada bulan <?= date('F Y', strtotime($bulan_filter)) ?>
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
  <script>
    $(document).ready(function() {
      // Check if table is empty
      if ($('#riwayatTable tbody tr').length === 1 && $('#riwayatTable tbody tr td[colspan]').length > 0) {
        // For empty tables, don't initialize DataTables at all
      } else {
        // Only initialize DataTables if there's actual data
        $('#riwayatTable').DataTable({
          language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json',
          },
          pageLength: 10,
          lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Semua"]],
          responsive: true,
          searching: false,
          info: false,
          ordering: true,
          order: [[1, "desc"]] // Order by date
        });
      }
      
      // Auto close alerts after 5 seconds
      setTimeout(function() {
        $("#autoCloseAlert").alert('close');
      }, 5000);
      
// Export to Excel functionality
$('#exportBtn').on('click', function() {
  // Create a new workbook
  const workbook = XLSX.utils.book_new();
  
  // Filter date information for the filename
  let filename = 'Riwayat_Pembayaran';
  let title = 'Riwayat Pembayaran Piutang & Hutang';
  
  filename += '_<?= date('Y-m', strtotime($bulan_filter)) ?>';
  title += ' - Bulan <?= date('F Y', strtotime($bulan_filter)) ?>';
  
  // Get table data
  const tableData = [];
  
  // Add header row
  tableData.push([
    'No', 
    'Tanggal Bayar', 
    'Tipe', 
    'Customer',
    'Status',
    'Keterangan', 
    'Jumlah Bayar', 
    'Dibuat Oleh'
  ]);
  
  // Get data from the table (excluding the action column)
  $('#riwayatTable tbody tr').each(function() {
    const row = [];
    $(this).find('td').each(function(index) {
      if (index < 8) { // Exclude action column
        let cellText = $(this).text().trim();
        // Clean up any HTML artifacts
        cellText = cellText.replace(/[\t\n]/g, ' ').trim();
        row.push(cellText);
      }
    });
    
    if (row.length > 0) {
      tableData.push(row);
    }
  });
  
  // Add summary row
  tableData.push(['', '', '', '', '', '', '', '']);
  tableData.push(['', '', 'RINGKASAN', '', '', '', '', '']);
  tableData.push(['', '', 'Total Pembayaran', '<?= $stats_data['total_pembayaran'] ?> transaksi', '', '', '', '']);
  tableData.push(['', '', 'Total Nominal', 'Rp <?= number_format($stats_data['total_bayar'], 0, ',', '.') ?>', '', '', '', '']);
  tableData.push(['', '', 'Pembayaran Hutang Produk', 'Rp <?= number_format($stats_data['total_nominal_produk'], 0, ',', '.') ?>', '<?= $stats_data['total_bayar_produk'] ?> transaksi', '', '', '']);
  tableData.push(['', '', 'Pembayaran Piutang Transaksi', 'Rp <?= number_format($stats_data['total_nominal_transaksi'], 0, ',', '.') ?>', '<?= $stats_data['total_bayar_transaksi'] ?> transaksi', '', '', '']);
  
  // Create worksheet and add to workbook
  const worksheet = XLSX.utils.aoa_to_sheet(tableData);
  XLSX.utils.book_append_sheet(workbook, worksheet, 'Riwayat');
  
  // Generate Excel file and trigger download
  XLSX.writeFile(workbook, filename + '.xlsx');
});
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