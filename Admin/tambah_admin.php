<?php
session_start();

// Cek apakah sudah login dan sebagai admin
if (!isset($_SESSION['admin']['logged_in']) || $_SESSION['admin']['logged_in'] !== true) {
  header("Location: ../login.php");
  exit();
}

include '../config.php'; // Pastikan path ke config.php benar

// Inisialisasi variabel
$username = $password = $nama = "";
$username_err = $password_err = $nama_err = "";
$success_message = "";

// Proses form ketika di-submit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validasi username
    if (empty(trim($_POST["username"]))) {
        $username_err = "Silakan masukkan username.";
    } else {
        // Cek apakah username sudah ada di tabel admin
        $sql = "SELECT id_admin FROM admin WHERE username = ?";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $param_username);
            
            $param_username = trim($_POST["username"]);
            
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    $username_err = "Username ini sudah digunakan.";
                } else {
                    $username = trim($_POST["username"]);
                }
            } else {
                $_SESSION['message'] = "Terjadi kesalahan. Silakan coba lagi.";
                $_SESSION['alert_type'] = "danger";
            }

            mysqli_stmt_close($stmt);
        }
        
        // Cek juga di tabel karyawan
        $sql = "SELECT id_karyawan FROM karyawan WHERE username = ?";
        
        if (empty($username_err) && $stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $param_username);
            
            $param_username = trim($_POST["username"]);
            
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    $username_err = "Username ini sudah digunakan oleh karyawan.";
                }
            }

            mysqli_stmt_close($stmt);
        }
        
        // Cek juga di tabel manajer
        $sql = "SELECT id_manajer FROM manajer WHERE username = ?";
        
        if (empty($username_err) && $stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $param_username);
            
            $param_username = trim($_POST["username"]);
            
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    $username_err = "Username ini sudah digunakan oleh manajer.";
                }
            }

            mysqli_stmt_close($stmt);
        }
    }
    
    // Validasi password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Silakan masukkan password.";     
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password harus memiliki minimal 6 karakter.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validasi nama
    if (empty(trim($_POST["nama"]))) {
        $nama_err = "Silakan masukkan nama.";     
    } else {
        $nama = trim($_POST["nama"]);
    }
    
    // Cek apakah tidak ada error validasi sebelum insert ke database
    if (empty($username_err) && empty($password_err) && empty($nama_err)) {
        
        // Buat query untuk memasukkan admin baru
        $sql = "INSERT INTO admin (username, password, nama, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())";
         
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "sss", $param_username, $param_password, $param_nama);
            
            $param_username = $username;
            $param_password = $password; // Simpan password apa adanya (plaintext)
            $param_nama = $nama;
            
            if (mysqli_stmt_execute($stmt)) {
                // Set session message for success
                $_SESSION['message'] = "Admin baru berhasil ditambahkan!";
                $_SESSION['alert_type'] = "success";
                
                // Redirect ke halaman karyawan (pengelola user)
                header("location: pengguna.php");
                exit();
            } else {
                $_SESSION['message'] = "Terjadi kesalahan. Silakan coba lagi.";
                $_SESSION['alert_type'] = "danger";
            }

            mysqli_stmt_close($stmt);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tambah Admin - BMS Bengkel</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #0052cc;
      --primary-dark: #003580;
      --primary-light: #e6f0ff;
      --accent: #00b8d9;
      --text-dark: #172b4d;
      --text-light: #5e6c84;
      --white: #ffffff;
      --border: #dfe1e6;
      --danger: #e53e3e;
      --danger-light: #fff5f5;
      --success: #10b981;
      --warning: #f59e0b;
      --info: #3b82f6;
    }
    
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #f0f7ff;
      color: var(--text-dark);
      min-height: 100vh;
    }
    
    /* Layout */
    .main-container {
      display: flex;
    }
    
    .sidebar {
      width: 250px;
      background-color: var(--white);
      height: 100vh;
      position: fixed;
      box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
      z-index: 1000;
      transition: all 0.3s ease;
    }
    
    .content-wrapper {
      flex: 1;
      margin-left: 250px;
      transition: all 0.3s ease;
      min-height: 100vh;
    }
    
    /* Topbar */
    .topbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 15px 30px;
      background-color: var(--white);
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    
    .hamburger-menu {
      font-size: 22px;
      color: var(--text-dark);
      cursor: pointer;
      display: none;
    }
    
    .page-title {
      font-weight: 600;
      font-size: 20px;
      color: var(--text-dark);
    }
    
    /* Main Content */
    .main-content {
      padding: 30px;
    }
    
    /* Card */
    .card {
      background-color: var(--white);
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
      margin-bottom: 25px;
      border: none;
    }
    
    .card-header {
      background-color: var(--white);
      border-bottom: 1px solid var(--border);
      padding: 20px 25px;
    }
    
    .card-title {
      font-weight: 600;
      font-size: 18px;
      color: var(--text-dark);
      margin-bottom: 0;
    }
    
    .card-body {
      padding: 25px;
    }
    
    /* Form */
    .form-group {
      margin-bottom: 20px;
    }
    
    .form-label {
      font-weight: 500;
      color: var(--text-dark);
      margin-bottom: 8px;
    }
    
    .form-control {
      border: 1px solid var(--border);
      border-radius: 6px;
      padding: 12px 15px;
      font-size: 14px;
      transition: all 0.3s ease;
    }
    
    .form-control:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(0, 82, 204, 0.15);
    }
    
    .invalid-feedback {
      color: var(--danger);
      font-size: 13px;
      margin-top: 5px;
    }
    
    /* Buttons */
    .btn {
      font-weight: 500;
      padding: 10px 20px;
      border-radius: 6px;
      transition: all 0.3s ease;
    }
    
    .btn-primary {
      background-color: var(--primary);
      border-color: var(--primary);
    }
    
    .btn-primary:hover {
      background-color: var(--primary-dark);
      border-color: var(--primary-dark);
      transform: translateY(-2px);
      box-shadow: 0 4px 10px rgba(0, 82, 204, 0.2);
    }
    
    .btn-secondary {
      background-color: var(--white);
      border-color: var(--border);
      color: var(--text-dark);
    }
    
    .btn-secondary:hover {
      background-color: var(--primary-light);
      color: var(--primary);
      transform: translateY(-2px);
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
    }
    
    /* Responsive */
    @media (max-width: 992px) {
      .content-wrapper {
        margin-left: 0;
      }
      
      .sidebar {
        transform: translateX(-100%);
      }
      
      .sidebar.active {
        transform: translateX(0);
      }
      
      .hamburger-menu {
        display: block;
      }
    }
    
    @media (max-width: 768px) {
      .main-content {
        padding: 20px;
      }
    }
  </style>
</head>
<body>

<div class="main-container">
  <!-- Sidebar (Include from sidebar.php) -->
  <?php include 'sidebar.php'; ?>
  
  <!-- Content Wrapper -->
  <div class="content-wrapper">
    <!-- Topbar -->
    <div class="topbar">
      <div class="d-flex align-items-center">
        <span class="hamburger-menu" id="hamburger">
          <i class="fas fa-bars"></i>
        </span>
        <h1 class="page-title ms-3">Tambah Admin</h1>
      </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
      <div class="row">
        <div class="col-lg-8 col-md-10 mx-auto">
          <div class="card">
            <div class="card-header d-flex align-items-center">
              <i class="fas fa-user-shield me-2 text-danger"></i>
              <h5 class="card-title">Form Tambah Admin</h5>
            </div>
            <div class="card-body">
              <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-group">
                  <label for="username" class="form-label">Username</label>
                  <input type="text" name="username" id="username" class="form-control <?= !empty($username_err) ? 'is-invalid' : ''; ?>" value="<?= $username; ?>" required>
                  <div class="invalid-feedback"><?= $username_err; ?></div>
                </div>
                
                <div class="form-group">
                  <label for="password" class="form-label">Password</label>
                  <input type="password" name="password" id="password" class="form-control <?= !empty($password_err) ? 'is-invalid' : ''; ?>" required>
                  <div class="invalid-feedback"><?= $password_err; ?></div>
                </div>
                
                <div class="form-group">
                  <label for="nama" class="form-label">Nama Lengkap</label>
                  <input type="text" name="nama" id="nama" class="form-control <?= !empty($nama_err) ? 'is-invalid' : ''; ?>" value="<?= $nama; ?>" required>
                  <div class="invalid-feedback"><?= $nama_err; ?></div>
                </div>
                
                <div class="form-group text-end mt-4">
                  <a href="pengguna.php" class="btn btn-secondary me-2">
                    <i class="fas fa-arrow-left me-1"></i> Kembali
                  </a>
                  <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i> Simpan
                  </button>
                </div>
              </form>
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
  // Toggle sidebar on mobile
  document.getElementById('hamburger').addEventListener('click', function() {
    document.querySelector('.sidebar').classList.toggle('active');
  });
</script>

</body>
</html>