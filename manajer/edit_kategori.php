<?php
session_start();

// Check if logged in and manajer
if (!isset($_SESSION['manajer']['logged_in']) || $_SESSION['manajer']['logged_in'] !== true) {
  header("Location: ../login.php");
  exit();
}

include '../config.php'; // Database connection

// Redirect if no ID provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
  $_SESSION['message'] = "ID Kategori tidak ditemukan!";
  $_SESSION['alert_type'] = "danger";
  header("Location: kategori.php");
  exit();
}

$id = mysqli_real_escape_string($conn, $_GET['id']);

// Fetch category data
$query = "SELECT * FROM kategori WHERE id = '$id'";
$result = mysqli_query($conn, $query);

// Check if category exists
if (mysqli_num_rows($result) == 0) {
  $_SESSION['message'] = "Kategori tidak ditemukan!";
  $_SESSION['alert_type'] = "danger";
  header("Location: kategori.php");
  exit();
}

$row = mysqli_fetch_assoc($result);
$nama_kategori = $row['nama_kategori'];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  // Get form data
  $new_nama_kategori = mysqli_real_escape_string($conn, $_POST['nama_kategori']);
  
  // Update database
  $update_query = "UPDATE kategori SET nama_kategori = '$new_nama_kategori' WHERE id = '$id'";
  
  if (mysqli_query($conn, $update_query)) {
    $_SESSION['message'] = "Kategori berhasil diperbarui!";
    $_SESSION['alert_type'] = "success";
    header("Location: kategori.php");
    exit();
  } else {
    $_SESSION['message'] = "Gagal memperbarui kategori: " . mysqli_error($conn);
    $_SESSION['alert_type'] = "danger";
    header("Location: kategori.php");
    exit();
  }
}

// Count products in this category
$products_query = "SELECT COUNT(*) as count FROM produk WHERE kategori_id = '$id'";
$products_result = mysqli_query($conn, $products_query);
$products_count = mysqli_fetch_assoc($products_result)['count'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Kategori - BMS Bengkel</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary-orange: #EF6C00;
      --secondary-orange: #F59E0B;
      --light-orange: #FFF3E0;
      --accent-orange: #D84315;
      --white: #ffffff;
      --light-gray: #f8f9fa;
      --text-dark: #2C3E50;
    }
    
    body {
      background-color: var(--light-gray);
      font-family: 'Poppins', 'Arial', sans-serif;
      color: var(--text-dark);
      margin: 0;
      padding: 0;
    }
    
    /* Navbar Styling - Synchronized with dashboard.php */
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
    
    /* Content area */
    .content {
      margin-left: 280px;
      transition: margin-left 0.3s ease;
      min-height: 100vh;
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
    
    /* Form card */
    .form-card {
      border: none;
      border-radius: 15px;
      overflow: hidden;
      box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
      background-color: var(--white);
      padding: 1.5rem;
      transition: all 0.3s ease;
    }
    
    .form-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 12px 20px rgba(0, 0, 0, 0.12);
    }
    
    /* Form section header */
    .form-section-header {
      border-bottom: 2px solid var(--light-orange);
      padding-bottom: 1rem;
      margin-bottom: 1.5rem;
    }
    
    .form-section-title {
      font-size: 1.25rem;
      font-weight: 600;
      color: var(--primary-orange);
      margin-bottom: 0;
    }
    
    /* Form group styling */
    .form-label {
      font-weight: 600;
      color: var(--text-dark);
    }
    
    .form-control {
      border-radius: 8px;
      border: 1px solid #e0e6ed;
      padding: 0.75rem 1rem;
      font-size: 1rem;
      transition: all 0.3s ease;
    }
    
    .form-control:focus {
      border-color: var(--secondary-orange);
      box-shadow: 0 0 0 0.25rem rgba(239, 108, 0, 0.25);
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
    
    .btn-secondary {
      background-color: #6c757d;
      border: none;
      border-radius: 8px;
      padding: 0.75rem 1.5rem;
      font-weight: 600;
      transition: all 0.3s ease;
    }
    
    .btn-secondary:hover {
      background-color: #5a6268;
      transform: translateY(-2px);
      box-shadow: 0 4px 10px rgba(108, 117, 125, 0.2);
    }
    
    .btn-outline-light {
      border-radius: 8px;
      font-weight: 500;
      transition: all 0.3s ease;
    }
    
    /* Required field indicator */
    .required-field::after {
      content: "*";
      color: #dc3545;
      margin-left: 4px;
    }
    
    /* Form text helper */
    .form-text {
      font-size: 0.8rem;
    }
    
    /* Info card */
    .info-card {
      background-color: var(--light-orange);
      border-radius: 10px;
      padding: 1rem;
      margin-bottom: 1rem;
    }
    
    .info-card-icon {
      color: var(--accent-orange);
      font-size: 1.25rem;
      margin-right: 0.5rem;
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
    
    /* Responsive */
    @media (max-width: 992px) {
      .content {
        margin-left: 0;
      }
    }
    
    @media (max-width: 768px) {
      .page-header {
        padding: 1.5rem;
      }
    }
  </style>
</head>
<body>

  <!-- Sidebar -->
  <?php include 'sidebar.php'; ?>

  <!-- Content -->
  <div class="content">
    <!-- Navbar - Already updated to match dashboard.php -->
    <nav class="navbar navbar-expand-lg navbar-dark">
      <div class="container-fluid">
        <span class="navbar-brand">
          <i class="fas fa-edit me-2"></i>
          Edit Kategori
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
        <div class="d-flex align-items-center">
          <div>
            <h1><i class="fas fa-edit me-2"></i>Edit Kategori</h1>
            <p class="lead mb-0">Perbarui informasi kategori dalam sistem kasir bengkel.</p>
          </div>
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
      endif;
      ?>

      <div class="row">
        <div class="col-lg-8 mx-auto">
          <div class="form-card">
            <div class="form-section-header">
              <h5 class="form-section-title">
                <i class="fas fa-tag me-2"></i>
                Edit Kategori
              </h5>
            </div>
            
            <?php if ($products_count > 0): ?>
            <div class="info-card mb-4">
              <div class="d-flex align-items-center">
                <i class="fas fa-info-circle info-card-icon"></i>
                <div>
                  <strong>Informasi:</strong> Kategori ini digunakan oleh <?= $products_count ?> produk. Perubahan akan mempengaruhi semua produk terkait.
                </div>
              </div>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
              <div class="mb-3">
                <label for="nama_kategori" class="form-label required-field">Nama Kategori</label>
                <input type="text" class="form-control" id="nama_kategori" name="nama_kategori" value="<?= htmlspecialchars($nama_kategori) ?>" required>
                <div class="form-text">
                  <i class="fas fa-info-circle me-1"></i>
                  Gunakan nama kategori yang jelas dan spesifik.
                </div>
              </div>

              <div class="mt-4 d-flex justify-content-between">
                <a href="kategori.php" class="btn btn-secondary">
                  <i class="fas fa-arrow-left me-1"></i>
                  Kembali
                </a>
                <button type="submit" class="btn btn-primary">
                  <i class="fas fa-save me-1"></i>
                  Simpan Perubahan
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
  
  <script>
    // Auto close alerts after 5 seconds - dengan penanganan yang lebih baik
    var alertElement = document.getElementById('autoCloseAlert');
    if (alertElement) {
      // Gunakan Bootstrap alert object untuk handling lebih halus
      var bsAlert = new bootstrap.Alert(alertElement);
      
      // Set timeout untuk menutup alert setelah 5 detik
      setTimeout(function() {
        // Verifikasi alert masih ada sebelum mencoba menutupnya
        if (document.body.contains(alertElement)) {
          bsAlert.close();
        }
      }, 5000);
    }
  </script>
</body>
</html>