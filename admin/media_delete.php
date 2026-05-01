<?php
declare(strict_types=1);
require __DIR__ . '/../core/bootstrap.php';
require_login();
csrf_check();

$id = (int)($_POST['id'] ?? 0);
if ($id) delete_media($id);
redirect('/admin/media.php');
