<?php
// config/config.php - Konfigurasi Utama Web Ticketing IT Support

// Zona waktu default Indonesia
date_default_timezone_set('Asia/Jakarta');

// Konfigurasi Database (Mendukung override Environment Variable jika di-deploy ke Cloud/Docker)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'ticketing_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

// Deteksi Base URL secara dinamis dan portabel agar kompatibel di XAMPP localhost maupun subfolder git clone
if (!defined('BASE_URL')) {
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
                || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $protocol = $is_https ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Ambil path folder root aplikasi secara dinamis
    $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $app_dir = preg_replace('#/(pages|api|includes|config|assets|uploads).*$#i', '', $script_dir);
    $app_dir = rtrim($app_dir, '/');
    
    if (empty($app_dir) && (empty($_SERVER['SCRIPT_NAME']) || str_starts_with($_SERVER['SCRIPT_NAME'], '/ticketing'))) {
        $app_dir = '/ticketing';
    }
    
    define('BASE_URL', $protocol . $host . $app_dir);
}
define('APP_NAME', 'HelpDesk IT Support');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5 MB

// Daftar ekstensi & MIME yang diizinkan untuk lampiran
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf']);
define('ALLOWED_MIME_TYPES', [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'application/pdf'
]);

// SLA Respon dalam Jam berdasarkan Prioritas
define('SLA_HOURS', [
    'Urgent' => 1,
    'Tinggi' => 4,
    'Sedang' => 8,
    'Rendah' => 24
]);

// Durasi auto-close untuk tiket Resolved tanpa respon (hari)
define('AUTO_CLOSE_DAYS', 3);

// Batas percobaan login (Brute-force protection)
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 menit (900 detik)

/**
 * Mendapatkan koneksi PDO Database Singleton
 * @return PDO
 */
function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Tampilan ramah bila database belum diimpor / MySQL mati
            die('
            <!DOCTYPE html>
            <html lang="id">
            <head>
                <meta charset="UTF-8">
                <title>Koneksi Database Gagal</title>
                <script src="https://cdn.tailwindcss.com"></script>
            </head>
            <body class="bg-slate-100 flex items-center justify-center min-h-screen p-4">
                <div class="max-w-md w-full bg-white rounded-2xl shadow-xl p-6 text-center border border-red-100">
                    <div class="w-16 h-16 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <h1 class="text-xl font-bold text-slate-800 mb-2">Gagal Menghubungkan ke Database</h1>
                    <p class="text-sm text-slate-600 mb-4">Pastikan service MySQL di XAMPP Control Panel sudah berjalan (Start) dan database <span class="font-mono bg-slate-100 px-2 py-0.5 rounded text-red-600 font-semibold">ticketing_db</span> sudah diimpor via phpMyAdmin.</p>
                    <div class="text-xs bg-slate-50 p-3 rounded-lg text-left text-slate-500 font-mono mb-4 overflow-x-auto">
                        ' . htmlspecialchars($e->getMessage()) . '
                    </div>
                    <a href="javascript:location.reload()" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-medium px-5 py-2.5 rounded-xl transition">Coba Lagi</a>
                </div>
            </body>
            </html>
            ');
        }
    }
    return $pdo;
}
