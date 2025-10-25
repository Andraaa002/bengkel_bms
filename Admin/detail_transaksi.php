<?php
include '../config.php';
session_start();

// Check if user is logged in as admin
if (!isset($_SESSION['admin']['logged_in']) || $_SESSION['admin']['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['id'])) {
    echo "<script>alert('ID Transaksi tidak ditemukan.'); window.location='data_transaksi.php';</script>";
    exit;
}

$id = intval($_GET['id']);

// Ambil data transaksi utama
$transaksiQuery = $conn->query("SELECT * FROM transaksi WHERE id = $id");
if ($transaksiQuery->num_rows == 0) {
    echo "<script>alert('Transaksi tidak ditemukan.'); window.location='data_transaksi.php';</script>";
    exit;
}
$transaksi = $transaksiQuery->fetch_assoc();

// Ambil detail produk dalam transaksi
$detailQuery = $conn->query("
    SELECT td.*, p.nama AS nama_produk 
    FROM transaksi_detail td
    JOIN produk p ON td.produk_id = p.id
    WHERE td.transaksi_id = $id
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Transaksi #<?= $id ?></title>
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
        
        .btn-success {
            background-color: var(--success-color);
        }
        
        .btn-success:hover {
            background-color: #388E3C;
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
        
        .invoice-container {
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .invoice-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 20px rgba(0, 0, 0, 0.12);
        }
        
        .invoice-header {
            background: linear-gradient(135deg, var(--primary-purple), var(--accent-purple));
            color: white;
            padding: 25px;
            text-align: center;
            position: relative;
        }
        
        .invoice-header h2 {
            font-size: 24px;
            font-weight: 600;
            margin: 0;
        }
        
        .invoice-number {
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 5px 15px;
            font-size: 14px;
            margin-top: 8px;
            display: inline-block;
        }
        
        .invoice-body {
            padding: 35px;
        }
        
        .invoice-section {
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
        
        .customer-info {
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
        
        .products-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .products-table th {
            background-color: var(--light-purple);
            color: var(--accent-purple);
            font-weight: 600;
            text-align: left;
            padding: 12px 15px;
            font-size: 14px;
        }
        
        .products-table td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }
        
        .products-table tr:last-child td {
            border-bottom: none;
        }
        
        .products-table tr:hover {
            background-color: var(--light-purple);
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
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
        
        .invoice-footer {
            display: flex;
            justify-content: space-between;
            padding: 22px 30px;
            background-color: #f9f9f9;
            border-top: 1px solid var(--border-color);
        }
        
        .payment-badge {
            display: inline-flex;
            align-items: center;
            background-color: var(--light-purple);
            color: var(--accent-purple);
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .payment-badge i {
            margin-right: 5px;
        }
        
        /* Styling for piutang */
        .piutang-badge {
            display: inline-flex;
            align-items: center;
            background-color: var(--danger-light);
            color: var(--danger-color);
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 700;
            margin-top: 15px;
        }
        
        .piutang-badge i {
            margin-right: 5px;
        }
        
        .piutang-info {
            background-color: var(--danger-light);
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            border-left: 4px solid var(--danger-color);
        }
        
        .piutang-title {
            color: var(--danger-color);
            font-weight: 600;
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .piutang-title i {
            margin-right: 8px;
        }
        
        .piutang-details {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
        }
        
        .payment-status {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .status-paid {
            background-color: rgba(76, 175, 80, 0.15);
            color: var(--success-color);
        }
        
        .status-partial {
            background-color: rgba(255, 167, 38, 0.15);
            color: var(--warning-color);
        }
        
        .status-unpaid {
            background-color: var(--danger-light);
            color: var(--danger-color);
        }
        
        @media (max-width: 768px) {
            .customer-info {
                grid-template-columns: 1fr;
            }
            
            .invoice-footer {
                flex-direction: column;
                gap: 15px;
            }
            
            .products-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
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
            
            .invoice-container {
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
            <a href="riwayat_transaksi.php" class="btn btn-secondary back-btn">
                <i class="fas fa-arrow-left"></i> Kembali ke Riwayat
            </a>
            
            <div class="invoice-container">
                <div class="invoice-header">
                    <h2>Detail Transaksi</h2>
                    <div class="invoice-number">Invoice #<?= $id ?></div>
                    
                    <?php if ($transaksi['hutang'] > 0): ?>
                    <div class="piutang-badge mt-2">
                        <i class="fas fa-exclamation-circle"></i>
                        Ada Piutang Rp <?= number_format($transaksi['hutang'], 0, ',', '.') ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="invoice-body">
                    <div class="invoice-section">
                        <h3 class="section-title">Informasi Transaksi</h3>
                        <div class="customer-info">
                            <div class="info-item">
                                <span class="info-label">Tanggal Transaksi</span>
                                <span class="info-value">
                                    <i class="far fa-calendar-alt"></i> 
                                    <?= date('d F Y', strtotime($transaksi['tanggal'])) ?>
                                </span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Kasir</span>
                                <span class="info-value">
                                    <i class="fas fa-user"></i> 
                                    <?= $transaksi['kasir'] ?>
                                </span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Nama Customer</span>
                                <span class="info-value">
                                    <i class="fas fa-user-circle"></i> 
                                    <?= htmlspecialchars($transaksi['nama_customer']) ?>
                                </span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">No. WhatsApp</span>
                                <span class="info-value">
                                    <i class="fab fa-whatsapp" style="color: #25D366;"></i> 
                                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $transaksi['no_whatsapp']) ?>" target="_blank" style="color: inherit; text-decoration: none;">
                                        <?= $transaksi['no_whatsapp'] ?>
                                    </a>
                                </span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Alamat</span>
                                <span class="info-value">
                                    <i class="fas fa-map-marker-alt"></i> 
                                    <?= $transaksi['alamat'] ?>
                                </span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Plat Nomor</span>
                                <span class="info-value">
                                    <i class="fas fa-motorcycle"></i> 
                                    <?= strtoupper($transaksi['plat_nomor_motor']) ?>
                                </span>
                            </div>
                        </div>
                        
                        <?php if ($transaksi['hutang'] > 0): ?>
                        <div class="piutang-info">
                            <div class="piutang-title">
                                <i class="fas fa-exclamation-triangle"></i>
                                Informasi Piutang
                            </div>
                            <p>Transaksi ini memiliki piutang yang belum dibayarkan oleh pelanggan.</p>
                            
                            <div class="piutang-details">
                                <div>
                                    <div><strong>Total Transaksi:</strong> Rp <?= number_format($transaksi['total'], 0, ',', '.') ?></div>
                                    <div><strong>Sudah Dibayar:</strong> Rp <?= number_format($transaksi['pendapatan'], 0, ',', '.') ?></div>
                                    <div><strong>Sisa Piutang:</strong> <span class="fw-bold text-danger">Rp <?= number_format($transaksi['hutang'], 0, ',', '.') ?></span></div>
                                </div>
                                
                                <div class="text-end">
                                    <div class="payment-status status-partial">
                                        <i class="fas fa-clock me-1"></i> Pembayaran Sebagian
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="invoice-section">
                        <h3 class="section-title">Detail Produk</h3>
                        <table class="products-table">
                            <thead>
                                <tr>
                                    <th width="5%">No</th>
                                    <th width="40%">Nama Produk</th>
                                    <th width="15%">Jumlah</th>
                                    <th width="20%">Harga</th>
                                    <th width="20%">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = 1;
                                $total = 0;
                                if ($detailQuery->num_rows > 0):
                                    while ($row = $detailQuery->fetch_assoc()):
                                        $total += $row['subtotal'];
                                ?>
                                <tr>
                                    <td class="text-center"><?= $no++ ?></td>
                                    <td><?= $row['nama_produk'] ?></td>
                                    <td class="text-center"><?= $row['jumlah'] ?></td>
                                    <td class="text-right">Rp <?= number_format($row['harga_satuan'], 0, ',', '.') ?></td>
                                    <td class="text-right">Rp <?= number_format($row['subtotal'], 0, ',', '.') ?></td>
                                </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                <tr>
                                    <td colspan="5" class="text-center">Tidak ada data produk</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        
                        <div class="summary-section">
                            <div class="summary-row">
                                <span>Total</span>
                                <span>Rp <?= number_format($transaksi['total'], 0, ',', '.') ?></span>
                            </div>
                            
                            <?php if ($transaksi['hutang'] > 0): ?>
                            <div class="summary-row">
                                <span>Dibayar</span>
                                <span>Rp <?= number_format($transaksi['pendapatan'], 0, ',', '.') ?></span>
                            </div>
                            <div class="summary-row" style="color: var(--danger-color); font-weight: bold;">
                                <span>Piutang</span>
                                <span>Rp <?= number_format($transaksi['hutang'], 0, ',', '.') ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="summary-row total-row">
                                <span>Total Pembayaran</span>
                                <span>Rp <?= number_format($transaksi['total'], 0, ',', '.') ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="invoice-footer">
                    <div class="payment-info">
                        <div class="payment-badge">
                            <i class="fas fa-money-bill-wave"></i>
                            Metode Pembayaran: <?= $transaksi['metode_pembayaran'] ?? 'Cash' ?>
                        </div>
                        
                        <?php if ($transaksi['hutang'] <= 0): ?>
                        <div class="payment-status status-paid mt-2">
                            <i class="fas fa-check-circle me-1"></i> Lunas
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <button onclick="window.print()" class="btn btn-primary print-btn">
                        <i class="fas fa-print"></i> Cetak Invoice
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