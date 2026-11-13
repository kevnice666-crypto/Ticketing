-- ==============================================================================
-- DATABASE WEB TICKETING IT SUPPORT (ticketing_db)
-- Kompatibel dengan MySQL 5.7+, MariaDB 10.3+, dan PHP 8+
-- ==============================================================================

CREATE DATABASE IF NOT EXISTS `ticketing_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ticketing_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. TABEL: users
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nama` VARCHAR(100) NOT NULL,
  `email` VARCHAR(120) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('Admin', 'IT Support', 'User') NOT NULL DEFAULT 'User',
  `departemen` VARCHAR(100) DEFAULT NULL,
  `no_hp` VARCHAR(30) DEFAULT NULL,
  `avatar` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('Aktif', 'Nonaktif') NOT NULL DEFAULT 'Aktif',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 2. TABEL: categories
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nama` VARCHAR(100) NOT NULL,
  `deskripsi` VARCHAR(255) DEFAULT NULL,
  `urutan` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 3. TABEL: tickets
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `tickets`;
CREATE TABLE `tickets` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ticket_number` VARCHAR(30) NOT NULL UNIQUE,
  `user_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED DEFAULT NULL,
  `assigned_to` INT UNSIGNED DEFAULT NULL,
  `judul` VARCHAR(200) NOT NULL,
  `deskripsi` TEXT NOT NULL,
  `prioritas` ENUM('Rendah', 'Sedang', 'Tinggi', 'Urgent') NOT NULL DEFAULT 'Sedang',
  `status` ENUM('Open', 'In Progress', 'Waiting for User', 'Resolved', 'Closed', 'Reopened') NOT NULL DEFAULT 'Open',
  `lampiran` VARCHAR(255) DEFAULT NULL,
  `lampiran_nama` VARCHAR(255) DEFAULT NULL,
  `lampiran_ukuran` INT UNSIGNED DEFAULT 0,
  `sla_due_at` DATETIME NOT NULL,
  `first_response_at` DATETIME DEFAULT NULL,
  `resolved_at` DATETIME DEFAULT NULL,
  `closed_at` DATETIME DEFAULT NULL,
  `rating` TINYINT UNSIGNED DEFAULT NULL,
  `rating_feedback` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_status` (`status`),
  INDEX `idx_prioritas` (`prioritas`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_assigned_to` (`assigned_to`),
  INDEX `idx_category_id` (`category_id`),
  CONSTRAINT `fk_tickets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tickets_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tickets_agent` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 4. TABEL: ticket_messages (Percakapan & Catatan Internal)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `ticket_messages`;
CREATE TABLE `ticket_messages` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `message` TEXT NOT NULL,
  `is_internal` TINYINT(1) NOT NULL DEFAULT 0,
  `lampiran` VARCHAR(255) DEFAULT NULL,
  `lampiran_nama` VARCHAR(255) DEFAULT NULL,
  `lampiran_ukuran` INT UNSIGNED DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_ticket_id` (`ticket_id`),
  INDEX `idx_is_internal` (`is_internal`),
  CONSTRAINT `fk_messages_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_messages_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 5. TABEL: ticket_history (Riwayat Status & Audit Trail)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `ticket_history`;
CREATE TABLE `ticket_history` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `keterangan` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_history_ticket` (`ticket_id`),
  CONSTRAINT `fk_history_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 6. TABEL: notifications (Pemberitahuan In-App)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `ticket_id` INT UNSIGNED DEFAULT NULL,
  `title` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `link` VARCHAR(255) NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_notif_user` (`user_id`),
  INDEX `idx_notif_read` (`is_read`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 7. TABEL: login_attempts (Proteksi Brute-Force Login)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ip_address` VARCHAR(45) NOT NULL,
  `email` VARCHAR(120) NOT NULL,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_attempts_ip` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ==============================================================================
-- DATA AWAL (SEEDING)
-- ==============================================================================

-- 1. PENGGUNA AWAL (Semua password default: 'password123')
-- Hash bcrypt standar PHP: $2y$10$mNQg9b/Lm.Kvt6d.H8Bi5.Jd3MswiKmoJu85tR8OuowrQOPa2SlzO
INSERT INTO `users` (`id`, `nama`, `email`, `password`, `role`, `departemen`, `no_hp`, `status`) VALUES
(1, 'Kevin Pratama (Admin)', 'admin@ticketing.local', '$2y$10$mNQg9b/Lm.Kvt6d.H8Bi5.Jd3MswiKmoJu85tR8OuowrQOPa2SlzO', 'Admin', 'Information Technology', '081255550001', 'Aktif'),
(2, 'Budi Santoso', 'support1@ticketing.local', '$2y$10$mNQg9b/Lm.Kvt6d.H8Bi5.Jd3MswiKmoJu85tR8OuowrQOPa2SlzO', 'IT Support', 'IT Support & Infrastructure', '081255550002', 'Aktif'),
(3, 'Siti Rahmawati', 'support2@ticketing.local', '$2y$10$mNQg9b/Lm.Kvt6d.H8Bi5.Jd3MswiKmoJu85tR8OuowrQOPa2SlzO', 'IT Support', 'IT Support & Helpdesk', '081255550003', 'Aktif'),
(4, 'Dedi Kurniawan', 'user1@ticketing.local', '$2y$10$mNQg9b/Lm.Kvt6d.H8Bi5.Jd3MswiKmoJu85tR8OuowrQOPa2SlzO', 'User', 'Keuangan & Akuntansi', '081255550004', 'Aktif'),
(5, 'Rina Anggraini', 'user2@ticketing.local', '$2y$10$mNQg9b/Lm.Kvt6d.H8Bi5.Jd3MswiKmoJu85tR8OuowrQOPa2SlzO', 'User', 'Kepegawaian & SDM', '081255550005', 'Aktif'),
(6, 'Fajar Hidayat', 'user3@ticketing.local', '$2y$10$mNQg9b/Lm.Kvt6d.H8Bi5.Jd3MswiKmoJu85tR8OuowrQOPa2SlzO', 'User', 'Pelayanan Terpadu & Perizinan', '081255550006', 'Aktif');

-- 2. KATEGORI MASALAH
INSERT INTO `categories` (`id`, `nama`, `deskripsi`, `urutan`, `is_active`) VALUES
(1, 'Hardware', 'Perangkat keras PC, Laptop, Monitor, Mouse, Keyboard', 1, 1),
(2, 'Software', 'Aplikasi perkantoran, OS Windows/Linux, error program', 2, 1),
(3, 'Jaringan', 'Koneksi LAN, Wi-Fi kantor, VPN, Internet lambat/putus', 3, 1),
(4, 'Akun', 'Reset password akun sistem, pembuatan akun baru, hak akses', 4, 1),
(5, 'Email', 'Masalah email resmi kantor, Outlook, kuota mailbox penuh', 5, 1),
(6, 'Printer', 'Printer sharing, paper jam, tinta habis, scanner', 6, 1),
(7, 'Lainnya', 'Permintaan instalasi, konsultasi teknis, dan kebutuhan IT lain', 7, 1);

-- 3. TIKET CONTOH (8 Tiket Mencakup Seluruh Variasi Status & Prioritas)
INSERT INTO `tickets` (`id`, `ticket_number`, `user_id`, `category_id`, `assigned_to`, `judul`, `deskripsi`, `prioritas`, `status`, `lampiran`, `lampiran_nama`, `lampiran_ukuran`, `sla_due_at`, `first_response_at`, `resolved_at`, `closed_at`, `rating`, `rating_feedback`, `created_at`, `updated_at`) VALUES
(1, 'TKT-20261110-0001', 4, 6, 2, 'Printer Epson L3210 Ruang Keuangan Menarik Kertas Dobel', 'Saat mencetak berkas SPJ pembayaran, printer sering menarik 2-3 lembar kertas sekaligus dan macet di roll penarik.', 'Sedang', 'Resolved', NULL, NULL, 0, '2026-11-10 16:00:00', '2026-11-10 09:30:00', '2026-11-10 14:15:00', NULL, 5, 'Pelayanan cepat dan ramah, roll karet penarik sudah diganti baru.', '2026-11-10 08:00:00', '2026-11-10 14:15:00'),
(2, 'TKT-20261111-0001', 5, 3, 2, 'Koneksi Wi-Fi Lantai 2 Sering Putus Saat Jam Kerja', 'Sinyal Wi-Fi SSID "Kantor-Lantai2" sering drop dan minta login ulang berkali-kali sejak kemarin pagi.', 'Tinggi', 'In Progress', NULL, NULL, 0, '2026-11-11 13:00:00', '2026-11-11 10:15:00', NULL, NULL, NULL, NULL, '2026-11-11 09:00:00', '2026-11-11 10:15:00'),
(3, 'TKT-20261111-0002', 6, 4, 3, 'Lupa Kata Sandi Akun Portal SIMPEG', 'Akun kepegawaian terkunci setelah salah memasukkan kata sandi 3 kali. Mohon bantuan reset password.', 'Urgent', 'Closed', NULL, NULL, 0, '2026-11-11 11:30:00', '2026-11-11 10:45:00', '2026-11-11 11:10:00', '2026-11-11 11:20:00', 5, 'Sangat responsif dan langsung bisa dipakai kembali. Mantap!', '2026-11-11 10:30:00', '2026-11-11 11:20:00'),
(4, 'TKT-20261112-0001', 4, 1, 3, 'Komputer Meja Kasir Tiba-Tiba Mati Sendiri (Blue Screen)', 'Layar mendadak biru dengan kode MEMORY_MANAGEMENT saat sedang merekap data nota kasir.', 'Urgent', 'In Progress', NULL, NULL, 0, '2026-11-12 09:30:00', '2026-11-12 08:50:00', NULL, NULL, NULL, NULL, '2026-11-12 08:30:00', '2026-11-12 08:50:00'),
(5, 'TKT-20261112-0002', 5, 2, 2, 'Aplikasi SIKD Tidak Bisa Dibuka Muncul Error Database Client', 'Saat klik shortcut SIKD muncul notifikasi error "Unable to connect to service host 192.168.1.10".', 'Tinggi', 'Waiting for User', NULL, NULL, 0, '2026-11-12 14:00:00', '2026-11-12 11:15:00', NULL, NULL, NULL, NULL, '2026-11-12 10:00:00', '2026-11-12 11:15:00'),
(6, 'TKT-20261112-0003', 6, 5, NULL, 'Kapasitas Mailbox Email Resmi Menipis (Hampir Penuh)', 'Muncul peringatan kuota storage email dinas tersisa 5%. Mohon bantuan penambahan kuota penyimpanan.', 'Rendah', 'Open', NULL, NULL, 0, '2026-11-13 11:00:00', NULL, NULL, NULL, NULL, NULL, '2026-11-12 11:00:00', '2026-11-12 11:00:00'),
(7, 'TKT-20261112-0004', 4, 1, 2, 'Kabel LAN Meja 4 Rusak Terjepit Pintu Lemari', 'Koneksi ethernet terputus karena jaket kabel LAN terkoyak dan pin RJ45 patah.', 'Sedang', 'Reopened', NULL, NULL, 0, '2026-11-12 20:00:00', '2026-11-12 13:00:00', NULL, NULL, NULL, NULL, '2026-11-12 12:00:00', '2026-11-12 14:30:00'),
(8, 'TKT-20261108-0001', 5, 7, 2, 'Permintaan Pemasangan Aplikasi PDF Editor Resmi', 'Memerlukan aplikasi editor dokumen untuk paraf digital berkas kepegawaian.', 'Rendah', 'Closed', NULL, NULL, 0, '2026-11-09 10:00:00', '2026-11-08 11:30:00', '2026-11-08 16:00:00', '2026-11-08 17:00:00', 4, 'Sudah terpasang dan berfungsi dengan baik.', '2026-11-08 10:00:00', '2026-11-08 17:00:00');

-- 4. PERCAKAPAN CONTOH (Termasuk Balasan Publik & Catatan Internal IT Support)
INSERT INTO `ticket_messages` (`ticket_id`, `user_id`, `message`, `is_internal`, `created_at`) VALUES
-- Tiket 1 (Printer)
(1, 2, 'Halo Pak Dedi, teknisi akan segera meluncur ke Ruang Keuangan membawa pickup roller pengganti.', 0, '2026-11-10 09:30:00'),
(1, 2, 'Catatan teknisi: Roll penarik aus karena penggunaan kertas tebal 100gsm. Sudah diganti part nomor sparepart EP-3200.', 1, '2026-11-10 14:10:00'),
(1, 2, 'Pergantian komponen roll telah selesai dan dicoba cetak 20 lembar lancar. Tiket kami ubah ke Resolved ya.', 0, '2026-11-10 14:15:00'),
(1, 4, 'Terima kasih Mas Budi, printer sudah normal kembali.', 0, '2026-11-10 14:30:00'),

-- Tiket 2 (Wi-Fi)
(2, 2, 'Selamat pagi Bu Rina, Access Point Lantai 2 sedang kami restart dan kami pantau channel interference frekuensinya.', 0, '2026-11-11 10:15:00'),
(2, 2, 'Catatan internal: AP Ubiquiti lantai 2 mengalami beban 68 client serentak. Rencana pasang tambahan 1 AP di koridor barat.', 1, '2026-11-11 10:20:00'),

-- Tiket 3 (Reset Password SIMPEG)
(3, 3, 'Halo Mas Fajar, kata sandi sementara akun SIMPEG Anda telah di-generate: "Pontianak2026!". Harap segera ubah setelah login pertama kali.', 0, '2026-11-11 10:45:00'),
(3, 6, 'Sudah dicoba dan sukses login. Terima kasih banyak Mbak Siti!', 0, '2026-11-11 11:10:00'),

-- Tiket 4 (Komputer Blue Screen)
(4, 3, 'Mohon jangan dinyalakan dulu komputernya Pak Dedi. Saya bawa modul RAM cadangan untuk pengujian.', 0, '2026-11-12 08:50:00'),
(4, 3, 'Catatan internal: Slot RAM 2 kemasukan debu tebal, saat ini sedang running MemTest86.', 1, '2026-11-12 09:10:00'),

-- Tiket 5 (Aplikasi SIKD)
(5, 2, 'Halo Bu Rina, apakah komputer Ibu terhubung ke jaringan LAN kabel atau Wi-Fi? Aplikasi SIKD mensyaratkan kabel LAN kantor.', 0, '2026-11-12 11:15:00'),

-- Tiket 7 (Kabel LAN Reopened)
(7, 2, 'Sudah dipasang konektor RJ45 baru tadi siang.', 0, '2026-11-12 13:00:00'),
(7, 4, '[REOPEN TIKET] Mas Budi, saat kabel digeser sedikit koneksinya kembali putus-nyambung. Sepertinya bagian tengah tembaganya juga patah di dalam.', 0, '2026-11-12 14:30:00');

-- 5. RIWAYAT AKTIVITAS AWAL (ticket_history)
INSERT INTO `ticket_history` (`ticket_id`, `user_id`, `action`, `keterangan`, `created_at`) VALUES
(1, 4, 'Dibuat', 'Tiket dibuat dengan prioritas Sedang', '2026-11-10 08:00:00'),
(1, 2, 'Penugasan Teknisi', 'Tiket diambil oleh Budi Santoso', '2026-11-10 09:30:00'),
(1, 2, 'Status Diubah', 'Status diubah menjadi Resolved', '2026-11-10 14:15:00'),
(1, 4, 'Rating Diberikan', 'Pemohon memberikan rating kepuasan 5/5 bintang', '2026-11-10 14:35:00'),
(2, 5, 'Dibuat', 'Tiket dibuat dengan prioritas Tinggi', '2026-11-11 09:00:00'),
(2, 2, 'Penugasan Teknisi', 'Tiket diambil oleh Budi Santoso', '2026-11-11 10:15:00'),
(3, 6, 'Dibuat', 'Tiket dibuat dengan prioritas Urgent', '2026-11-11 10:30:00'),
(3, 3, 'Penugasan Teknisi', 'Tiket diambil oleh Siti Rahmawati', '2026-11-11 10:45:00'),
(3, 3, 'Status Diubah', 'Status diubah menjadi Resolved', '2026-11-11 11:10:00'),
(3, 6, 'Tiket Ditutup', 'Tiket diselesaikan dan ditutup langsung oleh pemohon', '2026-11-11 11:20:00'),
(4, 4, 'Dibuat', 'Tiket dibuat dengan prioritas Urgent', '2026-11-12 08:30:00'),
(4, 3, 'Penugasan Teknisi', 'Tiket diambil oleh Siti Rahmawati', '2026-11-12 08:50:00'),
(5, 5, 'Dibuat', 'Tiket dibuat dengan prioritas Tinggi', '2026-11-12 10:00:00'),
(5, 2, 'Status Diubah', 'Status diubah menjadi Waiting for User', '2026-11-12 11:15:00'),
(6, 6, 'Dibuat', 'Tiket dibuat dengan prioritas Rendah', '2026-11-12 11:00:00'),
(7, 4, 'Dibuat', 'Tiket dibuat dengan prioritas Sedang', '2026-11-12 12:00:00'),
(7, 2, 'Status Diubah', 'Status diubah menjadi Resolved', '2026-11-12 13:00:00'),
(7, 4, 'Buka Ulang Tiket', 'Tiket dibuka kembali oleh pemohon karena kabel tengah patah', '2026-11-12 14:30:00');

-- 6. NOTIFIKASI AWAL (notifications)
INSERT INTO `notifications` (`user_id`, `ticket_id`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES
(4, 1, 'Status Tiket Diperbarui', 'Status tiket TKT-20261110-0001 diubah menjadi Resolved oleh Budi Santoso.', 'http://localhost/ticketing/pages/ticket_detail.php?id=1', 1, '2026-11-10 14:15:00'),
(5, 2, 'Balasan dari IT Support', 'Teknisi Budi Santoso membalas tiket TKT-20261111-0001', 'http://localhost/ticketing/pages/ticket_detail.php?id=2', 0, '2026-11-11 10:15:00'),
(2, 7, 'Tiket Dibuka Kembali!', 'Pemohon membuka kembali tiket TKT-20261112-0004.', 'http://localhost/ticketing/pages/ticket_detail.php?id=7', 0, '2026-11-12 14:30:00');
