<?php
session_start();

// Cek apakah sudah login dan sebagai admin
if (!isset($_SESSION['admin']['logged_in']) || $_SESSION['admin']['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
} 

include '../config.php'; // Database connection

// Query untuk menghitung jumlah karyawan
$query_karyawan = "SELECT COUNT(*) AS jumlah_karyawan FROM karyawan WHERE role = 'karyawan'";
$result_karyawan = mysqli_query($conn, $query_karyawan);
$data_karyawan = mysqli_fetch_assoc($result_karyawan);

// Query untuk menghitung total pendapatan
$query_pendapatan = "SELECT SUM(total) AS total_pendapatan FROM transaksi";
$result_pendapatan = mysqli_query($conn, $query_pendapatan);
$data_pendapatan = mysqli_fetch_assoc($result_pendapatan);

// Query untuk mendapatkan 7 transaksi terakhir (terbaru dulu)
$query_grafik = "SELECT DATE(tanggal) AS tanggal, SUM(total) AS pendapatan 
                 FROM transaksi 
                 GROUP BY DATE(tanggal) 
                 ORDER BY tanggal DESC 
                 LIMIT 7";
$result_grafik = mysqli_query($conn, $query_grafik);

// Masukkan ke array dan balik urutan jadi ASC (lama ke baru)
$grafik_data = [];
while ($row = mysqli_fetch_assoc($result_grafik)) {
    $grafik_data[] = $row;
}
$grafik_data = array_reverse($grafik_data);

// Pisahkan tanggal dan pendapatan
$grafik_labels = array_column($grafik_data, 'tanggal');
$grafik_values = array_column($grafik_data, 'pendapatan');
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Admin - BMS Bengkel</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
    
    /* Content area */
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
      border-bottom: 2px solid var(--light-purple);
    }
    
    .card-title {
      font-size: 1.25rem;
      font-weight: 600;
      color: var(--primary-purple);
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
    
    .stats-icon.purple {
      color: var(--primary-purple);
    }
    
    .stats-icon.green {
      color: #4CAF50;
    }
    
    .stats-icon.orange {
      color: #F59E0B;
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
      background-color: var(--light-purple);
      color: var(--primary-purple);
      border: none;
      border-radius: 8px;
      padding: 8px 12px;
      font-size: 13px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.3s ease;
    }
    
    .chart-option.active {
      background-color: var(--primary-purple);
      color: var(--white);
    }
    
    .chart-container {
      height: 300px;
      position: relative;
      margin-top: 15px;
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
    
    .btn-outline-light {
      border-radius: 8px;
      font-weight: 500;
      transition: all 0.3s ease;
    }
    
    /* Tips card */
    .tips-card {
      background-color: var(--light-purple);
      border-left: 4px solid var(--secondary-purple);
      border-radius: 10px;
      padding: 1.25rem;
    }
    
    .tips-icon {
      color: var(--primary-purple);
      font-size: 1.5rem;
      margin-right: 1rem;
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
          Dashboard Admin
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
            <h1><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h1>
            <p class="lead mb-0">Ringkasan dan statistik sistem kasir bengkel Anda.</p>
          </div>
          <span class="badge bg-light text-primary p-3 fs-6">
            <i class="fas fa-user-shield me-1"></i> Admin
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
        <!-- Statistik Karyawan -->
        <div class="col-md-6">
          <div class="data-card mb-4" style="border-left: 4px solid #4CAF50;">
            <div class="stats-card">
              <div class="stats-icon green">
                <i class="fas fa-users"></i>
              </div>
              <div class="stats-content">
                <h3 id="employeeValue"><?= $data_karyawan['jumlah_karyawan'] ?></h3>
                <p>Total Karyawan</p>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Statistik Pendapatan -->
        <div class="col-md-6">
          <div class="data-card mb-4" style="border-left: 4px solid #F59E0B;">
            <div class="stats-card">
              <div class="stats-icon orange">
                <i class="fas fa-money-bill-wave"></i>
              </div>
              <div class="stats-content">
                <h3 id="incomeValue">Rp <?= number_format($data_pendapatan['total_pendapatan'] ?? 0, 0, ',', '.') ?></h3>
                <p>Total Penjualan Produk Sparepart / Servis</p>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Chart Section -->
      <div class="row g-4 mb-4">
        <div class="col-md-12">
          <div class="data-card">
            <div class="card-header-actions">
              <h5 class="card-title">
                <i class="fas fa-chart-line me-2"></i>
                Grafik Pendapatan (7 Hari Terakhir)
              </h5>
              <div class="chart-options">
                <button class="chart-option active" data-type="bar">
                  <i class="fas fa-chart-bar me-1"></i>
                  Bar
                </button>
                <button class="chart-option" data-type="line">
                  <i class="fas fa-chart-line me-1"></i>
                  Line
                </button>
                <button class="chart-option" data-type="doughnut">
                  <i class="fas fa-chart-pie me-1"></i>
                  Doughnut
                </button>
              </div>
            </div>
            
            <div class="chart-container">
              <canvas id="revenueChart"></canvas>
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
                  <h5 class="mb-2">Tips Dashboard</h5>
                  <ul class="mb-0">
                    <li>Pantau statistik pendapatan untuk mengevaluasi perkembangan bisnis bengkel Anda</li>
                    <li>Periksa stok produk secara berkala untuk memastikan ketersediaan barang</li>
                    <li>Buat transaksi baru dengan mudah melalui tombol "Transaksi Baru" di menu</li>
                    <li>Akses menu laporan untuk analisis lebih detail tentang penjualan dan layanan bengkel</li>
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
      if (!element) return; // Check if element exists
      
      let startTimestamp = null;
      const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        const value = Math.floor(progress * (end - start) + start);
        
        // Format nilai khusus untuk pendapatan
        if (element.textContent.includes('Rp')) {
          element.textContent = 'Rp ' + value.toLocaleString('id-ID');
        } else {
          element.textContent = value;
        }
        
        if (progress < 1) {
          window.requestAnimationFrame(step);
        } else {
          // Final value
          if (element.textContent.includes('Rp')) {
            element.textContent = 'Rp ' + end.toLocaleString('id-ID');
          } else {
            element.textContent = end;
          }
        }
      };
      window.requestAnimationFrame(step);
    }
    
    // Initialize charts and animate counters
    document.addEventListener('DOMContentLoaded', function() {
      // Animate counters
      const employeeValue = document.getElementById('employeeValue');
      const incomeValue = document.getElementById('incomeValue');
      
      if (employeeValue) animateCounter(employeeValue, 0, <?= $data_karyawan['jumlah_karyawan'] ?>, 1500);
      
      const totalIncome = <?= empty($data_pendapatan['total_pendapatan']) ? 0 : $data_pendapatan['total_pendapatan'] ?>;
      
      if (incomeValue && incomeValue.textContent.includes('Rp')) {
        incomeValue.textContent = 'Rp 0';
        animateCounter(incomeValue, 0, totalIncome, 1500);
      }
      
      // Initialize chart
      initChart('bar');
      
      // Chart type buttons
      const chartButtons = document.querySelectorAll('.chart-option');
      chartButtons.forEach(button => {
        button.addEventListener('click', function() {
          chartButtons.forEach(btn => btn.classList.remove('active'));
          this.classList.add('active');
          
          const chartType = this.getAttribute('data-type');
          initChart(chartType);
        });
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
    
    // Initialize chart function
    function initChart(type) {
      // Chart data
      const labels = <?= json_encode($grafik_labels) ?>;
      const values = <?= json_encode($grafik_values) ?>;
      
      // Format dates
      const formattedLabels = labels.map(date => {
        const dateObj = new Date(date);
        return dateObj.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
      });
      
      // Format rupiah function
      const formatRupiah = (value) => {
        return 'Rp ' + parseInt(value).toLocaleString('id-ID');
      };
      
      // Chart configuration
      const ctx = document.getElementById('revenueChart').getContext('2d');
      
      // Destroy previous chart if exists
      if (window.myChart) {
        window.myChart.destroy();
      }
      
      // Colors - using purple theme
      const primaryColor = '#7E57C2';
      const secondaryColor = '#5E35B1';
      const lightColor = 'rgba(126, 87, 194, 0.2)';
      
      // Chart configuration
      let config = {
        type: type,
        data: {
          labels: formattedLabels,
          datasets: [{
            label: 'Pendapatan',
            data: values,
            backgroundColor: lightColor,
            borderColor: secondaryColor,
            borderWidth: 2
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: type === 'doughnut',
              position: 'top',
            },
            tooltip: {
              callbacks: {
                label: function(context) {
                  return formatRupiah(context.raw);
                }
              }
            }
          }
        }
      };
      
      // Specific configurations per chart type
      if (type === 'line') {
        // Create gradient for line chart
        const gradientFill = ctx.createLinearGradient(0, 0, 0, 400);
        gradientFill.addColorStop(0, 'rgba(126, 87, 194, 0.4)');
        gradientFill.addColorStop(1, 'rgba(126, 87, 194, 0.05)');
        
        config.data.datasets[0].backgroundColor = gradientFill;
        config.data.datasets[0].fill = true;
        config.data.datasets[0].tension = 0.4;
        
        config.options.scales = {
          x: {
            grid: {
              display: false
            }
          },
          y: {
            beginAtZero: true,
            ticks: {
              callback: function(value) {
                return formatRupiah(value);
              }
            }
          }
        };
      } 
      else if (type === 'bar') {
        // For bar chart
        config.data.datasets[0].backgroundColor = values.map(() => 'rgba(126, 87, 194, 0.7)');
        config.data.datasets[0].borderColor = values.map(() => '#5E35B1');
        config.data.datasets[0].borderRadius = 5;
        
        config.options.scales = {
          x: {
            grid: {
              display: false
            }
          },
          y: {
            beginAtZero: true,
            ticks: {
              callback: function(value) {
                return formatRupiah(value);
              }
            }
          }
        };
      }
      else if (type === 'doughnut') {
        // For doughnut chart
        config.data.datasets[0].backgroundColor = [
          'rgba(126, 87, 194, 0.7)',
          'rgba(76, 175, 80, 0.7)',
          'rgba(245, 158, 11, 0.7)',
          'rgba(239, 68, 68, 0.7)',
          'rgba(66, 165, 245, 0.7)',
          'rgba(0, 188, 212, 0.7)',
          'rgba(255, 152, 0, 0.7)'
        ];
        
        config.data.datasets[0].borderColor = '#ffffff';
        config.data.datasets[0].borderWidth = 2;
        config.options.cutout = '65%';
      }
      
      // Create chart
      window.myChart = new Chart(ctx, config);
    }
  </script>

</body>
</html>