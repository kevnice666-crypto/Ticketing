<?php
// index.php - Entry point aplikasi, redirect ke dashboard atau login

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header("Location: " . BASE_URL . "/pages/dashboard.php");
} else {
    header("Location: " . BASE_URL . "/pages/login.php");
}
exit;
