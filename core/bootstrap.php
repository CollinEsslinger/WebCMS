<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

if (DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
}

// ─── SESSION ────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_name('webcms_sid');
    session_start();
}

// Idle-timeout
if (!empty($_SESSION['user_id']) && !empty($_SESSION['last_activity'])) {
    if (time() - (int)$_SESSION['last_activity'] > SESSION_LIFETIME) {
        $_SESSION = [];
        session_destroy();
        session_start();
    }
}
$_SESSION['last_activity'] = time();

// ─── INSTALL CHECK ──────────────────────────────────────────
// If the database is missing core tables, we offer to install.
if (!is_installed()) {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (basename($script) !== 'install.php') {
        header('Location: ' . site_url('/install.php'));
        exit;
    }
}
