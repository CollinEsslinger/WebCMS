<?php
declare(strict_types=1);
require __DIR__ . '/../core/bootstrap.php';
require_login();

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$page = $id ? fetch_page_by_id($id) : null;
$saved = !empty($_GET['saved']);

if (!$page) {
    $blocks = default_blocks('Neue Seite');
    $page = [
        'id' => '',
        'title' => 'Neue Seite',
        'slug' => 'neue-seite',
        'parent_id' => null,
        'meta_description' => '',
        'status' => 'draft',
        'is_home' => 0,
        'blocks_json' => json_encode($blocks, JSON_UNESCAPED_UNICODE),
    ];
}

$allPages = fetch_pages();
$slugParts = array_values(array_filter(explode('/', (string)$page['slug'])));
$slugPart = $slugParts ? end($slugParts) : (string)$page['slug'];
$mediaList = media_items();
$blockTypes = block_types();
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="de" data-theme="<?= e(safe_theme(setting('theme','light'))) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title>Editor – <?= e($page['title']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="<?= e(google_fonts_url()) ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(site_url('/assets/css/site.css')) ?>">
    <link rel="stylesheet" href="<?= e(site_url('/assets/css/admin.css')) ?>">
    <style>:root{<?= theme_css_vars() ?>}</style>
</head>
<body class="editor-body">

<button class="theme-toggle-btn cms-theme-toggle editor-theme-toggle" type="button" data-theme-toggle aria-pressed="false">
    <span class="theme-icon theme-icon-sun" aria-hidden="true">☀</span>
    <span class="theme-icon theme-icon-moon" aria-hidden="true">☾</span>
    <span class="sr-only" data-theme-label>Mond</span>
</button>

<div class="editor-shell">
    <aside class="editor-panel" id="editorPanel">
        <div class="editor-panel-head">
            <a class="btn btn-ghost btn-sm" href="<?= e(site_url('/admin/')) ?>">← Übersicht</a>
        </div>

        <form id="pageForm" method="post" action="<?= e(site_url('/admin/page_save.php')) ?>">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= e((string)$page['id']) ?>">
            <input type="hidden" name="blocks_json" id="blocksJson">

            <div class="editor-section">
                <div class="form-group">
                    <label class="form-label" for="title">Seitentitel</label>
                    <input class="input" id="title" name="title" value="<?= e($page['title']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="parent_id">Übergeordnete Seite</label>
                    <select class="select" id="parent_id" name="parent_id">
                        <option value="">— Keine (Hauptseite) —</option>
                        <?php
                        // Build hierarchical option list
                        $byId = [];
                        foreach ($allPages as $p) {
                            $byId[(int)$p['id']] = ['page' => $p, 'children' => []];
                        }
                        $roots = [];
                        foreach ($byId as $pid => &$node) {
                            $par = !empty($node['page']['parent_id']) ? (int)$node['page']['parent_id'] : 0;
                            if ($par && isset($byId[$par])) $byId[$par]['children'][] = &$node;
                            else $roots[] = &$node;
                        }
                        unset($node);
                        $sortFn = function (&$nodes) use (&$sortFn) {
                            usort($nodes, fn($a,$b) => strcasecmp($a['page']['title'],$b['page']['title']));
                            foreach ($nodes as &$n) if (!empty($n['children'])) $sortFn($n['children']);
                        };
                        $sortFn($roots);
                        $renderOpts = function ($nodes, $depth = 0) use (&$renderOpts, $page) {
                            foreach ($nodes as $n) {
                                $p = $n['page'];
                                if ((string)$p['id'] === (string)$page['id']) continue;
                                $prefix = str_repeat('— ', $depth);
                                $sel = ((string)($page['parent_id'] ?? '') !== '' && (int)$page['parent_id'] === (int)$p['id']) ? 'selected' : '';
                                echo '<option value="' . (int)$p['id'] . '" data-slug="' . e((string)$p['slug']) . '" ' . $sel . '>' . e($prefix . $p['title']) . '</option>';
                                if (!empty($n['children'])) $renderOpts($n['children'], $depth + 1);
                            }
                        };
                        $renderOpts($roots);
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="slug_part">URL-Slug</label>
                    <input class="input font-mono" id="slug_part" name="slug_part" value="<?= e($slugPart) ?>" required>
                    <span class="form-hint">Vollständig: /<code id="slugFullPreview"><?= e((string)$page['slug']) ?></code></span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="meta_description">Meta Description</label>
                    <textarea class="textarea" id="meta_description" name="meta_description" rows="2"><?= e($page['meta_description'] ?? '') ?></textarea>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label" for="status">Status</label>
                        <select class="select" id="status" name="status">
                            <option value="draft" <?= $page['status']==='draft'?'selected':'' ?>>Entwurf</option>
                            <option value="published" <?= $page['status']==='published'?'selected':'' ?>>Veröffentlicht</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label">&nbsp;</label>
                        <label class="check-wrap" style="margin-top:8px">
                            <input type="checkbox" name="is_home" value="1" <?= !empty($page['is_home'])?'checked':'' ?>>
                            <span>Startseite</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="divider"></div>

            <div class="editor-section">
                <h4>Blöcke hinzufügen</h4>
                <div class="block-palette">
                    <?php foreach ($blockTypes as $type => $meta): ?>
                        <button type="button" class="block-btn" data-add-block="<?= e($type) ?>" title="<?= e($meta['desc']) ?>">
                            <span class="block-btn-icon"><?= $meta['icon'] ?></span>
                            <span><?= e($meta['label']) ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="divider"></div>

            <div class="editor-section">
                <h4>Medien</h4>
                <label class="upload-zone" for="mediaFile" style="padding:14px;display:block;cursor:pointer;margin-bottom:10px">
                    <div style="font-size:1.4rem;margin-bottom:4px">📎</div>
                    <div style="font-size:.75rem;color:var(--text-subtle)">Datei wählen</div>
                    <div style="font-size:.7rem;color:var(--text-subtle);margin-top:3px">max. <?= e(format_bytes(effective_upload_max_bytes())) ?></div>
                </label>
                <div class="media-grid" id="editorMediaGrid">
                    <?php foreach ($mediaList as $m):
                        $url = media_url($m);
                        $isImg = strpos($m['mime'], 'image/') === 0;
                        $isVideo = strpos($m['mime'], 'video/') === 0;
                    ?>
                        <button type="button" class="media-thumb" data-media-url="<?= e($url) ?>" data-media-mime="<?= e($m['mime']) ?>" title="<?= e($m['original_name']) ?>">
                            <?php if ($isImg): ?>
                                <img src="<?= e($url) ?>" alt="<?= e($m['original_name']) ?>">
                            <?php elseif ($isVideo): ?>
                                <span>Video</span>
                            <?php else: ?>
                                <span>📄</span>
                            <?php endif; ?>
                            <span class="media-thumb-name"><?= e($m['original_name']) ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="divider"></div>

            <div class="editor-actions">
                <button class="btn btn-primary w-full" type="submit">💾  Speichern</button>
                <?php if (!empty($page['id'])): ?>
                    <a class="btn btn-secondary btn-sm" href="<?= e(site_url('/' . ($page['is_home'] ? '' : $page['slug']))) ?>" target="_blank" rel="noopener">Vorschau ↗</a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Media upload – separate form, outside pageForm to avoid nesting -->
        <form id="mediaUploadForm" action="<?= e(site_url('/admin/media_upload.php')) ?>" method="post" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="file" id="mediaFile" name="file" style="display:none"
                   accept=".jpg,.jpeg,.png,.webp,.gif,.mp4,.webm,.pdf">
        </form>
    </aside>

    <main class="editor-canvas">
        <?php if ($saved): ?>
            <div class="alert alert-success" style="margin-bottom:16px">
                <span class="alert-icon">✓</span>
                <div><p class="alert-title">Gespeichert</p><p class="alert-body">Ihre Änderungen wurden übernommen.</p></div>
            </div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="alert alert-danger" style="margin-bottom:16px">
                <span class="alert-icon">✕</span>
                <div><p class="alert-title">Fehler</p><p class="alert-body"><?= e($flashError) ?></p></div>
            </div>
        <?php endif; ?>

        <div id="visualEditor" class="canvas-inner"
             data-initial='<?= e($page['blocks_json'] ?? '[]') ?>'
             data-block-defaults='<?= e(json_encode(array_combine(array_keys($blockTypes), array_map('block_default_settings', array_keys($blockTypes))), JSON_UNESCAPED_UNICODE)) ?>'></div>

    </main>
</div>

<div class="toast-stack" id="toastStack" aria-live="polite"></div>

<script src="<?= e(site_url('/assets/js/theme.js')) ?>"></script>
<script src="<?= e(site_url('/assets/js/admin.js')) ?>"></script>
<script>
window.CMS_MEDIA_LIBRARY = <?= json_encode(array_map(static function ($m) {
    return [
        'id' => (int)$m['id'],
        'url' => media_url($m),
        'mime' => (string)$m['mime'],
        'name' => (string)$m['original_name'],
        'size' => format_bytes((int)$m['size']),
    ];
}, $mediaList), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= e(site_url('/assets/js/editor.js')) ?>"></script>
</body>
</html>
