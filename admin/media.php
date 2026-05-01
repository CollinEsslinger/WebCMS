<?php
declare(strict_types=1);
require __DIR__ . '/../core/bootstrap.php';
require_login();

$media = media_items();
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="de" data-theme="<?= e(setting('theme','light')) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title>Mediathek – WebCMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(site_url('/assets/css/site.css')) ?>">
    <link rel="stylesheet" href="<?= e(site_url('/assets/css/admin.css')) ?>">
    <style>:root{--accent:<?= e(setting('accent_color', DEFAULT_ACCENT)) ?>;--accent-light:color-mix(in srgb,var(--accent) 14%,transparent);--accent-dark:color-mix(in srgb,var(--accent) 75%,#000)}</style>
</head>
<body class="admin-body">
<?php include __DIR__ . '/_sidebar.php'; ?>

<main class="cms-main">
    <div class="cms-topbar">
        <div class="topbar-left">
            <div class="view-header-line"></div>
            <h1>Mediathek</h1>
            <p>Bilder, Videos und Dokumente verwalten</p>
        </div>
        <div class="topbar-actions">
            <button class="theme-toggle-btn cms-theme-toggle" type="button" data-theme-toggle aria-pressed="false">
                <span class="theme-icon theme-icon-sun" aria-hidden="true">☀</span>
                <span class="theme-icon theme-icon-moon" aria-hidden="true">☾</span>
                <span class="sr-only" data-theme-label>Mond</span>
            </button>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-success" style="margin-bottom:16px">
            <span class="alert-icon">✓</span><div><p class="alert-body"><?= e($flash) ?></p></div>
        </div>
    <?php endif; ?>

    <div class="panel" style="margin-bottom:20px">
        <div class="panel-body">
            <form id="bigUploadForm" action="<?= e(site_url('/admin/media_upload.php')) ?>" method="post" enctype="multipart/form-data">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="redirect" value="media">
                <label class="upload-zone" style="display:block;cursor:pointer" id="dropZone">
                    <input type="file" name="file" id="bigFileInput" style="display:none"
                           accept=".jpg,.jpeg,.png,.webp,.gif,.svg,.mp4,.webm,.pdf">
                    <div class="upload-zone-icon">📂</div>
                    <p style="font-size:.875rem;margin-bottom:6px"><strong>Datei hochladen</strong> – klicken oder ablegen</p>
                    <p style="font-size:.78rem;color:var(--text-subtle)">JPG, PNG, WEBP, SVG, MP4, PDF – max. <?= round(UPLOAD_MAX_BYTES / 1024 / 1024) ?> MB</p>
                </label>
            </form>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <h3>Alle Dateien <span class="badge badge-neutral" style="margin-left:6px"><?= count($media) ?></span></h3>
        </div>
        <div class="panel-body" style="padding-top:0">
            <?php if (empty($media)): ?>
                <p style="text-align:center;padding:40px;color:var(--text-subtle)">Noch keine Dateien hochgeladen.</p>
            <?php else: ?>
                <div class="media-manager-grid">
                    <?php foreach ($media as $m):
                        $url = media_url($m);
                        $isImg = strpos($m['mime'], 'image/') === 0;
                    ?>
                        <div class="media-card">
                            <div class="media-card-thumb">
                                <?php if ($isImg): ?>
                                    <img src="<?= e($url) ?>" alt="<?= e($m['original_name']) ?>">
                                <?php elseif (strpos($m['mime'], 'video/') === 0): ?>
                                    <span>🎬</span>
                                <?php elseif ($m['mime'] === 'application/pdf'): ?>
                                    <span>📄</span>
                                <?php else: ?>
                                    <span>📎</span>
                                <?php endif; ?>
                            </div>
                            <div class="media-card-info">
                                <div class="media-card-name" title="<?= e($m['original_name']) ?>"><?= e($m['original_name']) ?></div>
                                <div class="media-card-size"><?= e(format_bytes((int)$m['size'])) ?></div>
                            </div>
                            <div style="display:flex;gap:4px;padding:6px 8px;border-top:1px solid var(--border)">
                                <button class="btn btn-ghost btn-sm" type="button" onclick="navigator.clipboard.writeText('<?= e($url) ?>');toast('📋','Kopiert','URL in Zwischenablage')">📋</button>
                                <a class="btn btn-ghost btn-sm" href="<?= e($url) ?>" target="_blank" rel="noopener">↗</a>
                                <form method="post" action="<?= e(site_url('/admin/media_delete.php')) ?>" data-confirm="Datei wirklich löschen?" data-confirm-title="Datei löschen" style="margin-left:auto">
                                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                                    <button class="btn btn-danger btn-sm" type="submit">✕</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>

<div class="toast-stack" id="toastStack"></div>

<script src="<?= e(site_url('/assets/js/theme.js')) ?>"></script>
<script src="<?= e(site_url('/assets/js/admin.js')) ?>"></script>
<script>
// Auto-submit on file select
document.getElementById('bigFileInput')?.addEventListener('change', function() {
    if (this.files && this.files[0]) document.getElementById('bigUploadForm').submit();
});
// Drag & drop
const dz = document.getElementById('dropZone');
if (dz) {
    dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('drag-over'); });
    dz.addEventListener('dragleave', () => dz.classList.remove('drag-over'));
    dz.addEventListener('drop', e => {
        e.preventDefault();
        dz.classList.remove('drag-over');
        const input = document.getElementById('bigFileInput');
        input.files = e.dataTransfer.files;
        document.getElementById('bigUploadForm').submit();
    });
}
</script>
</body>
</html>
