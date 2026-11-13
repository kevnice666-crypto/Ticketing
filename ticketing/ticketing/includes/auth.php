<?php
// includes/auth.php - Otentikasi, Sesi, Proteksi Brute-Force & Otorisasi Peran

if (session_status() === PHP_SESSION_NONE) {
    // Pengaturan sesi aman
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

/**
 * Cek apakah user sedang login
 * @return bool
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Dapatkan data user yang sedang login dari sesi
 * @return array|null
 */
function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'       => $_SESSION['user_id'],
        'nama'     => $_SESSION['user_nama'],
        'email'    => $_SESSION['user_email'],
        'role'     => $_SESSION['user_role'],
        'avatar'   => $_SESSION['user_avatar'] ?? null,
    ];
}

/**
 * Wajib login, redirect jika belum login
 * @param string|null $redirect_url
 */
function require_login(?string $redirect_url = null): void {
    if (!is_logged_in()) {
        set_flash('warning', 'Silakan login terlebih dahulu untuk mengakses halaman ini.');
        redirect($redirect_url ?: (BASE_URL . '/pages/login.php'));
    }
}

/**
 * Wajib memiliki salah satu peran yang ditentukan
 * @param array $allowed_roles
 */
function require_role(array $allowed_roles): void {
    require_login();
    $role = $_SESSION['user_role'] ?? '';
    if (!in_array($role, $allowed_roles, true)) {
        http_response_code(403);
        include __DIR__ . '/../pages/403.php';
        exit;
    }
}

/**
 * Cek status percobaan login (Brute-force protection)
 * @param string $ip
 * @return array ['blocked' => bool, 'remaining_seconds' => int]
 */
function check_login_attempts(string $ip): array {
    $db = get_db();
    $cutoff = date('Y-m-d H:i:s', time() - LOGIN_LOCKOUT_TIME);
    
    $stmt = $db->prepare("SELECT COUNT(*) AS total, MAX(attempted_at) AS last_attempt 
                          FROM login_attempts 
                          WHERE ip_address = :ip AND attempted_at > :cutoff");
    $stmt->execute([':ip' => $ip, ':cutoff' => $cutoff]);
    $row = $stmt->fetch();

    $attempts = (int)($row['total'] ?? 0);
    if ($attempts >= MAX_LOGIN_ATTEMPTS) {
        $last = strtotime($row['last_attempt']);
        $passed = time() - $last;
        $remaining = max(0, LOGIN_LOCKOUT_TIME - $passed);
        return ['blocked' => true, 'remaining_seconds' => $remaining];
    }

    return ['blocked' => false, 'remaining_seconds' => 0];
}

/**
 * Catat kegagalan login
 * @param string $ip
 * @param string $email
 */
function record_failed_login(string $ip, string $email): void {
    $db = get_db();
    $stmt = $db->prepare("INSERT INTO login_attempts (ip_address, email, attempted_at) VALUES (:ip, :email, NOW())");
    $stmt->execute([':ip' => $ip, ':email' => $email]);
}

/**
 * Hapus catatan percobaan login setelah login berhasil
 * @param string $ip
 */
function clear_login_attempts(string $ip): void {
    $db = get_db();
    $stmt = $db->prepare("DELETE FROM login_attempts WHERE ip_address = :ip");
    $stmt->execute([':ip' => $ip]);
}

/**
 * Login user dan isi sesi
 * @param array $user
 */
function login_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']    = (int)$user['id'];
    $_SESSION['user_nama']  = $user['nama'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role']  = $user['role'];
    $_SESSION['user_avatar']= $user['avatar'] ?? null;
    
    // Reset login attempts
    clear_login_attempts(get_client_ip());
}

/**
 * Logout user
 */
function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}
