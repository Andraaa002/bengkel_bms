<?php
session_start();

// Check if logged in and manajer
if (!isset($_SESSION['manajer']['logged_in']) || $_SESSION['manajer']['logged_in'] !== true) {
  header("Location: ../login.php");
  exit();
}

include '../config.php'; // Database connection

// Process payment form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_hutang_transaksi') {
    $transaksi_id = mysqli_real_escape_string($conn, $_POST['transaksi_id']);
    $jumlah_bayar = mysqli_real_escape_string($conn, $_POST['jumlah_bayar']);
    $tanggal_bayar = mysqli_real_escape_string($conn, $_POST['tanggal_bayar']);
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
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
            $_SESSION['message'] = "Status hutang transaksi berhasil diupdate menjadi LUNAS!";
            $_SESSION['alert_type'] = "success";
        }
    } else {
        $_SESSION['message'] = "Transaksi tidak ditemukan atau tidak memiliki hutang!";
        $_SESSION['alert_type'] = "danger";
    }
    
    header("Location: transaksi_detail.php?id=" . $transaksi_id);
    exit();
}

// Get transaction ID from URL parameter
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = "ID Transaksi tidak ditemukan!";
    $_SESSION['alert_type'] = "danger";
    header("Location: hutang_piutang.php");
    exit();
}

$transaksi_id = mysqli_real_escape_string($conn, $_GET['id']);

// Get transaction details
$transaksi_query = "SELECT t.* FROM transaksi t WHERE t.id = '$transaksi_id'";
$transaksi_result = mysqli_query($conn, $transaksi_query);

if (mysqli_num_rows($transaksi_result) == 0) {
    $_SESSION['message'] = "Transaksi tidak ditemukan!";
    $_SESSION['alert_type'] = "danger";
    header("Location: hutang_piutang.php");
    exit();
}

$transaksi = mysqli_fetch_assoc($transaksi_result);

// Get transaction items - Fixed query to properly include manual products and their prices
$items_query = "SELECT td.*, td.nama_produk_manual, td.harga_satuan, 
                p.nama as nama_produk, p.kategori_id, k.nama_kategori
                FROM transaksi_detail td
                LEFT JOIN produk p ON td.produk_id = p.id
                LEFT JOIN kategori k ON p.kategori_id = k.id
                WHERE td.transaksi_id = '$transaksi_id' 
                ORDER BY td.id ASC";
                
$items_result = mysqli_query($conn, $items_query);

// Check for SQL errors in the items query
if (!$items_result) {
    error_log("SQL Error in items query: " . mysqli_error($conn));
}

// Get payment history for this transaction
$payment_query = "SELECT pc.*, 
                 CASE
                   WHEN m.nama IS NOT NULL THEN m.nama
                   ELSE 'System'
                 END as created_by_name
                 FROM piutang_cair pc
                 LEFT JOIN manajer m ON pc.created_by = m.id_manajer
                 WHERE pc.transaksi_id = '$transaksi_id'
                 ORDER BY pc.tanggal_bayar DESC, pc.id DESC";
$payment_result = mysqli_query($conn, $payment_query);

// Check for SQL errors in the payment query
if (!$payment_result) {
    error_log("SQL Error in payment query: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Detail Transaksi - BMS Bengkel</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      /* Manajer orange theme */
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
    
    /* Content area */
    .content {
      margin-left: 280px;
      transition: margin-left 0.3s ease;
      min-height: 100vh;
    }
    
    /* Navbar Styling */
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
    
    /* Page header section */
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
    
    /* Data card */
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
    
    /* Card header with actions */
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
    
    /* Table styling */
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
    
    /* Button styling */
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
    
    /* Tab styling - updated to improve readability of active tab */
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
      color: var(--white); /* Changed to white for better contrast */
      background: linear-gradient(135deg, var(--primary-orange), var(--secondary-orange)); /* Changed to match header gradient */
      font-weight: 600;
    }
    
    .nav-tabs .nav-link:hover:not(.active) {
      background-color: #f8f9fa;
      color: var(--primary-orange);
    }
    
    /* Status badge styling */
    .badge {
      padding: 0.5rem 0.75rem;
      font-weight: 500;
      border-radius: 6px;
    }
    
    /* Detail and info section styling */
    .detail-section {
      background-color: var(--light-gray);
      border-radius: 10px;
      padding: 1.25rem;
      margin-bottom: 1.5rem;
    }
    
    .detail-row {
      display: flex;
      flex-wrap: wrap;
      margin-bottom: 0.75rem;
    }
    
    .detail-label {
      flex: 0 0 200px;
      font-weight: 600;
      color: var(--text-dark);
    }
    
    .detail-value {
      flex: 1;
      min-width: 150px;
    }
    
    /* Container fluid fix */
    .container-fluid {
      padding-left: 15px;
      padding-right: 15px;
    }
    
    /* Row margins */
    .row {
      margin-right: 0;
      margin-left: 0;
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
      
      .detail-label, .detail-value {
        flex: 0 0 100%;
      }
      
      .detail-label {
        margin-bottom: 0.25rem;
      }
      
      .detail-row {
        margin-bottom: 1rem;
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
          <i class="fas fa-file-invoice-dollar me-2"></i>
          Detail Transaksi
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
            <h1><i class="fas fa-file-invoice-dollar me-2"></i>Detail Transaksi #<?= $transaksi_id ?></h1>
            <p class="lead mb-0">
              Transaksi untuk <?= htmlspecialchars($transaksi['nama_customer']) ?> 
              pada tanggal <?= date('d/m/Y', strtotime($transaksi['tanggal'])) ?>
            </p>
          </div>
          <div>
            <a href="hutang_piutang.php" class="btn btn-light">
              <i class="fas fa-arrow-left me-2"></i>
              Kembali ke Hutang & Piutang
            </a>
            <?php if (isset($transaksi['status_hutang']) && $transaksi['status_hutang'] == 1 && isset($transaksi['hutang']) && $transaksi['hutang'] > 0): ?>
            <a href="bayar_transaksi.php?id=<?= $transaksi_id ?>" class="btn btn-primary ms-2">
              <i class="fas fa-money-bill-wave me-2"></i>
              Bayar Piutang Pelanggan
            </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    
      <!-- Alert Messages -->
      <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['alert_type'] ?> alert-dismissible fade show" role="alert">
          <i class="fas fa-<?= $_SESSION['alert_type'] == 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
          <?= $_SESSION['message'] ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php 
          unset($_SESSION['message']);
          unset($_SESSION['alert_type']);
        ?>
      <?php endif; ?>
    
      <!-- Transaction Details -->
      <div class="data-card">
        <div class="card-header-actions">
          <h5 class="card-title">
            <i class="fas fa-info-circle me-2"></i>
            Informasi Transaksi
          </h5>
          <span class="badge <?= isset($transaksi['status_hutang']) && $transaksi['status_hutang'] == 0 ? 'bg-success' : 'bg-warning text-dark' ?>">
            <?php if (isset($transaksi['status_hutang']) && $transaksi['status_hutang'] == 0): ?>
              <i class="fas fa-check-circle me-1"></i> LUNAS
            <?php else: ?>
              <i class="fas fa-exclamation-circle me-1"></i> HUTANG
            <?php endif; ?>
          </span>
        </div>
        
        <div class="detail-section">
          <div class="row">
            <div class="col-md-6">
              <div class="detail-row">
                <div class="detail-label">ID Transaksi</div>
                <div class="detail-value">#<?= $transaksi_id ?></div>
              </div>
              <div class="detail-row">
                <div class="detail-label">Tanggal</div>
                <div class="detail-value"><?= date('d/m/Y H:i', strtotime($transaksi['tanggal'])) ?></div>
              </div>
              <div class="detail-row">
                <div class="detail-label">Nama Customer</div>
                <div class="detail-value"><?= htmlspecialchars($transaksi['nama_customer']) ?></div>
              </div>
              <div class="detail-row">
                <div class="detail-label">No. Telepon</div>
                <div class="detail-value">
                  <?= !empty($transaksi['no_whatsapp']) ? htmlspecialchars($transaksi['no_whatsapp']) : '-' ?>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="detail-row">
                <div class="detail-label">Plat Nomor</div>
                <div class="detail-value">
                  <?= !empty($transaksi['plat_nomor_motor']) ? htmlspecialchars($transaksi['plat_nomor_motor']) : '-' ?>
                </div>
              </div>
              <div class="detail-row">
                <div class="detail-label">Metode Pembayaran</div>
                <div class="detail-value"><?= isset($transaksi['metode_pembayaran']) ? htmlspecialchars($transaksi['metode_pembayaran']) : '-' ?></div>
              </div>
              <div class="detail-row">
                <div class="detail-label">Total Transaksi</div>
                <div class="detail-value">Rp <?= isset($transaksi['total']) ? number_format($transaksi['total'], 0, ',', '.') : '0' ?></div>
              </div>
              <?php if (isset($transaksi['status_hutang']) && $transaksi['status_hutang'] == 1 && isset($transaksi['hutang'])): ?>
              <div class="detail-row">
                <div class="detail-label">Sisa Hutang</div>
                <div class="detail-value text-danger fw-bold">Rp <?= number_format($transaksi['hutang'], 0, ',', '.') ?></div>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
        
<!-- Detail Tabs - Hanya tampilkan tab payment jika ada riwayat pembayaran atau status LUNAS -->
<ul class="nav nav-tabs" id="transactionTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="items-tab" data-bs-toggle="tab" data-bs-target="#items" type="button" role="tab">
      <i class="fas fa-boxes me-2"></i> Detail Item
    </button>
  </li>
  <?php if (mysqli_num_rows($payment_result) > 0 || (isset($transaksi['status_hutang']) && $transaksi['status_hutang'] == 0)): ?>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="payment-tab" data-bs-toggle="tab" data-bs-target="#payment" type="button" role="tab">
      <i class="fas fa-history me-2"></i> Riwayat Pembayaran
    </button>
  </li>
  <?php endif; ?>
</ul>

<div class="tab-content" id="transactionTabContent">
  <!-- Detail Item Tab -->
  <div class="tab-pane fade show active" id="items" role="tabpanel" tabindex="0">
    <div class="table-responsive">
      <table class="table table-hover" id="itemsTable">
        <thead>
          <tr>
            <th width="5%">No</th>
            <th width="35%">Nama Produk</th>
            <th width="15%">Kategori</th>
            <th width="10%">Jumlah</th>
            <th width="15%">Harga Satuan</th>
            <th width="20%">Subtotal</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          $no = 1;
          $total = 0;
          if (mysqli_num_rows($items_result) > 0):
            while ($row = mysqli_fetch_assoc($items_result)):
              // Ensure we have numeric values
              $harga = isset($row['harga_satuan']) ? intval($row['harga_satuan']) : 0;
              $jumlah = isset($row['jumlah']) ? intval($row['jumlah']) : 0;
              
              // Calculate subtotal (without diskon)
              $subtotal = $jumlah * $harga;
              $total += $subtotal;
          ?>
          <tr>
            <td><?= $no++ ?></td>
            <td>
              <?php
              if (!empty($row['nama_produk_manual'])) {
                echo htmlspecialchars($row['nama_produk_manual']);
              } elseif (isset($row['nama_produk'])) {
                echo htmlspecialchars($row['nama_produk']);
              } else {
                echo 'Produk tidak ditemukan';
              }
              ?>
            </td>
            <td>
              <?php
              if (!empty($row['nama_produk_manual']) && empty($row['nama_kategori'])) {
                echo 'Tidak dalam kategori';
              } else {
                echo isset($row['nama_kategori']) ? htmlspecialchars($row['nama_kategori']) : '-';
              }
              ?>
            </td>
            <td><?= $jumlah ?></td>
            <td>Rp <?= number_format($harga, 0, ',', '.') ?></td>
            <td>Rp <?= number_format($subtotal, 0, ',', '.') ?></td>
          </tr>
          <?php 
            endwhile;
          else:
          ?>
          <tr>
            <td colspan="6" class="text-center py-4">Tidak ada item dalam transaksi ini</td>
          </tr>
          <?php endif; ?>
        </tbody>
        <tfoot>
          <tr class="fw-bold">
            <td colspan="5" class="text-end">Total</td>
            <td>Rp <?= number_format($total, 0, ',', '.') ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
  
  <!-- Payment History Tab - Hanya ditampilkan jika ada riwayat pembayaran atau status LUNAS -->
  <?php if (mysqli_num_rows($payment_result) > 0 || (isset($transaksi['status_hutang']) && $transaksi['status_hutang'] == 0)): ?>
  <div class="tab-pane fade" id="payment" role="tabpanel" tabindex="0">
    <div class="table-responsive">
      <table class="table table-hover" id="paymentTable">
        <thead>
          <tr>
            <th width="5%">No</th>
            <th width="15%">Tanggal</th>
            <th width="20%">Jumlah</th>
            <th width="40%">Keterangan</th>
            <th width="20%">Dibuat Oleh</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          $no = 1;
          if (mysqli_num_rows($payment_result) > 0):
            mysqli_data_seek($payment_result, 0); // Reset pointer to beginning
            while ($row = mysqli_fetch_assoc($payment_result)):
          ?>
          <tr>
            <td><?= $no++ ?></td>
            <td><?= date('d/m/Y', strtotime($row['tanggal_bayar'])) ?></td>
            <td>Rp <?= isset($row['jumlah_bayar']) ? number_format($row['jumlah_bayar'], 0, ',', '.') : '0' ?></td>
            <td><?= isset($row['keterangan']) ? htmlspecialchars($row['keterangan'] ?: 'Pembayaran piutang transaksi') : 'Pembayaran piutang transaksi' ?></td>
            <td><?= isset($row['created_by_name']) ? htmlspecialchars($row['created_by_name']) : 'System' ?></td>
          </tr>
          <?php 
            endwhile;
          else:
          ?>
          <tr>
            <td colspan="5" class="text-center py-4">Belum ada riwayat pembayaran</td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Add Bootstrap JS and DataTables JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
  // Initialize DataTables
  $('#itemsTable').DataTable({
    paging: false,
    searching: false,
    info: false,
    responsive: true,
    language: {
      emptyTable: "Tidak ada item dalam transaksi ini"
    }
  });
  
  // Initialize payment table if it exists
  if ($('#paymentTable').length > 0) {
    $('#paymentTable').DataTable({
      paging: false,
      searching: false,
      info: false,
      responsive: true,
      language: {
        emptyTable: "Belum ada riwayat pembayaran"
      }
    });
  }
  
  // Auto close alerts after 5 seconds
  setTimeout(function() {
    $('.alert').alert('close');
  }, 5000);
});
</script>
</body>
</html>