<?php
session_start();

// Cek apakah sudah login dan sebagai manajer (menggunakan struktur session seperti admin)
if (!isset($_SESSION['manajer']['logged_in']) || $_SESSION['manajer']['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

include '../config.php'; // Pastikan path ke config.php benar

// Query untuk menghitung jumlah produk
$query_produk = "SELECT COUNT(*) AS jumlah_produk FROM produk";
$result_produk = mysqli_query($conn, $query_produk);
$data_produk = mysqli_fetch_assoc($result_produk);

// Query untuk menghitung jumlah kategori
$query_kategori = "SELECT COUNT(DISTINCT kategori_id) AS jumlah_kategori FROM produk";
$result_kategori = mysqli_query($conn, $query_kategori);
$data_kategori = mysqli_fetch_assoc($result_kategori);

// Query untuk mendapatkan 5 produk terlaris
$query_terlaris = "SELECT p.nama AS nama_produk, COALESCE(SUM(td.jumlah), 0) AS total_terjual 
                   FROM produk p
                   LEFT JOIN transaksi_detail td ON p.id = td.produk_id
                   GROUP BY p.id
                   ORDER BY total_terjual DESC
                   LIMIT 5";
$result_terlaris = mysqli_query($conn, $query_terlaris);

// Query untuk mendapatkan produk per kategori
$query_per_kategori = "SELECT k.nama_kategori, COUNT(p.id) AS jumlah_produk
                       FROM kategori k
                       LEFT JOIN produk p ON k.id = p.kategori_id
                       GROUP BY k.id
                       ORDER BY jumlah_produk DESC";
$result_per_kategori = mysqli_query($conn, $query_per_kategori);

// Kumpulkan data untuk chart kategori
$kategori_labels = [];
$kategori_values = [];
while ($row = mysqli_fetch_assoc($result_per_kategori)) {
    $kategori_labels[] = $row['nama_kategori'];
    $kategori_values[] = $row['jumlah_produk'];
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Manajer - BMS Bengkel</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    :root {
      /* Manajer orange theme - matched from index.php */
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
    
    /* Stats cards */
    .stats-card {
      display: flex;
      align-items: center;
      height: 100%;
    }
    
    .stats-icon {
      font-size: 3rem;
      margin-right: 1rem;
    }
    
    .stats-icon.orange {
      color: var(--primary-orange);
    }
    
    .stats-icon.blue {
      color: #42A5F5;
    }
    
    .stats-icon.green {
      color: #4CAF50;
    }
    
    .stats-content h3 {
      font-size: 1.75rem;
      font-weight: 700;
      margin-bottom: 0.25rem;
    }
    
    .stats-content p {
      color: var(--text-dark);
      margin-bottom: 0;
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
    
    /* Chart styling */
    .chart-options {
      display: flex;
      gap: 10px;
    }
    
    .chart-option {
      background-color: var(--light-orange);
      color: var(--primary-orange);
      border: none;
      border-radius: 8px;
      padding: 8px 12px;
      font-size: 13px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.3s ease;
    }
    
    .chart-option.active {
      background-color: var(--primary-orange);
      color: var(--white);
    }
    
    .chart-container {
      height: 300px;
      position: relative;
      margin-top: 15px;
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
    
    /* Tips card */
    .tips-card {
      background-color: var(--light-orange);
      border-left: 4px solid var(--secondary-orange);
      border-radius: 10px;
      padding: 1.25rem;
    }
    
    .tips-icon {
      color: var(--primary-orange);
      font-size: 1.5rem;
      margin-right: 1rem;
    }
    
    /* Table styling */
    .table {
      border-collapse: separate;
      border-spacing: 0;
    }
    
    .table th {
      background-color: var(--light-orange);
      color: var(--primary-orange);
      font-weight: 600;
      border: none;
    }
    
    .table td {
      vertical-align: middle;
      border-color: #f0f0f0;
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
      
      .card-header-actions {
        flex-direction: column;
        align-items: flex-start;
      }
      
      .chart-options {
        margin-top: 1rem;
        align-self: flex-start;
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
          <i class="fas fa-tachometer-alt me-2"></i>
          Dashboard Manajer
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
            <h1><i class="fas fa-tachometer-alt me-2"></i>Dashboard Manajer</h1>
            <p class="lead mb-0">Informasi dan statistik produk dan kategori bengkel Anda.</p>
          </div>
          <span class="badge bg-light text-warning p-3 fs-6">
            <i class="fas fa-user-cog me-1"></i> Manajer
          </span>
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
      endif;
      ?>

      <!-- Statistics Cards Row -->
      <div class="row g-4 mb-4">
        <!-- Statistik Produk -->
        <div class="col-md-6">
          <div class="data-card mb-4" style="border-left: 4px solid #EF6C00;">
            <div class="stats-card">
              <div class="stats-icon orange">
                <i class="fas fa-box"></i>
              </div>
              <div class="stats-content">
                <h3 id="productValue"><?= $data_produk['jumlah_produk'] ?></h3>
                <p>Total Produk</p>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Statistik Kategori -->
        <div class="col-md-6">
          <div class="data-card mb-4" style="border-left: 4px solid #F59E0B;">
            <div class="stats-card">
              <div class="stats-icon orange">
                <i class="fas fa-tags"></i>
              </div>
              <div class="stats-content">
                <h3 id="categoryValue"><?= $data_kategori['jumlah_kategori'] ?></h3>
                <p>Total Kategori</p>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Charts and Tables Row -->
      <div class="row g-4 mb-4">
        <!-- Produk per Kategori Chart -->
        <div class="col-md-6">
          <div class="data-card">
            <div class="card-header-actions">
              <h5 class="card-title">
                <i class="fas fa-chart-pie me-2"></i>
                Produk per Kategori
              </h5>
            </div>
            
            <div class="chart-container">
              <canvas id="categoryChart"></canvas>
            </div>
          </div>
        </div>
        
        <!-- Produk Terlaris Table -->
        <div class="col-md-6">
          <div class="data-card">
            <div class="card-header-actions">
              <h5 class="card-title">
                <i class="fas fa-trophy me-2"></i>
                5 Produk Terlaris
              </h5>
            </div>
            
            <div class="table-responsive">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Nama Produk</th>
                    <th class="text-end">Total Terjual</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $no = 1;
                  while ($row = mysqli_fetch_assoc($result_terlaris)) {
                  ?>
                  <tr>
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($row['nama_produk']) ?></td>
                    <td class="text-end"><?= $row['total_terjual'] ?> unit</td>
                  </tr>
                  <?php } ?>
                  
                  <?php if (mysqli_num_rows($result_terlaris) == 0): ?>
                  <tr>
                    <td colspan="3" class="text-center py-3">Belum ada data penjualan produk</td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Tips Section -->
      <div class="row">
        <div class="col-md-12">
          <div class="data-card mb-4">
            <div class="tips-card">
              <div class="d-flex align-items-start">
                <i class="fas fa-lightbulb tips-icon"></i>
                <div>
                  <h5 class="mb-2">Tips Pengelolaan Produk</h5>
                  <ul class="mb-0">
                    <li>Pantau stok produk secara berkala untuk menghindari kehabisan stok</li>
                    <li>Periksa produk terlaris untuk menentukan produk mana yang harus dijaga ketersediaannya</li>
                    <li>Kategorikan produk dengan baik untuk memudahkan pencarian dan pengelolaan</li>
                    <li>Tambahkan produk baru sesuai dengan kebutuhan pasar dan tren terkini</li>
                  </ul>
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
  
  <script>
    // Animate counter function
    function animateCounter(element, start, end, duration) {
      let startTimestamp = null;
      const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        const value = Math.floor(progress * (end - start) + start);
        
        element.textContent = value;
        
        if (progress < 1) {
          window.requestAnimationFrame(step);
        } else {
          // Final value
          element.textContent = end;
        }
      };
      window.requestAnimationFrame(step);
    }
    
    // Initialize charts and animate counters
    document.addEventListener('DOMContentLoaded', function() {
      // Animate counters
      const productValue = document.getElementById('productValue');
      const categoryValue = document.getElementById('categoryValue');
      
      animateCounter(productValue, 0, <?= $data_produk['jumlah_produk'] ?>, 1500);
      animateCounter(categoryValue, 0, <?= $data_kategori['jumlah_kategori'] ?>, 1500);
      
      // Initialize kategori chart
      const categoryCtx = document.getElementById('categoryChart').getContext('2d');
      
      const categoryLabels = <?= json_encode($kategori_labels) ?>;
      const categoryData = <?= json_encode($kategori_values) ?>;
      
      // Updated chart colors to match orange theme
      const bgColors = [
        'rgba(239, 108, 0, 0.7)',
        'rgba(245, 158, 11, 0.7)',
        'rgba(216, 67, 21, 0.7)',
        'rgba(255, 167, 38, 0.7)',
        'rgba(255, 138, 101, 0.7)',
        'rgba(251, 140, 0, 0.7)',
        'rgba(255, 112, 67, 0.7)',
        'rgba(255, 87, 34, 0.7)',
        'rgba(244, 81, 30, 0.7)',
        'rgba(230, 81, 0, 0.7)'
      ];
      
      const borderColors = [
        'rgba(239, 108, 0, 1)',
        'rgba(245, 158, 11, 1)',
        'rgba(216, 67, 21, 1)',
        'rgba(255, 167, 38, 1)',
        'rgba(255, 138, 101, 1)',
        'rgba(251, 140, 0, 1)',
        'rgba(255, 112, 67, 1)',
        'rgba(255, 87, 34, 1)',
        'rgba(244, 81, 30, 1)',
        'rgba(230, 81, 0, 1)'
      ];
      
      new Chart(categoryCtx, {
        type: 'doughnut',
        data: {
          labels: categoryLabels,
          datasets: [{
            data: categoryData,
            backgroundColor: bgColors,
            borderColor: borderColors,
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'right',
              labels: {
                boxWidth: 15,
                padding: 15
              }
            },
            tooltip: {
              callbacks: {
                label: function(context) {
                  const label = context.label || '';
                  const value = context.raw || 0;
                  return `${label}: ${value} produk`;
                }
              }
            }
          },
          cutout: '60%'
        }
      });
      
      // Auto close alerts after 5 seconds
      setTimeout(function() {
        document.querySelectorAll(".alert").forEach(function(alert) {
          let closeButton = alert.querySelector(".btn-close");
          if (closeButton) {
            closeButton.click();
          }
        });
      }, 5000);
    });
  </script>

</body>
</html>