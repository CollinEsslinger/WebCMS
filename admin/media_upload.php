<?php
declare(strict_types=1);
require __DIR__ . '/../core/bootstrap.php';
require_login();
csrf_check();

$wantJson = !empty($_POST['ajax']);
$redirect = $_POST['redirect'] ?? '';

function upload_error_message(int $code): string {
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Datei zu gross. Aktuelles Server-Limit: ' . format_bytes(effective_upload_max_bytes()) . '.',
        UPLOAD_ERR_PARTIAL => 'Datei wurde nur teilweise hochgeladen.',
        UPLOAD_ERR_NO_FILE => 'Keine Datei ausgewaehlt.',
        UPLOAD_ERR_NO_TMP_DIR => 'Serverfehler: temporaerer Upload-Ordner fehlt.',
        UPLOAD_ERR_CANT_WRITE => 'Serverfehler: Datei konnte nicht geschrieben werden.',
        UPLOAD_ERR_EXTENSION => 'Upload wurde durch eine PHP-Erweiterung gestoppt.',
        default => 'Datei-Upload fehlgeschlagen.',
    };
}

function uploaded_file_starts_with(string $path, string $bytes): bool {
    $handle = @fopen($path, 'rb');
    if (!$handle) return false;
    $data = fread($handle, strlen($bytes));
    fclose($handle);
    return $data === $bytes;
}

function uploaded_file_contains_at(string $path, int $offset, string $bytes): bool {
    $handle = @fopen($path, 'rb');
    if (!$handle) return false;
    fseek($handle, $offset);
    $data = fread($handle, strlen($bytes));
    fclose($handle);
    return $data === $bytes;
}

function detect_upload_mime(string $ext, string $path, string $finfoMime): string {
    $mime = strtolower(trim($finfoMime));

    if ($ext === 'mp4' && uploaded_file_contains_at($path, 4, 'ftyp')) {
        return 'video/mp4';
    }
    if ($ext === 'webm' && uploaded_file_starts_with($path, "\x1A\x45\xDF\xA3")) {
        return 'video/webm';
    }

    return $mime;
}

try {
    if (empty($_FILES['file'])) {
        throw new RuntimeException('Keine Datei ausgewaehlt oder Datei groesser als Server-Limit: ' . format_bytes(effective_upload_max_bytes()) . '.');
    }
    if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(upload_error_message((int)$_FILES['file']['error']));
    }

    $f = $_FILES['file'];
    if ($f['size'] > effective_upload_max_bytes()) {
        throw new RuntimeException('Datei zu gross. Max. ' . format_bytes(effective_upload_max_bytes()) . '.');
    }

    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, UPLOAD_ALLOWED_EXT, true)) {
        throw new RuntimeException('Dateityp nicht erlaubt: .' . $ext);
    }

    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = detect_upload_mime($ext, $f['tmp_name'], (string)finfo_file($finfo, $f['tmp_name']));
    finfo_close($finfo);

    $allowedMimeByExt = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
        'mp4' => ['video/mp4', 'application/mp4', 'video/x-m4v', 'video/quicktime'],
        'webm' => ['video/webm'],
        'pdf' => ['application/pdf'],
    ];
    if (empty($allowedMimeByExt[$ext]) || !in_array($mime, $allowedMimeByExt[$ext], true)) {
        throw new RuntimeException('Dateiinhalt passt nicht zum erlaubten Dateityp. Erkannt: ' . $mime);
    }

    $base = preg_replace('/[^a-zA-Z0-9_\-]/', '-', pathinfo($f['name'], PATHINFO_FILENAME));
    $base = substr(trim((string)$base, '-'), 0, 60) ?: 'file';
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
        json_response([
            'ok' => true,
            'id' => $id,
            'url' => site_url('/storage/uploads/' . $filename),
            'mime' => $mime,
            'name' => $f['name'],
            'size' => format_bytes((int)$f['size']),
        ]);
    }

    $_SESSION['flash'] = 'Datei "' . $f['name'] . '" hochgeladen.';
    redirect($redirect === 'media' ? '/admin/media.php' : '/admin/');
} catch (Throwable $e) {
    if ($wantJson) json_response(['ok' => false, 'message' => $e->getMessage()], 400);
    $_SESSION['flash_error'] = $e->getMessage();
    redirect($redirect === 'media' ? '/admin/media.php' : '/admin/');
}
