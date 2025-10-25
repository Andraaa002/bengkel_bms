<?php
session_start();

// Cek apakah sudah login dan sebagai admin
if (!isset($_SESSION['admin']['logged_in']) || $_SESSION['admin']['logged_in'] !== true) {
  header("Location: ../login.php");
  exit();
}

include '../config.php'; // Pastikan path ke config.php benar

// Inisialisasi variabel
$id = '';
$username = $nama = $role = '';
$error_message = '';
$success_message = '';

// Cek jika ada id di parameter
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Ambil data karyawan berdasarkan id
    $query = "SELECT * FROM karyawan WHERE id = '$id'";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        $username = $user['username'];
        $nama = $user['nama'];
        $role = $user['role'];
    } else {
        header("Location: pengguna.php?status=not_found");
        exit();
    }
} else {
    header("Location: pengguna.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ambil data dari form
    $username = $_POST['username'];
    $nama = $_POST['nama'];
    $role = $_POST['role'];
    $password = $_POST['password'];

    // Validasi input
    if (empty($username) || empty($nama) || empty($role)) {
        $error_message = 'Semua kolom harus diisi kecuali password!';
    } else {
        // Cek apakah username sudah ada (selain username saat ini)
        $check_query = "SELECT * FROM karyawan WHERE username = '$username' AND id != '$id'";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) > 0) {
            $error_message = 'Username sudah digunakan, silakan pilih username lain.';
        } else {
            // Jika password kosong, jangan update password
            if (empty($password)) {
                $query = "UPDATE karyawan SET username = '$username', role = '$role', nama = '$nama' WHERE id = '$id'";
            } else {
                // Hash password jika ada perubahan password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $query = "UPDATE karyawan SET username = '$username', password = '$hashed_password', role = '$role', nama = '$nama' WHERE id = '$id'";
            }
            
            if (mysqli_query($conn, $query)) {
                $success_message = 'Data karyawan berhasil diperbarui!';
                
                // Perbarui data yang ditampilkan
                $query = "SELECT * FROM karyawan WHERE id = '$id'";
                $result = mysqli_query($conn, $query);
                $user = mysqli_fetch_assoc($result);
                $username = $user['username'];
                $nama = $user['nama'];
                $role = $user['role'];
            } else {
                $error_message = "Gagal memperbarui data karyawan: " . mysqli_error($conn);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Karyawan - Kasir Bengkel</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --primary-blue: #1565C0;
      --secondary-blue: #42A5F5;
      --light-blue: #E3F2FD;
      --accent-blue: #0D47A1;
      --white: #ffffff;
      --light-gray: #f8f9fa;
      --text-dark: #2C3E50;
    }
    
    body {
      background-color: var(--light-gray);
      font-family: 'Poppins', 'Arial', sans-serif;
      color: var(--text-dark);
    }
    
    /* Navbar Styling */
    .navbar {
      background: linear-gradient(135deg, var(--primary-blue), var(--accent-blue));
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      border: none;
      padding: 0.8rem 1.5rem;
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
      padding: 20px;
      background-color: var(--light-gray);
      min-height: 100vh;
    }
    
    /* Page header section */
    .page-header {
      background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
      border-radius: 15px;
      padding: 2rem;
      color: var(--white);
      margin-bottom: 2rem;
      box-shadow: 0 6px 18px rgba(0, 123, 255, 0.15);
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
    }
    
    .card-header-actions {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-bottom: 1rem;
      margin-bottom: 1.5rem;
      border-bottom: 2px solid var(--light-blue);
    }
    
    .card-title {
      font-size: 1.25rem;
      font-weight: 600;
      color: var(--primary-blue);
      margin-bottom: 0;
    }
    
    /* Button styling */
    .btn-primary {
      background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
      border: none;
      border-radius: 8px;
      padding: 0.75rem 1.5rem;
      font-weight: 600;
      transition: all 0.3s ease;
      box-shadow: 0 4px 10px rgba(21, 101, 192, 0.2);
    }
    
    .btn-primary:hover {
      background: linear-gradient(135deg, var(--accent-blue), var(--primary-blue));
      transform: translateY(-2px);
      box-shadow: 0 6px 15px rgba(21, 101, 192, 0.3);
    }
    
    .btn-outline-primary {
      color: var(--primary-blue);
      border-color: var(--primary-blue);
      border-radius: 8px;
      font-weight: 500;
      transition: all 0.3s ease;
    }
    
    .btn-outline-primary:hover {
      background-color: var(--primary-blue);
      color: var(--white);
      transform: translateY(-2px);
      box-shadow: 0 4px 10px rgba(21, 101, 192, 0.2);
    }
    
    .btn-secondary {
      border-radius: 8px;
      transition: all 0.3s ease;
    }
    
    .btn-secondary:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 10px rgba(108, 117, 125, 0.2);
    }
    
    /* Form styling */
    .form-label {
      font-weight: 500;
      color: var(--text-dark);
      margin-bottom: 0.5rem;
    }
    
    .form-control, .form-select {
      border-radius: 8px;
      border: 1px solid #e0e6ed;
      padding: 0.75rem 1rem;
      transition: all 0.3s ease;
    }
    
    .form-control:focus, .form-select:focus {
      border-color: var(--secondary-blue);
      box-shadow: 0 0 0 0.25rem rgba(66, 165, 245, 0.15);
    }
    
    .form-text {
      color: #6c757d;
      font-size: 0.875rem;
    }
    
    /* Form section spacing */
    .form-section {
      margin-bottom: 2rem;
      padding-bottom: 2rem;
      border-bottom: 1px solid #e9ecef;
    }
    
    .form-section:last-child {
      border-bottom: none;
      margin-bottom: 0;
      padding-bottom: 0;
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
    
    /* Input group styling */
    .input-group-text {
      background-color: var(--light-blue);
      color: var(--primary-blue);
      border: 1px solid #e0e6ed;
      border-radius: 8px 0 0 8px;
    }
    
    /* Required field indicator */
    .required-field::after {
      content: " *";
      color: #dc3545;
    }
    
    /* Password strength indicator */
    .password-strength {
      margin-top: 5px;
      height: 5px;
      border-radius: 3px;
      transition: all 0.3s;
    }

    .strength-weak {
      background-color: #dc3545;
      width: 25%;
    }

    .strength-medium {
      background-color: #ffc107;
      width: 50%;
    }

    .strength-strong {
      background-color: #28a745;
      width: 100%;
    }

    .password-info {
      font-size: 0.8rem;
      margin-top: 5px;
      color: #6c757d;
    }
    
    /* Responsive media queries */
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

  <!-- Content -->
  <div class="content">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
      <div class="container-fluid">
        <span class="navbar-brand">
          <i class="fas fa-user-edit me-2"></i>
          Edit Karyawan
        </span>
        <div class="d-flex align-items-center">
          <span class="text-white me-3">
            <i class="fas fa-user-circle me-1"></i>
            <?= htmlspecialchars($_SESSION['nama']) ?>
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
            <h1><i class="fas fa-user-edit me-2"></i>Edit Karyawan</h1>
            <p class="lead mb-0">Perbarui informasi untuk karyawan: <strong><?= htmlspecialchars($nama) ?></strong></p>
          </div>
          <a href="pengguna.php" class="btn btn-light">
            <i class="fas fa-arrow-left me-2"></i>
            Kembali ke Daftar Karyawan
          </a>
        </div>
      </div>

      <!-- Alert Messages -->
      <?php if ($error_message): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?= $error_message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
      <?php endif; ?>

      <?php if ($success_message): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?= $success_message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
      <?php endif; ?>

      <!-- Edit Employee Form Card -->
      <div class="row">
        <div class="col-lg-12">
          <div class="data-card mb-4">
            <div class="card-header-actions">
              <h5 class="card-title">
                <i class="fas fa-clipboard-list me-2"></i>
                Formulir Edit Karyawan
              </h5>
            </div>
            
            <form method="POST" action="edit_karyawan.php?id=<?= $id ?>">
              <!-- Account Information -->
              <div class="form-section">
                <h6 class="text-primary mb-3"><i class="fas fa-user-circle me-2"></i>Informasi Akun</h6>
                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label for="username" class="form-label required-field">Username</label>
                    <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($username) ?>" required>
                    <div class="form-text">Username harus unik dan tidak boleh mengandung spasi.</div>
                  </div>
                  <div class="col-md-6 mb-3">
                    <label for="password" class="form-label">Password (Opsional)</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Kosongkan jika tidak ingin mengganti password" onkeyup="checkPasswordStrength()">
                    <div class="password-strength" id="passwordStrength"></div>
                    <div class="password-info" id="passwordInfo">Biarkan kosong jika tidak ingin mengubah password</div>
                  </div>
                </div>
              </div>

              <!-- Personal Information -->
              <div class="form-section">
                <h6 class="text-primary mb-3"><i class="fas fa-id-card me-2"></i>Informasi Pribadi</h6>
                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label for="nama" class="form-label required-field">Nama Karyawan</label>
                    <input type="text" class="form-control" id="nama" name="nama" value="<?= htmlspecialchars($nama) ?>" required>
                    <div class="form-text">Masukkan nama lengkap karyawan.</div>
                  </div>
                  <div class="col-md-6 mb-3">
                    <label for="role" class="form-label required-field">Role</label>
                    <select class="form-select" id="role" name="role" required>
                      <option value="">Pilih role</option>
                      <option value="karyawan" <?= $role == 'karyawan' ? 'selected' : '' ?>>Karyawan</option>
                      <option value="admin" <?= $role == 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                    <div class="form-text">Role Admin memiliki akses penuh ke semua fitur sistem.</div>
                  </div>
                </div>
              </div>
              
              <!-- Submit Buttons -->
              <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="pengguna.php" class="btn btn-secondary">
                  <i class="fas fa-times me-1"></i> Batal
                </a>
                <button type="submit" class="btn btn-primary">
                  <i class="fas fa-save me-1"></i> Simpan Perubahan
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
    // Password strength checker
    function checkPasswordStrength() {
      const password = document.getElementById('password').value;
      const strengthBar = document.getElementById('passwordStrength');
      const info = document.getElementById('passwordInfo');
      
      // Remove existing classes
      strengthBar.className = 'password-strength';
      
      if (password.length === 0) {
        strengthBar.style.width = '0';
        info.textContent = 'Biarkan kosong jika tidak ingin mengubah password';
        return;
      }
      
      // Check password strength
      let strength = 0;
      if (password.length >= 8) strength += 1;
      if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength += 1;
      if (password.match(/\d/)) strength += 1;
      if (password.match(/[^a-zA-Z\d]/)) strength += 1;
      
      // Update UI based on strength
      if (strength <= 2) {
        strengthBar.classList.add('strength-weak');
        info.textContent = 'Password lemah - tambahkan karakter, angka, dan simbol';
      } else if (strength === 3) {
        strengthBar.classList.add('strength-medium');
        info.textContent = 'Password cukup kuat';
      } else {
        strengthBar.classList.add('strength-strong');
        info.textContent = 'Password kuat';
      }
    }
    
    $(document).ready(function() {
      // Auto close alerts after 5 seconds
      setTimeout(function() {
        $(".alert").alert('close');
      }, 5000);
    });
  </script>
</body>
</html>