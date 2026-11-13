<?php
// pages/categories.php - Manajemen Kategori Tiket (Khusus Admin)

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['Admin']);
$db = get_db();
$page_title = 'Kelola Kategori Permasalahan';

// Handle Tambah / Edit Kategori
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    // Tambah Kategori
    if ($action === 'create_category') {
        $nama = trim($_POST['nama'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $urutan = (int)($_POST['urutan'] ?? 0);

        if (empty($nama)) {
            set_flash('error', 'Nama kategori wajib diisi.');
        } else {
            $ins = $db->prepare("INSERT INTO categories (nama, deskripsi, urutan, is_active, created_at) VALUES (:nama, :desk, :urutan, 1, NOW())");
            $ins->execute([':nama' => $nama, ':desk' => $deskripsi, ':urutan' => $urutan]);
            set_flash('success', "Kategori '$nama' berhasil ditambahkan.");
            redirect(BASE_URL . '/pages/categories.php');
        }
    }

    // Toggle Aktif / Nonaktif Kategori
    if ($action === 'toggle_active') {
        $cat_id = (int)($_POST['category_id'] ?? 0);
        $current_state = (int)($_POST['is_active'] ?? 0);
        $new_state = $current_state === 1 ? 0 : 1;

        $db->prepare("UPDATE categories SET is_active = :st WHERE id = :id")->execute([':st' => $new_state, ':id' => $cat_id]);
        set_flash('success', 'Status kategori berhasil diperbarui.');
        redirect(BASE_URL . '/pages/categories.php');
    }
}

// Ambil daftar kategori beserta jumlah tiket
$categories = $db->query("SELECT c.*, COUNT(t.id) AS total_tiket 
                          FROM categories c 
                          LEFT JOIN tickets t ON c.id = t.category_id 
                          GROUP BY c.id 
                          ORDER BY c.urutan ASC, c.nama ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="max-w-5xl mx-auto py-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Kategori Permasalahan IT</h1>
            <p class="text-sm text-slate-500 mt-0.5">Kelola pilihan kategori saat pengguna membuat tiket bantuan</p>
        </div>

        <button type="button" onclick="document.getElementById('modalAddCat').classList.remove('hidden')"
            class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-sm shadow-md shadow-blue-500/20 transition flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
            Tambah Kategori Baru
        </button>
    </div>

    <!-- Table Categories -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <th class="py-3 px-4">Urutan</th>
                        <th class="py-3 px-4">Nama Kategori</th>
                        <th class="py-3 px-4">Deskripsi</th>
                        <th class="py-3 px-4">Total Tiket Terkait</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($categories as $c): ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="py-3 px-4 font-mono text-xs font-bold text-slate-500">
                            <?= $c['urutan'] ?>
                        </td>
                        <td class="py-3 px-4 font-bold text-slate-900">
                            <?= e($c['nama']) ?>
                        </td>
                        <td class="py-3 px-4 text-xs text-slate-600 max-w-xs">
                            <?= e($c['deskripsi'] ?: '-') ?>
                        </td>
                        <td class="py-3 px-4 text-xs font-semibold text-slate-700">
                            <?= $c['total_tiket'] ?> Tiket
                        </td>
                        <td class="py-3 px-4 whitespace-nowrap">
                            <?php if ($c['is_active']): ?>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">Aktif</span>
                            <?php else: ?>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4 text-center whitespace-nowrap">
                            <form method="POST" action="" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="category_id" value="<?= $c['id'] ?>">
                                <input type="hidden" name="is_active" value="<?= $c['is_active'] ?>">
                                <button type="submit" class="px-3 py-1 text-xs font-semibold rounded-lg transition <?= $c['is_active'] ? 'text-rose-600 hover:bg-rose-50' : 'text-emerald-600 hover:bg-emerald-50' ?>">
                                    <?= $c['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah Kategori -->
<div id="modalAddCat" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100">
        <h3 class="text-base font-black text-slate-900 mb-4">Tambah Kategori Kendala Baru</h3>

        <form method="POST" action="" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_category">

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Nama Kategori *</label>
                <input type="text" name="nama" required placeholder="cth: Keamanan Siber, Database" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Deskripsi Singkat</label>
                <textarea name="deskripsi" rows="2" placeholder="Ruang lingkup kendala kategori ini..." class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs"></textarea>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Urutan Tampilan</label>
                <input type="number" name="urutan" value="10" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs">
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('modalAddCat').classList.add('hidden')" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl">Batal</button>
                <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-md">Simpan Kategori</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
