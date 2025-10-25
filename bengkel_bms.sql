-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 02 Bulan Mei 2025 pada 09.32
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bengkel_bms`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `admin`
--

CREATE TABLE `admin` (
  `id_admin` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `no_wa` varchar(20) DEFAULT NULL,
  `foto` varchar(255) DEFAULT 'default.jpg',
  `alamat` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `role` varchar(20) NOT NULL DEFAULT 'admin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `admin`
--

INSERT INTO `admin` (`id_admin`, `username`, `password`, `nama`, `no_wa`, `foto`, `alamat`, `created_at`, `updated_at`, `role`) VALUES
(3, 'admin', 'admin1', 'Admin Bengkel BMS', NULL, '../uploads/1745159925_pexels-iriser-1366957 (1).jpg', NULL, '2025-04-20 08:08:02', '2025-05-02 02:56:07', 'admin');

-- --------------------------------------------------------

--
-- Struktur dari tabel `jual_bekas`
--

CREATE TABLE `jual_bekas` (
  `id` int(11) NOT NULL,
  `jenis` enum('Besi','Oli','Lainnya') NOT NULL,
  `berat` decimal(10,2) NOT NULL,
  `harga_per_kg` decimal(10,2) NOT NULL,
  `total_harga` decimal(10,2) NOT NULL,
  `pembeli` varchar(100) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `tanggal` date NOT NULL,
  `bulan` varchar(7) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `status_kas` tinyint(1) DEFAULT 0,
  `tanggal_masuk_kas` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `jual_bekas`
--

INSERT INTO `jual_bekas` (`id`, `jenis`, `berat`, `harga_per_kg`, `total_harga`, `pembeli`, `keterangan`, `tanggal`, `bulan`, `created_by`, `status_kas`, `tanggal_masuk_kas`, `created_at`, `updated_at`) VALUES
(13, 'Besi', 0.00, 0.00, 15000.00, 'Andra', '', '2025-04-29', '2025-04', 2, 1, '2025-04-29', '2025-04-29 02:15:24', '2025-04-29 02:15:24'),
(14, 'Besi', 0.00, 0.00, 20000.00, 'Andra', '', '2025-05-01', '2025-05', 2, 1, '2025-05-01', '2025-05-01 09:13:49', '2025-05-01 09:13:57'),
(15, 'Besi', 0.00, 0.00, 10000.00, 'Andra', '', '2025-05-01', '2025-05', 2, 1, '2025-05-01', '2025-05-01 11:05:50', '2025-05-01 11:05:56'),
(16, 'Besi', 0.00, 0.00, 10000.00, '', '', '2025-05-01', '2025-05', 2, 1, '2025-05-01', '2025-05-01 15:37:50', '2025-05-01 15:37:50');

-- --------------------------------------------------------

--
-- Struktur dari tabel `karyawan`
--

CREATE TABLE `karyawan` (
  `id_karyawan` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `no_wa` varchar(20) DEFAULT NULL,
  `foto` varchar(255) DEFAULT 'default.jpg',
  `alamat` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `role` varchar(20) NOT NULL DEFAULT 'karyawan'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `karyawan`
--

INSERT INTO `karyawan` (`id_karyawan`, `username`, `password`, `nama`, `no_wa`, `foto`, `alamat`, `created_at`, `updated_at`, `role`) VALUES
(5, 'cikal', 'cikal', 'Haical ravinda rassya', NULL, '../uploads/1745172686_cikal.jpg', 'Bekasi', '2025-04-20 18:10:34', '2025-04-28 12:15:09', 'karyawan'),
(7, 'andra', 'andra1', 'Ananda Andra Adrianto', NULL, '../uploads/1745939307_pastry-1.jpg', NULL, '2025-04-26 13:23:23', '2025-05-02 02:56:35', 'karyawan');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kasbon_manajer`
--

CREATE TABLE `kasbon_manajer` (
  `id` int(11) NOT NULL,
  `id_manajer` int(11) NOT NULL,
  `jumlah` decimal(10,2) NOT NULL,
  `tanggal` date NOT NULL,
  `bulan` varchar(7) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kasbon_manajer`
--

INSERT INTO `kasbon_manajer` (`id`, `id_manajer`, `jumlah`, `tanggal`, `bulan`, `keterangan`, `created_at`) VALUES
(2, 2, 100000.00, '2025-04-28', '2025-04', '', '2025-04-28 10:51:05'),
(3, 2, 100000.00, '2025-04-29', '2025-04', '', '2025-04-29 05:24:41'),
(4, 2, 20000.00, '2025-04-30', '2025-04', 'beli kopi\r\n', '2025-04-30 05:54:25'),
(5, 2, 100000.00, '2025-04-30', '2025-04', '', '2025-04-30 17:28:14');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kategori`
--

CREATE TABLE `kategori` (
  `id` int(11) NOT NULL,
  `nama_kategori` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kategori`
--

INSERT INTO `kategori` (`id`, `nama_kategori`) VALUES
(4, 'Ban'),
(8, 'Knalpot'),
(7, 'Mesin'),
(6, 'Oli'),
(9, 'Rem'),
(10, 'Spion');

-- --------------------------------------------------------

--
-- Struktur dari tabel `manajer`
--

CREATE TABLE `manajer` (
  `id_manajer` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `no_wa` varchar(20) DEFAULT NULL,
  `foto` varchar(255) DEFAULT 'default.jpg',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `role` varchar(20) NOT NULL DEFAULT 'manajer'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `manajer`
--

INSERT INTO `manajer` (`id_manajer`, `username`, `password`, `nama`, `no_wa`, `foto`, `created_at`, `updated_at`, `role`) VALUES
(2, 'manajer', 'manajer', 'Manajer Bengkel BMS', NULL, '../uploads/1745939554_bus-scene.gif', '2025-04-20 08:12:30', '2025-05-02 02:56:48', 'manajer');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengeluaran`
--

CREATE TABLE `pengeluaran` (
  `id` int(11) NOT NULL,
  `kategori` enum('Sewa Lahan','Token Listrik','Kasbon Karyawan','Uang Makan','Gaji Karyawan','Lainnya') NOT NULL,
  `jumlah` decimal(10,2) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `tanggal` date NOT NULL,
  `bulan` varchar(7) NOT NULL COMMENT 'Format: YYYY-MM',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL COMMENT 'ID User yang input'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pengeluaran`
--

INSERT INTO `pengeluaran` (`id`, `kategori`, `jumlah`, `keterangan`, `tanggal`, `bulan`, `created_at`, `created_by`) VALUES
(166, 'Sewa Lahan', 100000.00, '', '2025-05-01', '2025-05', '2025-05-01 10:43:11', 0),
(175, 'Token Listrik', 100000.00, '', '2025-05-01', '2025-05', '2025-05-01 11:09:32', 0),
(176, 'Lainnya', 100000.00, '', '2025-05-01', '2025-05', '2025-05-01 11:09:37', 0),
(177, 'Uang Makan', 50000.00, 'Uang Makan untuk Haical ravinda rassya:  [ID_KARYAWAN:5]', '2025-05-01', '2025-05', '2025-05-01 11:09:48', 0),
(179, 'Gaji Karyawan', 1000000.00, 'Gaji Ananda Andra Adrianto:  (Gaji Asli: Rp 1.000.000 - Kasbon: Rp 200.000)', '2025-05-01', '2025-05', '2025-05-01 11:10:08', 0),
(180, '', 50000.00, 'Penambahan stok produk: Ban Cacing (+5) [LUNAS]', '2025-05-01', '2025-05', '2025-05-01 11:10:34', NULL),
(181, '', 25000.00, 'Penambahan stok produk: Ban Cacing lunas (+5) [SEBAGIAN HUTANG: Rp 25.000]', '2025-05-01', '2025-05', '2025-05-01 11:12:24', NULL),
(184, '', 100000.00, 'Pembelian produk baru: Ban Cacing 21222 (x10) [LUNAS]', '2025-05-01', '2025-05', '2025-05-01 11:16:38', NULL),
(187, 'Gaji Karyawan', 1000000.00, 'Gaji Ananda Andra Adrianto:  (Gaji Asli: Rp 1.000.000 - Kasbon: Rp 400.000)', '2025-05-01', '2025-05', '2025-05-01 15:15:31', 0);

-- --------------------------------------------------------

--
-- Struktur dari tabel `piutang_cair`
--

CREATE TABLE `piutang_cair` (
  `id` int(11) NOT NULL,
  `transaksi_id` int(11) NOT NULL,
  `jumlah_bayar` decimal(10,2) NOT NULL,
  `tanggal_bayar` date NOT NULL,
  `bulan_bayar` varchar(7) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL COMMENT 'ID User yang input'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `piutang_cair`
--

INSERT INTO `piutang_cair` (`id`, `transaksi_id`, `jumlah_bayar`, `tanggal_bayar`, `bulan_bayar`, `keterangan`, `created_at`, `created_by`) VALUES
(180, 136, 1000.00, '2025-06-06', NULL, 'Pelunasan Piutang transaksi', '2025-05-02 07:31:40', 0),
(181, 136, 14000.00, '2025-05-02', NULL, 'Pelunasan Piutang transaksi', '2025-05-02 07:31:47', 0);

-- --------------------------------------------------------

--
-- Struktur dari tabel `produk`
--

CREATE TABLE `produk` (
  `id` int(11) NOT NULL,
  `nama` varchar(50) NOT NULL,
  `harga_beli` decimal(10,2) DEFAULT NULL,
  `harga_jual` decimal(10,2) NOT NULL,
  `stok` int(11) NOT NULL,
  `kategori_id` int(11) DEFAULT NULL,
  `hutang_sparepart` enum('Hutang','Cash') DEFAULT 'Cash',
  `nominal_hutang` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `produk`
--

INSERT INTO `produk` (`id`, `nama`, `harga_beli`, `harga_jual`, `stok`, `kategori_id`, `hutang_sparepart`, `nominal_hutang`) VALUES
(83, 'Ban Cacing', 10000.00, 15000.00, 11, 4, 'Cash', 0.00),
(84, 'Ban Cacing lunas', 10000.00, 15000.00, 19, 4, 'Cash', 0.00),
(85, 'Ananda Andra Adrianto aaaa', 25000.00, 30000.00, 1, NULL, 'Hutang', 9000.00),
(86, 'Ananda Andra', 10000.00, 13000.00, 3, NULL, 'Cash', 0.00),
(87, 'Ban Cacing 21222', 10000.00, 13000.00, 8, NULL, 'Cash', 0.00);

-- --------------------------------------------------------

--
-- Struktur dari tabel `produk_status_log`
--

CREATE TABLE `produk_status_log` (
  `id` int(11) NOT NULL,
  `produk_id` int(11) NOT NULL,
  `status_baru` varchar(50) NOT NULL,
  `status_lama` varchar(50) DEFAULT NULL,
  `waktu_perubahan` datetime NOT NULL DEFAULT current_timestamp(),
  `keterangan` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `transaksi`
--

CREATE TABLE `transaksi` (
  `id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `jumlah_bayar` decimal(10,2) DEFAULT 0.00,
  `hutang` decimal(10,2) DEFAULT 0.00,
  `status_hutang` tinyint(1) DEFAULT 0,
  `kembalian` decimal(10,2) DEFAULT 0.00,
  `kasir` varchar(25) NOT NULL,
  `nama_customer` varchar(100) NOT NULL,
  `no_whatsapp` varchar(20) NOT NULL,
  `alamat` text NOT NULL,
  `plat_nomor_motor` varchar(20) NOT NULL,
  `metode_pembayaran` enum('Cash','Transfer') NOT NULL,
  `pendapatan` decimal(15,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `transaksi`
--

INSERT INTO `transaksi` (`id`, `tanggal`, `total`, `jumlah_bayar`, `hutang`, `status_hutang`, `kembalian`, `kasir`, `nama_customer`, `no_whatsapp`, `alamat`, `plat_nomor_motor`, `metode_pembayaran`, `pendapatan`) VALUES
(134, '2025-05-02', 13000.00, 15000.00, 0.00, 0, 2000.00, 'andra', 'Andra', '+62829702018', 'Jln raya hankam', 'B 1477 KRS', 'Transfer', 13000.00),
(135, '2025-05-02', 13000.00, 15000.00, 0.00, 0, 2000.00, 'andra', 'Andra', '+62829702018', 'Jln raya hankam', 'B 1477 KRS', 'Transfer', 13000.00),
(136, '2025-05-02', 15000.00, 0.00, 0.00, 0, 0.00, 'andra', 'Andra', '+62829702018', 'Jln raya hankam', 'B 1477 KRS', 'Cash', 15000.00);

-- --------------------------------------------------------

--
-- Struktur dari tabel `transaksi_detail`
--

CREATE TABLE `transaksi_detail` (
  `id` int(11) NOT NULL,
  `transaksi_id` int(11) NOT NULL,
  `produk_id` int(11) DEFAULT NULL,
  `nama_produk_manual` varchar(255) DEFAULT NULL,
  `jumlah` int(11) NOT NULL,
  `harga_satuan` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `transaksi_detail`
--

INSERT INTO `transaksi_detail` (`id`, `transaksi_id`, `produk_id`, `nama_produk_manual`, `jumlah`, `harga_satuan`, `subtotal`) VALUES
(151, 134, 87, NULL, 1, 13000.00, 13000.00),
(152, 135, 87, NULL, 1, 13000.00, 13000.00),
(153, 136, 83, NULL, 1, 15000.00, 15000.00);

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id_admin`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indeks untuk tabel `jual_bekas`
--
ALTER TABLE `jual_bekas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indeks untuk tabel `karyawan`
--
ALTER TABLE `karyawan`
  ADD PRIMARY KEY (`id_karyawan`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indeks untuk tabel `kasbon_manajer`
--
ALTER TABLE `kasbon_manajer`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `kategori`
--
ALTER TABLE `kategori`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama_kategori` (`nama_kategori`);

--
-- Indeks untuk tabel `manajer`
--
ALTER TABLE `manajer`
  ADD PRIMARY KEY (`id_manajer`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indeks untuk tabel `pengeluaran`
--
ALTER TABLE `pengeluaran`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_bulan` (`bulan`),
  ADD KEY `idx_tanggal` (`tanggal`);

--
-- Indeks untuk tabel `piutang_cair`
--
ALTER TABLE `piutang_cair`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_transaksi` (`transaksi_id`),
  ADD KEY `idx_bulan_bayar` (`bulan_bayar`),
  ADD KEY `idx_transaksi_id` (`transaksi_id`),
  ADD KEY `idx_tanggal_bayar` (`tanggal_bayar`);

--
-- Indeks untuk tabel `produk`
--
ALTER TABLE `produk`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kategori_id` (`kategori_id`);

--
-- Indeks untuk tabel `produk_status_log`
--
ALTER TABLE `produk_status_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `produk_id` (`produk_id`),
  ADD KEY `status_baru` (`status_baru`),
  ADD KEY `waktu_perubahan` (`waktu_perubahan`);

--
-- Indeks untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status_hutang` (`status_hutang`);

--
-- Indeks untuk tabel `transaksi_detail`
--
ALTER TABLE `transaksi_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaksi_id` (`transaksi_id`),
  ADD KEY `produk_id` (`produk_id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `admin`
--
ALTER TABLE `admin`
  MODIFY `id_admin` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `jual_bekas`
--
ALTER TABLE `jual_bekas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT untuk tabel `karyawan`
--
ALTER TABLE `karyawan`
  MODIFY `id_karyawan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `kasbon_manajer`
--
ALTER TABLE `kasbon_manajer`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `kategori`
--
ALTER TABLE `kategori`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT untuk tabel `manajer`
--
ALTER TABLE `manajer`
  MODIFY `id_manajer` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `pengeluaran`
--
ALTER TABLE `pengeluaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=188;

--
-- AUTO_INCREMENT untuk tabel `piutang_cair`
--
ALTER TABLE `piutang_cair`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=182;

--
-- AUTO_INCREMENT untuk tabel `produk`
--
ALTER TABLE `produk`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88;

--
-- AUTO_INCREMENT untuk tabel `produk_status_log`
--
ALTER TABLE `produk_status_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=137;

--
-- AUTO_INCREMENT untuk tabel `transaksi_detail`
--
ALTER TABLE `transaksi_detail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=154;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `jual_bekas`
--
ALTER TABLE `jual_bekas`
  ADD CONSTRAINT `jual_bekas_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `manajer` (`id_manajer`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `piutang_cair`
--
ALTER TABLE `piutang_cair`
  ADD CONSTRAINT `fk_transaksi` FOREIGN KEY (`transaksi_id`) REFERENCES `transaksi` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `produk`
--
ALTER TABLE `produk`
  ADD CONSTRAINT `produk_ibfk_1` FOREIGN KEY (`kategori_id`) REFERENCES `kategori` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `transaksi_detail`
--
ALTER TABLE `transaksi_detail`
  ADD CONSTRAINT `transaksi_detail_ibfk_1` FOREIGN KEY (`transaksi_id`) REFERENCES `transaksi` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transaksi_detail_ibfk_2` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
