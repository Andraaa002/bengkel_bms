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

// Define stock threshold - CHANGED FROM 10 to 5
$stock_threshold = 5; // Products with stock ≤ 5 are considered "low stock"

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

// Create a custom PDF class with modern styling
class PDF extends FPDF
{
    // Properties to hold month and date information
    public $month_year;
    public $export_date;
    
    // Colors
    private $headerBlueColor = array(21, 101, 192); // Blue header
    private $tableHeaderBlueColor = array(25, 90, 171); // Dark blue for table headers
    private $summaryBlueColor = array(51, 102, 204); // Blue for summary headers
    private $alternateBlueColor = array(217, 226, 243); // Light blue for alternate rows
    private $lightOrangeColor = array(255, 235, 156); // Light orange for low stock
    private $lightGreenColor = array(226, 239, 218); // Light green for good values
    private $lightPinkColor = array(255, 204, 204); // Light pink/red for warning
    
    // Page header
    function Header()
    {
        // Title background - CHANGED FROM ORANGE TO BLUE
        $this->SetFillColor($this->headerBlueColor[0], $this->headerBlueColor[1], $this->headerBlueColor[2]);
        $this->SetDrawColor($this->headerBlueColor[0], $this->headerBlueColor[1], $this->headerBlueColor[2]);
        $this->Rect(0, 0, $this->GetPageWidth(), 35, 'F');
        
        // IMPROVED SPACING FOR HEADER TEXT
        // Title - positioned at 12mm from top
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(255, 255, 255);
        $this->SetY(12);
        $this->Cell(0, 6, 'DATA PRODUK BENGKEL BMS', 0, 1, 'C');
        
        // Month/Year - positioned at 20mm from top (8mm below title)
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(255, 255, 255);
        $this->SetY(20);
        $this->Cell(0, 6, 'PERIODE: ' . $this->month_year, 0, 1, 'C');
        
        // Export date - positioned at 27mm from top (7mm below subtitle)
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(255, 255, 255);
        $this->SetY(27);
        $this->Cell(0, 6, 'Tanggal Export: ' . $this->export_date, 0, 1, 'C');
        
        // Note about low stock - positioned at 40mm from top (5mm below header)
        $this->SetY(40);
        $this->SetTextColor(100, 100, 100);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 5, '* Stok menipis: produk dengan stok kurang dari atau sama dengan 5 unit', 0, 1, 'L');
        
        // Add space before table - 5mm gap to table
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
        // Use blue color for table headers
        $this->SetFillColor($this->tableHeaderBlueColor[0], $this->tableHeaderBlueColor[1], $this->tableHeaderBlueColor[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(8, 7, 'No', 1, 0, 'C', true);
        $this->Cell(38, 7, 'Nama Produk', 1, 0, 'C', true);
        $this->Cell(15, 7, 'Kategori', 1, 0, 'C', true);
        $this->Cell(20, 7, 'Harga Beli', 1, 0, 'C', true);
        $this->Cell(20, 7, 'Harga Jual', 1, 0, 'C', true);
        $this->Cell(12, 7, 'Stok', 1, 0, 'C', true);
        $this->Cell(22, 7, 'Nilai Modal', 1, 0, 'C', true);
        $this->Cell(22, 7, 'Nilai Jual', 1, 0, 'C', true);
        $this->Cell(15, 7, 'Status', 1, 0, 'C', true);
        $this->Cell(23, 7, 'Nominal Hutang', 1, 1, 'C', true);
        
        // Reset colors
        $this->SetTextColor(0, 0, 0);
    }
    
    // Summary Header
    function SummaryHeader()
    {
        $this->SetFillColor($this->summaryBlueColor[0], $this->summaryBlueColor[1], $this->summaryBlueColor[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(195, 8, 'RINGKASAN DATA PRODUK', 1, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
    }
    
    // Draw signature section
function SignatureSection()
{
    // Make sure text color is black
    $this->SetTextColor(0, 0, 0);
    $this->SetFont('Arial', '', 9); // Reduced font size from 10 to 9
    
    // Headers for the signatures - positioned across the page
    $this->Cell(63, 6, 'Dibuat Oleh,', 0, 0, 'C'); // Reduced height from 8 to 6
    $this->Cell(63, 6, 'Diperiksa Oleh,', 0, 0, 'C');
    $this->Cell(63, 6, 'Disetujui Oleh,', 0, 1, 'C');
    
    // Add space for signatures - reduced from 15mm to 10mm high
    $this->Ln(10);
    
    // Signature lines
    $this->Cell(63, 6, '(________________)', 0, 0, 'C'); // Reduced height from 8 to 6
    $this->Cell(63, 6, '(________________)', 0, 0, 'C');
    $this->Cell(63, 6, '(________________)', 0, 1, 'C');
    
    // Signature labels directly below the lines
    $this->Cell(63, 6, 'Admin', 0, 0, 'C'); // Reduced height from 8 to 6
    $this->Cell(63, 6, 'Manajer', 0, 0, 'C');
    $this->Cell(63, 6, 'Pemilik', 0, 1, 'C');
}
}

// Initialize PDF
$pdf = new PDF('P', 'mm', 'A4');

// Set document information
$pdf->SetTitle('Data Produk BMS Bengkel - ' . $bulan_tahun);
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

if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Alternate row colors (white and very light gray)
        $rowColor = ($rowColor == 255) ? 245 : 255;
        
        // IMPORTANT: Reset text color at the beginning of each row
        $pdf->SetTextColor(0, 0, 0);
        
        $pdf->SetFillColor($rowColor, $rowColor, $rowColor);
        
        // Calculate inventory value and sales value
        $inventory_value = $row['harga_beli'] * $row['stok'];
        $sales_value = $row['harga_jual'] * $row['stok'];
        
        // Row data - using smaller font and ensuring black text
        $pdf->SetFont('Arial', '', 7);
        
        // Cell for No - explicitly using black color
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(8, 6, $no++, 1, 0, 'C', true);
        
        // Cell for Nama Produk - explicitly using black color
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(38, 6, utf8_decode($row['nama']), 1, 0, 'L', true);
        
        // Cell for Kategori - explicitly using black color
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(15, 6, utf8_decode($row['nama_kategori'] ?: 'N/A'), 1, 0, 'L', true);
        
        // Cell for Harga Beli and Harga Jual - explicitly using black color
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(20, 6, 'Rp ' . number_format($row['harga_beli'], 0, ',', '.'), 1, 0, 'R', true);
        $pdf->Cell(20, 6, 'Rp ' . number_format($row['harga_jual'], 0, ',', '.'), 1, 0, 'R', true);
        
        // Stock cell with color based on value
        if ($row['stok'] == 0) {
            $pdf->SetFillColor(255, 204, 204); // Red for zero stock
            $pdf->SetTextColor(255, 0, 0); // Red text
            $pdf->Cell(12, 6, $row['stok'], 1, 0, 'C', true);
            // Restore fill color
            $pdf->SetFillColor($rowColor, $rowColor, $rowColor);
        } elseif ($row['stok'] <= 5) {
            $pdf->SetFillColor(255, 235, 156); // Orange for low stock
            $pdf->SetTextColor(255, 102, 0); // Orange text
            $pdf->Cell(12, 6, $row['stok'], 1, 0, 'C', true);
            // Restore fill color
            $pdf->SetFillColor($rowColor, $rowColor, $rowColor);
        } else {
            // Reset text color to black for normal stock
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(12, 6, $row['stok'], 1, 0, 'C', true);
        }
        
        // Inventory value (Modal) - Using blue color
        $pdf->SetFillColor(217, 226, 243); // Light blue for modal
        $pdf->SetTextColor(0, 0, 0); // Reset to black text
        $pdf->Cell(22, 6, 'Rp ' . number_format($inventory_value, 0, ',', '.'), 1, 0, 'R', true);
        
        // Sales value (Nilai Jual) - Using green color
        $pdf->SetFillColor(226, 239, 218); // Light green for nilai jual
        $pdf->SetTextColor(0, 0, 0); // Reset to black text
        $pdf->Cell(22, 6, 'Rp ' . number_format($sales_value, 0, ',', '.'), 1, 0, 'R', true);
        
        // Restore row fill color
        $pdf->SetFillColor($rowColor, $rowColor, $rowColor);
        
        // Payment status
        if ($row['hutang_sparepart'] == 'Hutang') {
            // Use red for Hutang status
            $pdf->SetFillColor(255, 204, 204); // Light red for hutang
            $pdf->SetTextColor(198, 40, 40); // Dark red text
            $pdf->Cell(15, 6, 'Hutang', 1, 0, 'C', true);
            
            // Nominal Hutang with red background for hutang
            $pdf->Cell(23, 6, 'Rp ' . number_format($row['nominal_hutang'], 0, ',', '.'), 1, 1, 'R', true);
        } else {
            // Use green for Cash status
            $pdf->SetFillColor(200, 230, 201); // Light green for cash
            $pdf->SetTextColor(46, 125, 50); // Dark green text
            $pdf->Cell(15, 6, 'Cash', 1, 0, 'C', true);
            
            // Restore fill color for nominal hutang
            $pdf->SetFillColor($rowColor, $rowColor, $rowColor);
            $pdf->SetTextColor(0, 0, 0); // Reset to black text
            
            // Nominal Hutang normal
            $pdf->Cell(23, 6, 'Rp ' . number_format($row['nominal_hutang'], 0, ',', '.'), 1, 1, 'R', true);
        }
        
        // Reset text color at the end of each row
        $pdf->SetTextColor(0, 0, 0);
    }
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 10, 'Tidak ada data produk', 1, 1, 'C', true);
}

// RESET TEXT COLOR before summary section
$pdf->SetTextColor(0, 0, 0);

// Summary section
$pdf->Ln(5); // Reduced spacing to save space

// Get the current X position
$startX = $pdf->GetX();
$summaryWidth = 195; // Fixed width for the entire summary section

$pdf->SummaryHeader();

// Define fixed width for the summary table
$labelWidth = 70;
$valueWidth = $summaryWidth - $labelWidth;

// White background for "Total Produk"
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(255, 255, 255);
$pdf->SetTextColor(0, 0, 0); // Reset to black
$pdf->Cell($labelWidth, 8, 'Total Produk:', 1, 0, 'L', true);
$pdf->Cell($valueWidth, 8, $summary['total_produk'] . ' produk', 1, 1, 'L', true);

// Blue background for "Total Stok Tersedia"
$pdf->SetFillColor(51, 102, 204);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell($labelWidth, 8, 'Total Stok Tersedia:', 1, 0, 'L', true);
$pdf->Cell($valueWidth, 8, $summary['total_stok'] . ' unit', 1, 1, 'L', true);
$pdf->SetTextColor(0, 0, 0);

// Light orange for "Produk dengan Stok Menipis"
$pdf->SetFillColor(255, 235, 156);
$textColor = $summary['produk_stok_menipis'] > 0 ? array(255, 102, 0) : array(0, 0, 0);
$pdf->SetTextColor(0, 0, 0); // Reset first
$pdf->Cell($labelWidth, 8, 'Produk dengan Stok Menipis (<=5):', 1, 0, 'L', true);
$pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
$pdf->Cell($valueWidth, 8, $summary['produk_stok_menipis'] . ' produk', 1, 1, 'L', true);
$pdf->SetTextColor(0, 0, 0);

// Light blue for "Total Nilai Inventory (Modal)"
$pdf->SetFillColor(217, 226, 243);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell($labelWidth, 8, 'Total Nilai Inventory (Modal):', 1, 0, 'L', true);
$pdf->Cell($valueWidth, 8, 'Rp ' . number_format($summary['total_nilai_inventory'], 0, ',', '.'), 1, 1, 'R', true);

// Light green for "Total Nilai Jual"
$pdf->SetFillColor(226, 239, 218);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell($labelWidth, 8, 'Total Nilai Jual:', 1, 0, 'L', true);
$pdf->Cell($valueWidth, 8, 'Rp ' . number_format($summary['total_nilai_jual'], 0, ',', '.'), 1, 1, 'R', true);

// Light yellow for "Potensi Keuntungan"
$pdf->SetFillColor(255, 235, 156);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell($labelWidth, 8, 'Potensi Keuntungan:', 1, 0, 'L', true);
$pdf->Cell($valueWidth, 8, 'Rp ' . number_format($potential_profit, 0, ',', '.') . ' (' . number_format($profit_percentage, 1) . '%)', 1, 1, 'R', true);

// Light red for "Produk dengan Status Hutang"
$pdf->SetFillColor(255, 204, 204);
$textColor = $summary['produk_hutang'] > 0 ? array(198, 40, 40) : array(0, 0, 0);
$pdf->SetTextColor(0, 0, 0); // Reset first
$pdf->Cell($labelWidth, 8, 'Produk dengan Status Hutang:', 1, 0, 'L', true);
$pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
$pdf->Cell($valueWidth, 8, $summary['produk_hutang'] . ' produk', 1, 1, 'L', true);
$pdf->SetTextColor(0, 0, 0);

// Light red for "Total Nominal Hutang"
$pdf->SetFillColor(255, 204, 204);
$textColor = $summary['total_hutang'] > 0 ? array(198, 40, 40) : array(0, 0, 0);
$pdf->SetTextColor(0, 0, 0); // Reset first
$pdf->Cell($labelWidth, 8, 'Total Nominal Hutang:', 1, 0, 'L', true);
$pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
$pdf->Cell($valueWidth, 8, 'Rp ' . number_format($summary['total_hutang'], 0, ',', '.'), 1, 1, 'R', true);
$pdf->SetTextColor(0, 0, 0);

// Check if we're approaching the end of the page
// If there's not enough room for signatures, move to the next page
if ($pdf->GetY() > 220) {
    $pdf->AddPage();
} else {
    // Add space between summary and signatures
    $pdf->Ln(10); // Keep this at 10 or reduce to 5
}
// Draw the signature section
$pdf->SignatureSection();

// Output the PDF
$pdf->Output('Data_Produk_' . date('m-Y') . '.pdf', 'I'); // 'I' means show in browser