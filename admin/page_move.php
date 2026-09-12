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

    cms_require('publish');
    cms_assert_page($movedId);
    cms_assert_page($targetId);
    move_page($movedId, $targetId, $mode);
    db()->exec('UPDATE pages SET version=version+1');
    cms_audit('page.move','page',$movedId,'Seite verschoben');
    json_response(['ok' => true]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => $e->getMessage()], 400);
}
