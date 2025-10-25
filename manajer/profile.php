<?php
session_start();
include '../config.php';

// Cek apakah user sudah login dan role-nya manajer
if (!isset($_SESSION['manajer']['logged_in']) || $_SESSION['manajer']['logged_in'] !== true) {
  header("Location: ../login.php");
  exit();
}

// Ambil user ID dari session
if (!isset($_SESSION['manajer']['id'])) {
    $nama_session = mysqli_real_escape_string($conn, $_SESSION['manajer']['nama']);
    $query_get_id = mysqli_query($conn, "SELECT id_manajer FROM manajer WHERE nama = '$nama_session' LIMIT 1");
    if ($query_get_id && mysqli_num_rows($query_get_id) > 0) {
        $data_id = mysqli_fetch_assoc($query_get_id);
        $_SESSION['manajer']['id'] = $data_id['id_manajer'];
    } else {
        die("Gagal mendapatkan data pengguna.");
    }
}

$id_manajer = $_SESSION['manajer']['id'];
$query_manajer = "SELECT * FROM manajer WHERE id_manajer = $id_manajer";
$result_manajer = mysqli_query($conn, $query_manajer);
$manajer = mysqli_fetch_assoc($result_manajer);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $foto = isset($manajer['foto']) ? $manajer['foto'] : null; // default foto lama

    // Proses upload foto baru jika ada
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        $target_dir = "../uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_name = basename($_FILES["foto"]["name"]);
        $target_file = $target_dir . time() . "_" . $file_name;
        $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        $check = getimagesize($_FILES["foto"]["tmp_name"]);
        if ($check !== false) {
            if (move_uploaded_file($_FILES["foto"]["tmp_name"], $target_file)) {
                $foto = $target_file;
            } else {
                $_SESSION['message'] = "Gagal mengunggah foto.";
                $_SESSION['alert_type'] = "danger";
            }
        } else {
            $_SESSION['message'] = "File yang diunggah bukan gambar.";
            $_SESSION['alert_type'] = "danger";
        }
    }

    // Update database
    if (!empty($password)) {
        $password_hash = hash('sha256', $password); // Sesuai dengan struktur SQL
        $query_update = "UPDATE manajer SET username = '$username', nama = '$nama', password = '$password_hash', foto = '$foto' WHERE id_manajer = $id_manajer";
    } else {
        $query_update = "UPDATE manajer SET username = '$username', nama = '$nama', foto = '$foto' WHERE id_manajer = $id_manajer";
    }

    if (mysqli_query($conn, $query_update)) {
        $_SESSION['manajer']['nama'] = $nama;
        $_SESSION['manajer']['photo'] = $foto;
        $_SESSION['message'] = "Profil berhasil diperbarui.";
        $_SESSION['alert_type'] = "success";
        header("Location: profile.php");
        exit();
    } else {
        $_SESSION['message'] = "Gagal memperbarui profil: " . mysqli_error($conn);
        $_SESSION['alert_type'] = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profil Manajer - BMS Bengkel</title>
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
      --border-color: #e1e7ef;
    }
    
    body {
      background-color: var(--light-gray);
      font-family: 'Poppins', 'Arial', sans-serif;
      color: var(--text-dark);
      margin: 0;
      padding: 0;
    }
    
    /* Content area */
    .content {
      margin-left: 280px;
      transition: margin-left 0.3s ease;
      min-height: 100vh;
    }
    
    /* Navbar Styling - Sesuai dengan dashboard */
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
    
    /* Page header section - Sesuai dengan dashboard */
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
    
    .btn-outline-primary {
      color: var(--primary-orange);
      border-color: var(--primary-orange);
      border-radius: 8px;
      font-weight: 500;
      transition: all 0.3s ease;
    }
    
    .btn-outline-primary:hover {
      background-color: var(--primary-orange);
      color: var(--white);
      transform: translateY(-2px);
      box-shadow: 0 4px 10px rgba(239, 108, 0, 0.2);
    }
    
    .btn-outline-light {
      border-radius: 8px;
      font-weight: 500;
      transition: all 0.3s ease;
    }
    
    .btn-outline-secondary {
      border-radius: 8px;
      font-weight: 500;
    }
    
    /* Profile image styling */
    .profile-image-container {
      text-align: center;
      margin-bottom: 20px;
    }

    .profile-image {
      width: 150px;
      height: 150px;
      border-radius: 50%;
      object-fit: cover;
      box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
    }

    .profile-upload-btn {
      position: relative;
      overflow: hidden;
      display: inline-block;
      margin-top: 15px;
    }

    .profile-upload-btn input[type=file] {
      position: absolute;
      top: 0;
      right: 0;
      min-width: 100%;
      min-height: 100%;
      font-size: 100px;
      text-align: right;
      filter: alpha(opacity=0);
      opacity: 0;
      outline: none;
      cursor: pointer;
      display: block;
    }
    
    /* Form styling */
    .form-label {
      color: var(--text-dark);
      font-weight: 600;
      margin-bottom: 8px;
    }

    .form-control {
      border-radius: 8px;
      border: 1px solid #d1e3f0;
      padding: 12px 15px;
      transition: all 0.3s;
      background-color: var(--white);
    }

    .form-control:focus {
      border-color: var(--primary-orange);
      box-shadow: 0 0 0 0.25rem rgba(239, 108, 0, 0.25);
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
      background-color: #FFF3E0;
      color: #D84315;
    }
    
    .alert-danger {
      background-color: #FFEBEE;
      color: #B71C1C;
    }
    
    /* Responsive media queries */
    @media (max-width: 992px) {
      .content {
        margin-left: 0;
      }
    }
    
    /* User no-image avatar */
    .user-avatar {
      width: 40px;
      height: 40px;
      background-color: var(--primary-orange);
      color: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
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
    
    .stats-icon.orange-dark {
      color: var(--primary-orange);
    }
    
    .stats-icon.orange-light {
      color: var(--secondary-orange);
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
    
    /* Container fluid padding */
    .container-fluid {
      padding-left: 15px;
      padding-right: 15px;
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
          <i class="fas fa-user-circle me-2"></i>
          Profil Manajer
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
            <h1><i class="fas fa-user-edit me-2"></i>Profil Manajer</h1>
            <p class="lead mb-0">Kelola informasi akun dan foto profil Anda</p>
          </div>
          <span class="badge bg-light text-warning p-3 fs-6">
            <i class="fas fa-user-tie me-1"></i> Manajer
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

      <!-- Profile Card -->
      <div class="row g-4 mb-4">
        <div class="col-12">
          <div class="data-card">
            <div class="card-header-actions">
              <h5 class="card-title">
                <i class="fas fa-user-edit me-2"></i>
                Informasi Profil
              </h5>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
              <div class="row">
                <div class="col-md-4">
                  <div class="profile-image-container">
                    <?php if (!empty($manajer['foto']) && file_exists($manajer['foto'])): ?>
                      <img src="<?= htmlspecialchars($manajer['foto']) ?>" alt="Foto Profil" class="profile-image mb-3">
                    <?php else: ?>
                      <div class="profile-image d-flex align-items-center justify-content-center bg-light mb-3">
                        <i class="fas fa-user fa-4x text-muted"></i>
                      </div>
                    <?php endif; ?>
                    
                    <div class="profile-upload-btn mt-2">
                      <button type="button" class="btn btn-outline-primary">
                        <i class="fas fa-camera me-2"></i> Pilih Foto
                      </button>
                      <input type="file" id="foto" name="foto" accept="image/*">
                    </div>
                    <div class="mt-2 text-muted small">
                      Format: JPG, PNG (Maks: 2MB)
                    </div>
                  </div>
                </div>
                
                <div class="col-md-8">
                  <div class="mb-3">
                    <label for="username" class="form-label">
                      <i class="fas fa-user me-2 text-primary" style="color: var(--primary-orange) !important;"></i> Username
                    </label>
                    <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($manajer['username']) ?>" required>
                  </div>
                  
                  <div class="mb-3">
                    <label for="nama" class="form-label">
                      <i class="fas fa-id-card me-2 text-primary" style="color: var(--primary-orange) !important;"></i> Nama Lengkap
                    </label>
                    <input type="text" class="form-control" id="nama" name="nama" value="<?= htmlspecialchars($manajer['nama']) ?>" required>
                  </div>
                  
                  <div class="mb-4">
                    <label for="password" class="form-label">
                      <i class="fas fa-lock me-2 text-primary" style="color: var(--primary-orange) !important;"></i> Password Baru
                    </label>
                    <div class="input-group">
                      <input type="password" class="form-control" id="password" name="password">
                      <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                        <i class="fas fa-eye"></i>
                      </button>
                    </div>
                    <small class="form-text text-muted">
                      <i class="fas fa-info-circle me-1"></i> Kosongkan jika tidak ingin mengganti password
                    </small>
                  </div>
                  
                  <div class="text-end">
                    <a href="dashboard.php" class="btn btn-outline-secondary me-2">
                      <i class="fas fa-arrow-left me-1"></i> Kembali
                    </a>
                    <button type="submit" class="btn btn-primary">
                      <i class="fas fa-save me-1"></i> Simpan Perubahan
                    </button>
                  </div>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
      
      <!-- Info Cards -->
      <div class="row g-4 mb-4">
        <div class="col-md-6">
          <div class="data-card" style="border-left: 4px solid #F59E0B;">
            <div class="stats-card">
              <div class="stats-icon orange-light">
                <i class="fas fa-shield-alt"></i>
              </div>
              <div class="stats-content">
                <h6 class="mb-1">Keamanan Akun</h6>
                <p class="mb-0">Pastikan untuk menggunakan password yang kuat dan mengganti password Anda secara berkala.</p>
              </div>
            </div>
          </div>
        </div>
        
        <div class="col-md-6">
          <div class="data-card" style="border-left: 4px solid #EF6C00;">
            <div class="stats-card">
              <div class="stats-icon orange-dark">
                <i class="fas fa-user-tie"></i>
              </div>
              <div class="stats-content">
                <h6 class="mb-1">Status Akun</h6>
                <p class="mb-0">Anda login sebagai <strong>Manajer</strong> dengan akses ke pengelolaan produk, kategori, dan laporan.</p>
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
    document.addEventListener('DOMContentLoaded', function() {
      // Display selected filename
      document.getElementById('foto').addEventListener('change', function() {
        const fileName = this.files[0]?.name;
        if (fileName) {
          const parent = this.closest('.profile-upload-btn');
          if (parent.querySelector('button')) {
            parent.querySelector('button').innerHTML = '<i class="fas fa-check me-2"></i> ' + fileName.substring(0, 15) + (fileName.length > 15 ? '...' : '');
            parent.querySelector('button').classList.remove('btn-outline-primary');
            parent.querySelector('button').classList.add('btn-success');
          }
        }
      });
      
      // Toggle password visibility
      if (document.getElementById('togglePassword')) {
        document.getElementById('togglePassword').addEventListener('click', function() {
          const passwordInput = document.getElementById('password');
          const icon = this.querySelector('i');
          
          if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
          } else {
            passwordInput.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
          }
        });
      }
      
      // Auto close alerts after 5 seconds
      setTimeout(function() {
        $(".alert").alert('close');
      }, 5000);
    });
  </script>
</body>
</html>