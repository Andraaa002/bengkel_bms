<?php
session_start();

// Check if logged in and manajer
if (!isset($_SESSION['manajer']['logged_in']) || $_SESSION['manajer']['logged_in'] !== true) {
  header("Location: ../login.php");
  exit();
}

include '../config.php'; // Database connection

// Get product ID from URL parameter
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = "ID Produk tidak ditemukan!";
    $_SESSION['alert_type'] = "danger";
    header("Location: hutang_piutang.php");
    exit();
}

$produk_id = mysqli_real_escape_string($conn, $_GET['id']);

// Get product details
$produk_query = "SELECT p.*, k.nama_kategori 
                FROM produk p 
                LEFT JOIN kategori k ON p.kategori_id = k.id 
                WHERE p.id = '$produk_id'";
$produk_result = mysqli_query($conn, $produk_query);

if (mysqli_num_rows($produk_result) == 0) {
    $_SESSION['message'] = "Produk tidak ditemukan!";
    $_SESSION['alert_type'] = "danger";
    header("Location: hutang_piutang.php");
    exit();
}

$produk = mysqli_fetch_assoc($produk_result);
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Detail Produk - BMS Bengkel</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
          <i class="fas fa-box me-2"></i>
          Detail Produk
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
            <h1><i class="fas fa-box me-2"></i>Detail Produk</h1>
            <p class="lead mb-0">
              Informasi lengkap produk: <?= htmlspecialchars($produk['nama']) ?>
            </p>
          </div>
          <div>
            <a href="hutang_piutang.php" class="btn btn-light">
              <i class="fas fa-arrow-left me-2"></i>
              Kembali ke Hutang & Piutang
            </a>
            <?php if ($produk['hutang_sparepart'] == 'Hutang' && $produk['nominal_hutang'] > 0): ?>
            <a href="bayar_hutang.php?id=<?= $produk_id ?>" class="btn btn-primary ms-2">
              <i class="fas fa-money-bill-wave me-2"></i>
              Bayar Hutang
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
    
      <!-- Product Details -->
      <div class="data-card">
        <div class="card-header-actions">
          <h5 class="card-title">
            <i class="fas fa-info-circle me-2"></i>
            Informasi Produk
          </h5>
          <span class="badge <?= $produk['hutang_sparepart'] == 'Hutang' ? 'bg-warning text-dark' : 'bg-success' ?>">
            <?php if ($produk['hutang_sparepart'] == 'Hutang'): ?>
              <i class="fas fa-exclamation-circle me-1"></i> HUTANG
            <?php else: ?>
              <i class="fas fa-check-circle me-1"></i> LUNAS
            <?php endif; ?>
          </span>
        </div>
        
        <div class="detail-section">
          <div class="row">
            <div class="col-md-6">
              <div class="detail-row">
                <div class="detail-label">ID Produk</div>
                <div class="detail-value">#<?= $produk_id ?></div>
              </div>
              <div class="detail-row">
                <div class="detail-label">Nama Produk</div>
                <div class="detail-value"><?= htmlspecialchars($produk['nama']) ?></div>
              </div>
              <div class="detail-row">
                <div class="detail-label">Kategori</div>
                <div class="detail-value"><?= htmlspecialchars($produk['nama_kategori'] ?? 'Tidak ada kategori') ?></div>
              </div>
              <div class="detail-row">
                <div class="detail-label">Stok</div>
                <div class="detail-value"><?= number_format($produk['stok']) ?> unit</div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="detail-row">
                <div class="detail-label">Harga Beli</div>
                <div class="detail-value">Rp <?= number_format($produk['harga_beli'], 0, ',', '.') ?></div>
              </div>
              <div class="detail-row">
                <div class="detail-label">Harga Jual</div>
                <div class="detail-value">Rp <?= number_format($produk['harga_jual'], 0, ',', '.') ?></div>
              </div>
              <div class="detail-row">
                <div class="detail-label">Status Pembayaran</div>
                <div class="detail-value"><?= htmlspecialchars($produk['hutang_sparepart']) ?></div>
              </div>
              <?php if ($produk['hutang_sparepart'] == 'Hutang'): ?>
              <div class="detail-row">
                <div class="detail-label">Sisa Hutang</div>
                <div class="detail-value text-danger fw-bold">Rp <?= number_format($produk['nominal_hutang'], 0, ',', '.') ?></div>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
        
        <!-- Payment History section has been removed -->
        
      </div>
    </div>
  </div>
  
<!-- JavaScript Libraries -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<script>
  $(document).ready(function() {
    // Auto close alerts after 5 seconds
    setTimeout(function() {
      $('.alert').alert('close');
    }, 5000);
  });
</script>
</body>
</html>