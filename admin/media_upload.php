<?php
declare(strict_types=1);
require __DIR__ . '/../core/bootstrap.php';
require_login();
csrf_check();

$wantJson = !empty($_POST['ajax']);
$redirect = $_POST['redirect'] ?? '';

try {
    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Datei-Upload fehlgeschlagen.');
    }

    $f = $_FILES['file'];
    if ($f['size'] > UPLOAD_MAX_BYTES) {
        throw new RuntimeException('Datei zu groß. Max. ' . round(UPLOAD_MAX_BYTES/1024/1024) . ' MB.');
    }

    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, UPLOAD_ALLOWED_EXT, true)) {
        throw new RuntimeException('Dateityp nicht erlaubt: .' . $ext);
    }

    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $f['tmp_name']);
    finfo_close($finfo);

    // Sanitize filename
    $base = preg_replace('/[^a-zA-Z0-9_\-]/', '-', pathinfo($f['name'], PATHINFO_FILENAME));
    $base = substr(trim($base, '-'), 0, 60) ?: 'file';
    $filename = $base . '-' . substr(md5(uniqid('', true)), 0, 8) . '.' . $ext;
    $target = UPLOAD_DIR . '/' . $filename;

    if (!move_uploaded_file($f['tmp_name'], $target)) {
        throw new RuntimeException('Datei konnte nicht gespeichert werden.');
    }

    $u = current_user();
    db()->prepare('INSERT INTO media (filename, original_name, mime, size, uploaded_by) VALUES (?,?,?,?,?)')
        ->execute([$filename, $f['name'], $mime, $f['size'], $u['id'] ?? null]);
    $id = (int)db()->lastInsertId();

    if ($wantJson) {
        json_response(['ok' => true, 'id' => $id, 'url' => site_url('/storage/uploads/' . $filename), 'mime' => $mime, 'name' => $f['name']]);
    }

    $_SESSION['flash'] = 'Datei "' . $f['name'] . '" hochgeladen.';
    redirect($redirect === 'media' ? '/admin/media.php' : '/admin/');
} catch (Throwable $e) {
    if ($wantJson) json_response(['ok' => false, 'message' => $e->getMessage()], 400);
    $_SESSION['flash_error'] = $e->getMessage();
    redirect($redirect === 'media' ? '/admin/media.php' : '/admin/');
}
