<?php
declare(strict_types=1);
require __DIR__ . '/../core/bootstrap.php';
require_login();
csrf_check();

try {
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $newId = cms_save_page(array_merge($_POST, [
        'title'            => $_POST['title'] ?? '',
        'parent_id'        => $_POST['parent_id'] ?? null,
        'slug_part'        => $_POST['slug_part'] ?? '',
        'meta_description' => $_POST['meta_description'] ?? '',
        'status'           => $_POST['status'] ?? 'draft',
        'is_home'          => !empty($_POST['is_home']) ? 1 : 0,
        'blocks_json'      => $_POST['blocks_json'] ?? '[]',
    ]), $id);
    if (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json') json_response(['ok'=>true,'id'=>$newId]);
    redirect('/admin/editor.php?id=' . $newId . '&saved=1');
} catch (Throwable $e) {
    if (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json') json_response(['ok'=>false,'message'=>$e->getMessage()],400);
    $_SESSION['failed_page_form'] = $_POST;
    $_SESSION['flash_error'] = $e->getMessage();
    redirect('/admin/editor.php' . ($id ? '?id=' . $id : ''));
}
