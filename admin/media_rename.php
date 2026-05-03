<?php
declare(strict_types=1);
require __DIR__ . '/../core/bootstrap.php';
require_login();
csrf_check();

$id = (int)($_POST['id'] ?? 0);
$name = trim((string)($_POST['name'] ?? ''));

if ($id <= 0 || $name === '') {
    $_SESSION['flash_error'] = 'Ungueltiger Dateiname.';
    redirect('/admin/media.php');
}

$name = preg_replace('/[^\pL\pN\s._-]+/u', '-', $name) ?? $name;
$name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
$name = function_exists('mb_substr') ? mb_substr($name, 0, 180, 'UTF-8') : substr($name, 0, 180);

if ($name === '') {
    $_SESSION['flash_error'] = 'Ungueltiger Dateiname.';
    redirect('/admin/media.php');
}

db()->prepare('UPDATE media SET original_name = ? WHERE id = ?')->execute([$name, $id]);
$_SESSION['flash'] = 'Dateiname aktualisiert.';
redirect('/admin/media.php');
