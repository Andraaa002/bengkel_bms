<?php
session_start();

// Check if logged in and admin
if (!isset($_SESSION['manajer']['logged_in']) || $_SESSION['manajer']['logged_in'] !== true) {
  header("Location: ../login.php");
  exit();
}

include '../config.php'; // Database connection

// Set timezone to Jakarta/Indonesia
date_default_timezone_set('Asia/Jakarta');

// Process delete action if requested
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete']);
    
    // Mulai transaksi database untuk memastikan konsistensi data
    mysqli_begin_transaction($conn);
    
    try {
        // Get product info before deleting
        $product_query = "SELECT nama, stok, harga_beli FROM produk WHERE id = '$id'";
        $product_result = mysqli_query($conn, $product_query);
        
        if ($product_result && mysqli_num_rows($product_result) > 0) {
            $product_data = mysqli_fetch_assoc($product_result);
            $product_name = $product_data['nama'];
            
            // Cari pengeluaran terkait produk ini berdasarkan keterangan
            $pengeluaran_query = "SELECT id FROM pengeluaran 
                                WHERE keterangan LIKE '%Pembelian produk: $product_name%'";
            $pengeluaran_result = mysqli_query($conn, $pengeluaran_query);
            
            // Hapus semua pengeluaran terkait
            if ($pengeluaran_result && mysqli_num_rows($pengeluaran_result) > 0) {
                while ($row = mysqli_fetch_assoc($pengeluaran_result)) {
                    $pengeluaran_id = $row['id'];
                    $delete_expense_query = "DELETE FROM pengeluaran WHERE id = '$pengeluaran_id'";
                    
                    if (!mysqli_query($conn, $delete_expense_query)) {
                        throw new Exception("Gagal menghapus pengeluaran terkait: " . mysqli_error($conn));
                    }
                }
            }
            
            // Hapus produk
            $delete_query = "DELETE FROM produk WHERE id = '$id'";
            if (!mysqli_query($conn, $delete_query)) {
                throw new Exception("Gagal menghapus produk: " . mysqli_error($conn));
            }
            
            // Commit transaksi jika berhasil
            mysqli_commit($conn);
            
            $_SESSION['message'] = "Produk berhasil dihapus! Catatan pengeluaran terkait juga telah dihapus.";
            $_SESSION['alert_type'] = "success";
        } else {
            throw new Exception("Produk tidak ditemukan");
        }
    } catch (Exception $e) {
        // Rollback transaksi jika terjadi error
        mysqli_rollback($conn);
        
        $_SESSION['message'] = "Gagal menghapus produk: " . $e->getMessage();
        $_SESSION['alert_type'] = "danger";
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Fetch products with category join
$query = "
    SELECT produk.id, produk.nama, produk.harga_beli, produk.harga_jual, produk.stok, produk.hutang_sparepart, produk.nominal_hutang, kategori.nama_kategori
    FROM produk
    LEFT JOIN kategori ON produk.kategori_id = kategori.id
    ORDER BY produk.nama ASC
";
$result = mysqli_query($conn, $query);

// Define stock threshold
$stock_threshold = 5; // Products with stock ≤ 5 are considered "low stock"

// Queries for summary cards
$count_query = "SELECT COUNT(*) as total FROM produk";
$count_result = mysqli_query($conn, $count_query);
$count_data = mysqli_fetch_assoc($count_result);

$low_stock_query = "SELECT COUNT(*) as total FROM produk WHERE stok <= $stock_threshold";
$low_stock_result = mysqli_query($conn, $low_stock_query);
$low_stock_data = mysqli_fetch_assoc($low_stock_result);

$hutang_count_query = "SELECT COUNT(*) as total FROM produk WHERE hutang_sparepart = 'Hutang'";
$hutang_count_result = mysqli_query($conn, $hutang_count_query);
$hutang_count_data = mysqli_fetch_assoc($hutang_count_result);

$hutang_query = "SELECT SUM(nominal_hutang) as total FROM produk WHERE hutang_sparepart = 'Hutang'";
$hutang_result = mysqli_query($conn, $hutang_query);
$hutang_data = mysqli_fetch_assoc($hutang_result);
$total_hutang = $hutang_data['total'] ? $hutang_data['total'] : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manajemen Produk - BMS Bengkel</title>
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
    
    .stats-icon-products {
      color: #EF6C00;
    }
    
    .stats-icon-lowstock {
      background-color: #FFEBEE;
      color: #F44336;
    }
    
    .stats-icon-credit {
      background-color: #E8EAF6;
      color: #3F51B5;
    }
    
    .stats-icon-money {
      background-color: #E0F7FA;
      color: #00ACC1;
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
    
    /* Stock threshold notice */
    .stock-threshold-notice {
      font-size: 0.85rem;
      font-style: italic;
      color: #F44336;
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      background-color: #FFEBEE;
      padding: 0.5rem 1rem;
      border-radius: 8px;
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
    
    .badge-stock-low {
      background-color: #FFECB3;
      color: #FF8F00;
    }
    
    .badge-stock-ok {
      background-color: #C8E6C9;
      color: #2E7D32;
    }
    
    /* Payment status badges */
    .badge-cash {
      background-color: #C8E6C9;
      color: #2E7D32;
    }
    
    .badge-hutang {
      background-color: #FFCDD2;
      color: #C62828;
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
    
    /* Price column alignment */
    .text-price {
      text-align: right;
    }
    
    /* Stock status colors */
    .stock-low {
      color: #F44336;
      font-weight: 600;
    }
    
    .stock-warning {
      color: #FF9800;
      font-weight: 600;
    }
    
    .stock-ok {
      color: #4CAF50;
      font-weight: 600;
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

    /* Product name bold styling */
    .product-name {
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
          <i class="fas fa-box me-2"></i>
          Manajemen Produk
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
            <h1><i class="fas fa-boxes me-2"></i>Daftar Produk</h1>
            <p class="lead mb-0">Kelola semua produk dalam sistem kasir bengkel.</p>
          </div>
          <a href="tambah_produk.php" class="btn btn-light btn-lg">
            <i class="fas fa-plus me-2"></i>
            Tambah Produk
          </a>
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
        <div class="col-md-3">
          <div class="stats-card-wrapper stats-card-orange">
            <div class="stats-card">
              <div class="stats-icon-container">
                <i class="fas fa-boxes stats-icon stats-icon-products"></i>
              </div>
              <div class="stats-content">
                <h3><?= $count_data['total'] ?></h3>
                <p>Total Produk</p>
              </div>
            </div>
          </div>
        </div>
        
        <div class="col-md-3">
          <div class="stats-card-wrapper stats-card-red">
            <div class="stats-card">
              <div class="stats-icon-container stats-icon-lowstock">
                <i class="fas fa-exclamation-triangle stats-icon"></i>
              </div>
              <div class="stats-content">
                <h3><?= $low_stock_data['total'] ?></h3>
                <p>Stok Menipis</p>
                <p>(≤ <?= $stock_threshold ?> unit)</p>
              </div>
            </div>
          </div>
        </div>
        
        <div class="col-md-3">
          <div class="stats-card-wrapper stats-card-blue">
            <div class="stats-card">
              <div class="stats-icon-container stats-icon-credit">
                <i class="fas fa-credit-card stats-icon"></i>
              </div>
              <div class="stats-content">
                <h3><?= $hutang_count_data['total'] ?></h3>
                <p>Produk Hutang</p>
              </div>
            </div>
          </div>
        </div>
        
        <div class="col-md-3">
          <div class="stats-card-wrapper stats-card-green">
            <div class="stats-card">
              <div class="stats-icon-container stats-icon-money">
                <i class="fas fa-money-bill-alt stats-icon"></i>
              </div>
              <div class="stats-content">
                <h3>Rp <?= number_format($total_hutang, 0, ',', '.') ?></h3>
                <p>Total Hutang</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="stock-threshold-notice">
        <i class="fas fa-info-circle me-2"></i>
        <span>Catatan: Stok dianggap "menipis" jika kurang dari atau sama dengan <?= $stock_threshold ?> unit.</span>
      </div>

      <!-- Di bagian card-header-actions, perbarui bagian tombol: -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
  <div class="card-header bg-white py-3">
    <div class="d-flex justify-content-between align-items-center">
      <h5 class="mb-0 text-primary">
        <i class="fas fa-box me-2"></i>
        Daftar Semua Produk
      </h5>
      <div>
        <a href="export_produk_pdf.php" class="btn btn-danger btn-sm">
          <i class="fas fa-file-pdf me-2"></i> Export PDF
        </a>
        <a href="export_produk_excel.php" class="btn btn-success btn-sm ms-2">
          <i class="fas fa-file-excel me-2"></i> Export Excel
        </a>
      </div>
    </div>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <!-- Tabel produk di sini -->
            
            <div class="table-responsive">
              <table id="productsTable" class="table table-hover">
                <thead>
                  <tr>
                    <th width="5%">No</th>
                    <th width="20%">Nama Produk</th>
                    <th width="12%">Kategori</th>
                    <th width="12%" class="text-end">Harga Beli</th>
                    <th width="12%" class="text-end">Harga Jual</th>
                    <th width="8%" class="text-center">Stok</th>
                    <th width="15%" class="text-center">Status Pembayaran</th>
                    <th width="15%" class="text-center">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $no = 1;
                  if (mysqli_num_rows($result) > 0):
                    while ($row = mysqli_fetch_assoc($result)):
                      // Determine stock status class
                      $stock_class = '';
                      if ($row['stok'] <= $stock_threshold) {
                        $stock_class = 'stock-low';
                      } else if ($row['stok'] <= 10) {
                        $stock_class = 'stock-warning';
                      } else {
                        $stock_class = 'stock-ok';
                      }
                  ?>
                  <tr>
                    <td><?= $no++ ?></td>
                    <td>
                      <span class="product-name"><?= htmlspecialchars($row['nama']) ?></span>
                    </td>
                    <td>
                      <span class="badge bg-light text-dark">
                        <?= htmlspecialchars($row['nama_kategori'] ?: 'Tidak Terkategori') ?>
                      </span>
                    </td>
                    <td class="text-end"><strong>Rp <?= number_format($row['harga_beli'], 0, ',', '.') ?></strong></td>
                    <td class="text-end"><strong>Rp <?= number_format($row['harga_jual'], 0, ',', '.') ?></strong></td>
                    <td class="text-center">
                      <span class="<?= $stock_class ?>">
                        <?= $row['stok'] ?>
                      </span>
                    </td>
                    <td class="text-center">
                      <?php if ($row['hutang_sparepart'] == 'Hutang'): ?>
                        <span class="badge badge-hutang">
                          <i class="fas fa-credit-card me-1"></i> Hutang
                          <?php if ($row['nominal_hutang'] > 0): ?>
                            <br>
                            <small>Rp <?= number_format($row['nominal_hutang'], 0, ',', '.') ?></small>
                          <?php endif; ?>
                        </span>
                      <?php else: ?>
                        <span class="badge badge-cash">
                          <i class="fas fa-money-bill-wave me-1"></i> Cash
                        </span>
                      <?php endif; ?>
                    </td>
                    <td class="text-center">
                      <div class="btn-group" role="group">
                        <a href="edit_produk.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning btn-action" data-bs-toggle="tooltip" title="Edit">
                          <i class="fas fa-edit"></i>
                        </a>
                        <!-- Tombol hapus yang terintegrasi dengan modal -->
                        <button type="button" class="btn btn-sm btn-danger btn-action" 
                                onclick="showDeleteModal('<?= $row['id'] ?>', '<?= htmlspecialchars($row['nama']) ?>')" 
                                title="Hapus">
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
                    <td colspan="8" class="text-center py-4">
                      <div class="d-flex flex-column align-items-center">
                        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                        <h5 class="fw-bold">Belum Ada Produk</h5>
                        <p class="text-muted">Silahkan tambahkan produk baru untuk ditampilkan di sini.</p>
                        <a href="tambah_produk.php" class="btn btn-primary mt-2">
                          <i class="fas fa-plus me-1"></i>
                          Tambah Produk Sekarang
                        </a>
                      </div>
                    </td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
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
              <p>Anda yakin ingin menghapus produk <strong id="productNameText"></strong>?</p>
              <p class="text-warning mb-0"><i class="fas fa-info-circle me-1"></i> Catatan pengeluaran terkait produk ini juga akan dihapus.</p>
              <p class="text-danger mb-0"><small>Tindakan ini tidak dapat dibatalkan.</small></p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <i class="fas fa-times me-1"></i>
                Batal
              </button>
              <a href="#" id="deleteProductLink" class="btn btn-danger">
                <i class="fas fa-trash me-1"></i>
                Ya, Hapus
              </a>
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
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  
<script>
$(document).ready(function() {
  // Check if table is actually empty (has the "no products" message)
  if ($('#productsTable tbody tr').length === 1 && $('#productsTable tbody tr td[colspan]').length > 0) {
    // For empty tables, don't initialize DataTables at all
    // Just leave the default HTML table with the "no products" message
    return; // Skip DataTables initialization completely
  } else {
    // Only initialize DataTables if there's actual data
    $('#productsTable').DataTable({
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
    window.showDeleteModal = function(id, name) {
      $('#productNameText').text(name);
      $('#deleteProductLink').attr('href', `?delete=${id}`);
      const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
      deleteModal.show();
    };
  });
</script>
</body>
</html>