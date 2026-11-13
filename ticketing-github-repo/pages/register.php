<?php
// pages/register.php - Registrasi Akun Pengguna Baru (Peran User)

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    redirect(BASE_URL . '/pages/dashboard.php');
}

$page_title = 'Pendaftaran Akun Pengguna';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $nama            = trim($_POST['nama'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $departemen      = trim($_POST['departemen'] ?? '');
    $no_hp           = trim($_POST['no_hp'] ?? '');
    $password        = $_POST['password'] ?? '';
    $password_confirm= $_POST['password_confirm'] ?? '';

    // Validasi server-side
    if (empty($nama) || empty($email) || empty($password)) {
        $error = 'Nama lengkap, email, dan kata sandi wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format alamat email tidak valid.';
    } elseif (strlen($password) < 6) {
        $error = 'Kata sandi minimal harus 6 karakter.';
    } elseif ($password !== $password_confirm) {
        $error = 'Konfirmasi kata sandi tidak cocok.';
    } else {
        $db = get_db();
        
        // Cek duplikasi email
        $stmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            $error = 'Alamat email tersebut sudah terdaftar dalam sistem.';
        } else {
            // Hash password aman
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $role = 'User'; // Registrasi mandiri selalu berperan User

            $insert = $db->prepare("INSERT INTO users (nama, email, password, role, departemen, no_hp, created_at) 
                                    VALUES (:nama, :email, :password, :role, :departemen, :no_hp, NOW())");
            $insert->execute([
                ':nama'       => $nama,
                ':email'      => $email,
                ':password'   => $hashed,
                ':role'       => $role,
                ':departemen' => $departemen,
                ':no_hp'      => $no_hp,
            ]);

            set_flash('success', 'Pendaftaran akun berhasil! Silakan login dengan email Anda.');
            redirect(BASE_URL . '/pages/login.php');
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

<div class="max-w-lg w-full bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-100 p-8 sm:p-10 my-8">
    <!-- Header Logo -->
    <div class="text-center mb-6">
        <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white flex items-center justify-center font-black mx-auto mb-3 shadow-md shadow-blue-500/20">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
        </div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Daftar Akun Baru</h1>
        <p class="text-sm text-slate-500 mt-1">Buat akun untuk mengajukan tiket bantuan IT</p>
    </div>

    <!-- Error message -->
    <?php if ($error): ?>
        <div class="mb-5 p-3.5 rounded-xl text-sm font-medium border bg-red-50 text-red-800 border-red-200 flex items-center gap-2">
            <svg class="w-5 h-5 shrink-0 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Form Registrasi -->
    <form method="POST" action="" class="space-y-4">
        <?= csrf_field() ?>

        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1" for="nama">Nama Lengkap *</label>
            <input type="text" id="nama" name="nama" required
                value="<?= e($_POST['nama'] ?? '') ?>"
                placeholder="cth: Ahmad Zaelani"
                class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent text-sm transition">
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1" for="email">Alamat Email *</label>
            <input type="email" id="email" name="email" required
                value="<?= e($_POST['email'] ?? '') ?>"
                placeholder="nama@kantor.go.id"
                class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent text-sm transition">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1" for="departemen">Divisi / Bagian</label>
                <input type="text" id="departemen" name="departemen"
                    value="<?= e($_POST['departemen'] ?? '') ?>"
                    placeholder="cth: Kepegawaian, Keuangan"
                    class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent text-sm transition">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1" for="no_hp">No. WhatsApp / HP</label>
                <input type="text" id="no_hp" name="no_hp"
                    value="<?= e($_POST['no_hp'] ?? '') ?>"
                    placeholder="0812xxxxxxxx"
                    class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent text-sm transition">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1" for="password">Kata Sandi *</label>
                <input type="password" id="password" name="password" required minlength="6"
                    placeholder="Minimal 6 karakter"
                    class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent text-sm transition">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1" for="password_confirm">Ulangi Sandi *</label>
                <input type="password" id="password_confirm" name="password_confirm" required minlength="6"
                    placeholder="Ketik ulang kata sandi"
                    class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent text-sm transition">
            </div>
        </div>

        <button type="submit"
            class="w-full py-3.5 px-4 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold rounded-xl shadow-lg shadow-blue-500/25 transition active:scale-[0.99] mt-3">
            Daftar Akun Sekarang
        </button>
    </form>

    <div class="mt-6 pt-5 border-t border-slate-100 text-center">
        <p class="text-sm text-slate-600">
            Sudah memiliki akun? 
            <a href="<?= BASE_URL ?>/pages/login.php" class="font-bold text-blue-600 hover:text-blue-700 hover:underline">Masuk di sini</a>
        </p>
    </div>
</div>

</body>
</html>
