<?php
session_start();

// Cek apakah sudah login dan sebagai admin 
if (!isset($_SESSION['admin']['logged_in']) || $_SESSION['admin']['logged_in'] !== true) {
  header("Location: ../login.php");
  exit();
}

include '../config.php'; // Database connection

// Set timezone to Jakarta/Indonesia
date_default_timezone_set('Asia/Jakarta');

// Get date range from URL params
$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-t');

// Format for titles
$periode_text = '';
if (date('Y-m', strtotime($tgl_awal)) == date('Y-m', strtotime($tgl_akhir))) {
    $periode_text = 'Periode: ' . date('F Y', strtotime($tgl_awal));
} else {
    $periode_text = 'Periode: ' . date('d/m/Y', strtotime($tgl_awal)) . ' - ' . date('d/m/Y', strtotime($tgl_akhir));
}

// Set headers for Excel download
$filename = 'Laporan_Transaksi_' . date('Ymd', strtotime($tgl_awal)) . '_' . date('Ymd', strtotime($tgl_akhir)) . '.xls';
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Build where clause for filters
$where_clause = " WHERE 1=1 ";

// Filter berdasarkan rentang tanggal
if (!empty($tgl_awal) && !empty($tgl_akhir)) {
    $where_clause .= " AND DATE(t.tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir' ";
}

// Query untuk mendapatkan data transaksi
$query = "SELECT t.id, t.tanggal, t.total, t.pendapatan, t.hutang, u.nama AS kasir,
          SUM(td.jumlah * IFNULL(p.harga_beli, 0)) AS total_harga_beli,
          SUM(td.subtotal) AS total_harga_jual,
          (SUM(td.subtotal) - SUM(td.jumlah * IFNULL(p.harga_beli, 0))) AS keuntungan
          FROM transaksi t
          JOIN karyawan u ON t.kasir = u.username
          JOIN transaksi_detail td ON t.id = td.transaksi_id
          LEFT JOIN produk p ON td.produk_id = p.id
          $where_clause
          GROUP BY t.id, t.tanggal, t.total, u.nama
          ORDER BY t.tanggal DESC";
$result = mysqli_query($conn, $query);

// Summary query
$summary_query = "SELECT 
                COUNT(DISTINCT t.id) AS total_transaksi,
                SUM(t.total) AS total_pendapatan,
                SUM(t.pendapatan) AS total_kas,
                SUM(t.hutang) AS total_hutang,
                SUM((SELECT SUM(td.subtotal) - SUM(td.jumlah * IFNULL(p.harga_beli, 0)) 
                     FROM transaksi_detail td 
                     LEFT JOIN produk p ON td.produk_id = p.id 
                     WHERE td.transaksi_id = t.id)) AS total_laba
                FROM transaksi t
                $where_clause";
$summary_result = mysqli_query($conn, $summary_query);

if (!$summary_result) {
    error_log("Summary query failed: " . mysqli_error($conn) . " SQL: " . $summary_query);
    $summary_data = [
        'total_transaksi' => 0,
        'total_pendapatan' => 0,
        'total_kas' => 0,
        'total_hutang' => 0,
        'total_laba' => 0
    ];
} else {
    $summary_data = mysqli_fetch_assoc($summary_result);
}

// Calculate profit margin
$profit_margin = ($summary_data['total_pendapatan'] > 0) ? 
                 ($summary_data['total_laba'] / $summary_data['total_pendapatan'] * 100) : 0;
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
    <x:Name>Laporan Transaksi</x:Name>
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
    background-color: #7E57C2;
    color: white;
    font-weight: bold;
    text-align: center;
}
.cash-positive {
    background-color: #E8F5E9;
    color: #2E7D32;
}
.debt-warning {
    background-color: #FFEBEE;
    color: #D32F2F;
}
.summary-header {
    background-color: #7E57C2;
    color: white;
    font-weight: bold;
}
.summary-row-purple {
    background-color: #EDE7F6;
}
.summary-row-indigo {
    background-color: #E8EAF6;
}
.summary-row-teal {
    background-color: #E0F2F1;
}
.summary-row-red {
    background-color: #FFEBEE;
}
.summary-row-green {
    background-color: #E8F5E9;
}
.summary-row-blue {
    background-color: #E1F5FE;
}
</style>
</head>

<body>
    <div class="title">Laporan Transaksi Penjualan BMS Bengkel</div>
    <div class="subtitle"><?= $periode_text ?></div>
    <div class="subtitle">Tanggal Export: <?= date('d/m/Y H:i') ?></div>
    <br>
    
    <table border="1">
        <tr>
            <th>ID</th>
            <th>Tanggal</th>
            <th>Kasir</th>
            <th>Total Pendapatan</th>
            <th>Uang Masuk</th>
            <th>Piutang</th>
            <th>Harga Modal</th>
            <th>Laba Kotor</th>
            <th>Margin (%)</th>
        </tr>
        
        <?php
        $no = 1;
        while ($row = mysqli_fetch_assoc($result)):
            // Calculate profit margin for each transaction
            $transaction_margin = ($row['total'] > 0) ? 
                              ($row['keuntungan'] / $row['total'] * 100) : 0;
        ?>
        <tr>
            <td align="center"><?= $row['id'] ?></td>
            <td align="center"><?= date('d/m/Y H:i', strtotime($row['tanggal'])) ?></td>
            <td><?= $row['kasir'] ?></td>
            <td align="right" style="color:#7E57C2;"><b>Rp <?= number_format($row['total'], 0, ',', '.') ?></b></td>
            <td align="right" style="color:#00897B;">Rp <?= number_format($row['pendapatan'], 0, ',', '.') ?></td>
            
            <?php if ($row['hutang'] > 0): ?>
            <td align="right" class="debt-warning">Rp <?= number_format($row['hutang'], 0, ',', '.') ?></td>
            <?php else: ?>
            <td align="right" class="cash-positive">Rp <?= number_format($row['hutang'], 0, ',', '.') ?></td>
            <?php endif; ?>
            
            <td align="right" style="color:#757575;">Rp <?= number_format($row['total_harga_beli'], 0, ',', '.') ?></td>
            <td align="right" class="cash-positive">Rp <?= number_format($row['keuntungan'], 0, ',', '.') ?></td>
            <td align="right"><?= number_format($transaction_margin, 2) ?>%</td>
        </tr>
        <?php endwhile; ?>
    </table>
    
    <br>
    <div class="title">RINGKASAN TRANSAKSI:</div>
    <table border="1" style="width: 50%;">
        <tr>
            <td class="summary-header" colspan="2">RINGKASAN TRANSAKSI</td>
        </tr>
        <tr class="summary-row-purple">
            <td><b>Total Transaksi:</b></td>
            <td><?= number_format($summary_data['total_transaksi']) ?> transaksi</td>
        </tr>
        <tr class="summary-row-indigo">
            <td><b>Total Pendapatan:</b></td>
            <td align="right"><b>Rp <?= number_format($summary_data['total_pendapatan'], 0, ',', '.') ?></b></td>
        </tr>
        <tr class="summary-row-teal">
            <td><b>Total Uang Masuk (Kas):</b></td>
            <td align="right">Rp <?= number_format($summary_data['total_kas'], 0, ',', '.') ?></td>
        </tr>
        <tr class="summary-row-red">
            <td><b>Total Piutang:</b></td>
            <td align="right">Rp <?= number_format($summary_data['total_hutang'], 0, ',', '.') ?></td>
        </tr>
        <tr class="summary-row-green">
            <td><b>Total Laba Kotor:</b></td>
            <td align="right">Rp <?= number_format($summary_data['total_laba'], 0, ',', '.') ?></td>
        </tr>
        <tr class="summary-row-blue">
            <td><b>Margin Keuntungan:</b></td>
            <td align="right"><?= number_format($profit_margin, 2) ?> %</td>
        </tr>
    </table>
    <br>
</body>
</html>