<?php
session_start();

// Check if logged in and manajer
if (!isset($_SESSION['manajer']['logged_in']) || $_SESSION['manajer']['logged_in'] !== true) {
  header("Location: ../login.php");
  exit();
}

include '../config.php'; // Database connection

// Konfigurasi pagination
$per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $per_page;

// Default bulan adalah bulan ini
$bulan_ini = date('Y-m');
$bulan_filter = isset($_GET['bulan']) ? $_GET['bulan'] : $bulan_ini;

// Process add pengeluaran form for regular expenses
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $kategori = mysqli_real_escape_string($conn, $_POST['kategori']);
    $jumlah = mysqli_real_escape_string($conn, $_POST['jumlah']);
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
    $tanggal = mysqli_real_escape_string($conn, $_POST['tanggal']);
    $bulan = date('Y-m', strtotime($tanggal));
    $created_by = $_SESSION['manajer']['manajer'];

    // Insert pengeluaran
    $query = "INSERT INTO pengeluaran (kategori, jumlah, keterangan, tanggal, bulan, created_by) 
              VALUES ('$kategori', '$jumlah', '$keterangan', '$tanggal', '$bulan', '$created_by')";
    
    if (mysqli_query($conn, $query)) {
        $_SESSION['message'] = "Pengeluaran berhasil ditambahkan!";
        $_SESSION['alert_type'] = "success";
    } else {
        $_SESSION['message'] = "Gagal menambahkan pengeluaran: " . mysqli_error($conn);
        $_SESSION['alert_type'] = "danger";
    }
    
    header("Location: " . $_SERVER['PHP_SELF'] . "?bulan=" . $bulan_filter);
    exit();
}

// Process add gaji karyawan 
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_gaji') {
  $id_karyawan = mysqli_real_escape_string($conn, $_POST['id_karyawan']);
  $nama_karyawan = mysqli_real_escape_string($conn, $_POST['nama_karyawan']);
  $gaji_asli = mysqli_real_escape_string($conn, $_POST['gaji_asli']);
  $kasbon = mysqli_real_escape_string($conn, $_POST['kasbon']);
  $gaji_dibayarkan = $gaji_asli - $kasbon;
  $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
  $tanggal = mysqli_real_escape_string($conn, $_POST['tanggal']);
  $bulan = date('Y-m', strtotime($tanggal));
  $created_by = $_SESSION['manajer']['manajer'];

  // Begin transaction
  mysqli_begin_transaction($conn);
  
  try {
      // Use gaji_asli as expense amount for accurate calculation
      $query = "INSERT INTO pengeluaran (kategori, jumlah, keterangan, tanggal, bulan, created_by) 
                VALUES ('Gaji Karyawan', '$gaji_asli', 'Gaji $nama_karyawan: $keterangan (Gaji Asli: Rp " . number_format($gaji_asli, 0, ',', '.') . " - Kasbon: Rp " . number_format($kasbon, 0, ',', '.') . ")', '$tanggal', '$bulan', '$created_by')";
      
      mysqli_query($conn, $query);
      
      // Delete existing kasbon records for this karyawan
      if ($kasbon > 0) {
          $delete_kasbon_query = "DELETE FROM pengeluaran 
                                 WHERE kategori = 'Kasbon Karyawan' 
                                 AND keterangan LIKE '%[ID_KARYAWAN:$id_karyawan]%'";
          mysqli_query($conn, $delete_kasbon_query);
      }
      
      // Commit transaction
      mysqli_commit($conn);
      
      $_SESSION['message'] = "Pembayaran gaji karyawan berhasil ditambahkan dan kasbon direset!";
      $_SESSION['alert_type'] = "success";
  } catch (Exception $e) {
      // Rollback in case of error
      mysqli_rollback($conn);
      
      $_SESSION['message'] = "Gagal menambahkan pembayaran gaji: " . $e->getMessage();
      $_SESSION['alert_type'] = "danger";
  }
  
  header("Location: " . $_SERVER['PHP_SELF'] . "?bulan=" . $bulan_filter . "&tab=karyawan");
  exit();
}

// Process add kasbon karyawan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_kasbon') {
  $id_karyawan = mysqli_real_escape_string($conn, $_POST['id_karyawan']);
  $nama_karyawan = mysqli_real_escape_string($conn, $_POST['nama_karyawan']);
  $jumlah = mysqli_real_escape_string($conn, $_POST['jumlah']);
  $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
  $tanggal = mysqli_real_escape_string($conn, $_POST['tanggal']);
  $bulan = date('Y-m', strtotime($tanggal));
  $created_by = $_SESSION['manajer']['manajer'];

  // Insert kasbon as an expense
  $query = "INSERT INTO pengeluaran (kategori, jumlah, keterangan, tanggal, bulan, created_by) 
            VALUES ('Kasbon Karyawan', '$jumlah', 'Kasbon untuk $nama_karyawan: $keterangan [ID_KARYAWAN:$id_karyawan]', '$tanggal', '$bulan', '$created_by')";
  
  if (mysqli_query($conn, $query)) {
      $_SESSION['message'] = "Kasbon karyawan berhasil ditambahkan!";
      $_SESSION['alert_type'] = "success";
  } else {
      $_SESSION['message'] = "Gagal menambahkan kasbon: " . mysqli_error($conn);
      $_SESSION['alert_type'] = "danger";
  }
  
  header("Location: " . $_SERVER['PHP_SELF'] . "?bulan=" . $bulan_filter . "&tab=karyawan");
  exit();
}

// Process add uang makan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_makan') {
  $id_karyawan = mysqli_real_escape_string($conn, $_POST['id_karyawan']);
  $nama_karyawan = mysqli_real_escape_string($conn, $_POST['nama_karyawan']);
  $jumlah = mysqli_real_escape_string($conn, $_POST['jumlah']);
  $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
  $tanggal = mysqli_real_escape_string($conn, $_POST['tanggal']);
  $bulan = date('Y-m', strtotime($tanggal));
  $created_by = $_SESSION['manajer']['manajer'];

  // Insert uang makan as an expense 
  $query = "INSERT INTO pengeluaran (kategori, jumlah, keterangan, tanggal, bulan, created_by) 
            VALUES ('Uang Makan', '$jumlah', 'Uang Makan untuk $nama_karyawan: $keterangan [ID_KARYAWAN:$id_karyawan]', '$tanggal', '$bulan', '$created_by')";
  
  if (mysqli_query($conn, $query)) {
      $_SESSION['message'] = "Uang makan karyawan berhasil ditambahkan!";
      $_SESSION['alert_type'] = "success";
  } else {
      $_SESSION['message'] = "Gagal menambahkan uang makan: " . mysqli_error($conn);
      $_SESSION['alert_type'] = "danger";
  }
  
  header("Location: " . $_SERVER['PHP_SELF'] . "?bulan=" . $bulan_filter . "&tab=karyawan");
  exit();
}

// Process add pembelian produk
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_pembelian') {
  $nama_produk = mysqli_real_escape_string($conn, $_POST['nama_produk']);
  $jumlah = mysqli_real_escape_string($conn, $_POST['jumlah']);
  $harga_satuan = mysqli_real_escape_string($conn, $_POST['harga_satuan']);
  $jumlah_item = mysqli_real_escape_string($conn, $_POST['jumlah_item']);
  $supplier = mysqli_real_escape_string($conn, $_POST['supplier']);
  $metode_pembayaran = mysqli_real_escape_string($conn, $_POST['metode_pembayaran']);
  $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
  $tanggal = mysqli_real_escape_string($conn, $_POST['tanggal']);
  $bulan = date('Y-m', strtotime($tanggal));
  $created_by = $_SESSION['manajer']['manajer'];
  
  // Create detailed description
  $detail_keterangan = "Pembelian produk baru: {$nama_produk} ({$jumlah_item}x) - ";
  if (!empty($supplier)) {
      $detail_keterangan .= "supplier: {$supplier} - ";
  }
  $detail_keterangan .= "{$metode_pembayaran}";
  
  if (!empty($keterangan)) {
      $detail_keterangan .= " - {$keterangan}";
  }

  // Insert pembelian into pengeluaran table
  $query = "INSERT INTO pengeluaran (kategori, jumlah, keterangan, tanggal, bulan, created_by) 
            VALUES ('Pembelian Sparepart', '$jumlah', '$detail_keterangan', '$tanggal', '$bulan', '$created_by')";
  
  if (mysqli_query($conn, $query)) {
      $_SESSION['message'] = "Pembelian produk berhasil dicatat!";
      $_SESSION['alert_type'] = "success";
  } else {
      $_SESSION['message'] = "Gagal mencatat pembelian: " . mysqli_error($conn);
      $_SESSION['alert_type'] = "danger";
  }
  
  header("Location: " . $_SERVER['PHP_SELF'] . "?bulan=" . $bulan_filter . "&tab=produk");
  exit();
}

// Process delete pengeluaran
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete']);
    
    // Delete pengeluaran
    $delete_query = "DELETE FROM pengeluaran WHERE id = '$id'";
    if (mysqli_query($conn, $delete_query)) {
        $_SESSION['message'] = "Pengeluaran berhasil dihapus!";
        $_SESSION['alert_type'] = "success";
    } else {
        $_SESSION['message'] = "Gagal menghapus pengeluaran: " . mysqli_error($conn);
        $_SESSION['alert_type'] = "danger";
    }
    
    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'operasional';
    header("Location: " . $_SERVER['PHP_SELF'] . "?bulan=" . $bulan_filter . "&tab=" . $tab);
    exit();
}

// Get all karyawan data for dropdown
$karyawan_query = "SELECT id_karyawan, nama FROM karyawan ORDER BY nama ASC";
$karyawan_result = mysqli_query($conn, $karyawan_query);
$karyawan_list = [];
while ($row = mysqli_fetch_assoc($karyawan_result)) {
    $karyawan_list[] = $row;
}

// Get total kasbon per karyawan
$kasbon_query = "SELECT 
                SUBSTRING_INDEX(SUBSTRING_INDEX(keterangan, '[ID_KARYAWAN:', -1), ']', 1) as id_karyawan,
                SUM(jumlah) as total_kasbon
                FROM pengeluaran 
                WHERE kategori = 'Kasbon Karyawan'
                AND bulan = '$bulan_filter'
                AND keterangan LIKE '%[ID_KARYAWAN:%'
                GROUP BY id_karyawan";
$kasbon_result = mysqli_query($conn, $kasbon_query);
$kasbon_list = [];
if ($kasbon_result) {
    while ($row = mysqli_fetch_assoc($kasbon_result)) {
        $kasbon_list[$row['id_karyawan']] = $row['total_kasbon'];
    }
}

// Get summary data
$summary_query = "SELECT 
                  SUM(jumlah) as total_pengeluaran,
                  SUM(CASE WHEN kategori = 'Sewa Lahan' THEN jumlah ELSE 0 END) as sewa_lahan,
                  SUM(CASE WHEN kategori = 'Token Listrik' THEN jumlah ELSE 0 END) as token_listrik,
                  SUM(CASE WHEN kategori = 'Kasbon Karyawan' THEN jumlah ELSE 0 END) as kasbon_karyawan,
                  SUM(CASE WHEN kategori = 'Uang Makan' THEN jumlah ELSE 0 END) as uang_makan,
                  SUM(CASE WHEN kategori = 'Gaji Karyawan' THEN jumlah ELSE 0 END) as gaji_karyawan,
                  SUM(CASE WHEN kategori = 'Lainnya' THEN jumlah ELSE 0 END) as lainnya,
                  SUM(CASE WHEN kategori IN ('Kasbon Karyawan', 'Uang Makan', 'Gaji Karyawan') THEN jumlah ELSE 0 END) as total_karyawan,
                  SUM(CASE WHEN kategori NOT IN ('Kasbon Karyawan', 'Uang Makan', 'Gaji Karyawan', 'Pembelian Sparepart', 'Pembelian Barang') 
                       AND keterangan NOT LIKE '%produk:%' 
                       AND keterangan NOT LIKE '%produk baru:%' 
                       AND keterangan NOT LIKE 'Penambahan stok produk:%' 
                       THEN jumlah ELSE 0 END) as total_operasional,
                  SUM(CASE WHEN kategori IN ('Pembelian Sparepart', 'Pembelian Barang') OR keterangan LIKE '%produk:%' OR keterangan LIKE '%produk baru:%' OR keterangan LIKE 'Penambahan stok produk:%' THEN jumlah ELSE 0 END) as total_produk
                  FROM pengeluaran
                  WHERE bulan = '$bulan_filter'";
$summary_result = mysqli_query($conn, $summary_query);
$summary_data = mysqli_fetch_assoc($summary_result);

// Get summary data for produk with improved query
$produk_summary_query = "SELECT 
                        SUM(CASE WHEN (kategori = 'Pembelian Sparepart' OR kategori = 'Pembelian Barang' OR 
                            keterangan LIKE '%produk:%' OR keterangan LIKE '%produk baru:%') 
                            AND keterangan NOT LIKE '%kredit%' 
                            AND keterangan NOT LIKE '%hutang%' 
                            AND keterangan NOT LIKE '%tempo%' 
                            THEN jumlah ELSE 0 END) as total_pembelian_cash,
                        SUM(CASE WHEN (kategori = 'Pembelian Sparepart' OR kategori = 'Pembelian Barang' OR 
                            keterangan LIKE '%produk:%' OR keterangan LIKE '%produk baru:%') 
                            AND (keterangan LIKE '%kredit%' 
                            OR keterangan LIKE '%hutang%' 
                            OR keterangan LIKE '%tempo%') 
                            THEN jumlah ELSE 0 END) as total_pembelian_kredit,
                        SUM(CASE WHEN keterangan LIKE 'Penambahan stok produk:%' THEN jumlah ELSE 0 END) as total_stok,
                        COUNT(CASE WHEN kategori = 'Pembelian Sparepart' OR kategori = 'Pembelian Barang' OR 
                            keterangan LIKE 'Pembelian produk:%' OR keterangan LIKE 'Pembelian produk baru:%' THEN 1 END) as jumlah_pembelian,
                        COUNT(CASE WHEN keterangan LIKE 'Penambahan stok produk:%' THEN 1 END) as jumlah_tambah_stok
                        FROM pengeluaran
                        WHERE bulan = '$bulan_filter'";
$produk_summary_result = mysqli_query($conn, $produk_summary_query);
$produk_summary = mysqli_fetch_assoc($produk_summary_result);

// Get operational expenses
$operational_query = "SELECT p.*, 
                    CASE 
                      WHEN p.created_by IS NOT NULL THEN m.nama 
                      ELSE 'Unknown' 
                    END as created_by_name
                    FROM pengeluaran p
                    LEFT JOIN manajer m ON p.created_by = m.id_manajer
                    WHERE p.bulan = '$bulan_filter' 
                    AND p.kategori NOT IN ('Kasbon Karyawan', 'Uang Makan', 'Gaji Karyawan', 'Pembelian Sparepart', 'Pembelian Barang')
                    AND p.keterangan NOT LIKE '%produk:%'
                    AND p.keterangan NOT LIKE '%produk baru:%'
                    AND p.keterangan NOT LIKE 'Penambahan stok produk:%'
                    ORDER BY p.tanggal DESC, p.created_at DESC
                    LIMIT $start, $per_page";
$operational_result = mysqli_query($conn, $operational_query);

// Get employee related expenses
$employee_query = "SELECT p.*, 
                 CASE 
                   WHEN p.created_by IS NOT NULL THEN m.nama 
                   ELSE 'Unknown' 
                 END as created_by_name
                 FROM pengeluaran p
                 LEFT JOIN manajer m ON p.created_by = m.id_manajer
                 WHERE p.bulan = '$bulan_filter' 
                 AND p.kategori IN ('Kasbon Karyawan', 'Uang Makan', 'Gaji Karyawan')
                 ORDER BY p.tanggal DESC, p.created_at DESC
                 LIMIT $start, $per_page";
$employee_result = mysqli_query($conn, $employee_query);

// Count total rows for pagination
$count_operational_query = "SELECT COUNT(*) as total FROM pengeluaran 
                          WHERE bulan = '$bulan_filter' 
                          AND kategori NOT IN ('Kasbon Karyawan', 'Uang Makan', 'Gaji Karyawan', 'Pembelian Sparepart')
                          AND keterangan NOT LIKE '%produk:%'";
$count_operational_result = mysqli_query($conn, $count_operational_query);
$count_operational_data = mysqli_fetch_assoc($count_operational_result);
$total_operational_pages = ceil($count_operational_data['total'] / $per_page);

$count_employee_query = "SELECT COUNT(*) as total FROM pengeluaran 
                       WHERE bulan = '$bulan_filter' 
                       AND kategori IN ('Kasbon Karyawan', 'Uang Makan', 'Gaji Karyawan')";
$count_employee_result = mysqli_query($conn, $count_employee_query);
$count_employee_data = mysqli_fetch_assoc($count_employee_result);
$total_employee_pages = ceil($count_employee_data['total'] / $per_page);

// Get available months for filter
$months_query = "SELECT DISTINCT bulan FROM pengeluaran ORDER BY bulan DESC";
$months_result = mysqli_query($conn, $months_query);
$months = [];
while ($row = mysqli_fetch_assoc($months_result)) {
    $months[] = $row['bulan'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pengeluaran - BMS Bengkel</title>
  <!-- CSS links -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      /* Manajer orange theme */
      --primary-orange: #EF6C00;
      --secondary-orange: #F59E0B;
      --light-orange: #FFF3E0;
      --accent-orange: #D84315;
      
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
    
    /* Content area */
    .content {
      margin-left: 280px;
      transition: margin-left 0.3s ease;
      min-height: 100vh;
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
      margin-bottom: 1.5rem;
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
    
    .stats-card-blue {
      border-left-color: #2196F3;
    }

    .stats-card-red {
        border-left-color: #F44336;
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
      border-collapse: separate;
      border-spacing: 0;
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
    
    /* Tab styling */
    .nav-tabs {
      border-bottom: 2px solid var(--light-orange);
      margin-bottom: 1.5rem;
    }

    .nav-tabs .nav-link {
      border: none;
      color: #6c757d;
      font-weight: 500;
      padding: 0.75rem 1.5rem;
      border-radius: 8px 8px 0 0;
      transition: all 0.3s ease;
    }

    .nav-tabs .nav-link.active {
      color: var(--white); 
      background: linear-gradient(135deg, var(--primary-orange), var(--secondary-orange)); 
      font-weight: 600;
    }

    .nav-tabs .nav-link:hover:not(.active) {
      background-color: #f8f9fa;
      color: var(--primary-orange);
    }
    
    /* Filter controls styling */
    .filter-controls {
      margin-bottom: 1.5rem;
    }
    
    .month-badge {
      background: var(--light-orange);
      color: var(--primary-orange);
      font-weight: 600;
      padding: 0.75rem 1.25rem;
      border-radius: 10px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    /* Additional styles for kasbon details */
    .kasbon-badge {
      padding: 0.5rem 0.75rem;
      border-radius: 8px;
      font-size: 0.85rem;
      font-weight: 600;
    }
    
    .highlight-info {
      background-color: #E8F0FE;
      border-radius: 8px;
      padding: 10px 15px;
      border-left: 4px solid #4285F4;
    }
    
    /* Extended info section for expenses */
    .expense-details {
      background-color: rgba(239, 108, 0, 0.05);
      border-radius: 8px;
      padding: 10px;
      margin-top: 5px;
      font-size: 0.85rem;
    }
    
    .kasbon-summary {
      padding: 0.5rem;
      border-radius: 8px;
      background-color: #FFF9C4;
      border-left: 3px solid #FFC107;
      font-size: 0.9rem;
    }
    
    .kasbon-info {
      font-size: 0.8rem;
      color: #2C3E50;
      margin-top: 4px;
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
      
      .stats-icon-container {
        margin-right: 0;
        margin-bottom: 10px;
      }
      
      .month-filter {
        flex-direction: column;
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
          <i class="fas fa-money-bill-wave me-2"></i>
          Pengeluaran
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
            <h1><i class="fas fa-money-bill-wave me-2"></i>Pengeluaran</h1>
            <p class="lead mb-0">Manajemen data pengeluaran bengkel</p>
          </div>
          <button type="button" class="btn btn-light btn-lg" data-bs-toggle="modal" data-bs-target="#addPengeluaranModal">
            <i class="fas fa-plus me-2"></i>
            Tambah Pengeluaran
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
        ?>
      <?php endif; ?>

      <!-- Filter Controls -->
      <div class="filter-controls">
        <div class="d-flex gap-2 justify-content-between align-items-center">
          <div class="month-badge">
            <i class="fas fa-calendar-alt"></i>
            <span>Filter Bulan: <?= date('F Y', strtotime($bulan_filter . '-01')) ?></span>
          </div>
          
          <div class="d-flex gap-2">
            <div class="input-group" style="max-width: 400px;">
              <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
              <input type="month" id="bulanTahunFilter" class="form-control" value="<?= $bulan_filter ?>" 
                     onchange="filterByMonth(this.value)">
              <button class="btn btn-outline-secondary" onclick="setCurrentMonth()">
                <i class="fas fa-calendar-day me-1"></i> Bulan Ini
              </button>
              <button class="btn btn-outline-secondary" onclick="setPrevMonth()">
                <i class="fas fa-chevron-left"></i>
              </button>
              <button class="btn btn-outline-secondary" onclick="setNextMonth()">
                <i class="fas fa-chevron-right"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
      
<!-- Summary Cards - Horizontal Layout -->
<div class="row g-4 mb-4">
  <div class="col-md-3">
    <div class="stats-card-wrapper stats-card-green">
      <div class="stats-card">
        <div class="stats-icon-container">
          <i class="fas fa-tools stats-icon text-success"></i>
        </div>
        <div class="stats-content">
          <h3>Rp <?= number_format($summary_data['total_operasional'] ?? 0, 0, ',', '.') ?></h3>
          <p>Operasional</p>
        </div>
      </div>
    </div>
  </div>
  
  <div class="col-md-3">
    <div class="stats-card-wrapper stats-card-orange">
      <div class="stats-card">
        <div class="stats-icon-container stats-icon-products">
          <i class="fas fa-users stats-icon"></i>
        </div>
        <div class="stats-content">
          <h3>Rp <?= number_format($summary_data['total_karyawan'] ?? 0, 0, ',', '.') ?></h3>
          <p>Karyawan</p>
        </div>
      </div>
    </div>
  </div>
  
  <div class="col-md-3">
    <div class="stats-card-wrapper stats-card-blue">
      <div class="stats-card">
        <div class="stats-icon-container">
          <i class="fas fa-shopping-cart stats-icon text-primary"></i>
        </div>
        <div class="stats-content">
          <h3>Rp <?= number_format(($produk_summary['total_pembelian_cash'] ?? 0) + ($produk_summary['total_pembelian_kredit'] ?? 0), 0, ',', '.') ?></h3>
          <p>Produk</p>
        </div>
      </div>
    </div>
  </div>
  
  <div class="col-md-3">
    <div class="stats-card-wrapper stats-card-red" style="border-left-color: #FF5722;">
      <div class="stats-card">
        <div class="stats-icon-container" style="background-color: #FFCCBC;">
          <i class="fas fa-money-bill-alt stats-icon" style="color: #FF5722;"></i>
        </div>
        <div class="stats-content">
          <h3>Rp <?= number_format($summary_data['total_pengeluaran'] ?? 0, 0, ',', '.') ?></h3>
          <p>Total Pengeluaran</p>
          <p style="font-size: 0.8rem;"><?= date('F Y', strtotime($bulan_filter . '-01')) ?></p>
        </div>
      </div>
    </div>
  </div>
</div>
      
      
      <!-- Tabs for Pengeluaran -->
      <ul class="nav nav-tabs" id="pengeluaranTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link <?= (!isset($_GET['tab']) || $_GET['tab'] == 'operasional') ? 'active' : '' ?>" 
                  id="operasional-tab" data-bs-toggle="tab" data-bs-target="#operasional" 
                  type="button" role="tab">
            <i class="fas fa-tools me-2"></i> Pengeluaran Operasional
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link <?= (isset($_GET['tab']) && $_GET['tab'] == 'karyawan') ? 'active' : '' ?>" 
                  id="karyawan-tab" data-bs-toggle="tab" data-bs-target="#karyawan" 
                  type="button" role="tab">
            <i class="fas fa-users me-2"></i> Pengeluaran Karyawan
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link <?= (isset($_GET['tab']) && $_GET['tab'] == 'produk') ? 'active' : '' ?>" 
                  id="produk-tab" data-bs-toggle="tab" data-bs-target="#produk" 
                  type="button" role="tab">
            <i class="fas fa-shopping-cart me-2"></i> Pengeluaran Produk (Sparepart)
          </button>
        </li>
      </ul>

      <!-- Tab Content -->
      <div class="tab-content" id="pengeluaranTabContent">
        <!-- Tab Pengeluaran Operasional -->
        <div class="tab-pane fade <?= (!isset($_GET['tab']) || $_GET['tab'] == 'operasional') ? 'show active' : '' ?>" 
             id="operasional" role="tabpanel" tabindex="0">
          <div class="data-card mb-4">
            <div class="card-header-actions">
              <h5 class="card-title">
                <i class="fas fa-tools me-2"></i>
                Daftar Pengeluaran Operasional
              </h5>
            </div>
            
            <div class="table-responsive">
              <table class="table table-hover" id="operasionalTable">
                <thead>
                  <tr>
                    <th width="5%">#</th>
                    <th width="15%">Tanggal</th>
                    <th width="15%">Kategori</th>
                    <th width="15%">Jumlah</th>
                    <th width="40%">Keterangan</th>
                    <th width="10%">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                  $no = $start + 1;
                  if (mysqli_num_rows($operational_result) > 0):
                    while ($row = mysqli_fetch_assoc($operational_result)):
                  ?>
                  <tr>
                    <td><?= $no++ ?></td>
                    <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                    <td><span class="badge bg-primary"><?= $row['kategori'] ?></span></td>
                    <td>Rp <?= number_format($row['jumlah'], 0, ',', '.') ?></td>
                    <td><?= nl2br(htmlspecialchars($row['keterangan'])) ?></td>
                    <td>
                      <button type="button" class="btn btn-sm btn-danger btn-action" 
                              onclick="showDeleteModal('<?= $row['id'] ?>', '<?= $row['kategori'] ?>', '<?= number_format($row['jumlah'], 0, ',', '.') ?>', 'operasional')">
                        <i class="fas fa-trash-alt"></i>
                      </button>
                    </td>
                  </tr>
                  <?php 
                    endwhile;
                  else:
                  ?>
                  <tr>
                    <td colspan="6" class="text-center py-4">Tidak ada data pengeluaran operasional pada bulan ini</td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            
            <!-- Pagination - Operasional -->
            <?php if ($total_operational_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
              <ul class="pagination">
                <?php if ($page > 1): ?>
                <li class="page-item">
                  <a class="page-link" href="?page=<?= $page-1 ?>&bulan=<?= $bulan_filter ?>&tab=operasional" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                  </a>
                </li>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_operational_pages; $i++): ?>
                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                  <a class="page-link" href="?page=<?= $i ?>&bulan=<?= $bulan_filter ?>&tab=operasional"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                
                <?php if ($page < $total_operational_pages): ?>
                <li class="page-item">
                  <a class="page-link" href="?page=<?= $page+1 ?>&bulan=<?= $bulan_filter ?>&tab=operasional" aria-label="Next">
                    <span aria-hidden="true">&raquo;</span>
                  </a>
                </li>
                <?php endif; ?>
              </ul>
            </nav>
            <?php endif; ?>
          </div>
          
          <!-- Operasional Category Breakdown -->
          <div class="row g-4 mb-4">
            <div class="col-12">
              <div class="data-card">
                <div class="card-header-actions">
                  <h5 class="card-title">
                    <i class="fas fa-chart-bar me-2"></i>
                    Rincian Pengeluaran Operasional Per Kategori
                  </h5>
                </div>
                
                <div class="row g-4">
                  <div class="col-md-4 col-sm-6">
                    <div class="border rounded p-3">
                      <h6 class="text-primary mb-2"><i class="fas fa-building me-2"></i> Sewa Lahan</h6>
                      <h4>Rp <?= number_format($summary_data['sewa_lahan'] ?? 0, 0, ',', '.') ?></h4>
                    </div>
                  </div>
                  
                  <div class="col-md-4 col-sm-6">
                    <div class="border rounded p-3">
                      <h6 class="text-primary mb-2"><i class="fas fa-bolt me-2"></i> Token Listrik</h6>
                      <h4>Rp <?= number_format($summary_data['token_listrik'] ?? 0, 0, ',', '.') ?></h4>
                    </div>
                  </div>
                  
                  <div class="col-md-4 col-sm-6">
                    <div class="border rounded p-3">
                      <h6 class="text-primary mb-2"><i class="fas fa-tags me-2"></i> Lainnya</h6>
                      <h4>Rp <?= number_format($summary_data['lainnya'] ?? 0, 0, ',', '.') ?></h4>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Tab Pengeluaran Karyawan -->
        <div class="tab-pane fade <?= (isset($_GET['tab']) && $_GET['tab'] == 'karyawan') ? 'show active' : '' ?>" 
             id="karyawan" role="tabpanel" tabindex="0">
          <div class="data-card mb-4">
            <div class="card-header-actions">
              <h5 class="card-title">
                <i class="fas fa-users me-2"></i>
                Daftar Pengeluaran Karyawan
              </h5>
              <div>
                <button type="button" class="btn btn-info me-2" data-bs-toggle="modal" data-bs-target="#addMakanModal">
                  <i class="fas fa-utensils me-1"></i> Tambah Uang Makan
                </button>
                <button type="button" class="btn btn-warning me-2" data-bs-toggle="modal" data-bs-target="#addKasbonModal">
                  <i class="fas fa-money-bill-wave me-1"></i> Tambah Kasbon
                </button>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGajiModal">
                  <i class="fas fa-hand-holding-usd me-1"></i> Bayar Gaji
                </button>
              </div>
            </div>
            
            <div class="table-responsive">
              <table class="table table-hover" id="karyawanTable">
                <thead>
                  <tr>
                    <th width="5%">#</th>
                    <th width="15%">Tanggal</th>
                    <th width="15%">Kategori</th>
                    <th width="15%">Jumlah</th>
                    <th width="30%">Keterangan</th>
                    <th width="10%">Dibuat Oleh</th>
                    <th width="10%">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                  $no = $start + 1;
                  if (mysqli_num_rows($employee_result) > 0):
                    while ($row = mysqli_fetch_assoc($employee_result)):
                      $badge_class = ($row['kategori'] == 'Kasbon Karyawan') ? 'bg-warning text-dark' : 
                                     (($row['kategori'] == 'Gaji Karyawan') ? 'bg-success' : 'bg-info');
                  ?>
                  <tr>
                    <td><?= $no++ ?></td>
                    <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                    <td><span class="badge <?= $badge_class ?>"><?= $row['kategori'] ?></span></td>
                    <td>Rp <?= number_format($row['jumlah'], 0, ',', '.') ?></td>
                    <td>
                      <?= nl2br(htmlspecialchars($row['keterangan'])) ?>
                        <?php if (strpos($row['keterangan'], 'Gaji Asli') !== false): ?>
                        <div class="expense-details">
                          <?php
                            preg_match('/Gaji Asli: Rp ([0-9.,]+) - Kasbon: Rp ([0-9.,]+)/', $row['keterangan'], $matches);
                            if (count($matches) >= 3):
                              // Hitung gaji bersih: gaji asli dikurangi kasbon
                              $gaji_asli = str_replace('.', '', $matches[1]);
                              $kasbon = str_replace('.', '', $matches[2]);
                              $gaji_bersih = $gaji_asli - $kasbon;
                          ?>
                          <div><strong>Detail Penggajian:</strong></div>
                          <div>💰 Gaji Asli: Rp <?= $matches[1] ?></div>
                          <div>📝 Kasbon: Rp <?= $matches[2] ?></div>
                          <div>💵 Dibayarkan: Rp <?= number_format($gaji_bersih, 0, ',', '.') ?></div>
                          <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td><?= $row['created_by_name'] ?></td>
                    <td>
                      <button type="button" class="btn btn-sm btn-danger btn-action" 
                              onclick="showDeleteModal('<?= $row['id'] ?>', '<?= $row['kategori'] ?>', '<?= number_format($row['jumlah'], 0, ',', '.') ?>', 'karyawan')">
                        <i class="fas fa-trash-alt"></i>
                      </button>
                    </td>
                  </tr>
                  <?php 
                    endwhile;
                  else:
                  ?>
                  <tr>
                    <td colspan="7" class="text-center py-4">Tidak ada data pengeluaran karyawan pada bulan ini</td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            
            <!-- Pagination - Karyawan -->
            <?php if ($total_employee_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
              <ul class="pagination">
                <?php if ($page > 1): ?>
                <li class="page-item">
                  <a class="page-link" href="?page=<?= $page-1 ?>&bulan=<?= $bulan_filter ?>&tab=karyawan" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                  </a>
                </li>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_employee_pages; $i++): ?>
                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                  <a class="page-link" href="?page=<?= $i ?>&bulan=<?= $bulan_filter ?>&tab=karyawan"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                
                <?php if ($page < $total_employee_pages): ?>
                <li class="page-item">
                  <a class="page-link" href="?page=<?= $page+1 ?>&bulan=<?= $bulan_filter ?>&tab=karyawan" aria-label="Next">
                    <span aria-hidden="true">&raquo;</span>
                  </a>
                </li>
                <?php endif; ?>
              </ul>
            </nav>
            <?php endif; ?>
          </div>
          
          <!-- Kasbon Summary -->
          <div class="data-card mb-4">
            <div class="card-header-actions">
              <h5 class="card-title">
                <i class="fas fa-money-bill-wave me-2"></i>
                Ringkasan Kasbon Karyawan
              </h5>
              <div class="alert alert-info mb-0">
                <i class="fas fa-info-circle me-2"></i>
                Kasbon akan dikurangkan dari gaji karyawan pada saat penggajian berikutnya
              </div>
            </div>
            
            <div class="row g-4 mt-3">
              <?php 
              if (count($kasbon_list) > 0):
                foreach ($kasbon_list as $id => $kasbon):
                  // Get karyawan name
                  $nama = '';
                  foreach ($karyawan_list as $karyawan) {
                    if ($karyawan['id_karyawan'] == $id) {
                      $nama = $karyawan['nama'];
                      break;
                    }
                  }
              ?>
              <div class="col-md-4 col-sm-6">
                <div class="kasbon-summary">
                  <div class="d-flex justify-content-between align-items-center">
                    <strong><?= htmlspecialchars($nama) ?></strong>
                    <span class="badge bg-warning text-dark">Kasbon Aktif</span>
                  </div>
                  <div class="fw-bold mt-2">Rp <?= number_format($kasbon, 0, ',', '.') ?></div>
                  <div class="kasbon-info">
                    <i class="fas fa-info-circle me-1"></i> Nilai ini akan dikurangi dari gaji berikutnya
                  </div>
                </div>
              </div>
              <?php 
                endforeach;
              else:
              ?>
              <div class="col-12">
                <div class="text-center py-4 text-muted">
                  <i class="fas fa-check-circle fa-2x mb-3"></i>
                  <h6>Tidak ada kasbon aktif saat ini</h6>
                  <p class="small">Semua karyawan tidak memiliki tanggungan kasbon</p>
                </div>
              </div>
              <?php endif; ?>
            </div>
          </div>
          
          <!-- Karyawan Category Breakdown -->
          <div class="row g-4 mb-4">
            <div class="col-12">
              <div class="data-card">
                <div class="card-header-actions">
                  <h5 class="card-title">
                    <i class="fas fa-chart-bar me-2"></i>
                    Rincian Pengeluaran Karyawan Per Kategori
                  </h5>
                </div>
                
                <div class="row g-4">
                  <div class="col-md-4 col-sm-6">
                    <div class="border rounded p-3">
                      <h6 class="text-warning mb-2"><i class="fas fa-money-bill-wave me-2"></i> Kasbon Karyawan</h6>
                      <h4>Rp <?= number_format($summary_data['kasbon_karyawan'] ?? 0, 0, ',', '.') ?></h4>
                    </div>
                  </div>
                  
                  <div class="col-md-4 col-sm-6">
                    <div class="border rounded p-3">
                      <h6 class="text-info mb-2"><i class="fas fa-utensils me-2"></i> Uang Makan</h6>
                      <h4>Rp <?= number_format($summary_data['uang_makan'] ?? 0, 0, ',', '.') ?></h4>
                    </div>
                  </div>
                  
                  <div class="col-md-4 col-sm-6">
                    <div class="border rounded p-3">
                      <h6 class="text-success mb-2"><i class="fas fa-hand-holding-usd me-2"></i> Gaji Karyawan</h6>
                      <h4>Rp <?= number_format($summary_data['gaji_karyawan'] ?? 0, 0, ',', '.') ?></h4>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Tab Pengeluaran Produk (Sparepart) -->
        <div class="tab-pane fade <?= (isset($_GET['tab']) && $_GET['tab'] == 'produk') ? 'show active' : '' ?>" 
             id="produk" role="tabpanel" tabindex="0">
          <div class="data-card mb-4">
            <div class="card-header-actions">
              <h5 class="card-title">
                <i class="fas fa-shopping-cart me-2"></i>
                Daftar Pengeluaran Produk (Sparepart)
              </h5>
            </div>
            
            <div class="table-responsive">
              <table class="table table-hover" id="produkTable">
                <thead>
                  <tr>
                    <th width="5%">#</th>
                    <th width="15%">Tanggal</th>
                    <th width="15%">Kategori</th>
                    <th width="20%">Jumlah</th>
                    <th width="35%">Keterangan</th>
                    <th width="10%">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                  // Get produk data
$produk_query = "SELECT p.*, 
                  CASE 
                    WHEN p.created_by IS NOT NULL THEN m.nama 
                    ELSE 'Unknown' 
                  END as created_by_name,
                  CASE 
                    WHEN p.keterangan LIKE 'Pembelian produk baru:%' THEN 'Pembelian Sparepart Baru'
                    WHEN p.keterangan LIKE '%produk baru:%' AND p.keterangan LIKE '%pembayaran awal%' THEN 'Pembelian Sparepart Baru'
                    WHEN p.keterangan LIKE 'Penambahan stok produk:%' THEN 'Tambah Stok'
                    ELSE 'Produk'
                  END as kategori_transaksi
                FROM pengeluaran p
                LEFT JOIN manajer m ON p.created_by = m.id_manajer
                WHERE p.bulan = '$bulan_filter' 
                AND (
                    p.keterangan LIKE '%produk baru:%' OR 
                    p.keterangan LIKE '%produk:%' OR
                    p.kategori = 'Pembelian Sparepart' OR 
                    p.kategori = 'Penambahan Stok' OR
                    p.kategori = 'Pembelian Barang'
                )
                ORDER BY p.tanggal DESC, p.created_at DESC
                LIMIT $start, $per_page";
                  $produk_result = mysqli_query($conn, $produk_query);
                  
                  $no = $start + 1;
                  if (mysqli_num_rows($produk_result) > 0):
                    while ($row = mysqli_fetch_assoc($produk_result)):
                      $badge_class = ($row['kategori_transaksi'] == 'Pembelian' || $row['kategori_transaksi'] == 'Pembelian Sparepart Baru') ? 'bg-success' : 'bg-info';
                  ?>
                  <tr>
                    <td><?= $no++ ?></td>
                    <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                    <td><span class="badge <?= $badge_class ?>"><?= $row['kategori_transaksi'] ?></span></td>
                    <td>Rp <?= number_format($row['jumlah'], 0, ',', '.') ?></td>
                    <td><?= nl2br(htmlspecialchars($row['keterangan'])) ?></td>
                    <td>
                      <button type="button" class="btn btn-sm btn-danger btn-action" 
                              onclick="showDeleteModal('<?= $row['id'] ?>', '<?= $row['kategori_transaksi'] ?>', '<?= number_format($row['jumlah'], 0, ',', '.') ?>', 'produk')">
                        <i class="fas fa-trash-alt"></i>
                      </button>
                    </td>
                  </tr>
                  <?php 
                    endwhile;
                  else:
                  ?>
                  <tr>
                    <td colspan="6" class="text-center py-4">Tidak ada data pengeluaran produk pada bulan ini</td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

            <!-- Pagination - Produk -->
            <?php 
// Get total count for pagination
$count_produk_query = "SELECT COUNT(*) as total FROM pengeluaran 
                     WHERE bulan = '$bulan_filter' 
                     AND (
                         keterangan LIKE '%produk baru:%' OR
                         keterangan LIKE '%produk:%' OR 
                         kategori = 'Pembelian Sparepart' OR 
                         kategori = 'Pembelian Barang' OR
                         kategori = 'Penambahan Stok'
                     )";
$count_produk_result = mysqli_query($conn, $count_produk_query);
$count_produk_data = mysqli_fetch_assoc($count_produk_result);
$total_produk_pages = ceil($count_produk_data['total'] / $per_page);

 if ($total_produk_pages > 1): ?>
<nav aria-label="Page navigation" class="mt-4">
  <ul class="pagination">
    <?php if ($page > 1): ?>
    <li class="page-item">
      <a class="page-link" href="?page=<?= $page-1 ?>&bulan=<?= $bulan_filter ?>&tab=produk" aria-label="Previous">
        <span aria-hidden="true">&laquo;</span>
      </a>
    </li>
    <?php endif; ?>
    
    <?php for ($i = 1; $i <= $total_produk_pages; $i++): ?>
    <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
      <a class="page-link" href="?page=<?= $i ?>&bulan=<?= $bulan_filter ?>&tab=produk"><?= $i ?></a>
    </li>
    <?php endfor; ?>
    
    <?php if ($page < $total_produk_pages): ?>
    <li class="page-item">
      <a class="page-link" href="?page=<?= $page+1 ?>&bulan=<?= $bulan_filter ?>&tab=produk" aria-label="Next">
        <span aria-hidden="true">&raquo;</span>
      </a>
    </li>
    <?php endif; ?>
  </ul>
</nav>
<?php endif; ?>
          </div> <!-- Penutup .table-responsive -->
        </div> <!-- Penutup .data-card -->
      </div> <!-- Penutup .tab-pane for #produk -->
    </div> <!-- Penutup .tab-content -->
  </div> <!-- Penutup .container-fluid -->
</div> <!-- Penutup .content -->

<!-- Add Regular Pengeluaran Modal -->
<div class="modal fade" id="addPengeluaranModal" tabindex="-1" aria-labelledby="addPengeluaranModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="addPengeluaranModalLabel">
            <i class="fas fa-plus-circle me-2"></i>
            Tambah Pengeluaran Baru
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form action="" method="POST">
          <div class="modal-body">
            <input type="hidden" name="action" value="add">
            
            <div class="mb-3">
              <label for="kategori" class="form-label">Kategori Pengeluaran</label>
              <select class="form-select" id="kategori" name="kategori" required>
                <option value="" selected disabled>Pilih Kategori</option>
                <optgroup label="Pengeluaran Operasional">
                  <option value="Sewa Lahan">Sewa Lahan</option>
                  <option value="Token Listrik">Token Listrik</option>
                  <option value="Lainnya">Lainnya</option>
                </optgroup>
              </select>
              <small class="form-text text-muted">
                <i class="fas fa-info-circle me-1"></i>
                Untuk Kasbon, Uang Makan atau Gaji Karyawan, gunakan menu khusus di tab Karyawan
              </small>
            </div>
            
            <div class="mb-3">
              <label for="jumlah" class="form-label">Jumlah (Rp)</label>
              <div class="input-group">
                <span class="input-group-text">Rp</span>
                <input type="number" class="form-control" id="jumlah" name="jumlah" placeholder="Masukkan jumlah pengeluaran" required>
              </div>
            </div>
            
            <div class="mb-3">
              <label for="tanggal" class="form-label">Tanggal</label>
              <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= date('Y-m-d') ?>" required>
            </div>
            
            <div class="mb-3">
              <label for="keterangan" class="form-label">Keterangan</label>
              <textarea class="form-control" id="keterangan" name="keterangan" rows="3" placeholder="Tambahkan keterangan atau detail untuk pengeluaran ini"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
              <i class="fas fa-times me-1"></i>
              Batal
            </button>
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save me-1"></i>
              Simpan Pengeluaran
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <!-- Add Kasbon Modal -->
  <div class="modal fade" id="addKasbonModal" tabindex="-1" aria-labelledby="addKasbonModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="addKasbonModalLabel">
            <i class="fas fa-money-bill-wave me-2"></i>
            Tambah Kasbon Karyawan
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form action="" method="POST">
          <div class="modal-body">
            <input type="hidden" name="action" value="add_kasbon">
            
            <div class="alert alert-info mb-3">
              <i class="fas fa-info-circle me-2"></i>
              Kasbon akan dicatat sebagai pengeluaran dan akan dikurangkan pada saat pembayaran gaji berikutnya.
            </div>
            
            <div class="mb-3">
              <label for="id_karyawan" class="form-label">Karyawan</label>
              <select class="form-select" id="id_karyawan" name="id_karyawan" required>
                <option value="" selected disabled>Pilih Karyawan</option>
                <?php foreach ($karyawan_list as $karyawan): ?>
                <option value="<?= $karyawan['id_karyawan'] ?>" data-nama="<?= htmlspecialchars($karyawan['nama']) ?>">
                  <?= htmlspecialchars($karyawan['nama']) ?>
                  <?php if (isset($kasbon_list[$karyawan['id_karyawan']])): ?>
                  (Kasbon Aktif: Rp <?= number_format($kasbon_list[$karyawan['id_karyawan']], 0, ',', '.') ?>)
                  <?php endif; ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            
            <input type="hidden" name="nama_karyawan" id="nama_karyawan">
            
            <div class="mb-3">
              <label for="jumlah" class="form-label">Jumlah Kasbon (Rp)</label>
              <div class="input-group">
                <span class="input-group-text">Rp</span>
                <input type="number" class="form-control" id="jumlah_kasbon" name="jumlah" placeholder="Masukkan jumlah kasbon" required>
              </div>
            </div>
            
            <div class="mb-3">
              <label for="tanggal" class="form-label">Tanggal</label>
              <input type="date" class="form-control" id="tanggal_kasbon" name="tanggal" value="<?= date('Y-m-d') ?>" required>
            </div>
            
            <div class="mb-3">
              <label for="keterangan" class="form-label">Keterangan</label>
              <textarea class="form-control" id="keterangan_kasbon" name="keterangan" rows="3" placeholder="Tambahkan alasan atau detail kasbon"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
              <i class="fas fa-times me-1"></i>
              Batal
            </button>
            <button type="submit" class="btn btn-warning">
              <i class="fas fa-save me-1"></i>
              Simpan Kasbon
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Add Uang Makan Modal -->
  <div class="modal fade" id="addMakanModal" tabindex="-1" aria-labelledby="addMakanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="addMakanModalLabel">
            <i class="fas fa-utensils me-2"></i>
            Tambah Uang Makan Karyawan
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form action="" method="POST">
          <div class="modal-body">
            <input type="hidden" name="action" value="add_makan">
            
            <div class="alert alert-info mb-3">
              <i class="fas fa-info-circle me-2"></i>
              Uang makan akan dicatat sebagai pengeluaran karyawan terpisah dan tidak mempengaruhi perhitungan gaji.
            </div>
            
            <div class="mb-3">
              <label for="id_karyawan_makan" class="form-label">Karyawan</label>
              <select class="form-select" id="id_karyawan_makan" name="id_karyawan" required>
                <option value="" selected disabled>Pilih Karyawan</option>
                <?php foreach ($karyawan_list as $karyawan): ?>
                <option value="<?= $karyawan['id_karyawan'] ?>" data-nama="<?= htmlspecialchars($karyawan['nama']) ?>">
                  <?= htmlspecialchars($karyawan['nama']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            
            <input type="hidden" name="nama_karyawan" id="nama_karyawan_makan">
            
            <div class="mb-3">
              <label for="jumlah_makan" class="form-label">Jumlah Uang Makan (Rp)</label>
              <div class="input-group">
                <span class="input-group-text">Rp</span>
                <input type="number" class="form-control" id="jumlah_makan" name="jumlah" placeholder="Masukkan jumlah uang makan" required>
              </div>
            </div>
            
            <div class="mb-3">
              <label for="tanggal_makan" class="form-label">Tanggal</label>
              <input type="date" class="form-control" id="tanggal_makan" name="tanggal" value="<?= date('Y-m-d') ?>" required>
            </div>
            
            <div class="mb-3">
              <label for="keterangan_makan" class="form-label">Keterangan</label>
              <textarea class="form-control" id="keterangan_makan" name="keterangan" rows="3" placeholder="Tambahkan keterangan uang makan (opsional)"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
              <i class="fas fa-times me-1"></i>
              Batal
            </button>
            <button type="submit" class="btn btn-info">
              <i class="fas fa-save me-1"></i>
              Simpan Uang Makan
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <!-- Add Gaji Modal -->
  <div class="modal fade" id="addGajiModal" tabindex="-1" aria-labelledby="addGajiModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="addGajiModalLabel">
            <i class="fas fa-hand-holding-usd me-2"></i>
            Bayar Gaji Karyawan
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form action="" method="POST">
          <div class="modal-body">
            <input type="hidden" name="action" value="add_gaji">
            
            <div class="alert alert-info mb-3">
              <i class="fas fa-info-circle me-2"></i>
              Sistem akan secara otomatis mengurangi kasbon dari gaji karyawan jika ada.
            </div>
            
            <div class="mb-3">
              <label for="id_karyawan_gaji" class="form-label">Karyawan</label>
              <select class="form-select" id="id_karyawan_gaji" name="id_karyawan" required onchange="updateKasbonInfo()">
                <option value="" selected disabled>Pilih Karyawan</option>
                <?php foreach ($karyawan_list as $karyawan): ?>
                <option value="<?= $karyawan['id_karyawan'] ?>" 
                        data-nama="<?= htmlspecialchars($karyawan['nama']) ?>"
                        data-kasbon="<?= isset($kasbon_list[$karyawan['id_karyawan']]) ? $kasbon_list[$karyawan['id_karyawan']] : 0 ?>">
                  <?= htmlspecialchars($karyawan['nama']) ?>
                  <?php if (isset($kasbon_list[$karyawan['id_karyawan']])): ?>
                  (Kasbon: Rp <?= number_format($kasbon_list[$karyawan['id_karyawan']], 0, ',', '.') ?>)
                  <?php endif; ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            
            <input type="hidden" name="nama_karyawan" id="nama_karyawan_gaji">
            <input type="hidden" name="kasbon" id="kasbon_karyawan_gaji" value="0">
            
            <div class="mb-3">
              <label for="gaji_asli" class="form-label">Gaji Kotor (Rp)</label>
              <div class="input-group">
                <span class="input-group-text">Rp</span>
                <input type="number" class="form-control" id="gaji_asli" name="gaji_asli" placeholder="Masukkan jumlah gaji kotor" required onchange="calculateFinalSalary()">
              </div>
            </div>
            
            <div id="kasbonInfoBox" class="mb-3 p-3 rounded" style="background-color: #FFF9C4; border-left: 3px solid #FFC107; display: none;">
              <div class="d-flex align-items-start">
                <i class="fas fa-exclamation-triangle text-warning me-2 mt-1"></i>
                <div>
                  <h6 class="fw-bold mb-1">Informasi Kasbon</h6>
                  <p class="mb-1">Karyawan ini memiliki kasbon sebesar <strong>Rp <span id="kasbonAmount">0</span></strong> yang akan dikurangkan dari gaji.</p>
                  <div>
                    <span class="fw-bold">Gaji Kotor:</span> Rp <span id="gaji_asli_display">0</span><br>
                    <span class="fw-bold">Kasbon:</span> Rp <span id="kasbon_display">0</span><br>
                    <span class="fw-bold">Gaji Bersih:</span> Rp <span id="gaji_dibayarkan">0</span>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="mb-3">
              <label for="tanggal_gaji" class="form-label">Tanggal Pembayaran</label>
              <input type="date" class="form-control" id="tanggal_gaji" name="tanggal" value="<?= date('Y-m-d') ?>" required>
            </div>
            
            <div class="mb-3">
              <label for="keterangan_gaji" class="form-label">Keterangan</label>
              <textarea class="form-control" id="keterangan_gaji" name="keterangan" rows="3" placeholder="Tambahkan keterangan (contoh: periode gaji)"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
              <i class="fas fa-times me-1"></i>
              Batal
            </button>
            <button type="submit" class="btn btn-success">
              <i class="fas fa-money-check-alt me-1"></i>
              Bayar Gaji
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <!-- Delete Confirmation Modal -->
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
          <p>Anda yakin ingin menghapus pengeluaran <span id="deleteCategoryName"></span> sebesar <strong>Rp <span id="deleteAmount"></span></strong>?</p>
          <p class="text-danger mb-0"><small>Tindakan ini tidak dapat dibatalkan.</small></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="fas fa-times me-1"></i>
            Batal
          </button>
          <a href="#" id="deleteConfirmButton" class="btn btn-danger">
            <i class="fas fa-trash-alt me-1"></i>
            Ya, Hapus
          </a>
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
    // Global variable to track current active tab
    let currentTab = 'operasional';
    
    $(document).ready(function() {
      // Check if operasionalTable is empty
      if ($('#operasionalTable tbody tr').length === 1 && $('#operasionalTable tbody tr td[colspan]').length > 0) {
        // For empty tables, don't initialize DataTables
      } else {
        // Only initialize DataTables if there's actual data
        $('#operasionalTable').DataTable({
          language: {
            url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json"
          },
          responsive: true,
          paging: false,
          searching: false,
          info: false,
          ordering: true,
          "order": [[1, "desc"]]
        });
      }
      
      // Check if karyawanTable is empty
      if ($('#karyawanTable tbody tr').length === 1 && $('#karyawanTable tbody tr td[colspan]').length > 0) {
        // For empty tables, don't initialize DataTables
      } else {
        // Only initialize DataTables if there's actual data
        $('#karyawanTable').DataTable({
          language: {
            url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json"
          },
          responsive: true,
          paging: false,
          searching: false,
          info: false,
          ordering: true,
          "order": [[1, "desc"]]
        });
      }

      // Check if produkTable is empty
      if ($('#produkTable tbody tr').length === 1 && $('#produkTable tbody tr td[colspan]').length > 0) {
        // For empty tables, don't initialize DataTables
      } else {
        // Only initialize DataTables if there's actual data
        $('#produkTable').DataTable({
          language: {
            url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json"
          },
          responsive: true,
          paging: false,
          searching: false,
          info: false,
          ordering: true,
          "order": [[1, "desc"]]
        });
      }

      // Handle karyawan selection in makan modal
      $('#id_karyawan_makan').change(function() {
        const selectedOption = $(this).find('option:selected');
        const nama = selectedOption.data('nama');
        $('#nama_karyawan_makan').val(nama);
      });
      
      // Format numbers to Rupiah
      $('input[name="jumlah"], input[name="gaji_asli"], input[name="kasbon"]').on('input', function() {
        $(this).val($(this).val().replace(/[^0-9]/g, ''));
      });
      
      // Auto close alert
      setTimeout(function() {
        $("#autoCloseAlert").alert('close');
      }, 5000);
      
      // Set active tab based on URL parameter
      const urlParams = new URLSearchParams(window.location.search);
      const tabParam = urlParams.get('tab');
      
      if (tabParam === 'karyawan') {
        $('#pengeluaranTabs button[data-bs-target="#karyawan"]').tab('show');
        currentTab = 'karyawan';
      } else if (tabParam === 'produk') {
        $('#pengeluaranTabs button[data-bs-target="#produk"]').tab('show');
        currentTab = 'produk';
      } else {
        $('#pengeluaranTabs button[data-bs-target="#operasional"]').tab('show');
        currentTab = 'operasional';
      }
      
      // Store active tab when changing tabs
      $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        const targetId = $(e.target).attr('data-bs-target').substring(1);
        currentTab = targetId;
        
        const currentUrlParams = new URLSearchParams(window.location.search);
        currentUrlParams.set('tab', targetId);
        
        const newUrl = window.location.pathname + '?' + currentUrlParams.toString();
        history.replaceState(null, '', newUrl);
      });
      
      // Handle karyawan selection in kasbon modal
      $('#id_karyawan').change(function() {
        const selectedOption = $(this).find('option:selected');
        const nama = selectedOption.data('nama');
        $('#nama_karyawan').val(nama);
      });
      
      // Handle karyawan selection in gaji modal
      $('#id_karyawan_gaji').change(function() {
        const selectedOption = $(this).find('option:selected');
        const nama = selectedOption.data('nama');
        $('#nama_karyawan_gaji').val(nama);
      });
    });
    
    // Month-Year filter navigation functions
    function filterByMonth(value) {
      window.location.href = '?bulan=' + value + '&tab=' + currentTab;
    }
    
    function setCurrentMonth() {
      const now = new Date();
      const year = now.getFullYear();
      const month = (now.getMonth() + 1).toString().padStart(2, '0');
      const currentMonth = `${year}-${month}`;
      
      document.getElementById('bulanTahunFilter').value = currentMonth;
      window.location.href = '?bulan=' + currentMonth + '&tab=' + currentTab;
    }
    
    function setPrevMonth() {
      const currentValue = document.getElementById('bulanTahunFilter').value;
      const [year, month] = currentValue.split('-').map(num => parseInt(num));
      
      let prevMonth = month - 1;
      let prevYear = year;
      
      if (prevMonth < 1) {
        prevMonth = 12;
        prevYear -= 1;
      }
      
      const formattedMonth = prevMonth.toString().padStart(2, '0');
      const newValue = `${prevYear}-${formattedMonth}`;
      
      document.getElementById('bulanTahunFilter').value = newValue;
      window.location.href = '?bulan=' + newValue + '&tab=' + currentTab;
    }
    
    function setNextMonth() {
      const currentValue = document.getElementById('bulanTahunFilter').value;
      const [year, month] = currentValue.split('-').map(num => parseInt(num));
      
      let nextMonth = month + 1;
      let nextYear = year;
      
      if (nextMonth > 12) {
        nextMonth = 1;
        nextYear += 1;
      }
      
      const formattedMonth = nextMonth.toString().padStart(2, '0');
      const newValue = `${nextYear}-${formattedMonth}`;
      
      document.getElementById('bulanTahunFilter').value = newValue;
      window.location.href = '?bulan=' + newValue + '&tab=' + currentTab;
    }
    
    // Calculate final salary function
    function calculateFinalSalary() {
      const gajiAsli = parseInt($('#gaji_asli').val()) || 0;
      const kasbon = parseInt($('#kasbon_karyawan_gaji').val()) || 0;
      
      $('#gaji_asli_display').text(gajiAsli.toLocaleString('id-ID'));
      $('#kasbon_display').text(kasbon.toLocaleString('id-ID'));
      $('#gaji_dibayarkan').text((gajiAsli - kasbon).toLocaleString('id-ID'));
      
      // Show or hide kasbon info box based on whether there's a kasbon
      if (kasbon > 0) {
        $('#kasbonInfoBox').show();
      } else {
        $('#kasbonInfoBox').hide();
      }
    }
    
    // Update kasbon info when selecting employee for salary payment
    function updateKasbonInfo() {
      const selectedOption = $('#id_karyawan_gaji option:selected');
      const kasbon = selectedOption.data('kasbon') || 0;
      const nama = selectedOption.data('nama');
      
      $('#nama_karyawan_gaji').val(nama);
      $('#kasbon_karyawan_gaji').val(kasbon);
      $('#kasbonAmount').text(kasbon.toLocaleString('id-ID'));
      
      // Recalculate salary
      calculateFinalSalary();
    }
    
    // Delete modal function
    function showDeleteModal(id, category, amount, tab) {
      $('#deleteCategoryName').text(category);
      $('#deleteAmount').text(amount);
      $('#deleteConfirmButton').attr('href', `?delete=${id}&bulan=<?= $bulan_filter ?>&tab=${tab}`);
      
      // Show modal
      var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
      deleteModal.show();
    }
  </script>