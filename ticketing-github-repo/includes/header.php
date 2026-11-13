<?php
// includes/header.php - Header HTML, Navbar, Badge Notifikasi In-App & Profil User

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/functions.php';

$current_user = current_user();
$page_title = $page_title ?? APP_NAME;
$unread_notifications_count = 0;

if ($current_user) {
    $db = get_db();
    $unread_notifications_count = get_unread_notification_count($db, $current_user['id']);
}
?>
<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> - <?= APP_NAME ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            500: '#22c55e',
                            600: '#16a34a',
                            700: '#15803d',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="flex flex-col min-h-full text-slate-800 antialiased">

<?php if ($current_user): ?>
<!-- Navigation Bar -->
<header class="sticky top-0 z-40 bg-white border-b border-slate-200/80 shadow-sm backdrop-blur-md bg-white/95">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <!-- Brand & Logo -->
            <div class="flex items-center gap-6">
                <a href="<?= BASE_URL ?>/pages/dashboard.php" class="flex items-center gap-2.5 group">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white flex items-center justify-center font-black shadow-md shadow-blue-500/20 group-hover:scale-105 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <div>
                        <span class="text-base font-extrabold text-slate-900 tracking-tight block leading-tight">HelpDesk <span class="text-blue-600">IT</span></span>
                        <span class="text-[10px] text-slate-400 font-semibold tracking-wider uppercase">Ticketing System</span>
                    </div>
                </a>

                <!-- Desktop Menu -->
                <nav class="hidden md:flex items-center gap-1 pl-4 border-l border-slate-200">
                    <a href="<?= BASE_URL ?>/pages/dashboard.php" class="px-3.5 py-2 rounded-lg text-sm font-semibold transition <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                        Dashboard
                    </a>
                    <a href="<?= BASE_URL ?>/pages/tickets.php" class="px-3.5 py-2 rounded-lg text-sm font-semibold transition <?= basename($_SERVER['PHP_SELF']) == 'tickets.php' ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                        Daftar Tiket
                    </a>
                    <?php if ($current_user['role'] === 'User'): ?>
                    <a href="<?= BASE_URL ?>/pages/ticket_create.php" class="px-3.5 py-2 rounded-lg text-sm font-semibold text-blue-600 hover:bg-blue-50 transition flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Buat Tiket
                    </a>
                    <?php endif; ?>

                    <?php if ($current_user['role'] === 'Admin'): ?>
                    <div class="relative group" id="adminMenuDropdown">
                        <button type="button" class="px-3.5 py-2 rounded-lg text-sm font-semibold transition flex items-center gap-1 <?= in_array(basename($_SERVER['PHP_SELF']), ['users.php', 'categories.php']) ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                            <span>Kelola Sistem</span>
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div class="absolute left-0 mt-1 w-48 bg-white rounded-xl shadow-lg border border-slate-100 py-1.5 hidden group-hover:block z-50">
                            <a href="<?= BASE_URL ?>/pages/users.php" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 hover:text-blue-600 font-medium">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                                Kelola Pengguna
                            </a>
                            <a href="<?= BASE_URL ?>/pages/categories.php" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 hover:text-blue-600 font-medium">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                                Kelola Kategori
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </nav>
            </div>

            <!-- Right Profile & Notifications -->
            <div class="flex items-center gap-3">
                <!-- Notifications Bell -->
                <a href="<?= BASE_URL ?>/pages/notifications.php" class="relative p-2 rounded-xl text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition" title="Notifikasi In-App">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    <?php if ($unread_notifications_count > 0): ?>
                    <span class="absolute top-1.5 right-1.5 flex h-4 min-w-[16px] px-1 items-center justify-center rounded-full bg-red-600 text-[10px] font-black text-white shadow-sm ring-2 ring-white">
                        <?= $unread_notifications_count > 99 ? '99+' : $unread_notifications_count ?>
                    </span>
                    <?php endif; ?>
                </a>

                <!-- User Profile Badge -->
                <div class="flex items-center gap-2.5 pl-3 border-l border-slate-200">
                    <div class="w-9 h-9 rounded-full bg-slate-200 flex items-center justify-center font-bold text-slate-700 text-sm overflow-hidden border border-slate-300">
                        <?= strtoupper(substr($current_user['nama'], 0, 1)) ?>
                    </div>
                    <div class="hidden sm:block text-left">
                        <span class="text-xs font-bold text-slate-900 block leading-tight"><?= e($current_user['nama']) ?></span>
                        <span class="text-[11px] font-medium text-slate-500 block">
                            <?php if ($current_user['role'] === 'Admin'): ?>
                                <span class="text-purple-600 font-semibold">Administrator</span>
                            <?php elseif ($current_user['role'] === 'IT Support'): ?>
                                <span class="text-blue-600 font-semibold">IT Support</span>
                            <?php else: ?>
                                <span class="text-emerald-600 font-semibold">User (Pegawai)</span>
                            <?php endif; ?>
                        </span>
                    </div>

                    <!-- Logout Button -->
                    <a href="<?= BASE_URL ?>/pages/logout.php" class="p-2 ml-1 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition" title="Logout Sesi">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>
<?php endif; ?>

<!-- Flash Message Container -->
<main class="flex-1">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
        <?php $flash = get_flash(); if ($flash): ?>
        <div class="mb-4 p-4 rounded-xl text-sm font-medium flex items-center gap-3 border shadow-sm transition
            <?php
            switch ($flash['type']) {
                case 'success':
                    echo 'bg-emerald-50 text-emerald-800 border-emerald-200';
                    break;
                case 'error':
                    echo 'bg-red-50 text-red-800 border-red-200';
                    break;
                case 'warning':
                    echo 'bg-amber-50 text-amber-800 border-amber-200';
                    break;
                default:
                    echo 'bg-blue-50 text-blue-800 border-blue-200';
            }
            ?>">
            <?php if ($flash['type'] === 'success'): ?>
                <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <?php elseif ($flash['type'] === 'error'): ?>
                <svg class="w-5 h-5 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            <?php elseif ($flash['type'] === 'warning'): ?>
                <svg class="w-5 h-5 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <?php else: ?>
                <svg class="w-5 h-5 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <?php endif; ?>
            <div class="flex-1"><?= e($flash['message']) ?></div>
        </div>
        <?php endif; ?>
    </div>
