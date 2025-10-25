<?php
include '../config.php';
session_start();

// Cek akses
if (!isset($_SESSION['karyawan']['logged_in']) || $_SESSION['karyawan']['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Simpan Transaksi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = $_POST['nama_customer'];
    
    // Handle WhatsApp number (optional)
    $no_wa = '';
    if (!empty($_POST['no_whatsapp'])) {
        $no_wa = preg_replace('/[^0-9]/', '', $_POST['no_whatsapp']); // Hapus karakter selain angka
        if (substr($no_wa, 0, 1) === '0') {
            $no_wa = '+62' . substr($no_wa, 1); // Ubah awalan 0 jadi +62
        } else if (substr($no_wa, 0, 2) === '62') {
            $no_wa = '+'.$no_wa; // Jika sudah 62, tinggal tambahkan +
        }
    }
    
    $alamat = $_POST['alamat'];
    $plat = $_POST['plat_nomor_motor'];
    $metode = $_POST['metode_pembayaran'];
    $kasir = $_SESSION['karyawan']['username']; // Menggunakan struktur session baru
    $tanggal = date('Y-m-d');

    $produk_list = $_POST['produk'];
    $produk_nama_list = isset($_POST['produk_nama']) ? $_POST['produk_nama'] : []; // Untuk produk manual
    $jumlah_list = $_POST['jumlah'];
    $harga_list = $_POST['harga'];

    $total = 0;
    for ($i = 0; $i < count($produk_list); $i++) {
        $total += $jumlah_list[$i] * $harga_list[$i];
    }
    
    // Proses jumlah bayar, hutang, dan kembalian
    $jumlah_bayar = floatval($_POST['jumlah_bayar']);
    $kembalian = 0;
    
    if ($jumlah_bayar > $total) {
        // Jika bayar lebih dari total, maka ada kembalian dan tidak ada hutang
        $kembalian = $jumlah_bayar - $total;
        $hutang = 0;
        $status_hutang = 0;
    } else {
        // Jika bayar kurang dari atau sama dengan total
        $kembalian = 0;
        $hutang = $total - $jumlah_bayar;
        $status_hutang = ($hutang > 0) ? 1 : 0;
    }
    
    // Hitung pendapatan (jumlah yang dibayarkan customer - kembalian)
    // Sesuai dengan logika bisnis: Pendapatan = Jumlah Bayar - Kembalian
    $pendapatan = $jumlah_bayar - $kembalian;

    // Tambahkan jumlah bayar, hutang, kembalian, dan pendapatan ke tabel transaksi
    $stmt = $conn->prepare("INSERT INTO transaksi (tanggal, total, kasir, nama_customer, no_whatsapp, alamat, plat_nomor_motor, metode_pembayaran, jumlah_bayar, hutang, status_hutang, kembalian, pendapatan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sdssssssddidd", $tanggal, $total, $kasir, $nama, $no_wa, $alamat, $plat, $metode, $jumlah_bayar, $hutang, $status_hutang, $kembalian, $pendapatan); 
    $stmt->execute();
    $transaksi_id = $stmt->insert_id;

    // Insert transaction details
    for ($i = 0; $i < count($produk_list); $i++) {
        $subtotal = $jumlah_list[$i] * $harga_list[$i];
        
        // If produk from database (id numeric)
        if (is_numeric($produk_list[$i]) && $produk_list[$i] > 0) {
            $stmt_detail = $conn->prepare("INSERT INTO transaksi_detail (transaksi_id, produk_id, jumlah, harga_satuan, subtotal) VALUES (?, ?, ?, ?, ?)");
            $stmt_detail->bind_param("iiddd", $transaksi_id, $produk_list[$i], $jumlah_list[$i], $harga_list[$i], $subtotal);
            $stmt_detail->execute();

            // Update stok produk if from database
            $conn->query("UPDATE produk SET stok = stok - {$jumlah_list[$i]} WHERE id = {$produk_list[$i]}");
        } 
        // If manual product (id = 0)
        else {
            // Get the manual product name
            $nama_produk = $produk_nama_list[$i];
            
            // For manual products, ONLY use the columns that don't involve produk_id
            $stmt_detail = $conn->prepare("INSERT INTO transaksi_detail (transaksi_id, nama_produk_manual, jumlah, harga_satuan, subtotal) VALUES (?, ?, ?, ?, ?)");
            $stmt_detail->bind_param("isidd", $transaksi_id, $nama_produk, $jumlah_list[$i], $harga_list[$i], $subtotal);
            $stmt_detail->execute();
        }
    }

    // Redirect langsung ke halaman detail tanpa alert
    header("Location: detail_transaksi.php?id=" . $transaksi_id);
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Transaksi</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-green: #26A69A;
            --secondary-green: #00897B;
            --light-green: #E0F2F1;
            --accent-green: #00695C;
            --white: #ffffff;
            --light-gray: #f8f9fa;
            --text-dark: #2C3E50;
            --border-color: #e1e7ef;
            --warning-color: #FFC107;
            --danger-color: #F44336;
            --success-color: #4CAF50;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background-color: var(--light-gray);
            color: var(--text-dark);
            line-height: 1.6;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
            padding: 30px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid var(--primary-green);
            padding-bottom: 15px;
        }
        
        .header h2 {
            color: var(--primary-green);
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--accent-green);
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 2px rgba(38, 166, 154, 0.2);
        }
        
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }
        
        .col {
            flex: 1;
            padding: 0 10px;
            min-width: 200px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: var(--primary-green);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            text-align: center;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        
        .btn:hover {
            background-color: var(--accent-green);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(38, 166, 154, 0.15);
        }
        
        .btn-light {
            background-color: #f1f3f4;
            color: var(--text-dark);
        }
        
        .btn-light:hover {
            background-color: #e8eaed;
        }
        
        .section-title {
            margin: 30px 0 20px;
            color: var(--primary-green);
            font-weight: 600;
            border-left: 4px solid var(--primary-green);
            padding-left: 10px;
        }
        
        .produk-container {
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            background-color: var(--light-green);
        }
        
        .produk-item {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 15px;
            padding: 15px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .produk-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .produk-item > * {
            margin: 5px 10px 5px 0;
        }
        
        .produk-item input[type="number"] {
            width: 100px;
        }
        
        .produk-search {
            flex: 2;
            min-width: 200px;
        }
        
        .produk-qty {
            flex: 1;
            min-width: 100px;
        }
        
        .produk-price, .produk-subtotal {
            flex: 1;
            min-width: 150px;
        }
		
        .label-group {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .label-group span {
            font-size: 14px;
            color: #666;
        }
        
        .action-row {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        
        .total-section {
            background: linear-gradient(135deg, var(--primary-green), var(--accent-green));
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            text-align: right;
            margin-top: 20px;
            font-size: 18px;
            font-weight: 600;
            box-shadow: 0 4px 8px rgba(38, 166, 154, 0.15);
        }
        
        @media (max-width: 768px) {
            .col {
                flex: 100%;
            }
            
            .produk-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .produk-item > * {
                width: 100%;
                margin: 5px 0;
            }
        }
        
        .empty-state {
            padding: 20px;
            text-align: center;
            color: #666;
        }
        
        .btn-add-product {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--white);
            color: var(--primary-green);
            border: 1.5px solid var(--primary-green);
        }
        
        .btn-add-product:hover {
            background-color: var(--primary-green);
            color: var(--white);
        }
        
        .btn-add-product i {
            margin-right: 8px;
        }
        
        .toggle-switch {
            display: flex;
            align-items: center;
            margin-top: 5px;
        }
        
        .toggle-switch input[type="checkbox"] {
            height: 0;
            width: 0;
            visibility: hidden;
            position: absolute;
        }
        
        .toggle-switch label {
            cursor: pointer;
            width: 50px;
            height: 24px;
            background: #ccc;
            display: block;
            border-radius: 24px;
            position: relative;
            margin-right: 10px;
        }
        
        .toggle-switch label:after {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 18px;
            height: 18px;
            background: #fff;
            border-radius: 18px;
            transition: 0.3s;
        }
        
        .toggle-switch input:checked + label {
            background: var(--primary-green);
        }
        
        .toggle-switch input:checked + label:after {
            left: calc(100% - 3px);
            transform: translateX(-100%);
        }
        
        .toggle-text {
            font-size: 14px;
            color: #666;
        }
        
        .payment-section {
            background-color: var(--light-green);
            border-radius: 10px;
            padding: 20px;
            margin-top: 25px;
        }
        
        .payment-row {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .payment-label {
            flex: 1;
            font-weight: 500;
            color: var(--accent-green);
        }
        
        .payment-value {
            flex: 2;
        }
        
        .debt-alert {
            background-color: rgba(244, 67, 54, 0.1);
            color: var(--danger-color);
            padding: 10px 15px;
            border-radius: 8px;
            margin-top: 15px;
            font-weight: 500;
            display: none;
        }
        
        .debt-alert i {
            margin-right: 8px;
        }
        
        /* Tambahan untuk produk manual */
        .manual-mode .form-control {
            border-color: var(--primary-green);
            background-color: var(--light-green);
        }
    </style>
    <script>
        let produkList = <?php
        $result = $conn->query("SELECT id, nama, harga_jual FROM produk WHERE stok > 0");
        $produk_array = [];
        while ($row = $result->fetch_assoc()) {
            $produk_array[] = $row;
        }
        echo json_encode($produk_array);
        ?>;

        function tambahProduk() {
            const container = document.getElementById("produk-container");
            
            // Hapus pesan empty state jika ada
            const emptyState = document.querySelector('.empty-state');
            if (emptyState) {
                emptyState.remove();
            }

            const wrapper = document.createElement("div");
            wrapper.className = "produk-item";

            // Product search with toggle switch for manual input
            const searchDiv = document.createElement("div");
            searchDiv.className = "produk-search";
            
            const searchLabel = document.createElement("div");
            searchLabel.className = "label-group";
            searchLabel.innerHTML = '<span>Nama Produk</span>';
            
            // Toggle switch for manual product
            const toggleDiv = document.createElement("div");
            toggleDiv.className = "toggle-switch";
            
            const toggleId = "toggle-manual-" + Date.now();
            
            const toggleInput = document.createElement("input");
            toggleInput.type = "checkbox";
            toggleInput.id = toggleId;
            toggleInput.className = "toggle-manual";
            
            const toggleLabel = document.createElement("label");
            toggleLabel.setAttribute("for", toggleId);
            
            const toggleText = document.createElement("span");
            toggleText.className = "toggle-text";
            toggleText.innerText = "Produk Manual";
            
            toggleDiv.appendChild(toggleInput);
            toggleDiv.appendChild(toggleLabel);
            toggleDiv.appendChild(toggleText);
            
            searchLabel.appendChild(toggleDiv);
            
            // Input for product name/search
            const searchInput = document.createElement("input");
            searchInput.setAttribute("list", "produkList");
            searchInput.className = "form-control product-name-input";
            searchInput.placeholder = "Ketik nama produk";
            
            // Hidden product select
            const produkSelect = document.createElement("select");
            produkSelect.name = "produk[]";
            produkSelect.style.display = "none";
            
            // Add default option for manual product
            const defaultOpt = document.createElement("option");
            defaultOpt.value = "0";
            defaultOpt.text = "Produk Manual";
            produkSelect.appendChild(defaultOpt);
            
            // Add database products
            produkList.forEach(p => {
                const opt = document.createElement("option");
                opt.value = p.id;
                opt.text = p.nama;
                produkSelect.appendChild(opt);
            });
            
            // Input for manual product name
            const produkNamaInput = document.createElement("input");
            produkNamaInput.type = "text";
            produkNamaInput.name = "produk_nama[]";
            produkNamaInput.className = "form-control";
            produkNamaInput.style.display = "none";
            
            searchInput.oninput = function () {
                if (!toggleInput.checked) {
                    // Database product mode
                    const selected = produkList.find(p => p.nama === searchInput.value);
                    if (selected) {
                        produkSelect.value = selected.id;
                        produkNamaInput.value = ""; // Clear manual name
                        hargaInput.value = selected.harga_jual;
                        hargaInput.readOnly = true;
                    }
                } else {
                    // Manual product mode - update hidden field
                    produkNamaInput.value = searchInput.value;
                }
                updateSubtotal();
            };
            
            toggleInput.onchange = function() {
                if (toggleInput.checked) {
                    // Manual product mode
                    searchDiv.classList.add("manual-mode");
                    searchInput.removeAttribute("list");
                    searchInput.placeholder = "Masukkan nama produk manual";
                    searchInput.value = "";
                    produkSelect.value = "0"; // Set to manual product
                    produkNamaInput.value = ""; // Clear initially
                    hargaInput.value = "";
                    hargaInput.readOnly = false;
                } else {
                    // Database product mode
                    searchDiv.classList.remove("manual-mode");
                    searchInput.setAttribute("list", "produkList");
                    searchInput.placeholder = "Ketik nama produk";
                    searchInput.value = "";
                    produkNamaInput.value = "";
                    hargaInput.value = "";
                    hargaInput.readOnly = true;
                }
                updateSubtotal();
            };
            
            searchDiv.appendChild(searchLabel);
            searchDiv.appendChild(searchInput);

            // Quantity input
            const qtyDiv = document.createElement("div");
            qtyDiv.className = "produk-qty";
            
            const qtyLabel = document.createElement("div");
            qtyLabel.className = "label-group";
            qtyLabel.innerHTML = '<span>Jumlah</span>';
            
            const jumlahInput = document.createElement("input");
            jumlahInput.name = "jumlah[]";
            jumlahInput.type = "number";
            jumlahInput.className = "form-control";
            jumlahInput.min = 1;
            jumlahInput.value = 1;
            jumlahInput.oninput = updateSubtotal;
            
            qtyDiv.appendChild(qtyLabel);
            qtyDiv.appendChild(jumlahInput);

            // Price input
            const priceDiv = document.createElement("div");
            priceDiv.className = "produk-price";
            
            const priceLabel = document.createElement("div");
            priceLabel.className = "label-group";
            priceLabel.innerHTML = '<span>Harga Satuan</span>';
            
            const hargaInput = document.createElement("input");
            hargaInput.name = "harga[]";
            hargaInput.type = "number";
            hargaInput.className = "form-control";
            hargaInput.readOnly = true;
            hargaInput.oninput = updateSubtotal;
            
            priceDiv.appendChild(priceLabel);
            priceDiv.appendChild(hargaInput);

            // Subtotal input
            const subtotalDiv = document.createElement("div");
            subtotalDiv.className = "produk-subtotal";
            
            const subtotalLabel = document.createElement("div");
            subtotalLabel.className = "label-group";
            subtotalLabel.innerHTML = '<span>Subtotal</span>';
            
            const subtotalInput = document.createElement("input");
            subtotalInput.type = "number";
            subtotalInput.className = "form-control subtotal";
            subtotalInput.readOnly = true;
            
            subtotalDiv.appendChild(subtotalLabel);
            subtotalDiv.appendChild(subtotalInput);

            // Remove button
            const removeBtn = document.createElement("button");
            removeBtn.type = "button";
            removeBtn.className = "btn btn-light";
            removeBtn.innerHTML = '<i class="fas fa-trash"></i>';
            removeBtn.onclick = function() {
                wrapper.remove();
                updateSubtotal();
                
                // Show empty state if no products are left
                if (document.querySelectorAll(".produk-item").length === 0) {
                    showEmptyState();
                }
            };

            // Append all elements
            wrapper.appendChild(searchDiv);
            wrapper.appendChild(produkSelect);
            wrapper.appendChild(produkNamaInput);
            wrapper.appendChild(qtyDiv);
            wrapper.appendChild(priceDiv);
            wrapper.appendChild(subtotalDiv);
            wrapper.appendChild(removeBtn);

            container.appendChild(wrapper);
            updateSubtotal();
        }

        function showEmptyState() {
            const container = document.getElementById("produk-container");
            const emptyState = document.createElement("div");
            emptyState.className = "empty-state";
            emptyState.innerHTML = '<i class="fas fa-shopping-cart" style="font-size: 48px; color: #26A69A; margin-bottom: 15px;"></i><p>Belum ada produk. Klik tombol "Tambah Produk" untuk menambahkan produk.</p>';
            container.appendChild(emptyState);
        }

        function updateSubtotal() {
            const items = document.querySelectorAll(".produk-item");
            let total = 0;
            
            items.forEach(item => {
                const qty = parseFloat(item.querySelector("input[name='jumlah[]']").value) || 0;
                const harga = parseFloat(item.querySelector("input[name='harga[]']").value) || 0;
                const subtotal = qty * harga;
                item.querySelector(".subtotal").value = subtotal;
                total += subtotal;
            });
            
            document.getElementById("total_label").innerHTML = "Rp " + total.toLocaleString('id-ID');
            document.getElementById("total_amount").value = total;
            
            // Update jumlah bayar default
            const jumlahBayarInput = document.getElementById("jumlah_bayar");
            if (!jumlahBayarInput.hasUserInput) {
                jumlahBayarInput.value = total;
            }
            
            // Calculate and show hutang or kembalian
            calculateHutang();
        }
        
        function calculateHutang() {
            const totalAmount = parseFloat(document.getElementById("total_amount").value) || 0;
            const jumlahBayar = parseFloat(document.getElementById("jumlah_bayar").value) || 0;
            
            if (jumlahBayar > totalAmount) {
                // Calculate change when payment exceeds total
                const kembalian = jumlahBayar - totalAmount;
                document.getElementById("hutang_label").textContent = "Rp 0";
                document.getElementById("kembalian_label").textContent = "Rp " + kembalian.toLocaleString('id-ID');
                document.getElementById("kembalian_amount").value = kembalian;
                document.getElementById("kembalian_row").style.display = "flex";
                document.getElementById("debt-alert").style.display = "none";
                
                // Menampilkan pendapatan saat ada kembalian
                const pendapatan = totalAmount; // Pendapatan = Total (karena kembalian dikembalikan)
                document.getElementById("pendapatan_label").textContent = "Rp " + pendapatan.toLocaleString('id-ID');
                document.getElementById("pendapatan_amount").value = pendapatan;
                document.getElementById("pendapatan_row").style.display = "flex";
            } else {
                // Calculate debt as before
                const hutang = totalAmount - jumlahBayar;
                document.getElementById("hutang_label").textContent = "Rp " + hutang.toLocaleString('id-ID');
                document.getElementById("kembalian_label").textContent = "Rp 0";
                document.getElementById("kembalian_amount").value = 0;
                document.getElementById("kembalian_row").style.display = "none";
                
                // Menampilkan pendapatan saat ada hutang
                const pendapatan = jumlahBayar; // Pendapatan = Jumlah Bayar (karena hanya itu yang diterima)
                document.getElementById("pendapatan_label").textContent = "Rp " + pendapatan.toLocaleString('id-ID');
                document.getElementById("pendapatan_amount").value = pendapatan;
                document.getElementById("pendapatan_row").style.display = "flex";
                
                // Show or hide debt alert
                const debtAlert = document.getElementById("debt-alert");
                if (hutang > 0) {
                    debtAlert.style.display = "block";
                    debtAlert.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Perhatian: Transaksi ini memiliki hutang sebesar Rp ' + hutang.toLocaleString('id-ID');
                } else {
                    debtAlert.style.display = "none";
                }
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            showEmptyState();
            
            // Mark jumlah bayar when user manually changes it
            const jumlahBayarInput = document.getElementById("jumlah_bayar");
            jumlahBayarInput.addEventListener('input', function() {
                jumlahBayarInput.hasUserInput = true;
                calculateHutang();
            });
        });
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2><i class="fas fa-shopping-cart"></i> Input Transaksi Baru</h2>
        </div>
        
        <form method="POST">
            <h3 class="section-title">Data Customer</h3>
            <div class="row">
                <div class="col">
                    <div class="form-group">
                        <label for="nama_customer">Nama Customer</label>
                        <input type="text" id="nama_customer" name="nama_customer" class="form-control" placeholder="Masukkan nama customer">
                    </div>
                </div>
                <div class="col">
                    <div class="form-group">
                        <label for="no_whatsapp">No WhatsApp</label>
                        <input type="text" id="no_whatsapp" name="no_whatsapp" class="form-control" placeholder="contoh: 08123456789">
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col">
                    <div class="form-group">
                        <label for="alamat">Alamat</label>
                        <input type="text" id="alamat" name="alamat" class="form-control" placeholder="Masukkan alamat lengkap">
                    </div>
                </div>
                <div class="col">
                    <div class="form-group">
                        <label for="plat_nomor_motor">Plat Nomor Kendaraan</label>
                        <input type="text" id="plat_nomor_motor" name="plat_nomor_motor" class="form-control" placeholder="contoh: B 1234 ABC">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="metode_pembayaran">Metode Pembayaran</label>
                <select id="metode_pembayaran" name="metode_pembayaran" class="form-control" required>
                    <option value="">-- Pilih Metode Pembayaran --</option>
                    <option value="Cash">Cash</option>
                    <option value="Transfer">Transfer</option>
                </select>
            </div>
            
            <h3 class="section-title">Detail Produk</h3>
            <datalist id="produkList">
                <?php foreach ($produk_array as $produk): ?>
                    <option value="<?= $produk['nama'] ?>">
                <?php endforeach; ?>
            </datalist>
            
            <div id="produk-container" class="produk-container">
                <!-- Product items will be added here -->
            </div>
            
            <button type="button" class="btn btn-add-product" onclick="tambahProduk()">
                <i class="fas fa-plus-circle"></i> Tambah Produk
            </button>
            
            <div class="total-section">
                Total: <span id="total_label">Rp 0</span>
                <input type="hidden" id="total_amount" value="0">
            </div>
            
            <div class="payment-section">
                <h3 class="section-title">Pembayaran</h3>
                
                <div class="payment-row">
                    <div class="payment-label">Jumlah Bayar:</div>
                    <div class="payment-value">
                        <input type="number" id="jumlah_bayar" name="jumlah_bayar" class="form-control" value="0" min="0" required>
                    </div>
                </div>
                
                <div class="payment-row">
                    <div class="payment-label">Hutang:</div>
                    <div class="payment-value">
                        <strong id="hutang_label">Rp 0</strong>
                    </div>
                </div>
                
                <div class="payment-row" id="kembalian_row" style="display: none;">
                    <div class="payment-label">Kembalian:</div>
                    <div class="payment-value">
                        <strong id="kembalian_label">Rp 0</strong>
                        <input type="hidden" id="kembalian_amount" name="kembalian" value="0">
                    </div>
                </div>
                
                <div class="payment-row" id="pendapatan_row">
                    <div class="payment-label">Pendapatan:</div>
                    <div class="payment-value">
                        <strong id="pendapatan_label">Rp 0</strong>
                        <input type="hidden" id="pendapatan_amount" name="pendapatan" value="0">
                    </div>
                </div>
                
                <div id="debt-alert" class="debt-alert">
                    <!-- Debt warning message will appear here -->
                </div>
            </div>
            
            <div class="action-row">
                <a href="index.php" class="btn btn-light">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
                <button type="submit" class="btn">
                    <i class="fas fa-save"></i> Simpan Transaksi
                </button>
            </div>
        </form>
    </div>
</body>
</html>