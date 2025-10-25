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

// Get bulan parameter from URL
$bulan_filter = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');
$bulan_tahun = date('F Y', strtotime($bulan_filter . '-01')); // Format: April 2025

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="Riwayat_Pembayaran_' . $bulan_filter . '.xls"');
header('Cache-Control: max-age=0');

// Adjust query to filter by month
$filter_clause = "";
if (!empty($bulan_filter)) {
  $filter_clause = "WHERE DATE_FORMAT(pc.tanggal_bayar, '%Y-%m') = '$bulan_filter'";
}

// Get all payment records (both product debts and transaction debts)
$piutang_cair_query = "SELECT pc.*, 
                       CASE 
                         WHEN pc.transaksi_id = '-1' THEN 'Pembayaran Hutang Produk' 
                         ELSE CONCAT('Transaksi #', pc.transaksi_id) 
                       END as sumber,
                       CASE 
                         WHEN pc.transaksi_id = '-1' THEN 'Produk' 
                         ELSE 'Transaksi' 
                       END as tipe,
                       COALESCE(m.nama, a.nama, k.nama, 'System') as nama_user,
                       CASE 
                         WHEN pc.transaksi_id = '-1' THEN '-' 
                         ELSE t.nama_customer
                       END as nama_customer,
                       CASE 
                         WHEN pc.transaksi_id = '-1' THEN 'LUNAS'
                         WHEN t.status_hutang = 0 THEN 'LUNAS' 
                         ELSE 'HUTANG'
                       END as status_pembayaran
                       FROM piutang_cair pc
                       LEFT JOIN manajer m ON pc.created_by = m.id_manajer
                       LEFT JOIN admin a ON pc.created_by = a.id_admin
                       LEFT JOIN karyawan k ON pc.created_by = k.id_karyawan
                       LEFT JOIN transaksi t ON pc.transaksi_id = t.id
                       $filter_clause
                       ORDER BY pc.tanggal_bayar DESC, pc.id DESC";
$piutang_cair_result = mysqli_query($conn, $piutang_cair_query);

// Get statistics based on month filter
$stats_query = "SELECT 
               COUNT(id) as total_pembayaran,
               SUM(jumlah_bayar) as total_bayar,
               COUNT(CASE WHEN transaksi_id = '-1' THEN 1 END) as total_bayar_produk,
               COUNT(CASE WHEN transaksi_id != '-1' THEN 1 END) as total_bayar_transaksi,
               SUM(CASE WHEN transaksi_id = '-1' THEN jumlah_bayar ELSE 0 END) as total_nominal_produk,
               SUM(CASE WHEN transaksi_id != '-1' THEN jumlah_bayar ELSE 0 END) as total_nominal_transaksi
               FROM piutang_cair
               " . ($filter_clause ? str_replace('pc.', '', $filter_clause) : "");
$stats_result = mysqli_query($conn, $stats_query);

if (!$stats_result) {
  error_log("MySQL Stats Error: " . mysqli_error($conn));
  $stats_data = [
    'total_pembayaran' => 0,
    'total_bayar' => 0,
    'total_bayar_produk' => 0,
    'total_bayar_transaksi' => 0,
    'total_nominal_produk' => 0,
    'total_nominal_transaksi' => 0
  ];
} else {
  $stats_data = mysqli_fetch_assoc($stats_result);
}
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
    <x:Name>Riwayat Pembayaran</x:Name>
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
    background-color: #EF6C00;
    color: white;
    font-weight: bold;
    text-align: center;
}
.product-type {
    background-color: #E8F5E9;
    color: #198754;
}
.transaction-type {
    background-color: #E3F2FD;
    color: #0d6efd;
}
.status-lunas {
    background-color: #E8F5E9;
    color: #198754;
}
.status-hutang {
    background-color: #FFF3CD;
    color: #fd7e14;
}
.summary-header {
    background-color: #EF6C00;
    color: white;
    font-weight: bold;
}
.summary-row-orange {
    background-color: #FFF3E0;
}
.summary-row-green {
    background-color: #E8F5E9;
}
.summary-row-blue {
    background-color: #E3F2FD;
}
</style>
</head>

<body>
    <div class="title">Riwayat Pembayaran Hutang & Piutang Bengkel BMS</div>
    <div class="subtitle">PERIODE: <?= $bulan_tahun ?></div>
    <div class="subtitle">Tanggal Export: <?= date('d/m/Y H:i') ?></div>
    <br>
    
    <table border="1">
        <tr>
            <th>No</th>
            <th>Tanggal Bayar</th>
            <th>Tipe Pembayaran</th>
            <th>Customer</th>
            <th>Status</th>
            <th>Keterangan</th>
            <th>Jumlah Bayar</th>
            <th>Dibuat Oleh</th>
        </tr>
        
        <?php
        $no = 1;
        while ($row = mysqli_fetch_assoc($piutang_cair_result)):
            // Determine payment type style
            $type_class = $row['tipe'] == 'Produk' ? 'product-type' : 'transaction-type';
            $type_text = $row['tipe'] == 'Produk' ? 'Hutang Produk' : 'Piutang Transaksi';
            
            // Determine payment status style
            $status_class = $row['status_pembayaran'] == 'LUNAS' ? 'status-lunas' : 'status-hutang';
        ?>
        <tr>
            <td align="center"><?= $no++ ?></td>
            <td align="center"><?= date('d/m/Y', strtotime($row['tanggal_bayar'])) ?></td>
            <td class="<?= $type_class ?>" align="center"><?= $type_text ?></td>
            <td><?= $row['nama_customer'] ?></td>
            <td class="<?= $status_class ?>" align="center"><?= $row['status_pembayaran'] ?></td>
            <td><?= $row['keterangan'] ?></td>
            <td align="right">Rp <?= number_format($row['jumlah_bayar'], 0, ',', '.') ?></td>
            <td><?= $row['nama_user'] ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
    
    <br>
    <div class="title">RINGKASAN PEMBAYARAN:</div>
    <table border="1" style="width: 50%;">
        <tr>
            <td class="summary-header" colspan="2">RINGKASAN PEMBAYARAN</td>
        </tr>
        <tr>
            <td><b>Total Pembayaran:</b></td>
            <td><?= $stats_data['total_pembayaran'] ?> transaksi</td>
        </tr>
        <tr class="summary-row-orange">
            <td><b>Total Nominal Pembayaran:</b></td>
            <td align="right"><b>Rp <?= number_format($stats_data['total_bayar'], 0, ',', '.') ?></b></td>
        </tr>
        <tr class="summary-row-green">
            <td><b>Pembayaran Hutang Produk:</b></td>
            <td align="right">Rp <?= number_format($stats_data['total_nominal_produk'], 0, ',', '.') ?> 
                (<?= $stats_data['total_bayar_produk'] ?> transaksi)</td>
        </tr>
        <tr class="summary-row-blue">
            <td><b>Pembayaran Piutang Transaksi:</b></td>
            <td align="right">Rp <?= number_format($stats_data['total_nominal_transaksi'], 0, ',', '.') ?> 
                (<?= $stats_data['total_bayar_transaksi'] ?> transaksi)</td>
        </tr>
    </table>
</body>
</html>