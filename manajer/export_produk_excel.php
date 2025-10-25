<?php
session_start();

// Check if logged in and manajer
if (!isset($_SESSION['manajer']['logged_in']) || $_SESSION['manajer']['logged_in'] !== true) {
  header("Location: ../login.php");
  exit();
}

include '../config.php'; // Database connection

// Set timezone to Jakarta/Indonesia
date_default_timezone_set('Asia/Jakarta');

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="Data_Produk_' . date('m-Y') . '.xls"');
header('Cache-Control: max-age=0');

// Define stock threshold
$stock_threshold = 5; // Products with stock ≤ 5 are considered "low stock" (UPDATED FROM 10 TO 5)

// Get current month and year for the title and bulan parameter
$bulan_tahun = date('F Y'); // Format: April 2025
$bulan = date('Y-m'); // Format: 2025-04 (for database queries if needed)

// Fetch products with category join
$query = "
    SELECT 
        produk.id, 
        produk.nama, 
        produk.harga_beli, 
        produk.harga_jual, 
        produk.stok, 
        produk.hutang_sparepart, 
        produk.nominal_hutang, 
        kategori.nama_kategori
    FROM produk
    LEFT JOIN kategori ON produk.kategori_id = kategori.id
    ORDER BY produk.nama ASC
";
$result = mysqli_query($conn, $query);

// Get summary data with additional inventory and sales value calculations
// Updated to reflect new stock threshold of 5
$summary_query = "SELECT 
                 COUNT(*) as total_produk,
                 SUM(stok) as total_stok,
                 SUM(CASE WHEN stok <= $stock_threshold THEN 1 ELSE 0 END) as produk_stok_menipis,
                 SUM(CASE WHEN hutang_sparepart = 'Hutang' THEN 1 ELSE 0 END) as produk_hutang,
                 SUM(CASE WHEN hutang_sparepart = 'Hutang' THEN nominal_hutang ELSE 0 END) as total_hutang,
                 SUM(harga_beli * stok) as total_nilai_inventory,
                 SUM(harga_jual * stok) as total_nilai_jual
                 FROM produk";
$summary_result = mysqli_query($conn, $summary_query);
$summary = mysqli_fetch_assoc($summary_result);

// Calculate potential profit
$potential_profit = $summary['total_nilai_jual'] - $summary['total_nilai_inventory'];
$profit_percentage = ($summary['total_nilai_inventory'] > 0) ? 
                    ($potential_profit / $summary['total_nilai_inventory'] * 100) : 0;
?>

<html xmlns:o="urn:schemas-microsoft-com:office:office" 
      xmlns:x="urn:schemas-microsoft-com:office:excel" 
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="ProgId" content="Excel.Sheet">
<meta name="Generator" content="Microsoft Excel 11">
<!--[if gte mso 9]>
<xml>
 <x:ExcelWorkbook>
  <x:ExcelWorksheets>
   <x:ExcelWorksheet>
    <x:Name>Data Produk</x:Name>
    <x:WorksheetOptions>
     <x:DisplayGridlines/>
    </x:WorksheetOptions>
   </x:ExcelWorksheet>
  </x:ExcelWorksheets>
 </x:ExcelWorkbook>
</xml>
<![endif]-->
<style>
.title {
    font-weight: bold;
    font-size: 18px;
}
.subtitle {
    font-size: 14px;
}
.note {
    font-size: 12px;
    font-style: italic;
    color: #666;
}
.summary-title {
    font-weight: bold;
    font-size: 16px;
    margin-top: 20px;
}
.text-right {
    mso-number-format:"\#\,\#\#0\.00";
    text-align: right;
}
.num-format {
    mso-number-format:"\#\,\#\#0";
}
table, td, th {
    border: 1px solid black;
    border-collapse: collapse;
}
th {
    background-color: #f2f2f2;
    font-weight: bold;
    text-align: center;
}
</style>
</head>

<body>
    <div class="title">Data Produk Bengkel BMS</div>
    <div class="subtitle">PERIODE: <?= $bulan_tahun ?></div>
    <div class="subtitle">Tanggal Export: <?= date('d/m/Y H:i') ?></div>
    <div class="note">* Stok menipis: produk dengan stok kurang dari atau sama dengan <?= $stock_threshold ?> unit</div>
    <br>
    
    <table border="1">
        <tr>
            <th>No</th>
            <th>Nama Produk</th>
            <th>Kategori</th>
            <th>Harga Beli</th>
            <th>Harga Jual</th>
            <th>Stok</th>
            <th>Nilai Inventory (Modal)</th>
            <th>Nilai Jual</th>
            <th>Status Pembayaran</th>
            <th>Nominal Hutang</th>
        </tr>
        
        <?php
        $no = 1;
        while ($row = mysqli_fetch_assoc($result)):
            // Calculate values
            $inventory_value = $row['harga_beli'] * $row['stok'];
            $sales_value = $row['harga_jual'] * $row['stok'];
            
            // Stock status background color
            $stock_bg = '';
            if ($row['stok'] == 0) {
                $stock_bg = ' bgcolor="#FFCCCC"'; // Red for zero stock
            } elseif ($row['stok'] <= $stock_threshold) {
                $stock_bg = ' bgcolor="#FFECB3"'; // Orange for low stock
            }
            
            // Payment status background
            $payment_bg = '';
            if ($row['hutang_sparepart'] == 'Hutang') {
                $payment_bg = ' bgcolor="#FFCDD2"'; // Red for Hutang
            }
        ?>
        <tr>
            <td align="center"><?= $no++ ?></td>
            <td><?= $row['nama'] ?></td>
            <td><?= $row['nama_kategori'] ?: 'Tidak Terkategori' ?></td>
            <td align="right">Rp <?= number_format($row['harga_beli'], 0, ',', '.') ?></td>
            <td align="right">Rp <?= number_format($row['harga_jual'], 0, ',', '.') ?></td>
            <td align="center"<?= $stock_bg ?>><?= $row['stok'] ?></td>
            <td align="right" bgcolor="#E1F5FE">Rp <?= number_format($inventory_value, 0, ',', '.') ?></td>
            <td align="right" bgcolor="#E8F5E9">Rp <?= number_format($sales_value, 0, ',', '.') ?></td>
            <td align="center"<?= $payment_bg ?>><?= $row['hutang_sparepart'] ?></td>
            <td align="right"<?= $payment_bg ?>>Rp <?= number_format($row['nominal_hutang'], 0, ',', '.') ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
    
    <br>
    <div class="summary-title">RINGKASAN DATA PRODUK:</div>
    <table border="1" style="width: 50%;">
        <tr>
            <td><b>Total Produk:</b></td>
            <td><?= $summary['total_produk'] ?> produk</td>
        </tr>
        <tr>
            <td><b>Total Stok Tersedia:</b></td>
            <td><?= $summary['total_stok'] ?> unit</td>
        </tr>
        <tr>
            <td><b>Produk dengan Stok Menipis (≤ <?= $stock_threshold ?> unit):</b></td>
            <td<?= ($summary['produk_stok_menipis'] > 0) ? ' bgcolor="#FFECB3"' : '' ?>><?= $summary['produk_stok_menipis'] ?> produk</td>
        </tr>
        <tr>
            <td bgcolor="#E1F5FE"><b>Total Nilai Inventory (Modal):</b></td>
            <td align="right" bgcolor="#E1F5FE"><b>Rp <?= number_format($summary['total_nilai_inventory'], 0, ',', '.') ?></b></td>
        </tr>
        <tr>
            <td bgcolor="#E8F5E9"><b>Total Nilai Jual:</b></td>
            <td align="right" bgcolor="#E8F5E9"><b>Rp <?= number_format($summary['total_nilai_jual'], 0, ',', '.') ?></b></td>
        </tr>
        <tr>
            <td bgcolor="#FFF9C4"><b>Potensi Keuntungan:</b></td>
            <td align="right" bgcolor="#FFF9C4"><b>Rp <?= number_format($potential_profit, 0, ',', '.') ?> (<?= number_format($profit_percentage, 1) ?>%)</b></td>
        </tr>
        <tr>
            <td><b>Produk dengan Status Hutang:</b></td>
            <td<?= ($summary['produk_hutang'] > 0) ? ' bgcolor="#FFCDD2"' : '' ?>><?= $summary['produk_hutang'] ?> produk</td>
        </tr>
        <tr>
            <td><b>Total Nominal Hutang:</b></td>
            <td align="right"<?= ($summary['total_hutang'] > 0) ? ' bgcolor="#FFCDD2"' : '' ?>><b>Rp <?= number_format($summary['total_hutang'], 0, ',', '.') ?></b></td>
        </tr>
    </table>
</body>
</html>