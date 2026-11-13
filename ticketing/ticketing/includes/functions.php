<?php
// includes/functions.php - Helper Functions, Validasi, Flash Message, SLA, dan Logika Bisnis

require_once __DIR__ . '/../config/config.php';

/**
 * Sanitasi output teks untuk mencegah XSS
 * @param mixed $data
 * @return string
 */
function e($data): string {
    return htmlspecialchars((string)($data ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect ke URL tertentu
 * @param string $url
 */
function redirect(string $url): void {
    header("Location: " . $url);
    exit;
}

/**
 * Dapatkan IP address user dengan aman
 * @return string
 */
function get_client_ip(): string {
    return $_SERVER['HTTP_CF_CONNECTING_IP']
        ?? $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '127.0.0.1';
}

/**
 * Set flash message (success, error, warning, info)
 * @param string $type
 * @param string $message
 */
function set_flash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message,
    ];
}

/**
 * Ambil flash message dan hapus dari session
 * @return array|null
 */
function get_flash(): ?array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Format tanggal Indonesia ramah pengguna
 * @param string|null $datetime
 * @param bool $with_time
 * @return string
 */
function format_date(?string $datetime, bool $with_time = true): string {
    if (!$datetime) return '-';
    $timestamp = strtotime($datetime);
    $months = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $d = date('j', $timestamp);
    $m = $months[(int)date('n', $timestamp)];
    $y = date('Y', $timestamp);
    
    if ($with_time) {
        $t = date('H:i', $timestamp);
        return "$d $m $y, $t WIB";
    }
    return "$d $m $y";
}

/**
 * Format waktu relatif (e.g. 5 menit yang lalu)
 * @param string $datetime
 * @return string
 */
function time_ago(string $datetime): string {
    $timestamp = strtotime($datetime);
    $difference = time() - $timestamp;

    if ($difference < 60) {
        return 'Baru saja';
    } elseif ($difference < 3600) {
        $mins = floor($difference / 60);
        return $mins . ' menit lalu';
    } elseif ($difference < 86400) {
        $hours = floor($difference / 3600);
        return $hours . ' jam lalu';
    } elseif ($difference < 2592000) {
        $days = floor($difference / 86400);
        return $days . ' hari lalu';
    } else {
        return format_date($datetime, false);
    }
}

/**
 * Generate nomor tiket otomatis: TKT-YYYYMMDD-0001
 * Menggunakan transaksi database agar aman dari race condition
 * @param PDO $db
 * @return string
 */
function generate_ticket_number(PDO $db): string {
    $today = date('Ymd');
    $prefix = "TKT-$today-";

    $stmt = $db->prepare("SELECT ticket_number FROM tickets WHERE ticket_number LIKE :prefix ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $stmt->execute([':prefix' => $prefix . '%']);
    $last_ticket = $stmt->fetchColumn();

    if ($last_ticket) {
        $parts = explode('-', $last_ticket);
        $last_seq = (int)end($parts);
        $new_seq = $last_seq + 1;
    } else {
        $new_seq = 1;
    }

    return $prefix . str_pad((string)$new_seq, 4, '0', STR_PAD_LEFT);
}

/**
 * Hitung deadline SLA respon tiket
 * @param string $prioritas
 * @param string|null $created_at
 * @return string Y-m-d H:i:s
 */
function calculate_sla_due(string $prioritas, ?string $created_at = null): string {
    $hours = SLA_HOURS[$prioritas] ?? 24;
    $base_time = $created_at ? strtotime($created_at) : time();
    return date('Y-m-d H:i:s', $base_time + ($hours * 3600));
}

/**
 * Cek apakah tiket melewati batas SLA respon (Overdue)
 * Tiket overdue jika first_response_at masih NULL dan waktu sekarang > sla_due_at,
 * dan status tiket masih aktif (bukan Closed atau Resolved)
 * @param array $ticket
 * @return bool
 */
function is_ticket_overdue(array $ticket): bool {
    if (in_array($ticket['status'], ['Closed', 'Resolved'])) {
        return false;
    }
    if (!empty($ticket['first_response_at'])) {
        return false;
    }
    if (empty($ticket['sla_due_at'])) {
        return false;
    }
    return time() > strtotime($ticket['sla_due_at']);
}

/**
 * Catat riwayat perubahan tiket (ticket_history)
 * @param PDO $db
 * @param int $ticket_id
 * @param int $user_id
 * @param string $action
 * @param string|null $keterangan
 */
function add_ticket_history(PDO $db, int $ticket_id, int $user_id, string $action, ?string $keterangan = null): void {
    $stmt = $db->prepare("INSERT INTO ticket_history (ticket_id, user_id, action, keterangan, created_at)
                          VALUES (:ticket_id, :user_id, :action, :keterangan, NOW())");
    $stmt->execute([
        ':ticket_id'   => $ticket_id,
        ':user_id'     => $user_id,
        ':action'      => $action,
        ':keterangan'  => $keterangan,
    ]);
}

/**
 * Buat notifikasi in-app
 * @param PDO $db
 * @param int $user_id
 * @param int $ticket_id
 * @param string $title
 * @param string $message
 * @param string $link
 */
function create_notification(PDO $db, int $user_id, int $ticket_id, string $title, string $message, string $link): void {
    $stmt = $db->prepare("INSERT INTO notifications (user_id, ticket_id, title, message, link, is_read, created_at)
                          VALUES (:user_id, :ticket_id, :title, :message, :link, 0, NOW())");
    $stmt->execute([
        ':user_id'   => $user_id,
        ':ticket_id' => $ticket_id,
        ':title'     => $title,
        ':message'   => $message,
        ':link'      => $link,
    ]);
}

/**
 * Dapatkan jumlah notifikasi belum dibaca untuk user tertentu
 * @param PDO $db
 * @param int $user_id
 * @return int
 */
function get_unread_notification_count(PDO $db, int $user_id): int {
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0");
    $stmt->execute([':user_id' => $user_id]);
    return (int)$stmt->fetchColumn();
}

/**
 * Fitur 9: Auto-close tiket dengan status 'Resolved' setelah 3 hari tanpa balasan
 * Dijalankan otomatis di background saat halaman dashboard dibuka (tanpa cron)
 * @param PDO $db
 * @return int Jumlah tiket yang ditutup
 */
function run_auto_close_tickets(PDO $db): int {
    $cutoff = date('Y-m-d H:i:s', time() - (AUTO_CLOSE_DAYS * 86400));
    
    // Cari tiket Resolved yang di-update sebelum cutoff
    $stmt = $db->prepare("SELECT id, user_id, ticket_number, judul FROM tickets 
                          WHERE status = 'Resolved' AND updated_at < :cutoff");
    $stmt->execute([':cutoff' => $cutoff]);
    $tickets = $stmt->fetchAll();

    if (empty($tickets)) {
        return 0;
    }

    $closed_count = 0;
    foreach ($tickets as $t) {
        $update = $db->prepare("UPDATE tickets SET status = 'Closed', updated_at = NOW() WHERE id = :id AND status = 'Resolved'");
        $update->execute([':id' => $t['id']]);

        // Catat riwayat (user_id 1 / system)
        add_ticket_history($db, (int)$t['id'], 1, 'Auto-Close', 'Ditutup otomatis oleh sistem setelah 3 hari tanpa respon (Resolved)');

        // Notifikasi ke pembuat tiket
        create_notification(
            $db,
            (int)$t['user_id'],
            (int)$t['id'],
            'Tiket Ditutup Otomatis',
            "Tiket {$t['ticket_number']} telah ditutup otomatis karena sudah 3 hari terselesaikan.",
            BASE_URL . "/pages/ticket_detail.php?id=" . $t['id']
        );
        $closed_count++;
    }

    return $closed_count;
}

/**
 * Handle upload file lampiran tiket / balasan
 * @param array $file $_FILES['lampiran']
 * @return array ['success' => bool, 'path' => string, 'name' => string, 'size' => int, 'error' => string]
 */
function handle_file_upload(array $file): array {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'path' => null, 'name' => null, 'size' => 0];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Terjadi kesalahan saat mengunggah berkas (Kode error: ' . $file['error'] . ')'];
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['success' => false, 'error' => 'Ukuran berkas melebihi batas maksimal 5 MB.'];
    }

    $original_name = basename($file['name']);
    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

    if (!in_array($ext, ALLOWED_EXTENSIONS, true)) {
        return ['success' => false, 'error' => 'Ekstensi berkas tidak diizinkan. Hanya diperbolehkan: ' . implode(', ', ALLOWED_EXTENSIONS)];
    }

    // Validasi MIME type riil dengan finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!in_array($mime, ALLOWED_MIME_TYPES, true)) {
        return ['success' => false, 'error' => 'Tipe konten berkas tidak valid (' . e($mime) . '). Berkas harus berupa Gambar atau Dokumen PDF.'];
    }

    // Pastikan folder upload ada
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    // Nama acak unik aman
    $new_filename = bin2hex(random_bytes(16)) . '_' . time() . '.' . $ext;
    $destination = UPLOAD_DIR . $new_filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'error' => 'Gagal memindahkan berkas yang diunggah ke direktori server.'];
    }

    return [
        'success' => true,
        'path'    => $new_filename,
        'name'    => $original_name,
        'size'    => $file['size'],
    ];
}

/**
 * Helper badge warna status tiket
 * @param string $status
 * @return string HTML class badge
 */
function get_status_badge(string $status): string {
    switch ($status) {
        case 'Open':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">Open</span>';
        case 'In Progress':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 border border-blue-200">In Progress</span>';
        case 'Waiting for User':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-200">Waiting for User</span>';
        case 'Resolved':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-800 border border-purple-200">Resolved</span>';
        case 'Closed':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">Closed</span>';
        case 'Reopened':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 border border-rose-200">Reopened</span>';
        default:
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-800">' . e($status) . '</span>';
    }
}

/**
 * Helper badge warna prioritas tiket
 * @param string $priority
 * @return string HTML class badge
 */
function get_priority_badge(string $priority): string {
    switch ($priority) {
        case 'Urgent':
            return '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-800 border border-red-200"><span class="w-1.5 h-1.5 rounded-full bg-red-600 animate-pulse"></span>Urgent (1 Jam)</span>';
        case 'Tinggi':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-orange-100 text-orange-800 border border-orange-200">Tinggi (4 Jam)</span>';
        case 'Sedang':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800 border border-yellow-200">Sedang (8 Jam)</span>';
        case 'Rendah':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">Rendah (24 Jam)</span>';
        default:
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-800">' . e($priority) . '</span>';
    }
}
