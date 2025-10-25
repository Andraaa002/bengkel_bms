<?php
session_start();

// Cek apakah sudah login dan sebagai admin dengan namespace baru
if (!isset($_SESSION['admin']['logged_in']) || $_SESSION['admin']['logged_in'] !== true) {
  header("Location: ../login.php");
  exit();
}

include '../config.php'; // Pastikan path ke config.php benar

// Set default filter to current month
$today = date('Y-m-d');

// Standardized month filter parameter - ALWAYS default to current month
$bulan_filter = isset($_GET['bulan_filter']) ? $_GET['bulan_filter'] : date('Y-m');

// Handle date range filter if provided
$dari_tanggal = isset($_GET['dari_tanggal']) ? $_GET['dari_tanggal'] : date('Y-m-01'); // First day of current month
$sampai_tanggal = isset($_GET['sampai_tanggal']) ? $_GET['sampai_tanggal'] : date('Y-m-t'); // Last day of current month

$kasir = isset($_GET['kasir']) ? $_GET['kasir'] : '';

// Build where clause for filters
$where_clause = " WHERE 1=1 ";

// If bulan_filter is set, it takes precedence over other date filters
if (isset($_GET['bulan_filter']) && !empty($_GET['bulan_filter'])) {
    $where_clause = " WHERE DATE_FORMAT(t.tanggal, '%Y-%m') = '$bulan_filter' ";
} else if (!empty($dari_tanggal) && !empty($sampai_tanggal)) {
    $where_clause .= " AND DATE(t.tanggal) BETWEEN '$dari_tanggal' AND '$sampai_tanggal' ";
}

if (!empty($kasir)) {
    $where_clause .= " AND t.kasir = '$kasir' ";
}

// Query untuk mendapatkan data transaksi dengan harga beli dan harga jual
$query = "SELECT t.id, t.tanggal, t.total, u.nama AS kasir,
          SUM(td.jumlah * IFNULL(p.harga_beli, 0)) AS total_harga_beli,
          SUM(td.subtotal) AS total_harga_jual,
          (SUM(td.subtotal) - SUM(td.jumlah * IFNULL(p.harga_beli, 0))) AS keuntungan
          FROM transaksi t
          JOIN karyawan u ON t.kasir = u.username
          JOIN transaksi_detail td ON t.id = td.transaksi_id
          LEFT JOIN produk p ON td.produk_id = p.id
          $where_clause
          GROUP BY t.id, t.tanggal, t.total, u.nama
          ORDER BY t.tanggal DESC
          LIMIT 10";
$result = mysqli_query($conn, $query);

// Error handling for main query
if ($result === false) {
    error_log("Query failed: " . mysqli_error($conn) . " SQL: " . $query);
    $result = [];
}

// Perbaikan query untuk operasional expenses:
if (isset($_GET['bulan_filter']) && !empty($_GET['bulan_filter'])) {
  // Jika menggunakan filter bulan
  $operasional_query = "SELECT 
                      SUM(CASE WHEN kategori NOT IN ('Kasbon Karyawan', 'Uang Makan', 'Gaji Karyawan', 'Pembelian Sparepart', 'Pembelian Barang', 'Kasbon Manajer') 
                              AND keterangan NOT LIKE '%produk:%' 
                              AND keterangan NOT LIKE '%produk baru:%'
                              AND keterangan NOT LIKE 'Penambahan stok produk:%' 
                              THEN jumlah ELSE 0 END) as total_operasional,
                      SUM(CASE WHEN kategori IN ('Kasbon Karyawan', 'Uang Makan', 'Gaji Karyawan') 
                              THEN jumlah ELSE 0 END) as total_karyawan
                      FROM pengeluaran 
                      WHERE bulan = '$bulan_filter'";
} else {
  // Jika menggunakan filter tanggal
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

// Error handling for operasional_query
if ($operasional_result === false) {
  error_log("Operasional query failed: " . mysqli_error($conn) . " SQL: " . $operasional_query);
  $total_operasional = 0;
  $total_karyawan = 0;
} else {
  $operasional_row = mysqli_fetch_assoc($operasional_result);
  $total_operasional = $operasional_row['total_operasional'] ?? 0;
  $total_karyawan = $operasional_row['total_karyawan'] ?? 0;
}

// Query untuk mendapatkan total nilai inventory dan nilai jual
$inventory_query = "SELECT 
                  SUM(harga_beli * stok) AS total_nilai_inventory,
                  SUM(harga_jual * stok) AS total_nilai_jual
                  FROM produk 
                  WHERE stok > 0";
$inventory_result = mysqli_query($conn, $inventory_query);

// Error handling untuk inventory_query
if ($inventory_result === false) {
    error_log("Inventory query failed: " . mysqli_error($conn) . " SQL: " . $inventory_query);
    $total_nilai_inventory = 0;
    $total_nilai_jual = 0;
} else {
    $inventory_row = mysqli_fetch_assoc($inventory_result);
    $total_nilai_inventory = $inventory_row['total_nilai_inventory'] ?: 0;
    $total_nilai_jual = $inventory_row['total_nilai_jual'] ?: 0;
}

// Hitung potensi keuntungan dan persentasenya
$total_potensi_keuntungan = $total_nilai_jual - $total_nilai_inventory;
$persentase_potensi = ($total_nilai_inventory > 0) ? ($total_potensi_keuntungan / $total_nilai_inventory) * 100 : 0;

// Total biaya operasional (operasional + karyawan)
$total_biaya_operasional = $total_operasional + $total_karyawan;

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

// Error handling for total_keuntungan_query
if ($total_keuntungan_result === false) {
    error_log("Keuntungan query failed: " . mysqli_error($conn) . " SQL: " . $total_keuntungan_query);
    $total_keuntungan = 0;
} else {
    $total_keuntungan_row = mysqli_fetch_assoc($total_keuntungan_result);
    $total_keuntungan = $total_keuntungan_row['total_keuntungan'] ?: 0;
}

// Hitung total pendapatan dengan filter
$total_query = "SELECT SUM(total) AS total_pendapatan FROM transaksi t $where_clause";
$total_result = mysqli_query($conn, $total_query);

// Error handling for total_query
if ($total_result === false) {
    error_log("Total query failed: " . mysqli_error($conn) . " SQL: " . $total_query);
    $total_pendapatan = 0;
} else {
    $total_row = mysqli_fetch_assoc($total_result);
    $total_pendapatan = $total_row['total_pendapatan'] ?: 0;
}

// Tambahkan query untuk menghitung total uang kas (pendapatan real yang masuk)
$kas_query = "SELECT SUM(pendapatan) AS total_kas FROM transaksi t $where_clause";
$kas_result = mysqli_query($conn, $kas_query);

// Error handling for kas_query
if ($kas_result === false) {
    error_log("Kas query failed: " . mysqli_error($conn) . " SQL: " . $kas_query);
    $total_kas = 0;
} else {
    $kas_row = mysqli_fetch_assoc($kas_result);
    $total_kas = $kas_row['total_kas'] ?: 0;
}

// Query untuk menghitung total hutang
$hutang_query = "SELECT SUM(hutang) AS total_hutang FROM transaksi t $where_clause";
$hutang_result = mysqli_query($conn, $hutang_query);

// Error handling for hutang_query
if ($hutang_result === false) {
    error_log("Hutang query failed: " . mysqli_error($conn) . " SQL: " . $hutang_query);
    $total_hutang = 0;
} else {
    $hutang_row = mysqli_fetch_assoc($hutang_result);
    $total_hutang = $hutang_row['total_hutang'] ?: 0;
}

// Mengambil data kas bulanan dan hutang
$monthly_kas_query = "SELECT 
    DATE_FORMAT(t.tanggal, '%Y-%m') as bulan,
    SUM(t.pendapatan) AS kas_masuk,
    SUM(t.hutang) AS total_hutang
    FROM transaksi t
    WHERE DATE_FORMAT(t.tanggal, '%Y-%m') = '$bulan_filter'
    " . (!empty($kasir) ? " AND t.kasir = '$kasir' " : "") . "
    GROUP BY DATE_FORMAT(t.tanggal, '%Y-%m')
    ORDER BY bulan DESC";

$monthly_kas_result = mysqli_query($conn, $monthly_kas_query);

// Error handling for monthly_kas_query
if ($monthly_kas_result === false) {
    error_log("Monthly kas query failed: " . mysqli_error($conn) . " SQL: " . $monthly_kas_query);
    $monthly_kas = [];
} else {
    $monthly_kas = [];
    while ($row = mysqli_fetch_assoc($monthly_kas_result)) {
        $monthly_kas[$row['bulan']] = [
            'kas_masuk' => (float)$row['kas_masuk'],
            'total_hutang' => (float)$row['total_hutang']
        ];
    }
}

// Handle all_months initialization - MODIFIED to only include current month
$all_months = array_keys($monthly_kas);
    
// If empty, create array with just current month
if (empty($all_months)) {
    $all_months[] = $bulan_filter;
}

// Tambahkan data kas dan hutang ke array untuk chart
$kas_masuk_data = [];
$hutang_data = [];

foreach ($all_months as $month) {
    $kas_masuk = isset($monthly_kas[$month]) ? $monthly_kas[$month]['kas_masuk'] : 0;
    $hutang = isset($monthly_kas[$month]) ? $monthly_kas[$month]['total_hutang'] : 0;
    
    $kas_masuk_data[] = $kas_masuk;
    $hutang_data[] = $hutang;
}

$kas_masuk_data_json = json_encode($kas_masuk_data);
$hutang_data_json = json_encode($hutang_data);

// Hitung total transaksi dengan filter
$count_query = "SELECT COUNT(*) AS total_transaksi FROM transaksi t $where_clause";
$count_result = mysqli_query($conn, $count_query);

// Error handling for count_query
if ($count_result === false) {
    error_log("Count query failed: " . mysqli_error($conn) . " SQL: " . $count_query);
    $total_transaksi = 0;
} else {
    $count_row = mysqli_fetch_assoc($count_result);
    $total_transaksi = $count_row['total_transaksi'];
}

// Mengambil pendapatan dari penjualan barang bekas
if (isset($_GET['bulan_filter']) && !empty($_GET['bulan_filter'])) {
    // Jika filter menggunakan bulan, ambil penjualan barang bekas sesuai bulan
    // Hanya ambil penjualan yang status_kas = 1 (sudah masuk kas) jika kolom ini ada
    $cek_status_kas = mysqli_query($conn, "SHOW COLUMNS FROM jual_bekas LIKE 'status_kas'");
    
    if (mysqli_num_rows($cek_status_kas) > 0) {
        // Jika kolom status_kas ada
        $bekas_query = "SELECT SUM(total_harga) as total_pendapatan_bekas
                      FROM jual_bekas 
                      WHERE bulan = '$bulan_filter' AND status_kas = 1";
        
        // Query untuk penjualan barang bekas yang belum masuk kas
        $bekas_belum_kas_query = "SELECT SUM(total_harga) as total_bekas_belum_kas
                               FROM jual_bekas 
                               WHERE bulan = '$bulan_filter' AND (status_kas = 0 OR status_kas IS NULL)";
    } else {
        // Jika kolom status_kas tidak ada, ambil semua
        $bekas_query = "SELECT SUM(total_harga) as total_pendapatan_bekas
                      FROM jual_bekas 
                      WHERE bulan = '$bulan_filter'";
        
        // Tidak ada yang belum masuk kas
        $bekas_belum_kas_query = "SELECT 0 as total_bekas_belum_kas";
    }
} else {
    // Jika filter menggunakan tanggal
    $cek_status_kas = mysqli_query($conn, "SHOW COLUMNS FROM jual_bekas LIKE 'status_kas'");
    
    if (mysqli_num_rows($cek_status_kas) > 0) {
        // Jika kolom status_kas ada
        $bekas_query = "SELECT SUM(total_harga) as total_pendapatan_bekas
                      FROM jual_bekas
                      WHERE tanggal BETWEEN '$dari_tanggal' AND '$sampai_tanggal' AND status_kas = 1";
        
        // Query untuk penjualan barang bekas yang belum masuk kas
        $bekas_belum_kas_query = "SELECT SUM(total_harga) as total_bekas_belum_kas
                               FROM jual_bekas
                               WHERE tanggal BETWEEN '$dari_tanggal' AND '$sampai_tanggal' AND (status_kas = 0 OR status_kas IS NULL)";
    } else {
        // Jika kolom status_kas tidak ada, ambil semua
        $bekas_query = "SELECT SUM(total_harga) as total_pendapatan_bekas
                      FROM jual_bekas
                      WHERE tanggal BETWEEN '$dari_tanggal' AND '$sampai_tanggal'";
        
        // Tidak ada yang belum masuk kas
        $bekas_belum_kas_query = "SELECT 0 as total_bekas_belum_kas";
    }
}

$bekas_result = mysqli_query($conn, $bekas_query);

// Error handling for bekas_query
if ($bekas_result === false) {
    error_log("Bekas query failed: " . mysqli_error($conn) . " SQL: " . $bekas_query);
    $total_pendapatan_bekas = 0;
} else {
    $bekas_row = mysqli_fetch_assoc($bekas_result);
    $total_pendapatan_bekas = $bekas_row['total_pendapatan_bekas'] ?: 0;
}

$bekas_belum_kas_result = mysqli_query($conn, $bekas_belum_kas_query);

// Error handling for bekas_belum_kas_query
if ($bekas_belum_kas_result === false) {
    error_log("Bekas belum kas query failed: " . mysqli_error($conn) . " SQL: " . $bekas_belum_kas_query);
    $total_bekas_belum_kas = 0;
} else {
    $bekas_belum_kas_row = mysqli_fetch_assoc($bekas_belum_kas_result);
    $total_bekas_belum_kas = $bekas_belum_kas_row['total_bekas_belum_kas'] ?: 0;
}

// Total pendapatan barang bekas keseluruhan (yang sudah dan belum masuk kas)
$total_pendapatan_bekas_all = $total_pendapatan_bekas + $total_bekas_belum_kas;

// Update total pendapatan keseluruhan (produk + semua barang bekas)
$total_pendapatan_keseluruhan = $total_pendapatan + $total_pendapatan_bekas_all;

// Update total kas dengan pendapatan barang bekas yang sudah masuk kas
$total_kas += $total_pendapatan_bekas;

// Laba kotor hanya dari produk regular (tanpa barang bekas)
$total_keuntungan_all = $total_keuntungan;

// Buat query untuk mendapatkan data bulanan penjualan barang bekas yang sudah masuk kas
// Cek dulu apakah kolom status_kas ada di tabel jual_bekas
$cek_status_kas = mysqli_query($conn, "SHOW COLUMNS FROM jual_bekas LIKE 'status_kas'");

if (mysqli_num_rows($cek_status_kas) > 0) {
    // Jika kolom status_kas ada
    $monthly_bekas_kas_query = "SELECT 
      bulan, 
      SUM(CASE WHEN status_kas = 1 THEN total_harga ELSE 0 END) as total_bekas_masuk_kas,
      SUM(CASE WHEN status_kas = 0 OR status_kas IS NULL THEN total_harga ELSE 0 END) as total_bekas_belum_kas
      FROM jual_bekas 
      WHERE bulan = '$bulan_filter'
      GROUP BY bulan";
} else {
    // Jika kolom status_kas tidak ada, anggap semua sudah masuk kas
    $monthly_bekas_kas_query = "SELECT 
      bulan, 
      SUM(total_harga) as total_bekas_masuk_kas,0 as total_bekas_belum_kas
      FROM jual_bekas 
      WHERE bulan = '$bulan_filter'
      GROUP BY bulan";
}

$monthly_bekas_kas_result = mysqli_query($conn, $monthly_bekas_kas_query);

// Error handling for monthly_bekas_kas_query
if ($monthly_bekas_kas_result === false) {
    error_log("Monthly bekas kas query failed: " . mysqli_error($conn) . " SQL: " . $monthly_bekas_kas_query);
    $monthly_bekas_kas = [];
} else {
    $monthly_bekas_kas = [];
    while ($row = mysqli_fetch_assoc($monthly_bekas_kas_result)) {
        $monthly_bekas_kas[$row['bulan']] = [
            'masuk_kas' => (float)$row['total_bekas_masuk_kas'],
            'belum_kas' => (float)$row['total_bekas_belum_kas']
        ];
    }
}

// Mengambil total pengeluaran dari tabel pengeluaran dan hutang yang telah lunas

if (isset($_GET['bulan_filter']) && !empty($_GET['bulan_filter'])) {
  // Query untuk pengeluaran dari tabel pengeluaran
  $pengeluaran_query = "SELECT SUM(jumlah) as total_pengeluaran 
                      FROM pengeluaran 
                      WHERE bulan = '$bulan_filter'
                      AND kategori != 'Kasbon Manajer'";

  // Query untuk hutang produk yang lunas pada bulan tersebut 
  $hutang_lunas_query = "SELECT SUM(pc.jumlah_bayar) as total_hutang_lunas
                       FROM piutang_cair pc
                       WHERE DATE_FORMAT(pc.tanggal_bayar, '%Y-%m') = '$bulan_filter'
                       AND pc.transaksi_id = '-1'";
} else {
  // Jika filter menggunakan tanggal
  $pengeluaran_query = "SELECT SUM(jumlah) as total_pengeluaran 
                      FROM pengeluaran 
                      WHERE tanggal BETWEEN '$dari_tanggal' AND '$sampai_tanggal'
                      AND kategori != 'Kasbon Manajer'";

  // Query untuk hutang produk yang lunas pada rentang tanggal tersebut
  $hutang_lunas_query = "SELECT SUM(pc.jumlah_bayar) as total_hutang_lunas
                       FROM piutang_cair pc
                       WHERE pc.tanggal_bayar BETWEEN '$dari_tanggal' AND '$sampai_tanggal'
                       AND pc.transaksi_id = '-1'";
}
// Eksekusi query pengeluaran
$pengeluaran_result = mysqli_query($conn, $pengeluaran_query);

// Error handling for pengeluaran_query
if ($pengeluaran_result === false) {
    error_log("Pengeluaran query failed: " . mysqli_error($conn) . " SQL: " . $pengeluaran_query);
    $total_pengeluaran_reguler = 0;
} else {
    $pengeluaran_row = mysqli_fetch_assoc($pengeluaran_result);
    $total_pengeluaran_reguler = $pengeluaran_row['total_pengeluaran'] ?: 0;
}

// Adjust the query for pengeluaran details based on filter type
if (isset($bulan_filter)) {
  $pengeluaran_detail_query = "SELECT id, tanggal, kategori, keterangan, jumlah, created_by 
                          FROM pengeluaran 
                          WHERE DATE_FORMAT(tanggal, '%Y-%m') = '$bulan_filter'
                          AND kategori != 'Kasbon Manajer'
                          ORDER BY tanggal ASC";
} else {
  $pengeluaran_detail_query = "SELECT id, tanggal, kategori, keterangan, jumlah, created_by 
                          FROM pengeluaran 
                          WHERE tanggal BETWEEN '$dari_tanggal' AND '$sampai_tanggal'
                          AND kategori != 'Kasbon Manajer'
                          ORDER BY tanggal ASC";
}

// Eksekusi query hutang lunas
$hutang_lunas_result = mysqli_query($conn, $hutang_lunas_query);

// Error handling for hutang_lunas_query
if ($hutang_lunas_result === false) {
    error_log("Hutang lunas query failed: " . mysqli_error($conn) . " SQL: " . $hutang_lunas_query);
    $total_hutang_lunas = 0;
} else {
    $hutang_lunas_row = mysqli_fetch_assoc($hutang_lunas_result);
    $total_hutang_lunas = $hutang_lunas_row['total_hutang_lunas'] ?: 0;
}

// Fetch product debt payments
if ($total_hutang_lunas > 0) {
  // Adjust the query for hutang lunas based on filter type
  if (isset($bulan_filter)) {
      $hutang_lunas_detail_query = "SELECT pc.id, pc.tanggal_bayar, pc.keterangan, pc.jumlah_bayar,
                                  COALESCE(m.nama, a.nama, k.nama, 'User') as nama_user
                                  FROM piutang_cair pc
                                  LEFT JOIN manajer m ON pc.created_by = m.id_manajer
                                  LEFT JOIN admin a ON pc.created_by = a.id_admin
                                  LEFT JOIN karyawan k ON pc.created_by = k.id_karyawan
                                  WHERE DATE_FORMAT(pc.tanggal_bayar, '%Y-%m') = '$bulan_filter'
                                  AND pc.transaksi_id = '-1'
                                  ORDER BY pc.tanggal_bayar ASC";
  } else {
      $hutang_lunas_detail_query = "SELECT pc.id, pc.tanggal_bayar, pc.keterangan, pc.jumlah_bayar,
                                  COALESCE(m.nama, a.nama, k.nama, 'User') as nama_user
                                  FROM piutang_cair pc
                                  LEFT JOIN manajer m ON pc.created_by = m.id_manajer
                                  LEFT JOIN admin a ON pc.created_by = a.id_admin
                                  LEFT JOIN karyawan k ON pc.created_by = k.id_karyawan
                                  WHERE pc.tanggal_bayar BETWEEN '$dari_tanggal' AND '$sampai_tanggal'
                                  AND pc.transaksi_id = '-1'
                                  ORDER BY pc.tanggal_bayar ASC";
  }
  $hutang_lunas_detail_result = mysqli_query($conn, $hutang_lunas_detail_query);
  // ...
}
// Gabungkan kedua nilai untuk mendapatkan total pengeluaran keseluruhan
$total_pengeluaran = $total_pengeluaran_reguler + $total_hutang_lunas;

// PERBAIKAN: Hitung laba bersih dengan menambahkan pendapatan barang bekas secara langsung
// Laba bersih = laba kotor + pendapatan barang bekas - total pengeluaran
$laba_bersih_baru = ($total_keuntungan + $total_pendapatan_bekas_all) - $total_biaya_operasional;

// Query untuk mendapatkan total kasbon manajer
if (isset($_GET['bulan_filter']) && !empty($_GET['bulan_filter'])) {
  $kasbon_manajer_query = "SELECT SUM(jumlah) as total_kasbon_manajer 
                         FROM kasbon_manajer 
                         WHERE bulan = '$bulan_filter'";
} else {
  $kasbon_manajer_query = "SELECT SUM(jumlah) as total_kasbon_manajer 
                         FROM kasbon_manajer 
                         WHERE tanggal BETWEEN '$dari_tanggal' AND '$sampai_tanggal'";
}
$kasbon_manajer_result = mysqli_query($conn, $kasbon_manajer_query);

// Error handling untuk kasbon_manajer_query
if ($kasbon_manajer_result === false) {
  error_log("Kasbon manajer query failed: " . mysqli_error($conn) . " SQL: " . $kasbon_manajer_query);
  $total_kasbon_manajer = 0;
} else {
  $kasbon_manajer_row = mysqli_fetch_assoc($kasbon_manajer_result);
  $total_kasbon_manajer = $kasbon_manajer_row['total_kasbon_manajer'] ?: 0;
}

// Hitung bagian manajer (50% dari laba bersih)
$bagian_manajer = $laba_bersih_baru * 0.5;

// Hitung sisa bagian manajer setelah dikurangi kasbon
$sisa_bagian_manajer = $bagian_manajer - $total_kasbon_manajer;

// Tentukan apakah manajer memiliki hutang
$manajer_memiliki_hutang = $sisa_bagian_manajer < 0;

// Query untuk data grafik pendapatan dan keuntungan per hari
$chart_query = "SELECT 
    DATE(t.tanggal) as tanggal,
    SUM(td.subtotal) AS pendapatan,
    SUM(CASE 
         WHEN td.produk_id = 0 OR td.produk_id IS NULL THEN td.subtotal
         ELSE td.subtotal - (td.jumlah * IFNULL(p.harga_beli, 0))
        END) AS keuntungan
    FROM transaksi t
    JOIN transaksi_detail td ON t.id = td.transaksi_id
    LEFT JOIN produk p ON td.produk_id = p.id
    WHERE DATE(t.tanggal) BETWEEN '$dari_tanggal' AND '$sampai_tanggal'
    " . (!empty($kasir) ? " AND t.kasir = '$kasir' " : "") . "
    GROUP BY DATE(t.tanggal)
    ORDER BY DATE(t.tanggal) ASC";

    $chart_result = mysqli_query($conn, $chart_query);

    // Error handling for chart_query
    if ($chart_result === false) {
        error_log("Chart query failed: " . mysqli_error($conn) . " SQL: " . $chart_query);
        $chart_dates = [];
        $chart_income = [];
        $chart_profit = [];
    } else {
        $chart_dates = [];
        $chart_income = [];
        $chart_profit = [];
    
        while ($row = mysqli_fetch_assoc($chart_result)) {
            $chart_dates[] = date('d M', strtotime($row['tanggal']));
            $chart_income[] = (float)$row['pendapatan'];
            $chart_profit[] = (float)$row['keuntungan'];
        }
    }
    
    $chart_dates_json = json_encode($chart_dates);
    $chart_income_json = json_encode($chart_income);
    $chart_profit_json = json_encode($chart_profit);
    
    // Query untuk data bulanan hanya bulan ini
    $monthly_data_query = "SELECT 
        DATE_FORMAT(t.tanggal, '%Y-%m') as bulan,
        SUM(td.subtotal) AS pendapatan,
        SUM(CASE 
             WHEN td.produk_id = 0 OR td.produk_id IS NULL THEN td.subtotal
             ELSE td.subtotal - (td.jumlah * IFNULL(p.harga_beli, 0))
            END) AS laba_kotor
        FROM transaksi t
        JOIN transaksi_detail td ON t.id = td.transaksi_id
        LEFT JOIN produk p ON td.produk_id = p.id
        WHERE DATE_FORMAT(t.tanggal, '%Y-%m') = '$bulan_filter'
        " . (!empty($kasir) ? " AND t.kasir = '$kasir' " : "") . "
        GROUP BY DATE_FORMAT(t.tanggal, '%Y-%m')";
    
    $monthly_data_result = mysqli_query($conn, $monthly_data_query);
    
    // Error handling for monthly_data_query
    if ($monthly_data_result === false) {
        error_log("Monthly data query failed: " . mysqli_error($conn) . " SQL: " . $monthly_data_query);
        $monthly_data = [];
    } else {
        $monthly_data = [];
        while ($row = mysqli_fetch_assoc($monthly_data_result)) {
            $monthly_data[$row['bulan']] = [
                'pendapatan' => (float)$row['pendapatan'],
                'laba_kotor' => (float)$row['laba_kotor']
            ];
        }
    }
    
    // Mengambil data pendapatan dari barang bekas bulanan hanya bulan ini
    $monthly_bekas_query = "SELECT 
        bulan, 
        SUM(total_harga) as total_pendapatan_bekas 
        FROM jual_bekas 
        WHERE bulan = '$bulan_filter'
        GROUP BY bulan";
    
    $monthly_bekas_result = mysqli_query($conn, $monthly_bekas_query);
    
    // Error handling for monthly_bekas_query
    if ($monthly_bekas_result === false) {
        error_log("Monthly bekas query failed: " . mysqli_error($conn) . " SQL: " . $monthly_bekas_query);
        $monthly_bekas = [];
    } else {
        $monthly_bekas = [];
        while ($row = mysqli_fetch_assoc($monthly_bekas_result)) {
            $monthly_bekas[$row['bulan']] = (float)$row['total_pendapatan_bekas'];
        }
    }
    
    // Adjust the query for barang bekas details based on filter type
    if (isset($bulan_filter)) {
      $bekas_detail_query = "SELECT id, tanggal, jenis, keterangan, total_harga, 
                        " . (mysqli_num_rows($cek_status_kas) > 0 ? "(CASE WHEN status_kas = 1 THEN 'Ya' ELSE 'Belum' END) as status_kas_text," : "'Ya' as status_kas_text,") . "
                        created_by 
                        FROM jual_bekas 
                        WHERE DATE_FORMAT(tanggal, '%Y-%m') = '$bulan_filter'
                        ORDER BY tanggal ASC";
    } else {
      $bekas_detail_query = "SELECT id, tanggal, jenis, keterangan, total_harga, 
                        " . (mysqli_num_rows($cek_status_kas) > 0 ? "(CASE WHEN status_kas = 1 THEN 'Ya' ELSE 'Belum' END) as status_kas_text," : "'Ya' as status_kas_text,") . "
                        created_by 
                        FROM jual_bekas 
                        WHERE tanggal BETWEEN '$dari_tanggal' AND '$sampai_tanggal'
                        ORDER BY tanggal ASC";
    }
    
    // Query pengeluaran bulanan hanya untuk bulan ini
    $monthly_pengeluaran_query = "SELECT 
        t.bulan, 
        SUM(t.jumlah) as total_pengeluaran 
        FROM (
            -- Regular expenses from pengeluaran table, excluding Kasbon Manajer
            SELECT 
                bulan as bulan, 
                jumlah
            FROM pengeluaran 
            WHERE bulan = '$bulan_filter'
            AND kategori != 'Kasbon Manajer'
            
            UNION ALL
            
            -- Paid debts from piutang_cair table
            SELECT 
                DATE_FORMAT(tanggal_bayar, '%Y-%m') as bulan,
                jumlah_bayar
            FROM piutang_cair
            WHERE DATE_FORMAT(tanggal_bayar, '%Y-%m') = '$bulan_filter'
            AND transaksi_id = '-1'
        ) t
        GROUP BY t.bulan";
    
    $monthly_pengeluaran_result = mysqli_query($conn, $monthly_pengeluaran_query);
    
    // Error handling for monthly_pengeluaran_query
    if ($monthly_pengeluaran_result === false) {
        error_log("Monthly pengeluaran query failed: " . mysqli_error($conn) . " SQL: " . $monthly_pengeluaran_query);
        $monthly_pengeluaran = [];
    } else {
        $monthly_pengeluaran = [];
        while ($row = mysqli_fetch_assoc($monthly_pengeluaran_result)) {
            $monthly_pengeluaran[$row['bulan']] = (float)$row['total_pengeluaran'];
        }
    }
    
    // Update all_months to include only current month data sources
    $all_months = array_unique(array_merge(
        array_keys($monthly_data), 
        array_keys($monthly_pengeluaran), 
        array_keys($monthly_bekas),
        array_keys($monthly_kas),
        array_keys($monthly_bekas_kas),
        [$bulan_filter] // Ensure current month is included
    ));
    
    // Create data arrays for charts with consistent structure
    $laba_bersih_months = [];
    $pendapatan_data = [];
    $laba_kotor_data = [];
    $pengeluaran_data = [];
    $laba_bersih_data = [];
    $kas_masuk_data = []; // Reset this array
    $hutang_data = []; // Reset this array
    
    // Process data for each month - only current month
    foreach ($all_months as $month) {
      // Format month for display
      $laba_bersih_months[] = date('M Y', strtotime($month . '-01'));
      
      // Get values for each metric, defaulting to 0 if not available
      $pendapatan = isset($monthly_data[$month]) ? $monthly_data[$month]['pendapatan'] : 0;
      $laba_kotor = isset($monthly_data[$month]) ? $monthly_data[$month]['laba_kotor'] : 0;
      
      // Pastikan kita mengambil SEMUA pendapatan dari barang bekas, termasuk yang belum masuk kas
      $pendapatan_bekas_masuk_kas = isset($monthly_bekas_kas[$month]) ? $monthly_bekas_kas[$month]['masuk_kas'] : 0;
      $pendapatan_bekas_belum_kas = isset($monthly_bekas_kas[$month]) ? $monthly_bekas_kas[$month]['belum_kas'] : 0;
      $pendapatan_bekas_all = $pendapatan_bekas_masuk_kas + $pendapatan_bekas_belum_kas;
      
      // Use total_pengeluaran instead of estimating operational expenses
      $pengeluaran = isset($monthly_pengeluaran[$month]) ? $monthly_pengeluaran[$month] : 0;
      
      // We'll still need to calculate operational expenses for the laba bersih calculation
      $persentase_operasional = ($total_pengeluaran > 0) ? $total_biaya_operasional / $total_pengeluaran : 0;
      $biaya_operasional_estimasi = $pengeluaran * $persentase_operasional;
      
      // Get kas and hutang data directly
      $kas_bulan = isset($monthly_kas[$month]) ? $monthly_kas[$month]['kas_masuk'] : 0;
      $kas_bulan_updated = $kas_bulan + $pendapatan_bekas_masuk_kas;
      $hutang_bulan = isset($monthly_kas[$month]) ? $monthly_kas[$month]['total_hutang'] : 0;
      
      // Calculate total income - menggunakan semua pendapatan termasuk barang bekas
      $pendapatan_total = $pendapatan + $pendapatan_bekas_all;
      
      // Menghitung laba bersih dengan rumus yang sama dengan card laba bersih
      // Laba bersih = (laba kotor + pendapatan barang bekas) - biaya operasional
      $laba_bersih_monthly = ($laba_kotor + $pendapatan_bekas_all) - $biaya_operasional_estimasi;
      
      // Store all values in arrays for chart display
      $pendapatan_data[] = $pendapatan_total;
      $laba_kotor_data[] = $laba_kotor;
      $pengeluaran_data[] = $pengeluaran; // Changed from biaya_operasional_estimasi to pengeluaran
      $laba_bersih_data[] = $laba_bersih_monthly;
      $kas_masuk_data[] = $kas_bulan_updated;
      $hutang_data[] = $hutang_bulan;
    }
    
    // Convert arrays to JSON for JavaScript
    $laba_bersih_months_json = json_encode($laba_bersih_months);
    $pendapatan_data_json = json_encode($pendapatan_data);
    $laba_kotor_data_json = json_encode($laba_kotor_data);
    $pengeluaran_data_json = json_encode($pengeluaran_data);
    $laba_bersih_data_json = json_encode($laba_bersih_data);
    $kas_masuk_data_json = json_encode($kas_masuk_data);
    $hutang_data_json = json_encode($hutang_data);
    
    // Calculate available cash
    $kas_tersedia = $total_kas - $total_pengeluaran;
    
    // Determine if the available cash is positive or negative for styling
    $kas_tersedia_class = ($kas_tersedia >= 0) ? 'success' : 'danger';
    
    // Query untuk data pie chart - persentase kategori produk
    $kategori_query = "SELECT 
                      COALESCE(k.nama_kategori, 'Servis') as nama_kategori,
                      SUM(td.subtotal) AS total_penjualan
                      FROM transaksi_detail td
                      LEFT JOIN produk p ON td.produk_id = p.id
                      LEFT JOIN kategori k ON p.kategori_id = k.id
                      JOIN transaksi t ON td.transaksi_id = t.id
                      $where_clause
                      GROUP BY COALESCE(k.nama_kategori, 'Servis')";
    $kategori_result = mysqli_query($conn, $kategori_query);
    
    // Error handling for kategori_query
    if ($kategori_result === false) {
        error_log("Kategori query failed: " . mysqli_error($conn) . " SQL: " . $kategori_query);
        $kategori_data = [];
    } else {
        $kategori_data = [];
        while ($row = mysqli_fetch_assoc($kategori_result)) {
            $kategori_data[] = $row;
        }
    }
    $kategori_data_json = json_encode($kategori_data);
    
    // Query untuk data produk terlaris
    $produk_query = "SELECT 
                    COALESCE(p.nama, td.nama_produk_manual, 'Servis') AS nama_produk,
                    SUM(td.jumlah) AS jumlah_terjual,
                    SUM(td.subtotal) AS total_penjualan
                    FROM transaksi_detail td
                    LEFT JOIN produk p ON td.produk_id = p.id
                    JOIN transaksi t ON td.transaksi_id = t.id
                    $where_clause
                    GROUP BY COALESCE(p.id, td.nama_produk_manual, -1), COALESCE(p.nama, td.nama_produk_manual, 'Servis')
                    ORDER BY jumlah_terjual DESC
                    LIMIT 5";
    $produk_result = mysqli_query($conn, $produk_query);
    
    // Error handling for produk_query
    if ($produk_result === false) {
        error_log("Produk query failed: " . mysqli_error($conn) . " SQL: " . $produk_query);
        $produk_data = [];
    } else {
        $produk_data = [];
        while ($row = mysqli_fetch_assoc($produk_result)) {
            $produk_data[] = $row;
        }
    }
    $produk_data_json = json_encode($produk_data);
    
    // Query untuk mengecek ada tidaknya transaksi pada bulan tersebut
    $cek_transaksi_query = "SELECT COUNT(*) as total_transaksi 
                            FROM transaksi 
                            WHERE DATE_FORMAT(tanggal, '%Y-%m') = '$bulan_filter' " . 
                            (!empty($kasir) ? " AND kasir = '$kasir'" : "");
    
    $cek_result = mysqli_query($conn, $cek_transaksi_query);
    
    // Error handling for cek_transaksi_query
    if ($cek_result === false) {
        error_log("Cek transaksi query failed: " . mysqli_error($conn) . " SQL: " . $cek_transaksi_query);
        $ada_transaksi = false;
    } else {
        $row = mysqli_fetch_assoc($cek_result);
        $ada_transaksi = $row['total_transaksi'] > 0;
    }
    ?>
    
    <!DOCTYPE html>
    <html lang="id">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Laporan Penjualan - BMS Bengkel</title>
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
      <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
      <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        }
        
        body {
          font-family: 'Poppins', sans-serif;
          background-color: var(--light-gray);
          margin: 0;
          padding: 0;
          color: var(--text-dark);
        }
    
        .content {
          margin-left: 280px;
          transition: margin-left 0.3s ease;
          min-height: 100vh;
        }
    
        /* Navbar Styling */
        .navbar {
          background: linear-gradient(135deg, var(--primary-purple), var(--accent-purple));
          box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
          border: none;
          padding: 0.8rem 1.5rem;
          margin-bottom: 20px;
        }
    
        .navbar-brand {
          font-weight: 700;
          font-size: 1.5rem;
          color: var(--white);
          letter-spacing: 0.5px;
        }
    
        /* Style for active nav tabs */
        .nav-tabs .nav-link {
          color: var(--text-dark);
          font-weight: 500;
          border-radius: 8px 8px 0 0;
          padding: 10px 15px;
          transition: all 0.3s ease;
        }
    
        .nav-tabs .nav-link.active {
          color: var(--white);
          background: linear-gradient(135deg, var(--primary-purple), var(--secondary-purple));
          border-color: transparent;
          font-weight: 600;
        }
    
        .nav-tabs .nav-link:hover:not(.active) {
          background-color: var(--light-purple);
          border-color: transparent;
        }
    
        /* Page header section */
        .page-header {
          background: linear-gradient(135deg, var(--primary-purple), var(--secondary-purple));
          border-radius: 15px;
          padding: 30px;
          color: var(--white);
          margin-bottom: 25px;
          box-shadow: 0 6px 18px rgba(126, 87, 194, 0.15);
        }
        
        .page-header h1 {
          font-weight: 700;
          margin-bottom: 0.5rem;
        }
        
        /* Data card */
        .data-card, .filter-card, .table-card, .chart-container {
          border: none;
          border-radius: 15px;
          overflow: hidden;
          box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
          background-color: var(--white);
          padding: 1.5rem;
          margin-bottom: 1.5rem;
          transition: all 0.3s ease;
        }
        
        .data-card:hover, .chart-container:hover {
          transform: translateY(-5px);
          box-shadow: 0 12px 20px rgba(0, 0, 0, 0.12);
        }
        
        /* Card header with actions */
        .card-header-actions {
          display: flex;
          justify-content: space-between;
          align-items: center;
          padding-bottom: 1rem;
          margin-bottom: 1.5rem;
          border-bottom: 2px solid var(--light-purple);
        }
        
        .card-title, .chart-title, .filter-title {
          font-size: 1.25rem;
          font-weight: 600;
          color: var(--primary-purple);
          margin-bottom: 0;
        }
        
        /* Button styling */
        .btn-primary {
          background: linear-gradient(135deg, var(--primary-purple), var(--secondary-purple));
          border: none;
          border-radius: 8px;
          padding: 0.75rem 1.5rem;
          font-weight: 600;
          transition: all 0.3s ease;
          box-shadow: 0 4px 10px rgba(126, 87, 194, 0.2);
        }
        
        .btn-primary:hover {
          background: linear-gradient(135deg, var(--accent-purple), var(--primary-purple));
          transform: translateY(-2px);
          box-shadow: 0 6px 15px rgba(126, 87, 194, 0.3);
        }
        
        .btn-outline-light, .btn-outline-primary, .btn-outline-secondary {
          border-radius: 8px;
          font-weight: 500;
          transition: all 0.3s ease;
        }
        
        .btn-danger {
          background: linear-gradient(135deg, #F44336, #D32F2F);
          border: none;
          border-radius: 8px;
          padding: 0.75rem 1.5rem;
          font-weight: 600;
          transition: all 0.3s ease;
          box-shadow: 0 4px 10px rgba(244, 67, 54, 0.2);
        }
        
        .btn-danger:hover {
          background: linear-gradient(135deg, #D32F2F, #B71C1C);
          transform: translateY(-2px);
          box-shadow: 0 6px 15px rgba(244, 67, 54, 0.3);
        }
    
        /* Active state styling for all button types */
    .btn-primary,
    .btn.active,
    .btn-outline-primary.active,
    .btn-outline-secondary.active {
      color: var(--white) !important;
      font-weight: 600;
    }
    
    /* Custom class to ensure white text on active buttons */
    .btn-white-text.active,
    .btn-outline-primary.active,
    .btn-outline-secondary.active {
      color: var(--white) !important;
    }
    
        
        /* Form styling */
        .form-control, .form-select {
          border-radius: 8px;
          border: 1px solid #d1e3f0;
          padding: 0.75rem 1rem;
          transition: all 0.3s;
          background-color: var(--white);
        }
    
        .form-control:focus, .form-select:focus {
          border-color: var(--primary-purple);
          box-shadow: 0 0 0 0.25rem rgba(126, 87, 194, 0.25);
        }
        
        /* Summary cards */
        .summary-card {
          border-radius: 15px;
          color: var(--white);
          padding: 1.5rem;
          margin-bottom: 1.5rem;
          box-shadow: 0 6px 18px rgba(0, 0, 0, 0.15);
          height: 100%;
          transition: all 0.3s ease;
        }
        
        .summary-card:hover {
          transform: translateY(-5px);
          box-shadow: 0 12px 20px rgba(0, 0, 0, 0.18);
        }
        
        .summary-card {
          background: linear-gradient(135deg, #9575CD, #673AB7);
        }
        
        .summary-card.profit {
          background: linear-gradient(135deg, #66BB6A, #43A047);
        }
        
        .summary-card.transactions {
          background: linear-gradient(135deg, #FF7043, #E64A19);
        }
        
        .summary-card.expenses {
          background: linear-gradient(135deg, #F44336, #D32F2F);
        }
        
        .summary-card.net-profit {
          background: linear-gradient(135deg, #29B6F6, #0288D1);
        }
        
        .summary-card.bekas {
          background: linear-gradient(135deg, #FFA726, #FB8C00);
        }
        
        .summary-title {
          font-size: 1.1rem;
          font-weight: 600;
          margin-bottom: 0.5rem;
        }
        
        .summary-value {
          font-size: 1.8rem;
          font-weight: 700;
        }
        
        /* Table styling */
        .table {
          margin-bottom: 0;
        }
        
        .table thead {
          background-color: var(--light-purple);
        }
        
        .table thead th {
          color: var(--primary-purple);
          font-weight: 600;
          border-bottom: 2px solid var(--secondary-purple);
          padding: 1rem;
          vertical-align: middle;
        }
        
        .table tbody td {
          padding: 1rem;
          vertical-align: middle;
          border-color: var(--light-purple);
        }
        
        .table-striped tbody tr:nth-of-type(odd) {
          background-color: rgba(237, 231, 246, 0.3);
        }
        
        /* Produk terlaris chart */
        .product-item {
          padding: 1rem;
          margin-bottom: 1rem;
          border-radius: 10px;
          background-color: var(--light-purple);
          position: relative;
        }
        
        .product-name {
          font-weight: 600;
          margin-bottom: 5px;
          color: var(--accent-purple);
        }
        
        .product-progress {
          height: 8px;
          border-radius: 4px;
          margin-top: 5px;
          background-color: #e9ecef;
        }
        
        .product-progress .progress-bar {
          background: linear-gradient(135deg, var(--secondary-purple), var(--primary-purple));
          border-radius: 4px;
        }
        
        /* Alerts */
        .alert {
          border-radius: 10px;
          border: none;
          padding: 1rem 1.5rem;
          margin-bottom: 2rem;
          box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        
        .alert-success {
          background-color: #E8F5E9;
          color: #1B5E20;
        }
        
        .alert-danger {
          background-color: #FFEBEE;
          color: #B71C1C;
        }
        
        /* Tambahkan margin yang lebih besar pada summary cards row */
        .row.g-4.mb-4 {
          margin-bottom: 3rem !important; /* Menambah jarak di bawah row card summary */
        }
    
        /* Tambahkan sedikit margin di atas section chart */
        .chart-container {
          margin-top: 2rem;
        }
    
        /* Month badge styling */
      .month-badge {
        background: var(--light-purple);
        color: var(--primary-purple);
        font-weight: 600;
        padding: 0.75rem 1.25rem;
        border-radius: 10px;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 2px 6px rgba(126, 87, 194, 0.1);
      }
      
      /* Filter card styling */
      .filter-card {
        border: none;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
        background-color: var(--white);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        transition: all 0.3s ease;
      }
      
      /* Button group for month navigation */
      .month-nav-buttons .btn {
        border-radius: 8px;
        padding: 0.75rem 1rem;
        font-weight: 500;
        transition: all 0.2s ease;
      }
      
      .month-nav-buttons .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
      }
        /* Responsive media queries */
        @media (max-width: 992px) {
          .content {
            margin-left: 0;
          }
        }
        
        @media (max-width: 768px) {
          .chart-container, .table-card, .filter-card, .data-card {
            padding: 1rem;
          }
          
          .page-header {
            padding: 1.5rem;
          }
          
          .table thead th, .table tbody td {
            padding: 0.75rem;
          }
          
          .summary-value {
            font-size: 1.5rem;
          }
        }
      </style>
    </head>
    <body>
    
      <!-- Sidebar -->
      <?php include 'sidebar.php'; ?>
    
      <!-- Content -->
      <div class="content">
        <!-- Navbar -->
        <nav class="navbar navbar-expand-lg navbar-dark">
          <div class="container-fluid">
            <span class="navbar-brand">
              <i class="fas fa-chart-line me-2"></i>
              Laporan Penjualan
            </span>
            <div class="d-flex align-items-center">
              <span class="text-white me-3">
                <i class="fas fa-user-circle me-1"></i>
                <?= htmlspecialchars($_SESSION['admin']['nama']) ?>
              </span>
              <a href="profile.php" class="btn btn-outline-light btn-sm me-2">
                <i class="fas fa-user-edit me-1"></i> Profil
              </a>
              <a href="logout.php" class="btn btn-outline-light btn-sm">
                <i class="fas fa-sign-out-alt me-1"></i> Logout
              </a>
            </div>
          </div>
        </nav>
    
        <div class="container-fluid">
          <!-- Page Header with Dynamic Export Button -->
          <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h1><i class="fas fa-chart-line me-2"></i>Laporan Penjualan</h1>
                <p class="lead mb-0">Berikut adalah laporan penjualan yang tercatat di sistem kasir, termasuk penjualan barang bekas.</p>
          </div>
          <div class="d-flex gap-2">
  <a href="#" id="exportPdfBtn" class="btn btn-danger" target="_blank">
    <i class="fas fa-file-pdf me-2"></i> Export PDF
  </a>
  
  <a href="riwayat_transaksi.php?bulan=<?= $bulan_filter ?>" class="btn btn-primary">
    <i class="fas fa-history me-2"></i> Lihat Riwayat Transaksi
  </a>
</div>
        </div>
      </div>
      
      <!-- Alert Messages -->
      <?php if (isset($_SESSION['message'])): ?>
      <div class="alert alert-<?= $_SESSION['alert_type'] ?> alert-dismissible fade show" role="alert">
        <i class="fas fa-<?= $_SESSION['alert_type'] == 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
        <?= $_SESSION['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
      <?php
        unset($_SESSION['message']);
        unset($_SESSION['alert_type']);
      endif;
      ?>
      
<!-- Simplified Filter Controls (Only Month and Date Range filters) -->
<div class="filter-card mb-4">
  <div class="card-header-actions mb-3">
    <h5 class="filter-title">
      <i class="fas fa-filter me-2"></i> Filter Laporan
    </h5>
  </div>
  
  <!-- Filter tabs navigation -->
  <ul class="nav nav-tabs mb-3" id="filterTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="month-tab" data-bs-toggle="tab" data-bs-target="#month-filter" type="button" role="tab" aria-controls="month-filter" aria-selected="true">
        <i class="fas fa-calendar-alt me-1"></i> Filter Bulan
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="date-range-tab" data-bs-toggle="tab" data-bs-target="#date-range-filter" type="button" role="tab" aria-controls="date-range-filter" aria-selected="false">
        <i class="fas fa-calendar-week me-1"></i> Filter Rentang Tanggal
      </button>
    </li>
    <?php if (!empty($kasir)): ?>
    <li class="nav-item ms-auto">
      <div class="month-badge">
        <i class="fas fa-user"></i>
        <span>Kasir: <?= htmlspecialchars($kasir) ?></span>
        <a href="?bulan_filter=<?= $bulan_filter ?>" class="btn btn-sm btn-outline-secondary ms-2">
          <i class="fas fa-times"></i> Reset
        </a>
      </div>
    </li>
    <?php endif; ?>
  </ul>
  
  <!-- Filter tabs content -->
  <div class="tab-content" id="filterTabsContent">
    <!-- Month Filter -->
    <div class="tab-pane fade show active" id="month-filter" role="tabpanel" aria-labelledby="month-tab">
      <div class="d-flex gap-2 justify-content-between align-items-center">
        <div class="month-badge">
          <i class="fas fa-calendar-alt"></i>
          <span>Periode: <?= date('F Y', strtotime($bulan_filter . '-01')) ?></span>
        </div>
        
        <div class="d-flex gap-2">
          <div class="input-group">
            <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
            <input type="month" id="bulanTahunFilter" class="form-control" value="<?= $bulan_filter ?>" 
                  onchange="filterByMonth(this.value)">
            <button class="btn btn-outline-secondary" onclick="setCurrentMonth()">
              <i class="fas fa-calendar-day me-1"></i> Bulan Ini
            </button>
            <button class="btn btn-outline-secondary" onclick="setPrevMonth()">
              <i class="fas fa-chevron-left"></i>
            </button>
            <button class="btn btn-outline-secondary" onclick="setNextMonth()">
              <i class="fas fa-chevron-right"></i>
            </button>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Date Range Filter -->
    <div class="tab-pane fade" id="date-range-filter" role="tabpanel" aria-labelledby="date-range-tab">
      <form action="" method="GET" class="row g-3">
        <?php if (!empty($kasir)): ?>
        <input type="hidden" name="kasir" value="<?= htmlspecialchars($kasir) ?>">
        <?php endif; ?>
        
        <div class="col-md-4">
          <div class="input-group">
            <span class="input-group-text"><i class="fas fa-calendar-minus"></i></span>
            <input type="date" class="form-control" id="dari_tanggal" name="dari_tanggal" 
                  value="<?= $dari_tanggal ?>" placeholder="Dari Tanggal">
            <label for="dari_tanggal" class="input-group-text">Dari</label>
          </div>
        </div>
        
        <div class="col-md-4">
          <div class="input-group">
            <span class="input-group-text"><i class="fas fa-calendar-plus"></i></span>
            <input type="date" class="form-control" id="sampai_tanggal" name="sampai_tanggal" 
                  value="<?= $sampai_tanggal ?>" placeholder="Sampai Tanggal">
            <label for="sampai_tanggal" class="input-group-text">Sampai</label>
          </div>
        </div>
        
        <div class="col-md-4 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-grow-1">
            <i class="fas fa-filter me-1"></i> Terapkan Filter
          </button>
          <button type="button" class="btn btn-outline-secondary" onclick="setTodayDateRange()">
            <i class="fas fa-calendar-day me-1"></i> Hari Ini Saja
          </button>
        </div>
      </form>
      
      <?php if (isset($_GET['dari_tanggal']) && isset($_GET['sampai_tanggal'])): ?>
      <div class="mt-3">
        <div class="month-badge">
          <i class="fas fa-calendar-week"></i>
          <span>Filter aktif: <?= date('d F Y', strtotime($dari_tanggal)) ?> - <?= date('d F Y', strtotime($sampai_tanggal)) ?></span>
          <a href="<?= !empty($kasir) ? '?kasir=' . urlencode($kasir) : '?' ?>" class="btn btn-sm btn-outline-secondary ms-2">
            <i class="fas fa-times"></i> Reset
          </a>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Summary Cards Section -->
<div class="row g-4 mb-4">
      <div class="col-md-6 col-lg-3">
        <div class="summary-card">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h6 class="summary-title">Total Penjualan Produk</h6>
              <h2 class="summary-value">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></h2>
              <small class="text-white-50">Semua transaksi penjualan & servis</small><br>
              <small class="text-white-50">Termasuk data piutang</small>
            </div>
            <i class="fas fa-money-bill-wave fa-3x text-white-50"></i>
          </div>
        </div>
      </div>

      <div class="col-md-6 col-lg-3">
        <div class="summary-card bekas">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h6 class="summary-title">Total Penjualan Barang Bekas</h6>
              <h2 class="summary-value">Rp <?= number_format($total_pendapatan_bekas, 0, ',', '.') ?></h2>
              <small class="text-white-50">Penjualan semua barang bekas</small>
              <small class="text-white-50">Status: sudah masuk kas</small>
              <?php if($total_bekas_belum_kas > 0): ?>
              <br><small class="text-white-50">Belum masuk kas: Rp <?= number_format($total_bekas_belum_kas, 0, ',', '.') ?></small>
              <?php endif; ?>
            </div>
            <i class="fas fa-recycle fa-3x text-white-50"></i>
          </div>
        </div>
      </div>

        <!-- Total Pendapatan Keseluruhan Card -->
        <div class="col-md-6 col-lg-3">
          <div class="summary-card" style="background: linear-gradient(135deg, #9C27B0, #673AB7);">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h6 class="summary-title">Total Penjualan Keseluruhan</h6>
                <h2 class="summary-value">Rp <?= number_format($total_pendapatan + $total_pendapatan_bekas_all, 0, ',', '.') ?></h2>
                <small class="text-white-50">Produk: Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></small><br>
                <small class="text-white-50">Barang Bekas: Rp <?= number_format($total_pendapatan_bekas_all, 0, ',', '.') ?></small>
              </div>
              <i class="fas fa-hand-holding-usd fa-3x text-white-50"></i>
            </div>
          </div>
        </div>

        <div class="col-md-6 col-lg-3">
          <div class="summary-card" style="background: linear-gradient(135deg, #FF8A65, #FF5722);">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h6 class="summary-title">Total Piutang Pelanggan</h6>
                <h2 class="summary-value">Rp <?= number_format($total_hutang, 0, ',', '.') ?></h2>
                <small class="text-white-50">Dari transaksi kredit pelanggan</small><br>
                <small class="text-white-50">Belum dibayarkan ke bengkel</small>
              </div>
              <i class="fas fa-credit-card fa-3x text-white-50"></i>
            </div>
          </div>
        </div>
        
        <div class="col-md-6 col-lg-3">
          <div class="summary-card" style="background: linear-gradient(135deg, #5C6BC0, #3949AB);">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h6 class="summary-title">Uang Kas Masuk</h6>
                <h2 class="summary-value">Rp <?= number_format($total_kas, 0, ',', '.') ?></h2>
                <small class="text-white-50">Sudah dikurangi piutang</small><br>
                <small class="text-white-50">Termasuk penjualan barang bekas</small>
              </div>
              <i class="fas fa-cash-register fa-3x text-white-50"></i>
            </div>
          </div>
        </div>

        <!-- Biaya Operasional Card (Updated) -->
        <div class="col-md-6 col-lg-3">
          <div class="summary-card" style="background: linear-gradient(135deg, #8D6E63, #5D4037);">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h6 class="summary-title">Biaya Operasional</h6>
                <h2 class="summary-value">Rp <?= number_format($total_biaya_operasional, 0, ',', '.') ?></h2>
                <small class="text-white-50">Operasional: Rp <?= number_format($total_operasional, 0, ',', '.') ?></small><br>
                <small class="text-white-50">Karyawan: Rp <?= number_format($total_karyawan, 0, ',', '.') ?></small>
              </div>
              <i class="fas fa-tools fa-3x text-white-50"></i>
            </div>
          </div>
        </div>

<!-- NEW CARD: Pengeluaran Produk (Complete calculation including all product expenses) -->
<?php
if (isset($_GET['dari_tanggal']) && isset($_GET['sampai_tanggal'])) {
  // If using date range filter
  $hutang_produk_query = "SELECT SUM(jumlah_bayar) as total_bayar_hutang_produk 
                        FROM piutang_cair 
                        WHERE transaksi_id = '-1' 
                        AND keterangan LIKE 'Pembayaran hutang produk:%'
                        AND tanggal_bayar BETWEEN '$dari_tanggal' AND '$sampai_tanggal'";
} else {
  // If using month filter (default)
  $hutang_produk_query = "SELECT SUM(jumlah_bayar) as total_bayar_hutang_produk 
                        FROM piutang_cair 
                        WHERE transaksi_id = '-1' 
                        AND keterangan LIKE 'Pembayaran hutang produk:%'
                        AND DATE_FORMAT(tanggal_bayar, '%Y-%m') = '$bulan_filter'";
}
$hutang_produk_result = mysqli_query($conn, $hutang_produk_query);
$hutang_produk_data = mysqli_fetch_assoc($hutang_produk_result);
$total_bayar_hutang_produk = $hutang_produk_data['total_bayar_hutang_produk'] ?: 0;

// PERBAIKAN QUERY PEMBELIAN PRODUK
// Ganti query pembelian yang ada di file laporan.php (sekitar baris 732-740) dengan query berikut:

if (isset($_GET['dari_tanggal']) && isset($_GET['sampai_tanggal'])) {
  // If using date range filter
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
    
  FROM pengeluaran
  WHERE tanggal BETWEEN '$dari_tanggal' AND '$sampai_tanggal'";
} else {
  // If using month filter (default)
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
    
  FROM pengeluaran
  WHERE DATE_FORMAT(tanggal, '%Y-%m') = '$bulan_filter'";
}
$pembelian_result = mysqli_query($conn, $pembelian_query);
$pembelian_data = mysqli_fetch_assoc($pembelian_result);

// Calculate all product expense components
$total_pembelian_sparepart = $pembelian_data['total_pembelian_sparepart'] ?: 0;
$total_tambah_stok = $pembelian_data['total_tambah_stok'] ?: 0;

// Total pengeluaran produk (all components)
$total_pengeluaran_produk = $total_pembelian_sparepart + $total_tambah_stok + $total_bayar_hutang_produk;
?>
<div class="col-md-6 col-lg-3">
  <div class="summary-card" style="background: linear-gradient(135deg, #3F51B5, #303F9F);">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h6 class="summary-title">Pengeluaran Produk</h6>
        <h2 class="summary-value">Rp <?= number_format($total_pengeluaran_produk, 0, ',', '.') ?></h2>
        <small class="text-white-50">Pembelian Sparepart Baru: Rp <?= number_format($total_pembelian_sparepart, 0, ',', '.') ?></small><br>
        <small class="text-white-50">Tambah Stok Sparepart Lama: Rp <?= number_format($total_tambah_stok, 0, ',', '.') ?></small><br>
        <small class="text-white-50">Bayar Hutang: Rp <?= number_format($total_bayar_hutang_produk, 0, ',', '.') ?></small>
      </div>
      <i class="fas fa-shopping-cart fa-3x text-white-50"></i>
    </div>
  </div>
</div>

        <div class="col-md-6 col-lg-3">
          <div class="summary-card expenses">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h6 class="summary-title">Uang Kas Keluar</h6>
                <h2 class="summary-value">Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?></h2>
                <small class="text-white-50">Termasuk operasional & karyawan</small><br>
                <small class="text-white-50">Termasuk pembelian stok dan bayar hutang ke suplier</small>
              </div>
              <i class="fas fa-file-invoice-dollar fa-3x text-white-50"></i>
            </div>
          </div>
        </div>

        <!-- Add this card to your Summary Cards section -->
<div class="col-md-6 col-lg-3">
  <div class="summary-card" style="background: linear-gradient(135deg, #4CAF50, #2E7D32);">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h6 class="summary-title">Saldo Kas</h6>
        <h2 class="summary-value">Rp <?= number_format($kas_tersedia, 0, ',', '.') ?></h2>
        <small class="text-white-50">Kas Masuk: Rp <?= number_format($total_kas, 0, ',', '.') ?></small><br>
        <small class="text-white-50">Kas Keluar: Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?></small>
      </div>
      <i class="fas fa-balance-scale fa-3x text-white-50"></i>
    </div>
  </div>
</div>

<div class="col-md-6 col-lg-3">
          <div class="summary-card profit">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h6 class="summary-title">Laba Kotor</h6>
                <h2 class="summary-value">Rp <?= number_format($total_keuntungan_all, 0, ',', '.') ?></h2>
                <small class="text-white-50">Penjualan - Harga Pokok Produk</small><br>
                <small class="text-white-50">Belum dikurangi biaya operasional</small>
              </div>
              <i class="fas fa-chart-line fa-3x text-white-50"></i>
            </div>
          </div>
        </div>
        
      <!-- Laba Bersih Card (Updated) -->
      <div class="col-md-6 col-lg-3">
        <div class="summary-card net-profit">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h6 class="summary-title">Laba Bersih</h6>
              <h2 class="summary-value">Rp <?= number_format($laba_bersih_baru, 0, ',', '.') ?></h2>
              <small class="text-white-50">Laba Kotor: Rp <?= number_format($total_keuntungan, 0, ',', '.') ?></small><br>
              <small class="text-white-50">+ Penjualan Barang Bekas: Rp <?= number_format($total_pendapatan_bekas_all, 0, ',', '.') ?></small><br>
              <small class="text-white-50">- Biaya Operasional: Rp <?= number_format($total_biaya_operasional, 0, ',', '.') ?></small>
            </div>
            <i class="fas fa-wallet fa-3x text-white-50"></i>
          </div>
        </div>
      </div>
        
    <!-- Margin Laba Bersih Card (Updated) -->
    <div class="col-md-6 col-lg-3">
      <div class="summary-card" style="background: linear-gradient(135deg, #26A69A, #00897B);">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="summary-title">Margin Laba Bersih</h6>
            <h2 class="summary-value"><?= ($total_pendapatan_keseluruhan > 0) ? number_format(($laba_bersih_baru / $total_pendapatan_keseluruhan) * 100, 2) : '0' ?>%</h2>
            <small class="text-white-50">Laba bersih / total pendapatan keseluruhan</small>
          </div>
          <i class="fas fa-percentage fa-3x text-white-50"></i>
        </div>
      </div>
    </div>

<!-- Chart Section dengan tinggi yang distandarisasi -->
<div class="row">
  <!-- Chart Tren Pendapatan dan Laba Kotor -->
  <div class="col-lg-6 mb-4">
    <div class="chart-container">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="chart-title mb-0">
          <i class="fas fa-chart-bar me-2"></i>
          Tren Pendapatan dan Laba Kotor
        </h5>
      </div>
      <div class="chart-wrapper" style="height: 300px;">
        <?php if ((!empty($chart_dates) && count($chart_dates) > 0) && (!isset($_GET['bulan_filter']) || $ada_transaksi)) { ?>
          <canvas id="incomeChart"></canvas>
        <?php } else { ?>
          <div class="text-center py-4">
            <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
            <p>Tidak ada data transaksi pada periode yang dipilih.</p>
          </div>
        <?php } ?>
      </div>
    </div>
  </div>
        
  <!-- Chart Kategori Produk -->
  <div class="col-lg-6 mb-4">
    <div class="chart-container">
      <h5 class="chart-title">
        <i class="fas fa-chart-pie me-2"></i>
        Kategori Produk Terjual
      </h5>
      <div class="chart-wrapper" style="height: 300px;">
        <?php if ((!empty($kategori_data) && count($kategori_data) > 0) && (!isset($_GET['bulan_filter']) || $ada_transaksi)) { ?>
          <canvas id="categoryChart"></canvas>
        <?php } else { ?>
          <div class="text-center py-4">
            <i class="fas fa-chart-pie fa-3x text-muted mb-3"></i>
            <p>Tidak ada data kategori produk pada periode yang dipilih.</p>
          </div>
        <?php } ?>
      </div>
    </div>
  </div>
        
  <!-- Chart Tren Bulanan dengan tinggi yang distandarisasi -->
  <div class="col-lg-8 mb-4">
    <div class="chart-container">
      <h5 class="chart-title">
        <i class="fas fa-chart-bar me-2"></i>
        Tren Keuangan Bulanan
      </h5>
      <div class="chart-wrapper" style="height: 300px;">
        <?php if ((!empty($laba_bersih_months) && count($laba_bersih_months) > 0) && (!isset($_GET['bulan_filter']) || $ada_transaksi)) { ?>
          <canvas id="netProfitChart"></canvas>
        <?php } else { ?>
          <div class="text-center py-4">
            <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
            <p>Tidak ada data keuangan bulanan pada periode yang dipilih.</p>
          </div>
        <?php } ?>
      </div>
    </div>
  </div>
                
  <!-- Produk Terlaris -->
  <div class="col-lg-4 mb-4">
    <div class="chart-container">
      <h5 class="chart-title">
        <i class="fas fa-medal me-2"></i>
        5 Produk Terlaris
      </h5>
      <div id="topProducts" style="height: 300px; overflow-y: auto;">
        <?php
        $max_quantity = 0;
        foreach($produk_data as $product) {
          if($product['jumlah_terjual'] > $max_quantity) {
            $max_quantity = $product['jumlah_terjual'];
          }
        }
        
        foreach($produk_data as $product) {
          $percentage = ($max_quantity > 0) ? ($product['jumlah_terjual'] / $max_quantity) * 100 : 0;
        ?>
        <div class="product-item">
          <div class="product-name"><?= htmlspecialchars($product['nama_produk']) ?></div>
          <div class="d-flex justify-content-between">
            <small><?= number_format($product['jumlah_terjual']) ?> unit</small>
            <small>Rp <?= number_format($product['total_penjualan'], 0, ',', '.') ?></small>
          </div>
          <div class="product-progress">
            <div class="progress-bar" role="progressbar" style="width: <?= $percentage ?>%" 
                  aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100"></div>
          </div>
        </div>
        <?php } ?>
        
        <?php if (empty($produk_data)) { ?>
          <div class="text-center py-4">
            <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
            <p>Tidak ada data produk pada periode yang dipilih.</p>
          </div>
        <?php } ?>
      </div>
    </div>
  </div>
</div>
      


  </div> <!-- End of container-fluid -->
</div> <!-- End of content -->

  <!-- Scripts -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
  
  <script>
    // Synchronized Month-Year filter navigation functions
    function filterByMonth(value) {
      // Check if we have a kasir parameter to preserve
      const urlParams = new URLSearchParams(window.location.search);
      const kasir = urlParams.get('kasir');
      
      let url = '?bulan_filter=' + value;
      if (kasir) {
        url += '&kasir=' + kasir;
      }
      
      window.location.href = url;
    }

    function setCurrentMonth() {
      const now = new Date();
      const year = now.getFullYear();
      const month = (now.getMonth() + 1).toString().padStart(2, '0');
      const currentMonth = `${year}-${month}`;
      
      document.getElementById('bulanTahunFilter').value = currentMonth;
      filterByMonth(currentMonth);
    }

    function setPrevMonth() {
      const currentValue = document.getElementById('bulanTahunFilter').value;
      const [year, month] = currentValue.split('-').map(num => parseInt(num));
      
      let prevMonth = month - 1;
      let prevYear = year;
      
      if (prevMonth < 1) {
        prevMonth = 12;
        prevYear -= 1;
      }
      
      const formattedMonth = prevMonth.toString().padStart(2, '0');
      const newValue = `${prevYear}-${formattedMonth}`;
      
      document.getElementById('bulanTahunFilter').value = newValue;
      filterByMonth(newValue);
    }

    function setNextMonth() {
      const currentValue = document.getElementById('bulanTahunFilter').value;
      const [year, month] = currentValue.split('-').map(num => parseInt(num));
      
      let nextMonth = month + 1;
      let nextYear = year;
      
      if (nextMonth > 12) {
        nextMonth = 1;
        nextYear += 1;
      }
      
      const formattedMonth = nextMonth.toString().padStart(2, '0');
      const newValue = `${nextYear}-${formattedMonth}`;
      
      document.getElementById('bulanTahunFilter').value = newValue;
      filterByMonth(newValue);
    }

    // Chart Data
    const chartDates = <?= $chart_dates_json ?>;
    const chartIncome = <?= $chart_income_json ?>;
    const chartProfit = <?= $chart_profit_json ?>;
    
    // Laba Bersih Chart Data
    const labaBersihMonths = <?= $laba_bersih_months_json ?>;
    const labaKotorData = <?= $laba_kotor_data_json ?>;
    const pengeluaranData = <?= $pengeluaran_data_json ?>;
    const labaBersihData = <?= $laba_bersih_data_json ?>;
    const pendapatanData = <?= $pendapatan_data_json ?>;
    const kas_masuk_data = <?= $kas_masuk_data_json ?>;
    const hutang_data = <?= $hutang_data_json ?>;
    
    // Kategori Chart Data
    const kategoriData = <?= $kategori_data_json ?>;
    
    // Auto close alerts after 5 seconds
    setTimeout(function() {
      document.querySelectorAll(".alert").forEach(function(alert) {
        let closeButton = alert.querySelector(".btn-close");
        if (closeButton) {
          closeButton.click();
        }
      });
    }, 5000);
    
    // Initialize Income and Profit Chart
    const incomeChartCtx = document.getElementById('incomeChart');
    if (incomeChartCtx && chartDates && chartDates.length > 0) {  
      const incomeChart = new Chart(incomeChartCtx.getContext('2d'), {
        type: 'bar',
        data: {
          labels: chartDates,
          datasets: [
            {
              label: 'Pendapatan',
              data: chartIncome,
              backgroundColor: 'rgba(126, 87, 194, 0.7)', // Purple for pendapatan
              borderColor: '#7E57C2',
              borderWidth: 1
            },
            {
              label: 'Laba Kotor',
              data: chartProfit,
              backgroundColor: 'rgba(76, 175, 80, 0.7)', // Green for laba kotor
              borderColor: '#4CAF50',
              borderWidth: 1
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                callback: function(value) {
                  return 'Rp ' + value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                }
              }
            }
          },
          plugins: {
            legend: {
              position: 'top',
            },
            tooltip: {
              callbacks: {
                label: function(context) {
                  let label = context.dataset.label || '';
                  if (label) {
                    label += ': ';
                  }
                  label += 'Rp ' + context.parsed.y.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                  return label;
                }
              }
            }
          }
        }
      });
    }

// Function to update export button URL based on active filter tab
function updateExportButtonURL() {
  const exportButton = document.querySelector('.btn-danger');
  if (!exportButton) return;
  
  let exportUrl = 'export_detail_transaksi.php?';
  const urlParams = new URLSearchParams(window.location.search);
  const kasir = urlParams.get('kasir');
  
  // Check which tab is active
  const activeTab = document.querySelector('.nav-tabs .nav-link.active');
  
  if (activeTab.id === 'date-range-tab') {
    // Date range filter is active
    const dariTanggal = document.getElementById('dari_tanggal').value;
    const sampaiTanggal = document.getElementById('sampai_tanggal').value;
    
    if (dariTanggal && sampaiTanggal) {
      exportUrl += `dari_tanggal=${dariTanggal}&sampai_tanggal=${sampaiTanggal}`;
    }
  } else {
    // Month filter is active (default)
    const bulanFilter = document.getElementById('bulanTahunFilter').value;
    exportUrl += `bulan=${bulanFilter}`;
  }
  
  // Add kasir parameter if present
  if (kasir) {
    exportUrl += `&kasir=${kasir}`;
  }
  
  exportButton.href = exportUrl;
}

// Call the update function when the page loads
document.addEventListener('DOMContentLoaded', function() {
  // Initialize the export button URL
  updateExportButtonURL();
  
  // Update URL when tabs are clicked
  document.querySelectorAll('.nav-tabs .nav-link').forEach(tab => {
    tab.addEventListener('click', updateExportButtonURL);
  });
  
  // Update URL when filter values change
  document.getElementById('dari_tanggal')?.addEventListener('change', updateExportButtonURL);
  document.getElementById('sampai_tanggal')?.addEventListener('change', updateExportButtonURL);
  document.getElementById('bulanTahunFilter')?.addEventListener('change', updateExportButtonURL);
  
  // Also trigger update when the filter form is submitted
  document.querySelector('form')?.addEventListener('submit', function() {
    setTimeout(updateExportButtonURL, 100); // Small delay to ensure DOM is updated
  });
});
     
// Function to set date range to today only
function setTodayDateRange() {
  const today = new Date();
  const year = today.getFullYear();
  const month = (today.getMonth() + 1).toString().padStart(2, '0');
  const day = today.getDate().toString().padStart(2, '0');
  const formattedDate = `${year}-${month}-${day}`;
  
  // Set both from and to dates to today
  document.getElementById('dari_tanggal').value = formattedDate;
  document.getElementById('sampai_tanggal').value = formattedDate;
  
  // Submit the form immediately
  document.getElementById('dari_tanggal').closest('form').submit();
}
// Function to initialize date range filter
function initDateRangeFilter() {
  // Get URL parameters to determine which tab to activate
  const urlParams = new URLSearchParams(window.location.search);
  
  // If date range parameters are present, activate date range tab
  if (urlParams.has('dari_tanggal') && urlParams.has('sampai_tanggal')) {
    document.querySelector('#date-range-tab').click();
  }
  
  // Set default dates if none provided
  if (!document.getElementById('dari_tanggal').value) {
    const today = new Date();
    
    // Format date function
    const formatDate = (date) => {
      const year = date.getFullYear();
      const month = (date.getMonth() + 1).toString().padStart(2, '0');
      const day = date.getDate().toString().padStart(2, '0');
      return `${year}-${month}-${day}`;
    };
    
    // Default to today's date for both fields
    const formattedToday = formatDate(today);
    document.getElementById('dari_tanggal').value = formattedToday;
    document.getElementById('sampai_tanggal').value = formattedToday;
  }
}

// Initialize date range filter on page load
document.addEventListener('DOMContentLoaded', function() {
  initDateRangeFilter();
});

    // Initialize Category Chart
    const categoryChartCtx = document.getElementById('categoryChart');
    if (categoryChartCtx && kategoriData && kategoriData.length > 0) {
      // Prepare data for category chart
      const categoryLabels = kategoriData.map(item => item.nama_kategori);
      const categoryValues = kategoriData.map(item => item.total_penjualan);
      const categoryColors = [
        '#7E57C2', // Primary purple
        '#4CAF50', // Green 
        '#FF7043', // Orange
        '#29B6F6', // Blue
        '#EC407A', // Pink
        '#26A69A', // Teal
        '#5C6BC0', // Indigo
        '#26C6DA', // Cyan
        '#D4E157', // Lime
        '#FFD54F'  // Amber
      ];
      
      const categoryChart = new Chart(categoryChartCtx.getContext('2d'), {
        type: 'doughnut',
        data: {
          labels: categoryLabels,
          datasets: [{
            data: categoryValues,
            backgroundColor: categoryColors,
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'right',
            },
            tooltip: {
              callbacks: {
                label: function(context) {
                  const label = context.label || '';
                  const value = 'Rp ' + context.parsed.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                  return `${label}: ${value}`;
                }
              }
            }
          }
        }
      });
    }
      
    // Initialize Monthly Finance Chart
    const netProfitChartCtx = document.getElementById('netProfitChart');
    if (netProfitChartCtx && labaBersihMonths && labaBersihMonths.length > 0) {
      const netProfitChart = new Chart(netProfitChartCtx.getContext('2d'), {
        type: 'bar',
        data: {
          labels: labaBersihMonths,
          datasets: [
            {
              label: 'Total Pendapatan keseluruhan',
              data: pendapatanData,
              backgroundColor: 'rgba(126, 87, 194, 0.7)', // Purple for pendapatan
              borderColor: '#7E57C2',
              borderWidth: 1,
              order: 1
            },
            {
              label: 'Uang Kas Masuk',
              data: kas_masuk_data,
              backgroundColor: 'rgba(92, 107, 192, 0.7)', // Indigo for kas masuk
              borderColor: '#5C6BC0',
              borderWidth: 1,
              order: 2
            },
            {
              label: 'Total Hutang',
              data: hutang_data,
              backgroundColor: 'rgba(255, 138, 101, 0.7)', // Orange for hutang
              borderColor: '#FF8A65',
              borderWidth: 1,
              order: 3
            },
            {
              label: 'Laba Kotor',
              data: labaKotorData,
              backgroundColor: 'rgba(76, 175, 80, 0.7)', // Green for laba kotor
              borderColor: '#4CAF50',
              borderWidth: 1,
              order: 4
            },
            {
              // Changed from 'Biaya Operasional' to 'Total Pengeluaran'
              label: 'Total Pengeluaran',
              data: pengeluaranData,
              backgroundColor: 'rgba(244, 67, 54, 0.7)',
              borderColor: '#F44336',
              borderWidth: 1,
              order: 5
            },
            {
              label: 'Laba Bersih',
              data: labaBersihData,
              backgroundColor: 'rgba(41, 182, 246, 0.7)', // Blue for laba bersih
              borderColor: '#29B6F6',
              borderWidth: 1,
              order: 6
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            x: {
              stacked: false
            },
            y: {
              beginAtZero: true,
              ticks: {
                callback: function(value) {
                  return 'Rp ' + value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                }
              }
            }
          },
          interaction: {
            mode: 'index',
            intersect: false
          },
          plugins: {
            legend: {
              position: 'top',
              align: 'center',
              labels: {
                boxWidth: 12,
                padding: 10
              }
            },
            tooltip: {
              callbacks: {
                label: function(context) {
                  let label = context.dataset.label || '';
                  if (label) {
                    label += ': ';
                  }
                  label += 'Rp ' + context.parsed.y.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                  return label;
                }
              }
            }
          }
        }
      });
    }
  </script>
</body>
</html>