# 🎫 HelpDesk IT Support - Web Ticketing System

Sistem Informasi Manajemen Tiket Bantuan & Dukungan Teknis IT (Helpdesk Ticketing System) berbasis **PHP 8 Native**, **MySQL/MariaDB via PDO**, **Tailwind CSS CDN**, dan **Vanilla JavaScript**.

Aplikasi dirancang lengkap dan siap digunakan untuk kebutuhan operasional kantor/instansi pemerintah/perusahaan, serta sangat mudah di-deploy di XAMPP, Laragon, Docker, maupun hosting Linux.

---

## 🚀 Fitur Unggulan

1. **Multi-Peran dengan Kontrol Hak Akses Ketat (RBAC):**
   - **User (Pegawai):** Registrasi mandiri, membuat tiket bantuan baru, memantau kemajuan, berinteraksi via percakapan, melampirkan berkas, menutup/membuka ulang tiket, dan memberi rating kepuasan (1-5 bintang). Hanya dapat melihat tiket miliknya sendiri.
   - **IT Support (Teknisi):** Melihat seluruh tiket masuk, mengambil penugasan (*assign* mandiri atau ditugaskan rekan), memperbarui status & prioritas, membalas percakapan publik ke pemohon, serta menulis **Catatan Internal** (rahasia teknisi, tidak terlihat oleh user).
   - **Admin (Administrator):** Memiliki semua hak IT Support ditambah manajemen data master pengguna (tambah, aktif/nonaktifkan akun, reset password), manajemen kategori tiket, statistik waktu respon, dan **Ekspor Laporan ke format CSV (Excel-Friendly)**.

2. **Percakapan Interaktif (Chat In-Ticket):**
   - Tampilan chat modern dua sisi (user & teknisi).
   - Dilengkapi **Auto-Refresh via Fetch API setiap 10 detik** tanpa memuat ulang (*reload*) halaman.
   - Pembeda visual yang tegas antara pesan publik dan catatan internal IT Support.

3. **Service Level Agreement (SLA) & Overdue Warning:**
   - **Urgent:** Target respon 1 Jam (indikator merah & berkedip jika terlambat).
   - **Tinggi:** Target respon 4 Jam.
   - **Sedang:** Target respon 8 Jam.
   - **Rendah:** Target respon 24 Jam.
   - Penghitungan otomatis `first_response_at` saat teknisi pertama kali membalas publik.

4. **Otomasi Auto-Close (Tanpa Cronjob):**
   - Tiket dengan status `Resolved` yang tidak mendapat balasan lebih dari 3 hari akan secara otomatis ditutup (`Closed`) oleh sistem saat halaman dashboard diakses.

5. **Notifikasi In-App Real-Time:**
   - Ikon lonceng notifikasi interaktif dengan badge hitung pesan yang belum dibaca (*unread counter*).

6. **Pencarian, Filter Lengkap & Pagination:**
   - Pencarian berdasarkan nomor tiket, nama pemohon, atau isi masalah.
   - Filter multi-kriteria: Status, Prioritas, Kategori masalah, dan Teknisi pengampu.

7. **Standar Keamanan Tinggi:**
   - **SQL Injection Prevention:** 100% prepared statements PDO (`PDO::ATTR_EMULATE_PREPARES => false`).
   - **XSS Prevention:** Sanitasi ketat fungsi `e()` (`htmlspecialchars` UTF-8 ENT_QUOTES) pada seluruh output dinamis.
   - **CSRF Protection:** Token acak unik dengan `hash_equals()` pada setiap form dan request AJAX.
   - **Brute-Force Login Protection:** Pembatasan maksimal 5 percobaan gagal per alamat IP dengan penguncian sementara selama 15 menit.
   - **Secure File Upload:** Pengecekan ukuran berkas (maks 5MB), validasi ekstensi berkas, inspeksi MIME-Type riil via `finfo`, penggantian nama file ke string acak hexadecimal unik, serta proteksi file `.htaccess` yang melarang eksekusi script PHP di direktori `uploads/`.

---

## 🛠️ Persyaratan Sistem

- PHP versi 8.0, 8.1, 8.2, 8.3, atau lebih baru (Ekstensi aktif: `pdo_mysql`, `fileinfo`).
- Database MySQL 5.7+ atau MariaDB 10.3+.
- Web Server Apache / Nginx (XAMPP / Laragon).
- Koneksi internet untuk memuat Tailwind CSS CDN pada peramban (*browser*).

---

## 📦 Petunjuk Pemasangan di XAMPP (Localhost)

1. **Salin Folder Proyek:**
   - Ekstrak berkas `ticketing.zip` ke dalam direktori `htdocs` XAMPP Anda:
     ```text
     C:\xampp\htdocs\ticketing\
     ```
   - Pastikan struktur direktori di dalamnya memuat folder `config`, `includes`, `pages`, `api`, `uploads`, dan berkas `database.sql`.

2. **Jalankan Apache & MySQL:**
   - Buka aplikasi **XAMPP Control Panel**.
   - Klik tombol **Start** pada modul **Apache** dan **MySQL**.

3. **Impor Database:**
   - Buka browser dan akses **phpMyAdmin**: [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
   - Buat database baru bernama: `ticketing_db`
   - Pilih tab **Import** (Impor), klik **Choose File**, lalu pilih berkas `database.sql` yang berada di dalam folder `ticketing/`.
   - Klik tombol **Import / Go** di bagian bawah halaman hingga muncul pesan sukses.

4. **Konfigurasi Koneksi (Bila Diperlukan):**
   - Berkas konfigurasi berada di: `config/config.php`
   - Pengaturan default sudah disesuaikan untuk XAMPP standar:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_PORT', '3306');
     define('DB_NAME', 'ticketing_db');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     ```
   - `BASE_URL` dideteksi secara otomatis dan portabel, sehingga langsung cocok diakses di [http://localhost/ticketing](http://localhost/ticketing).

5. **Akses Aplikasi:**
   - Buka peramban (Chrome, Edge, Firefox) dan buka tautan:
     👉 **[http://localhost/ticketing](http://localhost/ticketing)**

---

## 👥 Akun Demo Pengujian

Semua akun contoh di bawah ini menggunakan kata sandi yang sama:
🔑 **Kata Sandi (Password):** `password123`

| Peran (Role) | Nama | Alamat Email | Hak Akses Utama |
| :--- | :--- | :--- | :--- |
| **Admin** | Kevin Pratama | `admin@ticketing.local` | Akses penuh, kelola pengguna & kategori, statistik, ekspor CSV |
| **IT Support 1** | Budi Santoso | `support1@ticketing.local` | Ambil tiket, ubah status/prioritas, chat publik, catatan internal |
| **IT Support 2** | Siti Rahmawati | `support2@ticketing.local` | Tangani tiket, pemantauan antrian masuk, catatan teknis |
| **User (Pegawai 1)** | Dedi Kurniawan | `user1@ticketing.local` | Buat tiket baru, pantau tiket miliknya, beri rating kepuasan |
| **User (Pegawai 2)** | Rina Anggraini | `user2@ticketing.local` | Buat tiket kendala jaringan & software |
| **User (Pegawai 3)** | Fajar Hidayat | `user3@ticketing.local` | Buat tiket reset sandi akun |

*(Pada halaman Login juga telah disediakan tombol klik cepat untuk langsung mengisi email demo di atas).*

---

## 📂 Struktur Direktori Proyek

```text
ticketing/
├── .gitignore              # Konfigurasi ignore git untuk uploads & file temporer
├── README.md               # Dokumentasi lengkap & panduan instalasi
├── database.sql            # Skema DDL tabel + Seeding data awal lengkap
├── index.php               # Router utama (Redirect otomatis ke dashboard/login)
├── config/
│   └── config.php          # Konfigurasi database PDO, path, SLA & URL dinamis
├── includes/
│   ├── auth.php            # Otentikasi sesi, pengecekan role & proteksi brute force
│   ├── csrf.php            # Token CSRF generator & validator
│   ├── functions.php       # Helper format tanggal, upload, SLA, auto-close, badge
│   ├── header.php          # Header HTML, Navbar, indikator notifikasi in-app
│   └── footer.php          # Footer HTML & metadata
├── pages/
│   ├── 403.php             # Tampilan ramah akses ditolak (Unauthorized)
│   ├── login.php           # Halaman masuk sistem dengan proteksi serangan
│   ├── register.php        # Halaman pendaftaran mandiri pegawai
│   ├── logout.php          # Pengakhiran sesi aman
│   ├── dashboard.php       # Dashboard analitik adaptif sesuai peran
│   ├── tickets.php         # Daftar tiket, pencarian, multi-filter & pagination
│   ├── ticket_create.php   # Formulir permohonan tiket bantuan baru
│   ├── ticket_detail.php   # Detail tiket, riwayat aktivitas, live chat, rating
│   ├── users.php           # Modul manajemen pengguna (Khusus Admin)
│   ├── categories.php      # Modul manajemen kategori masalah (Khusus Admin)
│   └── export_csv.php      # Modul ekspor laporan tiket ke CSV Excel
├── api/
│   └── messages.php        # REST endpoint untuk pengiriman & auto-refresh chat
├── assets/
│   ├── css/
│   │   └── custom.css      # Styling khusus scrollbar & elemen kustom
│   └── js/
│       └── app.js          # JavaScript pendukung
└── uploads/
    ├── .htaccess           # Keamanan anti-eksekusi file PHP di folder unggahan
    └── index.php           # Mencegah directory listing
```

---

## 📝 Catatan Asumsi Teknis (Design Decisions)

1. **Kompatibilitas GitHub & Hosting:**
   - File `.gitignore` sudah disiapkan agar folder `uploads/` tidak membocorkan dokumen pengguna ke repository publik, namun tetap menyertakan proteksi `.htaccess` dan `index.php`.
   - `BASE_URL` dikonfigurasi dinamis berbasis `$_SERVER['HTTP_HOST']` dan path direktori, sehingga jika repository di-clone ke nama folder lain (misal `/it-helpdesk` atau di-host di domain root), seluruh routing URL tetap berfungsi tanpa perlu edit manual.
2. **Kemandirian Tanpa Background Worker (Daemon/Cron):**
   - Mekanisme **Auto-Close tiket Resolved 3 hari** diimplementasikan secara cerdas lewat pemanggilan fungsi non-blocking pada saat halaman dashboard dibuka (`run_auto_close_tickets()`), sehingga aplikasi dapat berjalan 100% mandiri di XAMPP Windows tanpa memerlukan Task Scheduler atau cronjob Linux tambahan.
3. **Penyimpanan Password:**
   - Semua kata sandi diamankan menggunakan fungsi enkripsi bawaan `password_hash()` dengan algoritma Bcrypt standar industri.

---
Dikembangkan dengan standar kualitas rekayasa perangkat lunak oleh Senior Full-Stack Engineer.
