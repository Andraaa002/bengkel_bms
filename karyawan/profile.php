<?php
session_start();
include '../config.php';

// Cek apakah user sudah login dan role-nya karyawan dengan struktur session baru
if (!isset($_SESSION['karyawan']['logged_in']) || $_SESSION['karyawan']['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Ambil user ID dari session dengan struktur baru
$id_karyawan = $_SESSION['karyawan']['id'];
$query_karyawan = "SELECT * FROM karyawan WHERE id_karyawan = $id_karyawan";
$result_karyawan = mysqli_query($conn, $query_karyawan);
$karyawan = mysqli_fetch_assoc($result_karyawan);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $foto = isset($karyawan['foto']) ? $karyawan['foto'] : null; // default foto lama

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
        $query_update = "UPDATE karyawan SET username = '$username', nama = '$nama', password = '$password_hash', foto = '$foto' WHERE id_karyawan = $id_karyawan";
    } else {
        $query_update = "UPDATE karyawan SET username = '$username', nama = '$nama', foto = '$foto' WHERE id_karyawan = $id_karyawan";
    }

    if (mysqli_query($conn, $query_update)) {
        // Update session dengan struktur baru
        $_SESSION['karyawan']['nama'] = $nama;
        $_SESSION['karyawan']['username'] = $username;
        if (isset($foto)) {
            $_SESSION['karyawan']['photo'] = $foto;
        }
        
        $_SESSION['message'] = "Profil berhasil diperbarui.";
        $_SESSION['alert_type'] = "success";
        header("Location: profile.php");
        exit();
    } else {
        $_SESSION['message'] = "Gagal memperbarui profil: " . mysqli_error($conn);
        $_SESSION['alert_type'] = "danger";
    }
}

$nama_karyawan = $_SESSION['karyawan']['nama'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Profil Karyawan | Kasir Bengkel</title>

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
    
    /* Profile specific styles */
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
      border-color: var(--primary-green);
      box-shadow: 0 0 0 0.25rem rgba(38, 166, 154, 0.25);
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
    
    /* Responsive media queries */
    @media (max-width: 992px) {
      .content {
        margin-left: 0;
      }
    }
    
    /* Info card with border */
    .info-card {
      border-left: 4px solid;
      padding: 15px;
    }
    
    .info-card.security {
      border-color: var(--primary-green);
    }
    
    .info-card.status {
      border-color: #FF9800;
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
          <i class="fas fa-user-circle me-2"></i>
          Profil Karyawan
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

    <!-- Main Content -->
    <div class="container-fluid py-4">
      <!-- Welcome Header -->
      <div class="welcome-header">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h3>Profil Karyawan</h3>
            <p class="mb-0">Kelola informasi akun dan foto profil Anda</p>
          </div>
          <span class="badge bg-success p-3">
            <i class="fas fa-user-tie me-1"></i> Karyawan
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
      <div class="card mb-4">
        <h5 class="mb-4 border-bottom pb-3">
          <i class="fas fa-user-edit me-2 text-primary"></i>
          Informasi Profil
        </h5>
        
        <form method="POST" enctype="multipart/form-data">
          <div class="row">
            <div class="col-md-4">
              <div class="profile-image-container">
                <?php if (!empty($karyawan['foto']) && file_exists($karyawan['foto'])): ?>
                  <img src="<?= htmlspecialchars($karyawan['foto']) ?>" alt="Foto Profil" class="profile-image mb-3">
                <?php else: ?>
                  <div class="profile-image d-flex align-items-center justify-content-center bg-light mb-3">
                    <i class="fas fa-user fa-4x text-muted"></i>
                  </div>
                <?php endif; ?>
                
                <div class="profile-upload-btn mt-2">
                  <button type="button" class="btn btn-primary-outline">
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
                  <i class="fas fa-user me-2 text-primary"></i> Username
                </label>
                <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($karyawan['username']) ?>" required>
              </div>
              
              <div class="mb-3">
                <label for="nama" class="form-label">
                  <i class="fas fa-id-card me-2 text-primary"></i> Nama Lengkap
                </label>
                <input type="text" class="form-control" id="nama" name="nama" value="<?= htmlspecialchars($karyawan['nama']) ?>" required>
              </div>
              
              <div class="mb-4">
                <label for="password" class="form-label">
                  <i class="fas fa-lock me-2 text-primary"></i> Password Baru
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
                <a href="index.php" class="btn btn-outline-secondary me-2">
                  <i class="fas fa-arrow-left me-1"></i> Kembali
                </a>
                <button type="submit" class="btn btn-primary-outline">
                  <i class="fas fa-save me-1"></i> Simpan Perubahan
                </button>
              </div>
            </div>
          </div>
        </form>
      </div>
      
      <!-- Info Cards -->
      <div class="row g-4">
        <div class="col-md-6">
          <div class="card">
            <div class="card-icon mx-auto">
              <i class="fas fa-shield-alt"></i>
            </div>
            <h5 class="text-center">Keamanan Akun</h5>
            <p class="text-center">Gunakan password yang kuat dan ganti secara berkala untuk meningkatkan keamanan akun Anda.</p>
          </div>
        </div>
        
        <div class="col-md-6">
          <div class="card">
            <div class="card-icon mx-auto">
              <i class="fas fa-user-shield"></i>
            </div>
            <h5 class="text-center">Status Akun</h5>
            <p class="text-center">Anda login sebagai <strong>Karyawan</strong> dengan akses ke fungsi sistem tertentu.</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap Script -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Display selected filename
      document.getElementById('foto').addEventListener('change', function() {
        const fileName = this.files[0]?.name;
        if (fileName) {
          const parent = this.closest('.profile-upload-btn');
          if (parent.querySelector('button')) {
            parent.querySelector('button').innerHTML = '<i class="fas fa-check me-2"></i> ' + fileName.substring(0, 15) + (fileName.length > 15 ? '...' : '');
            parent.querySelector('button').classList.remove('btn-primary-outline');
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
        const alertElement = document.querySelector(".alert");
        if (alertElement) {
          const bsAlert = new bootstrap.Alert(alertElement);
          bsAlert.close();
        }
      }, 5000);
    });
  </script>
</body>
</html>