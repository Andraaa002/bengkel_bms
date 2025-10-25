<?php
session_start();

// Cek apakah user sudah login dan memiliki role 'karyawan' dengan struktur session baru
if (!isset($_SESSION['karyawan']['logged_in']) || $_SESSION['karyawan']['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$nama_karyawan = $_SESSION['karyawan']['nama'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard Karyawan | Kasir Bengkel</title>

  <!-- Bootstrap & Icon -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />

  <style>
    :root {
      --primary-green: #26A69A;
      --secondary-green: #00897B;
      --light-green: #E0F2F1;
      --accent-green: #00695C;
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
      background: linear-gradient(135deg, var(--primary-green), var(--accent-green));
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
    
    .user-area {
      display: flex;
      align-items: center;
      gap: 20px;
    }
    
    .user-info {
      color: var(--white);
      font-weight: 500;
      font-size: 0.95rem;
    }
    
    .logout-btn {
      background-color: rgba(255, 255, 255, 0.2);
      color: var(--white);
      padding: 8px 18px;
      border: none;
      border-radius: 6px;
      text-decoration: none;
      font-size: 0.85rem;
      font-weight: 500;
      transition: all 0.2s ease;
    }
    
    .logout-btn:hover {
      background-color: var(--white);
      color: var(--primary-green);
      transform: translateY(-1px);
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    
    .welcome-header {
      background: linear-gradient(135deg, var(--primary-green), var(--secondary-green));
      padding: 30px;
      border-radius: 15px;
      margin-bottom: 25px;
      box-shadow: 0 6px 18px rgba(38, 166, 154, 0.15);
      color: var(--white);
    }
    
    .welcome-header h3 {
      font-weight: 600;
      margin: 0;
    }
    
    .card {
      border: none;
      border-radius: 15px;
      padding: 25px;
      box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
      background-color: var(--white);
      transition: all 0.3s ease;
      height: 100%;
    }
    
    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 12px 20px rgba(0, 0, 0, 0.12);
    }
    
    .card-icon {
      background-color: var(--light-green);
      color: var(--primary-green);
      width: 65px;
      height: 65px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.6rem;
      margin-bottom: 20px;
      transition: all 0.3s ease;
    }
    
    .card:hover .card-icon {
      background-color: var(--primary-green);
      color: var(--white);
      transform: scale(1.05);
      box-shadow: 0 5px 15px rgba(38, 166, 154, 0.25);
    }
    
    .card h5 {
      color: var(--text-dark);
      font-weight: 600;
      margin-bottom: 12px;
      font-size: 1.2rem;
    }
    
    .card p {
      color: var(--text-light);
      margin-bottom: 20px;
      font-size: 0.95rem;
    }
    
    .btn-primary-outline {
      color: var(--primary-green);
      background-color: var(--white);
      border: 1.5px solid var(--primary-green);
      border-radius: 8px;
      padding: 8px 20px;
      font-weight: 500;
      transition: all 0.2s ease;
    }
    
    .btn-primary-outline:hover {
      background-color: var(--primary-green);
      color: var(--white);
      transform: translateY(-2px);
      box-shadow: 0 4px 10px rgba(38, 166, 154, 0.15);
    }
    
    .dashboard-section {
      padding: 30px;
    }
    
    /* Responsive styles */
    @media (max-width: 992px) {
      .content {
        margin-left: 0;
      }
    }
  </style>
</head>
<body>

  <!-- Sidebar -->
  <?php include 'sidebar.php'; ?>

  <!-- Content Area -->
  <div class="content">
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
      <div class="container-fluid">
        <span class="navbar-brand">
          <i class="fas fa-tachometer-alt me-2"></i>
          Dashboard Karyawan
        </span>
        <div class="d-flex align-items-center">
          <span class="text-white me-3">
            <i class="fas fa-user-circle me-1"></i>
            <?= htmlspecialchars($nama_karyawan) ?>
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

    <!-- Main Dashboard Content -->
    <div class="container-fluid py-4">
      <div class="welcome-header">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h3>Selamat Datang, <?= htmlspecialchars($nama_karyawan) ?>!</h3>
            <p class="mb-0">Akses panel administrasi bengkel Anda di sini.</p>
          </div>
          <span class="badge bg-success p-3">
            <i class="fas fa-user-tie me-1"></i> Karyawan
          </span>
        </div>
      </div>
      
      <div class="row g-4 justify-content-center">
        <div class="col-md-5">
          <div class="card text-center">
            <div class="card-icon mx-auto">
              <i class="fas fa-cash-register"></i>
            </div>
            <h5>Input Transaksi</h5>
            <p>Masukkan transaksi penjualan barang atau jasa kepada pelanggan.</p>
            <a href="input_transaksi.php" class="btn btn-primary-outline mt-2">Masuk <i class="fas fa-arrow-right ms-1"></i></a>
          </div>
        </div>

        <div class="col-md-5">
          <div class="card text-center">
            <div class="card-icon mx-auto">
              <i class="fas fa-receipt"></i>
            </div>
            <h5>Daftar Transaksi</h5>
            <p>Lihat daftar transaksi dan detail histori pembelian pelanggan.</p>
            <a href="data_transaksi.php" class="btn btn-primary-outline mt-2">Lihat <i class="fas fa-arrow-right ms-1"></i></a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap Script -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>