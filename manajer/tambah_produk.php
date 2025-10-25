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

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize input
    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
    $harga_beli = mysqli_real_escape_string($conn, $_POST['harga_beli']);
    $harga_jual = mysqli_real_escape_string($conn, $_POST['harga_jual']);
    $stok = mysqli_real_escape_string($conn, $_POST['stok']);
    $kategori_id = !empty($_POST['kategori_id']) ? mysqli_real_escape_string($conn, $_POST['kategori_id']) : NULL;
    $hutang_sparepart = mysqli_real_escape_string($conn, $_POST['hutang_sparepart']);
    $nominal_hutang = 0;
    
    // Cek apakah catat_pengeluaran dicentang (1) atau tidak (0)
    $catat_pengeluaran = isset($_POST['catat_pengeluaran']) ? 1 : 0;
    
    // Hitung total nilai pembelian
    $total_nilai_pembelian = $harga_beli * $stok;
    
    if ($hutang_sparepart === 'Hutang') {
        $nominal_hutang = mysqli_real_escape_string($conn, $_POST['nominal_hutang']);
        // Pastikan nominal hutang tidak melebihi total nilai pembelian
        if ($nominal_hutang > $total_nilai_pembelian) {
            $nominal_hutang = $total_nilai_pembelian;
        }
    }
    
    // Mulai transaksi database
    mysqli_begin_transaction($conn);
    
    try {
        // Insert into database
        $query = "INSERT INTO produk (nama, harga_beli, harga_jual, stok, kategori_id, hutang_sparepart, nominal_hutang) 
                VALUES ('$nama', '$harga_beli', '$harga_jual', '$stok', " . ($kategori_id === NULL ? "NULL" : "'$kategori_id'") . ", '$hutang_sparepart', '$nominal_hutang')";
        
        if (mysqli_query($conn, $query)) {
            $produk_id = mysqli_insert_id($conn);
            
            // Hanya catat pengeluaran jika checkbox dicentang
            if ($catat_pengeluaran) {
                // Hitung total nilai pembelian dan pengeluaran aktual
                $total_nilai_pembelian = $harga_beli * $stok;
                
                // Format tanggal dan bulan untuk tabel pengeluaran
                $tanggal = date('Y-m-d');
                $bulan = date('Y-m');
                
                // Hitung pengeluaran aktual berdasarkan status pembayaran
                if ($hutang_sparepart === 'Hutang') {
                    // Jika hutang, pengeluaran = total nilai - nominal hutang
                    $total_pengeluaran = $total_nilai_pembelian - $nominal_hutang;
                } else {
                    // Jika cash, pengeluaran = total nilai pembelian
                    $total_pengeluaran = $total_nilai_pembelian;
                }
                
                // Tambahkan ke pengeluaran jika ada pengeluaran aktual
                if ($total_pengeluaran > 0) {
                    // Deskripsi pengeluaran
                    if ($hutang_sparepart === 'Hutang') {
                      $keterangan = "Pembelian produk baru: $nama (x$stok) [SEBAGIAN HUTANG: Rp " . number_format($nominal_hutang, 0, ',', '.') . "]";
                  } else {
                      $keterangan = "Pembelian produk baru: $nama (x$stok) [LUNAS]";
                  }
                    
                    // Tambahkan ke tabel pengeluaran
                    $pengeluaran_query = "INSERT INTO pengeluaran (tanggal, bulan, keterangan, jumlah, kategori) 
                                         VALUES ('$tanggal', '$bulan', '$keterangan', '$total_pengeluaran', 'Pembelian Barang')";
                    
                    if (!mysqli_query($conn, $pengeluaran_query)) {
                        throw new Exception("Gagal mencatat pengeluaran: " . mysqli_error($conn));
                    }
                }
            }
            
            // Jika berhasil, commit transaksi
            mysqli_commit($conn);
            
            $pengeluaran_msg = "";
            if ($catat_pengeluaran) {
                if ($hutang_sparepart === 'Hutang') {
                    $total_pengeluaran = $total_nilai_pembelian - $nominal_hutang;
                } else {
                    $total_pengeluaran = $total_nilai_pembelian;
                }
                
                if ($total_pengeluaran > 0) {
                    $pengeluaran_msg = " Pengeluaran Rp " . number_format($total_pengeluaran, 0, ',', '.') . " telah dicatat.";
                }
                
                if ($hutang_sparepart === 'Hutang' && $nominal_hutang > 0) {
                    $pengeluaran_msg .= " Hutang Rp " . number_format($nominal_hutang, 0, ',', '.') . " telah dicatat.";
                }
            } else {
                $pengeluaran_msg = " Tidak ada pengeluaran yang dicatat.";
            }
            
            $_SESSION['message'] = "Produk berhasil ditambahkan!" . $pengeluaran_msg;
            $_SESSION['alert_type'] = "success";
            header("Location: produk.php");
            exit();
        } else {
            throw new Exception("Gagal menambahkan produk: " . mysqli_error($conn));
        }
    } catch (Exception $e) {
        // Jika terjadi error, rollback transaksi
        mysqli_rollback($conn);
        
        $_SESSION['message'] = $e->getMessage();
        $_SESSION['alert_type'] = "danger";
    }
}

// Get all categories
$kategori_query = "SELECT * FROM kategori ORDER BY nama_kategori ASC";
$kategori_result = mysqli_query($conn, $kategori_query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tambah Produk - BMS Bengkel</title>
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
    }
    
    body {
      background-color: var(--light-gray);
      font-family: 'Poppins', 'Arial', sans-serif;
      color: var(--text-dark);
      margin: 0;
      padding: 0;
    }
    
    /* Navbar Styling - Synchronized with dashboard.php */
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
    
    /* Form card */
    .form-card {
      border: none;
      border-radius: 15px;
      overflow: hidden;
      box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
      background-color: var(--white);
      padding: 1.5rem;
      transition: all 0.3s ease;
    }
    
    .form-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 12px 20px rgba(0, 0, 0, 0.12);
    }
    
    /* Form section header */
    .form-section-header {
      border-bottom: 2px solid var(--light-orange);
      padding-bottom: 1rem;
      margin-bottom: 1.5rem;
    }
    
    .form-section-title {
      font-size: 1.25rem;
      font-weight: 600;
      color: var(--primary-orange);
      margin-bottom: 0;
    }
    
    /* Form group styling */
    .form-label {
      font-weight: 600;
      color: var(--text-dark);
    }
    
    .form-control, .form-select {
      border-radius: 8px;
      border: 1px solid #e0e6ed;
      padding: 0.75rem 1rem;
      font-size: 1rem;
      transition: all 0.3s ease;
    }
    
    .form-control:focus, .form-select:focus {
      border-color: var(--secondary-orange);
      box-shadow: 0 0 0 0.25rem rgba(239, 108, 0, 0.25);
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
    
    .btn-secondary {
      background-color: #6c757d;
      border: none;
      border-radius: 8px;
      padding: 0.75rem 1.5rem;
      font-weight: 600;
      transition: all 0.3s ease;
    }
    
    .btn-secondary:hover {
      background-color: #5a6268;
      transform: translateY(-2px);
      box-shadow: 0 4px 10px rgba(108, 117, 125, 0.2);
    }
    
    .btn-outline-light {
      border-radius: 8px;
      font-weight: 500;
      transition: all 0.3s ease;
    }
    
    /* Required field indicator */
    .required-field::after {
      content: "*";
      color: #dc3545;
      margin-left: 4px;
    }
    
    /* Form text helper */
    .form-text {
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
    
    /* Information badge */
    .info-badge {
      background-color: #E8F5E9;
      color: #1B5E20;
      padding: 10px 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
    }
    
    .info-badge.warning {
      background-color: #FFF3E0;
      color: #E65100;
    }
    
    /* Checkbox styling */
    .form-check-input {
      width: 1.2em;
      height: 1.2em;
      margin-top: 0.15em;
      vertical-align: top;
      background-color: #fff;
      background-repeat: no-repeat;
      background-position: center;
      background-size: contain;
      border: 1px solid rgba(0, 0, 0, 0.25);
      -webkit-appearance: none;
      -moz-appearance: none;
      appearance: none;
      -webkit-print-color-adjust: exact;
      border-radius: 0.25em;
      transition: background-color 0.15s ease-in-out, background-position 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    
    .form-check-input:checked {
      background-color: var(--primary-orange);
      border-color: var(--primary-orange);
    }
    
    .form-check-label {
      cursor: pointer;
    }
    
    .form-switch .form-check-input {
      width: 2em;
      margin-left: -2.5em;
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='rgba%280, 0, 0, 0.25%29'/%3e%3c/svg%3e");
      background-position: left center;
      border-radius: 2em;
      transition: background-position 0.15s ease-in-out;
    }
    
    .form-switch .form-check-input:checked {
      background-position: right center;
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='%23fff'/%3e%3c/svg%3e");
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
    }
  </style>
</head>
<body>

  <!-- Sidebar -->
  <?php include 'sidebar.php'; ?>

  <!-- Content -->
  <div class="content">
    <!-- Navbar - Updated to match dashboard.php -->
    <nav class="navbar navbar-expand-lg navbar-dark">
      <div class="container-fluid">
        <span class="navbar-brand">
          <i class="fas fa-box me-2"></i>
          Tambah Produk
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
        <div class="d-flex align-items-center">
          <div>
            <h1><i class="fas fa-plus-circle me-2"></i>Tambah Produk Baru</h1>
            <p class="lead mb-0">Tambahkan produk baru ke dalam sistem kasir bengkel.</p>
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

      <div class="row">
        <div class="col-lg-8 mx-auto">
          <!-- Informasi Pengeluaran -->
          <div class="info-badge mb-3">
            <i class="fas fa-info-circle me-2"></i>
            <div>
              <p class="mb-1">Secara default, pembelian produk baru akan dicatat sebagai pengeluaran:</p>
              <ul class="mb-0" style="padding-left: 20px;">
                <li>Status <strong>Cash</strong>: Seluruh nilai pembelian dicatat sebagai pengeluaran</li>
                <li>Status <strong>Hutang</strong>: Nilai pembelian dikurangi nominal hutang dicatat sebagai pengeluaran</li>
                <li>Nonaktifkan opsi "Catat sebagai Pengeluaran" jika produk sudah ada tetapi belum tercatat dalam sistem</li>
              </ul>
            </div>
          </div>
          
          <div class="form-card">
            <div class="form-section-header">
              <h5 class="form-section-title">
                <i class="fas fa-box me-2"></i>
                Informasi Produk
              </h5>
            </div>
            
            <form method="POST" action="">
              <div class="mb-3">
                <label for="nama" class="form-label required-field">Nama Produk</label>
                <input type="text" class="form-control" id="nama" name="nama" required>
                <div class="form-text">Masukkan nama lengkap produk.</div>
              </div>
              
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label for="harga_beli" class="form-label required-field">Harga Beli</label>
                  <div class="input-group">
                    <span class="input-group-text">Rp</span>
                    <input type="number" class="form-control" id="harga_beli" name="harga_beli" min="0" required>
                  </div>
                  <div class="form-text">Harga pembelian produk dari supplier.</div>
                </div>
                
                <div class="col-md-6">
                  <label for="harga_jual" class="form-label required-field">Harga Jual</label>
                  <div class="input-group">
                    <span class="input-group-text">Rp</span>
                    <input type="number" class="form-control" id="harga_jual" name="harga_jual" min="0" required>
                  </div>
                  <div class="form-text">Harga penjualan produk ke pelanggan.</div>
                </div>
              </div>
              
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label for="stok" class="form-label required-field">Stok</label>
                  <input type="number" class="form-control" id="stok" name="stok" min="0" required>
                  <div class="form-text">Jumlah barang yang tersedia.</div>
                </div>
                
                <div class="col-md-6">
                  <label for="kategori_id" class="form-label">Kategori</label>
                  <select class="form-select" id="kategori_id" name="kategori_id">
                    <option value="">Pilih Kategori</option>
                    <?php while ($kategori = mysqli_fetch_assoc($kategori_result)): ?>
                      <option value="<?= $kategori['id'] ?>"><?= htmlspecialchars($kategori['nama_kategori']) ?></option>
                    <?php endwhile; ?>
                  </select>
                  <div class="form-text">Pilih kategori yang sesuai untuk produk ini.</div>
                </div>
              </div>
              
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label for="hutang_sparepart" class="form-label required-field">Status Pembayaran</label>
                  <select class="form-select" id="hutang_sparepart" name="hutang_sparepart" required>
                    <option value="Cash" selected>Cash</option>
                    <option value="Hutang">Hutang</option>
                  </select>
                  <div class="form-text">Pilih status pembayaran untuk sparepart ini.</div>
                </div>
                
                <div class="col-md-6" id="nominal_hutang_container" style="display: none;">
                  <label for="nominal_hutang" class="form-label">Nominal Hutang</label>
                  <div class="input-group">
                    <span class="input-group-text">Rp</span>
                    <input type="number" class="form-control" id="nominal_hutang" name="nominal_hutang" value="0" min="0" step="1000">
                  </div>
                  <div class="form-text">Masukkan nominal hutang untuk sparepart ini.</div>
                </div>
              </div>
              
              <!-- Opsi catat sebagai pengeluaran -->
              <div class="form-check form-switch mb-4">
                <input class="form-check-input" type="checkbox" id="catat_pengeluaran" name="catat_pengeluaran" checked>
                <label class="form-check-label" for="catat_pengeluaran">
                  <strong>Catat sebagai Pengeluaran</strong>
                  <div class="form-text">Nonaktifkan opsi ini jika produk sudah ada tetapi belum tercatat dalam sistem.</div>
                </label>
              </div>

              <!-- Tampilkan informasi total pengeluaran -->
              <div class="info-badge warning mt-3 mb-4" id="pengeluaran_info" style="display: none;">
                <i class="fas fa-money-bill-wave me-2"></i>
                <div>
                  <strong>Perhatian:</strong>
                  <div>Total pengeluaran yang akan tercatat: <strong id="total_pengeluaran">Rp 0</strong></div>
                </div>
              </div>

              <div class="mt-4 d-flex justify-content-between">
                <a href="produk.php" class="btn btn-secondary">
                  <i class="fas fa-arrow-left me-1"></i>
                  Kembali
                </a>
                <button type="submit" class="btn btn-primary">
                  <i class="fas fa-save me-1"></i>
                  Simpan Produk
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
    // Script untuk menampilkan/menyembunyikan field nominal hutang
    document.getElementById('hutang_sparepart').addEventListener('change', function() {
      var nominalHutangContainer = document.getElementById('nominal_hutang_container');
      
      if (this.value === 'Hutang') {
        nominalHutangContainer.style.display = 'block';
        // Update nominal hutang default dengan total nilai pembelian
        var hargaBeli = parseFloat(document.getElementById('harga_beli').value) || 0;
        var stok = parseInt(document.getElementById('stok').value) || 0;
        var totalNilai = hargaBeli * stok;
        document.getElementById('nominal_hutang').value = totalNilai;
      } else {
        nominalHutangContainer.style.display = 'none';
        document.getElementById('nominal_hutang').value = '0';
      }
      updatePengeluaranInfo();
    });
    
    // Function to update pengeluaran info
    function updatePengeluaranInfo() {
      var hargaBeli = parseFloat(document.getElementById('harga_beli').value) || 0;
      var stok = parseInt(document.getElementById('stok').value) || 0;
      var status = document.getElementById('hutang_sparepart').value;
      var totalNilai = hargaBeli * stok;
      var pengeluaranInfo = document.getElementById('pengeluaran_info');
      var catatPengeluaran = document.getElementById('catat_pengeluaran').checked;
      
      // Sembunyikan info pengeluaran jika opsi catat pengeluaran dinonaktifkan
      if (!catatPengeluaran) {
        pengeluaranInfo.style.display = 'none';
        return;
      }
      
      if (totalNilai > 0) {
        var nominalHutang = 0;
        
        if (status === 'Hutang') {
          nominalHutang = parseFloat(document.getElementById('nominal_hutang').value) || 0;
          // Pastikan nominal hutang tidak melebihi total nilai
          if (nominalHutang > totalNilai) {
            nominalHutang = totalNilai;
            document.getElementById('nominal_hutang').value = totalNilai;
          }
        }
        
        var totalPengeluaran = totalNilai - nominalHutang;
        
        if (totalPengeluaran > 0) {
          var infoText = '';
          if (status === 'Hutang') {
            infoText = '<strong>Perhatian:</strong><div>Total nilai pembelian: <strong>Rp ' + numberWithCommas(totalNilai) + '</strong></div>' +
                      '<div>Nominal hutang: <strong>Rp ' + numberWithCommas(nominalHutang) + '</strong></div>' +
                      '<div>Pengeluaran yang akan tercatat: <strong id="total_pengeluaran">Rp ' + numberWithCommas(totalPengeluaran) + '</strong></div>';
          } else {
            infoText = '<strong>Perhatian:</strong><div>Total pengeluaran yang akan tercatat: <strong id="total_pengeluaran">Rp ' + numberWithCommas(totalPengeluaran) + '</strong></div>';
          }
          
          pengeluaranInfo.innerHTML = '<i class="fas fa-money-bill-wave me-2"></i><div>' + infoText + '</div>';
          pengeluaranInfo.style.display = 'flex';
        } else {
          pengeluaranInfo.style.display = 'none';
        }
      } else {
        pengeluaranInfo.style.display = 'none';
      }
    }
    
    // Function to format number with commas
    function numberWithCommas(x) {
      return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }
    
    // Add event listeners to update pengeluaran info
    document.getElementById('harga_beli').addEventListener('input', updatePengeluaranInfo);
    document.getElementById('stok').addEventListener('input', updatePengeluaranInfo);
    document.getElementById('hutang_sparepart').addEventListener('change', updatePengeluaranInfo);
    document.getElementById('nominal_hutang').addEventListener('input', updatePengeluaranInfo);
    document.getElementById('catat_pengeluaran').addEventListener('change', updatePengeluaranInfo);
    
    // Auto close alerts after 5 seconds
    setTimeout(function() {
      $(".alert").alert('close');
    }, 5000);
    
    // Calculate recommended selling price
    document.getElementById('harga_beli').addEventListener('input', function() {
      const hargaBeli = parseFloat(this.value) || 0;
      const recommendedPrice = Math.ceil(hargaBeli * 1.3 / 1000) * 1000; // 30% markup rounded to nearest 1000
      document.getElementById('harga_jual').value = recommendedPrice;
    });
  </script>
</body>
</html> 