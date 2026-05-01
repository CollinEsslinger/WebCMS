<?php
declare(strict_types=1);
require __DIR__ . '/../core/bootstrap.php';
require_admin();
csrf_check();

try {
    $id = (int)($_POST['id'] ?? 0);
    $current = current_user();
    if ($id === (int)$current['id']) throw new RuntimeException('Eigenes Konto kann nicht gelöscht werden.');
    if (!$id) throw new RuntimeException('Ungültige ID.');
    db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    $_SESSION['flash'] = 'Benutzer gelöscht.';
} catch (Throwable $e) {
    $_SESSION['flash_error'] = $e->getMessage();
}
redirect('/admin/users.php');
