<?php
// pages/ticket_create.php - Formulir Pengajuan Tiket Bantuan IT Baru

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$user = current_user();
$db = get_db();
$page_title = 'Buat Tiket Bantuan Baru';
$error = '';

// Ambil kategori aktif dari database
$categories = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY urutan ASC, nama ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $judul       = trim($_POST['judul'] ?? '');
    $deskripsi   = trim($_POST['deskripsi'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $prioritas   = trim($_POST['prioritas'] ?? 'Sedang');

    $valid_priorities = ['Rendah', 'Sedang', 'Tinggi', 'Urgent'];

    if (empty($judul) || empty($deskripsi) || $category_id <= 0) {
        $error = 'Judul tiket, kategori, dan rincian masalah wajib diisi.';
    } elseif (!in_array($prioritas, $valid_priorities, true)) {
        $error = 'Pilihan tingkat prioritas tidak valid.';
    } else {
        // Handle lampiran berkas jika diunggah
        $upload_res = handle_file_upload($_FILES['lampiran'] ?? []);
        if (!$upload_res['success']) {
            $error = $upload_res['error'];
        } else {
            try {
                $db->beginTransaction();

                // 1. Generate nomor tiket otomatis TKT-YYYYMMDD-0001
                $ticket_number = generate_ticket_number($db);

                // 2. Hitung SLA deadline
                $sla_due_at = calculate_sla_due($prioritas);

                // 3. Simpan tiket baru
                $stmt = $db->prepare("INSERT INTO tickets 
                    (ticket_number, user_id, category_id, judul, deskripsi, prioritas, status, lampiran, lampiran_nama, lampiran_ukuran, sla_due_at, created_at, updated_at)
                    VALUES 
                    (:ticket_number, :user_id, :category_id, :judul, :deskripsi, :prioritas, 'Open', :lampiran, :lampiran_nama, :lampiran_ukuran, :sla_due_at, NOW(), NOW())");

                $stmt->execute([
                    ':ticket_number'   => $ticket_number,
                    ':user_id'         => $user['id'],
                    ':category_id'     => $category_id,
                    ':judul'           => $judul,
                    ':deskripsi'       => $deskripsi,
                    ':prioritas'       => $prioritas,
                    ':lampiran'        => $upload_res['path'],
                    ':lampiran_nama'   => $upload_res['name'],
                    ':lampiran_ukuran' => $upload_res['size'],
                    ':sla_due_at'      => $sla_due_at,
                ]);

                $ticket_id = (int)$db->lastInsertId();

                // 4. Catat riwayat pembuatan tiket
                add_ticket_history($db, $ticket_id, $user['id'], 'Dibuat', "Tiket dibuat dengan prioritas $prioritas");

                // 5. Kirim notifikasi in-app ke seluruh IT Support & Admin
                $staffs = $db->query("SELECT id FROM users WHERE role IN ('IT Support', 'Admin') AND status = 'Aktif'")->fetchAll();
                foreach ($staffs as $st) {
                    create_notification(
                        $db,
                        (int)$st['id'],
                        $ticket_id,
                        'Tiket Baru Diajukan',
                        "Tiket {$ticket_number} ({$prioritas}): {$judul}",
                        BASE_URL . "/pages/ticket_detail.php?id=" . $ticket_id
                    );
                }

                $db->commit();
                set_flash('success', "Tiket berhasil diajukan dengan nomor <strong>{$ticket_number}</strong>.");
                redirect(BASE_URL . "/pages/ticket_detail.php?id=" . $ticket_id);
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Gagal menyimpan tiket: ' . $e->getMessage();
            }
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto py-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Buat Pengajuan Tiket Bantuan</h1>
            <p class="text-sm text-slate-500 mt-0.5">Sampaikan kendala perangkat keras, jaringan, sistem, atau aplikasi kepada tim IT Support</p>
        </div>
        <a href="<?= BASE_URL ?>/pages/tickets.php" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-slate-200 text-slate-700 text-sm font-semibold rounded-xl hover:bg-slate-50 shadow-sm transition">
            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Kembali
        </a>
    </div>

    <?php if ($error): ?>
        <div class="mb-6 p-4 rounded-2xl bg-red-50 text-red-800 border border-red-200 text-sm font-medium flex items-center gap-3">
            <svg class="w-5 h-5 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div><?= e($error) ?></div>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-3xl shadow-sm border border-slate-200/80 p-6 sm:p-8">
        <form method="POST" action="" enctype="multipart/form-data" class="space-y-6">
            <?= csrf_field() ?>

            <!-- Baris Kategori & Prioritas -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label for="category_id" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                        Kategori Permasalahan <span class="text-red-500">*</span>
                    </label>
                    <select id="category_id" name="category_id" required
                        class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent text-sm bg-white font-medium">
                        <option value="">-- Pilih Kategori Kendala --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= (isset($_POST['category_id']) && (int)$_POST['category_id'] === (int)$cat['id']) ? 'selected' : '' ?>>
                                <?= e($cat['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="prioritas" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                        Tingkat Prioritas (SLA Respon) <span class="text-red-500">*</span>
                    </label>
                    <select id="prioritas" name="prioritas" required
                        class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent text-sm bg-white font-medium">
                        <option value="Rendah" <?= (isset($_POST['prioritas']) && $_POST['prioritas'] === 'Rendah') ? 'selected' : '' ?>>Rendah (Target respon: 24 Jam)</option>
                        <option value="Sedang" <?= (!isset($_POST['prioritas']) || $_POST['prioritas'] === 'Sedang') ? 'selected' : '' ?>>Sedang (Target respon: 8 Jam)</option>
                        <option value="Tinggi" <?= (isset($_POST['prioritas']) && $_POST['prioritas'] === 'Tinggi') ? 'selected' : '' ?>>Tinggi (Target respon: 4 Jam)</option>
                        <option value="Urgent" <?= (isset($_POST['prioritas']) && $_POST['prioritas'] === 'Urgent') ? 'selected' : '' ?>>Urgent / Darurat (Target respon: 1 Jam)</option>
                    </select>
                </div>
            </div>

            <!-- Judul Tiket -->
            <div>
                <label for="judul" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                    Judul Ringkas Permasalahan <span class="text-red-500">*</span>
                </label>
                <input type="text" id="judul" name="judul" required
                    value="<?= e($_POST['judul'] ?? '') ?>"
                    placeholder="cth: Printer Ruang Pelayanan Paper Jam / Tidak Mau Menarik Kertas"
                    class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent text-sm font-medium">
            </div>

            <!-- Deskripsi Rinci -->
            <div>
                <label for="deskripsi" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                    Deskripsi Lengkap & Langkah Kejadian <span class="text-red-500">*</span>
                </label>
                <textarea id="deskripsi" name="deskripsi" rows="6" required
                    placeholder="Jelaskan detail masalah: sejak kapan terjadi, pesan kesalahan yang muncul di layar, nomor perangkat jika ada, dan upaya yang sudah dicoba..."
                    class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent text-sm leading-relaxed"><?= e($_POST['deskripsi'] ?? '') ?></textarea>
            </div>

            <!-- Lampiran File -->
            <div class="p-5 bg-slate-50 rounded-2xl border border-slate-200/80">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                    Lampiran Pendukung (Opsional)
                </label>
                <p class="text-xs text-slate-500 mb-3">Foto layar kendala (Screenshot) atau dokumen PDF pendukung. Format: JPG, PNG, GIF, WEBP, PDF (Maksimal 5 MB).</p>
                <input type="file" id="lampiran" name="lampiran" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf"
                    class="block w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition cursor-pointer">
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <a href="<?= BASE_URL ?>/pages/tickets.php" class="px-5 py-3 text-sm font-semibold text-slate-600 hover:text-slate-800 rounded-xl hover:bg-slate-100 transition">
                    Batal
                </a>
                <button type="submit"
                    class="px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white text-sm font-bold rounded-xl shadow-lg shadow-blue-500/25 transition active:scale-[0.99] flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    Kirim Tiket Sekarang
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
