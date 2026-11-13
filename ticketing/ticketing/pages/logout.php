<?php
// pages/logout.php - Logout Sesi Pengguna

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

logout_user();
set_flash('info', 'Anda telah berhasil keluar dari sistem.');
redirect(BASE_URL . '/pages/login.php');
