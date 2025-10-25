  <?php
  session_start();

  // Check if logged in and manajer
  if (!isset($_SESSION['manajer']['logged_in']) || $_SESSION['manajer']['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
  }

  include '../config.php'; // Database connection

  // Check if transaction ID is provided
  if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = "ID Transaksi tidak valid!";
    $_SESSION['alert_type'] = "danger";
    header("Location: piutang.php");
    exit();
  }

  $transaksi_id = mysqli_real_escape_string($conn, $_GET['id']);

  // Get transaction data
  $transaksi_query = "SELECT t.* FROM transaksi t WHERE t.id = '$transaksi_id' AND t.status_hutang = 1 AND t.hutang > 0";
  $transaksi_result = mysqli_query($conn, $transaksi_query);

  // Check if transaction exists and has debt
  if (mysqli_num_rows($transaksi_result) == 0) {
    $_SESSION['message'] = "Transaksi tidak ditemukan atau tidak memiliki hutang!";
    $_SESSION['alert_type'] = "danger";
    header("Location: piutang.php");
    exit();
  }

  $transaksi = mysqli_fetch_assoc($transaksi_result);

  // Process payment form submission
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $jumlah_bayar = mysqli_real_escape_string($conn, $_POST['jumlah_bayar']);
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
    $tanggal_bayar = mysqli_real_escape_string($conn, $_POST['tanggal_bayar']);
    $created_by = $_SESSION['manajer']['id_manajer'];
    
    // Validate payment amount
    if ($jumlah_bayar <= 0) {
      $_SESSION['message'] = "Jumlah pembayaran harus lebih dari 0!";
      $_SESSION['alert_type'] = "danger";
      header("Location: bayar_transaksi.php?id=$transaksi_id");
      exit();
    }
    
    if ($jumlah_bayar > $transaksi['hutang']) {
      $_SESSION['message'] = "Jumlah pembayaran tidak boleh melebihi total hutang!";
      $_SESSION['alert_type'] = "danger";
      header("Location: bayar_transaksi.php?id=$transaksi_id");
      exit();
    }
    
    // Calculate remaining debt
    $sisa_hutang = $transaksi['hutang'] - $jumlah_bayar;
    
    // Start transaction
    mysqli_autocommit($conn, false);
    $error = false;
    
    // Update transaction status
    if ($sisa_hutang == 0) {
      // Full payment - update status to paid
      $update_query = "UPDATE transaksi SET 
                      hutang = 0, 
                      status_hutang = 0,
                      pendapatan = pendapatan + $jumlah_bayar 
                      WHERE id = '$transaksi_id'";
    } else {
      // Partial payment - update remaining debt
      $update_query = "UPDATE transaksi SET 
                      hutang = '$sisa_hutang',
                      pendapatan = pendapatan + $jumlah_bayar
                      WHERE id = '$transaksi_id'";
    }
    
    if (!mysqli_query($conn, $update_query)) {
      $error = true;
    }
    
    // Insert payment record
    $insert_query = "INSERT INTO piutang_cair (
                    transaksi_id, 
                    jumlah_bayar, 
                    tanggal_bayar, 
                    keterangan, 
                    created_by) 
                    VALUES 
                    ('$transaksi_id', '$jumlah_bayar', '$tanggal_bayar', '$keterangan', '$created_by')";
    
    if (!mysqli_query($conn, $insert_query)) {
      $error = true;
    }
    
    // Commit or rollback transaction
    if ($error) {
      mysqli_rollback($conn);
      $_SESSION['message'] = "Gagal memproses pembayaran: " . mysqli_error($conn);
      $_SESSION['alert_type'] = "danger";
      header("Location: bayar_transaksi.php?id=$transaksi_id");
    } else {
      mysqli_commit($conn);
      if ($sisa_hutang == 0) {
        $_SESSION['message'] = "Pembayaran hutang transaksi berhasil! Status transaksi menjadi LUNAS.";
      } else {
        $_SESSION['message'] = "Pembayaran partial berhasil diproses! Sisa hutang: Rp " . number_format($sisa_hutang, 0, ',', '.');
      }
      $_SESSION['alert_type'] = "success";
      header("Location: transaksi_detail.php?id=$transaksi_id");
    }
    exit();
  }

  // Get transaction items for display
  $items_query = "SELECT td.*, td.nama_produk_manual, td.harga_satuan, 
                  p.nama as nama_produk, k.nama_kategori
                  FROM transaksi_detail td
                  LEFT JOIN produk p ON td.produk_id = p.id
                  LEFT JOIN kategori k ON p.kategori_id = k.id
                  WHERE td.transaksi_id = '$transaksi_id' 
                  ORDER BY td.id ASC";
  $items_result = mysqli_query($conn, $items_query);
  ?>

  <!DOCTYPE html>
  <html lang="id">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bayar Piutang Transaksi - BMS Bengkel</title>
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
        padding-left: 280px; /* Add this to account for the sidebar */
      }
      
      /* Content area */
      .content {
        padding: 20px;
        background-color: var(--light-gray);
        min-height: 100vh;
      }
      
      /* Page header section */
      .page-header {
        background: linear-gradient(135deg, var(--primary-orange), var(--secondary-orange));
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
        height: 100%;
        margin-bottom: 1.5rem;
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
      
      /* Alert styling */
      .alert-dismissible {
        border-radius: 10px;
        border: none;
        box-shadow: 0 4px 8px rgba(0,0,0,0.05);
      }
      
      .alert-success {
        background-color: #d1e7dd;
        color: #0f5132;
      }
      
      .alert-danger {
        background-color: #f8d7da;
        color: #842029;
      }
      
      /* Transaction info card */
      .transaction-info {
        background-color: var(--light-orange);
        border-radius: 10px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
      }
      
      .transaction-id {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--primary-orange);
        margin-bottom: 1rem;
      }
      
      .transaction-detail {
        font-weight: 500;
        margin-bottom: 0.5rem;
      }
      
      /* Payment form */
      .form-label {
        font-weight: 500;
        color: var(--text-dark);
      }
      
      .input-group-text {
        background-color: var(--light-orange);
        color: var(--primary-orange);
        border-color: #ced4da;
        font-weight: 500;
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
      
      /* Table styling */
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
      
      /* Responsive adjustments for mobile */
      @media (max-width: 992px) {
        body {
          padding-left: 0;
        }
        
        .content {
          padding: 70px 15px 20px;
        }
        
        .page-header {
          padding: 1.5rem;
          margin-bottom: 1.5rem;
        }
        
        .data-card {
          padding: 1rem;
        }
      }
    </style>
  </head>
  <body>
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>
    
    <div class="content">
      <!-- Page Header -->
      <div class="page-header">
        <div class="container-fluid">
          <div class="row align-items-center">
            <div class="col-md-6">
              <h1><i class="fas fa-money-bill-wave me-2"></i> Bayar Piutang Transaksi</h1>
              <p class="mb-0">Form pembayaran piutang transaksi</p>
            </div>
            <div class="col-md-6 text-md-end mt-3 mt-md-0">
              <a href="hutang_piutang.php?id=<?= $transaksi_id ?>" class="btn btn-light">
                <i class="fas fa-arrow-left me-1"></i> Kembali ke Menu Hutang & Piutang
              </a>
            </div>
          </div>
        </div>  
      </div>
      
      <!-- Alert Messages -->
      <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['alert_type'] ?> alert-dismissible fade show" role="alert" id="autoCloseAlert">
          <?= $_SESSION['message'] ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php 
          unset($_SESSION['message']);
          unset($_SESSION['alert_type']);
        ?>
      <?php endif; ?>
      
      <div class="row">
        <!-- Transaction Information Card -->
        <div class="col-lg-4 mb-4">
          <div class="data-card h-100">
            <div class="card-header-actions">
              <h5 class="card-title">Informasi Transaksi</h5>
            </div>
            
            <div class="transaction-info">
              <div class="transaction-id">Transaksi #<?= $transaksi_id ?></div>
              <div class="transaction-detail">
                <i class="fas fa-user me-2"></i> Customer: <?= htmlspecialchars($transaksi['nama_customer']) ?>
              </div>
              <div class="transaction-detail">
                <i class="fas fa-calendar me-2"></i> Tanggal: <?= date('d/m/Y', strtotime($transaksi['tanggal'])) ?>
              </div>
              <div class="transaction-detail">
                <i class="fas fa-motorcycle me-2"></i> Plat: <?= htmlspecialchars($transaksi['plat_nomor_motor'] ?? '-') ?>
              </div>
              <div class="transaction-detail">
                <i class="fas fa-phone me-2"></i> No. HP: <?= htmlspecialchars($transaksi['no_whatsapp'] ?? '-') ?>
              </div>
              <hr>
              <div class="transaction-detail">
                <i class="fas fa-hand-holding-usd me-2"></i> Status: <span class="badge bg-warning text-dark">Hutang</span>
              </div>
              <div class="transaction-detail">
                <i class="fas fa-shopping-cart me-2"></i> Total Transaksi: Rp <?= number_format($transaksi['total'], 0, ',', '.') ?>
              </div>
              <div class="transaction-detail">
                <i class="fas fa-money-bill-wave me-2"></i> Sudah Dibayar: Rp <?= number_format($transaksi['jumlah_bayar'], 0, ',', '.') ?>
              </div>
              <div class="transaction-detail">
                <i class="fas fa-money-bill-wave me-2"></i> Sisa Hutang: 
                <span class="fw-bold text-danger fs-5">
                  Rp <?= number_format($transaksi['hutang'], 0, ',', '.') ?>
                </span>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Payment Form Card -->
        <div class="col-lg-8 mb-4">
          <div class="data-card">
            <div class="card-header-actions">
              <h5 class="card-title">Form Pembayaran Piutang</h5>
            </div>
            
            <form action="" method="POST" id="paymentForm">
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="jumlah_bayar" class="form-label">Jumlah Pembayaran <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <span class="input-group-text">Rp</span>
                    <input type="number" class="form-control" id="jumlah_bayar" name="jumlah_bayar" 
                          required value="<?= $transaksi['hutang'] ?>" 
                          max="<?= $transaksi['hutang'] ?>" min="1">
                  </div>
                  <div class="form-text">Masukkan jumlah pembayaran (maksimal Rp <?= number_format($transaksi['hutang'], 0, ',', '.') ?>)</div>
                </div>
                
                <div class="col-md-6 mb-3">
                  <label for="tanggal_bayar" class="form-label">Tanggal Pembayaran <span class="text-danger">*</span></label>
                  <input type="date" class="form-control" id="tanggal_bayar" name="tanggal_bayar" 
                        required value="<?= date('Y-m-d') ?>">
                </div>
              </div>
              
              <div class="mb-3">
                <label for="keterangan" class="form-label">Keterangan</label>
                <textarea class="form-control" id="keterangan" name="keterangan" rows="3" 
                          placeholder="Masukkan keterangan pembayaran...">Pelunasan Piutang transaksi</textarea>
              </div>
              
              <div class="mb-3">
                <div class="alert alert-info">
                  <div class="mb-2"><i class="fas fa-info-circle me-2"></i> <strong>Informasi Pembayaran:</strong></div>
                  <p class="mb-1">- Anda dapat melakukan pembayaran partial (sebagian)</p>
                  <p class="mb-1">- Jika total hutang lunas, status transaksi akan otomatis berubah menjadi "Lunas"</p>
                  <p class="mb-0">- Rekam pembayaran akan tersimpan di riwayat pembayaran</p>
                </div>
              </div>
              
              <div class="text-end">
                <a href="transaksi_detail.php?id=<?= $transaksi_id ?>" class="btn btn-outline-secondary me-2">
                  <i class="fas fa-times me-1"></i> Batal
                </a>
                <button type="button" class="btn btn-primary" id="btnConfirmPayment">
                  <i class="fas fa-money-bill-wave me-1"></i> Proses Pembayaran
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
      
      <!-- Transaction Items Card -->
      <div class="data-card">
        <div class="card-header-actions">
          <h5 class="card-title">Detail Item Transaksi</h5>
        </div>
        
        <div class="table-responsive">
          <table class="table table-hover">
            <thead>
              <tr>
                <th width="5%">No</th>
                <th width="35%">Nama Produk</th>
                <th width="15%">Kategori</th>
                <th width="10%">Jumlah</th>
                <th width="15%">Harga Satuan</th>
                <th width="20%">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <?php 
              $no = 1;
              $total = 0;
              if (mysqli_num_rows($items_result) > 0):
                while ($row = mysqli_fetch_assoc($items_result)):
                  $harga = isset($row['harga_satuan']) ? intval($row['harga_satuan']) : 0;
                  $jumlah = isset($row['jumlah']) ? intval($row['jumlah']) : 0;
                  $subtotal = $jumlah * $harga;
                  $total += $subtotal;
              ?>
              <tr>
                <td><?= $no++ ?></td>
                <td>
                  <?php
                  if (!empty($row['nama_produk_manual'])) {
                    echo htmlspecialchars($row['nama_produk_manual']);
                  } elseif (isset($row['nama_produk'])) {
                    echo htmlspecialchars($row['nama_produk']);
                  } else {
                    echo 'Produk tidak ditemukan';
                  }
                  ?>
                </td>
                <td>
                  <?php
                  if (!empty($row['nama_produk_manual']) && empty($row['nama_kategori'])) {
                    echo 'Tidak dalam kategori';
                  } else {
                    echo isset($row['nama_kategori']) ? htmlspecialchars($row['nama_kategori']) : '-';
                  }
                  ?>
                </td>
                <td><?= $jumlah ?></td>
                <td>Rp <?= number_format($harga, 0, ',', '.') ?></td>
                <td>Rp <?= number_format($subtotal, 0, ',', '.') ?></td>
              </tr>
              <?php 
                endwhile;
              else:
              ?>
              <tr>
                <td colspan="6" class="text-center py-4">Tidak ada item dalam transaksi ini</td>
              </tr>
              <?php endif; ?>
            </tbody>
            <tfoot>
              <tr class="fw-bold">
                <td colspan="5" class="text-end">Total</td>
                <td>Rp <?= number_format($total, 0, ',', '.') ?></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
      
      <!-- Konfirmasi Modal -->
      <div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="confirmationModalLabel">
                <i class="fas fa-exclamation-circle text-warning me-2"></i>
                Konfirmasi Pembayaran
              </h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <p>Anda akan memproses pembayaran piutang untuk transaksi:</p>
              <div class="d-flex align-items-center mb-3 p-3 rounded bg-light">
                <i class="fas fa-file-invoice-dollar me-3 fs-3 text-primary"></i>
                <div>
                  <h6 class="mb-0">Transaksi #<?= $transaksi_id ?></h6>
                  <small>Customer: <?= htmlspecialchars($transaksi['nama_customer']) ?></small>
                </div>
              </div>
              <p class="fw-bold mb-1">Detail Pembayaran:</p>
              <ul class="list-group mb-3">
                <li class="list-group-item d-flex justify-content-between align-items-center">
                  <span>Jumlah Pembayaran:</span>
                  <span class="fw-bold" id="confirmAmount">Rp 0</span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                  <span>Tanggal Pembayaran:</span>
                  <span id="confirmDate">-</span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                  <span>Keterangan:</span>
                  <span id="confirmNote" class="text-muted">-</span>
                </li>
              </ul>
              <p class="text-danger mb-0"><small>Pastikan data pembayaran sudah benar sebelum melanjutkan.</small></p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                <i class="fas fa-times me-1"></i> Batal
              </button>
              <button type="button" class="btn btn-primary" id="btnSubmitPayment">
                <i class="fas fa-check me-1"></i> Ya, Proses Pembayaran
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <script>
      $(document).ready(function() {
        // Auto dismiss alerts
        var alertElement = document.getElementById('autoCloseAlert');
        if (alertElement) {
          // Gunakan Bootstrap alert object untuk handling lebih halus
          var bsAlert = new bootstrap.Alert(alertElement);
          
          // Set timeout untuk menutup alert setelah 5 detik
          setTimeout(function() {
            // Verifikasi alert masih ada sebelum mencoba menutupnya
            if (document.body.contains(alertElement)) {
              bsAlert.close();
            }
          }, 5000);
        }
        
        // Validate payment amount
        $('#jumlah_bayar').on('change', function() {
          const maxHutang = <?= $transaksi['hutang'] ?>;
          const bayar = $(this).val();
          
          if (bayar <= 0) {
            $(this).val(1);
            alert('Jumlah pembayaran harus lebih dari 0!');
          } else if (bayar > maxHutang) {
            $(this).val(maxHutang);
            alert('Jumlah pembayaran tidak boleh melebihi total hutang!');
          }
        });
        
        // Show confirmation modal
        $('#btnConfirmPayment').on('click', function() {
          const jumlahBayar = $('#jumlah_bayar').val();
          const tanggalBayar = $('#tanggal_bayar').val();
          const keterangan = $('#keterangan').val() || 'Pelunasan Piutang transaksi';
          
          // Format currency for display
          const formatCurrency = new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0
          });
          
          // Format date for display (from YYYY-MM-DD to DD/MM/YYYY)
          const formatDate = (dateString) => {
            const parts = dateString.split('-');
            return `${parts[2]}/${parts[1]}/${parts[0]}`;
          };
          
          // Update modal with form values
          $('#confirmAmount').text(formatCurrency.format(jumlahBayar));
          $('#confirmDate').text(formatDate(tanggalBayar));
          $('#confirmNote').text(keterangan);
          
          // Show modal
          new bootstrap.Modal(document.getElementById('confirmationModal')).show();
        });
        
        // Submit form when confirmed
        $('#btnSubmitPayment').on('click', function() {
          $('#paymentForm').submit();
        });
      });
    </script>
  </body>
  </html>