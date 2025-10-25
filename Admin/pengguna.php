<?php
session_start();

// Cek apakah sudah login dan sebagai admin dengan namespace baru
if (!isset($_SESSION['admin']['logged_in']) || $_SESSION['admin']['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

include '../config.php'; // Pastikan path ke config.php benar

// Proses hapus user (karyawan/admin/manajer) jika ada parameter id dan tipe
if (isset($_GET['hapus']) && isset($_GET['tipe'])) {
    $id = mysqli_real_escape_string($conn, $_GET['hapus']);
    $tipe = mysqli_real_escape_string($conn, $_GET['tipe']);
    
    // Tentukan tabel berdasarkan tipe user
    $tabel = '';
    $id_kolom = '';
    
    if ($tipe == 'karyawan') {
        $tabel = 'karyawan';
        $id_kolom = 'id_karyawan';
    } elseif ($tipe == 'admin') {
        $tabel = 'admin';
        $id_kolom = 'id_admin';
    } elseif ($tipe == 'manajer') {
        $tabel = 'manajer';
        $id_kolom = 'id_manajer';
    }
    
    if (!empty($tabel)) {
        // Query untuk menghapus user
        $query = "DELETE FROM $tabel WHERE $id_kolom = '$id'";
        
        if (mysqli_query($conn, $query)) {
            $_SESSION['message'] = ucfirst($tipe) . " berhasil dihapus!";
            $_SESSION['alert_type'] = "success";
        } else {
            $_SESSION['message'] = "Gagal menghapus data " . $tipe . ": " . mysqli_error($conn);
            $_SESSION['alert_type'] = "danger";
        }
    } else {
        $_SESSION['message'] = "Tipe user tidak valid!";
        $_SESSION['alert_type'] = "danger";
    }
    
    // Redirect ke halaman ini sendiri setelah berhasil hapus
    header("Location: pengguna.php");
    exit();
}

// Query untuk mengambil data dari semua tabel user
$query_karyawan = "SELECT id_karyawan as id, username, nama, 'karyawan' as tipe, updated_at FROM karyawan ORDER BY nama";
$query_admin = "SELECT id_admin as id, username, nama, 'admin' as tipe, updated_at FROM admin ORDER BY nama";
$query_manajer = "SELECT id_manajer as id, username, nama, 'manajer' as tipe, updated_at FROM manajer ORDER BY nama";

$result_karyawan = mysqli_query($conn, $query_karyawan);
$result_admin = mysqli_query($conn, $query_admin);
$result_manajer = mysqli_query($conn, $query_manajer);

// Gabungkan hasil query ke dalam satu array
$all_users = [];
if ($result_karyawan) {
    while ($row = mysqli_fetch_assoc($result_karyawan)) {
        $all_users[] = $row;
    }
}

if ($result_admin) {
    while ($row = mysqli_fetch_assoc($result_admin)) {
        $all_users[] = $row;
    }
}

if ($result_manajer) {
    while ($row = mysqli_fetch_assoc($result_manajer)) {
        $all_users[] = $row;
    }
}

// Urutkan array berdasarkan nama
usort($all_users, function($a, $b) {
    return strcmp($a['nama'], $b['nama']);
});

// Hitung jumlah user per tipe
$count_karyawan = mysqli_num_rows($result_karyawan);
$count_admin = mysqli_num_rows($result_admin);
$count_manajer = mysqli_num_rows($result_manajer);
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kelola Pengguna - BMS Bengkel</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
    
    /* Table styling */
    .table {
      margin-bottom: 0;
    }

    .table th {
      background-color: var(--light-purple);
      color: var(--primary-purple);
      font-weight: 600;
      border: none;
      vertical-align: middle;
    }
    
    .table td {
      vertical-align: middle;
      border-color: #e9ecef;
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
    
    .stats-icon.blue {
      color: var(--primary-purple); /* Changed from blue to purple */
    }
    
    .stats-icon.green {
      color: #4CAF50;
    }
    
    .stats-icon.orange {
      color: #F59E0B;
    }
    
    .stats-icon.red {
      color: #E53E3E;
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
    
    .btn-warning {
      border-radius: 8px;
      transition: all 0.3s ease;
    }
    
    .btn-danger {
      border-radius: 8px;
      transition: all 0.3s ease;
    }
    
    .btn-action {
      padding: 0.4rem 0.7rem;
      border-radius: 6px;
      margin-right: 0.25rem;
    }
    
    .btn-group {
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
      border-radius: 8px;
    }
    
    /* Badge styling */
    .badge {
      padding: 0.5rem 0.75rem;
      font-weight: 500;
      border-radius: 6px;
      font-size: 0.8rem;
    }
    
    .badge-admin {
      background-color: rgba(126, 87, 194, 0.1);
      color: var(--primary-purple);
    }
    
    .badge-karyawan {
      background-color: rgba(126, 87, 194, 0.1);
      color: var(--primary-purple);
    }
    
    .badge-manajer {
      background-color: rgba(126, 87, 194, 0.1);
      color: var(--primary-purple);
    }
    
    /* Nav tabs styling */
    .nav-tabs {
      border-bottom: 1px solid var(--light-purple);
      margin-bottom: 20px;
    }

    .nav-tabs .nav-item {
      margin-right: 10px;
    }

    .nav-tabs .nav-link {
      border: none;
      color: var(--text-dark);
      padding: 12px 20px;
      border-radius: 8px 8px 0 0;
      font-weight: 500;
      transition: all 0.3s ease;
    }

    .nav-tabs .nav-link.active {
      background-color: var(--primary-purple);
      color: white;
      border-bottom: 3px solid var(--primary-purple);
    }

    .nav-tabs .nav-link:hover:not(.active) {
      background-color: rgba(126, 87, 194, 0.1);
      color: var(--primary-purple);
    }
    
    /* Dropdown styling */
    .dropdown-menu {
      border: none;
      border-radius: 8px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
      padding: 10px 0;
    }
    
    .dropdown-item {
      padding: 10px 20px;
      color: var(--text-dark);
      font-weight: 500;
      transition: all 0.3s ease;
    }
    
    .dropdown-item:hover {
      background-color: var(--light-purple);
      color: var(--primary-purple);
    }
    
    .dropdown-item i {
      margin-right: 10px;
      color: var(--primary-purple);
    }
    
    /* Modal styling */
    .modal-content {
      border-radius: 15px;
      border: none;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }
    
    .modal-header {
      background-color: var(--light-purple);
      border-bottom: none;
      border-top-left-radius: 15px;
      border-top-right-radius: 15px;
      padding: 1.5rem;
    }
    
    .modal-title {
      color: var(--primary-purple);
      font-weight: 600;
    }
    
    .modal-body {
      padding: 1.5rem;
    }
    
    .modal-footer {
      border-top: none;
      padding: 1.5rem;
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
    
    /* Empty state */
    .empty-state {
      text-align: center;
      padding: 3rem 1rem;
    }
    
    .empty-state-icon {
      font-size: 4rem;
      color: var(--light-purple);
      margin-bottom: 1.5rem;
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
          <i class="fas fa-users me-2"></i>
          Kelola Pengguna
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
            <h1><i class="fas fa-users me-2"></i>Daftar Pengguna</h1>
            <p class="lead mb-0">Kelola semua pengguna sistem kasir bengkel.</p>
          </div>
          <div class="dropdown">
            <button class="btn btn-light btn-lg dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fas fa-user-plus me-2"></i>
              Tambah Pengguna
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton">
              <li>
                <a class="dropdown-item" href="tambah_admin.php">
                  <i class="fas fa-user-shield"></i> Admin
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="tambah_karyawan.php">
                  <i class="fas fa-user"></i> Karyawan
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="tambah_manajer.php">
                  <i class="fas fa-user-tie"></i> Manajer
                </a>
              </li>
            </ul>
          </div>
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
        <div class="col-md-4">
          <div class="data-card mb-4" style="border-left: 4px solid var(--primary-purple);">
            <div class="stats-card">
              <div class="stats-icon purple">
                <i class="fas fa-users"></i>
              </div>
              <div class="stats-content">
                <h3><?= $count_karyawan ?></h3>
                <p>Total Karyawan</p>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="data-card mb-4" style="border-left: 4px solid var(--secondary-purple);">
            <div class="stats-card">
              <div class="stats-icon purple">
                <i class="fas fa-user-shield"></i>
              </div>
              <div class="stats-content">
                <h3><?= $count_admin ?></h3>
                <p>Total Admin</p>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="data-card mb-4" style="border-left: 4px solid var(--accent-purple);">
            <div class="stats-card">
              <div class="stats-icon purple">
                <i class="fas fa-user-tie"></i>
              </div>
              <div class="stats-content">
                <h3><?= $count_manajer ?></h3>
                <p>Total Manajer</p>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Tabs for filtering users -->
      <ul class="nav nav-tabs" id="userTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab" aria-controls="all" aria-selected="true">
            Semua Pengguna
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="admin-tab" data-bs-toggle="tab" data-bs-target="#admin" type="button" role="tab" aria-controls="admin" aria-selected="false">
            Admin
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="karyawan-tab" data-bs-toggle="tab" data-bs-target="#karyawan" type="button" role="tab" aria-controls="karyawan" aria-selected="false">
            Karyawan
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="manajer-tab" data-bs-toggle="tab" data-bs-target="#manajer" type="button" role="tab" aria-controls="manajer" aria-selected="false">
            Manajer
          </button>
        </li>
      </ul>
      
      <!-- Tab Content -->
      <div class="tab-content" id="userTabsContent">
        <!-- All Users Tab -->
        <div class="tab-pane fade show active" id="all" role="tabpanel" aria-labelledby="all-tab">
          <div class="data-card mb-4">
            <div class="card-header-actions">
              <h5 class="card-title">
                <i class="fas fa-users me-2"></i>
                Daftar Semua Pengguna
              </h5>
            </div>
            
            <div class="table-responsive">
              <table id="allUsersTable" class="table table-hover">
                <thead>
                  <tr>
                    <th width="5%">No</th>
                    <th width="20%">Username</th>
                    <th width="30%">Nama Lengkap</th>
                    <th width="15%">Tipe</th>
                    <th width="15%">Login Terakhir</th>
                    <th width="15%" class="text-center">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                  $no = 1;
                  if (count($all_users) > 0): 
                    foreach ($all_users as $user): 
                      // Siapkan badge class berdasarkan tipe
                      $badge_class = "";
                      $icon_class = "";
                      
                      if ($user['tipe'] == 'admin') {
                        $badge_class = "badge-admin";
                        $icon_class = "user-shield";
                      } elseif ($user['tipe'] == 'karyawan') {
                        $badge_class = "badge-karyawan";
                        $icon_class = "user";
                      } elseif ($user['tipe'] == 'manajer') {
                        $badge_class = "badge-manajer";
                        $icon_class = "user-tie";
                      }
                  ?>
                  <tr>
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($user['username']) ?></td>
                    <td><?= htmlspecialchars($user['nama']) ?></td>
                    <td>
                      <span class="badge <?= $badge_class ?>">
                        <i class="fas fa-<?= $icon_class ?> me-1"></i>
                        <?= ucfirst(htmlspecialchars($user['tipe'])) ?>
                      </span>
                    </td>
                    <td><?= isset($user['updated_at']) && $user['updated_at'] ? date('d/m/Y H:i', strtotime($user['updated_at'])) : '-' ?></td>
                    <td class="text-center">
                      <div class="btn-group" role="group">
                        <a href="edit_pengguna.php?id=<?= $user['id'] ?>&tipe=<?= $user['tipe'] ?>" class="btn btn-sm btn-warning btn-action" data-bs-toggle="tooltip" title="Edit">
                          <i class="fas fa-edit"></i>
                        </a>
                        <button type="button" class="btn btn-sm btn-danger btn-action" 
                                onclick="showDeleteModal('<?= $user['id'] ?>', '<?= $user['tipe'] ?>', '<?= htmlspecialchars($user['nama']) ?>')" 
                                title="Hapus">
                          <i class="fas fa-trash"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                  <?php 
                    endforeach; 
                  else: 
                  ?>
                  <tr>
                    <td colspan="6">
                      <div class="empty-state">
                        <i class="fas fa-users-slash empty-state-icon"></i>
                        <h5 class="fw-bold">Belum Ada Data Pengguna</h5>
                        <p class="text-muted">Belum ada data pengguna yang tersedia. Silakan tambahkan pengguna baru.</p>
                        <div class="dropdown">
                          <button class="btn btn-primary mt-2 dropdown-toggle" type="button" id="emptyDropdownButton" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-plus me-2"></i> Tambah Pengguna
                          </button>
                          <ul class="dropdown-menu" aria-labelledby="emptyDropdownButton">
                            <li>
                              <a class="dropdown-item" href="tambah_admin.php">
                                <i class="fas fa-user-shield"></i> Admin
                              </a>
                            </li>
                            <li>
                              <a class="dropdown-item" href="tambah_karyawan.php">
                                <i class="fas fa-user"></i> Karyawan
                              </a>
                            </li>
                            <li>
                              <a class="dropdown-item" href="tambah_manajer.php">
                                <i class="fas fa-user-tie"></i> Manajer
                              </a>
                            </li>
                          </ul>
                        </div>
                      </div>
                    </td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        
        <!-- Admin Tab -->
        <div class="tab-pane fade" id="admin" role="tabpanel" aria-labelledby="admin-tab">
          <!-- Akan dipopulasi dengan JavaScript -->
        </div>
        
        <!-- Karyawan Tab -->
        <div class="tab-pane fade" id="karyawan" role="tabpanel" aria-labelledby="karyawan-tab">
          <!-- Akan dipopulasi dengan JavaScript -->
        </div>
        
        <!-- Manajer Tab -->
        <div class="tab-pane fade" id="manajer" role="tabpanel" aria-labelledby="manajer-tab">
          <!-- Akan dipopulasi dengan JavaScript -->
        </div>
      </div>
      
     <!-- Single Delete Modal for All Users -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteModalLabel">
          <i class="fas fa-exclamation-triangle text-danger me-2"></i>
          Konfirmasi Hapus
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Anda yakin ingin menghapus <span id="userTypeText"></span> <strong id="userNameText"></strong>?</p>
        <p class="text-danger mb-0"><small>Tindakan ini tidak dapat dibatalkan.</small></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="fas fa-times me-1"></i>
          Batal
        </button>
        <a href="#" id="deleteUserLink" class="btn btn-danger">
          <i class="fas fa-trash me-1"></i>
          Ya, Hapus
        </a>
      </div>
    </div>
  </div>
</div>
<!-- End of Delete Modal -->

<!-- Footer -->
<footer class="bg-light text-center text-muted py-3 mt-5">
  <p class="mb-0">&copy; <?= date('Y') ?> BMS Bengkel - Sistem Manajemen Bengkel</p>
</footer>
      
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

  <script>
$(document).ready(function() {
  // Inisialisasi DataTables hanya jika tabel memiliki data
  if ($('#allUsersTable tbody tr').length > 0 && $('#allUsersTable tbody tr td[colspan]').length === 0) {
    const allUsersTable = $('#allUsersTable').DataTable({
      language: {
        url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
      },
      pageLength: 10,
      lengthMenu: [
        [10, 25, 50, -1],
        [10, 25, 50, 'Semua']
      ],
      responsive: true,
      columnDefs: [
        { orderable: false, targets: [5] }
      ],
      order: [[2, 'asc']]
    });
  }
  
  // Tooltip initialization
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });
  
  // Filter data untuk setiap tab (admin, karyawan, manajer)
  function populateTabContent(tabId, userType) {
    // Buat konten tab
    const cardContent = `
      <div class="data-card mb-4">
        <div class="card-header-actions">
          <h5 class="card-title">
            <i class="fas fa-${userType === 'karyawan' ? 'user' : userType === 'admin' ? 'user-shield' : 'user-tie'} me-2"></i>
            Daftar ${userType.charAt(0).toUpperCase() + userType.slice(1)}
          </h5>
          <a href="tambah_${userType}.php" class="btn btn-primary">
            <i class="fas fa-user-plus me-1"></i>
            Tambah ${userType.charAt(0).toUpperCase() + userType.slice(1)}
          </a>
        </div>
        <div class="table-responsive">
          <table id="${tabId}Table" class="table table-hover">
            <thead>
              <tr>
                <th width="5%">No</th>
                <th width="20%">Username</th>
                <th width="30%">Nama Lengkap</th>
                <th width="15%">Tipe</th>
                <th width="15%">Login Terakhir</th>
                <th width="15%" class="text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
            </tbody>
          </table>
        </div>
      </div>
    `;
    
    // Kosongkan dan tambahkan konten baru
    $(`#${tabId}`).empty().html(cardContent);
    
    // Salin data dari tabel asli yang sesuai dengan tipe user
    let hasData = false;
    let counter = 1;
    
    $('#allUsersTable tbody tr').each(function() {
      const typeCell = $(this).find('td:eq(3)').text().trim().toLowerCase();
      
      if (typeCell.includes(userType)) {
        hasData = true;
        
        // Clone baris dengan semua data
        const clonedRow = $(this).clone();
        
        // Update nomor urut
        clonedRow.find('td:first').text(counter++);
        
        // Tambahkan ke tabel tab
        $(`#${tabId}Table tbody`).append(clonedRow);
      }
    });
    
    // Jika tidak ada data, tampilkan pesan kosong
    if (!hasData) {
      $(`#${tabId}Table tbody`).html(`
        <tr>
          <td colspan="6">
            <div class="empty-state">
              <i class="fas fa-users-slash empty-state-icon"></i>
              <h5 class="fw-bold">Belum Ada Data ${userType.charAt(0).toUpperCase() + userType.slice(1)}</h5>
              <p class="text-muted">Belum ada data ${userType} yang tersedia. Silakan tambahkan ${userType} baru.</p>
              <a href="tambah_${userType}.php" class="btn btn-primary mt-2">
                <i class="fas fa-user-plus me-1"></i> Tambah ${userType.charAt(0).toUpperCase() + userType.slice(1)}
              </a>
            </div>
          </td>
        </tr>
      `);
    } else {
      // Inisialisasi DataTable untuk tab ini jika ada data
      $(`#${tabId}Table`).DataTable({
        language: {
          url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
        },
        pageLength: 10,
        lengthMenu: [
          [10, 25, 50, -1],
          [10, 25, 50, 'Semua']
        ],
        responsive: true,
        columnDefs: [
          { orderable: false, targets: [5] }
        ],
        order: [[2, 'asc']]
      });
    }
  }
  
  // Populate tab content saat tab aktif berubah (perbaikan: menggunakan button, bukan a)
  $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
    const targetTabId = $(e.target).attr('data-bs-target').replace('#', '');
    
    if(targetTabId === 'admin') {
      populateTabContent('admin', 'admin');
    } else if(targetTabId === 'karyawan') {
      populateTabContent('karyawan', 'karyawan');
    } else if(targetTabId === 'manajer') {
      populateTabContent('manajer', 'manajer');
    }
  });
  
  // Fungsi untuk menampilkan modal hapus pengguna
  window.showDeleteModal = function(id, type, name) {
    $('#userTypeText').text(type);
    $('#userNameText').text(name);
    $('#deleteUserLink').attr('href', `pengguna.php?hapus=${id}&tipe=${type}`);
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    deleteModal.show();
  };
  
  // Auto close alerts after 5 seconds
  var alertElement = document.querySelector('.alert');
  if (alertElement) {
    setTimeout(function() {
      alertElement.classList.remove('show');
      setTimeout(function() {
        alertElement.remove();
      }, 150);
    }, 5000);
  }
});
  </script>
</body>
</html>