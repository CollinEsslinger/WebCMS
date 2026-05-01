<?php
declare(strict_types=1);
require __DIR__ . '/../core/bootstrap.php';
require_login();
csrf_check();

try {
    $userId = (int)($_POST['user_id'] ?? 0);
    $current = current_user();
    if (!$current || $userId !== (int)$current['id']) throw new RuntimeException('Ungültige Anfrage.');

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (strlen($username) < 3) throw new RuntimeException('Benutzername zu kurz.');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('E-Mail-Adresse ist ungültig.');
    }

    $stmt = db()->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
    $stmt->execute([$username, $userId]);
    if ($stmt->fetch()) throw new RuntimeException('Benutzername bereits vergeben.');

    db()->prepare('UPDATE users SET username = ?, email = ? WHERE id = ?')
        ->execute([$username, $email ?: null, $userId]);

    $_SESSION['username'] = $username;
    $_SESSION['flash'] = 'Benutzerdaten erfolgreich geändert.';
} catch (Throwable $e) {
    $_SESSION['flash_error'] = $e->getMessage();
}
redirect('/admin/users.php');
