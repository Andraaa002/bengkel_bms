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

// Require library FPDF
require('../fpdf186/fpdf.php');

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

// Create a custom PDF class with modern styling
class PDF extends FPDF
{
    // Properties to hold period and date information
    public $periode;
    public $export_date;
    public $title_suffix;
    
    // Colors
    private $headerPurpleColor = array(126, 87, 194); // Purple header
    private $tableHeaderPurpleColor = array(94, 53, 177); // Dark purple for table headers
    private $summaryPurpleColor = array(94, 53, 177); // Purple for summary headers
    private $alternatePurpleColor = array(237, 231, 246); // Light purple for alternate rows
    private $operationalColor = array(237, 231, 246); // Light purple for operational
    private $karyawanColor = array(232, 245, 233); // Light green for employee
    private $kasbonColor = array(255, 249, 196); // Light yellow for kasbon
    private $produkColor = array(255, 235, 238); // Light red for product
    private $makanColor = array(227, 242, 253); // Light blue for meals
    private $totalColor = array(255, 205, 210); // Light red for total
    private $hutangProdukColor = array(243, 229, 245); // Light purple for hutang produk
    
    // Page header
    function Header()
    {
        // Title background - PURPLE
        $this->SetFillColor($this->headerPurpleColor[0], $this->headerPurpleColor[1], $this->headerPurpleColor[2]);
        $this->SetDrawColor($this->headerPurpleColor[0], $this->headerPurpleColor[1], $this->headerPurpleColor[2]);
        $this->Rect(0, 0, $this->GetPageWidth(), 35, 'F');
        
        // IMPROVED SPACING FOR HEADER TEXT
        // Title - positioned at 12mm from top
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(255, 255, 255);
        $this->SetY(12);
        $this->Cell(0, 6, 'HISTORY PENGELUARAN BMS BENGKEL', 0, 1, 'C');
        
        // Subtitle with title suffix - positioned at 20mm from top (8mm below title)
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(255, 255, 255);
        $this->SetY(20);
        $this->Cell(0, 6, $this->title_suffix, 0, 1, 'C');
        
        // Export date - positioned at 27mm from top (7mm below subtitle)
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(255, 255, 255);
        $this->SetY(27);
        $this->Cell(0, 6, 'Periode: ' . $this->periode . ' | Export: ' . $this->export_date, 0, 1, 'C');
        
        // Add space before table - 5mm gap to table
        $this->SetY(40);
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
    
    // Table Header without source column
    function TableHeader()
    {
        // Use purple color for table headers
        $this->SetFillColor($this->tableHeaderPurpleColor[0], $this->tableHeaderPurpleColor[1], $this->tableHeaderPurpleColor[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(8, 7, 'No', 1, 0, 'C', true);
        $this->Cell(20, 7, 'Tanggal', 1, 0, 'C', true);
        $this->Cell(40, 7, 'Kategori', 1, 0, 'C', true);
        $this->Cell(30, 7, 'Jumlah', 1, 0, 'C', true);
        $this->Cell(97, 7, 'Keterangan', 1, 1, 'C', true);
        
        // Reset colors
        $this->SetTextColor(0, 0, 0);
    }
    
    // Summary Header
    function SummaryHeader()
    {
        $this->SetFillColor($this->summaryPurpleColor[0], $this->summaryPurpleColor[1], $this->summaryPurpleColor[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(195, 8, 'RINGKASAN PENGELUARAN', 1, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
    }
    
    // Category Breakdown Header
    function CategoryBreakdownHeader()
    {
        $this->SetFillColor($this->summaryPurpleColor[0], $this->summaryPurpleColor[1], $this->summaryPurpleColor[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(195, 8, 'RINCIAN PER KATEGORI', 1, 1, 'L', true);
        
        // Reset for normal text
        $this->SetTextColor(0, 0, 0);
        
        // Column headers
        $this->SetFillColor($this->tableHeaderPurpleColor[0], $this->tableHeaderPurpleColor[1], $this->tableHeaderPurpleColor[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(10, 7, 'No', 1, 0, 'C', true);
        $this->Cell(50, 7, 'Kategori', 1, 0, 'C', true);
        $this->Cell(30, 7, 'Jumlah Transaksi', 1, 0, 'C', true);
        $this->Cell(50, 7, 'Total Pengeluaran', 1, 0, 'C', true);
        $this->Cell(55, 7, 'Persentase', 1, 1, 'C', true);
        
        // Reset text color
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
$pdf->SetTitle('History Pengeluaran BMS Bengkel - ' . $title_suffix);
$pdf->SetAuthor('BMS Bengkel');
$pdf->SetCreator('BMS Bengkel System');

// Set periode and export date
$pdf->periode = $periode;
$pdf->export_date = date('d/m/Y H:i');
$pdf->title_suffix = $title_suffix;

// Add a page
$pdf->AddPage();
$pdf->AliasNbPages();

// Table Header
$pdf->TableHeader();

// Add data rows
$no = 1;
$rowColor = 255; // Starting with white
$total_amount = 0;

if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Alternate row colors (white and very light gray)
        $rowColor = ($rowColor == 255) ? 245 : 255;
        $total_amount += $row['jumlah'];
        
        // IMPORTANT: Reset text color at the beginning of each row
        $pdf->SetTextColor(0, 0, 0);
        
        $pdf->SetFillColor($rowColor, $rowColor, $rowColor);
        
        // Row data - using smaller font
        $pdf->SetFont('Arial', '', 7);
        
        // Cell for No
        $pdf->Cell(8, 6, $no++, 1, 0, 'C', true);
        
        // Cell for Tanggal
        $pdf->Cell(20, 6, date('d/m/Y', strtotime($row['tanggal'])), 1, 0, 'C', true);
        
        // Cell for Kategori with color based on category
        if (in_array($row['kategori'], ['Sewa Lahan', 'Token Listrik', 'Air', 'Internet', 'Lainnya'])) {
            $pdf->SetFillColor(237, 231, 246); // Light purple for operational
        } elseif ($row['kategori'] == 'Gaji Karyawan' || $row['kategori_transaksi'] == 'Gaji Karyawan') {
            $pdf->SetFillColor(232, 245, 233); // Light green for salary
        } elseif ($row['kategori'] == 'Kasbon Karyawan' || $row['kategori_transaksi'] == 'Kasbon Karyawan') {
            $pdf->SetFillColor(255, 249, 196); // Light yellow for kasbon
        } elseif ($row['kategori'] == 'Uang Makan' || $row['kategori_transaksi'] == 'Uang Makan') {
            $pdf->SetFillColor(227, 242, 253); // Light blue for meals
        } elseif ($row['kategori_transaksi'] == 'Pembelian Sparepart Baru' || 
                  $row['kategori'] == 'Pembelian Sparepart' || 
                  $row['kategori'] == 'Pembelian Barang') {
            $pdf->SetFillColor(255, 235, 238); // Light red for products
        } elseif ($row['kategori_transaksi'] == 'Tambah Stok') {
            $pdf->SetFillColor(224, 247, 250); // Light cyan for stock
        } elseif ($row['kategori_transaksi'] == 'Bayar Hutang Produk' || $row['kategori'] == 'Bayar Hutang Produk') {
            $pdf->SetFillColor(243, 229, 245); // Light purple for hutang produk
        } else {
            $pdf->SetFillColor($rowColor, $rowColor, $rowColor); // Default row color
        }
        
        $pdf->Cell(40, 6, utf8_decode($row['kategori_transaksi']), 1, 0, 'L', true);
        
        // Restore fill color for the rest of the row
        $pdf->SetFillColor($rowColor, $rowColor, $rowColor);
        
        // Cell for Jumlah in red background
        $pdf->SetFillColor(255, 235, 238); // Light red
        $pdf->Cell(30, 6, 'Rp ' . number_format($row['jumlah'], 0, ',', '.'), 1, 0, 'R', true);
        
        // Restore fill color
        $pdf->SetFillColor($rowColor, $rowColor, $rowColor);
        
        // Cell for Keterangan - shortened if too long
        $keterangan = $row['keterangan'];
        if (strlen($keterangan) > 85) {
            $keterangan = substr($keterangan, 0, 82) . '...';
        }
        $pdf->Cell(97, 6, utf8_decode($keterangan), 1, 1, 'L', true);
    }
    
    // Total row
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(255, 205, 210); // Light red for total
    $pdf->Cell(68, 7, 'TOTAL PENGELUARAN', 1, 0, 'R', true);
    $pdf->Cell(30, 7, 'Rp ' . number_format($total_amount, 0, ',', '.'), 1, 0, 'R', true);
    $pdf->SetFillColor(245, 245, 245); // Light gray
    $pdf->Cell(97, 7, '', 1, 1, 'L', true);
    
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(195, 10, 'Tidak ada data pengeluaran untuk periode ini', 1, 1, 'C', true);
}

// Summary section
$pdf->Ln(5);

// Summary Header
$pdf->SummaryHeader();

// Define fixed width for the summary table
$summaryWidth = 195;
$labelWidth = 100;
$valueWidth = $summaryWidth - $labelWidth;

// Total Transaksi
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(227, 242, 253); // Light blue
$pdf->Cell($labelWidth, 8, 'Total Transaksi Pengeluaran:', 1, 0, 'L', true);
$pdf->Cell($valueWidth, 8, $jumlah_transaksi . ' transaksi', 1, 1, 'L', true);

// Total Uang Kas Keluar
$pdf->SetFillColor(255, 205, 210); // Light red
$pdf->Cell($labelWidth, 8, 'Uang Kas Keluar (Total):', 1, 0, 'L', true);
$pdf->Cell($valueWidth, 8, 'Rp ' . number_format($total_pengeluaran, 0, ',', '.'), 1, 1, 'R', true);

// Pengeluaran Operasional
$pdf->SetFillColor(237, 231, 246); // Light purple
$pdf->Cell($labelWidth, 8, 'Pengeluaran Operasional:', 1, 0, 'L', true);
$pdf->Cell($valueWidth, 8, 'Rp ' . number_format($total_operasional, 0, ',', '.'), 1, 1, 'R', true);

// Pengeluaran Karyawan
$pdf->SetFillColor(232, 245, 233); // Light green
$pdf->Cell($labelWidth, 8, 'Pengeluaran Karyawan:', 1, 0, 'L', true);
$pdf->Cell($valueWidth, 8, 'Rp ' . number_format($total_karyawan, 0, ',', '.'), 1, 1, 'R', true);

// Pengeluaran Produk
$pdf->SetFillColor(255, 243, 224); // Light orange
$pdf->Cell($labelWidth, 8, 'Pengeluaran Produk/Sparepart:', 1, 0, 'L', true);
$pdf->Cell($valueWidth, 8, 'Rp ' . number_format($total_produk, 0, ',', '.'), 1, 1, 'R', true);

// Bayar Hutang Produk
$pdf->SetFillColor(243, 229, 245); // Light purple for hutang produk
$pdf->Cell($labelWidth, 8, 'Bayar Hutang Produk:', 1, 0, 'L', true);
$pdf->Cell($valueWidth, 8, 'Rp ' . number_format($total_bayar_hutang, 0, ',', '.'), 1, 1, 'R', true);

// Category breakdown if active_tab is "semua" and there's data
if ($active_tab == 'semua' && count($kategori_breakdown) > 0) {
    $pdf->Ln(5);
    $pdf->CategoryBreakdownHeader();
    
    $no = 1;
    foreach ($kategori_breakdown as $kategori) {
        $percentage = ($total_pengeluaran > 0) ? ($kategori['total'] / $total_pengeluaran * 100) : 0;
        
        // Get the category name from the modified query result
        $displayKategori = $kategori['display_kategori'];
        
        // Determine background color based on category
        if (in_array($displayKategori, ['Sewa Lahan', 'Token Listrik', 'Air', 'Internet', 'Lainnya'])) {
            $pdf->SetFillColor(237, 231, 246); // Light purple for operational
        } elseif ($displayKategori == 'Gaji Karyawan') {
            $pdf->SetFillColor(232, 245, 233); // Light green for salary
        } elseif ($displayKategori == 'Kasbon Karyawan') {
            $pdf->SetFillColor(255, 249, 196); // Light yellow for kasbon
        } elseif ($displayKategori == 'Uang Makan') {
            $pdf->SetFillColor(227, 242, 253); // Light blue for meals
        } elseif ($displayKategori == 'Pembelian Sparepart Baru') {
            $pdf->SetFillColor(255, 235, 238); // Light red for products
        } elseif ($displayKategori == 'Tambah Stok') {
            $pdf->SetFillColor(224, 247, 250); // Light cyan for stock
        } elseif ($displayKategori == 'Bayar Hutang Produk') {
            $pdf->SetFillColor(243, 229, 245); // Light purple for product debt
        } else {
            $pdf->SetFillColor(245, 245, 245); // Light gray for others
        }
        
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(10, 6, $no++, 1, 0, 'C', true);
        $pdf->Cell(50, 6, utf8_decode($displayKategori), 1, 0, 'L', true);
        $pdf->Cell(30, 6, $kategori['jumlah'] . ' transaksi', 1, 0, 'C', true);
        $pdf->Cell(50, 6, 'Rp ' . number_format($kategori['total'], 0, ',', '.'), 1, 0, 'R', true);
        $pdf->Cell(55, 6, number_format($percentage, 1) . '%', 1, 1, 'R', true);
    }
}

// Check if we're approaching the end of the page
// If there's not enough room for signatures, move to the next page
if ($pdf->GetY() > 220) {
    $pdf->AddPage();
} else {
    // Add space between summary and signatures
    $pdf->Ln(10);
}

// Draw the signature section
$pdf->SignatureSection();

// Output the PDF
$pdf->Output('History_Pengeluaran_' . date('m-Y') . '.pdf', 'I'); // 'I' means show in browser