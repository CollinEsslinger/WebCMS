<?php
declare(strict_types=1);
require __DIR__ . '/../core/bootstrap.php';
require_login();

$pages = fetch_pages();
$user = current_user();

// Build tree
function build_pages_tree(array $pages): array {
    $byId = [];
    foreach ($pages as $p) {
        $p['children'] = [];
        $byId[(int)$p['id']] = $p;
    }
    $roots = [];
    foreach ($byId as $id => &$node) {
        $pid = !empty($node['parent_id']) ? (int)$node['parent_id'] : 0;
        if ($pid && isset($byId[$pid])) {
            $byId[$pid]['children'][] = &$node;
        } else {
            $roots[] = &$node;
        }
    }
    $sortTree = function (&$nodes) use (&$sortTree) {
        usort($nodes, function ($a, $b) {
            $h = ((int)($b['is_home'] ?? 0) <=> (int)($a['is_home'] ?? 0));
            if ($h !== 0) return $h;
            return ((int)($a['sort_order'] ?? 0) <=> (int)($b['sort_order'] ?? 0))
                ?: ((int)$a['id'] <=> (int)$b['id']);
        });
        foreach ($nodes as &$n) if (!empty($n['children'])) $sortTree($n['children']);
    };
    $sortTree($roots);
    return $roots;
}

function render_page_rows(array $nodes, int $level = 0, ?int $parentId = null): void {
    foreach ($nodes as $page) {
        $hasChildren = !empty($page['children']);
        $indent = 12 + ($level * 22);
        $changed = !empty($page['updated_at']) ? date('d.m.Y H:i', strtotime((string)$page['updated_at'])) : '—';
        ?>
        <tr data-page-id="<?= (int)$page['id'] ?>"
            data-parent-id="<?= e((string)($parentId ?? '')) ?>"
            data-level="<?= (int)$level ?>"
            <?= $level > 0 ? 'class="page-tree-hidden"' : '' ?>>
            <td class="page-title-cell" style="padding-left:<?= (int)$indent ?>px">
                <div class="page-title-inner">
                    <span class="drag-handle" data-drag-id="<?= (int)$page['id'] ?>" aria-label="Seite verschieben" tabindex="0">⠿</span>
                    <?php if ($hasChildren): ?>
                        <button type="button" class="tree-toggle" data-toggle-id="<?= (int)$page['id'] ?>" aria-expanded="false" aria-label="Unterseiten ein-/ausklappen"><span class="tree-toggle-icon" aria-hidden="true">▸</span></button>
                    <?php else: ?>
                        <span class="tree-spacer" aria-hidden="true"></span>
                    <?php endif; ?>
                    <span class="page-title-text"><?= e($page['title']) ?>
                        <?php if (!empty($page['is_home'])): ?>
                            <span class="badge badge-accent" style="font-size:.6rem;margin-left:4px">Home</span>
                        <?php endif; ?>
                    </span>
                </div>
            </td>
            <td><span class="page-slug-text">/<?= e($page['slug']) ?></span></td>
            <td>
                <span class="badge page-status-toggle <?= $page['status']==='published' ? 'badge-success' : 'badge-warning' ?>"
                      role="button" tabindex="0"
                      data-page-id="<?= (int)$page['id'] ?>"
                      data-status="<?= e($page['status']) ?>">
                    <?= $page['status'] === 'published' ? 'Veröffentlicht' : 'Entwurf' ?>
                </span>
            </td>
            <td style="font-size:.78rem;color:var(--text-subtle)"><?= e($changed) ?></td>
            <td>
                <div class="action-row">
                    <a class="btn btn-secondary btn-sm" href="<?= e(site_url('/admin/editor.php?id='.$page['id'])) ?>">Bearbeiten</a>
                    <a class="btn btn-ghost btn-sm" href="<?= e(page_url($page)) ?>" target="_blank" rel="noopener" title="Ansehen">↗</a>
                    <div class="move-controls">
                        <button type="button" class="btn btn-ghost btn-sm page-move-btn" data-move="up"      data-id="<?= (int)$page['id'] ?>" title="Nach oben">↑</button>
                        <button type="button" class="btn btn-ghost btn-sm page-move-btn" data-move="down"    data-id="<?= (int)$page['id'] ?>" title="Nach unten">↓</button>
                        <button type="button" class="btn btn-ghost btn-sm page-move-btn" data-move="indent"  data-id="<?= (int)$page['id'] ?>" title="Unterordnen">→</button>
                        <button type="button" class="btn btn-ghost btn-sm page-move-btn" data-move="outdent" data-id="<?= (int)$page['id'] ?>" title="Hochstufen">←</button>
                    </div>
                    <?php if (empty($page['is_home'])): ?>
                        <form method="post" action="<?= e(site_url('/admin/page_delete.php')) ?>" data-confirm="Seite '<?= e($page['title']) ?>' wirklich löschen?" data-confirm-title="Seite löschen" style="display:inline">
                            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= (int)$page['id'] ?>">
                            <button class="btn btn-danger btn-sm" type="submit" title="Löschen">✕</button>
                        </form>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php
        if ($hasChildren) render_page_rows($page['children'], $level + 1, (int)$page['id']);
    }
}

$tree = build_pages_tree($pages);
$totalCount = count($pages);
$publishedCount = count(array_filter($pages, fn($p) => $p['status']==='published'));
$draftCount = $totalCount - $publishedCount;
?>
<!DOCTYPE html>
<html lang="de" data-theme="<?= e(safe_theme(setting('theme','light'))) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title>Seitenverwaltung – WebCMS</title>
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
            <h1>Seitenverwaltung</h1>
            <p>Seiten anordnen, bearbeiten und veröffentlichen</p>
        </div>
        <div class="topbar-actions">
            <button class="btn btn-secondary btn-sm" onclick="expandAll()">Alle aufklappen</button>
            <button class="btn btn-secondary btn-sm" onclick="collapseAll()">Alle zuklappen</button>
            <a class="btn btn-primary" href="<?= e(site_url('/admin/editor.php')) ?>">+ Neue Seite</a>
            <button class="theme-toggle-btn cms-theme-toggle" type="button" data-theme-toggle aria-pressed="false">
                <span class="theme-icon theme-icon-sun" aria-hidden="true">☀</span>
                <span class="theme-icon theme-icon-moon" aria-hidden="true">☾</span>
                <span class="sr-only" data-theme-label>Mond</span>
            </button>
        </div>
    </div>

    <div class="stat-mini-grid">
        <div class="stat-mini">
            <div class="lbl">Seiten gesamt</div>
            <div class="val"><?= $totalCount ?></div>
        </div>
        <div class="stat-mini">
            <div class="lbl">Veröffentlicht</div>
            <div class="val"><?= $publishedCount ?></div>
            <div class="delta" style="color:#22c55e">● Live</div>
        </div>
        <div class="stat-mini">
            <div class="lbl">Entwürfe</div>
            <div class="val"><?= $draftCount ?></div>
            <div class="delta" style="color:#f59e0b">● Nicht veröffentlicht</div>
        </div>
        <div class="stat-mini">
            <div class="lbl">Angemeldet</div>
            <div class="val" style="font-size:.95rem;line-height:1.3"><?= e($user['username']) ?></div>
            <div class="delta" style="color:var(--text-subtle)"><?= e($user['role']) ?></div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <h3>Seitenbaum</h3>
            <div class="flex-row">
                <input class="input" style="width:220px;padding:7px 10px;font-size:.8rem"
                       placeholder="🔍  Seiten suchen…" oninput="filterPages(this.value)">
            </div>
        </div>
        <div class="pages-table-wrap">
            <table class="pages-table" id="pagesTable">
                <thead>
                    <tr>
                        <th style="width:32%">Titel</th>
                        <th>Slug</th>
                        <th>Status</th>
                        <th>Geändert</th>
                        <th style="min-width:240px">Aktionen</th>
                    </tr>
                </thead>
                <tbody><?php render_page_rows($tree); ?></tbody>
            </table>
        </div>
    </div>
</main>

<button class="sidebar-toggle" id="sidebarToggle" onclick="document.getElementById('sidebar').classList.toggle('open')" aria-label="Menü">☰</button>

<div id="drag-preview" aria-hidden="true" style="display:none;position:fixed;top:-999px;left:-999px;z-index:9999">
    <span id="drag-preview-icon">⠿</span>
    <span id="drag-preview-text">Seite</span>
</div>

<div class="toast-stack" id="toastStack" aria-live="polite"></div>

<script src="<?= e(site_url('/assets/js/theme.js')) ?>"></script>
<script src="<?= e(site_url('/assets/js/admin.js')) ?>"></script>
<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const moveUrl = "<?= e(site_url('/admin/page_move.php')) ?>";
    const statusUrl = "<?= e(site_url('/admin/page_toggle_status.php')) ?>";
    initPagesTable({ csrf, moveUrl, statusUrl });
})();
</script>
</body>
</html>
