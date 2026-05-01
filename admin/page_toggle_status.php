<?php
declare(strict_types=1);
require __DIR__ . '/../core/bootstrap.php';
require_login();
csrf_check();

header('Content-Type: application/json; charset=UTF-8');

try {
    $id     = (int)($_POST['id'] ?? 0);
    $status = (string)($_POST['status'] ?? '');
    if (!$id || !in_array($status, ['draft','published'], true)) {
        throw new RuntimeException('Ungültige Parameter.');
    }
    db()->prepare('UPDATE pages SET status = ?, updated_at = ? WHERE id = ?')
        ->execute([$status, date('Y-m-d H:i:s'), $id]);
    json_response(['ok' => true]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => $e->getMessage()], 400);
}
