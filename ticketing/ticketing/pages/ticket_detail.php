<?php
// pages/ticket_detail.php - Detail Lengkap Tiket, Percakapan Interaktif (Chat), Catatan Internal, Riwayat & Rating

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$user = current_user();
$db = get_db();

$ticket_id = (int)($_GET['id'] ?? 0);
if ($ticket_id <= 0) {
    set_flash('error', 'ID Tiket tidak valid.');
    redirect(BASE_URL . '/pages/tickets.php');
}

// Ambil data tiket beserta relasi
$stmt = $db->prepare("SELECT t.*, 
                             u.nama AS pemohon_nama, u.email AS pemohon_email, u.departemen AS pemohon_dept, u.no_hp AS pemohon_hp,
                             c.nama AS kategori_nama,
                             a.nama AS agent_nama, a.email AS agent_email
                      FROM tickets t
                      JOIN users u ON t.user_id = u.id
                      LEFT JOIN categories c ON t.category_id = c.id
                      LEFT JOIN users a ON t.assigned_to = a.id
                      WHERE t.id = :id LIMIT 1");
$stmt->execute([':id' => $ticket_id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    set_flash('error', 'Tiket tidak ditemukan dalam sistem.');
    redirect(BASE_URL . '/pages/tickets.php');
}

// Otorisasi: User biasa hanya boleh melihat tiket miliknya sendiri
if ($user['role'] === 'User' && (int)$ticket['user_id'] !== (int)$user['id']) {
    include __DIR__ . '/../pages/403.php';
    exit;
}

// Tandai notifikasi terkait tiket ini sebagai sudah dibaca untuk user yang sedang login
$db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND ticket_id = :tid")->execute([
    ':uid' => $user['id'],
    ':tid' => $ticket_id
]);

// Handle Tindakan POST (Status, Assign, Tutup, Reopen, Rating)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    // 1. Assign Tiket ke Diri Sendiri (IT Support / Admin)
    if ($action === 'assign_self' && in_array($user['role'], ['IT Support', 'Admin'])) {
        $stmt_up = $db->prepare("UPDATE tickets SET assigned_to = :agent_id, status = IF(status='Open', 'In Progress', status), updated_at = NOW() WHERE id = :id");
        $stmt_up->execute([':agent_id' => $user['id'], ':id' => $ticket_id]);
        add_ticket_history($db, $ticket_id, $user['id'], 'Penugasan Teknisi', "Tiket diambil oleh {$user['nama']}");
        set_flash('success', 'Anda telah mengambil penugasan tiket ini.');
        redirect(BASE_URL . "/pages/ticket_detail.php?id=" . $ticket_id);
    }

    // 2. Assign ke Teknisi Lain (Admin & IT Support)
    if ($action === 'assign_other' && in_array($user['role'], ['IT Support', 'Admin'])) {
        $new_agent_id = (int)($_POST['assigned_to'] ?? 0);
        $agent_name = "Belum Ditugaskan";
        if ($new_agent_id > 0) {
            $agent_row = $db->query("SELECT nama FROM users WHERE id = $new_agent_id")->fetch();
            $agent_name = $agent_row['nama'] ?? 'Teknisi';
            $stmt_up = $db->prepare("UPDATE tickets SET assigned_to = :agent_id, updated_at = NOW() WHERE id = :id");
            $stmt_up->execute([':agent_id' => $new_agent_id, ':id' => $ticket_id]);
            add_ticket_history($db, $ticket_id, $user['id'], 'Penugasan Teknisi', "Ditugaskan kepada {$agent_name}");
            create_notification($db, $new_agent_id, $ticket_id, 'Penugasan Tiket Baru', "Anda ditugaskan menangani tiket {$ticket['ticket_number']}", BASE_URL . "/pages/ticket_detail.php?id=" . $ticket_id);
        } else {
            $stmt_up = $db->prepare("UPDATE tickets SET assigned_to = NULL, updated_at = NOW() WHERE id = :id");
            $stmt_up->execute([':id' => $ticket_id]);
            add_ticket_history($db, $ticket_id, $user['id'], 'Penugasan Teknisi', "Penugasan teknisi dibatalkan (Unassigned)");
        }
        set_flash('success', "Penugasan teknisi berhasil diperbarui: $agent_name.");
        redirect(BASE_URL . "/pages/ticket_detail.php?id=" . $ticket_id);
    }

    // 3. Ubah Status Tiket (IT Support & Admin)
    if ($action === 'change_status' && in_array($user['role'], ['IT Support', 'Admin'])) {
        $new_status = $_POST['new_status'] ?? '';
        $valid_status = ['Open', 'In Progress', 'Waiting for User', 'Resolved', 'Closed'];
        if (in_array($new_status, $valid_status, true)) {
            $extra_sql = "";
            if ($new_status === 'Resolved' && empty($ticket['resolved_at'])) {
                $extra_sql = ", resolved_at = NOW()";
            }
            if ($new_status === 'Closed' && empty($ticket['closed_at'])) {
                $extra_sql .= ", closed_at = NOW()";
            }
            $stmt_up = $db->prepare("UPDATE tickets SET status = :status $extra_sql, updated_at = NOW() WHERE id = :id");
            $stmt_up->execute([':status' => $new_status, ':id' => $ticket_id]);

            add_ticket_history($db, $ticket_id, $user['id'], 'Status Diubah', "Status diubah menjadi {$new_status}");
            create_notification($db, (int)$ticket['user_id'], $ticket_id, 'Status Tiket Diperbarui', "Status tiket {$ticket['ticket_number']} diubah menjadi '{$new_status}' oleh IT Support.", BASE_URL . "/pages/ticket_detail.php?id=" . $ticket_id);

            set_flash('success', "Status tiket berhasil diubah menjadi {$new_status}.");
            redirect(BASE_URL . "/pages/ticket_detail.php?id=" . $ticket_id);
        }
    }

    // 4. Ubah Prioritas (IT Support & Admin)
    if ($action === 'change_priority' && in_array($user['role'], ['IT Support', 'Admin'])) {
        $new_prio = $_POST['new_priority'] ?? '';
        if (in_array($new_prio, ['Rendah', 'Sedang', 'Tinggi', 'Urgent'], true)) {
            $new_due = calculate_sla_due($new_prio, $ticket['created_at']);
            $stmt_up = $db->prepare("UPDATE tickets SET prioritas = :prio, sla_due_at = :due, updated_at = NOW() WHERE id = :id");
            $stmt_up->execute([':prio' => $new_prio, ':due' => $new_due, ':id' => $ticket_id]);
            add_ticket_history($db, $ticket_id, $user['id'], 'Prioritas Diubah', "Prioritas diubah menjadi {$new_prio} (Tenggat SLA baru disesuaikan)");
            set_flash('success', "Prioritas tiket berhasil diubah menjadi {$new_prio}.");
            redirect(BASE_URL . "/pages/ticket_detail.php?id=" . $ticket_id);
        }
    }

    // 5. Tutup Tiket Mandiri oleh User (Close Ticket)
    if ($action === 'user_close_ticket' && $user['role'] === 'User') {
        $stmt_up = $db->prepare("UPDATE tickets SET status = 'Closed', closed_at = NOW(), updated_at = NOW() WHERE id = :id");
        $stmt_up->execute([':id' => $ticket_id]);
        add_ticket_history($db, $ticket_id, $user['id'], 'Tiket Ditutup', 'Tiket diselesaikan dan ditutup langsung oleh pemohon');
        if ($ticket['assigned_to']) {
            create_notification($db, (int)$ticket['assigned_to'], $ticket_id, 'Tiket Ditutup Pemohon', "Pemohon telah menutup tiket {$ticket['ticket_number']}.", BASE_URL . "/pages/ticket_detail.php?id=" . $ticket_id);
        }
        set_flash('success', 'Tiket telah Anda tutup.');
        redirect(BASE_URL . "/pages/ticket_detail.php?id=" . $ticket_id);
    }

    // 6. Buka Ulang Tiket oleh User (Reopen Ticket)
    if ($action === 'user_reopen_ticket' && $user['role'] === 'User') {
        $alasan_reopen = trim($_POST['alasan_reopen'] ?? 'Masalah masih belum tuntas.');
        $stmt_up = $db->prepare("UPDATE tickets SET status = 'Reopened', updated_at = NOW() WHERE id = :id");
        $stmt_up->execute([':id' => $ticket_id]);

        add_ticket_history($db, $ticket_id, $user['id'], 'Buka Ulang Tiket', "Tiket dibuka kembali oleh pemohon. Catatan: $alasan_reopen");
        
        // Simpan alasan sebagai pesan percakapan
        $db->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, is_internal, created_at) VALUES (:tid, :uid, :msg, 0, NOW())")
           ->execute([':tid' => $ticket_id, ':uid' => $user['id'], ':msg' => "[REOPEN TIKET] " . $alasan_reopen]);

        if ($ticket['assigned_to']) {
            create_notification($db, (int)$ticket['assigned_to'], $ticket_id, 'Tiket Dibuka Kembali!', "Pemohon membuka kembali tiket {$ticket['ticket_number']}.", BASE_URL . "/pages/ticket_detail.php?id=" . $ticket_id);
        }
        set_flash('warning', 'Tiket telah dibuka kembali untuk ditangani lebih lanjut.');
        redirect(BASE_URL . "/pages/ticket_detail.php?id=" . $ticket_id);
    }

    // 7. Beri Rating & Ulasan Kepuasan (Rating 1 - 5)
    if ($action === 'submit_rating' && $user['role'] === 'User') {
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $feedback = trim($_POST['rating_feedback'] ?? '');

        $stmt_up = $db->prepare("UPDATE tickets SET rating = :rating, rating_feedback = :fb WHERE id = :id");
        $stmt_up->execute([':rating' => $rating, ':fb' => $feedback, ':id' => $ticket_id]);

        add_ticket_history($db, $ticket_id, $user['id'], 'Rating Diberikan', "Pemohon memberikan rating kepuasan {$rating}/5 bintang");
        if ($ticket['assigned_to']) {
            create_notification($db, (int)$ticket['assigned_to'], $ticket_id, 'Penilaian Layanan IT Masuk', "Pemohon memberikan rating {$rating}/5 untuk tiket {$ticket['ticket_number']}.", BASE_URL . "/pages/ticket_detail.php?id=" . $ticket_id);
        }
        set_flash('success', 'Terima kasih atas penilaian kepuasan yang Anda berikan!');
        redirect(BASE_URL . "/pages/ticket_detail.php?id=" . $ticket_id);
    }
}

// Ambil daftar riwayat tiket
$stmt_hist = $db->prepare("SELECT h.*, u.nama AS user_nama, u.role AS user_role 
                           FROM ticket_history h 
                           JOIN users u ON h.user_id = u.id 
                           WHERE h.ticket_id = :id 
                           ORDER BY h.id DESC");
$stmt_hist->execute([':id' => $ticket_id]);
$histories = $stmt_hist->fetchAll();

// Ambil daftar seluruh teknisi untuk opsi assign (Admin / IT Support)
$all_technicians = $db->query("SELECT id, nama, role FROM users WHERE role IN ('IT Support', 'Admin') AND status = 'Aktif' ORDER BY nama ASC")->fetchAll();

$page_title = "Tiket " . $ticket['ticket_number'];
$is_overdue = is_ticket_overdue($ticket);

include __DIR__ . '/../includes/header.php';
?>

<div class="py-6">
    <!-- Breadcrumb & Top Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 mb-1">
                <a href="<?= BASE_URL ?>/pages/tickets.php" class="hover:text-blue-600">Daftar Tiket</a>
                <span>/</span>
                <span class="font-mono text-slate-700"><?= e($ticket['ticket_number']) ?></span>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-black text-slate-900 tracking-tight"><?= e($ticket['judul']) ?></h1>
                <?= get_status_badge($ticket['status']) ?>
                <?= get_priority_badge($ticket['prioritas']) ?>
                <?php if ($is_overdue): ?>
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-black bg-red-100 text-red-800 border border-red-200 animate-pulse">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Terlambat SLA!
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="<?= BASE_URL ?>/pages/tickets.php" class="px-4 py-2 bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 text-xs font-bold rounded-xl shadow-sm transition">
                &larr; Kembali
            </a>
            <?php if (in_array($user['role'], ['IT Support', 'Admin']) && (int)$ticket['assigned_to'] !== (int)$user['id']): ?>
                <form method="POST" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="assign_self">
                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-sm transition flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        Ambil Tiket Ini
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Grid Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Kolom Kiri: Percakapan & Formulir Balasan (2 Col) -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Detail Deskripsi Awal Tiket -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-slate-100 border border-slate-200 text-slate-700 font-bold flex items-center justify-center text-sm">
                            <?= strtoupper(substr($ticket['pemohon_nama'], 0, 1)) ?>
                        </div>
                        <div>
                            <span class="font-bold text-sm text-slate-900 block"><?= e($ticket['pemohon_nama']) ?></span>
                            <span class="text-xs text-slate-500"><?= e($ticket['pemohon_dept'] ?: 'Umum') ?> &bull; Dibuat <?= format_date($ticket['created_at']) ?></span>
                        </div>
                    </div>
                    <span class="text-xs font-bold px-2.5 py-1 bg-slate-100 rounded-lg text-slate-600">
                        <?= e($ticket['kategori_nama'] ?? 'Lainnya') ?>
                    </span>
                </div>

                <div class="text-sm text-slate-700 whitespace-pre-wrap leading-relaxed">
                    <?= e($ticket['deskripsi']) ?>
                </div>

                <!-- Lampiran Tiket Awal -->
                <?php if ($ticket['lampiran']): 
                    $file_url = UPLOAD_URL . $ticket['lampiran'];
                    $is_pdf = str_ends_with(strtolower($ticket['lampiran']), '.pdf');
                ?>
                <div class="mt-5 pt-4 border-t border-slate-100">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500 block mb-2">Berkas Lampiran Pengajuan:</span>
                    <a href="<?= $file_url ?>" target="_blank" class="inline-flex items-center gap-2.5 px-4 py-2.5 bg-slate-50 hover:bg-blue-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 hover:text-blue-700 transition group">
                        <?php if ($is_pdf): ?>
                            <svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>
                        <?php else: ?>
                            <svg class="w-5 h-5 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/></svg>
                        <?php endif; ?>
                        <span><?= e($ticket['lampiran_nama'] ?: 'Lihat Berkas Lampiran') ?></span>
                        <svg class="w-4 h-4 text-slate-400 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Bagian Rating Tiket jika Tiket Resolved/Closed -->
            <?php if (in_array($ticket['status'], ['Resolved', 'Closed'])): ?>
            <div class="bg-gradient-to-r from-amber-50 to-orange-50 rounded-2xl border border-amber-200 p-6">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-black text-amber-900 flex items-center gap-2">
                        <svg class="w-5 h-5 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        Penilaian Kepuasan Layanan IT Support
                    </h3>
                    <?php if ($ticket['rating']): ?>
                        <span class="px-2.5 py-1 bg-amber-100 text-amber-800 font-extrabold text-xs rounded-lg">
                            <?= $ticket['rating'] ?> / 5 Bintang
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($ticket['rating']): ?>
                    <div class="text-xs text-amber-800">
                        <p class="font-medium">Rating: <strong><?= str_repeat('⭐', $ticket['rating']) ?> (<?= $ticket['rating'] ?>/5)</strong></p>
                        <?php if ($ticket['rating_feedback']): ?>
                            <p class="mt-1 italic">"<?= e($ticket['rating_feedback']) ?>"</p>
                        <?php endif; ?>
                    </div>
                <?php elseif ($user['role'] === 'User'): ?>
                    <form method="POST" action="" class="space-y-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="submit_rating">
                        <p class="text-xs text-amber-800">Bagaimana kepuasan Anda terhadap penyelesaian masalah pada tiket ini?</p>
                        <div class="flex items-center gap-4">
                            <?php for ($r = 1; $r <= 5; $r++): ?>
                                <label class="flex items-center gap-1 text-xs font-bold text-amber-900 cursor-pointer">
                                    <input type="radio" name="rating" value="<?= $r ?>" <?= $r === 5 ? 'checked' : '' ?> class="text-amber-500 focus:ring-amber-400">
                                    <span><?= $r ?> ★</span>
                                </label>
                            <?php endfor; ?>
                        </div>
                        <input type="text" name="rating_feedback" placeholder="Tuliskan ulasan atau masukan untuk teknisi kami (opsional)..."
                            class="w-full px-3 py-2 rounded-xl border border-amber-200 text-xs bg-white focus:outline-none focus:ring-2 focus:ring-amber-500">
                        <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-xl shadow-sm transition">
                            Kirim Penilaian Kepuasan
                        </button>
                    </form>
                <?php else: ?>
                    <p class="text-xs text-amber-700">Pemohon belum memberikan penilaian kepuasan untuk tiket ini.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Riwayat Percakapan (Chat Box) -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div class="flex items-center gap-2">
                        <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wider">Percakapan Tiket</h2>
                        <span id="chatLiveBadge" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-pulse"></span>
                            Live (10s)
                        </span>
                    </div>
                    <button type="button" onclick="loadMessages(true)" class="text-xs font-semibold text-blue-600 hover:underline flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Muat Ulang
                    </button>
                </div>

                <!-- Container Pesan Chat -->
                <div id="messagesContainer" class="p-6 space-y-4 max-h-[550px] overflow-y-auto bg-slate-50/30">
                    <div class="text-center py-8 text-slate-400 text-xs" id="chatLoadingState">
                        Memuat percakapan tiket...
                    </div>
                </div>

                <!-- Form Input Balasan Chat -->
                <?php if ($ticket['status'] !== 'Closed'): ?>
                <div class="p-4 sm:p-5 border-t border-slate-200/80 bg-white">
                    <form id="replyForm" method="POST" action="<?= BASE_URL ?>/api/messages.php" enctype="multipart/form-data" class="space-y-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">

                        <!-- Switch Catatan Internal (IT Support & Admin Only) -->
                        <?php if (in_array($user['role'], ['IT Support', 'Admin'])): ?>
                        <div class="flex items-center justify-between bg-amber-50/70 border border-amber-200/80 px-3.5 py-2 rounded-xl">
                            <label class="flex items-center gap-2 text-xs font-bold text-amber-900 cursor-pointer">
                                <input type="checkbox" name="is_internal" value="1" id="isInternalCheckbox" class="rounded text-amber-600 focus:ring-amber-500">
                                <span>Tulis sebagai Catatan Internal (Hanya terlihat oleh IT Support & Admin)</span>
                            </label>
                            <span class="text-[10px] text-amber-700 font-semibold hidden sm:inline">User tidak melihat catatan ini</span>
                        </div>
                        <?php endif; ?>

                        <div>
                            <textarea id="replyMessage" name="pesan" rows="3" required
                                placeholder="Ketik balasan tanggapan atau informasi progres penanganan di sini..."
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-600 text-sm leading-relaxed"></textarea>
                        </div>

                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <label class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 rounded-lg text-xs font-semibold text-slate-700 cursor-pointer transition">
                                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                    <span>Lampirkan Berkas</span>
                                    <input type="file" name="lampiran" id="replyFile" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf" class="hidden" onchange="showSelectedFileName(this)">
                                </label>
                                <span id="selectedFileName" class="text-xs text-slate-500 truncate max-w-[200px]"></span>
                            </div>

                            <button type="submit" id="btnSendReply"
                                class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-xs shadow-md shadow-blue-500/20 transition flex items-center justify-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                <span>Kirim Balasan</span>
                            </button>
                        </div>
                    </form>
                </div>
                <?php else: ?>
                <div class="p-4 bg-slate-50 text-center text-xs text-slate-500 border-t border-slate-200">
                    Tiket ini telah <strong>Ditutup (Closed)</strong>. Percakapan dinonaktifkan kecuali tiket dibuka kembali.
                </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Kolom Kanan: Panel Kontrol Tiket, SLA & Riwayat (1 Col) -->
        <div class="space-y-6">

            <!-- Panel Tindakan & Status (IT Support & Admin) -->
            <?php if (in_array($user['role'], ['IT Support', 'Admin'])): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 space-y-4">
                <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider pb-3 border-b border-slate-100">
                    Panel Kontrol IT Support
                </h3>

                <!-- Ubah Status Tiket -->
                <form method="POST" action="" class="space-y-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="change_status">
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600">Perbarui Status</label>
                    <div class="flex gap-2">
                        <select name="new_status" class="flex-1 px-3 py-2 rounded-xl border border-slate-200 text-xs font-semibold bg-white focus:outline-none focus:ring-2 focus:ring-blue-600">
                            <?php foreach (['Open', 'In Progress', 'Waiting for User', 'Resolved', 'Closed'] as $st): ?>
                                <option value="<?= $st ?>" <?= $ticket['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="px-3.5 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-xl transition">
                            Ubah
                        </button>
                    </div>
                </form>

                <!-- Ubah Prioritas Tiket -->
                <form method="POST" action="" class="space-y-2 pt-3 border-t border-slate-100">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="change_priority">
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600">Ubah Prioritas SLA</label>
                    <div class="flex gap-2">
                        <select name="new_priority" class="flex-1 px-3 py-2 rounded-xl border border-slate-200 text-xs font-semibold bg-white focus:outline-none focus:ring-2 focus:ring-blue-600">
                            <?php foreach (['Rendah', 'Sedang', 'Tinggi', 'Urgent'] as $pr): ?>
                                <option value="<?= $pr ?>" <?= $ticket['prioritas'] === $pr ? 'selected' : '' ?>><?= $pr ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="px-3.5 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-xl transition">
                            Ubah
                        </button>
                    </div>
                </form>

                <!-- Penugasan Teknisi (Assignee) -->
                <form method="POST" action="" class="space-y-2 pt-3 border-t border-slate-100">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="assign_other">
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600">Tugaskan Teknisi</label>
                    <div class="flex gap-2">
                        <select name="assigned_to" class="flex-1 px-3 py-2 rounded-xl border border-slate-200 text-xs font-semibold bg-white focus:outline-none focus:ring-2 focus:ring-blue-600">
                            <option value="0">-- Belum Ditugaskan --</option>
                            <?php foreach ($all_technicians as $tch): ?>
                                <option value="<?= $tch['id'] ?>" <?= (int)$ticket['assigned_to'] === (int)$tch['id'] ? 'selected' : '' ?>>
                                    <?= e($tch['nama']) ?> (<?= $tch['role'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="px-3.5 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-xl transition">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- Tindakan Khusus User Pemohon (Tutup / Reopen Tiket) -->
            <?php if ($user['role'] === 'User'): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 space-y-3">
                <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider pb-2 border-b border-slate-100">
                    Aksi Tiket Pemohon
                </h3>

                <?php if ($ticket['status'] !== 'Closed'): ?>
                    <form method="POST" action="" onsubmit="return confirm('Apakah Anda yakin ingin menutup tiket ini?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="user_close_ticket">
                        <button type="submit" class="w-full py-2.5 px-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition flex items-center justify-center gap-1.5">
                            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Tutup Tiket (Masalah Selesai)
                        </button>
                    </form>
                <?php else: ?>
                    <!-- Form Buka Ulang Tiket -->
                    <form method="POST" action="" onsubmit="return confirm('Apakah Anda yakin ingin membuka kembali tiket ini?');" class="space-y-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="user_reopen_ticket">
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600">Alasan Buka Kembali:</label>
                        <textarea name="alasan_reopen" rows="2" required placeholder="Jelaskan kendala yang masih terjadi..."
                            class="w-full p-2.5 rounded-xl border border-slate-200 text-xs focus:outline-none focus:ring-2 focus:ring-rose-500"></textarea>
                        <button type="submit" class="w-full py-2.5 px-3 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center justify-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Buka Ulang Tiket Ini
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Info Metadata & SLA -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 space-y-3.5 text-xs">
                <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider pb-2 border-b border-slate-100">
                    Informasi & Parameter SLA
                </h3>

                <div class="space-y-2.5">
                    <div class="flex justify-between items-center py-1 border-b border-slate-100">
                        <span class="text-slate-500">Nomor Tiket:</span>
                        <span class="font-mono font-bold text-slate-800"><?= e($ticket['ticket_number']) ?></span>
                    </div>

                    <div class="flex justify-between items-center py-1 border-b border-slate-100">
                        <span class="text-slate-500">Pemohon:</span>
                        <span class="font-bold text-slate-800 text-right"><?= e($ticket['pemohon_nama']) ?></span>
                    </div>

                    <div class="flex justify-between items-center py-1 border-b border-slate-100">
                        <span class="text-slate-500">Kontak:</span>
                        <span class="text-slate-700 text-right"><?= e($ticket['pemohon_email']) ?> <?= $ticket['pemohon_hp'] ? "({$ticket['pemohon_hp']})" : "" ?></span>
                    </div>

                    <div class="flex justify-between items-center py-1 border-b border-slate-100">
                        <span class="text-slate-500">Teknisi Pengampu:</span>
                        <span class="font-bold text-blue-600 text-right"><?= e($ticket['agent_nama'] ?? 'Belum Ditugaskan') ?></span>
                    </div>

                    <div class="flex justify-between items-center py-1 border-b border-slate-100">
                        <span class="text-slate-500">Target Respon SLA:</span>
                        <span class="font-semibold text-slate-800"><?= SLA_HOURS[$ticket['prioritas']] ?? 24 ?> Jam</span>
                    </div>

                    <div class="flex justify-between items-center py-1 border-b border-slate-100">
                        <span class="text-slate-500">Batas Waktu Respon:</span>
                        <span class="font-semibold <?= $is_overdue ? 'text-red-600 font-bold' : 'text-slate-800' ?>">
                            <?= format_date($ticket['sla_due_at'], true) ?>
                        </span>
                    </div>

                    <div class="flex justify-between items-center py-1 border-b border-slate-100">
                        <span class="text-slate-500">Respon Pertama:</span>
                        <span class="font-semibold text-slate-800">
                            <?= $ticket['first_response_at'] ? format_date($ticket['first_response_at'], true) : '<span class="text-amber-600 font-bold">Belum Ada</span>' ?>
                        </span>
                    </div>

                    <div class="flex justify-between items-center py-1">
                        <span class="text-slate-500">Waktu Selesai:</span>
                        <span class="font-semibold text-slate-800">
                            <?= $ticket['resolved_at'] ? format_date($ticket['resolved_at'], true) : '-' ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Riwayat Perubahan Tiket (Audit Trail) -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 space-y-3">
                <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider pb-2 border-b border-slate-100 flex items-center justify-between">
                    <span>Riwayat Aktivitas</span>
                    <span class="text-[10px] text-slate-400 font-normal"><?= count($histories) ?> entri</span>
                </h3>

                <div class="space-y-3 max-h-72 overflow-y-auto pr-1">
                    <?php if (empty($histories)): ?>
                        <p class="text-xs text-slate-400 text-center py-4">Belum ada riwayat aktivitas.</p>
                    <?php else: ?>
                        <?php foreach ($histories as $h): ?>
                        <div class="flex gap-2.5 text-xs border-l-2 border-slate-200 pl-3 py-1">
                            <div>
                                <span class="font-bold text-slate-800 block"><?= e($h['action']) ?></span>
                                <span class="text-[11px] text-slate-600 block"><?= e($h['keterangan']) ?></span>
                                <span class="text-[10px] text-slate-400 block mt-0.5"><?= e($h['user_nama']) ?> &bull; <?= time_ago($h['created_at']) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </div>
</div>

<!-- JavaScript Interaktif Chat & Auto-Refresh Tiap 10 Detik -->
<script>
const TICKET_ID = <?= $ticket_id ?>;
const CSRF_TOKEN = '<?= csrf_token() ?>';
const CURRENT_USER_ID = <?= $user['id'] ?>;
let lastMessageId = 0;
let refreshInterval = null;

function showSelectedFileName(input) {
    const label = document.getElementById('selectedFileName');
    if (input.files && input.files[0]) {
        label.textContent = input.files[0].name;
    } else {
        label.textContent = '';
    }
}

// Render Satu Bubble Chat
function renderMessageHtml(msg) {
    const isMe = msg.is_me;
    const isInternal = msg.is_internal;

    let bubbleStyle = isMe 
        ? 'bg-blue-600 text-white rounded-br-sm' 
        : 'bg-white border border-slate-200 text-slate-800 rounded-bl-sm';

    if (isInternal) {
        bubbleStyle = 'bg-amber-50 border border-amber-300 text-amber-900 rounded-xl';
    }

    let roleBadge = '';
    if (msg.user_role === 'Admin') {
        roleBadge = '<span class="text-[10px] px-1.5 py-0.2 rounded bg-purple-100 text-purple-700 font-bold ml-1.5">Admin</span>';
    } else if (msg.user_role === 'IT Support') {
        roleBadge = '<span class="text-[10px] px-1.5 py-0.2 rounded bg-blue-100 text-blue-700 font-bold ml-1.5">IT Support</span>';
    }

    let internalHeader = '';
    if (isInternal) {
        internalHeader = `
            <div class="flex items-center gap-1.5 text-[11px] font-bold text-amber-800 mb-1.5 pb-1 border-b border-amber-200/60">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                Catatan Internal IT Support
            </div>
        `;
    }

    let attachmentHtml = '';
    if (msg.lampiran) {
        const isPdf = msg.lampiran.toLowerCase().endsWith('.pdf');
        attachmentHtml = `
            <div class="mt-2.5 pt-2 border-t ${isMe && !isInternal ? 'border-blue-500/60' : 'border-slate-100'}">
                <a href="${msg.lampiran}" target="_blank" class="inline-flex items-center gap-1.5 text-xs font-semibold ${isMe && !isInternal ? 'text-blue-100 hover:text-white' : 'text-blue-600 hover:underline'}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                    <span>${msg.lampiran_nama || 'Unduh Berkas Lampiran'}</span>
                </a>
            </div>
        `;
    }

    return `
        <div class="flex flex-col ${isMe ? 'items-end' : 'items-start'} my-2" data-message-id="${msg.id}">
            <div class="flex items-center gap-1.5 mb-1 text-[11px] text-slate-500 font-medium">
                <span class="font-bold text-slate-700">${msg.user_nama}</span>
                ${roleBadge}
                <span>&bull; ${msg.time_ago}</span>
            </div>
            <div class="max-w-[85%] sm:max-w-[75%] p-3.5 rounded-2xl shadow-sm ${bubbleStyle}">
                ${internalHeader}
                <div class="text-xs leading-relaxed whitespace-pre-wrap">${msg.message}</div>
                ${attachmentHtml}
            </div>
        </div>
    `;
}

// Fetch Messages dari API
async function loadMessages(isManual = false) {
    try {
        const res = await fetch(`<?= BASE_URL ?>/api/messages.php?ticket_id=${TICKET_ID}&last_id=${lastMessageId}`);
        if (!res.ok) throw new Error('Gagal mengambil balasan.');
        const data = await res.json();

        if (data.success && data.messages.length > 0) {
            const container = document.getElementById('messagesContainer');
            const loadingState = document.getElementById('chatLoadingState');
            if (loadingState) loadingState.remove();

            data.messages.forEach(msg => {
                if (msg.id > lastMessageId) {
                    container.insertAdjacentHTML('beforeend', renderMessageHtml(msg));
                    lastMessageId = msg.id;
                }
            });

            // Scroll otomatis ke dasar percakapan
            container.scrollTop = container.scrollHeight;
        } else if (lastMessageId === 0 && (!data.messages || data.messages.length === 0)) {
            const container = document.getElementById('messagesContainer');
            container.innerHTML = '<div class="text-center py-8 text-slate-400 text-xs">Belum ada percakapan pada tiket ini. Silakan kirim balasan pertama.</div>';
        }
    } catch (err) {
        console.error('Chat auto-refresh error:', err);
    }
}

// Submit Form Balasan via Fetch AJAX
document.addEventListener('DOMContentLoaded', () => {
    loadMessages();

    // Set interval auto-refresh setiap 10 detik (10.000 ms)
    refreshInterval = setInterval(() => {
        loadMessages(false);
    }, 10000);

    const replyForm = document.getElementById('replyForm');
    if (replyForm) {
        replyForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('btnSendReply');
            const textarea = document.getElementById('replyMessage');
            btn.disabled = true;
            btn.classList.add('opacity-75');

            const formData = new FormData(replyForm);

            try {
                const res = await fetch(replyForm.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-Token': CSRF_TOKEN
                    }
                });

                const json = await res.json();
                if (json.success) {
                    textarea.value = '';
                    const fileInput = document.getElementById('replyFile');
                    if (fileInput) fileInput.value = '';
                    document.getElementById('selectedFileName').textContent = '';
                    const internalCb = document.getElementById('isInternalCheckbox');
                    if (internalCb) internalCb.checked = false;

                    // Langsung muat pesan baru
                    await loadMessages(true);
                } else {
                    alert('Gagal mengirim balasan: ' + (json.error || 'Terjadi kesalahan sistem'));
                }
            } catch (err) {
                alert('Terjadi kesalahan jaringan atau sesi kedaluwarsa.');
            } finally {
                btn.disabled = false;
                btn.classList.remove('opacity-75');
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
