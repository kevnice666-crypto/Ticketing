<?php
// pages/dashboard.php - Dashboard Statistik Berdasarkan Peran (User, IT Support, Admin)

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$user = current_user();
$db = get_db();
$page_title = 'Dashboard Utama';

// Fitur 9: Auto-close tiket status Resolved yang sudah lewat 3 hari tanpa respon
$auto_closed = run_auto_close_tickets($db);
if ($auto_closed > 0) {
    // Info pasif jika ada tiket yang ditutup
}

// Inisialisasi data dashboard per peran
$role = $user['role'];
$stats = [];
$recent_tickets = [];

if ($role === 'User') {
    // -------------------------------------------------------------
    // DASHBOARD USER: Tiket saya per status
    // -------------------------------------------------------------
    $stmt = $db->prepare("SELECT 
        COUNT(*) AS total,
        SUM(status = 'Open') AS open_count,
        SUM(status = 'In Progress') AS progress_count,
        SUM(status = 'Waiting for User') AS waiting_count,
        SUM(status = 'Resolved') AS resolved_count,
        SUM(status = 'Closed') AS closed_count,
        SUM(status = 'Reopened') AS reopened_count
        FROM tickets WHERE user_id = :uid");
    $stmt->execute([':uid' => $user['id']]);
    $stats = $stmt->fetch();

    // Tiket terbaru milik user
    $stmt_rec = $db->prepare("SELECT t.*, c.nama AS kategori_nama, a.nama AS agent_nama 
                             FROM tickets t 
                             LEFT JOIN categories c ON t.category_id = c.id 
                             LEFT JOIN users a ON t.assigned_to = a.id 
                             WHERE t.user_id = :uid 
                             ORDER BY t.updated_at DESC LIMIT 5");
    $stmt_rec->execute([':uid' => $user['id']]);
    $recent_tickets = $stmt_rec->fetchAll();

} elseif ($role === 'IT Support') {
    // -------------------------------------------------------------
    // DASHBOARD IT SUPPORT: Antrian baru, Tiket saya, Urgent/Terlambat
    // -------------------------------------------------------------
    // Antrian tiket baru (Open & belum ada teknisi)
    $q_queue = $db->query("SELECT COUNT(*) FROM tickets WHERE status = 'Open' AND assigned_to IS NULL")->fetchColumn();
    
    // Tiket yang ditugaskan ke saya dan masih aktif
    $stmt_my = $db->prepare("SELECT COUNT(*) FROM tickets WHERE assigned_to = :aid AND status NOT IN ('Closed', 'Resolved')");
    $stmt_my->execute([':aid' => $user['id']]);
    $my_active = $stmt_my->fetchColumn();

    // Tiket Urgent yang aktif
    $urgent_active = $db->query("SELECT COUNT(*) FROM tickets WHERE prioritas = 'Urgent' AND status NOT IN ('Closed', 'Resolved')")->fetchColumn();

    // Tiket yang sudah lewat SLA dan belum direspons
    $overdue_count = $db->query("SELECT COUNT(*) FROM tickets WHERE status NOT IN ('Closed', 'Resolved') AND first_response_at IS NULL AND NOW() > sla_due_at")->fetchColumn();

    $stats = [
        'unassigned_queue' => (int)$q_queue,
        'my_tickets'       => (int)$my_active,
        'urgent_count'     => (int)$urgent_active,
        'overdue_count'    => (int)$overdue_count,
    ];

    // Tiket yang perlu tindakan segera (Urgent / Overdue / Antrian baru)
    $recent_tickets = $db->query("SELECT t.*, u.nama AS pemohon_nama, c.nama AS kategori_nama, a.nama AS agent_nama 
                                  FROM tickets t 
                                  JOIN users u ON t.user_id = u.id 
                                  LEFT JOIN categories c ON t.category_id = c.id 
                                  LEFT JOIN users a ON t.assigned_to = a.id 
                                  WHERE t.status NOT IN ('Closed')
                                  ORDER BY (t.prioritas = 'Urgent') DESC, (NOW() > t.sla_due_at AND t.first_response_at IS NULL) DESC, t.created_at DESC 
                                  LIMIT 7")->fetchAll();

} elseif ($role === 'Admin') {
    // -------------------------------------------------------------
    // DASHBOARD ADMIN: Jumlah per status/kategori, SLA, Rata-rata Rating, Waktu Selesai
    // -------------------------------------------------------------
    $total_tickets = $db->query("SELECT COUNT(*) FROM tickets")->fetchColumn();
    $total_users = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $open_tickets = $db->query("SELECT COUNT(*) FROM tickets WHERE status = 'Open'")->fetchColumn();
    $in_progress = $db->query("SELECT COUNT(*) FROM tickets WHERE status = 'In Progress'")->fetchColumn();
    $waiting_user = $db->query("SELECT COUNT(*) FROM tickets WHERE status = 'Waiting for User'")->fetchColumn();
    $resolved_tickets = $db->query("SELECT COUNT(*) FROM tickets WHERE status = 'Resolved'")->fetchColumn();
    $closed_tickets = $db->query("SELECT COUNT(*) FROM tickets WHERE status = 'Closed'")->fetchColumn();
    $reopened_tickets = $db->query("SELECT COUNT(*) FROM tickets WHERE status = 'Reopened'")->fetchColumn();

    // Rata-rata rating kepuasan (1-5)
    $avg_rating = $db->query("SELECT ROUND(AVG(rating), 2) FROM tickets WHERE rating IS NOT NULL")->fetchColumn() ?: 0;
    $total_rated = $db->query("SELECT COUNT(*) FROM tickets WHERE rating IS NOT NULL")->fetchColumn();

    // Rata-rata durasi penyelesaian tiket (dalam jam) dari dibuat sampai resolved_at
    $avg_resolve_time = $db->query("SELECT ROUND(AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)), 1) FROM tickets WHERE resolved_at IS NOT NULL")->fetchColumn() ?: 0;

    // Tiket overdue SLA
    $overdue_count = $db->query("SELECT COUNT(*) FROM tickets WHERE status NOT IN ('Closed', 'Resolved') AND first_response_at IS NULL AND NOW() > sla_due_at")->fetchColumn();

    $stats = [
        'total'            => (int)$total_tickets,
        'total_users'      => (int)$total_users,
        'open'             => (int)$open_tickets,
        'in_progress'      => (int)$in_progress,
        'waiting_user'     => (int)$waiting_user,
        'resolved'         => (int)$resolved_tickets,
        'closed'           => (int)$closed_tickets,
        'reopened'         => (int)$reopened_tickets,
        'avg_rating'       => $avg_rating,
        'total_rated'      => (int)$total_rated,
        'avg_resolve_time' => $avg_resolve_time,
        'overdue_count'    => (int)$overdue_count,
    ];

    // Distribusi per kategori
    $category_stats = $db->query("SELECT c.nama, COUNT(t.id) AS total 
                                  FROM categories c 
                                  LEFT JOIN tickets t ON c.id = t.category_id 
                                  GROUP BY c.id, c.nama 
                                  ORDER BY total DESC")->fetchAll();

    // Tiket terbaru keseluruhan
    $recent_tickets = $db->query("SELECT t.*, u.nama AS pemohon_nama, c.nama AS kategori_nama, a.nama AS agent_nama 
                                  FROM tickets t 
                                  JOIN users u ON t.user_id = u.id 
                                  LEFT JOIN categories c ON t.category_id = c.id 
                                  LEFT JOIN users a ON t.assigned_to = a.id 
                                  ORDER BY t.created_at DESC 
                                  LIMIT 7")->fetchAll();
}

include __DIR__ . '/../includes/header.php';
?>

<div class="py-6 space-y-6">
    <!-- Welcome Header Banner -->
    <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-slate-900 rounded-3xl p-6 sm:p-8 text-white shadow-xl shadow-blue-900/10 flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/10 backdrop-blur-md text-xs font-semibold text-blue-200 mb-2">
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                <span>Sistem Operasional Aktif</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Selamat Datang, <?= e($user['nama']) ?>!</h1>
            <p class="text-sm text-blue-100/90 mt-1 max-w-xl">
                <?php if ($role === 'User'): ?>
                    Pantau kemajuan perbaikan perangkat keras, kendala software, dan akses jaringan Anda di sini.
                <?php elseif ($role === 'IT Support'): ?>
                    Kelola antrian tiket pengguna, pantau tenggat SLA, dan selesaikan kendala teknis dengan cepat.
                <?php else: ?>
                    Ringkasan performa tim IT Support, waktu respon SLA, kepuasan pengguna, dan statistik operasional.
                <?php endif; ?>
            </p>
        </div>

        <div class="flex items-center gap-3">
            <?php if ($role === 'User'): ?>
                <a href="<?= BASE_URL ?>/pages/ticket_create.php" class="px-5 py-3 bg-white text-blue-700 hover:bg-blue-50 font-bold text-sm rounded-2xl shadow-lg transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                    Buat Tiket Baru
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/pages/export_csv.php" class="px-5 py-3 bg-white/10 hover:bg-white/20 text-white font-bold text-sm rounded-2xl backdrop-blur-md transition flex items-center gap-2 border border-white/20">
                    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Ekspor CSV
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- STATISTIK KARTU SESUAI PERAN -->
    <?php if ($role === 'User'): ?>
        <!-- USER METRICS -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-sm">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Total Tiket</span>
                <span class="text-2xl font-black text-slate-800 mt-1 block"><?= $stats['total'] ?? 0 ?></span>
            </div>
            <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-sm">
                <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-600 block">Open</span>
                <span class="text-2xl font-black text-emerald-600 mt-1 block"><?= $stats['open_count'] ?? 0 ?></span>
            </div>
            <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-sm">
                <span class="text-[11px] font-bold uppercase tracking-wider text-blue-600 block">In Progress</span>
                <span class="text-2xl font-black text-blue-600 mt-1 block"><?= $stats['progress_count'] ?? 0 ?></span>
            </div>
            <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-sm">
                <span class="text-[11px] font-bold uppercase tracking-wider text-amber-600 block">Perlu Respon</span>
                <span class="text-2xl font-black text-amber-600 mt-1 block"><?= $stats['waiting_count'] ?? 0 ?></span>
            </div>
            <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-sm">
                <span class="text-[11px] font-bold uppercase tracking-wider text-purple-600 block">Resolved</span>
                <span class="text-2xl font-black text-purple-600 mt-1 block"><?= $stats['resolved_count'] ?? 0 ?></span>
            </div>
            <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-sm">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 block">Closed</span>
                <span class="text-2xl font-black text-slate-600 mt-1 block"><?= $stats['closed_count'] ?? 0 ?></span>
            </div>
        </div>

    <?php elseif ($role === 'IT Support'): ?>
        <!-- IT SUPPORT METRICS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400 block">Antrian Tiket Baru</span>
                    <span class="text-3xl font-black text-slate-800 mt-1 block"><?= $stats['unassigned_queue'] ?></span>
                    <span class="text-[11px] text-slate-500 mt-0.5 block">Belum ada teknisi penanggungjawab</span>
                </div>
                <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-blue-600 block">Tiket Ditugaskan ke Saya</span>
                    <span class="text-3xl font-black text-blue-600 mt-1 block"><?= $stats['my_tickets'] ?></span>
                    <span class="text-[11px] text-slate-500 mt-0.5 block">Sedang dalam penanganan aktif</span>
                </div>
                <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-orange-600 block">Tiket Urgent Aktif</span>
                    <span class="text-3xl font-black text-orange-600 mt-1 block"><?= $stats['urgent_count'] ?></span>
                    <span class="text-[11px] text-slate-500 mt-0.5 block">SLA respon darurat 1 jam</span>
                </div>
                <div class="w-12 h-12 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-red-600 block">Terlambat SLA Respon</span>
                    <span class="text-3xl font-black text-red-600 mt-1 block"><?= $stats['overdue_count'] ?></span>
                    <span class="text-[11px] text-slate-500 mt-0.5 block">Melewati batas waktu SLA</span>
                </div>
                <div class="w-12 h-12 rounded-xl bg-red-50 text-red-600 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>

    <?php elseif ($role === 'Admin'): ?>
        <!-- ADMIN METRICS -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-sm">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Total Tiket</span>
                <span class="text-2xl font-black text-slate-800 mt-1 block"><?= $stats['total'] ?></span>
            </div>
            <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-sm">
                <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-600 block">Tiket Open</span>
                <span class="text-2xl font-black text-emerald-600 mt-1 block"><?= $stats['open'] ?></span>
            </div>
            <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-sm">
                <span class="text-[11px] font-bold uppercase tracking-wider text-blue-600 block">In Progress</span>
                <span class="text-2xl font-black text-blue-600 mt-1 block"><?= $stats['in_progress'] ?></span>
            </div>
            <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-sm">
                <span class="text-[11px] font-bold uppercase tracking-wider text-purple-600 block">Resolved</span>
                <span class="text-2xl font-black text-purple-600 mt-1 block"><?= $stats['resolved'] ?></span>
            </div>
            <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-sm">
                <span class="text-[11px] font-bold uppercase tracking-wider text-amber-600 block">Rata Rating</span>
                <span class="text-2xl font-black text-amber-600 mt-1 block">★ <?= $stats['avg_rating'] ?></span>
                <span class="text-[10px] text-slate-400"><?= $stats['total_rated'] ?> ulasan</span>
            </div>
            <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-sm">
                <span class="text-[11px] font-bold uppercase tracking-wider text-indigo-600 block">Rata Selesai</span>
                <span class="text-2xl font-black text-indigo-600 mt-1 block"><?= $stats['avg_resolve_time'] ?> Jam</span>
                <span class="text-[10px] text-slate-400">Mean time to resolve</span>
            </div>
        </div>

        <!-- Detail Kategori (Admin Only) -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm">
            <h3 class="text-xs font-black uppercase tracking-wider text-slate-800 mb-3">Distribusi Tiket per Kategori</h3>
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
                <?php foreach ($category_stats as $cs): ?>
                <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 text-center">
                    <span class="text-xs font-semibold text-slate-600 block truncate"><?= e($cs['nama']) ?></span>
                    <span class="text-lg font-black text-slate-800 mt-0.5 block"><?= $cs['total'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- TIKET TERBARU SECTION -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h2 class="text-base font-extrabold text-slate-900 tracking-tight">
                    <?= $role === 'User' ? 'Tiket Terkini Milik Anda' : ($role === 'IT Support' ? 'Antrian Prioritas IT Support' : 'Aktivitas Tiket Terbaru') ?>
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">Pantau respon dan update percakapan terakhir</p>
            </div>
            <a href="<?= BASE_URL ?>/pages/tickets.php" class="text-xs font-bold text-blue-600 hover:underline flex items-center gap-1">
                <span>Lihat Semua Tiket</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

        <?php if (empty($recent_tickets)): ?>
            <div class="p-8 text-center text-slate-400 text-xs">
                Belum ada tiket pada antrian saat ini.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/70 border-b border-slate-200 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                            <th class="py-3 px-4">No. Tiket</th>
                            <th class="py-3 px-4">Judul & Kategori</th>
                            <?php if ($role !== 'User'): ?>
                                <th class="py-3 px-4">Pemohon</th>
                                <th class="py-3 px-4">Teknisi</th>
                            <?php endif; ?>
                            <th class="py-3 px-4">Prioritas</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4">SLA Respon</th>
                            <th class="py-3 px-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <?php foreach ($recent_tickets as $t): 
                            $overdue = is_ticket_overdue($t);
                        ?>
                        <tr class="hover:bg-slate-50 transition <?= $overdue ? 'bg-red-50/30' : '' ?>">
                            <td class="py-3 px-4 font-mono font-bold text-xs text-blue-600 whitespace-nowrap">
                                <a href="<?= BASE_URL ?>/pages/ticket_detail.php?id=<?= $t['id'] ?>" class="hover:underline">
                                    <?= e($t['ticket_number']) ?>
                                </a>
                            </td>
                            <td class="py-3 px-4 max-w-xs">
                                <a href="<?= BASE_URL ?>/pages/ticket_detail.php?id=<?= $t['id'] ?>" class="font-bold text-slate-800 hover:text-blue-600 block truncate">
                                    <?= e($t['judul']) ?>
                                </a>
                                <span class="text-[11px] text-slate-400 font-medium"><?= e($t['kategori_nama'] ?? 'Lainnya') ?></span>
                            </td>
                            <?php if ($role !== 'User'): ?>
                                <td class="py-3 px-4 whitespace-nowrap text-xs font-semibold text-slate-700">
                                    <?= e($t['pemohon_nama'] ?? '-') ?>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap text-xs font-semibold text-slate-700">
                                    <?= e($t['agent_nama'] ?? '<span class="text-amber-600 font-semibold">Belum</span>') ?>
                                </td>
                            <?php endif; ?>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <?= get_priority_badge($t['prioritas']) ?>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <?= get_status_badge($t['status']) ?>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap text-xs">
                                <?php if ($overdue): ?>
                                    <span class="text-red-700 font-black bg-red-100 px-2 py-0.5 rounded text-[11px] animate-pulse">Terlambat SLA!</span>
                                <?php elseif (!empty($t['first_response_at'])): ?>
                                    <span class="text-emerald-700 font-medium">Terespons</span>
                                <?php else: ?>
                                    <span class="text-slate-500 font-medium"><?= date('H:i', strtotime($t['sla_due_at'])) ?> WIB</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                <a href="<?= BASE_URL ?>/pages/ticket_detail.php?id=<?= $t['id'] ?>" class="px-3 py-1 bg-slate-100 hover:bg-blue-600 hover:text-white text-slate-700 rounded-lg text-xs font-bold transition">
                                    Buka
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
