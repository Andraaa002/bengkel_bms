<?php
session_start();

// Check if logged in and admin
if (!isset($_SESSION['admin']['logged_in']) || $_SESSION['admin']['logged_in'] !== true) {
  header("Location: ../login.php");
  exit();
}

include '../config.php'; // Database connection

// Set timezone to Jakarta/Indonesia
date_default_timezone_set('Asia/Jakarta');

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="History_Pengeluaran_' . date('m-Y') . '.xls"');
header('Cache-Control: max-age=0');

// Get filter parameters
$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-t');
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'semua';

// Format period for display
$periode = date('d/m/Y', strtotime($tgl_awal)) . ' - ' . date('d/m/Y', strtotime($tgl_akhir));

// Build where clause for filters (pengeluaran table)
$where_clause = " WHERE 1=1 ";

// Filter berdasarkan rentang tanggal (pengeluaran table)
if (!empty($tgl_awal) && !empty($tgl_akhir)) {
    $where_clause .= " AND DATE(p.tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir' ";
}

// Filter berdasarkan tab aktif (pengeluaran table)
if ($active_tab == 'operasional') {
    $where_clause .= " AND p.kategori NOT IN ('Kasbon Karyawan', 'Uang Makan', 'Gaji Karyawan', 'Pembelian Sparepart', 'Pembelian Barang', 'Bayar Hutang Produk') 
                        AND p.keterangan NOT LIKE '%produk:%' 
                        AND p.keterangan NOT LIKE '%produk baru:%' 
                        AND p.keterangan NOT LIKE 'Penambahan stok produk:%' ";
    $title_suffix = "Operasional";
} elseif ($active_tab == 'karyawan') {
    $where_clause .= " AND p.kategori IN ('Kasbon Karyawan', 'Uang Makan', 'Gaji Karyawan') ";
    $title_suffix = "Karyawan";
} elseif ($active_tab == 'produk') {
    $where_clause .= " AND (
                            p.kategori IN ('Pembelian Sparepart', 'Pembelian Barang') OR 
                            p.keterangan LIKE '%produk:%' OR 
                            p.keterangan LIKE '%produk baru:%' OR 
                            p.keterangan LIKE 'Penambahan stok produk:%'
                        ) ";
    $title_suffix = "Produk & Sparepart";
} elseif ($active_tab == 'bayar_hutang_produk') {
    $where_clause .= " AND p.kategori = 'Bayar Hutang Produk' ";
    $title_suffix = "Bayar Hutang Produk";
} else {
    $title_suffix = "Semua Kategori";
}

// Build where clause for piutang_cair table
$pc_where_clause = " WHERE pc.transaksi_id = '-1'"; // Only include product debt payments (not transaction debts)

// Filter berdasarkan rentang tanggal (piutang_cair table)
if (!empty($tgl_awal) && !empty($tgl_akhir)) {
    $pc_where_clause .= " AND DATE(pc.tanggal_bayar) BETWEEN '$tgl_awal' AND '$tgl_akhir' ";
}

// Only include Bayar Hutang Produk tab for piutang_cair
$include_piutang_cair = ($active_tab == 'semua' || $active_tab == 'bayar_hutang_produk');

// Query to get expenses from pengeluaran table
$query = "SELECT p.id, p.tanggal, p.kategori, p.jumlah, p.keterangan, p.created_at,
           CASE 
             WHEN p.keterangan LIKE 'Pembelian produk baru:%' THEN 'Pembelian Sparepart Baru'
             WHEN p.keterangan LIKE '%produk baru:%' AND p.keterangan LIKE '%pembayaran awal%' THEN 'Pembelian Sparepart Baru'
             WHEN p.keterangan LIKE 'Penambahan stok produk:%' THEN 'Tambah Stok'
             WHEN p.kategori = 'Pembelian Sparepart' THEN 'Pembelian Sparepart Baru'
             WHEN p.kategori = 'Pembelian Barang' THEN 'Pembelian Sparepart Baru'
             WHEN p.kategori = 'Bayar Hutang Produk' THEN 'Bayar Hutang Produk'
             ELSE p.kategori
           END as kategori_transaksi,
           'pengeluaran' as source_table
          FROM pengeluaran p
          $where_clause";

// Query to get payments from piutang_cair table
if ($include_piutang_cair) {
    $pc_query = "SELECT 
                pc.id, 
                pc.tanggal_bayar as tanggal, 
                'Bayar Hutang Produk' as kategori, 
                pc.jumlah_bayar as jumlah, 
                pc.keterangan, 
                pc.created_at,
                'Bayar Hutang Produk' as kategori_transaksi,
                'piutang_cair' as source_table
              FROM piutang_cair pc
              $pc_where_clause";
    
    // Combine the two queries with UNION
    $query = "($query) UNION ($pc_query) ORDER BY tanggal DESC, id DESC";
} else {
    // Add ordering if only using pengeluaran table
    $query .= " ORDER BY p.tanggal DESC, p.id DESC";
}

$result = mysqli_query($conn, $query);

// Hitung summary berdasarkan filter
$summary_query = "SELECT 
                  (SELECT SUM(p.jumlah) FROM pengeluaran p WHERE DATE(p.tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir') +
                  (SELECT COALESCE(SUM(pc.jumlah_bayar), 0) FROM piutang_cair pc WHERE pc.transaksi_id = '-1' AND DATE(pc.tanggal_bayar) BETWEEN '$tgl_awal' AND '$tgl_akhir') 
                  as total_pengeluaran,

                  (SELECT SUM(CASE WHEN p.kategori IN ('Kasbon Karyawan', 'Uang Makan', 'Gaji Karyawan') THEN p.jumlah ELSE 0 END) 
                   FROM pengeluaran p WHERE DATE(p.tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir') 
                  as total_karyawan,

                  (SELECT SUM(CASE WHEN p.kategori IN ('Pembelian Sparepart', 'Pembelian Barang') OR 
                              p.keterangan LIKE '%produk:%' OR p.keterangan LIKE '%produk baru:%' OR 
                              p.keterangan LIKE 'Penambahan stok produk:%' THEN p.jumlah ELSE 0 END) 
                   FROM pengeluaran p WHERE DATE(p.tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir')
                  as total_produk,

                  (SELECT SUM(CASE WHEN p.kategori = 'Bayar Hutang Produk' THEN p.jumlah ELSE 0 END)
                   FROM pengeluaran p WHERE DATE(p.tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir') +
                  (SELECT COALESCE(SUM(pc.jumlah_bayar), 0) 
                   FROM piutang_cair pc WHERE pc.transaksi_id = '-1' AND DATE(pc.tanggal_bayar) BETWEEN '$tgl_awal' AND '$tgl_akhir')
                  as total_bayar_hutang,

                  (SELECT SUM(CASE WHEN p.kategori NOT IN ('Kasbon Karyawan', 'Uang Makan', 'Gaji Karyawan', 'Pembelian Sparepart', 'Pembelian Barang', 'Bayar Hutang Produk') 
                       AND p.keterangan NOT LIKE '%produk:%' 
                       AND p.keterangan NOT LIKE '%produk baru:%' 
                       AND p.keterangan NOT LIKE 'Penambahan stok produk:%' 
                       THEN p.jumlah ELSE 0 END)
                   FROM pengeluaran p WHERE DATE(p.tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir')
                  as total_operasional,

                  (SELECT COUNT(*) FROM pengeluaran p WHERE DATE(p.tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir') +
                  (SELECT COUNT(*) FROM piutang_cair pc WHERE pc.transaksi_id = '-1' AND DATE(pc.tanggal_bayar) BETWEEN '$tgl_awal' AND '$tgl_akhir')
                  as jumlah_transaksi";

$summary_result = mysqli_query($conn, $summary_query);

// Error handling for summary_query
if ($summary_result === false) {
    error_log("Summary query failed: " . mysqli_error($conn) . " SQL: " . $summary_query);
    $total_pengeluaran = 0;
    $total_karyawan = 0;
    $total_produk = 0;
    $total_bayar_hutang = 0;
    $total_operasional = 0;
    $jumlah_transaksi = 0;
} else {
    $summary_data = mysqli_fetch_assoc($summary_result);
    $total_pengeluaran = $summary_data['total_pengeluaran'] ?: 0;
    $total_karyawan = $summary_data['total_karyawan'] ?: 0;
    $total_produk = $summary_data['total_produk'] ?: 0;
    $total_bayar_hutang = $summary_data['total_bayar_hutang'] ?: 0;
    $total_operasional = $summary_data['total_operasional'] ?: 0;
    $jumlah_transaksi = $summary_data['jumlah_transaksi'] ?: 0;
}

// Get category breakdown if it's the "semua" tab
$kategori_breakdown = [];
if ($active_tab == 'semua') {
    // First part: Get from pengeluaran table
    $kategori_breakdown_query = "
        SELECT 
            display_kategori,
            SUM(jumlah) as total,
            COUNT(*) as jumlah
        FROM (
            SELECT 
                CASE 
                    WHEN p.kategori IN ('Pembelian Sparepart', 'Pembelian Barang') 
                        OR p.keterangan LIKE 'Pembelian produk baru:%' 
                        OR (p.keterangan LIKE '%produk baru:%' AND p.keterangan LIKE '%pembayaran awal%')
                    THEN 'Pembelian Sparepart Baru'
                    WHEN p.keterangan LIKE 'Penambahan stok produk:%' THEN 'Tambah Stok'
                    WHEN p.kategori = 'Bayar Hutang Produk' THEN 'Bayar Hutang Produk'
                    ELSE p.kategori 
                END as display_kategori,
                p.jumlah,
                'pengeluaran' as source
            FROM pengeluaran p
            WHERE DATE(p.tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir'
            
            UNION ALL
            
            SELECT 
                'Bayar Hutang Produk' as display_kategori,
                pc.jumlah_bayar as jumlah,
                'piutang_cair' as source
            FROM piutang_cair pc
            WHERE pc.transaksi_id = '-1' AND DATE(pc.tanggal_bayar) BETWEEN '$tgl_awal' AND '$tgl_akhir'
        ) as combined
        GROUP BY display_kategori
        ORDER BY total DESC";
    
    $kategori_breakdown_result = mysqli_query($conn, $kategori_breakdown_query);
    
    if ($kategori_breakdown_result) {
        while ($row = mysqli_fetch_assoc($kategori_breakdown_result)) {
            $kategori_breakdown[] = $row;
        }
    }
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
    <x:Name>History Pengeluaran</x:Name>
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
    <div class="title">History Pengeluaran Bengkel BMS - <?= $title_suffix ?></div>
    <div class="subtitle">PERIODE: <?= $periode ?></div>
    <div class="subtitle">Tanggal Export: <?= date('d/m/Y H:i') ?></div>
    <br>
    
    <table border="1">
        <tr>
            <th>No</th>
            <th>Tanggal</th>
            <th>Kategori</th>
            <th>Jumlah</th>
            <th>Keterangan</th>
        </tr>
        
        <?php
        $no = 1;
        $total_amount = 0;
        
        if (mysqli_num_rows($result) > 0):
            while ($row = mysqli_fetch_assoc($result)):
                $total_amount += $row['jumlah'];
                
                // Determine cell background color based on category
                $kategori_bg = '';
                
                if (in_array($row['kategori'], ['Sewa Lahan', 'Token Listrik', 'Air', 'Internet', 'Lainnya'])) {
                    $kategori_bg = ' bgcolor="#EDE7F6"'; // Light purple for operational
                } elseif ($row['kategori'] == 'Gaji Karyawan' || $row['kategori_transaksi'] == 'Gaji Karyawan') {
                    $kategori_bg = ' bgcolor="#E8F5E9"'; // Light green for salary
                } elseif ($row['kategori'] == 'Kasbon Karyawan' || $row['kategori_transaksi'] == 'Kasbon Karyawan') {
                    $kategori_bg = ' bgcolor="#FFF9C4"'; // Light yellow for kasbon
                } elseif ($row['kategori'] == 'Uang Makan' || $row['kategori_transaksi'] == 'Uang Makan') {
                    $kategori_bg = ' bgcolor="#E3F2FD"'; // Light blue for meals
                } elseif ($row['kategori_transaksi'] == 'Pembelian Sparepart Baru' || 
                        $row['kategori'] == 'Pembelian Sparepart' || 
                        $row['kategori'] == 'Pembelian Barang') {
                    $kategori_bg = ' bgcolor="#FFEBEE"'; // Light red for products
                } elseif ($row['kategori_transaksi'] == 'Tambah Stok') {
                    $kategori_bg = ' bgcolor="#E0F7FA"'; // Light cyan for stock
                } elseif ($row['kategori_transaksi'] == 'Bayar Hutang Produk' || $row['kategori'] == 'Bayar Hutang Produk') {
                    $kategori_bg = ' bgcolor="#F3E5F5"'; // Light purple for product debt
                }
        ?>
        <tr>
            <td align="center"><?= $no++ ?></td>
            <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
            <td<?= $kategori_bg ?>><?= $row['kategori_transaksi'] ?></td>
            <td align="right" bgcolor="#FFEBEE">Rp <?= number_format($row['jumlah'], 0, ',', '.') ?></td>
            <td><?= $row['keterangan'] ?></td>
        </tr>
        <?php 
            endwhile;
        else:
        ?>
        <tr>
            <td colspan="5" align="center">Tidak ada data pengeluaran untuk periode ini</td>
        </tr>
        <?php endif; ?>
        
        <!-- Total Row -->
        <tr>
            <td colspan="3" align="right" bgcolor="#EEEEEE"><strong>TOTAL PENGELUARAN</strong></td>
            <td align="right" bgcolor="#FFCDD2"><strong>Rp <?= number_format($total_amount, 0, ',', '.') ?></strong></td>
            <td bgcolor="#EEEEEE"></td>
        </tr>
    </table>
    
    <br>
    <div class="summary-title">RINGKASAN PENGELUARAN:</div>
    <table border="1" style="width: 50%;">
        <tr>
            <td bgcolor="#E1F5FE"><b>Total Transaksi Pengeluaran:</b></td>
            <td bgcolor="#E1F5FE"><?= $jumlah_transaksi ?> transaksi</td>
        </tr>
        <tr>
            <td bgcolor="#FFCDD2"><b>Uang Kas Keluar (Total):</b></td>
            <td align="right" bgcolor="#FFCDD2"><b>Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?></b></td>
        </tr>
        <tr>
            <td bgcolor="#EDE7F6"><b>Pengeluaran Operasional:</b></td>
            <td align="right" bgcolor="#EDE7F6"><b>Rp <?= number_format($total_operasional, 0, ',', '.') ?></b></td>
        </tr>
        <tr>
            <td bgcolor="#E8F5E9"><b>Pengeluaran Karyawan:</b></td>
            <td align="right" bgcolor="#E8F5E9"><b>Rp <?= number_format($total_karyawan, 0, ',', '.') ?></b></td>
        </tr>
        <tr>
            <td bgcolor="#FFF3E0"><b>Pengeluaran Produk/Sparepart:</b></td>
            <td align="right" bgcolor="#FFF3E0"><b>Rp <?= number_format($total_produk, 0, ',', '.') ?></b></td>
        </tr>
        <tr>
            <td bgcolor="#F3E5F5"><b>Bayar Hutang Produk:</b></td>
            <td align="right" bgcolor="#F3E5F5"><b>Rp <?= number_format($total_bayar_hutang, 0, ',', '.') ?></b></td>
        </tr>
    </table>
    
    <?php if ($active_tab == 'semua' && count($kategori_breakdown) > 0): ?>
    <br>
    <div class="summary-title">RINCIAN PER KATEGORI:</div>
    <table border="1" style="width: 70%;">
        <tr>
            <th>No</th>
            <th>Kategori</th>
            <th>Jumlah Transaksi</th>
            <th>Total Pengeluaran</th>
            <th>Persentase</th>
        </tr>
        <?php 
        $no = 1;
        foreach ($kategori_breakdown as $kategori):
            $percentage = ($total_pengeluaran > 0) ? ($kategori['total'] / $total_pengeluaran * 100) : 0;
            
            // Get the category name from the modified query result
            $displayKategori = $kategori['display_kategori'];
            
            // Determine cell background based on category
            $kategori_bg = '';
                
            if (in_array($displayKategori, ['Sewa Lahan', 'Token Listrik', 'Air', 'Internet', 'Lainnya'])) {
                $kategori_bg = ' bgcolor="#EDE7F6"'; // Light purple for operational
            } elseif ($displayKategori == 'Gaji Karyawan') {
                $kategori_bg = ' bgcolor="#E8F5E9"'; // Light green for salary
            } elseif ($displayKategori == 'Kasbon Karyawan') {
                $kategori_bg = ' bgcolor="#FFF9C4"'; // Light yellow for kasbon
            } elseif ($displayKategori == 'Uang Makan') {
                $kategori_bg = ' bgcolor="#E3F2FD"'; // Light blue for meals
            } elseif ($displayKategori == 'Pembelian Sparepart Baru') {
                $kategori_bg = ' bgcolor="#FFEBEE"'; // Light red for products
            } elseif ($displayKategori == 'Tambah Stok') {
                $kategori_bg = ' bgcolor="#E0F7FA"'; // Light cyan for stock
            } elseif ($displayKategori == 'Bayar Hutang Produk') {
                $kategori_bg = ' bgcolor="#F3E5F5"'; // Light purple for product debt
            } else {
                $kategori_bg = ' bgcolor="#F5F5F5"'; // Light gray for others
            }
        ?>
        <tr>
            <td align="center"><?= $no++ ?></td>
            <td<?= $kategori_bg ?>><?= $displayKategori ?></td>
            <td align="center"><?= $kategori['jumlah'] ?> transaksi</td>
            <td align="right">Rp <?= number_format($kategori['total'], 0, ',', '.') ?></td>
            <td align="right"><?= number_format($percentage, 1) ?>%</td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</body>
</html>