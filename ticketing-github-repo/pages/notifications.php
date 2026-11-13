<?php
// pages/notifications.php - Daftar Notifikasi In-App Pengguna & Tandai Telah Dibaca

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$user = current_user();
$db = get_db();
$page_title = 'Pemberitahuan Sistem';

// Tandai semua sebagai telah dibaca jika ada aksi POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_all_read') {
        $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid")->execute([':uid' => $user['id']]);
        set_flash('success', 'Semua pemberitahuan telah ditandai sebagai dibaca.');
        redirect(BASE_URL . '/pages/notifications.php');
    }
}

// Ambil daftar notifikasi untuk user ini
$stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = :uid ORDER BY id DESC LIMIT 50");
$stmt->execute([':uid' => $user['id']]);
$notifications = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto py-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Pemberitahuan Sistem</h1>
            <p class="text-sm text-slate-500 mt-0.5">Pembaruan status tiket, balasan pesan masuk, dan penugasan teknisi</p>
        </div>

        <?php if (!empty($notifications)): ?>
        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="mark_all_read">
            <button type="submit" class="px-4 py-2 bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 text-xs font-bold rounded-xl shadow-sm transition flex items-center gap-1.5">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Tandai Semua Dibaca
            </button>
        </form>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 divide-y divide-slate-100 overflow-hidden">
        <?php if (empty($notifications)): ?>
            <div class="p-12 text-center">
                <div class="w-16 h-16 bg-slate-100 text-slate-400 rounded-2xl flex items-center justify-center mx-auto mb-3">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                </div>
                <h3 class="text-sm font-bold text-slate-800">Tidak ada notifikasi</h3>
                <p class="text-xs text-slate-500 mt-1">Anda belum memiliki pemberitahuan aktivitas tiket.</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $n): ?>
            <a href="<?= e($n['link']) ?>" class="flex items-start gap-4 p-4 hover:bg-slate-50 transition <?= !$n['is_read'] ? 'bg-blue-50/40' : '' ?>">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 <?= !$n['is_read'] ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 text-slate-500' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-2">
                        <h4 class="text-xs font-bold text-slate-900 truncate"><?= e($n['title']) ?></h4>
                        <span class="text-[11px] text-slate-400 shrink-0"><?= time_ago($n['created_at']) ?></span>
                    </div>
                    <p class="text-xs text-slate-600 mt-0.5"><?= e($n['message']) ?></p>
                </div>
                <?php if (!$n['is_read']): ?>
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-600 self-center shrink-0"></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
