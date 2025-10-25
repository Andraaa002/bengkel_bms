<?php
include '../config.php';
session_start();

// Check if user is logged in as admin
if (!isset($_SESSION['admin']['logged_in']) || $_SESSION['admin']['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['id']) || !isset($_GET['source'])) {
    echo "<script>alert('ID atau sumber data tidak ditemukan.'); window.location='history_pengeluaran.php';</script>";
    exit();
}

$id = intval($_GET['id']);
$source = $_GET['source']; // 'pengeluaran' or 'piutang_cair'

// Validate source
if (!in_array($source, ['pengeluaran', 'piutang_cair'])) {
    echo "<script>alert('Sumber data tidak valid.'); window.location='history_pengeluaran.php';</script>";
    exit();
}

// Fetch expense data based on source
if ($source == 'pengeluaran') {
    $query = $conn->query("
        SELECT p.id, p.tanggal, p.kategori, p.jumlah, p.keterangan, p.created_at,
               CASE 
                 WHEN p.keterangan LIKE 'Pembelian produk baru:%' THEN 'Pembelian Sparepart Baru'
                 WHEN p.keterangan LIKE '%produk baru:%' AND p.keterangan LIKE '%pembayaran awal%' THEN 'Pembelian Sparepart Baru'
                 WHEN p.keterangan LIKE 'Penambahan stok produk:%' THEN 'Tambah Stok'
                 WHEN p.kategori = 'Pembelian Sparepart' THEN 'Pembelian Sparepart Baru'
                 WHEN p.kategori = 'Pembelian Barang' THEN 'Pembelian Sparepart Baru'
                 WHEN p.kategori = 'Bayar Hutang Produk' THEN 'Bayar Hutang Produk'
                 ELSE p.kategori
               END as kategori_transaksi
        FROM pengeluaran p 
        WHERE p.id = $id
    ");
} else {
    $query = $conn->query("
        SELECT pc.id, pc.tanggal_bayar as tanggal, 'Bayar Hutang Produk' as kategori, 
               pc.jumlah_bayar as jumlah, pc.keterangan, pc.created_at,
               'Bayar Hutang Produk' as kategori_transaksi
        FROM piutang_cair pc 
        WHERE pc.id = $id AND pc.transaksi_id = '-1'
    ");
}

if ($query->num_rows == 0) {
    echo "<script>alert('Data pengeluaran tidak ditemukan.'); window.location='history_pengeluaran.php';</script>";
    exit();
}
$expense = $query->fetch_assoc();

// Determine badge style based on kategori
$badge_class = "badge-lainnya";
if (in_array($expense['kategori'], ['Sewa Lahan', 'Token Listrik', 'Air', 'Internet', 'Lainnya'])) {
    $badge_class = "badge-operasional";
} elseif ($expense['kategori'] == 'Gaji Karyawan' || $expense['kategori_transaksi'] == 'Gaji Karyawan') {
    $badge_class = "badge-gaji";
} elseif ($expense['kategori'] == 'Kasbon Karyawan' || $expense['kategori_transaksi'] == 'Kasbon Karyawan') {
    $badge_class = "badge-kasbon";
} elseif ($expense['kategori'] == 'Uang Makan' || $expense['kategori_transaksi'] == 'Uang Makan') {
    $badge_class = "badge-makan";
} elseif ($expense['kategori_transaksi'] == 'Pembelian Sparepart Baru' || 
         $expense['kategori'] == 'Pembelian Sparepart' || 
         $expense['kategori'] == 'Pembelian Barang') {
    $badge_class = "badge-produk";
} elseif ($expense['kategori_transaksi'] == 'Tambah Stok') {
    $badge_class = "badge-tambah-stok";
} elseif ($expense['kategori'] == 'Bayar Hutang Produk' || $expense['kategori_transaksi'] == 'Bayar Hutang Produk') {
    $badge_class = "badge-hutang-produk";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pengeluaran #<?= $id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            --success-color: #4CAF50;
            --danger-color: #F44336;
            --danger-light: #ffecec;
            --warning-color: #FFA726;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background-color: var(--light-gray);
            color: var(--text-dark);
            line-height: 1.6;
            padding: 20px;
        }
        
        .content {
            margin-left: 280px;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            background-color: var(--primary-purple);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .btn:hover {
            background-color: var(--accent-purple);
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .btn i {
            margin-right: 8px;
        }
        
        .btn-secondary {
            background-color: #757575;
        }
        
        .btn-secondary:hover {
            background-color: #616161;
        }
        
        .btn-outline-light {
            color: white;
            border: 1px solid white;
            background: transparent;
        }
        
        .btn-outline-light:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .back-btn {
            margin-bottom: 25px;
        }
        
        .expense-container {
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .expense-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 20px rgba(0, 0, 0, 0.12);
        }
        
        .expense-header {
            background: linear-gradient(135deg, var(--primary-purple), var(--accent-purple));
            color: white;
            padding: 25px;
            text-align: center;
            position: relative;
        }
        
        .expense-header h2 {
            font-size: 24px;
            font-weight: 600;
            margin: 0;
        }
        
        .expense-number {
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 5px 15px;
            font-size: 14px;
            margin-top: 8px;
            display: inline-block;
        }
        
        .expense-body {
            padding: 35px;
        }
        
        .expense-section {
            margin-bottom: 35px;
        }
        
        .section-title {
            color: var(--primary-purple);
            font-weight: 600;
            font-size: 18px;
            margin-bottom: 20px;
            border-left: 4px solid var(--primary-purple);
            padding-left: 12px;
        }
        
        .expense-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .info-item {
            margin-bottom: 10px;
        }
        
        .info-label {
            font-weight: 500;
            color: #666;
            font-size: 14px;
            display: block;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-weight: 500;
            font-size: 16px;
        }
        
        .badge-kategori {
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            font-weight: 500;
            display: inline-block;
            min-width: 120px;
            text-align: center;
        }
        
        .badge-operasional {
            background-color: rgba(126, 87, 194, 0.15);
            color: #5E35B1;
        }
        
        .badge-gaji {
            background-color: rgba(76, 175, 80, 0.15);
            color: #43A047;
        }
        
        .badge-kasbon {
            background-color: rgba(255, 152, 0, 0.15);
            color: #F57C00;
        }
        
        .badge-makan {
            background-color: rgba(3, 169, 244, 0.15);
            color: #0288D1;
        }
        
        .badge-produk {
            background-color: rgba(233, 30, 99, 0.15);
            color: #C2185B;
        }
        
        .badge-tambah-stok {
            background-color: rgba(0, 188, 212, 0.15);
            color: #00838F;
        }
        
        .badge-hutang-produk {
            background-color: rgba(156, 39, 176, 0.15);
            color: #7B1FA2;
        }
        
        .badge-lainnya {
            background-color: rgba(158, 158, 158, 0.15);
            color: #616161;
        }
        
        .summary-section {
            background-color: var(--light-purple);
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 14px;
        }
        
        .total-row {
            font-size: 20px;
            font-weight: 600;
            color: var(--accent-purple);
            padding-top: 10px;
            margin-top: 10px;
            border-top: 1px dashed var(--border-color);
        }
        
        .expense-footer {
            display: flex;
            justify-content: space-between;
            padding: 22px 30px;
            background-color: #f9f9f9;
            border-top: 1px solid var(--border-color);
        }
        
        .source-badge {
            display: inline-flex;
            align-items: center;
            background-color: var(--light-purple);
            color: var(--accent-purple);
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .source-badge i {
            margin-right: 5px;
        }
        
        @media (max-width: 768px) {
            .expense-info {
                grid-template-columns: 1fr;
            }
            
            .expense-footer {
                flex-direction: column;
                gap: 15px;
            }
        }
        
        @media print {
            .back-btn, .print-btn {
                display: none;
            }
            
            body {
                background-color: white;
                padding: 0;
                margin: 0;
            }
            
            .container {
                width: 100%;
                max-width: none;
            }
            
            .expense-container {
                box-shadow: none;
            }
        }
        
        /* Mobile responsive */
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
        <div class="container">
            <a href="history_pengeluaran.php" class="btn btn-secondary back-btn">
                <i class="fas fa-arrow-left"></i> Kembali ke Pengeluaran
            </a>
            
            <div class="expense-container">
                <div class="expense-header">
                    <h2>Detail Pengeluaran</h2>
                    <div class="expense-number">ID #<?= $id ?></div>
                </div>
                
                <div class="expense-body">
                    <div class="expense-section">
                        <h3 class="section-title">Informasi Pengeluaran</h3>
                        <div class="expense-info">
                            <div class="info-item">
                                <span class="info-label">Tanggal</span>
                                <span class="info-value">
                                    <i class="far fa-calendar-alt"></i> 
                                    <?= date('d F Y', strtotime($expense['tanggal'])) ?>
                                </span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Kategori</span>
                                <span class="info-value">
                                    <span class="badge-kategori <?= $badge_class ?>">
                                        <?= htmlspecialchars($expense['kategori_transaksi']) ?>
                                    </span>
                                </span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Jumlah</span>
                                <span class="info-value text-danger fw-bold">
                                    Rp <?= number_format($expense['jumlah'], 0, ',', '.') ?>
                                </span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Sumber Data</span>
                                <span class="info-value">
                                    <i class="fas fa-database"></i> 
                                    <?= $source == 'pengeluaran' ? 'Tabel Pengeluaran' : 'Tabel Piutang Cair' ?>
                                </span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Waktu Dibuat</span>
                                <span class="info-value">
                                    <i class="far fa-clock"></i> 
                                    <?= isset($expense['created_at']) ? date('d F Y H:i', strtotime($expense['created_at'])) : '-' ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="expense-section">
                            <h3 class="section-title">Keterangan</h3>
                            <div class="p-3 bg-light rounded">
                                <?php if (strpos($expense['keterangan'], 'Gaji Asli') !== false): ?>
                                    <?php
                                    preg_match('/Gaji Asli: Rp ([0-9.,]+) - Kasbon: Rp ([0-9.,]+)/', $expense['keterangan'], $matches);
                                    if (count($matches) >= 3):
                                        $keterangan_parts = explode(':', $expense['keterangan'], 2);
                                        $main_keterangan = trim($keterangan_parts[0]);
                                    ?>
                                    <div class="fw-medium"><?= htmlspecialchars($main_keterangan) ?></div>
                                    <div class="mt-2">
                                        <div><i class="fas fa-money-bill-wave text-success me-1"></i> Gaji Asli: Rp <?= $matches[1] ?></div>
                                        <div><i class="fas fa-hand-holding-usd text-warning me-1"></i> Kasbon: Rp <?= $matches[2] ?></div>
                                        <div><i class="fas fa-wallet text-primary me-1"></i> Dibayarkan: Rp <?= number_format((int)str_replace('.', '', $matches[1]) - (int)str_replace('.', '', $matches[2]), 0, ',', '.') ?></div>
                                    </div>
                                    <?php else: ?>
                                        <?= nl2br(htmlspecialchars($expense['keterangan'])) ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?= nl2br(htmlspecialchars($expense['keterangan'])) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="summary-section">
                        <div class="summary-row total-row">
                            <span>Total Pengeluaran</span>
                            <span class="text-danger">Rp <?= number_format($expense['jumlah'], 0, ',', '.') ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="expense-footer">
                    <div class="source-info">
                        <div class="source-badge">
                            <i class="fas fa-database"></i>
                            Sumber: <?= $source == 'pengeluaran' ? 'Tabel Pengeluaran' : 'Tabel Piutang Cair' ?>
                        </div>
                    </div>
                    
                    <button onclick="window.print()" class="btn btn-primary print-btn">
                        <i class="fas fa-print"></i> Cetak Detail
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
    
    <script>
        // Add current year to footer
        document.addEventListener('DOMContentLoaded', function() {
            const year = new Date().getFullYear();
            if (document.querySelector('.year')) {
                document.querySelector('.year').textContent = year;
            }
        });
    </script>
</body>
</html>