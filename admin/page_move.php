<?php
declare(strict_types=1);
require __DIR__ . '/../core/bootstrap.php';
require_login();
csrf_check();

header('Content-Type: application/json; charset=UTF-8');

try {
    $movedId  = (int)($_POST['moved_id']  ?? 0);
    $targetId = (int)($_POST['target_id'] ?? 0);
    $mode     = (string)($_POST['mode']   ?? '');

    if (!$movedId || !$targetId || !in_array($mode, ['before','after','inside'], true)) {
        throw new RuntimeException('Ungültige Parameter.');
    }

    move_page($movedId, $targetId, $mode);
    json_response(['ok' => true]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => $e->getMessage()], 400);
}
