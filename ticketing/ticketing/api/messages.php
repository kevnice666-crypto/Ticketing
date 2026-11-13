<?php
// api/messages.php - Endpoint JSON untuk Chat Percakapan Real-Time (Auto-Refresh)

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Sesi kedaluwarsa. Silakan login kembali.']);
    exit;
}

$user = current_user();
$db = get_db();
$ticket_id = (int)($_GET['ticket_id'] ?? $_POST['ticket_id'] ?? 0);

if ($ticket_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parameter tiket tidak valid.']);
    exit;
}

// Cek hak akses terhadap tiket
$stmt = $db->prepare("SELECT * FROM tickets WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $ticket_id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Tiket tidak ditemukan.']);
    exit;
}

// User hanya boleh mengakses tiket miliknya sendiri
if ($user['role'] === 'User' && (int)$ticket['user_id'] !== (int)$user['id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Akses terhadap tiket ini ditolak.']);
    exit;
}

// Handle GET: Ambil daftar pesan
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $last_id = (int)($_GET['last_id'] ?? 0);

    // Filter pesan: User biasa tidak dapat melihat catatan internal (is_internal = 1)
    $internal_filter = ($user['role'] === 'User') ? "AND m.is_internal = 0" : "";

    $sql = "SELECT m.*, u.nama AS user_nama, u.role AS user_role, u.departemen AS user_dept
            FROM ticket_messages m
            JOIN users u ON m.user_id = u.id
            WHERE m.ticket_id = :tid AND m.id > :last_id $internal_filter
            ORDER BY m.id ASC";

    $stmt_msg = $db->prepare($sql);
    $stmt_msg->execute([':tid' => $ticket_id, ':last_id' => $last_id]);
    $messages = $stmt_msg->fetchAll();

    $formatted = [];
    foreach ($messages as $m) {
        $formatted[] = [
            'id'            => (int)$m['id'],
            'user_id'       => (int)$m['user_id'],
            'user_nama'     => $m['user_nama'],
            'user_role'     => $m['user_role'],
            'user_dept'     => $m['user_dept'],
            'is_me'         => ((int)$m['user_id'] === (int)$user['id']),
            'is_internal'   => (bool)$m['is_internal'],
            'message'       => nl2br(e($m['message'])),
            'raw_message'   => $m['message'],
            'lampiran'      => $m['lampiran'] ? UPLOAD_URL . $m['lampiran'] : null,
            'lampiran_nama' => $m['lampiran_nama'],
            'created_at'    => format_date($m['created_at'], true),
            'time_ago'      => time_ago($m['created_at']),
        ];
    }

    echo json_encode([
        'success'       => true,
        'ticket_status' => $ticket['status'],
        'messages'      => $formatted,
        'count'         => count($formatted),
    ]);
    exit;
}

// Handle POST: Kirim balasan baru via API / Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Token CSRF tidak valid.']);
        exit;
    }

    $pesan = trim($_POST['pesan'] ?? '');
    $is_internal = (int)($_POST['is_internal'] ?? 0);

    // User biasa dilarang membuat catatan internal
    if ($user['role'] === 'User') {
        $is_internal = 0;
    }

    if (empty($pesan) && empty($_FILES['lampiran']['name'])) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Pesan atau lampiran berkas wajib diisi.']);
        exit;
    }

    // Upload lampiran jika ada
    $upload_res = handle_file_upload($_FILES['lampiran'] ?? []);
    if (!$upload_res['success']) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => $upload_res['error']]);
        exit;
    }

    try {
        $db->beginTransaction();

        // 1. Simpan pesan percakapan
        $stmt_ins = $db->prepare("INSERT INTO ticket_messages 
            (ticket_id, user_id, message, is_internal, lampiran, lampiran_nama, lampiran_ukuran, created_at)
            VALUES 
            (:tid, :uid, :msg, :internal, :lamp, :lamp_name, :lamp_size, NOW())");

        $stmt_ins->execute([
            ':tid'       => $ticket_id,
            ':uid'       => $user['id'],
            ':msg'       => $pesan,
            ':internal'  => $is_internal,
            ':lamp'      => $upload_res['path'],
            ':lamp_name' => $upload_res['name'],
            ':lamp_size' => $upload_res['size'],
        ]);
        $new_msg_id = (int)$db->lastInsertId();

        // 2. Perbarui first_response_at jika ini respon pertama IT Support/Admin dan balasan bersifat publik
        if (in_array($user['role'], ['IT Support', 'Admin']) && !$is_internal && empty($ticket['first_response_at'])) {
            $db->prepare("UPDATE tickets SET first_response_at = NOW() WHERE id = :id")->execute([':id' => $ticket_id]);
        }

        // 3. Update status tiket jika relevan
        $new_status = $ticket['status'];
        if ($user['role'] === 'User') {
            // Jika user membalas dan status sebelumnya Waiting for User, kembalikan ke In Progress
            if ($ticket['status'] === 'Waiting for User') {
                $new_status = 'In Progress';
                $db->prepare("UPDATE tickets SET status = 'In Progress', updated_at = NOW() WHERE id = :id")->execute([':id' => $ticket_id]);
                add_ticket_history($db, $ticket_id, $user['id'], 'Status Diubah', 'Otomatis diubah menjadi In Progress setelah pemohon mengirim balasan');
            }
        } elseif (in_array($user['role'], ['IT Support', 'Admin']) && !$is_internal) {
            // Jika tiket masih Open saat IT Support membalas, otomatis set In Progress
            if ($ticket['status'] === 'Open') {
                $new_status = 'In Progress';
                $db->prepare("UPDATE tickets SET status = 'In Progress', updated_at = NOW() WHERE id = :id")->execute([':id' => $ticket_id]);
                add_ticket_history($db, $ticket_id, $user['id'], 'Status Diubah', 'Otomatis diubah menjadi In Progress oleh respon teknisi');
            }
        }

        // Selalu update updated_at tiket
        $db->prepare("UPDATE tickets SET updated_at = NOW() WHERE id = :id")->execute([':id' => $ticket_id]);

        // 4. Kirim notifikasi in-app
        if (!$is_internal) {
            if ($user['role'] === 'User') {
                // Notifikasi ke teknisi yang ditugaskan atau ke semua IT Support
                $recipient_id = $ticket['assigned_to'];
                if ($recipient_id) {
                    create_notification($db, (int)$recipient_id, $ticket_id, 'Balasan Baru dari Pemohon', "Pemohon {$user['nama']} membalas tiket {$ticket['ticket_number']}", BASE_URL . "/pages/ticket_detail.php?id=$ticket_id");
                }
            } else {
                // IT Support membalas publik -> Notifikasi ke pembuat tiket
                create_notification($db, (int)$ticket['user_id'], $ticket_id, 'Balasan dari IT Support', "Teknisi {$user['nama']} membalas tiket {$ticket['ticket_number']}", BASE_URL . "/pages/ticket_detail.php?id=$ticket_id");
            }
        }

        $db->commit();

        echo json_encode([
            'success'    => true,
            'message_id' => $new_msg_id,
            'ticket_id'  => $ticket_id,
            'status'     => $new_status,
        ]);
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Gagal menyimpan pesan: ' . $e->getMessage()]);
        exit;
    }
}
