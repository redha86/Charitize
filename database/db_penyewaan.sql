-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 14 Nov 2025 pada 10.30
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
-- Database: `db_penyewaan`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `cart`
--

CREATE TABLE `cart` (
  `cart_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `type` enum('kostum','makeup') NOT NULL,
  `variasi_id` int(11) NOT NULL,
  `jadwal_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `detail_transaksi`
--

CREATE TABLE `detail_transaksi` (
  `id` int(11) NOT NULL,
  `id_transaksi` int(11) NOT NULL,
  `id_kostum` int(11) DEFAULT NULL,
  `id_kostum_variasi` int(11) DEFAULT NULL,
  `id_layanan_makeup` int(11) DEFAULT NULL,
  `jumlah` int(11) DEFAULT 1,
  `subtotal` decimal(10,2) NOT NULL,
  `tanggal_layanan` date DEFAULT NULL,
  `jam_mulai` time DEFAULT NULL,
  `jam_selesai` time DEFAULT NULL,
  `tanggal_sewa` date DEFAULT NULL,
  `tanggal_selesai` date DEFAULT NULL,
  `jumlah_hari` int(11) DEFAULT 1,
  `metode_ambil` enum('ambil','gosend') DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `catatan_admin` varchar(255) NOT NULL,
  `alamat` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `detail_transaksi`
--

INSERT INTO `detail_transaksi` (`id`, `id_transaksi`, `id_kostum`, `id_kostum_variasi`, `id_layanan_makeup`, `jumlah`, `subtotal`, `tanggal_layanan`, `jam_mulai`, `jam_selesai`, `tanggal_sewa`, `tanggal_selesai`, `jumlah_hari`, `metode_ambil`, `catatan`, `catatan_admin`, `alamat`) VALUES
(45, 39, 7, 2, NULL, 1, 200000.00, NULL, NULL, NULL, '2025-11-14', '2025-11-14', 1, 'gosend', 'apa saja oke', 'oke', 'apa saja'),
(46, 39, 7, 4, NULL, 1, 200000.00, NULL, NULL, NULL, '2025-11-14', '2025-11-14', 1, 'gosend', 'apa saja oke', 'oke', 'apa saja'),
(47, 39, NULL, NULL, 13, 1, 300000.00, '2025-10-30', '20:00:00', '21:00:00', NULL, NULL, 1, 'gosend', 'apa saja oke', 'oke', 'apa saja');

-- --------------------------------------------------------

--
-- Struktur dari tabel `foto_layanan_makeup`
--

CREATE TABLE `foto_layanan_makeup` (
  `id` int(20) NOT NULL,
  `id_layanan_makeup` int(20) NOT NULL,
  `path_foto` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `foto_layanan_makeup`
--

INSERT INTO `foto_layanan_makeup` (`id`, `id_layanan_makeup`, `path_foto`) VALUES
(1, 13, '1762765968_dsmakeup.jpeg');

-- --------------------------------------------------------

--
-- Struktur dari tabel `jadwal_makeup`
--

CREATE TABLE `jadwal_makeup` (
  `id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `status` enum('tersedia','dipesan') DEFAULT 'tersedia'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `jadwal_makeup`
--

INSERT INTO `jadwal_makeup` (`id`, `tanggal`, `jam_mulai`, `jam_selesai`, `status`) VALUES
(9, '2025-10-30', '20:00:00', '21:00:00', 'dipesan'),
(10, '2025-10-31', '20:00:00', '22:00:00', 'dipesan'),
(11, '2025-10-31', '02:00:00', '03:00:00', 'dipesan'),
(20, '2025-11-11', '20:00:00', '21:00:00', 'dipesan');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kategori_kostum`
--

CREATE TABLE `kategori_kostum` (
  `id` int(11) NOT NULL,
  `nama_kategori` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kategori_kostum`
--

INSERT INTO `kategori_kostum` (`id`, `nama_kategori`) VALUES
(1, 'superhero');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kategori_makeup`
--

CREATE TABLE `kategori_makeup` (
  `id` int(20) NOT NULL,
  `nama_kategori` varchar(255) NOT NULL,
  `harga` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kategori_makeup`
--

INSERT INTO `kategori_makeup` (`id`, `nama_kategori`, `harga`) VALUES
(1, 'Weding', '300000'),
(2, 'karnaval', '200000'),
(3, 'kemerdekaan', '200000');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kostum`
--

CREATE TABLE `kostum` (
  `id` int(11) NOT NULL,
  `id_kategori` int(11) DEFAULT NULL,
  `nama_kostum` varchar(150) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `harga_sewa` decimal(10,2) NOT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kostum`
--

INSERT INTO `kostum` (`id`, `id_kategori`, `nama_kostum`, `deskripsi`, `harga_sewa`, `foto`, `status`) VALUES
(7, 1, 'batman', 'Kostum terbaik untuk menjadi super hero yang kamu inginkan dan keren setiap acar festival', 200000.00, 'kostum_69124cb24af5b.jpeg', 'aktif'),
(9, 1, 'Pahlawan RI', 'apa saja oke', 300000.00, 'kostum_691255774b7eb.jpg', 'aktif');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kostum_variasi`
--

CREATE TABLE `kostum_variasi` (
  `id` int(11) NOT NULL,
  `id_kostum` int(11) NOT NULL,
  `ukuran` enum('S','M','L','XL') NOT NULL,
  `stok` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kostum_variasi`
--

INSERT INTO `kostum_variasi` (`id`, `id_kostum`, `ukuran`, `stok`) VALUES
(1, 7, 'S', 1),
(2, 7, 'M', 1),
(3, 7, 'L', 1),
(4, 7, 'XL', 1);

-- --------------------------------------------------------

--
-- Struktur dari tabel `layanan_makeup`
--

CREATE TABLE `layanan_makeup` (
  `id` int(11) NOT NULL,
  `id_kategori_makeup` int(20) NOT NULL,
  `nama_layanan` varchar(150) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `durasi` int(11) DEFAULT 0,
  `kategori` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `layanan_makeup`
--

INSERT INTO `layanan_makeup` (`id`, `id_kategori_makeup`, `nama_layanan`, `deskripsi`, `durasi`, `kategori`) VALUES
(13, 1, 'Makeup Weding', 'Makeup terbaik untuk jadi diri anda dengan yang terbaik dan penuhi semua ekspetasi', 60, ''),
(14, 2, 'makeup cosplay', 'makeup cosplay karnaval terbaik untuk anda dan juga keluarga anda', 60, 'karnaval');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pembayaran`
--

CREATE TABLE `pembayaran` (
  `id` int(11) NOT NULL,
  `id_user` int(20) NOT NULL,
  `id_transaksi` int(11) NOT NULL,
  `metode_pembayaran` varchar(50) DEFAULT NULL,
  `jumlah_bayar` decimal(10,2) DEFAULT NULL,
  `bukti_pembayaran` varchar(255) DEFAULT NULL,
  `status_pembayaran` enum('menunggu','terkonfirmasi','ditolak') DEFAULT 'menunggu',
  `tanggal_bayar` datetime DEFAULT current_timestamp(),
  `catatan_admin` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pembayaran`
--

INSERT INTO `pembayaran` (`id`, `id_user`, `id_transaksi`, `metode_pembayaran`, `jumlah_bayar`, `bukti_pembayaran`, `status_pembayaran`, `tanggal_bayar`, `catatan_admin`) VALUES
(14, 5, 39, 'Transfer', 700000.00, 'payment_6916f33ada5d1.png', 'ditolak', '2025-11-14 16:15:38', 'pembayaran kurang');

-- --------------------------------------------------------

--
-- Struktur dari tabel `transaksi`
--

CREATE TABLE `transaksi` (
  `id` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `tanggal_pemesanan` datetime DEFAULT current_timestamp(),
  `total_harga` decimal(10,2) NOT NULL,
  `status` enum('pending','paid','proses','selesai','batal','ditolak') DEFAULT 'pending',
  `type` enum('kostum','makeup') NOT NULL,
  `updated_at` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `transaksi`
--

INSERT INTO `transaksi` (`id`, `id_user`, `tanggal_pemesanan`, `total_harga`, `status`, `type`, `updated_at`) VALUES
(39, 5, '2025-11-14 15:32:51', 700000.00, 'ditolak', 'kostum', '2025-11-14 16:27:00');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','customer') DEFAULT 'customer',
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`user_id`, `name`, `email`, `username`, `password`, `role`, `phone`, `address`, `created_at`, `updated_at`) VALUES
(4, 'Admin', 'adminn@gmail.com', 'Admin', '$2y$10$jG5JHiHsyp1Bo7/kg6EyE.Sd/i5LdJKKR6i5fknSNHJ.jTNGV/qWu', 'admin', '081234567891', NULL, '2025-11-11 00:15:48', '2025-11-11 00:16:10'),
(5, 'Costumer', 'costumer@gmail.com', 'Costumer', '$2y$10$Rav1/yR4ZqZXHtSJzF9wiuc8tGbSnCXfvVkEyQBQBI9zD6KS7tgMe', 'customer', '081234567891', NULL, '2025-11-11 00:16:41', '2025-11-11 00:16:41');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`cart_id`),
  ADD UNIQUE KEY `ux_cart_user_product_variasi` (`user_id`,`product_id`,`type`,`variasi_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indeks untuk tabel `detail_transaksi`
--
ALTER TABLE `detail_transaksi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_transaksi` (`id_transaksi`),
  ADD KEY `id_kostum` (`id_kostum`),
  ADD KEY `id_layanan_makeup` (`id_layanan_makeup`);

--
-- Indeks untuk tabel `foto_layanan_makeup`
--
ALTER TABLE `foto_layanan_makeup`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `jadwal_makeup`
--
ALTER TABLE `jadwal_makeup`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `kategori_kostum`
--
ALTER TABLE `kategori_kostum`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `kategori_makeup`
--
ALTER TABLE `kategori_makeup`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `kostum`
--
ALTER TABLE `kostum`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_kategori` (`id_kategori`);

--
-- Indeks untuk tabel `kostum_variasi`
--
ALTER TABLE `kostum_variasi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_kostum` (`id_kostum`);

--
-- Indeks untuk tabel `layanan_makeup`
--
ALTER TABLE `layanan_makeup`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_transaksi` (`id_transaksi`);

--
-- Indeks untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_user` (`id_user`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `cart`
--
ALTER TABLE `cart`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT untuk tabel `detail_transaksi`
--
ALTER TABLE `detail_transaksi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT untuk tabel `foto_layanan_makeup`
--
ALTER TABLE `foto_layanan_makeup`
  MODIFY `id` int(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `jadwal_makeup`
--
ALTER TABLE `jadwal_makeup`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT untuk tabel `kategori_kostum`
--
ALTER TABLE `kategori_kostum`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `kategori_makeup`
--
ALTER TABLE `kategori_makeup`
  MODIFY `id` int(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `kostum`
--
ALTER TABLE `kostum`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT untuk tabel `kostum_variasi`
--
ALTER TABLE `kostum_variasi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT untuk tabel `layanan_makeup`
--
ALTER TABLE `layanan_makeup`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `kostum`
--
ALTER TABLE `kostum`
  ADD CONSTRAINT `kostum_ibfk_1` FOREIGN KEY (`id_kategori`) REFERENCES `kategori_kostum` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD CONSTRAINT `pembayaran_ibfk_1` FOREIGN KEY (`id_transaksi`) REFERENCES `transaksi` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
