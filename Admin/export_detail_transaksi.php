<?php
session_start();

// Check if logged in as admin
if (!isset($_SESSION['admin']['logged_in']) || $_SESSION['admin']['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Include database connection
include '../config.php';

// Set timezone to Jakarta/Indonesia
date_default_timezone_set('Asia/Jakarta');

// Load FPDF library
require('../fpdf186/fpdf.php');

// Get filter parameters
$kasir = isset($_GET['kasir']) ? $_GET['kasir'] : '';

// Check which filter parameter is provided (bulan, bulan_filter, or date range)
if (isset($_GET['bulan']) && !empty($_GET['bulan'])) {
    // Month filter mode from export button in page header
    $bulan_filter = $_GET['bulan'];
    
    // Calculate first and last day of the month
    $first_day = date('Y-m-01', strtotime($bulan_filter . '-01'));
    $last_day = date('Y-m-t', strtotime($bulan_filter . '-01'));
    
    // Set the date variables
    $dari_tanggal = $first_day;
    $sampai_tanggal = $last_day;
    
    // Format period text for the report
    $periode_text = 'Periode: ' . date('F Y', strtotime($bulan_filter . '-01'));
    
    // Build where clause for month filter
    $where_clause = " WHERE DATE_FORMAT(t.tanggal, '%Y-%m') = '$bulan_filter' ";
} elseif (isset($_GET['bulan_filter']) && !empty($_GET['bulan_filter'])) {
    // Month filter mode from Filter Bulan tab
    $bulan_filter = $_GET['bulan_filter'];
    
    // Calculate first and last day of the month
    $first_day = date('Y-m-01', strtotime($bulan_filter . '-01'));
    $last_day = date('Y-m-t', strtotime($bulan_filter . '-01'));
    
    // Set the date variables
    $dari_tanggal = $first_day;
    $sampai_tanggal = $last_day;
    
    // Format period text for the report
    $periode_text = 'Periode: ' . date('F Y', strtotime($bulan_filter . '-01'));
    
    // Build where clause for month filter
    $where_clause = " WHERE DATE_FORMAT(t.tanggal, '%Y-%m') = '$bulan_filter' ";
} else {
    // Date range filter mode
    $dari_tanggal = isset($_GET['dari_tanggal']) ? $_GET['dari_tanggal'] : date('Y-m-d');
    $sampai_tanggal = isset($_GET['sampai_tanggal']) ? $_GET['sampai_tanggal'] : date('Y-m-d');
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dari_tanggal) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $sampai_tanggal)) {
        $_SESSION['message'] = "Format tanggal tidak valid!";
        $_SESSION['alert_type'] = "danger";
        header("Location: laporan.php");
        exit();
    }
    
    // Format period text for the report
    $periode_text = '';
    if ($dari_tanggal == $sampai_tanggal) {
        $periode_text = 'Periode: ' . date('d F Y', strtotime($dari_tanggal));
    } else {
        $periode_text = 'Periode: ' . date('d F Y', strtotime($dari_tanggal)) . ' - ' . date('d F Y', strtotime($sampai_tanggal));
    }
    
    // Build where clause for date range filter
    $where_clause = " WHERE DATE(t.tanggal) BETWEEN '$dari_tanggal' AND '$sampai_tanggal' ";
    
    // Set bulan_filter variable for later use (using the month of dari_tanggal)
    $bulan_filter = date('Y-m', strtotime($dari_tanggal));
}

// Add kasir filter if provided
if (!empty($kasir)) {
    $where_clause .= " AND t.kasir = '$kasir' ";
}

// Query untuk mendapatkan data transaksi dengan harga beli dan harga jual
$query = "SELECT t.id, t.tanggal, t.total, t.pendapatan, t.hutang, u.nama AS kasir,
          SUM(td.jumlah * IFNULL(p.harga_beli, 0)) AS total_harga_beli,
          SUM(td.subtotal) AS total_harga_jual,
          (SUM(td.subtotal) - SUM(td.jumlah * IFNULL(p.harga_beli, 0))) AS keuntungan
          FROM transaksi t
          JOIN karyawan u ON t.kasir = u.username
          JOIN transaksi_detail td ON t.id = td.transaksi_id
          LEFT JOIN produk p ON td.produk_id = p.id
          $where_clause
          GROUP BY t.id, t.tanggal, t.total, t.pendapatan, t.hutang, u.nama
          ORDER BY t.tanggal ASC";
$result = mysqli_query($conn, $query);

// Hitung total keuntungan dengan filter, termasuk produk manual yang dianggap 100% keuntungan
$total_keuntungan_query = "SELECT 
                           SUM(CASE 
                                WHEN td.produk_id = 0 OR td.produk_id IS NULL THEN td.subtotal  -- Produk manual 100% keuntungan
                                ELSE td.subtotal - (td.jumlah * IFNULL(p.harga_beli, 0))       -- Produk normal
                               END) AS total_keuntungan
                           FROM transaksi_detail td 
                           LEFT JOIN produk p ON td.produk_id = p.id
                           JOIN transaksi t ON td.transaksi_id = t.id
                           $where_clause";
$total_keuntungan_result = mysqli_query($conn, $total_keuntungan_query);

if ($total_keuntungan_result) {
    $total_keuntungan_row = mysqli_fetch_assoc($total_keuntungan_result);
    $total_keuntungan = $total_keuntungan_row['total_keuntungan'] ?: 0;
} else {
    $total_keuntungan = 0;
}

// Hitung total pendapatan dengan filter
$total_query = "SELECT SUM(total) AS total_pendapatan FROM transaksi t $where_clause";
$total_result = mysqli_query($conn, $total_query);

if ($total_result) {
    $total_row = mysqli_fetch_assoc($total_result);
    $total_pendapatan = $total_row['total_pendapatan'] ?: 0;
} else {
    $total_pendapatan = 0;
}

// Tambahkan query untuk menghitung total uang kas (pendapatan real yang masuk)
$kas_query = "SELECT SUM(pendapatan) AS total_kas FROM transaksi t $where_clause";
$kas_result = mysqli_query($conn, $kas_query);

if ($kas_result) {
    $kas_row = mysqli_fetch_assoc($kas_result);
    $total_kas = $kas_row['total_kas'] ?: 0;
} else {
    $total_kas = 0;
}

// Query untuk menghitung total hutang
$hutang_query = "SELECT SUM(hutang) AS total_hutang FROM transaksi t $where_clause";
$hutang_result = mysqli_query($conn, $hutang_query);

if ($hutang_result) {
    $hutang_row = mysqli_fetch_assoc($hutang_result);
    $total_hutang = $hutang_row['total_hutang'] ?: 0;
} else {
    $total_hutang = 0;
}

// Hitung total transaksi dengan filter
$count_query = "SELECT COUNT(*) AS total_transaksi FROM transaksi t $where_clause";
$count_result = mysqli_query($conn, $count_query);

if ($count_result) {
    $count_row = mysqli_fetch_assoc($count_result);
    $total_transaksi = $count_row['total_transaksi'] ?: 0;
} else {
    $total_transaksi = 0;
}

// Cek terlebih dahulu apakah kolom status_kas ada di tabel jual_bekas
$cek_status_kas = mysqli_query($conn, "SHOW COLUMNS FROM jual_bekas LIKE 'status_kas'");

// Menentukan where clause untuk barang bekas berdasarkan jenis filter
if (isset($_GET['bulan']) || isset($_GET['bulan_filter'])) {
    // Month filter mode
    $bekas_where_clause = " WHERE DATE_FORMAT(tanggal, '%Y-%m') = '$bulan_filter' ";
} else {
    // Date range filter mode
    $bekas_where_clause = " WHERE DATE(tanggal) BETWEEN '$dari_tanggal' AND '$sampai_tanggal' ";
}

// Query untuk penjualan barang bekas yang sudah masuk kas
if (mysqli_num_rows($cek_status_kas) > 0) {
    // Jika kolom status_kas ada
    $bekas_query = "SELECT 
                  SUM(CASE WHEN status_kas = 1 THEN total_harga ELSE 0 END) as total_pendapatan_bekas,
                  SUM(CASE WHEN status_kas = 0 OR status_kas IS NULL THEN total_harga ELSE 0 END) as total_bekas_belum_kas
                  FROM jual_bekas 
                  $bekas_where_clause";
} else {
    // Jika kolom status_kas tidak ada, anggap semua sudah masuk kas
    $bekas_query = "SELECT 
                  SUM(total_harga) as total_pendapatan_bekas,
                  0 as total_bekas_belum_kas
                  FROM jual_bekas 
                  $bekas_where_clause";
}

$bekas_result = mysqli_query($conn, $bekas_query);

if ($bekas_result) {
    $bekas_row = mysqli_fetch_assoc($bekas_result);
    $total_pendapatan_bekas = $bekas_row['total_pendapatan_bekas'] ?: 0;
    $total_bekas_belum_kas = $bekas_row['total_bekas_belum_kas'] ?: 0;
} else {
    $total_pendapatan_bekas = 0;
    $total_bekas_belum_kas = 0;
}

// Total pendapatan barang bekas keseluruhan (yang sudah dan belum masuk kas)
$total_pendapatan_bekas_all = $total_pendapatan_bekas + $total_bekas_belum_kas;

// Update total kas dengan pendapatan barang bekas yang sudah masuk kas
$total_kas += $total_pendapatan_bekas;

// Adjust the query for operational expenses based on filter type
if (isset($_GET['bulan']) || isset($_GET['bulan_filter'])) {
    // Month filter mode
    $operasional_query = "SELECT 
                        SUM(CASE WHEN kategori NOT IN ('Kasbon Karyawan', 'Uang Makan', 'Gaji Karyawan', 'Pembelian Sparepart', 'Pembelian Barang', 'Kasbon Manajer') 
                                AND keterangan NOT LIKE '%produk:%' 
                                AND keterangan NOT LIKE '%produk baru:%'
                                AND keterangan NOT LIKE 'Penambahan stok produk:%' 
                                THEN jumlah ELSE 0 END) as total_operasional,
                        SUM(CASE WHEN kategori IN ('Kasbon Karyawan', 'Uang Makan', 'Gaji Karyawan') 
                                THEN jumlah ELSE 0 END) as total_karyawan
                        FROM pengeluaran 
                        WHERE DATE_FORMAT(tanggal, '%Y-%m') = '$bulan_filter'";
} else {
    // Date range filter mode
    $operasional_query = "SELECT 
                        SUM(CASE WHEN kategori NOT IN ('Kasbon Karyawan', 'Uang Makan', 'Gaji Karyawan', 'Pembelian Sparepart', 'Pembelian Barang', 'Kasbon Manajer') 
                                AND keterangan NOT LIKE '%produk:%' 
                                AND keterangan NOT LIKE '%produk baru:%'
                                AND keterangan NOT LIKE 'Penambahan stok produk:%' 
                                THEN jumlah ELSE 0 END) as total_operasional,
                        SUM(CASE WHEN kategori IN ('Kasbon Karyawan', 'Uang Makan', 'Gaji Karyawan') 
                                THEN jumlah ELSE 0 END) as total_karyawan
                        FROM pengeluaran 
                        WHERE tanggal BETWEEN '$dari_tanggal' AND '$sampai_tanggal'";
}

$operasional_result = mysqli_query($conn, $operasional_query);

if ($operasional_result) {
    $operasional_row = mysqli_fetch_assoc($operasional_result);
    $total_operasional = $operasional_row['total_operasional'] ?: 0;
    $total_karyawan = $operasional_row['total_karyawan'] ?: 0;
} else {
    $total_operasional = 0;
    $total_karyawan = 0;
}

// Total biaya operasional (operasional + karyawan)
$total_biaya_operasional = $total_operasional + $total_karyawan;

// Get total payments for product debt (bayar hutang produk)
$hutang_produk_query = "SELECT SUM(jumlah_bayar) as total_bayar_hutang_produk 
                     FROM piutang_cair 
                     WHERE transaksi_id = '-1' 
                     AND keterangan LIKE 'Pembayaran hutang produk:%'";

if (isset($_GET['bulan']) || isset($_GET['bulan_filter'])) {
    $hutang_produk_query .= " AND DATE_FORMAT(tanggal_bayar, '%Y-%m') = '$bulan_filter'";
} else {
    $hutang_produk_query .= " AND tanggal_bayar BETWEEN '$dari_tanggal' AND '$sampai_tanggal'";
}

$hutang_produk_result = mysqli_query($conn, $hutang_produk_query);
$hutang_produk_data = mysqli_fetch_assoc($hutang_produk_result);
$total_bayar_hutang_produk = $hutang_produk_data['total_bayar_hutang_produk'] ?: 0;

// Query for product purchase expenses
$pembelian_query = "SELECT 
  SUM(CASE 
    WHEN kategori IN ('Pembelian Sparepart', 'Pembelian Barang') 
      OR keterangan LIKE '%pembelian produk baru:%' 
      OR keterangan LIKE '%pembelian produk:%' 
      OR keterangan LIKE '%produk baru:%' THEN jumlah 
    ELSE 0 
  END) as total_pembelian_sparepart,
  
  SUM(CASE 
    WHEN keterangan LIKE '%penambahan stok produk:%' 
      OR keterangan LIKE '%tambah stok%' THEN jumlah 
    ELSE 0 
  END) as total_tambah_stok
FROM pengeluaran";

if (isset($_GET['bulan']) || isset($_GET['bulan_filter'])) {
    $pembelian_query .= " WHERE DATE_FORMAT(tanggal, '%Y-%m') = '$bulan_filter'";
} else {
    $pembelian_query .= " WHERE tanggal BETWEEN '$dari_tanggal' AND '$sampai_tanggal'";
}

$pembelian_result = mysqli_query($conn, $pembelian_query);
$pembelian_data = mysqli_fetch_assoc($pembelian_result);

// Calculate all product expense components
$total_pembelian_sparepart = $pembelian_data['total_pembelian_sparepart'] ?: 0;
$total_tambah_stok = $pembelian_data['total_tambah_stok'] ?: 0;

// Total pengeluaran produk (all components)
$total_pengeluaran_produk = $total_pembelian_sparepart + $total_tambah_stok + $total_bayar_hutang_produk;

// Adjust the query for regular expenses based on filter type
if (isset($_GET['bulan']) || isset($_GET['bulan_filter'])) {
    // Month filter mode
    $pengeluaran_query = "SELECT SUM(jumlah) as total_pengeluaran 
                        FROM pengeluaran 
                        WHERE DATE_FORMAT(tanggal, '%Y-%m') = '$bulan_filter'
                        AND kategori != 'Kasbon Manajer'";

    // Query untuk hutang produk yang lunas pada bulan tersebut
    $hutang_lunas_query = "SELECT SUM(pc.jumlah_bayar) as total_hutang_lunas
                        FROM piutang_cair pc
                        WHERE DATE_FORMAT(pc.tanggal_bayar, '%Y-%m') = '$bulan_filter'
                        AND pc.transaksi_id = '-1'";
} else {
    // Date range filter mode
    $pengeluaran_query = "SELECT SUM(jumlah) as total_pengeluaran 
                        FROM pengeluaran 
                        WHERE tanggal BETWEEN '$dari_tanggal' AND '$sampai_tanggal'
                        AND kategori != 'Kasbon Manajer'";

    // Query untuk hutang produk yang lunas pada rentang tanggal
    $hutang_lunas_query = "SELECT SUM(pc.jumlah_bayar) as total_hutang_lunas
                        FROM piutang_cair pc
                        WHERE pc.tanggal_bayar BETWEEN '$dari_tanggal' AND '$sampai_tanggal'
                        AND pc.transaksi_id = '-1'";
}

$pengeluaran_result = mysqli_query($conn, $pengeluaran_query);

if ($pengeluaran_result) {
    $pengeluaran_row = mysqli_fetch_assoc($pengeluaran_result);
    $total_pengeluaran_reguler = $pengeluaran_row['total_pengeluaran'] ?: 0;
} else {
    $total_pengeluaran_reguler = 0;
}

$hutang_lunas_result = mysqli_query($conn, $hutang_lunas_query);

if ($hutang_lunas_result) {
    $hutang_lunas_row = mysqli_fetch_assoc($hutang_lunas_result);
    $total_hutang_lunas = $hutang_lunas_row['total_hutang_lunas'] ?: 0;
} else {
    $total_hutang_lunas = 0;
}

// Gabungkan kedua nilai untuk mendapatkan total pengeluaran keseluruhan
$total_pengeluaran = $total_pengeluaran_reguler + $total_hutang_lunas;

// Update total pendapatan dengan menambahkan pendapatan dari barang bekas
$total_pendapatan_all = $total_pendapatan + $total_pendapatan_bekas_all;

// Laba bersih = laba kotor + pendapatan barang bekas - total biaya operasional
$laba_bersih = ($total_keuntungan + $total_pendapatan_bekas_all) - $total_biaya_operasional;

// Calculate available cash (saldo kas)
$kas_tersedia = $total_kas - $total_pengeluaran;

// Get kasir name if filter is applied
$kasir_name = '';
if (!empty($kasir)) {
    $kasir_query = "SELECT nama FROM karyawan WHERE username = '$kasir' LIMIT 1";
    $kasir_result = mysqli_query($conn, $kasir_query);
    if ($kasir_result && mysqli_num_rows($kasir_result) > 0) {
        $kasir_row = mysqli_fetch_assoc($kasir_result);
        $kasir_name = $kasir_row['nama'];
    }
}

// Query kasbon manajer
if (isset($_GET['bulan']) || isset($_GET['bulan_filter'])) {
    // Month filter mode
    $kasbon_manajer_query = "SELECT SUM(jumlah) as total_kasbon_manajer 
                            FROM kasbon_manajer 
                            WHERE DATE_FORMAT(tanggal, '%Y-%m') = '$bulan_filter'";
} else {
    // Date range filter mode
    $kasbon_manajer_query = "SELECT SUM(jumlah) as total_kasbon_manajer 
                            FROM kasbon_manajer 
                            WHERE tanggal BETWEEN '$dari_tanggal' AND '$sampai_tanggal'";
}

$kasbon_manajer_result = mysqli_query($conn, $kasbon_manajer_query);
$total_kasbon_manajer = 0;
if ($kasbon_manajer_result && $row = mysqli_fetch_assoc($kasbon_manajer_result)) {
    $total_kasbon_manajer = $row['total_kasbon_manajer'] ?: 0;
}

// Create PDF class
class PDF extends FPDF {
    // Properties to hold period and date information
    public $periode_text;
    public $export_date;
    public $kasir_filter;
    
    // Colors (purple theme - matching with admin dashboard)
    private $headerPurpleColor = array(126, 87, 194); // Primary purple
    private $darkPurpleColor = array(69, 39, 160); // Accent purple
    private $tableHeaderPurpleColor = array(94, 53, 177); // Secondary purple
    private $summaryPurpleColor = array(94, 53, 177); // Secondary purple
    
    // Page header
    function Header() {
        // Title background
        $this->SetFillColor($this->headerPurpleColor[0], $this->headerPurpleColor[1], $this->headerPurpleColor[2]);
        $this->SetDrawColor($this->headerPurpleColor[0], $this->headerPurpleColor[1], $this->headerPurpleColor[2]);
        $this->Rect(0, 0, $this->GetPageWidth(), 35, 'F');
        
        // Title
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(255, 255, 255);
        $this->SetY(12);
        $this->Cell(0, 6, 'LAPORAN KESELURUHAN PENJUALAN', 0, 1, 'C');
        
        // Period
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(255, 255, 255);
        $this->SetY(20);
        $this->Cell(0, 6, $this->periode_text, 0, 1, 'C');
        
        if (!empty($this->kasir_filter)) {
            $this->SetFont('Arial', '', 10);
            $this->SetY(27);
            $this->Cell(0, 6, 'Filter Kasir: ' . $this->kasir_filter, 0, 1, 'C');
        }
        
        // Export date
        $this->SetFont('Arial', '', 10);
        if (empty($this->kasir_filter)) {
            $this->SetY(27);
        } else {
            $this->SetY(33);
        }
        $this->Cell(0, 6, 'Tanggal Export: ' . $this->export_date, 0, 1, 'C');
        
        // Add space before content
        $this->SetY(40);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(5);
    }
    
    // Page footer
    function Footer() {
        // Position at 1.5 cm from bottom
        $this->SetY(-15);
        
        // Arial italic 8
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        
        // Page number
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
    
    // Section Header
    function SectionHeader($title) {
        $this->SetFillColor($this->tableHeaderPurpleColor[0], $this->tableHeaderPurpleColor[1], $this->tableHeaderPurpleColor[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 8, $title, 1, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
    }
    
    // Draw signatures
    function SignatureSection() {
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Arial', '', 9);
        
        // Headers for signatures
        $this->Cell(63, 6, 'Dibuat Oleh,', 0, 0, 'C');
        $this->Cell(63, 6, 'Diperiksa Oleh,', 0, 0, 'C');
        $this->Cell(63, 6, 'Disetujui Oleh,', 0, 1, 'C');
        
        // Space for signatures
        $this->Ln(20);
        
        // Signature lines
        $this->Cell(63, 6, '(________________)', 0, 0, 'C');
        $this->Cell(63, 6, '(________________)', 0, 0, 'C');
        $this->Cell(63, 6, '(________________)', 0, 1, 'C');
        
        // Positions
        $this->Cell(63, 6, 'Admin', 0, 0, 'C');
        $this->Cell(63, 6, 'Manajer', 0, 0, 'C');
        $this->Cell(63, 6, 'Pemilik', 0, 1, 'C');
    }
    
    // Table header for transactions
    function TransactionTableHeader() {
        $this->SetFillColor($this->tableHeaderPurpleColor[0], $this->tableHeaderPurpleColor[1], $this->tableHeaderPurpleColor[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 8);
        
        $this->Cell(15, 7, 'ID', 1, 0, 'C', true);
        $this->Cell(25, 7, 'Tanggal', 1, 0, 'C', true);
        $this->Cell(25, 7, 'Kasir', 1, 0, 'C', true);
        $this->Cell(25, 7, 'Total', 1, 0, 'C', true);
        $this->Cell(25, 7, 'Uang Masuk', 1, 0, 'C', true);
        $this->Cell(25, 7, 'Piutang', 1, 0, 'C', true);
        $this->Cell(25, 7, 'Harga Modal', 1, 0, 'C', true);
        $this->Cell(15, 7, 'Laba', 1, 1, 'C', true);
        
        $this->SetTextColor(0, 0, 0);
    }
    
    // Summary Table
    function SummaryTable($summaryData) {
        // Define widths
        $labelWidth = 95;
        $valueWidth = 95;
        
        // Total Transaksi
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(237, 231, 246); // Light purple
        $this->SetTextColor(0, 0, 0);
        $this->Cell($labelWidth, 8, 'Total Transaksi:', 1, 0, 'L', true);
        $this->Cell($valueWidth, 8, number_format($summaryData['total_transaksi']) . ' transaksi', 1, 1, 'L', true);
        
        // Total Pendapatan Produk
        $this->SetFillColor(232, 234, 246); // Light indigo
        $this->SetTextColor(94, 53, 177); // Purple
        $this->Cell($labelWidth, 8, 'Total Pendapatan Produk:', 1, 0, 'L', true);
        $this->Cell($valueWidth, 8, 'Rp ' . number_format($summaryData['total_pendapatan'], 0, ',', '.'), 1, 1, 'R', true);
        
        // Pendapatan Barang Bekas
        $this->SetFillColor(255, 243, 224); // Light orange
        $this->SetTextColor(230, 81, 0); // Orange
        $this->Cell($labelWidth, 8, 'Pendapatan Barang Bekas:', 1, 0, 'L', true);
        $this->Cell($valueWidth, 8, 'Rp ' . number_format($summaryData['total_pendapatan_bekas'], 0, ',', '.'), 1, 1, 'R', true);
        
        // Total Pendapatan Keseluruhan
        $this->SetFillColor(225, 245, 254); // Light blue
        $this->SetTextColor(2, 119, 189); // Blue
        $this->Cell($labelWidth, 8, 'Total Pendapatan Keseluruhan:', 1, 0, 'L', true);
        $this->Cell($valueWidth, 8, 'Rp ' . number_format($summaryData['total_pendapatan_all'], 0, ',', '.'), 1, 1, 'R', true);
        
// Total Uang Masuk (Kas)
$this->SetFillColor(224, 242, 241); // Light teal
$this->SetTextColor(0, 137, 123); // Teal
$this->Cell($labelWidth, 8, 'Total Uang Masuk (Kas):', 1, 0, 'L', true);
$this->Cell($valueWidth, 8, 'Rp ' . number_format($summaryData['total_kas'], 0, ',', '.'), 1, 1, 'R', true);

// Total Piutang
$this->SetFillColor(255, 235, 238); // Light red
$this->SetTextColor(211, 47, 47); // Red
$this->Cell($labelWidth, 8, 'Total Piutang:', 1, 0, 'L', true);
$this->Cell($valueWidth, 8, 'Rp ' . number_format($summaryData['total_hutang'], 0, ',', '.'), 1, 1, 'R', true);

// Total Biaya Operasional
$this->SetFillColor(232, 234, 246); // Light indigo
$this->SetTextColor(26, 35, 126); // Indigo
$this->Cell($labelWidth, 8, 'Total Biaya Operasional:', 1, 0, 'L', true);
$this->Cell($valueWidth, 8, 'Rp ' . number_format($summaryData['total_biaya_operasional'], 0, ',', '.'), 1, 1, 'R', true);

// Pengeluaran Produk
$this->SetFillColor(227, 242, 253); // Light blue
$this->SetTextColor(13, 71, 161); // Dark blue
$this->Cell($labelWidth, 8, 'Pengeluaran Produk:', 1, 0, 'L', true);
$this->Cell($valueWidth, 8, 'Rp ' . number_format($summaryData['total_pengeluaran_produk'], 0, ',', '.'), 1, 1, 'R', true);

// Total Pengeluaran
$this->SetFillColor(239, 235, 233); // Light brown
$this->SetTextColor(62, 39, 35); // Brown
$this->Cell($labelWidth, 8, 'Total Pengeluaran:', 1, 0, 'L', true);
$this->Cell($valueWidth, 8, 'Rp ' . number_format($summaryData['total_pengeluaran'], 0, ',', '.'), 1, 1, 'R', true);

// Saldo Kas
$this->SetFillColor(232, 245, 233); // Light green
if ($summaryData['kas_tersedia'] >= 0) {
    $this->SetTextColor(46, 125, 50); // Green
} else {
    $this->SetTextColor(211, 47, 47); // Red
}
$this->Cell($labelWidth, 8, 'Saldo Kas:', 1, 0, 'L', true);
$this->Cell($valueWidth, 8, 'Rp ' . number_format($summaryData['kas_tersedia'], 0, ',', '.'), 1, 1, 'R', true);

// Laba Kotor
$this->SetFillColor(232, 245, 233); // Light green
$this->SetTextColor(46, 125, 50); // Green
$this->Cell($labelWidth, 8, 'Total Laba Kotor:', 1, 0, 'L', true);
$this->Cell($valueWidth, 8, 'Rp ' . number_format($summaryData['total_keuntungan'], 0, ',', '.'), 1, 1, 'R', true);

// Laba Bersih
$this->SetFillColor(209, 196, 233); // Lighter purple
$this->SetTextColor(69, 39, 160); // Dark purple
$this->Cell($labelWidth, 8, 'TOTAL LABA BERSIH:', 1, 0, 'L', true);
$this->Cell($valueWidth, 8, 'Rp ' . number_format($summaryData['laba_bersih'], 0, ',', '.'), 1, 1, 'R', true);

// Profit Margin
$profit_margin = ($summaryData['total_pendapatan_all'] > 0) ? 
               ($summaryData['laba_bersih'] / $summaryData['total_pendapatan_all'] * 100) : 0;

$this->SetFillColor(225, 245, 254); // Light blue
$this->SetTextColor(13, 71, 161); // Dark blue
$this->Cell($labelWidth, 8, 'Margin Laba Bersih:', 1, 0, 'L', true);
$this->Cell($valueWidth, 8, number_format($profit_margin, 2) . ' %', 1, 1, 'R', true);

$this->SetTextColor(0, 0, 0); // Reset text color
}

// Product Expense Breakdown Table
function ProductExpenseTable($data) {
// Define widths for consistent layout
$labelWidth = 95;
$valueWidth = 95;

// Pembelian Sparepart Baru
$this->SetFont('Arial', '', 9);
$this->SetFillColor(232, 234, 246); // Light indigo
$this->SetTextColor(13, 71, 161); // Dark blue
$this->Cell($labelWidth, 8, 'Pembelian Sparepart Baru:', 1, 0, 'L', true);
$this->Cell($valueWidth, 8, 'Rp ' . number_format($data['total_pembelian_sparepart'], 0, ',', '.'), 1, 1, 'R', true);

// Tambah Stok Sparepart Lama
$this->SetFillColor(225, 245, 254); // Light blue
$this->SetTextColor(13, 71, 161); // Dark blue
$this->Cell($labelWidth, 8, 'Tambah Stok Sparepart Lama:', 1, 0, 'L', true);
$this->Cell($valueWidth, 8, 'Rp ' . number_format($data['total_tambah_stok'], 0, ',', '.'), 1, 1, 'R', true);

// Bayar Hutang
$this->SetFillColor(227, 242, 253); // Lighter blue
$this->SetTextColor(13, 71, 161); // Dark blue
$this->Cell($labelWidth, 8, 'Bayar Hutang Produk:', 1, 0, 'L', true);
$this->Cell($valueWidth, 8, 'Rp ' . number_format($data['total_bayar_hutang_produk'], 0, ',', '.'), 1, 1, 'R', true);

// Total pengeluaran produk
$this->SetFont('Arial', 'B', 9);
$this->SetFillColor(3, 169, 244); // Blue
$this->SetTextColor(255, 255, 255); // White
$this->Cell($labelWidth, 8, 'TOTAL PENGELUARAN PRODUK:', 1, 0, 'L', true);
$this->Cell($valueWidth, 8, 'Rp ' . number_format($data['total_pengeluaran_produk'], 0, ',', '.'), 1, 1, 'R', true);

$this->SetTextColor(0, 0, 0); // Reset text color
}
}

// Instantiate PDF
$pdf = new PDF('P', 'mm', 'A4');

// Set document information
$pdf->SetTitle('Laporan Penjualan - ' . $periode_text);
$pdf->SetAuthor('BMS Bengkel');
$pdf->SetCreator('BMS Bengkel System');

// Pass period and export date
$pdf->periode_text = $periode_text;
$pdf->export_date = date('d/m/Y H:i');
$pdf->kasir_filter = $kasir_name;

// Add a page
$pdf->AddPage();
$pdf->AliasNbPages();

// === SECTION 1: SUMMARY ===
$pdf->SectionHeader('RINGKASAN LAPORAN');
$pdf->Ln(2);

// Prepare summary data
$summaryData = [
'total_transaksi' => $total_transaksi,
'total_pendapatan' => $total_pendapatan,
'total_pendapatan_bekas' => $total_pendapatan_bekas_all,
'total_pendapatan_all' => $total_pendapatan_all,
'total_kas' => $total_kas,
'total_hutang' => $total_hutang,
'total_biaya_operasional' => $total_biaya_operasional,
'total_pengeluaran_produk' => $total_pengeluaran_produk,
'total_pengeluaran' => $total_pengeluaran,
'kas_tersedia' => $kas_tersedia,
'total_keuntungan' => $total_keuntungan,
'laba_bersih' => $laba_bersih
];

// Draw summary table
$pdf->SummaryTable($summaryData);

// Add Product Expense Breakdown Table
$pdf->Ln(5);
$pdf->SectionHeader('RINCIAN PENGELUARAN PRODUK');
$pdf->Ln(2);

$productExpenseData = [
'total_pembelian_sparepart' => $total_pembelian_sparepart,
'total_tambah_stok' => $total_tambah_stok,
'total_bayar_hutang_produk' => $total_bayar_hutang_produk,
'total_pengeluaran_produk' => $total_pengeluaran_produk,
];

$pdf->ProductExpenseTable($productExpenseData);

$pdf->Ln(5);

// === SECTION 2: TOP 5 PRODUCTS ===
// Get top 5 produk terlaris
$top_products_query = "SELECT 
              COALESCE(p.nama, td.nama_produk_manual, 'Servis') AS nama_produk,
              SUM(td.jumlah) AS jumlah_terjual,
              SUM(td.subtotal) AS total_penjualan,
              SUM(CASE 
                    WHEN td.produk_id = 0 OR td.produk_id IS NULL THEN td.subtotal  
                    ELSE td.subtotal - (td.jumlah * IFNULL(p.harga_beli, 0))
                  END) AS keuntungan
              FROM transaksi_detail td
              LEFT JOIN produk p ON td.produk_id = p.id
              JOIN transaksi t ON td.transaksi_id = t.id
              $where_clause
              GROUP BY COALESCE(p.id, td.nama_produk_manual, -1), COALESCE(p.nama, td.nama_produk_manual, 'Servis')
              ORDER BY jumlah_terjual DESC
              LIMIT 5";
$top_products_result = mysqli_query($conn, $top_products_query);

if (mysqli_num_rows($top_products_result) > 0) {
$pdf->SectionHeader('PRODUK TERLARIS');
$pdf->Ln(2);

// Table Header for Top Products
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(237, 231, 246); // Light purple

$pdf->Cell(10, 8, 'No', 1, 0, 'C', true);
$pdf->Cell(85, 8, 'Nama Produk', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Jumlah', 1, 0, 'C', true);
$pdf->Cell(35, 8, 'Total', 1, 0, 'C', true);
$pdf->Cell(35, 8, 'Laba', 1, 1, 'C', true);

// Table content for Top Products
$pdf->SetFont('Arial', '', 9);
$no = 1;
$fill = false;

while ($product = mysqli_fetch_assoc($top_products_result)) {
// Alternating row colors
$fill = !$fill;
$pdf->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);

$pdf->Cell(10, 8, $no, 1, 0, 'C', $fill);
$pdf->Cell(85, 8, utf8_decode($product['nama_produk']), 1, 0, 'L', $fill);
$pdf->Cell(25, 8, number_format($product['jumlah_terjual']), 1, 0, 'C', $fill);

// Total penjualan with color
$pdf->SetTextColor(94, 53, 177); // Purple for total
$pdf->Cell(35, 8, 'Rp ' . number_format($product['total_penjualan'], 0, ',', '.'), 1, 0, 'R', $fill);

// Keuntungan with color
$pdf->SetTextColor(46, 125, 50); // Green for profit
$pdf->Cell(35, 8, 'Rp ' . number_format($product['keuntungan'], 0, ',', '.'), 1, 1, 'R', $fill);

// Reset text color
$pdf->SetTextColor(0, 0, 0);

$no++;
}

$pdf->Ln(5);
}

// === SECTION 3: CATEGORY SUMMARY ===
// Get kategori data
$kategori_query = "SELECT 
          COALESCE(k.nama_kategori, 'Servis') as nama_kategori,
          SUM(td.subtotal) AS total_penjualan,
          SUM(td.jumlah) AS jumlah_terjual
          FROM transaksi_detail td
          LEFT JOIN produk p ON td.produk_id = p.id
          LEFT JOIN kategori k ON p.kategori_id = k.id
          JOIN transaksi t ON td.transaksi_id = t.id
          $where_clause
          GROUP BY COALESCE(k.nama_kategori, 'Servis')
          ORDER BY total_penjualan DESC";
$kategori_result = mysqli_query($conn, $kategori_query);

if (mysqli_num_rows($kategori_result) > 0) {
$pdf->SectionHeader('PENJUALAN PER KATEGORI');
$pdf->Ln(2);

// Table Header for Categories
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(237, 231, 246); // Light purple

$pdf->Cell(10, 8, 'No', 1, 0, 'C', true);
$pdf->Cell(100, 8, 'Kategori', 1, 0, 'C', true);
$pdf->Cell(30, 8, 'Jumlah Terjual', 1, 0, 'C', true);
$pdf->Cell(50, 8, 'Total Penjualan', 1, 1, 'C', true);

// Table content for Categories
$pdf->SetFont('Arial', '', 9);
$no = 1;
$fill = false;
$total_kategori = 0;

while ($kategori = mysqli_fetch_assoc($kategori_result)) {
// Alternating row colors
$fill = !$fill;
$pdf->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);

$pdf->Cell(10, 8, $no, 1, 0, 'C', $fill);
$pdf->Cell(100, 8, utf8_decode($kategori['nama_kategori']), 1, 0, 'L', $fill);
$pdf->Cell(30, 8, number_format($kategori['jumlah_terjual']), 1, 0, 'C', $fill);

// Total penjualan with color
$pdf->SetTextColor(94, 53, 177); // Purple for total
$pdf->Cell(50, 8, 'Rp ' . number_format($kategori['total_penjualan'], 0, ',', '.'), 1, 1, 'R', $fill);

// Reset text color
$pdf->SetTextColor(0, 0, 0);

$total_kategori += $kategori['total_penjualan'];
$no++;
}

// Total row
$pdf->SetFillColor(237, 231, 246); // Light purple
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(140, 8, 'TOTAL', 1, 0, 'R', true);
$pdf->SetTextColor(94, 53, 177); // Purple for total
$pdf->Cell(50, 8, 'Rp ' . number_format($total_kategori, 0, ',', '.'), 1, 1, 'R', true);
$pdf->SetTextColor(0, 0, 0); // Reset color

$pdf->Ln(5);
}

// === SECTION 4: TRANSACTION LIST ===
if (mysqli_num_rows($result) > 0) {
// Create a new page for transactions if needed
if ($pdf->GetY() > 200) {
$pdf->AddPage();
}

// Define column widths with all amount columns the same width - these will be used consistently
$noWidth = 10;
$idWidth = 15;
$tanggalWidth = 25;
$kasirWidth = 35;  // Increased from original
$amountColWidth = 22;  // Make all amount columns the same width

// Calculate total table width for consistent header
$tableWidth = $noWidth + $idWidth + $tanggalWidth + $kasirWidth + ($amountColWidth * 5);

// Section header - using the total table width for consistency
$pdf->SetFillColor(94, 53, 177); // Secondary purple color
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell($tableWidth, 8, 'DAFTAR TRANSAKSI', 1, 1, 'L', true);
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(2);

// Table Header for Transactions
$pdf->SetFillColor(94, 53, 177); // Secondary purple color
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 8);

// Adjusted column widths
$pdf->Cell($noWidth, 7, 'No', 1, 0, 'C', true);
$pdf->Cell($idWidth, 7, 'ID', 1, 0, 'C', true);
$pdf->Cell($tanggalWidth, 7, 'Tanggal', 1, 0, 'C', true);
$pdf->Cell($kasirWidth, 7, 'Kasir', 1, 0, 'C', true);
$pdf->Cell($amountColWidth, 7, 'Total', 1, 0, 'C', true);
$pdf->Cell($amountColWidth, 7, 'Uang Masuk', 1, 0, 'C', true);
$pdf->Cell($amountColWidth, 7, 'Piutang', 1, 0, 'C', true);
$pdf->Cell($amountColWidth, 7, 'Harga Modal', 1, 0, 'C', true);
$pdf->Cell($amountColWidth, 7, 'Laba', 1, 1, 'C', true);

$pdf->SetTextColor(0, 0, 0);

// Table content for Transactions
$no = 1;
$fill = false;
$grand_total = 0;
$grand_total_pendapatan = 0;
$grand_total_hutang = 0;
$grand_total_modal = 0;
$grand_total_laba = 0;

mysqli_data_seek($result, 0); // Reset result pointer to beginning

while ($transaksi = mysqli_fetch_assoc($result)) {
// Check if we need a new page
if ($pdf->GetY() > 270) {
    $pdf->AddPage();
    
    // Repeat the header with the same column widths
    $pdf->SetFillColor(94, 53, 177); // Secondary purple color
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 8);
    
    $pdf->Cell($noWidth, 7, 'No', 1, 0, 'C', true);
    $pdf->Cell($idWidth, 7, 'ID', 1, 0, 'C', true);
    $pdf->Cell($tanggalWidth, 7, 'Tanggal', 1, 0, 'C', true);
    $pdf->Cell($kasirWidth, 7, 'Kasir', 1, 0, 'C', true);
    $pdf->Cell($amountColWidth, 7, 'Total', 1, 0, 'C', true);
    $pdf->Cell($amountColWidth, 7, 'Uang Masuk', 1, 0, 'C', true);
    $pdf->Cell($amountColWidth, 7, 'Piutang', 1, 0, 'C', true);
    $pdf->Cell($amountColWidth, 7, 'Harga Modal', 1, 0, 'C', true);
    $pdf->Cell($amountColWidth, 7, 'Laba', 1, 1, 'C', true);
    
    $pdf->SetTextColor(0, 0, 0);
}

// Alternating row colors
$fill = !$fill;
$pdf->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);

// Set font for row data
$pdf->SetFont('Arial', '', 8);

// No
$pdf->Cell($noWidth, 6, $no, 1, 0, 'C', $fill);

// ID
$pdf->Cell($idWidth, 6, '#' . $transaksi['id'], 1, 0, 'C', $fill);

// Tanggal (removed time, showing only date)
$pdf->Cell($tanggalWidth, 6, date('d/m/Y', strtotime($transaksi['tanggal'])), 1, 0, 'C', $fill);

// Kasir (increased width)
$pdf->Cell($kasirWidth, 6, utf8_decode($transaksi['kasir']), 1, 0, 'L', $fill);

// Total
$pdf->SetTextColor(94, 53, 177); // Purple for total
$pdf->Cell($amountColWidth, 6, 'Rp ' . number_format($transaksi['total'], 0, ',', '.'), 1, 0, 'R', $fill);

// Pendapatan (Kas)
$pdf->SetTextColor(0, 137, 123); // Teal for cash
$pdf->Cell($amountColWidth, 6, 'Rp ' . number_format($transaksi['pendapatan'], 0, ',', '.'), 1, 0, 'R', $fill);

// Hutang
if ($transaksi['hutang'] > 0) {
    $pdf->SetTextColor(211, 47, 47); // Red for debt
} else {
    $pdf->SetTextColor(76, 175, 80); // Green for no debt
}
$pdf->Cell($amountColWidth, 6, 'Rp ' . number_format($transaksi['hutang'], 0, ',', '.'), 1, 0, 'R', $fill);

// Harga Modal
$pdf->SetTextColor(117, 117, 117); // Gray for modal
$pdf->Cell($amountColWidth, 6, 'Rp ' . number_format($transaksi['total_harga_beli'], 0, ',', '.'), 1, 0, 'R', $fill);

// Laba
$pdf->SetTextColor(46, 125, 50); // Green for profit
$pdf->Cell($amountColWidth, 6, 'Rp ' . number_format($transaksi['keuntungan'], 0, ',', '.'), 1, 1, 'R', $fill);

// Reset text color
$pdf->SetTextColor(0, 0, 0);

// Menambahkan nilai ke total
$grand_total += $transaksi['total'];
$grand_total_pendapatan += $transaksi['pendapatan'];
$grand_total_hutang += $transaksi['hutang'];
$grand_total_modal += $transaksi['total_harga_beli'];
$grand_total_laba += $transaksi['keuntungan'];

$no++;
}

// Menambahkan baris total with consistent column widths
$pdf->SetFillColor(237, 231, 246); // Light purple
$pdf->SetFont('Arial', 'B', 8);

// Label total
$pdf->Cell($noWidth + $idWidth + $tanggalWidth + $kasirWidth, 6, 'TOTAL PENJUALAN', 1, 0, 'R', true);

// Total penjualan
$pdf->SetTextColor(94, 53, 177); // Purple for total
$pdf->Cell($amountColWidth, 6, 'Rp ' . number_format($grand_total, 0, ',', '.'), 1, 0, 'R', true);

// Total pendapatan kas
$pdf->SetTextColor(0, 137, 123); // Teal for cash
$pdf->Cell($amountColWidth, 6, 'Rp ' . number_format($grand_total_pendapatan, 0, ',', '.'), 1, 0, 'R', true);

// Total hutang
$pdf->SetTextColor(211, 47, 47); // Red for debt
$pdf->Cell($amountColWidth, 6, 'Rp ' . number_format($grand_total_hutang, 0, ',', '.'), 1, 0, 'R', true);

// Total harga modal
$pdf->SetTextColor(117, 117, 117); // Gray for modal
$pdf->Cell($amountColWidth, 6, 'Rp ' . number_format($grand_total_modal, 0, ',', '.'), 1, 0, 'R', true);

// Total laba
$pdf->SetTextColor(46, 125, 50); // Green for profit
$pdf->Cell($amountColWidth, 6, 'Rp ' . number_format($grand_total_laba, 0, ',', '.'), 1, 1, 'R', true);

// Reset text color
$pdf->SetTextColor(0, 0, 0);

$pdf->Ln(5);
}

// === SECTION 5: BARANG BEKAS ===
// Query untuk detail barang bekas
if (isset($_GET['bulan']) || isset($_GET['bulan_filter'])) {
// Month filter mode
$bekas_detail_query = "SELECT id, tanggal, jenis, keterangan, total_harga, 
              " . (mysqli_num_rows($cek_status_kas) > 0 ? "(CASE WHEN status_kas = 1 THEN 'Ya' ELSE 'Belum' END) as status_kas_text," : "'Ya' as status_kas_text,") . "
              created_by 
              FROM jual_bekas 
              WHERE DATE_FORMAT(tanggal, '%Y-%m') = '$bulan_filter'
              ORDER BY tanggal ASC";
} else {
// Date range filter mode
$bekas_detail_query = "SELECT id, tanggal, jenis, keterangan, total_harga, 
              " . (mysqli_num_rows($cek_status_kas) > 0 ? "(CASE WHEN status_kas = 1 THEN 'Ya' ELSE 'Belum' END) as status_kas_text," : "'Ya' as status_kas_text,") . "
              created_by 
              FROM jual_bekas 
              WHERE DATE(tanggal) BETWEEN '$dari_tanggal' AND '$sampai_tanggal'
              ORDER BY tanggal ASC";
}

$bekas_detail_result = mysqli_query($conn, $bekas_detail_query);

// Check if we have any barang bekas data
if ($bekas_detail_result && mysqli_num_rows($bekas_detail_result) > 0) {
// Check if we need a new page
if ($pdf->GetY() > 200) {
$pdf->AddPage();
}

$pdf->SectionHeader('PENJUALAN BARANG BEKAS');
$pdf->Ln(2);

// Table Header for Barang Bekas
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(237, 231, 246); // Light purple

$pdf->Cell(10, 8, 'No', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Tanggal', 1, 0, 'C', true);
    $pdf->Cell(40, 8, 'Jenis Barang', 1, 0, 'C', true);
    $pdf->Cell(60, 8, 'Keterangan', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Harga', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Masuk Kas', 1, 1, 'C', true);

    // Table content for Barang Bekas
    $pdf->SetFont('Arial', '', 9);
    $no = 1;
    $fill = false;

    while ($bekas = mysqli_fetch_assoc($bekas_detail_result)) {
        // Check if we need a new page
        if ($pdf->GetY() > 270) {
            $pdf->AddPage();
            
            // Repeat header
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(237, 231, 246);
            
            $pdf->Cell(10, 8, 'No', 1, 0, 'C', true);
            $pdf->Cell(25, 8, 'Tanggal', 1, 0, 'C', true);
            $pdf->Cell(40, 8, 'Jenis Barang', 1, 0, 'C', true);
            $pdf->Cell(60, 8, 'Keterangan', 1, 0, 'C', true);
            $pdf->Cell(30, 8, 'Harga', 1, 0, 'C', true);
            $pdf->Cell(25, 8, 'Masuk Kas', 1, 1, 'C', true);
            
            $pdf->SetFont('Arial', '', 9);
        }
        
        // Alternating row colors
        $fill = !$fill;
        $pdf->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);
        
        $pdf->Cell(10, 8, $no, 1, 0, 'C', $fill);
        $pdf->Cell(25, 8, date('d/m/Y', strtotime($bekas['tanggal'])), 1, 0, 'C', $fill);
        $pdf->Cell(40, 8, utf8_decode($bekas['jenis']), 1, 0, 'L', $fill);
        $pdf->Cell(60, 8, utf8_decode($bekas['keterangan']), 1, 0, 'L', $fill);
        
        // Harga with color
        $pdf->SetTextColor(230, 81, 0); // Orange for price
        $pdf->Cell(30, 8, 'Rp ' . number_format($bekas['total_harga'], 0, ',', '.'), 1, 0, 'R', $fill);
        
        // Status Kas
        if ($bekas['status_kas_text'] == 'Ya') {
            $pdf->SetTextColor(46, 125, 50); // Green for yes
        } else {
            $pdf->SetTextColor(211, 47, 47); // Red for no
        }
        $pdf->Cell(25, 8, $bekas['status_kas_text'], 1, 1, 'C', $fill);
        
        // Reset text color
        $pdf->SetTextColor(0, 0, 0);
        
        $no++;
    }

    // Total row
    $pdf->SetFillColor(255, 243, 224); // Light orange
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(135, 8, 'TOTAL PENDAPATAN BARANG BEKAS', 1, 0, 'R', true);
    $pdf->SetTextColor(230, 81, 0); // Orange
    $pdf->Cell(55, 8, 'Rp ' . number_format($total_pendapatan_bekas_all, 0, ',', '.'), 1, 1, 'R', true);

    // If there are items not yet in kas
    if ($total_bekas_belum_kas > 0) {
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Cell(135, 8, '- Sudah Masuk Kas', 1, 0, 'R', false);
        $pdf->SetTextColor(46, 125, 50); // Green
        $pdf->Cell(55, 8, 'Rp ' . number_format($total_pendapatan_bekas, 0, ',', '.'), 1, 1, 'R', false);
        
        $pdf->SetFillColor(255, 235, 238); // Light red
        $pdf->Cell(135, 8, '- Belum Masuk Kas', 1, 0, 'R', true);
        $pdf->SetTextColor(211, 47, 47); // Red
        $pdf->Cell(55, 8, 'Rp ' . number_format($total_bekas_belum_kas, 0, ',', '.'), 1, 1, 'R', true);
    }

    $pdf->SetTextColor(0, 0, 0); // Reset text color
    $pdf->Ln(5);
}

// === SECTION 6: OPERATIONAL EXPENSES ===
// Adjust the query for pengeluaran details based on filter type
if (isset($_GET['bulan']) || isset($_GET['bulan_filter'])) {
    // Month filter mode
    $pengeluaran_detail_query = "SELECT id, tanggal, kategori, keterangan, jumlah 
                            FROM pengeluaran 
                            WHERE DATE_FORMAT(tanggal, '%Y-%m') = '$bulan_filter'
                            AND kategori != 'Kasbon Manajer'
                            ORDER BY tanggal ASC, id ASC";
} else {
    // Date range filter mode
    $pengeluaran_detail_query = "SELECT id, tanggal, kategori, keterangan, jumlah 
                            FROM pengeluaran 
                            WHERE tanggal BETWEEN '$dari_tanggal' AND '$sampai_tanggal'
                            AND kategori != 'Kasbon Manajer'
                            ORDER BY tanggal ASC, id ASC";
}
$pengeluaran_detail_result = mysqli_query($conn, $pengeluaran_detail_query);

// Check if we have any operational expenses to show
if (mysqli_num_rows($pengeluaran_detail_result) > 0 || $total_hutang_lunas > 0) {
    // Add a new page for expenses
    $pdf->AddPage();

    $pdf->SectionHeader('DAFTAR PENGELUARAN');
    $pdf->Ln(2);

    // Table Header for Pengeluaran
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(237, 231, 246); // Light purple

    $pdf->Cell(10, 8, 'No', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Tanggal', 1, 0, 'C', true);
    $pdf->Cell(40, 8, 'Kategori', 1, 0, 'C', true);
    $pdf->Cell(85, 8, 'Keterangan', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Jumlah', 1, 1, 'C', true);

    // Collect all expenses data (pengeluaran + hutang lunas)
    $all_expenses = array();
    
// Collect all expenses data (pengeluaran + hutang lunas)
$all_expenses = array();

// Add regular expenses to the array
while ($pengeluaran = mysqli_fetch_assoc($pengeluaran_detail_result)) {
    // Use consistent category names across the entire system
    $kategori_display = $pengeluaran['kategori'];
    
    // Determine proper category display based on content
    if ($pengeluaran['kategori'] == 'Pembelian Sparepart' || 
        $pengeluaran['kategori'] == 'Pembelian Barang' || 
        strpos($pengeluaran['keterangan'], 'Pembelian produk') === 0 || 
        strpos($pengeluaran['keterangan'], 'produk baru:') !== false) {
        $kategori_display = 'Pembelian Sparepart Baru';
    } else if (strpos($pengeluaran['keterangan'], 'Penambahan stok produk:') === 0) {
        $kategori_display = 'Tambah Stok';
    } else if ($pengeluaran['kategori'] == 'Bayar Hutang Produk') {
        $kategori_display = 'Hutang Produk';
    }
    
    $all_expenses[] = array(
        'tanggal' => $pengeluaran['tanggal'],
        'kategori' => $kategori_display,
        'keterangan' => $pengeluaran['keterangan'],
        'jumlah' => $pengeluaran['jumlah']
    );
}
    
    // Add hutang lunas to the array if any
    if ($total_hutang_lunas > 0) {
        // Adjust the query for hutang lunas based on filter type
        if (isset($_GET['bulan']) || isset($_GET['bulan_filter'])) {
            $hutang_lunas_detail_query = "SELECT pc.id, pc.tanggal_bayar, pc.keterangan, pc.jumlah_bayar
                                        FROM piutang_cair pc
                                        WHERE DATE_FORMAT(pc.tanggal_bayar, '%Y-%m') = '$bulan_filter'
                                        AND pc.transaksi_id = '-1'
                                        ORDER BY pc.tanggal_bayar ASC";
        } else {
            $hutang_lunas_detail_query = "SELECT pc.id, pc.tanggal_bayar, pc.keterangan, pc.jumlah_bayar
                                        FROM piutang_cair pc
                                        WHERE pc.tanggal_bayar BETWEEN '$dari_tanggal' AND '$sampai_tanggal'
                                        AND pc.transaksi_id = '-1'
                                        ORDER BY pc.tanggal_bayar ASC";
        }
        $hutang_lunas_detail_result = mysqli_query($conn, $hutang_lunas_detail_query);
        
        while ($hutang_lunas = mysqli_fetch_assoc($hutang_lunas_detail_result)) {
            $all_expenses[] = array(
                'tanggal' => $hutang_lunas['tanggal_bayar'],
                'kategori' => 'Hutang Produk',
                'keterangan' => $hutang_lunas['keterangan'],
                'jumlah' => $hutang_lunas['jumlah_bayar']
            );
        }
    }
    
    // Sort all expenses by tanggal
    usort($all_expenses, function($a, $b) {
        return strtotime($a['tanggal']) - strtotime($b['tanggal']);
    });
    
    // Table content for All Expenses
    $pdf->SetFont('Arial', '', 9);
    $no = 1;
    $fill = false;
    
    foreach ($all_expenses as $expense) {
        // Check if we need a new page
        if ($pdf->GetY() > 270) {
            $pdf->AddPage();
            
            // Repeat header
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(237, 231, 246);
            
            $pdf->Cell(10, 8, 'No', 1, 0, 'C', true);
            $pdf->Cell(25, 8, 'Tanggal', 1, 0, 'C', true);
            $pdf->Cell(40, 8, 'Kategori', 1, 0, 'C', true);
            $pdf->Cell(85, 8, 'Keterangan', 1, 0, 'C', true);
            $pdf->Cell(30, 8, 'Jumlah', 1, 1, 'C', true);
            
            $pdf->SetFont('Arial', '', 9);
        }
        
        // Alternating row colors
        $fill = !$fill;
        $pdf->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);
        
        // Calculate height for possible long text
        $keterangan = utf8_decode($expense['keterangan']);
        $lines = max(1, ceil(strlen($keterangan) / 50)); // Approx. chars per line
        $height = max(6, $lines * 5); // Min 6mm height, 5mm per line
        
        // Get starting positions
        $x_position = $pdf->GetX();
        $y_position = $pdf->GetY();
        
        $pdf->Cell(10, $height, $no, 1, 0, 'C', $fill);
        $pdf->Cell(25, $height, date('d/m/Y', strtotime($expense['tanggal'])), 1, 0, 'C', $fill);
        $pdf->Cell(40, $height, utf8_decode($expense['kategori']), 1, 0, 'L', $fill);
        
        // For keterangan, use MultiCell since it might be long
        $pdf->MultiCell(85, $height/$lines, $keterangan, 1, 'L', $fill);
        
        // Reset position for remaining cells (after MultiCell)
        $pdf->SetXY($x_position + 10 + 25 + 40 + 85, $y_position);
        
        // Jumlah with color
        $pdf->SetTextColor(211, 47, 47); // Red for expenses
        $pdf->Cell(30, $height, 'Rp ' . number_format($expense['jumlah'], 0, ',', '.'), 1, 1, 'R', $fill);
        $pdf->SetTextColor(0, 0, 0); // Reset text color
        
        $no++;
    }

    // Total row
    $pdf->SetFillColor(239, 235, 233); // Light brown
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(160, 8, 'TOTAL PENGELUARAN', 1, 0, 'R', true);
    $pdf->SetTextColor(211, 47, 47); // Red
    $pdf->Cell(30, 8, 'Rp ' . number_format($total_pengeluaran, 0, ',', '.'), 1, 1, 'R', true);
    $pdf->SetTextColor(0, 0, 0); // Reset text color

    $pdf->Ln(5);
}

// === SECTION 7: KASBON MANAJER (Manager Cash Advances) ===
// Query kasbon manajer
if (isset($_GET['bulan']) || isset($_GET['bulan_filter'])) {
    // Month filter mode
    $kasbon_manajer_query = "SELECT k.id, k.tanggal, k.jumlah, k.keterangan, m.nama
                      FROM kasbon_manajer k
                      JOIN manajer m ON k.id_manajer = m.id_manajer
                      WHERE DATE_FORMAT(k.tanggal, '%Y-%m') = '$bulan_filter'
                      ORDER BY k.tanggal ASC";
} else {
    // Date range filter mode
    $kasbon_manajer_query = "SELECT k.id, k.tanggal, k.jumlah, k.keterangan, m.nama
                      FROM kasbon_manajer k
                      JOIN manajer m ON k.id_manajer = m.id_manajer
                      WHERE k.tanggal BETWEEN '$dari_tanggal' AND '$sampai_tanggal'
                      ORDER BY k.tanggal ASC";
}

$kasbon_manajer_result = mysqli_query($conn, $kasbon_manajer_query);

// Create a new page for Kasbon Manajer if needed
if (mysqli_num_rows($kasbon_manajer_result) > 0) {
    if ($pdf->GetY() > 200) {
        $pdf->AddPage();
    }

    $pdf->SectionHeader('KASBON MANAJER (CASH ADVANCES)');
    $pdf->Ln(2);

    // Table Header for Kasbon Manajer
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(237, 231, 246); // Light purple

    $pdf->Cell(10, 8, 'No', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Tanggal', 1, 0, 'C', true);
    $pdf->Cell(50, 8, 'Nama Manajer', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Jumlah', 1, 0, 'C', true);
    $pdf->Cell(75, 8, 'Keterangan', 1, 1, 'C', true);

    // Table content for Kasbon Manajer
    $pdf->SetFont('Arial', '', 9);
    $no = 1;
    $fill = false;

    while ($kasbon = mysqli_fetch_assoc($kasbon_manajer_result)) {
        // Check if we need a new page
        if ($pdf->GetY() > 270) {
            $pdf->AddPage();
            
            // Repeat header
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(237, 231, 246);
            
            $pdf->Cell(10, 8, 'No', 1, 0, 'C', true);
            $pdf->Cell(25, 8, 'Tanggal', 1, 0, 'C', true);
            $pdf->Cell(50, 8, 'Nama Manajer', 1, 0, 'C', true);
            $pdf->Cell(30, 8, 'Jumlah', 1, 0, 'C', true);
            $pdf->Cell(75, 8, 'Keterangan', 1, 1, 'C', true);
            
            $pdf->SetFont('Arial', '', 9);
        }
        
        // Alternating row colors
        $fill = !$fill;
        $pdf->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);
        
        // Calculate height for possible long text
        $keterangan = utf8_decode($kasbon['keterangan']);
        $lines = max(1, ceil(strlen($keterangan) / 40));
        $height = max(6, $lines * 5);
        
        // Get starting positions
        $x_position = $pdf->GetX();
        $y_position = $pdf->GetY();
        
        $pdf->Cell(10, $height, $no, 1, 0, 'C', $fill);
        $pdf->Cell(25, $height, date('d/m/Y', strtotime($kasbon['tanggal'])), 1, 0, 'C', $fill);
        $pdf->Cell(50, $height, utf8_decode($kasbon['nama']), 1, 0, 'L', $fill);
        
        // Jumlah with color
        $pdf->SetTextColor(94, 53, 177); // Purple for kasbon amount
        $pdf->Cell(30, $height, 'Rp ' . number_format($kasbon['jumlah'], 0, ',', '.'), 1, 0, 'R', $fill);
        $pdf->SetTextColor(0, 0, 0); // Reset text color
        
        // For keterangan, use MultiCell since it might be long
        $pdf->MultiCell(75, $height/$lines, $keterangan, 1, 'L', $fill);
        
        // Reset position for next row
        $pdf->SetXY($x_position, $y_position + $height);
        
        $no++;
    }

    // Total row
    $pdf->SetFillColor(237, 231, 246); // Light purple
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(85, 8, 'TOTAL KASBON MANAJER', 1, 0, 'R', true);
    $pdf->SetTextColor(94, 53, 177); // Purple for total
    $pdf->Cell(105, 8, 'Rp ' . number_format($total_kasbon_manajer, 0, ',', '.'), 1, 1, 'R', true);
    $pdf->SetTextColor(0, 0, 0); // Reset text color

    $pdf->Ln(5);
}

// === SECTION 8: FINAL SUMMARY AND SIGNATURES ===
// Add a new page if needed
if ($pdf->GetY() > 200) {
    $pdf->AddPage();
}

$pdf->SectionHeader('RINGKASAN LAPORAN AKHIR');
$pdf->Ln(5);

// Calculate profit shares
$admin_share = $laba_bersih * 0.5;
$manager_share = $laba_bersih * 0.5;
$sisa_bagian_manajer = $manager_share - $total_kasbon_manajer;

// Final summary with profit sharing
$pdf->SetFont('Arial', 'B', 10);

// Total laba bersih
$pdf->Cell(95, 10, 'TOTAL LABA BERSIH', 1, 0, 'L', false);
$pdf->SetTextColor(69, 39, 160); // Dark purple
$pdf->Cell(95, 10, 'Rp ' . number_format($laba_bersih, 0, ',', '.'), 1, 1, 'R', false);
$pdf->SetTextColor(0, 0, 0);

// Profit sharing - Admin (50%)
// Use a lighter background fill
$pdf->SetFillColor(237, 231, 246); // Light purple background
$pdf->Cell(95, 10, 'Bagian Admin/Pemilik (50%)', 1, 0, 'L', true);
// Make sure text color contrasts with background
$pdf->SetTextColor(94, 53, 177); // Purple text
$pdf->Cell(95, 10, 'Rp ' . number_format($admin_share, 0, ',', '.'), 1, 1, 'R', true);
$pdf->SetTextColor(0, 0, 0); // Reset text color

// Profit sharing - Manager (50%)
$pdf->Cell(95, 10, 'Bagian Manajer (50%)', 1, 0, 'L', false);
$pdf->SetTextColor(13, 71, 161); // Blue
$pdf->Cell(95, 10, 'Rp ' . number_format($manager_share, 0, ',', '.'), 1, 1, 'R', false);

// Total kasbon manajer
$pdf->SetFillColor(255, 235, 238); // Light red
$pdf->Cell(95, 10, 'Total Kasbon Manajer', 1, 0, 'L', true);
$pdf->SetTextColor(211, 47, 47); // Red for kasbon
$pdf->Cell(95, 10, 'Rp ' . number_format($total_kasbon_manajer, 0, ',', '.'), 1, 1, 'R', true);

// Sisa bagian manajer (setelah dikurangi kasbon)
$pdf->SetFillColor(232, 245, 233); // Light green background
$pdf->Cell(95, 10, 'Sisa Bagian Manajer', 1, 0, 'L', false);
if ($sisa_bagian_manajer < 0) {
    $pdf->SetTextColor(211, 47, 47); // Red for negative amount
} else {
    $pdf->SetTextColor(46, 125, 50); // Green for positive amount
}
$pdf->Cell(95, 10, 'Rp ' . number_format($sisa_bagian_manajer, 0, ',', '.'), 1, 1, 'R', false);
$pdf->SetTextColor(0, 0, 0); // Reset text color

// Add space before signatures
$pdf->Ln(20);

// Add signature section
$pdf->SignatureSection();

// Output the PDF - Generate filename based on filter type
if (isset($_GET['bulan']) || isset($_GET['bulan_filter'])) {
    // For month filter
    $filename = 'Laporan_' . $bulan_filter . '.pdf';
} else {
    // For date range filter
    $filename = 'Laporan_' . date('Ymd', strtotime($dari_tanggal)) . '_' . date('Ymd', strtotime($sampai_tanggal)) . '.pdf';
}

$pdf->Output($filename, 'I'); // 'I' means show in browser

// Close database connection
mysqli_close($conn);