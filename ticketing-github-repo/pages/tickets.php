<?php
// pages/tickets.php - Daftar Semua Tiket dengan Pencarian, Filter Multi-Kriteria & Pagination

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$user = current_user();
$db = get_db();
$page_title = 'Daftar Tiket Dukungan IT';

// Jalankan auto-close otomatis tiket Resolved > 3 hari
run_auto_close_tickets($db);

// Filter & Parameter
$search      = trim($_GET['search'] ?? '');
$status      = trim($_GET['status'] ?? '');
$prioritas   = trim($_GET['prioritas'] ?? '');
$category_id = (int)($_GET['category_id'] ?? 0);
$agent_id    = (int)($_GET['agent_id'] ?? 0);
$page        = max(1, (int)($_GET['page'] ?? 1));
$limit       = 10;
$offset      = ($page - 1) * $limit;

// Bangun Klausa Query
$where = [];
$params = [];

// Hak akses: Jika User biasa, hanya tiket miliknya
if ($user['role'] === 'User') {
    $where[] = "t.user_id = :uid";
    $params[':uid'] = $user['id'];
}

// Filter Pencarian kata kunci (nomor tiket, judul, deskripsi)
if (!empty($search)) {
    $where[] = "(t.ticket_number LIKE :kw OR t.judul LIKE :kw OR t.deskripsi LIKE :kw OR u.nama LIKE :kw)";
    $params[':kw'] = '%' . $search . '%';
}

// Filter Status
if (!empty($status)) {
    $where[] = "t.status = :status";
    $params[':status'] = $status;
}

// Filter Prioritas
if (!empty($prioritas)) {
    $where[] = "t.prioritas = :prioritas";
    $params[':prioritas'] = $prioritas;
}

// Filter Kategori
if ($category_id > 0) {
    $where[] = "t.category_id = :cat_id";
    $params[':cat_id'] = $category_id;
}

// Filter Agent yang menangani
if ($agent_id > 0) {
    $where[] = "t.assigned_to = :agent_id";
    $params[':agent_id'] = $agent_id;
}

$where_clause = empty($where) ? "" : "WHERE " . implode(" AND ", $where);

// Hitung Total Data untuk Pagination
$count_sql = "SELECT COUNT(*) FROM tickets t 
              JOIN users u ON t.user_id = u.id 
              $where_clause";
$stmt_count = $db->prepare($count_sql);
$stmt_count->execute($params);
$total_tickets = (int)$stmt_count->fetchColumn();
$total_pages = max(1, ceil($total_tickets / $limit));

// Ambil Data Tiket
$sql = "SELECT t.*, 
               u.nama AS pemohon_nama, u.email AS pemohon_email, u.departemen AS pemohon_dept,
               c.nama AS kategori_nama,
               a.nama AS agent_nama
        FROM tickets t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN categories c ON t.category_id = c.id
        LEFT JOIN users a ON t.assigned_to = a.id
        $where_clause
        ORDER BY 
            CASE 
                WHEN t.status = 'Open' THEN 1
                WHEN t.status = 'In Progress' THEN 2
                WHEN t.status = 'Waiting for User' THEN 3
                WHEN t.status = 'Reopened' THEN 4
                WHEN t.status = 'Resolved' THEN 5
                ELSE 6 
            END ASC,
            t.created_at DESC
        LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$tickets = $stmt->fetchAll();

// Data master untuk dropdown filter
$all_categories = $db->query("SELECT id, nama FROM categories WHERE is_active = 1 ORDER BY urutan ASC")->fetchAll();
$all_agents = $db->query("SELECT id, nama FROM users WHERE role IN ('IT Support', 'Admin') AND status = 'Aktif' ORDER BY nama ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="py-6">
    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Daftar Tiket Dukungan</h1>
            <p class="text-sm text-slate-500 mt-0.5">
                <?= $user['role'] === 'User' ? 'Kelola dan pantau seluruh status tiket bantuan yang pernah Anda ajukan' : 'Pusat tiket masuk, penugasan teknisi, dan pemantauan SLA operasional IT' ?>
            </p>
        </div>
        <div class="flex items-center gap-2.5">
            <?php if ($user['role'] === 'User'): ?>
            <a href="<?= BASE_URL ?>/pages/ticket_create.php" class="px-5 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold rounded-xl text-sm shadow-md shadow-blue-500/20 transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Buat Tiket Baru
            </a>
            <?php endif; ?>
            <?php if (in_array($user['role'], ['Admin', 'IT Support'])): ?>
            <a href="<?= BASE_URL ?>/pages/export_csv.php?<?= http_build_query($_GET) ?>" class="px-4 py-2.5 bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 font-semibold rounded-xl text-sm shadow-sm transition flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Ekspor CSV
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 mb-6">
        <form method="GET" action="" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3.5">
            <!-- Search Keyword -->
            <div class="lg:col-span-2">
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Cari Tiket / Masalah</label>
                <div class="relative">
                    <input type="text" name="search" value="<?= e($search) ?>" placeholder="No. Tiket, Judul, Pemohon..."
                        class="w-full pl-9 pr-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-600 text-xs">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>

            <!-- Status Filter -->
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-600 text-xs bg-white">
                    <option value="">Semua Status</option>
                    <?php foreach (['Open', 'In Progress', 'Waiting for User', 'Resolved', 'Closed', 'Reopened'] as $st): ?>
                        <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Prioritas Filter -->
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Prioritas</label>
                <select name="prioritas" class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-600 text-xs bg-white">
                    <option value="">Semua Prioritas</option>
                    <?php foreach (['Urgent', 'Tinggi', 'Sedang', 'Rendah'] as $pr): ?>
                        <option value="<?= $pr ?>" <?= $prioritas === $pr ? 'selected' : '' ?>><?= $pr ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Kategori Filter -->
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Kategori</label>
                <select name="category_id" class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-600 text-xs bg-white">
                    <option value="0">Semua Kategori</option>
                    <?php foreach ($all_categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $category_id === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- IT Support Filter (Admin & Support Only) -->
            <?php if (in_array($user['role'], ['Admin', 'IT Support'])): ?>
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Teknisi / Agent</label>
                <select name="agent_id" class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-600 text-xs bg-white">
                    <option value="0">Semua Teknisi</option>
                    <?php foreach ($all_agents as $ag): ?>
                        <option value="<?= $ag['id'] ?>" <?= $agent_id === (int)$ag['id'] ? 'selected' : '' ?>><?= e($ag['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
            <div></div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="lg:col-span-6 flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                <a href="<?= BASE_URL ?>/pages/tickets.php" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-900 rounded-lg hover:bg-slate-100 transition">
                    Reset Filter
                </a>
                <button type="submit" class="px-5 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-lg text-xs transition">
                    Terapkan Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Table Tiket Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
        <?php if (empty($tickets)): ?>
            <div class="p-12 text-center">
                <div class="w-16 h-16 bg-slate-100 text-slate-400 rounded-2xl flex items-center justify-center mx-auto mb-3">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                </div>
                <h3 class="text-base font-bold text-slate-800">Tidak ada tiket ditemukan</h3>
                <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">Tidak ada tiket yang sesuai dengan filter atau kata kunci yang Anda masukkan saat ini.</p>
                <?php if ($user['role'] === 'User'): ?>
                    <a href="<?= BASE_URL ?>/pages/ticket_create.php" class="inline-flex items-center gap-1.5 mt-4 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Buat Tiket Sekarang
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-200 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                            <th class="py-3.5 px-4">No. Tiket</th>
                            <th class="py-3.5 px-4">Judul & Kategori</th>
                            <?php if ($user['role'] !== 'User'): ?>
                            <th class="py-3.5 px-4">Pemohon</th>
                            <th class="py-3.5 px-4">Teknisi (Assign)</th>
                            <?php endif; ?>
                            <th class="py-3.5 px-4">Prioritas</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-4">SLA Respon</th>
                            <th class="py-3.5 px-4">Waktu</th>
                            <th class="py-3.5 px-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <?php foreach ($tickets as $t): 
                            $overdue = is_ticket_overdue($t);
                        ?>
                        <tr class="hover:bg-slate-50/80 transition group <?= $overdue ? 'bg-red-50/30' : '' ?>">
                            <!-- No Tiket -->
                            <td class="py-3.5 px-4 font-mono font-bold text-xs text-blue-600 whitespace-nowrap">
                                <a href="<?= BASE_URL ?>/pages/ticket_detail.php?id=<?= $t['id'] ?>" class="hover:underline">
                                    <?= e($t['ticket_number']) ?>
                                </a>
                            </td>

                            <!-- Judul & Kategori -->
                            <td class="py-3.5 px-4 max-w-xs sm:max-w-md">
                                <a href="<?= BASE_URL ?>/pages/ticket_detail.php?id=<?= $t['id'] ?>" class="font-bold text-slate-800 hover:text-blue-600 block line-clamp-1">
                                    <?= e($t['judul']) ?>
                                </a>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-[11px] font-semibold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-md">
                                        <?= e($t['kategori_nama'] ?? 'Lainnya') ?>
                                    </span>
                                    <?php if ($t['lampiran']): ?>
                                    <span class="text-[10px] text-slate-400 flex items-center gap-0.5">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                        Berkas
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Pemohon (Non-user view) -->
                            <?php if ($user['role'] !== 'User'): ?>
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <span class="font-semibold text-xs text-slate-800 block"><?= e($t['pemohon_nama']) ?></span>
                                <span class="text-[11px] text-slate-400 block"><?= e($t['pemohon_dept'] ?: 'Umum') ?></span>
                            </td>

                            <!-- Teknisi -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <?php if ($t['agent_nama']): ?>
                                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-700">
                                        <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                        <?= e($t['agent_nama']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-xs text-amber-600 font-semibold bg-amber-50 px-2 py-0.5 rounded-md">
                                        Belum Diambil
                                    </span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>

                            <!-- Prioritas -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <?= get_priority_badge($t['prioritas']) ?>
                            </td>

                            <!-- Status -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <?= get_status_badge($t['status']) ?>
                            </td>

                            <!-- SLA Respon Status -->
                            <td class="py-3.5 px-4 whitespace-nowrap text-xs">
                                <?php if (!empty($t['first_response_at'])): ?>
                                    <span class="text-emerald-700 font-medium flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        Terespons
                                    </span>
                                <?php elseif ($overdue): ?>
                                    <span class="text-red-700 font-bold bg-red-100 px-2 py-0.5 rounded-md flex items-center gap-1 w-fit animate-pulse">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        Terlambat SLA!
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-500 font-medium">
                                        Tenggat: <?= date('H:i', strtotime($t['sla_due_at'])) ?>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Waktu Dibuat -->
                            <td class="py-3.5 px-4 whitespace-nowrap text-xs text-slate-500">
                                <?= time_ago($t['created_at']) ?>
                            </td>

                            <!-- Aksi -->
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                <a href="<?= BASE_URL ?>/pages/ticket_detail.php?id=<?= $t['id'] ?>" 
                                    class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-50 hover:bg-blue-600 text-blue-700 hover:text-white rounded-lg text-xs font-bold transition">
                                    <span>Lihat</span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <?php if ($total_pages > 1): ?>
            <div class="px-6 py-4 border-t border-slate-200 bg-slate-50/50 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
                <div>
                    Menampilkan <strong><?= $offset + 1 ?></strong> - <strong><?= min($offset + $limit, $total_tickets) ?></strong> dari total <strong><?= $total_tickets ?></strong> tiket
                </div>
                <div class="flex items-center gap-1">
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg hover:bg-slate-100 font-medium text-slate-700">Sebelumnya</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                            class="px-3 py-1.5 rounded-lg font-bold <?= $i === $page ? 'bg-blue-600 text-white shadow-sm' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-100' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg hover:bg-slate-100 font-medium text-slate-700">Berikutnya</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
