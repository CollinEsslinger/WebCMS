<?php
declare(strict_types=1);
require __DIR__ . '/../core/bootstrap.php';
require_login();
csrf_check();

try {
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $newId = save_page([
        'title'            => $_POST['title'] ?? '',
        'parent_id'        => $_POST['parent_id'] ?? null,
        'slug_part'        => $_POST['slug_part'] ?? '',
        'meta_description' => $_POST['meta_description'] ?? '',
        'status'           => $_POST['status'] ?? 'draft',
        'is_home'          => !empty($_POST['is_home']) ? 1 : 0,
        'blocks_json'      => $_POST['blocks_json'] ?? '[]',
    ], $id);
    redirect('/admin/editor.php?id=' . $newId . '&saved=1');
} catch (Throwable $e) {
    $_SESSION['flash_error'] = $e->getMessage();
    redirect('/admin/editor.php' . ($id ? '?id=' . $id : ''));
}
