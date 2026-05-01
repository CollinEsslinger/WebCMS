<?php
declare(strict_types=1);
require __DIR__ . '/../core/bootstrap.php';
require_login();
csrf_check();

try {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) throw new RuntimeException('Ungültige ID.');
    delete_page($id);
} catch (Throwable $e) {
    if (DEBUG) throw $e;
}
redirect('/admin/');
