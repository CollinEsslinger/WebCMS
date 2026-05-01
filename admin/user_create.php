<?php
declare(strict_types=1);
require __DIR__ . '/../core/bootstrap.php';
require_admin();
csrf_check();

try {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = in_array($_POST['role'] ?? 'editor', ['admin','editor'], true) ? $_POST['role'] : 'editor';

    if (strlen($username) < 3) throw new RuntimeException('Benutzername zu kurz.');
    if (strlen($password) < 6) throw new RuntimeException('Passwort zu kurz.');

    $stmt = db()->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    if ($stmt->fetch()) throw new RuntimeException('Benutzername bereits vergeben.');

    $hash = password_hash($password, PASSWORD_DEFAULT);
    db()->prepare('INSERT INTO users (username, email, password_hash, role) VALUES (?,?,?,?)')
        ->execute([$username, $email ?: null, $hash, $role]);
    $_SESSION['flash'] = 'Benutzer "' . $username . '" angelegt.';
} catch (Throwable $e) {
    $_SESSION['flash_error'] = $e->getMessage();
}
redirect('/admin/users.php');
