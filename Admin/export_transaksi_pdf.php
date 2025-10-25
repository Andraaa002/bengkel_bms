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
$format = isset($_GET['format']) ? $_GET['format'] : 'pdf';

// Format for titles
$periode_text = '';
if (date('Y-m', strtotime($tgl_awal)) == date('Y-m', strtotime($tgl_akhir))) {
    $periode_text = 'Periode: ' . date('F Y', strtotime($tgl_awal));
} else {
    $periode_text = 'Periode: ' . date('d/m/Y', strtotime($tgl_awal)) . ' - ' . date('d/m/Y', strtotime($tgl_akhir));
}

// If Excel format is requested, redirect to the Excel export script
if ($format == 'excel') {
    header("Location: export_transaksi_excel.php?tgl_awal={$tgl_awal}&tgl_akhir={$tgl_akhir}");
    exit();
}

// Require library FPDF
require('../fpdf186/fpdf.php');

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

// Create a custom PDF class with modern styling
class PDF extends FPDF
{
    // Properties to hold period and date information
    public $periode_text;
    public $export_date;
    
    // Colors (purple theme - matching with admin dashboard)
    private $headerPurpleColor = array(126, 87, 194); // Primary purple
    private $darkPurpleColor = array(69, 39, 160); // Accent purple
    private $tableHeaderPurpleColor = array(94, 53, 177); // Secondary purple
    private $summaryPurpleColor = array(94, 53, 177); // Secondary purple
    private $alternatePurpleColor = array(237, 231, 246); // Light purple
    private $lightPurpleColor = array(209, 196, 233); // Lighter purple
    private $lightGreenColor = array(226, 239, 218); // Light green
    private $redColor = array(244, 67, 54); // Red for warnings
    
    // Page header
    function Header()
    {
        // Title background
        $this->SetFillColor($this->headerPurpleColor[0], $this->headerPurpleColor[1], $this->headerPurpleColor[2]);
        $this->SetDrawColor($this->headerPurpleColor[0], $this->headerPurpleColor[1], $this->headerPurpleColor[2]);
        $this->Rect(0, 0, $this->GetPageWidth(), 35, 'F');
        
        // Title - positioned at 12mm from top
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(255, 255, 255);
        $this->SetY(12);
        $this->Cell(0, 6, 'LAPORAN TRANSAKSI PENJUALAN', 0, 1, 'C');
        
        // Period - positioned at 20mm from top
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(255, 255, 255);
        $this->SetY(20);
        $this->Cell(0, 6, $this->periode_text, 0, 1, 'C');
        
        // Export date - positioned at 27mm from top
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(255, 255, 255);
        $this->SetY(27);
        $this->Cell(0, 6, 'Tanggal Export: ' . $this->export_date, 0, 1, 'C');
        
        // Add space before table
        $this->SetY(40);
        $this->SetTextColor(0, 0, 0);
        
        // Add space before table
        $this->Ln(5);
    }

    // Page footer
    function Footer()
    {
        // Position at 1.5 cm from bottom
        $this->SetY(-15);
        
        // Arial italic 8
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        
        // Page number
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
    
    // Table Header
    function TableHeader()
    {
        // Use purple color for table headers
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
        $this->Cell(25, 7, 'Laba Kotor', 1, 1, 'C', true);
        
        // Reset colors
        $this->SetTextColor(0, 0, 0);
    }
    
    // Summary Header
    function SummaryHeader()
    {
        $this->SetFillColor($this->summaryPurpleColor[0], $this->summaryPurpleColor[1], $this->summaryPurpleColor[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(195, 8, 'RINGKASAN TRANSAKSI', 1, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
    }
    
    // Draw signature section
    function SignatureSection()
    {
        // Make sure text color is black
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Arial', '', 9);
        
        // Headers for the signatures
        $this->Cell(63, 6, 'Dibuat Oleh,', 0, 0, 'C');
        $this->Cell(63, 6, 'Diperiksa Oleh,', 0, 0, 'C');
        $this->Cell(63, 6, 'Disetujui Oleh,', 0, 1, 'C');
        
        // Add space for signatures
        $this->Ln(10);
        
        // Signature lines
        $this->Cell(63, 6, '(________________)', 0, 0, 'C');
        $this->Cell(63, 6, '(________________)', 0, 0, 'C');
        $this->Cell(63, 6, '(________________)', 0, 1, 'C');
        
        // Signature labels
        $this->Cell(63, 6, 'Admin', 0, 0, 'C');
        $this->Cell(63, 6, 'Manajer', 0, 0, 'C');
        $this->Cell(63, 6, 'Pemilik', 0, 1, 'C');
    }
}

// Initialize PDF
$pdf = new PDF('P', 'mm', 'A4');

// Set document information
$pdf->SetTitle('Laporan Transaksi Penjualan ' . $periode_text);
$pdf->SetAuthor('BMS Bengkel');
$pdf->SetCreator('BMS Bengkel System');

// Set period and export date
$pdf->periode_text = $periode_text;
$pdf->export_date = date('d/m/Y H:i');

// Add a page
$pdf->AddPage();
$pdf->AliasNbPages();

// Define fixed table width - we'll use 195mm which is standard for A4 with margins
$tableWidth = 195;

// Define column widths - must sum up to tableWidth
$idWidth = 15;
$tanggalWidth = 25;
$kasirWidth = 35;
// Calculate remaining width for amount columns (divide equally)
$remainingWidth = $tableWidth - ($idWidth + $tanggalWidth + $kasirWidth);
$amountColWidth = $remainingWidth / 5;

// Table Header - using the full tableWidth
$pdf->SetFillColor(94, 53, 177); // Secondary purple color
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell($tableWidth, 8, 'DAFTAR TRANSAKSI', 1, 1, 'L', true);
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(2);

// Column Headers
$pdf->SetFillColor(94, 53, 177); // Secondary purple color
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell($idWidth, 7, 'ID', 1, 0, 'C', true);
$pdf->Cell($tanggalWidth, 7, 'Tanggal', 1, 0, 'C', true);
$pdf->Cell($kasirWidth, 7, 'Kasir', 1, 0, 'C', true);
$pdf->Cell($amountColWidth, 7, 'Total', 1, 0, 'C', true);
$pdf->Cell($amountColWidth, 7, 'Uang Masuk', 1, 0, 'C', true);
$pdf->Cell($amountColWidth, 7, 'Piutang', 1, 0, 'C', true);
$pdf->Cell($amountColWidth, 7, 'Harga Modal', 1, 0, 'C', true);
$pdf->Cell($amountColWidth, 7, 'Laba Kotor', 1, 1, 'C', true);

// Reset text color
$pdf->SetTextColor(0, 0, 0);

// Add data rows
$no = 1;
$rowColor = 255; // Starting with white

if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Alternate row colors (white and light purple)
        $rowColor = ($rowColor == 255) ? 243 : 255;
        
        // Reset text color at the beginning of each row
        $pdf->SetTextColor(0, 0, 0);
        
        $pdf->SetFillColor($rowColor, $rowColor, $rowColor);
        
        // Row data - using smaller font
        $pdf->SetFont('Arial', '', 7);
        
        // ID Transaksi
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell($idWidth, 6, $row['id'], 1, 0, 'C', true);
        
        // Tanggal (without time)
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell($tanggalWidth, 6, date('d/m/Y', strtotime($row['tanggal'])), 1, 0, 'C', true);
        
        // Kasir (with increased width)
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell($kasirWidth, 6, utf8_decode($row['kasir']), 1, 0, 'L', true);
        
        // Total Pendapatan
        $pdf->SetTextColor(94, 53, 177); // Purple text for total
        $pdf->Cell($amountColWidth, 6, 'Rp ' . number_format($row['total'], 0, ',', '.'), 1, 0, 'R', true);
        
        // Uang Masuk
        $pdf->SetTextColor(38, 166, 154); // Teal text for cash
        $pdf->Cell($amountColWidth, 6, 'Rp ' . number_format($row['pendapatan'], 0, ',', '.'), 1, 0, 'R', true);
        
        // Piutang
        if ($row['hutang'] > 0) {
            $pdf->SetTextColor(211, 47, 47); // Red text for debt
            $pdf->Cell($amountColWidth, 6, 'Rp ' . number_format($row['hutang'], 0, ',', '.'), 1, 0, 'R', true);
        } else {
            $pdf->SetTextColor(76, 175, 80); // Green text for no debt
            $pdf->Cell($amountColWidth, 6, 'Rp 0', 1, 0, 'R', true);
        }
        
        // Harga Modal
        $pdf->SetTextColor(117, 117, 117); // Gray text for modal
        $pdf->Cell($amountColWidth, 6, 'Rp ' . number_format($row['total_harga_beli'], 0, ',', '.'), 1, 0, 'R', true);
        
        // Laba Kotor
        $pdf->SetTextColor(76, 175, 80); // Green text for profit
        $pdf->Cell($amountColWidth, 6, 'Rp ' . number_format($row['keuntungan'], 0, ',', '.'), 1, 1, 'R', true);
        
        // Reset text color at the end of each row
        $pdf->SetTextColor(0, 0, 0);
    }
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell($tableWidth, 10, 'Tidak ada data transaksi pada periode yang dipilih.', 1, 1, 'C', true);
}

// Reset text color before summary section
$pdf->SetTextColor(0, 0, 0);

// Summary section
$pdf->Ln(5);

// Summary header with the same width as the transaction table
$pdf->SetFillColor(94, 53, 177); // Secondary purple color
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell($tableWidth, 8, 'RINGKASAN TRANSAKSI', 1, 1, 'L', true);
$pdf->SetTextColor(0, 0, 0);

// Define fixed width for the summary table - matching the transactions table width
$labelWidth = $tableWidth / 2;
$valueWidth = $tableWidth / 2;

// Total Transaksi
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(237, 231, 246); // Light purple
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell($labelWidth, 8, 'Total Transaksi:', 1, 0, 'L', true);
$pdf->Cell($valueWidth, 8, number_format($summary_data['total_transaksi']) . ' transaksi', 1, 1, 'L', true);

// Total Pendapatan
$pdf->SetFillColor(232, 234, 246); // Light indigo
$pdf->SetTextColor(94, 53, 177);
$pdf->Cell($labelWidth, 8, 'Total Pendapatan:', 1, 0, 'L', true);
$pdf->Cell($valueWidth, 8, 'Rp ' . number_format($summary_data['total_pendapatan'], 0, ',', '.'), 1, 1, 'R', true);
$pdf->SetTextColor(0, 0, 0);

// Total Uang Masuk (Kas)
$pdf->SetFillColor(224, 242, 241); // Light teal
$pdf->SetTextColor(0, 137, 123);
$pdf->Cell($labelWidth, 8, 'Total Uang Masuk (Kas):', 1, 0, 'L', true);
$pdf->Cell($valueWidth, 8, 'Rp ' . number_format($summary_data['total_kas'], 0, ',', '.'), 1, 1, 'R', true);
$pdf->SetTextColor(0, 0, 0);

// Total Piutang
$pdf->SetFillColor(255, 235, 238); // Light red
$pdf->SetTextColor(211, 47, 47);
$pdf->Cell($labelWidth, 8, 'Total Piutang:', 1, 0, 'L', true);
$pdf->Cell($valueWidth, 8, 'Rp ' . number_format($summary_data['total_hutang'], 0, ',', '.'), 1, 1, 'R', true);
$pdf->SetTextColor(0, 0, 0);

// Total Laba Kotor
$pdf->SetFillColor(232, 245, 233); // Light green
$pdf->SetTextColor(46, 125, 50);
$pdf->Cell($labelWidth, 8, 'Total Laba Kotor:', 1, 0, 'L', true);
$pdf->Cell($valueWidth, 8, 'Rp ' . number_format($summary_data['total_laba'], 0, ',', '.'), 1, 1, 'R', true);
$pdf->SetTextColor(0, 0, 0);

// Profit Margin (percentage of laba to pendapatan)
$profit_margin = ($summary_data['total_pendapatan'] > 0) ? 
                 ($summary_data['total_laba'] / $summary_data['total_pendapatan'] * 100) : 0;

$pdf->SetFillColor(225, 245, 254); // Light blue
$pdf->SetTextColor(2, 119, 189);
$pdf->Cell($labelWidth, 8, 'Margin Keuntungan:', 1, 0, 'L', true);
$pdf->Cell($valueWidth, 8, number_format($profit_margin, 2) . ' %', 1, 1, 'R', true);
$pdf->SetTextColor(0, 0, 0);

// Check if we're approaching the end of the page
if ($pdf->GetY() > 220) {
    $pdf->AddPage();
} else {
    // Add space between summary and signatures
    $pdf->Ln(10);
}

// Draw the signature section
$pdf->SignatureSection();

// Output the PDF
$filename = 'Laporan_Transaksi_' . date('Ymd', strtotime($tgl_awal)) . '_' . date('Ymd', strtotime($tgl_akhir)) . '.pdf';
$pdf->Output($filename, 'I'); // 'I' means show in browser