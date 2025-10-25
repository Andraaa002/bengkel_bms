<?php
session_start();

// Check if logged in and manajer
if (!isset($_SESSION['manajer']['logged_in']) || $_SESSION['manajer']['logged_in'] !== true) {
  header("Location: ../login.php");
  exit();
}
include '../config.php'; // Database connection

// Initialize variables
$nama_kategori = '';
$message = '';
$message_type = '';

// Process category addition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    // Get form data
    $nama_kategori = mysqli_real_escape_string($conn, $_POST['nama_kategori']);

    // Query to save new category to database
    $query = "INSERT INTO kategori (nama_kategori) VALUES ('$nama_kategori')";
    if (mysqli_query($conn, $query)) {
        $_SESSION['message'] = "Kategori berhasil ditambahkan!";
        $_SESSION['alert_type'] = "success";
        $nama_kategori = ''; // Reset form
    } else {
        $_SESSION['message'] = "Gagal menambah kategori: " . mysqli_error($conn);
        $_SESSION['alert_type'] = "danger";
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Process category deletion
if (isset($_GET['delete'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete']);
    
    // Check if category is used by products
    $check_query = "SELECT COUNT(*) as count FROM produk WHERE kategori_id = $id";
    $check_result = mysqli_query($conn, $check_query);
    $row = mysqli_fetch_assoc($check_result);
    
    if ($row['count'] > 0) {
        $_SESSION['message'] = "Kategori tidak dapat dihapus karena masih digunakan oleh produk!";
        $_SESSION['alert_type'] = "danger";
    } else {
        // Query to delete category
        $query = "DELETE FROM kategori WHERE id = $id";
        if (mysqli_query($conn, $query)) {
            $_SESSION['message'] = "Kategori berhasil dihapus!";
            $_SESSION['alert_type'] = "success";
        } else {
            $_SESSION['message'] = "Gagal menghapus kategori: " . mysqli_error($conn);
            $_SESSION['alert_type'] = "danger";
        }
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Get all category data
$query = "SELECT kategori.*, COUNT(produk.id) AS jumlah_produk 
          FROM kategori 
          LEFT JOIN produk ON kategori.id = produk.kategori_id 
          GROUP BY kategori.id 
          ORDER BY kategori.nama_kategori ASC";
$result = mysqli_query($conn, $query);

// Count categories
$count_query = "SELECT COUNT(*) as total FROM kategori";
$count_result = mysqli_query($conn, $count_query);
$count_data = mysqli_fetch_assoc($count_result);
$total_kategori = $count_data['total'];

// Count products in categories
$products_query = "SELECT COUNT(*) as total FROM produk WHERE kategori_id IS NOT NULL";
$products_result = mysqli_query($conn, $products_query);
$products_data = mysqli_fetch_assoc($products_result);
$total_products_categorized = $products_data['total'];

// Count products without category
$uncategorized_query = "SELECT COUNT(*) as total FROM produk WHERE kategori_id IS NULL";
$uncategorized_result = mysqli_query($conn, $uncategorized_query);
$uncategorized_data = mysqli_fetch_assoc($uncategorized_result);
$total_uncategorized = $uncategorized_data['total'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manajemen Kategori - BMS Bengkel</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
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
    }
    
    body {
      background-color: var(--light-gray);
      font-family: 'Poppins', 'Arial', sans-serif;
      color: var(--text-dark);
      margin: 0;
      padding: 0;
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
    
    /* Content area */
    .content {
      margin-left: 280px;
      transition: margin-left 0.3s ease;
      min-height: 100vh;
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
    
    /* Stats cards - horizontal layout */
    .stats-card-wrapper {
      position: relative;
      border-radius: 15px;
      overflow: hidden;
      height: 100%;
      background-color: white;
      border-left: 4px solid;
      box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
      transition: all 0.3s ease;
      padding: 1.5rem;
    }
    
    .stats-card-wrapper:hover {
      transform: translateY(-5px);
      box-shadow: 0 12px 20px rgba(0, 0, 0, 0.12);
    }
    
    .stats-card-orange {
      border-left-color: #EF6C00;
    }
    
    .stats-card-green {
      border-left-color: #4CAF50;
    }
    
    .stats-card-red {
      border-left-color: #F44336;
    }
    
    .stats-card-blue {
      border-left-color: #2196F3;
    }
    
    .stats-card {
      display: flex;
      align-items: center;
    }
    
    .stats-icon-container {
      width: 60px;
      height: 60px;
      border-radius: 15px;
      margin-right: 15px;
      display: flex;
      align-items: center;
      justify-content: center;
      background-color: #FFF3E0;
    }
    
    .stats-icon-categories {
      color: #EF6C00;
    }
    
    .stats-icon-categorized {
      background-color: #E8F5E9;
      color: #4CAF50;
    }
    
    .stats-icon-uncategorized {
      background-color: #FFEBEE;
      color: #F44336;
    }
    
    .stats-icon {
      font-size: 1.8rem;
    }
    
    .stats-content h3 {
      font-size: 1.5rem;
      font-weight: 700;
      margin-bottom: 0;
      color: #333;
    }
    
    .stats-content p {
      font-size: 0.9rem;
      color: #6c757d;
      margin-bottom: 0;
    }
    
    /* Table styling */
    .table {
      margin-bottom: 0;
    }

    .table th {
      background-color: var(--light-orange);
      color: var(--primary-orange);
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
    
    .btn-danger {
      border-radius: 8px;
      padding: 0.375rem 0.75rem;
      transition: all 0.3s ease;
    }
    
    .btn-danger:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 10px rgba(220, 53, 69, 0.2);
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
    
    /* DataTables customization */
    div.dataTables_wrapper div.dataTables_filter input {
      border-radius: 8px;
      border: 1px solid #e0e6ed;
      padding: 0.5rem 1rem;
      margin-left: 0.5rem;
    }
    
    div.dataTables_wrapper div.dataTables_length select {
      border-radius: 8px;
      border: 1px solid #e0e6ed;
      padding: 0.5rem;
    }
    
    .page-item.active .page-link {
      background-color: var(--primary-orange);
      border-color: var(--primary-orange);
    }
    
    .page-link {
      color: var(--primary-orange);
    }
    
    /* Tips notice */
    .tips-notice {
      font-size: 0.85rem;
      font-style: italic;
      color: #4CAF50;
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      background-color: #E8F5E9;
      padding: 0.5rem 1rem;
      border-radius: 8px;
    }
    
    /* Delete confirmation modal */
    .modal-content {
      border-radius: 15px;
      border: none;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }
    
    .modal-header {
      background-color: var(--light-orange);
      border-bottom: none;
      border-top-left-radius: 15px;
      border-top-right-radius: 15px;
      padding: 1.5rem;
    }
    
    .modal-title {
      color: var(--primary-orange);
      font-weight: 600;
    }
    
    .modal-body {
      padding: 1.5rem;
    }
    
    .modal-footer {
      border-top: none;
      padding: 1.5rem;
    }

    /* Category name bold styling */
    .category-name {
      font-weight: 700;
      color: var(--text-dark);
    }
    
    /* Responsive media queries */
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
      
      .stats-icon-container {
        margin-right: 0;
        margin-bottom: 10px;
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
          <i class="fas fa-tags me-2"></i>
          Manajemen Kategori
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
            <h1><i class="fas fa-tags me-2"></i>Daftar Kategori</h1>
            <p class="lead mb-0">Kelola semua kategori produk dalam sistem kasir bengkel.</p>
          </div>
          <button type="button" class="btn btn-light btn-lg" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
            <i class="fas fa-plus me-2"></i>
            Tambah Kategori
          </button>
        </div>
      </div>

      <!-- Alert Messages -->
      <?php if (isset($_SESSION['message'])): ?>
      <div class="alert alert-<?= $_SESSION['alert_type'] ?> alert-dismissible fade show" role="alert" id="autoCloseAlert">
        <i class="fas fa-<?= $_SESSION['alert_type'] == 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
        <?= $_SESSION['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
      <?php
        unset($_SESSION['message']);
        unset($_SESSION['alert_type']);
      endif;
      ?>
      
<!-- Summary Cards - Horizontal Layout -->
<div class="row g-4 mb-4">
  <div class="col-md-4">
    <div class="stats-card-wrapper stats-card-orange">
      <div class="stats-card">
        <div class="stats-icon-container">
          <i class="fas fa-tag stats-icon stats-icon-categories"></i>
        </div>
        <div class="stats-content">
          <h3><?= $total_kategori ?></h3>
          <p>Total Kategori</p>
        </div>
      </div>
    </div>
  </div>
  
  <div class="col-md-4">
  <div class="stats-card-wrapper stats-card-green">
    <div class="stats-card">
      <div class="stats-icon-container stats-icon-categorized">
        <i class="fas fa-check-circle stats-icon"></i> <!-- Ganti dengan ikon yang pasti ada -->
      </div>
      <div class="stats-content">
        <h3><?= $total_products_categorized ?></h3>
        <p>Produk Terkategori</p>
      </div>
    </div>
  </div>
</div>
  
  <div class="col-md-4">
    <div class="stats-card-wrapper stats-card-red">
      <div class="stats-card">
        <div class="stats-icon-container stats-icon-uncategorized">
          <i class="fas fa-box-open stats-icon"></i>
        </div>
        <div class="stats-content">
          <h3><?= $total_uncategorized ?></h3>
          <p>Produk Belum Terkategori</p>
        </div>
      </div>
    </div>
  </div>
</div>

      <div class="tips-notice">
        <i class="fas fa-lightbulb me-2"></i>
        <span>Tips: Kategori dengan produk yang terkait tidak dapat dihapus. Pindahkan produk terlebih dahulu ke kategori lain.</span>
      </div>

      <!-- Category Table Card -->
      <div class="card-header-actions">
        <h5 class="card-title">
          <i class="fas fa-tags me-2"></i>
          Daftar Semua Kategori
        </h5>
      </div>
            
      <div class="table-responsive">
        <table id="categoriesTable" class="table table-hover">
          <thead>
            <tr>
              <th width="5%">No</th>
              <th width="40%">Nama Kategori</th>
              <th width="15%" class="text-center">Jumlah Produk</th>
              <th width="15%" class="text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $no = 1;
            if (mysqli_num_rows($result) > 0):
              while ($row = mysqli_fetch_assoc($result)):
            ?>
            <tr>
              <td><?= $no++ ?></td>
              <td>
                <span class="category-name"><?= htmlspecialchars($row['nama_kategori']) ?></span>
              </td>
              <td class="text-center">
                <span class="badge bg-light text-dark">
                  <strong><?= $row['jumlah_produk'] ?> Produk</strong>
                </span>
              </td>
              <td class="text-center">
                <div class="btn-group" role="group">
                  <a href="edit_kategori.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning btn-action" data-bs-toggle="tooltip" title="Edit">
                    <i class="fas fa-edit"></i>
                  </a>
                  <!-- Tombol hapus yang terintegrasi dengan modal -->
                  <button type="button" class="btn btn-sm btn-danger btn-action" 
                          onclick="showDeleteModal('<?= $row['id'] ?>', '<?= htmlspecialchars($row['nama_kategori']) ?>', <?= $row['jumlah_produk'] ?>)" 
                          title="Hapus" <?= $row['jumlah_produk'] > 0 ? 'disabled' : '' ?>>
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
            <?php
              endwhile;
            else:
            ?>
            <tr>
              <td colspan="4" class="text-center py-4">
                <div class="d-flex flex-column align-items-center">
                  <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                  <h5 class="fw-bold">Belum Ada Kategori</h5>
                  <p class="text-muted">Silahkan tambahkan kategori baru untuk ditampilkan di sini.</p>
                  <button type="button" class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="fas fa-plus me-1"></i>
                    Tambah Kategori Sekarang
                  </button>
                </div>
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      
      <!-- Delete Modal -->
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
              <p>Anda yakin ingin menghapus kategori <strong id="categoryNameText"></strong>?</p>
              <p class="text-danger mb-0"><small>Tindakan ini tidak dapat dibatalkan.</small></p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <i class="fas fa-times me-1"></i>
                Batal
              </button>
              <a href="#" id="deleteCategoryLink" class="btn btn-danger">
                <i class="fas fa-trash me-1"></i>
                Ya, Hapus
              </a>
            </div>
          </div>
        </div>
      </div>
      
    </div>
  </div>

  <!-- Add Category Modal -->
  <div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="addCategoryModalLabel">
            <i class="fas fa-plus-circle me-2"></i>
            Tambah Kategori Baru
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form action="kategori.php" method="POST">
          <div class="modal-body">
            <input type="hidden" name="action" value="add">
            <div class="mb-3">
              <label for="nama_kategori" class="form-label">Nama Kategori</label>
              <input type="text" class="form-control" id="nama_kategori" name="nama_kategori" placeholder="Masukkan nama kategori" required>
              <div class="form-text">
                <i class="fas fa-info-circle me-1"></i>
                Contoh: Sparepart, Oli, Jasa Service
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
              <i class="fas fa-times me-1"></i>
              Batal
            </button>
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save me-1"></i>
              Simpan Kategori
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  
  <script>
    $(document).ready(function() {
      // Check if table is actually empty (has the "no categories" message)
      if ($('#categoriesTable tbody tr').length === 1 && $('#categoriesTable tbody tr td[colspan]').length > 0) {
        // For empty tables, don't initialize DataTables at all
        // Just leave the default HTML table with the "no categories" message
        return; // Skip DataTables initialization completely
      } else {
        // Only initialize DataTables if there's actual data
        $('#categoriesTable').DataTable({
          language: {
            url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json"
          },
          responsive: true,
          "order": [[0, "asc"]],
          "pageLength": 10,
          "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "Semua"]]
        });
      }
      
      // Initialize tooltips
      var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
      var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
      });
      
      // Auto close alerts after 5 seconds
      var alertElement = document.getElementById('autoCloseAlert');
      if (alertElement) {
        var bsAlert = new bootstrap.Alert(alertElement);
        
        setTimeout(function() {
          if (document.body.contains(alertElement)) {
            bsAlert.close();
          }
        }, 5000);
      }
      
      // Delete modal function
      window.showDeleteModal = function(id, name, jumlahProduk) {
        // Cek apakah kategori masih memiliki produk terkait
        if (jumlahProduk > 0) {
          // Jika masih ada produk terkait, tampilkan pesan error
          alert(`Kategori "${name}" tidak dapat dihapus karena masih memiliki ${jumlahProduk} produk terkait.`);
          return false;
        }
        
        // Jika tidak ada produk, tampilkan modal konfirmasi hapus
        $('#categoryNameText').text(name);
        $('#deleteCategoryLink').attr('href', `?delete=${id}`);
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        deleteModal.show();
      };
    });
  </script>
</body>
</html>