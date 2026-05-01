<?php
declare(strict_types=1);
require __DIR__ . '/../core/bootstrap.php';
require_login();
csrf_check();

try {
    $userId = (int)($_POST['user_id'] ?? 0);
    $current = current_user();
    if ($userId !== (int)$current['id']) throw new RuntimeException('Ungültige Anfrage.');

    $cur = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $new2 = $_POST['new_password2'] ?? '';

    if ($new !== $new2) throw new RuntimeException('Neue Passwörter stimmen nicht überein.');
    if (strlen($new) < 6) throw new RuntimeException('Passwort zu kurz.');

    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($cur, $row['password_hash'])) {
        throw new RuntimeException('Aktuelles Passwort ist falsch.');
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);
    db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $userId]);
    $_SESSION['flash'] = 'Passwort erfolgreich geändert.';
} catch (Throwable $e) {
    $_SESSION['flash_error'] = $e->getMessage();
}
redirect('/admin/users.php');
