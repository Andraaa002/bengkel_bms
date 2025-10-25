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

// Require library FPDF
require('../fpdf186/fpdf.php');

// Get bulan parameter from URL
$bulan_filter = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');
$bulan_tahun = date('F Y', strtotime($bulan_filter . '-01')); // Format: April 2025

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

// Create a custom PDF class with modern styling
class PDF extends FPDF
{
    // Properties to hold month and date information
    public $month_year;
    public $export_date;
    
    // Colors (orange theme - matching with manager dashboard)
    private $headerOrangeColor = array(239, 108, 0); // Primary orange
    private $darkOrangeColor = array(216, 67, 21); // Accent orange
    private $tableHeaderOrangeColor = array(239, 108, 0); // Primary orange for headers
    private $summaryOrangeColor = array(239, 108, 0); // Primary orange for summary
    private $alternateOrangeColor = array(255, 243, 224); // Light orange for alternating rows
    private $lightOrangeColor = array(255, 235, 156); // Light orange for warnings
    private $lightGreenColor = array(226, 239, 218); // Light green for positive values
    private $lightPinkColor = array(255, 204, 204); // Light pink/red for negative warnings
    
    // Page header
    function Header()
    {
        // Title background
        $this->SetFillColor($this->headerOrangeColor[0], $this->headerOrangeColor[1], $this->headerOrangeColor[2]);
        $this->SetDrawColor($this->headerOrangeColor[0], $this->headerOrangeColor[1], $this->headerOrangeColor[2]);
        $this->Rect(0, 0, $this->GetPageWidth(), 35, 'F');
        
        // Title - positioned at 12mm from top
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(255, 255, 255);
        $this->SetY(12);
        $this->Cell(0, 6, 'RIWAYAT PEMBAYARAN HUTANG & PIUTANG', 0, 1, 'C');
        
        // Month/Year - positioned at 20mm from top
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(255, 255, 255);
        $this->SetY(20);
        $this->Cell(0, 6, 'PERIODE: ' . $this->month_year, 0, 1, 'C');
        
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
        // Use orange color for table headers
        $this->SetFillColor($this->tableHeaderOrangeColor[0], $this->tableHeaderOrangeColor[1], $this->tableHeaderOrangeColor[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(8, 7, 'No', 1, 0, 'C', true);
        $this->Cell(20, 7, 'Tanggal Bayar', 1, 0, 'C', true);
        $this->Cell(28, 7, 'Tipe Pembayaran', 1, 0, 'C', true);
        $this->Cell(30, 7, 'Customer', 1, 0, 'C', true);
        $this->Cell(15, 7, 'Status', 1, 0, 'C', true);
        $this->Cell(42, 7, 'Keterangan', 1, 0, 'C', true);
        $this->Cell(30, 7, 'Jumlah', 1, 0, 'C', true);
        $this->Cell(22, 7, 'Dibuat Oleh', 1, 1, 'C', true);
        
        // Reset colors
        $this->SetTextColor(0, 0, 0);
    }
    
    // Summary Header
    function SummaryHeader()
    {
        $this->SetFillColor($this->summaryOrangeColor[0], $this->summaryOrangeColor[1], $this->summaryOrangeColor[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(195, 8, 'RINGKASAN PEMBAYARAN', 1, 1, 'L', true);
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
$pdf->SetTitle('Riwayat Pembayaran Hutang & Piutang - ' . $bulan_tahun);
$pdf->SetAuthor('BMS Bengkel');
$pdf->SetCreator('BMS Bengkel System');

// Set month and export date
$pdf->month_year = $bulan_tahun;
$pdf->export_date = date('d/m/Y H:i');

// Add a page
$pdf->AddPage();
$pdf->AliasNbPages();

// Table Header
$pdf->TableHeader();

// Add data rows
$no = 1;
$rowColor = 255; // Starting with white

if (mysqli_num_rows($piutang_cair_result) > 0) {
    while ($row = mysqli_fetch_assoc($piutang_cair_result)) {
        // Alternate row colors (white and light orange)
        $rowColor = ($rowColor == 255) ? 245 : 255;
        
        // Reset text color at the beginning of each row
        $pdf->SetTextColor(0, 0, 0);
        
        $pdf->SetFillColor($rowColor, $rowColor, $rowColor);
        
        // Row data - using smaller font
        $pdf->SetFont('Arial', '', 7);
        
        // Cell for No
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(8, 6, $no++, 1, 0, 'C', true);
        
        // Cell for Tanggal Bayar
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(20, 6, date('d/m/Y', strtotime($row['tanggal_bayar'])), 1, 0, 'C', true);
        
        // Cell for Tipe Pembayaran
        if ($row['tipe'] == 'Produk') {
            $pdf->SetFillColor(226, 239, 218); // Light green for product payments
            $pdf->SetTextColor(25, 135, 84);
            $pdf->Cell(28, 6, 'Hutang Produk', 1, 0, 'C', true);
            $pdf->SetFillColor($rowColor, $rowColor, $rowColor);
        } else {
            $pdf->SetFillColor(217, 226, 243); // Light blue for transaction payments
            $pdf->SetTextColor(13, 110, 253);
            $pdf->Cell(28, 6, 'Piutang Transaksi', 1, 0, 'C', true);
            $pdf->SetFillColor($rowColor, $rowColor, $rowColor);
        }
        
        // Cell for Customer
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(30, 6, utf8_decode($row['nama_customer']), 1, 0, 'L', true);
        
        // Cell for Status
        if ($row['status_pembayaran'] == 'LUNAS') {
            $pdf->SetFillColor(226, 239, 218); // Light green for LUNAS
            $pdf->SetTextColor(25, 135, 84);
            $pdf->Cell(15, 6, 'LUNAS', 1, 0, 'C', true);
            $pdf->SetFillColor($rowColor, $rowColor, $rowColor);
        } else {
            $pdf->SetFillColor(255, 243, 205); // Light orange for HUTANG
            $pdf->SetTextColor(253, 126, 20);
            $pdf->Cell(15, 6, 'HUTANG', 1, 0, 'C', true);
            $pdf->SetFillColor($rowColor, $rowColor, $rowColor);
        }
        
        // Cell for Keterangan
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(42, 6, utf8_decode(substr($row['keterangan'], 0, 55)), 1, 0, 'L', true);
        
        // Cell for Jumlah
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(30, 6, 'Rp ' . number_format($row['jumlah_bayar'], 0, ',', '.'), 1, 0, 'R', true);
        
        // Cell for Dibuat Oleh
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(22, 6, utf8_decode($row['nama_user']), 1, 1, 'L', true);
        
        // Reset text color at the end of each row
        $pdf->SetTextColor(0, 0, 0);
    }
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(195, 10, 'Tidak ada data pembayaran pada periode ini', 1, 1, 'C', true);
}

// Reset text color before summary section
$pdf->SetTextColor(0, 0, 0);

// Summary section
$pdf->Ln(5);

// Get the current X position
$startX = $pdf->GetX();
$summaryWidth = 195; // Fixed width for the entire summary section

$pdf->SummaryHeader();

// Define fixed width for the summary table
$labelWidth = 100;
$valueWidth = $summaryWidth - $labelWidth;

// Total Pembayaran
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(255, 255, 255);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell($labelWidth, 8, 'Total Pembayaran:', 1, 0, 'L', true);
$pdf->Cell($valueWidth, 8, $stats_data['total_pembayaran'] . ' transaksi', 1, 1, 'L', true);

// Total Nominal
$pdf->SetFillColor(255, 243, 224); // Light orange
$pdf->SetTextColor(239, 108, 0);
$pdf->Cell($labelWidth, 8, 'Total Nominal Pembayaran:', 1, 0, 'L', true);
$pdf->Cell($valueWidth, 8, 'Rp ' . number_format($stats_data['total_bayar'], 0, ',', '.'), 1, 1, 'R', true);
$pdf->SetTextColor(0, 0, 0);

// Pembayaran Hutang Produk
$pdf->SetFillColor(226, 239, 218); // Light green
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell($labelWidth, 8, 'Pembayaran Hutang Produk:', 1, 0, 'L', true);
$pdf->Cell($valueWidth, 8, 'Rp ' . number_format($stats_data['total_nominal_produk'], 0, ',', '.') . 
           ' (' . $stats_data['total_bayar_produk'] . ' transaksi)', 1, 1, 'R', true);

// Pembayaran Piutang Transaksi
$pdf->SetFillColor(217, 226, 243); // Light blue
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell($labelWidth, 8, 'Pembayaran Piutang Transaksi:', 1, 0, 'L', true);
$pdf->Cell($valueWidth, 8, 'Rp ' . number_format($stats_data['total_nominal_transaksi'], 0, ',', '.') . 
           ' (' . $stats_data['total_bayar_transaksi'] . ' transaksi)', 1, 1, 'R', true);

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
$pdf->Output('Riwayat_Pembayaran_' . $bulan_filter . '.pdf', 'I'); // 'I' means show in browser