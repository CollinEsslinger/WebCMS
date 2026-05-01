<?php
/**
 * Authentication & authorization helpers.
 */

function login_user(string $username, string $password): bool {
    $stmt = db()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) return false;
    if (!password_verify($password, $user['password_hash'])) return false;

    // Regenerate session ID to prevent fixation
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();

    // Update last login
    db()->prepare('UPDATE users SET last_login = ? WHERE id = ?')
        ->execute([date('Y-m-d H:i:s'), $user['id']]);

    return true;
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: ' . site_url('/admin/login.php'));
        exit;
    }
}

function current_user(): ?array {
    if (!is_logged_in()) return null;
    static $cached = null;
    if ($cached !== null) return $cached;
    $stmt = db()->prepare('SELECT id, username, role, email, created_at, last_login FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $cached = $stmt->fetch() ?: null;
    return $cached;
}

function is_admin(): bool {
    $u = current_user();
    return $u && $u['role'] === 'admin';
}

function require_admin(): void {
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        die('Forbidden – Admin-Rechte erforderlich.');
    }
}

// ─── CSRF PROTECTION ────────────────────────────────────────

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check(): void {
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('CSRF-Token ungültig. Bitte Seite neu laden.');
    }
}
