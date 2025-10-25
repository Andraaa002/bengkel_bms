<?php
session_start();

// Redirect if already logged in as manajer
if (isset($_SESSION['manajer']['logged_in']) && $_SESSION['manajer']['logged_in'] === true) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include '../config.php'; // Ensure this file establishes $conn connection

    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    // Query untuk memeriksa manajer dari tabel manajer
    $sql = "SELECT * FROM manajer WHERE username = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && mysqli_num_rows($result) === 1) {
        $user = mysqli_fetch_assoc($result);
        
        // Verifikasi password (menyesuaikan dengan sistem penyimpanan password Anda)
        if ($password === $user['password']) { // Ubah sesuai metode penyimpanan password
            // Set session dengan namespace 'manajer'
            $_SESSION['manajer'] = [
                'id' => $user['id_manajer'],
                'username' => $user['username'],
                'role' => 'manajer',
                'nama' => $user['nama'],
                'photo' => isset($user['foto']) ? $user['foto'] : null,
                'logged_in' => true
            ];

            // Update last login time jika kolom ada
            $update_login = "UPDATE manajer SET updated_at = NOW() WHERE id_manajer = ?";
            $stmt_update = mysqli_prepare($conn, $update_login);
            mysqli_stmt_bind_param($stmt_update, "i", $user['id_manajer']);
            mysqli_stmt_execute($stmt_update);

            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Username atau password salah.";
        }
    } else {
        $error = "Username atau password salah, atau Anda bukan manajer.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Kasir Bengkel</title>
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
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background-color: var(--light-gray);
      font-family: 'Poppins', 'Arial', sans-serif;
      /* Background image setup */
      background-image: url('../images/samping.jpg'); /* Sesuaikan path sesuai struktur folder */
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
    
    .login-container {
      width: 100%;
      max-width: 450px;
      padding: 20px;
      z-index: 1;
    }
    
    .login-logo {
      text-align: center;
      margin-bottom: 2rem;
    }
    
    .login-logo img {
      max-width: 100px;
      height: auto;
    }
    
    .login-logo h3 {
      color: var(--white);
      font-weight: 700;
      margin-top: 1rem;
      font-size: 2rem;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }
    
    .login-card {
      background-color: rgba(255, 255, 255, 0.95);
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .login-header {
      background: linear-gradient(135deg, var(--primary-blue), var(--accent-blue));
      color: var(--white);
      padding: 2rem;
      text-align: center;
      position: relative;
    }
    
    .login-header::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      height: 20px;
      background: linear-gradient(to right bottom, var(--accent-blue) 49%, transparent 50%),
                  linear-gradient(to left bottom, var(--accent-blue) 49%, transparent 50%);
      background-size: 40px 20px;
      background-repeat: repeat-x;
    }
    
    .login-header h4 {
      margin: 0;
      font-weight: 700;
      font-size: 1.5rem;
      letter-spacing: 0.5px;
    }
    
    .login-body {
      padding: 2.5rem;
    }
    
    .alert {
      border-radius: 12px;
      border: none;
      padding: 1rem;
      margin-bottom: 1.5rem;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
      font-weight: 500;
    }
    
    .alert-danger {
      background-color: #FFEBEE;
      color: #B71C1C;
    }
    
    .form-group {
      margin-bottom: 1.5rem;
    }
    
    .form-label {
      font-weight: 600;
      color: var(--text-dark);
      margin-bottom: 0.5rem;
    }
    
    .form-control {
      border-radius: 12px;
      border: 1px solid #e0e6ed;
      padding: 0.8rem 1rem;
      transition: all 0.3s ease;
      box-shadow: none;
      background-color: var(--light-gray);
    }
    
    .form-control:focus {
      border-color: var(--secondary-blue);
      box-shadow: 0 0 0 0.2rem rgba(66, 165, 245, 0.25);
      background-color: var(--white);
    }
    
    .input-group-text {
      border-radius: 0 12px 12px 0;
      background-color: var(--light-blue);
      border: 1px solid #e0e6ed;
      border-left: none;
      color: var(--primary-blue);
      cursor: pointer;
      transition: all 0.3s ease;
    }
    
    .input-group-text:hover {
      background-color: var(--primary-blue);
      color: var(--white);
    }
    
    .btn-login {
      background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
      border: none;
      border-radius: 12px;
      padding: 0.8rem 1rem;
      font-weight: 600;
      transition: all 0.3s ease;
      box-shadow: 0 4px 10px rgba(21, 101, 192, 0.2);
      letter-spacing: 0.5px;
      text-transform: uppercase;
      margin-top: 0.5rem;
    }
    
    .btn-login:hover {
      background: linear-gradient(135deg, var(--accent-blue), var(--primary-blue));
      transform: translateY(-2px);
      box-shadow: 0 6px 15px rgba(21, 101, 192, 0.3);
    }
    
    .btn-back {
      background: linear-gradient(135deg, #dc3545, #c82333);
      border: none;
      border-radius: 12px;
      padding: 0.8rem 1rem;
      font-weight: 600;
      transition: all 0.3s ease;
      box-shadow: 0 4px 10px rgba(220, 53, 69, 0.2);
      letter-spacing: 0.5px;
      text-transform: uppercase;
      margin-top: 1rem;
      color: #ffffff;
    }
    
    .btn-back:hover {
      background: linear-gradient(135deg, #c82333, #bd2130);
      transform: translateY(-2px);
      box-shadow: 0 6px 15px rgba(220, 53, 69, 0.3);
      color: #ffffff;
    }
    
    .buttons-container {
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
    }
    
    .divider {
      display: flex;
      align-items: center;
      text-align: center;
      margin: 1.5rem 0;
      color: #94a3b8;
    }
    
    .divider::before,
    .divider::after {
      content: '';
      flex: 1;
      border-bottom: 1px solid #e2e8f0;
    }
    
    .divider::before {
      margin-right: 1rem;
    }
    
    .divider::after {
      margin-left: 1rem;
    }
    
    .link-forgot {
      color: var(--primary-blue);
      font-weight: 500;
      text-decoration: none;
      transition: all 0.3s ease;
      display: block;
      text-align: center;
      margin-top: 1rem;
    }
    
    .link-forgot:hover {
      color: var(--accent-blue);
      text-decoration: underline;
    }
    
    .login-footer {
      text-align: center;
      color: var(--white);
      margin-top: 2rem;
      font-size: 0.9rem;
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
    }
    
    /* Responsive adjustments */
    @media (max-width: 576px) {
      .login-container {
        padding: 10px;
      }
      
      .login-body {
        padding: 1.5rem;
      }
      
      .login-header {
        padding: 1.5rem;
      }
    }
  </style>
</head>
<body>
  <div class="login-container">
    
    <!-- Login Card -->
    <div class="login-card">
      <div class="login-header">
        <h4><i class="fas fa-user-tie me-2"></i>Login Manajer BMS</h4>
      </div>
      
      <div class="login-body">
        <?php if (!empty($error)): ?>
          <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>
        
        <form method="POST" id="login-form">
          <div class="form-group">
            <label for="username" class="form-label">
              <i class="fas fa-user me-2"></i>Username
            </label>
            <input type="text" name="username" class="form-control" id="username" placeholder="Masukkan username" required>
          </div>
          
          <div class="form-group">
            <label for="password" class="form-label">
              <i class="fas fa-lock me-2"></i>Password
            </label>
            <div class="input-group">
              <input type="password" name="password" class="form-control" id="password" placeholder="Masukkan password" required>
              <span class="input-group-text" id="togglePassword">
                <i class="fas fa-eye-slash" id="eyeIcon"></i>
              </span>
            </div>
          </div>
          
          <div class="buttons-container">
            <button type="submit" class="btn btn-login w-100">
              <i class="fas fa-sign-in-alt me-2"></i>Login
            </button>
            
            <a href="../index.php" class="btn btn-back w-100">
              <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
          </div>
        </form>
        
      </div>
    </div>
    
    <!-- Footer -->
    <div class="login-footer">
      &copy; <?= date('Y') ?> Sistem Kasir Bengkel | All Rights Reserved
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
  <script>
    // Toggle Password Visibility
    const togglePassword = document.querySelector("#togglePassword");
    const password = document.querySelector("#password");
    const eyeIcon = document.querySelector("#eyeIcon");

    togglePassword.addEventListener("click", function (e) {
      // Toggle the type attribute
      const type = password.getAttribute("type") === "password" ? "text" : "password";
      password.setAttribute("type", type);

      // Toggle the eye icon
      eyeIcon.classList.toggle("fa-eye");
      eyeIcon.classList.toggle("fa-eye-slash");
    });
    
    // Prevent form resubmission on page refresh
    if (window.history.replaceState) {
      window.history.replaceState(null, null, window.location.href);
    }
    
    // Add focus effect
    const inputs = document.querySelectorAll('.form-control');
    
    inputs.forEach(input => {
      input.addEventListener('focus', () => {
        input.parentElement.classList.add('focused');
      });
      
      input.addEventListener('blur', () => {
        input.parentElement.classList.remove('focused');
      });
    });
  </script>
</body>
</html>