<?php
include '../config.php';
session_start();

if (!isset($_GET['id'])) {
    echo "ID Transaksi tidak ditemukan.";
    exit;
}

$id = intval($_GET['id']);

// Format ID transaksi dengan leading zero - format #0001
$formatted_id = sprintf("#%04d", $id);

// Ambil data transaksi utama
$transaksiQuery = $conn->query("SELECT * FROM transaksi WHERE id = $id");
if ($transaksiQuery->num_rows == 0) {
    echo "Transaksi tidak ditemukan.";
    exit;
}
$transaksi = $transaksiQuery->fetch_assoc();

// Ambil detail produk dalam transaksi
$detailQuery = $conn->query("
    SELECT td.*, p.nama AS nama_produk 
    FROM transaksi_detail td
    LEFT JOIN produk p ON td.produk_id = p.id
    WHERE td.transaksi_id = $id
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Struk - Bengkel BMS Cikunir</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js"></script>
    <style>
        @media print {
            body {
                width: 80mm;
                margin: 0;
                padding: 0;
            }
            .btn-print, .no-print {
                display: none !important;
            }
        }
        
        body {
            font-family: "Courier New", Courier, monospace;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .container {
            padding: 20px;
        }

        #struk {
            width: 80mm;
            margin: 0 auto;
            padding: 10px;
            border: 1px solid #ddd;
            font-size: 12px;
            text-align: center;
            background-color: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        #struk .header {
            border-bottom: 1px dashed #000;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }

        #struk .header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: bold;
        }

        #struk .header p {
            margin: 5px 0 0;
            font-size: 12px;
        }

        #struk .info {
            font-size: 12px;
            margin-bottom: 10px;
            text-align: left;
            border-bottom: 1px dashed #000;
            padding-bottom: 10px;
        }

        #struk .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }

        #struk .info-label {
            font-weight: bold;
            width: 40%;
        }

        #struk .info-value {
            width: 60%;
            text-align: left;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            table-layout: fixed;
        }

        th, td {
            padding: 4px 2px;
            text-align: left;
            font-size: 11px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        th {
            border-bottom: 1px solid #000;
        }
        
        .subtotal-row {
            border-top: 1px dashed #000;
            margin-top: 5px;
            padding-top: 5px;
        }

        .total {
            font-weight: bold;
            font-size: 14px;
            border-top: 1px dashed #000;
            margin-top: 10px;
            padding-top: 10px;
            text-align: right;
            padding-right: 5px;
        }

        .payment-details {
            font-size: 12px;
            text-align: right;
            padding-right: 5px;
            margin-top: 5px;
        }
        
        .kembalian-details {
            font-size: 12px;
            text-align: right;
            padding-right: 5px;
            margin-top: 5px;
            color: #4CAF50;
        }

        .debt-details {
            font-weight: bold;
            font-size: 14px;
            text-align: right;
            padding-right: 5px;
            margin-top: 5px;
            color: #F44336;
        }

        .payment-status {
            font-weight: bold;
            font-size: 12px;
            text-align: center;
            margin: 10px 0;
            padding: 5px;
            border: 1px dashed #000;
        }

        .lunas {
            color: #4CAF50;
        }

        .belum-lunas {
            color: #F44336;
        }

        .footer {
            margin-top: 20px;
            border-top: 1px dashed #000;
            padding-top: 10px;
            font-size: 11px;
            text-align: center;
        }

        .btn-print {
            display: block;
            margin: 20px auto;
            padding: 10px 15px;
            background-color: #26A69A;
            color: white;
            border: none;
            cursor: pointer;
            text-align: center;
            width: 80mm;
            border-radius: 5px;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        
        .btn-print:hover {
            background-color: #00897B;
        }
        
        .align-right {
            text-align: right;
            padding-right: 5px;
        }
        
        .align-center {
            text-align: center;
        }
        
        .thank-you {
            font-weight: bold;
            margin-top: 5px;
        }
        
        /* Adjusted column widths to prevent text from being cut off */
        .col-no {
            width: 5%;
        }
        
        .col-item {
            width: 50%;
        }
        
        .col-qty {
            width: 8%;
        }
        
        .col-price {
            width: 17%;
        }
        
        .col-total {
            width: 20%;
        }

        .manual-tag {
            font-style: italic;
            font-size: 9px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div id="struk">
            <div class="header">
                <h2>BENGKEL BMS CIKUNIR</h2>
                <p>Jalan Rambutan, Jatiasih</p>
                <p>Telp: 0815-8904-564</p>
            </div>

            <div class="info">
                <div class="info-row">
                    <div class="info-label">No. Struk</div>
                    <div class="info-value">: <?= $formatted_id ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Tanggal</div>
                    <div class="info-value">: <?= date('d/m/Y', strtotime($transaksi['tanggal'])) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Customer</div>
                    <div class="info-value">: <?= htmlspecialchars($transaksi['nama_customer']) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">WhatsApp</div>
                    <div class="info-value">: <?= $transaksi['no_whatsapp'] ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Plat Nomor</div>
                    <div class="info-value">: <?= strtoupper($transaksi['plat_nomor_motor']) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Kasir</div>
                    <div class="info-value">: <?= $transaksi['kasir'] ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Pembayaran</div>
                    <div class="info-value">: <?= ucfirst($transaksi['metode_pembayaran']) ?></div>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th class="col-no">No</th>
                        <th class="col-item">Item</th>
                        <th class="col-qty">Jml</th>
                        <th class="col-price">Harga</th>
                        <th class="col-total">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $no = 1;
                    $total = 0;
                    while ($row = $detailQuery->fetch_assoc()):
                        $total += $row['subtotal'];
                        // Check if it's a manual product or database product
                        $is_manual = ($row['produk_id'] == 0);
                        $product_name = $is_manual ? $row['nama_produk_manual'] : $row['nama_produk'];
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td>
                            <?= $product_name ?>
                        </td>
                        <td class="align-center"><?= $row['jumlah'] ?></td>
                        <td><?= number_format($row['harga_satuan'], 0, ',', '.') ?></td>
                        <td><?= number_format($row['subtotal'], 0, ',', '.') ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <div class="total">
                TOTAL: Rp <?= number_format($transaksi['total'], 0, ',', '.') ?>
            </div>

            <?php if (isset($transaksi['jumlah_bayar'])): ?>
            <div class="payment-details">
                BAYAR: Rp <?= number_format($transaksi['jumlah_bayar'], 0, ',', '.') ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($transaksi['kembalian']) && $transaksi['kembalian'] > 0): ?>
            <div class="kembalian-details">
                KEMBALI: Rp <?= number_format($transaksi['kembalian'], 0, ',', '.') ?>
            </div>
            <?php endif; ?>

            <?php if (isset($transaksi['hutang']) && $transaksi['hutang'] > 0): ?>
            <div class="debt-details">
                HUTANG: Rp <?= number_format($transaksi['hutang'], 0, ',', '.') ?>
            </div>
            <?php endif; ?>

            <?php if (isset($transaksi['status_hutang'])): ?>
            <div class="payment-status <?= ($transaksi['status_hutang'] == 0) ? 'lunas' : 'belum-lunas' ?>">
                <?= ($transaksi['status_hutang'] == 0) ? 'LUNAS' : 'BELUM LUNAS' ?>
            </div>
            <?php endif; ?>

            <div class="footer">
                <p class="thank-you">TERIMA KASIH ATAS KUNJUNGAN ANDA</p>
                <p>Silahkan datang kembali</p>
                <p>BMS MOTOR - SPESIALIS MOTOR ANDA</p>
            </div>
        </div>
        
        <button class="btn-print" onclick="cetakStruk()">CETAK STRUK</button>
    </div>

    <script>
        function cetakStruk() {
            var element = document.getElementById('struk');
            var opt = {
                margin: 1,
                filename: 'struk_<?= $formatted_id ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, letterRendering: true },
                jsPDF: { unit: 'mm', format: [85, 200], orientation: 'portrait' }
            };
            
            // Remove any temporary margin/padding added for screen display
            element.style.boxShadow = 'none';
            element.style.border = 'none';
            
            html2pdf().from(element).set(opt).save().then(function() {
                // Reset styles after PDF generation
                element.style.boxShadow = '0 0 10px rgba(0,0,0,0.1)';
                element.style.border = '1px solid #ddd';
                
                // Redirect to index page after printing
                setTimeout(function() {
                    window.location.href = 'index.php';
                }, 1000); // Add 1 second delay to ensure the PDF download is initiated
            });
        }
        
        // Auto-fit receipt to screen size for preview
        document.addEventListener('DOMContentLoaded', function() {
            const struk = document.getElementById('struk');
            if (window.innerWidth < 500) {
                struk.style.width = '95%';
            }
        });
    </script>
</body>
</html>