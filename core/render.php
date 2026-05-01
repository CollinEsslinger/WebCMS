<?php
/**
 * Render saved blocks as HTML for the public site.
 */

function render_blocks(array $blocks): string {
    $out = '';
    foreach ($blocks as $block) {
        $out .= render_block($block);
    }
    return $out;
}

function render_block(array $block): string {
    $type = $block['type'] ?? '';
    $s    = $block['settings'] ?? [];
    $fn   = "render_block_$type";
    if (function_exists($fn)) {
        return $fn($s);
    }
    return '';
}

function initials_from_name(string $name): string {
    $name = trim($name);
    if ($name === '') return '';

    $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
    if (!$parts) return '';

    $firstChar = static function (string $value): string {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, 1, 'UTF-8');
        }
        preg_match('/^./us', $value, $match);
        return $match[0] ?? substr($value, 0, 1);
    };
    $upper = static function (string $value): string {
        if (function_exists('mb_strtoupper')) {
            return mb_strtoupper($value, 'UTF-8');
        }
        return strtoupper($value);
    };

    $initials = '';
    foreach ($parts as $part) {
        $initials .= $firstChar($part);
    }

    return $upper($initials);
}

function stat_color_class(array $stat): string {
    $color = $stat['color'] ?? null;
    if (in_array($color, ['red', 'yellow', 'green'], true)) {
        return 'stat-' . $color;
    }
    return !empty($stat['up']) ? 'stat-green' : 'stat-red';
}

function stat_arrow_symbol(array $stat): string {
    $arrow = $stat['arrow'] ?? null;
    if ($arrow === 'down') return '↓';
    if ($arrow === 'right') return '→';
    return '↑';
}

function stat_delta_text(array $stat): string {
    $delta = trim((string)($stat['delta'] ?? ''));
    return preg_replace('/^[↑↓→]\s*/u', '', $delta) ?? $delta;
}

function pricing_feature_text($feature): string {
    if (is_array($feature)) {
        return (string)($feature['text'] ?? '');
    }
    return (string)$feature;
}

function pricing_feature_icon($feature): string {
    if (is_array($feature) && ($feature['icon'] ?? '') === 'x') {
        return 'x';
    }
    return 'check';
}

function render_multiline_text($text): string {
    return nl2br(e((string)($text ?? '')));
}

function render_block_hero(array $s): string {
    return '<section class="hero">
        <div class="container">
            ' . ($s['eyebrow'] ?? '' ? '<div class="hero-tag">' . e($s['eyebrow']) . '</div>' : '') . '
            <h1>' . e($s['heading'] ?? '') . '</h1>
            <p>' . render_multiline_text($s['text'] ?? '') . '</p>
            <div class="hero-cta">
                ' . (!empty($s['primaryLabel']) ? '<a class="btn btn-primary btn-lg" href="' . e($s['primaryUrl'] ?? '#') . '">' . e($s['primaryLabel']) . '</a>' : '') . '
                ' . (!empty($s['secondaryLabel']) ? '<a class="btn btn-secondary btn-lg" href="' . e($s['secondaryUrl'] ?? '#') . '">' . e($s['secondaryLabel']) . '</a>' : '') . '
            </div>
        </div>
    </section>';
}

function render_block_text(array $s): string {
    $align = ($s['align'] ?? 'left') === 'center' ? 'text-align:center' : '';
    return '<section class="section"><div class="container" style="' . $align . '">
        ' . (!empty($s['label']) ? '<span class="section-label">' . e($s['label']) . '</span>' : '') . '
        ' . (!empty($s['heading']) ? '<h2>' . e($s['heading']) . '</h2>' : '') . '
        <div class="prose">' . render_multiline_text($s['text'] ?? '') . '</div>
    </div></section>';
}

function render_block_cards(array $s): string {
    $items = $s['items'] ?? [];
    $cards = '';
    foreach ($items as $c) {
        $btn = !empty($c['urlLabel']) ? '<div class="card-footer"><a class="btn btn-ghost btn-sm" href="' . e($c['url'] ?? '#') . '">' . e($c['urlLabel']) . ' →</a></div>' : '';
        $cards .= '<div class="card card-interactive">
            <div class="card-icon">' . e($c['icon'] ?? '✦') . '</div>
            <h3>' . e($c['title'] ?? '') . '</h3>
            <p>' . render_multiline_text($c['text'] ?? '') . '</p>
            ' . $btn . '
        </div>';
    }
    return '<section class="section"><div class="container">
        ' . (!empty($s['label']) ? '<span class="section-label">' . e($s['label']) . '</span>' : '') . '
        <h2>' . e($s['heading'] ?? '') . '</h2>
        <div class="grid-3" style="margin-top:24px">' . $cards . '</div>
    </div></section>';
}

function render_block_stats(array $s): string {
    $items = $s['items'] ?? [];
    $cards = '';
    foreach ($items as $st) {
        $delta = stat_delta_text($st);
        $deltaHtml = $delta !== '' ? e(stat_arrow_symbol($st) . ' ' . $delta) : '';
        $cards .= '<div class="stat-card">
            <div class="stat-label">' . e($st['label'] ?? '') . '</div>
            <div class="stat-value">' . e($st['value'] ?? '') . '</div>
            <div class="stat-delta ' . stat_color_class($st) . '">' . $deltaHtml . '</div>
        </div>';
    }
    return '<section class="section"><div class="container">
        ' . (!empty($s['label']) ? '<span class="section-label">' . e($s['label']) . '</span>' : '') . '
        <h2>' . e($s['heading'] ?? '') . '</h2>
        <div class="grid-4" style="margin-top:24px">' . $cards . '</div>
    </div></section>';
}

function render_block_team(array $s): string {
    $members = $s['members'] ?? [];
    $items = '';
    foreach ($members as $m) {
        $color = e($m['color'] ?? '#1ae824');
        $initials = trim((string)($m['initials'] ?? ''));
        if ($initials === '') {
            $initials = initials_from_name((string)($m['name'] ?? ''));
        }
        $items .= '<div class="contact-card">
            <div class="avatar avatar-lg" style="background:' . $color . '">' . e($initials) . '</div>
            <div class="contact-info">
                <div class="contact-name">' . e($m['name'] ?? '') . '</div>
                <div class="contact-role">' . e($m['role'] ?? '') . '</div>
            </div>
        </div>';
    }
    return '<section class="section"><div class="container">
        ' . (!empty($s['label']) ? '<span class="section-label">' . e($s['label']) . '</span>' : '') . '
        <h2>' . e($s['heading'] ?? '') . '</h2>
        <div class="grid-2" style="margin-top:24px">' . $items . '</div>
    </div></section>';
}

function render_block_pricing(array $s): string {
    $plans = $s['plans'] ?? [];
    $cards = '';
    foreach ($plans as $p) {
        $featured = !empty($p['featured']) ? ' featured' : '';
        $badgeText = trim((string)($p['badgeLabel'] ?? 'Beliebteste Wahl'));
        $badge    = !empty($p['featured']) && $badgeText !== '' ? '<div class="pricing-badge">' . e($badgeText) . '</div>' : '';
        $features = '';
        foreach (($p['features'] ?? []) as $f) {
            $icon = pricing_feature_icon($f);
            $features .= '<li class="feature-' . e($icon) . '"><span class="pricing-feature-icon">' . ($icon === 'x' ? '×' : '✓') . '</span><span>' . e(pricing_feature_text($f)) . '</span></li>';
        }
        $btnClass = !empty($p['featured']) ? 'btn-primary' : 'btn-secondary';
        $cards .= '<div class="pricing-card' . $featured . '">
            ' . $badge . '
            <h3>' . e($p['name'] ?? '') . '</h3>
            <div class="pricing-price">' . e($p['price'] ?? '') . ' <span>' . e($p['period'] ?? '') . '</span></div>
            <ul class="pricing-features">' . $features . '</ul>
            <a class="btn ' . $btnClass . '" style="width:100%" href="' . e($p['buttonUrl'] ?? '#') . '">' . e($p['buttonLabel'] ?? 'Auswählen') . '</a>
        </div>';
    }
    return '<section class="section"><div class="container">
        <div class="section-header" style="text-align:center">
            ' . (!empty($s['label']) ? '<span class="section-label">' . e($s['label']) . '</span>' : '') . '
            <h2>' . e($s['heading'] ?? '') . '</h2>
        </div>
        <div class="grid-3" style="max-width:900px;margin:0 auto">' . $cards . '</div>
    </div></section>';
}

function render_block_testimonials(array $s): string {
    $items = $s['items'] ?? [];
    $cards = '';
    foreach ($items as $t) {
        $stars = str_repeat('★', max(0, min(5, (int)($t['stars'] ?? 5))));
        $initials = trim((string)($t['initials'] ?? ''));
        if ($initials === '') {
            $initials = initials_from_name((string)($t['author'] ?? ''));
        }
        $cards .= '<div class="card">
            <div style="color:#f59e0b;font-size:1.1rem;margin-bottom:8px">' . $stars . '</div>
            <p style="font-style:italic;margin-bottom:14px">"' . render_multiline_text($t['text'] ?? '') . '"</p>
            <div style="display:flex;align-items:center;gap:10px">
                <div class="avatar" style="background:' . e($t['color'] ?? '#1ae824') . '">' . e($initials) . '</div>
                <strong style="font-size:.9rem">' . e($t['author'] ?? '') . '</strong>
            </div>
        </div>';
    }
    return '<section class="section"><div class="container">
        ' . (!empty($s['label']) ? '<span class="section-label">' . e($s['label']) . '</span>' : '') . '
        <h2>' . e($s['heading'] ?? '') . '</h2>
        <div class="grid-3" style="margin-top:24px">' . $cards . '</div>
    </div></section>';
}

function render_block_accordion(array $s): string {
    $items = $s['items'] ?? [];
    $rows = '';
    $idx = 0;
    foreach ($items as $it) {
        $rid = 'acc-' . substr(md5(($it['title'] ?? '') . $idx), 0, 8);
        $rows .= '<div class="accordion-item" id="' . $rid . '">
            <button class="accordion-trigger" type="button" data-accordion-trigger aria-expanded="false">
                ' . e($it['title'] ?? '') . ' <span class="accordion-arrow">▼</span>
            </button>
            <div class="accordion-body"><p>' . render_multiline_text($it['body'] ?? '') . '</p></div>
        </div>';
        $idx++;
    }
    return '<section class="section"><div class="container">
        ' . (!empty($s['label']) ? '<span class="section-label">' . e($s['label']) . '</span>' : '') . '
        <h2>' . e($s['heading'] ?? '') . '</h2>
        <div style="max-width:680px;margin-top:24px">' . $rows . '</div>
    </div></section>';
}

function render_block_tabs(array $s): string {
    $items = $s['items'] ?? [];
    $btns = ''; $panels = '';
    foreach ($items as $i => $it) {
        $tid = 'tab-' . substr(md5(($it['title'] ?? '') . $i), 0, 8);
        $active = $i === 0 ? 'active' : '';
        $sel    = $i === 0 ? 'true'   : 'false';
        $btns   .= '<button class="tab-btn ' . $active . '" role="tab" aria-selected="' . $sel . '" onclick="this.closest(\'.tabs-wrap\').querySelectorAll(\'.tab-btn\').forEach(b=>b.classList.remove(\'active\'));this.classList.add(\'active\');this.closest(\'.tabs-wrap\').querySelectorAll(\'.tab-panel\').forEach(p=>p.classList.remove(\'active\'));document.getElementById(\'' . $tid . '\').classList.add(\'active\')">' . e($it['title'] ?? '') . '</button>';
        $panels .= '<div id="' . $tid . '" class="tab-panel ' . $active . '" role="tabpanel"><p>' . render_multiline_text($it['content'] ?? '') . '</p></div>';
    }
    return '<section class="section"><div class="container">
        ' . (!empty($s['label']) ? '<span class="section-label">' . e($s['label']) . '</span>' : '') . '
        <h2>' . e($s['heading'] ?? '') . '</h2>
        <div class="tabs-wrap" style="margin-top:24px">
            <div class="tabs">' . $btns . '</div>
            ' . $panels . '
        </div>
    </div></section>';
}

function render_block_timeline(array $s): string {
    $items = $s['items'] ?? [];
    $rows = '';
    foreach ($items as $it) {
        $rows .= '<div class="timeline-item">
            <div class="timeline-dot"></div>
            <div class="timeline-date">' . e($it['date'] ?? '') . '</div>
            <div class="timeline-title">' . e($it['title'] ?? '') . '</div>
            <div class="timeline-body">' . render_multiline_text($it['body'] ?? '') . '</div>
        </div>';
    }
    return '<section class="section"><div class="container">
        ' . (!empty($s['label']) ? '<span class="section-label">' . e($s['label']) . '</span>' : '') . '
        <h2>' . e($s['heading'] ?? '') . '</h2>
        <div class="timeline" style="max-width:540px;margin-top:24px">' . $rows . '</div>
    </div></section>';
}

function render_block_cta(array $s): string {
    return '<section class="section"><div class="container">
        <div class="card card-accent" style="text-align:center;padding:48px 24px">
            <h2 style="color:#fff;margin-bottom:12px">' . e($s['heading'] ?? '') . '</h2>
            <p style="color:rgba(255,255,255,.85);margin-bottom:20px;max-width:540px;margin-left:auto;margin-right:auto">' . render_multiline_text($s['text'] ?? '') . '</p>
            <a class="btn btn-lg" style="background:#fff;color:var(--accent);border-color:#fff" href="' . e($s['buttonUrl'] ?? '#') . '">' . e($s['buttonLabel'] ?? '') . '</a>
        </div>
    </div></section>';
}

function render_block_image(array $s): string {
    if (empty($s['url'])) return '';
    $w = $s['width'] ?? 'full';
    $maxW = $w === 'narrow' ? '560px' : ($w === 'medium' ? '840px' : '100%');
    $caption = !empty($s['captionTitle']) || !empty($s['captionText'])
        ? '<div class="img-caption"><strong>' . e($s['captionTitle'] ?? '') . '</strong><span>' . e($s['captionText'] ?? '') . '</span></div>'
        : '';
    return '<section class="section"><div class="container">
        <div class="img-card" style="max-width:' . $maxW . ';margin:0 auto">
            <img src="' . e($s['url']) . '" alt="' . e($s['alt'] ?? '') . '">
            ' . $caption . '
        </div>
    </div></section>';
}

function render_block_gallery(array $s): string {
    $items = $s['items'] ?? [];
    $imgs = '';
    foreach ($items as $g) {
        if (empty($g['url'])) continue;
        $imgs .= '<div class="img-card"><img src="' . e($g['url']) . '" alt="' . e($g['alt'] ?? '') . '" style="aspect-ratio:1;object-fit:cover"></div>';
    }
    return '<section class="section"><div class="container">
        ' . (!empty($s['label']) ? '<span class="section-label">' . e($s['label']) . '</span>' : '') . '
        <h2>' . e($s['heading'] ?? '') . '</h2>
        <div class="grid-3" style="margin-top:24px">' . $imgs . '</div>
    </div></section>';
}

function render_block_video(array $s): string {
    if (empty($s['url'])) return '';
    $poster = !empty($s['poster']) ? ' poster="' . e($s['poster']) . '"' : '';
    $caption = !empty($s['captionTitle']) || !empty($s['captionText'])
        ? '<p style="text-align:center;margin-top:12px;font-size:.85rem;color:var(--text-muted)"><strong>' . e($s['captionTitle'] ?? '') . '</strong> ' . e($s['captionText'] ?? '') . '</p>'
        : '';
    return '<section class="section"><div class="container">
        <div class="video-wrap" style="max-width:840px;margin:0 auto">
            <video controls' . $poster . '><source src="' . e($s['url']) . '"></video>
        </div>
        ' . $caption . '
    </div></section>';
}

function render_block_form(array $s): string {
    $fields = '';
    foreach (($s['fields'] ?? []) as $i => $f) {
        $name = 'field_' . $i;
        $req  = !empty($f['required']) ? 'required' : '';
        $lbl  = '<label class="form-label">' . e($f['label'] ?? '') . (!empty($f['required']) ? ' *' : '') . '</label>';
        if (($f['type'] ?? 'text') === 'textarea') {
            $fields .= '<div class="form-group">' . $lbl . '<textarea class="textarea" name="' . $name . '" placeholder="' . e($f['placeholder'] ?? '') . '" rows="4" ' . $req . '></textarea></div>';
        } else {
            $fields .= '<div class="form-group">' . $lbl . '<input class="input" type="' . e($f['type'] ?? 'text') . '" name="' . $name . '" placeholder="' . e($f['placeholder'] ?? '') . '" ' . $req . '></div>';
        }
    }
    return '<section class="section"><div class="container">
        ' . (!empty($s['label']) ? '<span class="section-label">' . e($s['label']) . '</span>' : '') . '
        <h2>' . e($s['heading'] ?? '') . '</h2>
        <form style="max-width:520px;margin-top:24px;display:flex;flex-direction:column;gap:14px" onsubmit="event.preventDefault();alert(\'Formular abgeschickt (Demo)\')">
            ' . $fields . '
            <button class="btn btn-primary" type="submit" style="align-self:flex-start">' . e($s['buttonLabel'] ?? 'Absenden') . '</button>
        </form>
    </div></section>';
}

function render_block_code(array $s): string {
    return '<section class="section"><div class="container">
        <pre><code>' . e($s['code'] ?? '') . '</code></pre>
    </div></section>';
}

function render_block_quote(array $s): string {
    return '<section class="section"><div class="container">
        <blockquote style="border-left:4px solid var(--accent);padding:16px 24px;font-style:italic;font-size:1.2rem;color:var(--text-muted);max-width:680px;margin:0 auto">
            <p style="margin-bottom:8px;color:var(--text-muted)">"' . render_multiline_text($s['text'] ?? '') . '"</p>
            ' . (!empty($s['author']) ? '<cite style="font-size:.875rem;font-style:normal;font-weight:600;color:var(--text)">— ' . e($s['author']) . '</cite>' : '') . '
        </blockquote>
    </div></section>';
}

function render_block_divider(array $s): string {
    if (!empty($s['text'])) {
        return '<div class="container" style="padding:24px 0;display:flex;align-items:center;gap:14px"><div style="flex:1;height:1px;background:var(--border)"></div><span style="font-size:.85rem;color:var(--text-subtle);text-transform:uppercase;letter-spacing:.07em">' . e($s['text']) . '</span><div style="flex:1;height:1px;background:var(--border)"></div></div>';
    }
    return '<div class="container"><div class="divider"></div></div>';
}

function render_block_spacer(array $s): string {
    $sizes = ['small'=>'24px','medium'=>'56px','large'=>'96px'];
    $h = $sizes[$s['size'] ?? 'medium'] ?? '56px';
    return '<div style="height:' . $h . '"></div>';
}

function render_block_html(array $s): string {
    // Trust admin-entered HTML (admins are trusted users)
    return '<div class="container">' . ($s['code'] ?? '') . '</div>';
}

// ─── PAGE TEMPLATE WITH NAV ─────────────────────────────────

function render_page_html(array $page): string {
    $blocks = json_decode($page['blocks_json'] ?? '[]', true) ?: [];
    $bodyHtml = render_blocks($blocks);
    $title = e($page['title']) . ' – ' . e(setting('site_name', SITE_NAME));
    $meta  = e($page['meta_description'] ?? setting('meta_default', ''));
    $accent = e(setting('accent_color', DEFAULT_ACCENT));
    $theme  = e(setting('theme', 'light'));
    $nav    = render_navigation((int)$page['id']);
    $footer = render_footer();

    return '<!DOCTYPE html>
<html lang="' . SITE_LANG . '" data-theme="' . $theme . '">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="' . $meta . '">
    <title>' . $title . '</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="' . e(site_url('/assets/css/site.css')) . '">
    <script src="' . e(site_url('/assets/js/theme.js')) . '" defer></script>
    <style>:root{--accent:' . $accent . ';--accent-light:color-mix(in srgb,var(--accent) 14%,transparent);--accent-dark:color-mix(in srgb,var(--accent) 75%,#000)}</style>
</head>
<body>
' . $nav . '<main class="site-main">' . $bodyHtml . '</main>' . $footer . '
</body>
</html>';
}

function render_navigation(?int $currentPageId = null): string {
    $stmt = db()->query("SELECT id, title, slug, is_home, parent_id FROM pages WHERE status = 'published' ORDER BY sort_order ASC, id ASC");
    $pages = $stmt->fetchAll();
    $publishedIds = [];
    foreach ($pages as $p) {
        $publishedIds[(int)$p['id']] = true;
    }

    $itemsByParent = [];
    $pageById = [];
    foreach ($pages as $p) {
        $id = (int)$p['id'];
        $parentId = $p['parent_id'] !== null ? (int)$p['parent_id'] : null;
        if ($parentId !== null && empty($publishedIds[$parentId])) {
            $parentId = null;
        }
        $key = $parentId === null ? 'root' : (string)$parentId;
        $itemsByParent[$key][] = $p;
        $pageById[$id] = $p;
    }

    $activeIds = [];
    $activeId = $currentPageId;
    while ($activeId !== null && isset($pageById[$activeId])) {
        $activeIds[$activeId] = true;
        $parentId = $pageById[$activeId]['parent_id'] !== null ? (int)$pageById[$activeId]['parent_id'] : null;
        $activeId = $parentId !== null && isset($pageById[$parentId]) ? $parentId : null;
    }
    $logo = e(logo_text());
    $logoAccent = e(logo_accent_text());
    $logoAfter = e(logo_text_after());
    $links = render_navigation_level($itemsByParent, $activeIds);
    return '<nav class="navbar">
        <div class="container navbar-inner">
            <a href="' . e(site_url('/')) . '" class="navbar-logo">' . $logo . '<span>' . $logoAccent . '</span>' . $logoAfter . '</a>
            <ul class="navbar-nav" id="site-navigation" role="list">' . $links . '</ul>
            <div class="navbar-actions">
                <button class="theme-toggle-btn site-theme-toggle" type="button" data-theme-toggle aria-pressed="false">
                    <span class="theme-icon theme-icon-sun" aria-hidden="true">☀</span>
                    <span class="theme-icon theme-icon-moon" aria-hidden="true">☾</span>
                    <span class="sr-only" data-theme-label>Mond</span>
                </button>
                <button class="nav-menu-toggle" type="button" data-nav-toggle aria-expanded="false" aria-controls="site-navigation">
                    <span class="nav-menu-icon" aria-hidden="true"></span>
                    <span class="sr-only">Navigation</span>
                </button>
            </div>
        </div>
    </nav>';
}

function render_navigation_level(array $itemsByParent, array $activeIds, ?int $parentId = null, int $depth = 0): string {
    $key = $parentId === null ? 'root' : (string)$parentId;
    if (empty($itemsByParent[$key])) return '';

    $html = '';
    foreach ($itemsByParent[$key] as $p) {
        $id = (int)$p['id'];
        $children = render_navigation_level($itemsByParent, $activeIds, $id, $depth + 1);
        $hasChildren = $children !== '';
        $classes = ['nav-item'];
        if ($hasChildren) $classes[] = 'has-submenu';
        if (!empty($activeIds[$id])) $classes[] = 'active';

        $html .= '<li class="' . e(implode(' ', $classes)) . '">';
        $html .= '<a class="nav-link" href="' . e(page_url($p)) . '">' . e($p['title']) . '</a>';
        if ($hasChildren) {
            $html .= '<button class="nav-submenu-toggle" type="button" data-submenu-toggle aria-expanded="false">';
            $html .= '<span class="sr-only">Unterseiten von ' . e($p['title']) . '</span>';
            $html .= '</button>';
            $html .= '<ul class="nav-submenu nav-submenu-depth-' . (int)$depth . '" role="list">' . $children . '</ul>';
        }
        $html .= '</li>';
    }

    return $html;
}

function render_footer(): string {
    $name = e(setting('site_name', SITE_NAME));
    return '<footer>
        <div class="container">
            <div class="footer-bottom">
                <p>© ' . date('Y') . ' ' . $name . '</p>
                <p style="font-size:.75rem">Powered by WebCMS (Collin Esslinger)</p>
            </div>
        </div>
    </footer>';
}
