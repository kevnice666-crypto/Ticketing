<?php
// pages/users.php - Manajemen Pengguna (Khusus Admin)

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['Admin']);
$db = get_db();
$page_title = 'Kelola Pengguna Sistem';

// Handle Aksi Tambah / Edit / Ubah Status User
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    // 1. Tambah Pengguna Baru
    if ($action === 'create_user') {
        $nama       = trim($_POST['nama'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $role       = trim($_POST['role'] ?? 'User');
        $departemen = trim($_POST['departemen'] ?? '');
        $no_hp      = trim($_POST['no_hp'] ?? '');
        $password   = $_POST['password'] ?? '';

        if (empty($nama) || empty($email) || empty($password)) {
            set_flash('error', 'Nama, email, dan kata sandi wajib diisi.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('error', 'Format email tidak valid.');
        } else {
            $stmt_chk = $db->prepare("SELECT id FROM users WHERE email = :email");
            $stmt_chk->execute([':email' => $email]);
            if ($stmt_chk->fetch()) {
                set_flash('error', 'Email tersebut sudah terdaftar.');
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins = $db->prepare("INSERT INTO users (nama, email, password, role, departemen, no_hp, status, created_at)
                                     VALUES (:nama, :email, :pass, :role, :dept, :hp, 'Aktif', NOW())");
                $ins->execute([
                    ':nama'  => $nama,
                    ':email' => $email,
                    ':pass'  => $hash,
                    ':role'  => $role,
                    ':dept'  => $departemen,
                    ':hp'    => $no_hp,
                ]);
                set_flash('success', "Akun pengguna $nama berhasil ditambahkan.");
                redirect(BASE_URL . '/pages/users.php');
            }
        }
    }

    // 2. Toggle Status Aktif / Nonaktif
    if ($action === 'toggle_status') {
        $target_id = (int)($_POST['user_id'] ?? 0);
        $new_status = $_POST['status'] === 'Aktif' ? 'Nonaktif' : 'Aktif';

        if ($target_id === (int)$_SESSION['user_id']) {
            set_flash('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
        } else {
            $db->prepare("UPDATE users SET status = :st WHERE id = :id")->execute([':st' => $new_status, ':id' => $target_id]);
            set_flash('success', "Status akun berhasil diubah menjadi $new_status.");
        }
        redirect(BASE_URL . '/pages/users.php');
    }

    // 3. Reset Password User
    if ($action === 'reset_password') {
        $target_id = (int)($_POST['user_id'] ?? 0);
        $new_pass = $_POST['new_password'] ?? '';
        if (strlen($new_pass) < 6) {
            set_flash('error', 'Password baru minimal 6 karakter.');
        } else {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $db->prepare("UPDATE users SET password = :p WHERE id = :id")->execute([':p' => $hash, ':id' => $target_id]);
            set_flash('success', 'Kata sandi pengguna berhasil diperbarui.');
        }
        redirect(BASE_URL . '/pages/users.php');
    }
}

// Ambil daftar seluruh user
$users = $db->query("SELECT u.*, 
                            (SELECT COUNT(*) FROM tickets WHERE user_id = u.id) AS total_tiket_dibuat,
                            (SELECT COUNT(*) FROM tickets WHERE assigned_to = u.id) AS total_tiket_ditugaskan
                     FROM users u 
                     ORDER BY FIELD(u.role, 'Admin', 'IT Support', 'User'), u.nama ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="py-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Manajemen Pengguna</h1>
            <p class="text-sm text-slate-500 mt-0.5">Kelola akun Admin, teknisi IT Support, dan pegawai (User)</p>
        </div>

        <button type="button" onclick="document.getElementById('modalAddUser').classList.remove('hidden')"
            class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-sm shadow-md shadow-blue-500/20 transition flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
            Tambah Pengguna Baru
        </button>
    </div>

    <!-- Table Users -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <th class="py-3.5 px-4">Pengguna</th>
                        <th class="py-3.5 px-4">Kontak & Departemen</th>
                        <th class="py-3.5 px-4">Peran (Role)</th>
                        <th class="py-3.5 px-4">Status</th>
                        <th class="py-3.5 px-4">Aktivitas Tiket</th>
                        <th class="py-3.5 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($users as $u): ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="py-3.5 px-4">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-slate-100 border border-slate-200 flex items-center justify-center font-bold text-slate-700 text-xs">
                                    <?= strtoupper(substr($u['nama'], 0, 1)) ?>
                                </div>
                                <div>
                                    <span class="font-bold text-slate-900 block"><?= e($u['nama']) ?></span>
                                    <span class="text-xs text-slate-400"><?= e($u['email']) ?></span>
                                </div>
                            </div>
                        </td>
                        <td class="py-3.5 px-4 text-xs">
                            <span class="font-semibold text-slate-700 block"><?= e($u['departemen'] ?: 'Umum / Staf') ?></span>
                            <span class="text-slate-400"><?= e($u['no_hp'] ?: '-') ?></span>
                        </td>
                        <td class="py-3.5 px-4 whitespace-nowrap">
                            <?php if ($u['role'] === 'Admin'): ?>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-purple-100 text-purple-800 border border-purple-200">Admin</span>
                            <?php elseif ($u['role'] === 'IT Support'): ?>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800 border border-blue-200">IT Support</span>
                            <?php else: ?>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">User</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3.5 px-4 whitespace-nowrap">
                            <?php if ($u['status'] === 'Aktif'): ?>
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    Aktif
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                    Nonaktif
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3.5 px-4 text-xs text-slate-500">
                            <?php if ($u['role'] === 'User'): ?>
                                <span><?= $u['total_tiket_dibuat'] ?> Tiket diajukan</span>
                            <?php else: ?>
                                <span><?= $u['total_tiket_ditugaskan'] ?> Tiket ditugaskan</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3.5 px-4 text-center whitespace-nowrap">
                            <div class="inline-flex items-center gap-2">
                                <!-- Reset Password Form -->
                                <button type="button" onclick="openResetPasswordModal(<?= $u['id'] ?>, '<?= e($u['nama']) ?>')" class="px-2.5 py-1 text-xs font-semibold text-slate-600 hover:text-blue-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition">
                                    Ganti Sandi
                                </button>

                                <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                                <form method="POST" action="" onsubmit="return confirm('Ubah status akun ini?');" class="inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="status" value="<?= $u['status'] ?>">
                                    <button type="submit" class="px-2.5 py-1 text-xs font-semibold <?= $u['status'] === 'Aktif' ? 'text-rose-600 hover:bg-rose-50' : 'text-emerald-600 hover:bg-emerald-50' ?> rounded-lg transition">
                                        <?= $u['status'] === 'Aktif' ? 'Nonaktifkan' : 'Aktifkan' ?>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah User -->
<div id="modalAddUser" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-100">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-5">
            <h3 class="text-lg font-black text-slate-900">Tambah Akun Pengguna</h3>
            <button type="button" onclick="document.getElementById('modalAddUser').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form method="POST" action="" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_user">

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Nama Lengkap *</label>
                <input type="text" name="nama" required class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Alamat Email *</label>
                <input type="email" name="email" required class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Peran (Role) *</label>
                    <select name="role" required class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs bg-white">
                        <option value="User">User (Pegawai)</option>
                        <option value="IT Support">IT Support</option>
                        <option value="Admin">Administrator</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Departemen / Divisi</label>
                    <input type="text" name="departemen" placeholder="cth: Keuangan" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">No. Handphone / WA</label>
                    <input type="text" name="no_hp" placeholder="0812xxxxxxxx" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Kata Sandi Awal *</label>
                    <input type="password" name="password" required minlength="6" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs">
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('modalAddUser').classList.add('hidden')" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl">Batal</button>
                <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-md">Simpan Akun</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Reset Password -->
<div id="modalResetPass" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100">
        <h3 class="text-base font-black text-slate-900 mb-1">Reset Kata Sandi Pengguna</h3>
        <p class="text-xs text-slate-500 mb-4" id="resetTargetName"></p>

        <form method="POST" action="" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="resetUserId" value="0">

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Kata Sandi Baru (Min. 6 Karakter)</label>
                <input type="password" name="new_password" required minlength="6" placeholder="••••••••" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs">
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('modalResetPass').classList.add('hidden')" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl">Batal</button>
                <button type="submit" class="px-5 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-xl">Perbarui Sandi</button>
            </div>
        </form>
    </div>
</div>

<script>
function openResetPasswordModal(userId, userName) {
    document.getElementById('resetUserId').value = userId;
    document.getElementById('resetTargetName').textContent = 'Memperbarui kata sandi untuk: ' + userName;
    document.getElementById('modalResetPass').classList.remove('hidden');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
