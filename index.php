<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Selamat Datang di BMS Bengkel</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --primary-blue: #1565C0;
      --secondary-blue: #42A5F5;
      --light-blue: #E3F2FD;
      --accent-blue: #0D47A1;
      --admin-color: #7E57C2; /* Purple for admin */
      --karyawan-color: #26A69A; /* Teal for karyawan */
      --manajer-color: #EF6C00; /* Orange for manajer */
      --white: #ffffff;
      --light-gray: #f8f9fa;
      --text-dark: #2C3E50;
    }
    
    body {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background-color: var(--light-gray);
      font-family: 'Poppins', 'Arial', sans-serif;
      /* Background image setup */
      background-image: url('images/samping.jpg'); /* Sesuaikan path sesuai struktur folder */
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      position: relative;
    }
    
    body::before {
      content: '';
      position: absolute;
      top: 0;
      right: 0;
      bottom: 0;
      left: 0;
      background: linear-gradient(135deg, rgba(21, 101, 192, 0.8), rgba(13, 71, 161, 0.9));
      z-index: -1;
    }
    
    .welcome-container {
      width: 100%;
      max-width: 1000px;
      padding: 20px;
      z-index: 1;
    }
    
    .welcome-header {
      text-align: center;
      margin-bottom: 3rem;
      color: var(--white);
    }
    
    .welcome-header h1 {
      font-size: 3rem;
      font-weight: 700;
      margin-bottom: 1rem;
      text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
    }
    
    .welcome-header p {
      font-size: 1.2rem;
      opacity: 0.9;
      max-width: 800px;
      margin: 0 auto;
      text-shadow: 0 1px 5px rgba(0, 0, 0, 0.2);
    }
    
    .login-options {
      display: flex;
      justify-content: center;
      flex-wrap: wrap;
      gap: 30px;
    }
    
    .login-card {
      background-color: rgba(255, 255, 255, 0.95);
      border-radius: 20px;
      overflow: hidden;
      width: 100%;
      max-width: 300px;
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
      transition: all 0.3s ease;
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .login-card:hover {
      transform: translateY(-10px);
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    }
    
    .card-header {
      padding: 2rem;
      text-align: center;
      position: relative;
    }
    
    .admin-header {
      background: linear-gradient(135deg, var(--admin-color), #5E35B1);
    }
    
    .karyawan-header {
      background: linear-gradient(135deg, var(--karyawan-color), #00897B);
    }
    
    .manajer-header {
      background: linear-gradient(135deg, var(--manajer-color), #D84315);
    }
    
    .card-icon {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 100px;
      height: 100px;
      border-radius: 50%;
      background-color: rgba(255, 255, 255, 0.15);
      margin: 0 auto 1.5rem;
      font-size: 3rem;
      color: var(--white);
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }
    
    .card-header h3 {
      color: var(--white);
      font-size: 1.5rem;
      font-weight: 700;
      margin: 0;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }
    
    .card-body {
      padding: 2rem;
      text-align: center;
    }
    
    .card-body p {
      color: var(--text-dark);
      margin-bottom: 1.5rem;
      font-size: 0.95rem;
      line-height: 1.6;
    }
    
    .btn-login {
      padding: 0.8rem 1.5rem;
      border: none;
      border-radius: 50px;
      font-weight: 600;
      font-size: 1rem;
      transition: all 0.3s ease;
      width: 100%;
      color: var(--white);
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
    }
    
    .btn-admin {
      background: linear-gradient(to right, var(--admin-color), #5E35B1);
    }
    
    .btn-admin:hover {
      background: linear-gradient(to right, #5E35B1, var(--admin-color));
      box-shadow: 0 6px 15px rgba(126, 87, 194, 0.3);
    }
    
    .btn-karyawan {
      background: linear-gradient(to right, var(--karyawan-color), #00897B);
    }
    
    .btn-karyawan:hover {
      background: linear-gradient(to right, #00897B, var(--karyawan-color));
      box-shadow: 0 6px 15px rgba(38, 166, 154, 0.3);
    }
    
    .btn-manajer {
      background: linear-gradient(to right, var(--manajer-color), #D84315);
    }
    
    .btn-manajer:hover {
      background: linear-gradient(to right, #D84315, var(--manajer-color));
      box-shadow: 0 6px 15px rgba(239, 108, 0, 0.3);
    }
    
    .welcome-footer {
      text-align: center;
      color: var(--white);
      margin-top: 3rem;
      padding-top: 2rem;
      border-top: 1px solid rgba(255, 255, 255, 0.1);
      font-size: 0.9rem;
    }
    
    /* Responsive adjustments */
    @media (max-width: 992px) {
      .login-options {
        flex-direction: column;
        align-items: center;
      }
      
      .login-card {
        max-width: 450px;
      }
    }
    
    @media (max-width: 576px) {
      .welcome-header h1 {
        font-size: 2.2rem;
      }
      
      .welcome-header p {
        font-size: 1rem;
      }
    }
  </style>
</head>
<body>
  <div class="welcome-container">
    <!-- Header -->
    <div class="welcome-header">
      <h1>Selamat Datang di Bengkel BMS</h1>
      <p>Silakan pilih peran Anda untuk masuk ke sistem manajemen bengkel</p>
    </div>
    
    <!-- Login Options -->
    <div class="login-options">
      <!-- Admin Login Card -->
      <div class="login-card">
        <div class="card-header admin-header">
          <div class="card-icon">
            <i class="fas fa-user-shield"></i>
          </div>
          <h3>Admin</h3>
        </div>
        <div class="card-body">
          <p>Akses ke pengaturan sistem, manajemen pengguna, laporan, dan analitik bengkel</p>
          <a href="Admin/login.php" class="btn btn-login btn-admin">
            <i class="fas fa-sign-in-alt me-2"></i>Login Admin
          </a>
        </div>
      </div>
      
      <!-- Karyawan Login Card -->
      <div class="login-card">
        <div class="card-header karyawan-header">
          <div class="card-icon">
            <i class="fas fa-user-tie"></i>
          </div>
          <h3>Karyawan</h3>
        </div>
        <div class="card-body">
          <p>Akses ke transaksi harian, pengelolaan servis, dan layanan pelanggan</p>
          <a href="karyawan/login.php" class="btn btn-login btn-karyawan">
            <i class="fas fa-sign-in-alt me-2"></i>Login Karyawan
          </a>
        </div>
      </div>
      
      <!-- Manajer Login Card -->
      <div class="login-card">
        <div class="card-header manajer-header">
          <div class="card-icon">
            <i class="fas fa-user-cog"></i>
          </div>
          <h3>Manajer</h3>
        </div>
        <div class="card-body">
          <p>Akses ke laporan keuangan, monitoring kinerja karyawan, dan data bengkel</p>
          <a href="Manajer/login.php" class="btn btn-login btn-manajer">
            <i class="fas fa-sign-in-alt me-2"></i>Login Manajer
          </a>
        </div>
      </div>
    </div>
    
    <!-- Footer -->
    <div class="welcome-footer">
      <p>&copy; <?= date('Y') ?> Bengkel BMS | Sistem Informasi Bisnis Bengkel | All Rights Reserved</p>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>