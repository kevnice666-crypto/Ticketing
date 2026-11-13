<?php
// pages/export_csv.php - Ekspor Laporan Data Tiket ke Berkas CSV Excel-Friendly

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['Admin', 'IT Support']);
$db = get_db();

// Filter opsional mengikuti query GET
$search      = trim($_GET['search'] ?? '');
$status      = trim($_GET['status'] ?? '');
$prioritas   = trim($_GET['prioritas'] ?? '');
$category_id = (int)($_GET['category_id'] ?? 0);
$agent_id    = (int)($_GET['agent_id'] ?? 0);

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(t.ticket_number LIKE :kw OR t.judul LIKE :kw OR u.nama LIKE :kw)";
    $params[':kw'] = '%' . $search . '%';
}
if (!empty($status)) {
    $where[] = "t.status = :status";
    $params[':status'] = $status;
}
if (!empty($prioritas)) {
    $where[] = "t.prioritas = :prioritas";
    $params[':prioritas'] = $prioritas;
}
if ($category_id > 0) {
    $where[] = "t.category_id = :cat_id";
    $params[':cat_id'] = $category_id;
}
if ($agent_id > 0) {
    $where[] = "t.assigned_to = :agent_id";
    $params[':agent_id'] = $agent_id;
}

$where_clause = empty($where) ? "" : "WHERE " . implode(" AND ", $where);

$sql = "SELECT t.ticket_number, t.judul, c.nama AS kategori, t.prioritas, t.status,
               u.nama AS pemohon_nama, u.email AS pemohon_email, u.departemen,
               a.nama AS teknisi,
               t.rating, t.rating_feedback,
               t.created_at, t.first_response_at, t.resolved_at, t.closed_at
        FROM tickets t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN categories c ON t.category_id = c.id
        LEFT JOIN users a ON t.assigned_to = a.id
        $where_clause
        ORDER BY t.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Header HTTP untuk download berkas CSV
$filename = "laporan_tiket_it_" . date('Ymd_His') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Tambahkan UTF-8 BOM agar terbaca sempurna di Microsoft Excel
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// Baris Judul Kolom
fputcsv($output, [
    'No. Tiket',
    'Judul Permasalahan',
    'Kategori',
    'Prioritas',
    'Status',
    'Nama Pemohon',
    'Email Pemohon',
    'Departemen / Divisi',
    'Teknisi Ditugaskan',
    'Rating Kepuasan (1-5)',
    'Ulasan Pemohon',
    'Waktu Dibuat',
    'Respon Pertama IT',
    'Waktu Selesai (Resolved)',
    'Waktu Ditutup (Closed)'
]);

foreach ($rows as $r) {
    fputcsv($output, [
        $r['ticket_number'],
        $r['judul'],
        $r['kategori'] ?? 'Lainnya',
        $r['prioritas'],
        $r['status'],
        $r['pemohon_nama'],
        $r['pemohon_email'],
        $r['departemen'] ?? '-',
        $r['teknisi'] ?? 'Belum Ditugaskan',
        $r['rating'] ? $r['rating'] . ' Bintang' : '-',
        $r['rating_feedback'] ?? '-',
        $r['created_at'],
        $r['first_response_at'] ?? '-',
        $r['resolved_at'] ?? '-',
        $r['closed_at'] ?? '-'
    ]);
}

fclose($output);
exit;
