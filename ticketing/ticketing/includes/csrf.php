<?php
// includes/csrf.php - Proteksi Cross-Site Request Forgery (CSRF)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate CSRF token dan simpan di session
 * @return string
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render input hidden CSRF untuk ditaruh di dalam tag <form>
 * @return string
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validasi token CSRF dari request POST atau Header X-CSRF-Token
 * @return bool
 */
function validate_csrf(): bool {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Wajib CSRF valid, hentikan eksekusi jika tidak valid
 */
function require_csrf(): void {
    if (!validate_csrf()) {
        http_response_code(403);
        die('
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <title>403 - CSRF Token Tidak Valid</title>
            <script src="https://cdn.tailwindcss.com"></script>
        </head>
        <body class="bg-slate-100 flex items-center justify-center min-h-screen p-4">
            <div class="max-w-md w-full bg-white rounded-2xl shadow-xl p-6 text-center border border-amber-100">
                <div class="w-16 h-16 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <h1 class="text-xl font-bold text-slate-800 mb-2">Permintaan Ditolak (CSRF)</h1>
                <p class="text-sm text-slate-600 mb-4">Sesi permintaan Anda kedaluwarsa atau token keamanan tidak valid. Silakan kembali dan muat ulang halaman.</p>
                <a href="javascript:history.back()" class="inline-block bg-slate-800 hover:bg-slate-900 text-white font-medium px-5 py-2.5 rounded-xl transition">Kembali</a>
            </div>
        </body>
        </html>
        ');
    }
}
