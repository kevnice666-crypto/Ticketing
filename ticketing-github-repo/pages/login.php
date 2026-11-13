<?php
// pages/login.php - Halaman Login dengan Proteksi Brute-force & CSRF

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

// Jika sudah login, lempar langsung ke dashboard
if (is_logged_in()) {
    redirect(BASE_URL . '/pages/dashboard.php');
}

$page_title = 'Masuk Akun';
$error = '';
$ip = get_client_ip();
$attempt_status = check_login_attempts($ip);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if ($attempt_status['blocked']) {
        $minutes = ceil($attempt_status['remaining_seconds'] / 60);
        $error = "Terlalu banyak percobaan gagal. Akses dibatasi sementara selama {$minutes} menit.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Email dan kata sandi wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email tidak valid.';
        } else {
            $db = get_db();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if (isset($user['status']) && $user['status'] === 'Nonaktif') {
                    $error = 'Akun Anda telah dinonaktifkan oleh administrator.';
                } else {
                    // Berhasil login
                    login_user($user);
                    set_flash('success', "Selamat datang kembali, {$user['nama']}!");
                    redirect(BASE_URL . '/pages/dashboard.php');
                }
            } else {
                record_failed_login($ip, $email);
                $updated_status = check_login_attempts($ip);
                if ($updated_status['blocked']) {
                    $error = 'Terlalu banyak percobaan gagal. Akun/IP diblokir selama 15 menit.';
                } else {
                    $error = 'Email atau kata sandi salah. Silakan periksa kembali.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="flex items-center justify-center min-h-full p-4 antialiased">

<div class="max-w-md w-full bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-100 p-8 sm:p-10">
    <!-- Header Logo -->
    <div class="text-center mb-8">
        <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white flex items-center justify-center font-black mx-auto mb-3 shadow-lg shadow-blue-500/25">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        </div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">HelpDesk IT Support</h1>
        <p class="text-sm text-slate-500 mt-1">Silakan masuk menggunakan akun terdaftar</p>
    </div>

    <!-- Flash message -->
    <?php $flash = get_flash(); if ($flash): ?>
        <div class="mb-5 p-3.5 rounded-xl text-sm font-medium border bg-blue-50 text-blue-800 border-blue-200">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>

    <!-- Error message -->
    <?php if ($error): ?>
        <div class="mb-5 p-3.5 rounded-xl text-sm font-medium border bg-red-50 text-red-800 border-red-200 flex items-center gap-2">
            <svg class="w-5 h-5 shrink-0 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Form Login -->
    <form method="POST" action="" class="space-y-4">
        <?= csrf_field() ?>

        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5" for="email">Alamat Email</label>
            <input type="email" id="email" name="email" required autofocus
                value="<?= e($_POST['email'] ?? '') ?>"
                placeholder="nama@kantor.go.id"
                class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent text-sm transition">
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5" for="password">Kata Sandi</label>
            <input type="password" id="password" name="password" required
                placeholder="••••••••"
                class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent text-sm transition">
        </div>

        <button type="submit"
            class="w-full py-3.5 px-4 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold rounded-xl shadow-lg shadow-blue-500/25 hover:shadow-blue-500/35 transition active:scale-[0.99] mt-2">
            Masuk Sekarang
        </button>
    </form>

    <div class="mt-6 pt-6 border-t border-slate-100 text-center">
        <p class="text-sm text-slate-600">
            Belum punya akun? 
            <a href="<?= BASE_URL ?>/pages/register.php" class="font-bold text-blue-600 hover:text-blue-700 hover:underline">Daftar Akun Baru</a>
        </p>
    </div>

    <!-- Quick Demo Accounts Helper -->
    <div class="mt-8 bg-slate-50 border border-slate-200/80 rounded-2xl p-4 text-xs text-slate-600">
        <p class="font-bold text-slate-800 mb-2 flex items-center gap-1.5">
            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Akun Demo Pengujian (Password: <code class="font-mono bg-white px-1.5 py-0.5 rounded border border-slate-200">password123</code>):
        </p>
        <div class="space-y-1.5 text-[11px]">
            <div class="flex justify-between items-center py-0.5 border-b border-slate-200/50">
                <span class="font-medium">Administrator:</span>
                <button type="button" onclick="fillLogin('admin@ticketing.local')" class="font-mono text-blue-600 hover:underline">admin@ticketing.local</button>
            </div>
            <div class="flex justify-between items-center py-0.5 border-b border-slate-200/50">
                <span class="font-medium">IT Support 1:</span>
                <button type="button" onclick="fillLogin('support1@ticketing.local')" class="font-mono text-blue-600 hover:underline">support1@ticketing.local</button>
            </div>
            <div class="flex justify-between items-center py-0.5">
                <span class="font-medium">User (Pegawai 1):</span>
                <button type="button" onclick="fillLogin('user1@ticketing.local')" class="font-mono text-blue-600 hover:underline">user1@ticketing.local</button>
            </div>
        </div>
    </div>
</div>

<script>
function fillLogin(email) {
    document.getElementById('email').value = email;
    document.getElementById('password').value = 'password123';
    document.getElementById('password').focus();
}
</script>

</body>
</html>
