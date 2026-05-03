<?php
declare(strict_types=1);
require __DIR__ . '/../core/bootstrap.php';
require_login();

$media = media_items();
$flash = $_SESSION['flash'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash']);
unset($_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="de" data-theme="<?= e(safe_theme(setting('theme','light'))) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title>Mediathek – WebCMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="<?= e(google_fonts_url()) ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(site_url('/assets/css/site.css')) ?>">
    <link rel="stylesheet" href="<?= e(site_url('/assets/css/admin.css')) ?>">
    <style>:root{<?= theme_css_vars() ?>}</style>
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
    <?php if ($flashError): ?>
        <div class="alert alert-danger" style="margin-bottom:16px">
            <span class="alert-icon">!</span><div><p class="alert-body"><?= e($flashError) ?></p></div>
        </div>
    <?php endif; ?>

    <div class="panel" style="margin-bottom:20px">
        <div class="panel-body">
            <form id="bigUploadForm" action="<?= e(site_url('/admin/media_upload.php')) ?>" method="post" enctype="multipart/form-data">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="redirect" value="media">
                <label class="upload-zone" style="display:block;cursor:pointer" id="dropZone">
                    <input type="file" name="file" id="bigFileInput" style="display:none"
                           accept=".jpg,.jpeg,.png,.webp,.gif,.mp4,.webm,.pdf">
                    <div class="upload-zone-icon">📂</div>
                    <p style="font-size:.875rem;margin-bottom:6px"><strong>Datei hochladen</strong> – klicken oder ablegen</p>
                    <p style="font-size:.78rem;color:var(--text-subtle)">JPG, PNG, WEBP, GIF, MP4, PDF – max. <?= e(format_bytes(effective_upload_max_bytes())) ?></p>
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
                            <button type="button" class="media-card-thumb media-preview-trigger" data-media-id="<?= (int)$m['id'] ?>">
                                <?php if ($isImg): ?>
                                    <img src="<?= e($url) ?>" alt="<?= e($m['original_name']) ?>">
                                <?php elseif (strpos($m['mime'], 'video/') === 0): ?>
                                    <span>🎬</span>
                                <?php elseif ($m['mime'] === 'application/pdf'): ?>
                                    <span>📄</span>
                                <?php else: ?>
                                    <span>📎</span>
                                <?php endif; ?>
                            </button>
                            <div class="media-card-info">
                                <div class="media-card-name" title="<?= e($m['original_name']) ?>"><?= e($m['original_name']) ?></div>
                                <div class="media-card-size"><?= e(format_bytes((int)$m['size'])) ?></div>
                                <button class="btn btn-ghost btn-sm" type="button" data-rename-media="<?= (int)$m['id'] ?>" data-current-name="<?= e($m['original_name']) ?>">Umbenennen</button>
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

<script>
window.CMS_MEDIA_LIBRARY = <?= json_encode(array_map(static function ($m) {
    return [
        'id' => (int)$m['id'],
        'url' => media_url($m),
        'mime' => (string)$m['mime'],
        'name' => (string)$m['original_name'],
    ];
}, $media), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= e(site_url('/assets/js/theme.js')) ?>"></script>
<script src="<?= e(site_url('/assets/js/admin.js')) ?>"></script>
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
const uploadUrl = '<?= e(site_url('/admin/media_upload.php')) ?>';
const deleteUrl = '<?= e(site_url('/admin/media_delete.php')) ?>';

const mediaItems = Array.isArray(window.CMS_MEDIA_LIBRARY) ? window.CMS_MEDIA_LIBRARY : [];
let activeMediaIndex = 0;
let mediaTouchStartX = 0;

function mediaAttr(value) {
    return String(value ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function getOrCreateMediaGrid() {
    let grid = document.querySelector('.media-manager-grid');
    if (!grid) {
        const panels = document.querySelectorAll('.panel');
        const panelBody = panels[panels.length - 1]?.querySelector('.panel-body');
        const empty = panelBody?.querySelector('p');
        if (empty) empty.remove();
        grid = document.createElement('div');
        grid.className = 'media-manager-grid';
        panelBody?.appendChild(grid);
    }
    return grid;
}

function uploadFile(file) {
    const grid = getOrCreateMediaGrid();

    const skeleton = document.createElement('div');
    skeleton.className = 'media-card media-card-skeleton';
    skeleton.innerHTML = `
        <div class="media-card-thumb" aria-hidden="true"></div>
        <div class="media-card-info">
            <div class="media-card-skeleton-bar" style="width:65%"></div>
            <div class="media-card-skeleton-bar" style="width:38%;margin-bottom:0"></div>
        </div>`;
    grid.prepend(skeleton);

    const fd = new FormData();
    fd.append('file', file);
    fd.append('_csrf', csrfToken);
    fd.append('redirect', 'media');
    fd.append('ajax', '1');

    fetch(uploadUrl, { method: 'POST', body: fd })
        .then(async r => {
            let d;
            try { d = await r.json(); } catch { throw new Error('Serverfehler (HTTP ' + r.status + ')'); }
            if (!d.ok) throw new Error(d.message || 'Upload fehlgeschlagen');
            return d;
        })
        .then(data => {
            const isImg = data.mime.startsWith('image/');
            const isVideo = data.mime.startsWith('video/');
            const thumbContent = isImg
                ? `<img src="${mediaAttr(data.url)}" alt="${mediaAttr(data.name)}">`
                : isVideo ? '<span>🎬</span>'
                : data.mime === 'application/pdf' ? '<span>📄</span>'
                : '<span>📎</span>';

            const card = document.createElement('div');
            card.className = 'media-card';
            card.innerHTML = `
                <button type="button" class="media-card-thumb media-preview-trigger" data-media-id="${data.id}">
                    ${thumbContent}
                </button>
                <div class="media-card-info">
                    <div class="media-card-name" title="${mediaAttr(data.name)}">${mediaAttr(data.name)}</div>
                    <div class="media-card-size">${mediaAttr(data.size)}</div>
                    <button class="btn btn-ghost btn-sm" type="button" data-rename-media="${data.id}" data-current-name="${mediaAttr(data.name)}">Umbenennen</button>
                </div>
                <div style="display:flex;gap:4px;padding:6px 8px;border-top:1px solid var(--border)">
                    <button class="btn btn-ghost btn-sm" type="button" data-copy-url="${mediaAttr(data.url)}">📋</button>
                    <a class="btn btn-ghost btn-sm" href="${mediaAttr(data.url)}" target="_blank" rel="noopener">↗</a>
                    <form method="post" action="${mediaAttr(deleteUrl)}" data-confirm="Datei wirklich löschen?" data-confirm-title="Datei löschen" style="margin-left:auto">
                        <input type="hidden" name="_csrf" value="${mediaAttr(csrfToken)}">
                        <input type="hidden" name="id" value="${data.id}">
                        <button class="btn btn-danger btn-sm" type="submit">✕</button>
                    </form>
                </div>`;

            skeleton.replaceWith(card);
            mediaItems.unshift({ id: data.id, url: data.url, mime: data.mime, name: data.name });

            const badge = document.querySelector('.panel-header .badge-neutral');
            if (badge) badge.textContent = String(mediaItems.length);

            toast('success', 'Hochgeladen', `"${mediaAttr(data.name)}" wurde hochgeladen`);
        })
        .catch(err => {
            skeleton.remove();
            toast('error', 'Upload fehlgeschlagen', err.message);
        });
}

// Auto-upload on file select
document.getElementById('bigFileInput')?.addEventListener('change', function() {
    if (this.files?.[0]) {
        uploadFile(this.files[0]);
        this.value = '';
    }
});

// Drag & drop
const dz = document.getElementById('dropZone');
if (dz) {
    dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('drag-over'); });
    dz.addEventListener('dragleave', () => dz.classList.remove('drag-over'));
    dz.addEventListener('drop', e => {
        e.preventDefault();
        dz.classList.remove('drag-over');
        const file = e.dataTransfer.files[0];
        if (file) uploadFile(file);
    });
}

function openMediaPreview(index) {
    activeMediaIndex = (index + mediaItems.length) % mediaItems.length;
    const item = mediaItems[activeMediaIndex];
    if (!item) return;
    let overlay = document.querySelector('.media-lightbox');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'media-lightbox';
        overlay.innerHTML = `
            <button type="button" class="media-lightbox-btn media-lightbox-close" data-media-close>×</button>
            <button type="button" class="media-lightbox-btn media-lightbox-prev" data-media-prev>‹</button>
            <div class="media-lightbox-stage" data-media-stage></div>
            <button type="button" class="media-lightbox-btn media-lightbox-next" data-media-next>›</button>`;
        document.body.appendChild(overlay);
        overlay.addEventListener('click', event => {
            if (event.target === overlay || event.target.closest('[data-media-close]')) overlay.remove();
            if (event.target.closest('[data-media-prev]')) openMediaPreview(activeMediaIndex - 1);
            if (event.target.closest('[data-media-next]')) openMediaPreview(activeMediaIndex + 1);
        });
        overlay.addEventListener('touchstart', event => {
            mediaTouchStartX = event.touches[0]?.clientX || 0;
        }, { passive: true });
        overlay.addEventListener('touchend', event => {
            const delta = (event.changedTouches[0]?.clientX || 0) - mediaTouchStartX;
            if (Math.abs(delta) > 45) openMediaPreview(activeMediaIndex + (delta < 0 ? 1 : -1));
        }, { passive: true });
    }
    const stage = overlay.querySelector('[data-media-stage]');
    if (item.mime.startsWith('video/')) {
        stage.innerHTML = `<video src="${mediaAttr(item.url)}" controls autoplay playsinline></video>`;
    } else if (item.mime.startsWith('image/')) {
        stage.innerHTML = `<img src="${mediaAttr(item.url)}" alt="${mediaAttr(item.name || '')}">`;
    } else {
        stage.innerHTML = `<a class="btn btn-primary" href="${item.url}" target="_blank" rel="noopener">${item.name || 'Datei oeffnen'}</a>`;
    }
}

document.addEventListener('click', event => {
    const trigger = event.target.closest('.media-preview-trigger');
    if (trigger) {
        const id = parseInt(trigger.dataset.mediaId || '0', 10);
        const index = mediaItems.findIndex(item => item.id === id);
        if (index >= 0) openMediaPreview(index);
    }

    const copyBtn = event.target.closest('[data-copy-url]');
    if (copyBtn) {
        navigator.clipboard.writeText(copyBtn.dataset.copyUrl);
        toast('info', 'Kopiert', 'URL in Zwischenablage');
    }

    const rename = event.target.closest('[data-rename-media]');
    if (rename) {
        const current = rename.dataset.currentName || '';
        const name = window.prompt('Neuer Dateiname', current);
        if (!name || name === current) return;
        const form = document.createElement('form');
        form.method = 'post';
        form.action = '<?= e(site_url('/admin/media_rename.php')) ?>';
        form.innerHTML = `<input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="${rename.dataset.renameMedia}"><input type="hidden" name="name">`;
        form.querySelector('[name="name"]').value = name;
        document.body.appendChild(form);
        form.submit();
    }
});

document.addEventListener('keydown', event => {
    if (!document.querySelector('.media-lightbox')) return;
    if (event.key === 'Escape') document.querySelector('.media-lightbox')?.remove();
    if (event.key === 'ArrowLeft') openMediaPreview(activeMediaIndex - 1);
    if (event.key === 'ArrowRight') openMediaPreview(activeMediaIndex + 1);
});
</script>
</body>
</html>
