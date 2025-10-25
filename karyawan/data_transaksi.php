<?php
include '../config.php';
session_start();

// Cek apakah user sudah login dan memiliki role 'karyawan' dengan struktur session baru
if (!isset($_SESSION['karyawan']['logged_in']) || $_SESSION['karyawan']['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$nama_karyawan = $_SESSION['karyawan']['nama'];

// Inisialisasi variabel filter
$tanggalFilter = isset($_GET['tanggal']) ? $_GET['tanggal'] : '';
$bulanFilter   = isset($_GET['bulan'])   ? $_GET['bulan']   : '';
$tahunIni = date('Y');


// Sorting parameters
$sortColumn = isset($_GET['sort']) ? $_GET['sort'] : 'id';  // Changed from 'tanggal' to 'id'
$sortOrder = isset($_GET['order']) ? $_GET['order'] : 'DESC';  // Keep this as DESC

// Valid column names for sorting to prevent SQL injection
$validColumns = ['id', 'tanggal', 'nama_customer', 'no_whatsapp', 'plat_nomor_motor', 'kasir', 'total', 'status_hutang', 'hutang'];
if (!in_array($sortColumn, $validColumns)) {
    $sortColumn = 'tanggal'; // Default to tanggal if invalid column
}

// Toggle sort order for links
$toggleOrder = ($sortOrder === 'ASC') ? 'DESC' : 'ASC';

// Function to generate sort link
function getSortLink($column, $currentSort, $currentOrder, $tanggalFilter, $bulanFilter) {
    $newOrder = ($currentSort === $column && $currentOrder === 'ASC') ? 'DESC' : 'ASC';
    $link = "?sort=$column&order=$newOrder";
    
    if (!empty($tanggalFilter)) {
        $link .= "&tanggal=" . urlencode($tanggalFilter);
    }
    if (!empty($bulanFilter)) {
        $link .= "&bulan=" . urlencode($bulanFilter);
    }
    
    return $link;
}

// Function to show sort icon
function getSortIcon($column, $currentSort, $currentOrder) {
    if ($currentSort !== $column) {
        return '<i class="fas fa-sort text-muted"></i>';
    }
    return ($currentOrder === 'ASC') ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Daftar Transaksi | Kasir Bengkel</title>

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
      --danger-color: #F44336;
      --success-color: #4CAF50;
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
    
    .filter-card {
      background-color: var(--light-green);
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 25px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }
    
    .filter-form {
      display: flex;
      flex-wrap: wrap;
      gap: 15px;
      align-items: flex-end;
    }
    
    .form-group {
      flex: 1;
      min-width: 200px;
    }
    
    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 500;
      color: var(--accent-green);
    }
    
    .form-control {
      width: 100%;
      padding: 10px;
      border: 1px solid var(--border-color);
      border-radius: 8px;
      font-size: 14px;
      transition: border-color 0.3s;
    }
    
    .form-control:focus {
      outline: none;
      border-color: var(--primary-green);
      box-shadow: 0 0 0 2px rgba(38, 166, 154, 0.2);
    }
    
    .form-buttons {
      display: flex;
      gap: 10px;
      align-items: center;
    }
    
    .btn-filter {
      background-color: var(--primary-green);
      color: white;
      border: none;
      border-radius: 8px;
      padding: 10px 20px;
      font-weight: 500;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .btn-filter:hover {
      background-color: var(--accent-green);
      transform: translateY(-2px);
      box-shadow: 0 4px 10px rgba(38, 166, 154, 0.25);
    }
    
    .btn-reset {
      background-color: var(--white);
      color: var(--text-dark);
      border: 1px solid var(--border-color);
      border-radius: 8px;
      padding: 10px 20px;
      font-weight: 500;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .btn-reset:hover {
      background-color: var(--light-gray);
      transform: translateY(-2px);
    }
    
    .table-container {
      background-color: var(--white);
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    }
    
    /* Horizontal scrolling for mobile */
    .table-responsive {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }
    
    /* Style for pagination container */
    .pagination-container {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      align-items: center;
      gap: 15px;
      margin-top: 25px;
    }
    
    .pagination-info {
      color: var(--text-dark);
      font-size: 14px;
      text-align: center;
      margin-top: 10px;
    }
    
    .table {
      width: 100%;
      margin-bottom: 0;
    }
    
    .table thead th {
      background-color: var(--primary-green);
      color: var(--white);
      font-weight: 600;
      border: none;
      padding: 15px;
      vertical-align: middle;
      position: relative;
    }
    
    .table thead th a {
      color: var(--white);
      text-decoration: none;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    
    .table thead th a:hover {
      color: var(--light-green);
    }
    
    .table tbody tr:hover {
      background-color: var(--light-green);
    }
    
    .table tbody td {
      padding: 15px;
      vertical-align: middle;
      border-color: var(--border-color);
    }
    
    .badge-whatsapp {
      background-color: #25D366;
      color: white;
      padding: 5px 12px;
      border-radius: 50px;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      font-size: 12px;
      font-weight: 500;
      text-decoration: none;
      transition: all 0.2s ease;
    }
    
    .badge-whatsapp:hover {
      background-color: #128C7E;
      transform: translateY(-2px);
      color: white;
    }
    
    .btn-detail {
      background-color: var(--primary-green);
      color: white;
      border: none;
      border-radius: 8px;
      padding: 6px 15px;
      font-size: 13px;
      transition: all 0.2s ease;
      text-decoration: none;
    }
    
    .btn-detail:hover {
      background-color: var(--accent-green);
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(38, 166, 154, 0.2);
      color: white;
    }
    
    .pagination {
      display: flex;
      justify-content: center;
      margin-top: 25px;
      gap: 5px;
    }
    
    .page-item .page-link {
      border-radius: 8px;
      color: var(--primary-green);
      border: 1px solid var(--border-color);
      width: 36px;
      height: 36px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 500;
    }
    
    .page-item.active .page-link {
      background-color: var(--primary-green);
      border-color: var(--primary-green);
      color: white; /* Make sure the text is visible when active */
    }
    
    .page-item .page-link:hover {
      background-color: var(--light-green);
      border-color: var(--primary-green);
    }
    
    .empty-state {
      text-align: center;
      padding: 60px 20px;
    }
    
    .empty-state i {
      font-size: 50px;
      color: #ccc;
      margin-bottom: 15px;
    }
    
    .empty-state p {
      font-size: 16px;
      color: #777;
      margin-bottom: 20px;
    }
    
    /* Badge styles for status */
    .badge-lunas {
      background-color: var(--success-color);
      color: white;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 12px;
      font-weight: 500;
    }
    
    .badge-hutang {
      background-color: var(--danger-color);
      color: white;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 12px;
      font-weight: 500;
    }
    
    .invoice-id {
      font-weight: 600;
      color: var(--primary-green);
    }
    
    /* Sort icon spacing */
    .sort-icon {
      margin-left: 5px;
    }
    
    /* Responsive styles */
    @media (max-width: 992px) {
      .content {
        margin-left: 0;
      }
      
      .filter-form {
        flex-direction: column;
      }
      
      .form-buttons {
        width: 100%;
        justify-content: space-between;
        margin-top: 10px;
      }
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
          <i class="fas fa-receipt me-2"></i>
          Daftar Transaksi
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
      <!-- Filter Section -->
      <div class="filter-card">
        <form class="filter-form" method="GET">
          <div class="form-group">
            <label for="tanggal">
              <i class="fas fa-calendar-day me-1"></i> Filter Tanggal
            </label>
            <input 
              type="date" 
              id="tanggal" 
              name="tanggal" 
              class="form-control" 
              value="<?= htmlspecialchars($tanggalFilter) ?>"
            >
          </div>
          
          <div class="form-group">
            <label for="bulan">
              <i class="fas fa-calendar-alt me-1"></i> Filter Bulan
            </label>
            <select id="bulan" name="bulan" class="form-control">
              <option value="">-- Pilih Bulan --</option>
              <?php
              for ($m = 1; $m <= 12; $m++) {
                $value = str_pad($m, 2, '0', STR_PAD_LEFT);
                $selected = ($bulanFilter === $value) ? 'selected' : '';
                echo "<option value='$value' $selected>" . date('F', mktime(0, 0, 0, $m, 10)) . "</option>";
              }
              ?>
            </select>
          </div>
          
          <!-- Preserve sort parameters in filter form -->
          <input type="hidden" name="sort" value="<?= htmlspecialchars($sortColumn) ?>">
          <input type="hidden" name="order" value="<?= htmlspecialchars($sortOrder) ?>">
          
          <div class="form-buttons">
            <button type="submit" class="btn-filter">
              <i class="fas fa-search"></i> Cari
            </button>
            <a href="data_transaksi.php" class="btn-reset">
              <i class="fas fa-sync-alt"></i> Reset
            </a>
          </div>
        </form>
      </div>
      
      <!-- Table Section -->
      <!-- Responsive Table Container -->
      <div class="table-responsive table-container">
        <table class="table">
          <thead>
            <tr>
              <th class="col-no">No</th>
              <th class="col-invoice">
                <a href="<?= getSortLink('id', $sortColumn, $sortOrder, $tanggalFilter, $bulanFilter) ?>">
                  ID Transaksi <?= getSortIcon('id', $sortColumn, $sortOrder) ?>
                </a>
              </th>
              <th class="col-tanggal">
                <a href="<?= getSortLink('tanggal', $sortColumn, $sortOrder, $tanggalFilter, $bulanFilter) ?>">
                  Tanggal <?= getSortIcon('tanggal', $sortColumn, $sortOrder) ?>
                </a>
              </th>
              <th class="col-customer">
                <a href="<?= getSortLink('nama_customer', $sortColumn, $sortOrder, $tanggalFilter, $bulanFilter) ?>">
                  Customer <?= getSortIcon('nama_customer', $sortColumn, $sortOrder) ?>
                </a>
              </th>
              <th class="col-wa">
                <a href="<?= getSortLink('no_whatsapp', $sortColumn, $sortOrder, $tanggalFilter, $bulanFilter) ?>">
                  Nomor WA <?= getSortIcon('no_whatsapp', $sortColumn, $sortOrder) ?>
                </a>
              </th>
              <th class="col-plat">
                <a href="<?= getSortLink('plat_nomor_motor', $sortColumn, $sortOrder, $tanggalFilter, $bulanFilter) ?>">
                  Plat Nomor <?= getSortIcon('plat_nomor_motor', $sortColumn, $sortOrder) ?>
                </a>
              </th>
              <th class="col-kasir">
                <a href="<?= getSortLink('kasir', $sortColumn, $sortOrder, $tanggalFilter, $bulanFilter) ?>">
                  Kasir <?= getSortIcon('kasir', $sortColumn, $sortOrder) ?>
                </a>
              </th>
              <th class="col-total text-end">
                <a href="<?= getSortLink('total', $sortColumn, $sortOrder, $tanggalFilter, $bulanFilter) ?>">
                  Total <?= getSortIcon('total', $sortColumn, $sortOrder) ?>
                </a>
              </th>
              <th class="col-status text-center">
                <a href="<?= getSortLink('status_hutang', $sortColumn, $sortOrder, $tanggalFilter, $bulanFilter) ?>">
                  Status <?= getSortIcon('status_hutang', $sortColumn, $sortOrder) ?>
                </a>
              </th>
              <th class="col-hutang text-end">
                <a href="<?= getSortLink('hutang', $sortColumn, $sortOrder, $tanggalFilter, $bulanFilter) ?>">
                  Hutang <?= getSortIcon('hutang', $sortColumn, $sortOrder) ?>
                </a>
              </th>
              <th class="col-aksi text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
          <?php
          // Konfigurasi pagination
          $records_per_page = 10;
          $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
          $offset = ($page - 1) * $records_per_page;
          
          // Query untuk menghitung total rows untuk pagination
          $count_query = "SELECT COUNT(*) as total FROM transaksi WHERE 1=1";
          
          if (!empty($tanggalFilter)) {
            $count_query .= " AND tanggal = '$tanggalFilter'";
          }
          if (!empty($bulanFilter)) {
            $count_query .= " AND MONTH(tanggal) = '$bulanFilter'";
          }
          
          $count_result = $conn->query($count_query);
          $total_rows = $count_result->fetch_assoc()['total'];
          $total_pages = ceil($total_rows / $records_per_page);
          
          // Query untuk data dengan pagination dan sorting
          $query = "SELECT * FROM transaksi WHERE 1=1";

          if (!empty($tanggalFilter)) {
            $query .= " AND tanggal = '$tanggalFilter'";
          }
          if (!empty($bulanFilter)) {
            $query .= " AND MONTH(tanggal) = '$bulanFilter'";
          }

          $query .= " ORDER BY $sortColumn $sortOrder LIMIT $offset, $records_per_page";
          $result = $conn->query($query);
          
          // Starting number for each page
          $no = $offset + 1;

          if ($result->num_rows > 0):
            while ($row = $result->fetch_assoc()):
              // Format tanggal ke format Indonesia
              $tanggal = date('d M Y', strtotime($row['tanggal']));
              
              // Format ID transaksi dengan leading zero - format #0001
              $formatted_id = sprintf("#%04d", $row['id']);
              
              // Determine the status (lunas or hutang)
              $is_lunas = isset($row['status_hutang']) ? ($row['status_hutang'] == 0) : true;
              $hutang_amount = isset($row['hutang']) ? $row['hutang'] : 0;
          ?>
            <tr>
              <td class="text-center"><?= $no++ ?></td>
              <td><span class="invoice-id"><?= $formatted_id ?></span></td>
              <td><?= $tanggal ?></td>
              <td><?= htmlspecialchars($row['nama_customer']) ?></td>
              <td>
                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $row['no_whatsapp']) ?>" target="_blank" class="badge-whatsapp">
                  <i class="fab fa-whatsapp"></i> <?= $row['no_whatsapp'] ?>
                </a>
              </td>
              <td><?= strtoupper($row['plat_nomor_motor']) ?></td>
              <td><?= $row['kasir'] ?></td>
              <td class="text-end"><strong>Rp <?= number_format($row['total'], 0, ',', '.') ?></strong></td>
              <td class="text-center">
                <?php if ($is_lunas): ?>
                  <span class="badge-lunas">Lunas</span>
                <?php else: ?>
                  <span class="badge-hutang">Belum Lunas</span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <?php if (!$is_lunas && $hutang_amount > 0): ?>
                  <strong>Rp <?= number_format($hutang_amount, 0, ',', '.') ?></strong>
                <?php else: ?>
                  <span class="text-muted">-</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <a href="detail_transaksi.php?id=<?= $row['id'] ?>" class="btn-detail">
                  <i class="fas fa-eye"></i> Detail
                </a>
              </td>
            </tr>
          <?php
            endwhile;
          else:
          ?>
            <tr>
              <td colspan="11">
                <div class="empty-state">
                  <i class="fas fa-search"></i>
                  <p>Tidak ada data transaksi ditemukan.</p>
                  <?php if (!empty($tanggalFilter) || !empty($bulanFilter)): ?>
                    <a href="data_transaksi.php" class="btn-reset">
                      Reset Filter
                    </a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
      
      <?php if ($total_rows > 0): ?>
      <div class="pagination-container">
        <ul class="pagination">
          <?php if ($page > 1): ?>
            <li class="page-item">
              <a class="page-link" href="?page=1&sort=<?= $sortColumn ?>&order=<?= $sortOrder ?><?= !empty($tanggalFilter) ? '&tanggal='.urlencode($tanggalFilter) : '' ?><?= !empty($bulanFilter) ? '&bulan='.urlencode($bulanFilter) : '' ?>" aria-label="First">
                <span aria-hidden="true"><i class="fas fa-angle-double-left"></i></span>
              </a>
            </li>
            <li class="page-item">
              <a class="page-link" href="?page=<?= $page - 1 ?>&sort=<?= $sortColumn ?>&order=<?= $sortOrder ?><?= !empty($tanggalFilter) ? '&tanggal='.urlencode($tanggalFilter) : '' ?><?= !empty($bulanFilter) ? '&bulan='.urlencode($bulanFilter) : '' ?>" aria-label="Previous">
                <span aria-hidden="true"><i class="fas fa-angle-left"></i></span>
              </a>
            </li>
          <?php endif; ?>
          
          <?php
          // Display page numbers with logic to show limited numbers
          $start_page = max(1, min($page - 2, $total_pages - 4));
          $end_page = min($total_pages, max(5, $page + 2));
          
          for ($i = $start_page; $i <= $end_page; $i++): 
            $active = ($i == $page) ? 'active' : '';
          ?>
            <li class="page-item <?= $active ?>">
              <a class="page-link" href="?page=<?= $i ?>&sort=<?= $sortColumn ?>&order=<?= $sortOrder ?><?= !empty($tanggalFilter) ? '&tanggal='.urlencode($tanggalFilter) : '' ?><?= !empty($bulanFilter) ? '&bulan='.urlencode($bulanFilter) : '' ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
          
          <?php if ($page < $total_pages): ?>
            <li class="page-item">
              <a class="page-link" href="?page=<?= $page + 1 ?>&sort=<?= $sortColumn ?>&order=<?= $sortOrder ?><?= !empty($tanggalFilter) ? '&tanggal='.urlencode($tanggalFilter) : '' ?><?= !empty($bulanFilter) ? '&bulan='.urlencode($bulanFilter) : '' ?>" aria-label="Next">
                <span aria-hidden="true"><i class="fas fa-angle-right"></i></span>
              </a>
            </li>
            <li class="page-item">
              <a class="page-link" href="?page=<?= $total_pages ?>&sort=<?= $sortColumn ?>&order=<?= $sortOrder ?><?= !empty($tanggalFilter) ? '&tanggal='.urlencode($tanggalFilter) : '' ?><?= !empty($bulanFilter) ? '&bulan='.urlencode($bulanFilter) : '' ?>" aria-label="Last">
                <span aria-hidden="true"><i class="fas fa-angle-double-right"></i></span>
              </a>
            </li>
          <?php endif; ?>
        </ul>
        
        <div class="pagination-info">
          Menampilkan <?= $offset + 1 ?>-<?= min($offset + $records_per_page, $total_rows) ?> dari <?= $total_rows ?> data
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Bootstrap Script -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>