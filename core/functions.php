<?php
/**
 * Core helper functions.
 */

// ─── ESCAPING & URLS ────────────────────────────────────────

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function site_url(string $path = ''): string {
    // Compute base URL from script
    $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';

    // Strip everything up to and including /admin/* or /index.php
    $base = preg_replace('#/admin(/.*)?$#', '', $script);
    $base = preg_replace('#/install\.php$#', '', $base);
    $base = preg_replace('#/page\.php$#', '', $base);
    $base = preg_replace('#/index\.php$#', '', $base);
    $base = rtrim($base, '/');

    return $scheme . '://' . $host . $base . $path;
}

function redirect(string $path): void {
    header('Location: ' . site_url($path));
    exit;
}

function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ─── INSTALL CHECK ──────────────────────────────────────────

function is_installed(): bool {
    try {
        $pdo = db();

        if (DB_DRIVER === 'sqlite') {
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('users','pages','settings')");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            // SHOW TABLES works reliably on shared hosting where information_schema can be restricted.
            $tables = [];
            foreach (['users', 'pages', 'settings'] as $table) {
                $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
                if ($stmt && $stmt->fetchColumn()) {
                    $tables[] = $table;
                }
            }
        }

        foreach (['users', 'pages', 'settings'] as $requiredTable) {
            if (!in_array($requiredTable, $tables, true)) {
                return false;
            }
        }

        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function database_connection_error(): ?string {
    try {
        db()->query('SELECT 1');
        return null;
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

function drop_all_database_tables(PDO $pdo): void {
    if (DB_DRIVER === 'sqlite') {
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
        $pdo->exec('PRAGMA foreign_keys = OFF');
        foreach ($tables as $table) {
            $pdo->exec('DROP TABLE IF EXISTS "' . str_replace('"', '""', $table) . '"');
        }
        $pdo->exec('PRAGMA foreign_keys = ON');
        return;
    }

    $tables = [];
    $stmt = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
    foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $row) {
        if (!empty($row[0])) {
            $tables[] = $row[0];
        }
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $table) {
        $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '``', $table) . '`');
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

function run_install(string $adminUser = DEFAULT_ADMIN_USER, string $adminPass = DEFAULT_ADMIN_PASS): void {
    $pdo = db();
    drop_all_database_tables($pdo);
    $pk = db_pk();

    // ── users ─────────────────────────────────────
    $pdo->exec("CREATE TABLE users (
        id $pk,
        username VARCHAR(80) UNIQUE NOT NULL,
        email VARCHAR(160),
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'editor',
        created_at TIMESTAMP " . db_now_default() . ",
        last_login TIMESTAMP NULL
    )");

    // ── pages ─────────────────────────────────────
    $pdo->exec("CREATE TABLE pages (
        id $pk,
        parent_id INTEGER NULL,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL UNIQUE,
        meta_description TEXT,
        status VARCHAR(20) NOT NULL DEFAULT 'draft',
        is_home INTEGER NOT NULL DEFAULT 0,
        sort_order INTEGER NOT NULL DEFAULT 0,
        blocks_json " . db_longtext() . ",
        created_at TIMESTAMP " . db_now_default() . ",
        updated_at TIMESTAMP NULL
    )");

    // ── media ─────────────────────────────────────
    $pdo->exec("CREATE TABLE media (
        id $pk,
        filename VARCHAR(255) NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        mime VARCHAR(120) NOT NULL,
        size INTEGER NOT NULL,
        uploaded_by INTEGER,
        uploaded_at TIMESTAMP " . db_now_default() . "
    )");

    // ── settings (key/value store) ────────────────
    $pdo->exec("CREATE TABLE settings (
        skey VARCHAR(80) PRIMARY KEY,
        svalue " . db_longtext() . "
    )");

    // ── default admin user ────────────────────────
    $hash = password_hash($adminPass, PASSWORD_DEFAULT);
    $pdo->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)')
        ->execute([$adminUser, $hash, 'admin']);

    // ── default home page ─────────────────────────
    $blocks = default_blocks('Willkommen');
    $pdo->prepare('INSERT INTO pages (title, slug, meta_description, status, is_home, sort_order, blocks_json, updated_at) VALUES (?,?,?,?,?,?,?,?)')
        ->execute(['Startseite','','Willkommen auf der Startseite','published',1,0,
            json_encode($blocks, JSON_UNESCAPED_UNICODE), date('Y-m-d H:i:s')]);

    // ── default settings ──────────────────────────
    $defaults = [
        'site_name'      => SITE_NAME,
        'logo_text'      => SITE_NAME,
        'logo_accent_text' => '.',
        'logo_text_after' => '',
        'site_tagline'   => SITE_TAGLINE,
        'accent_color'   => DEFAULT_ACCENT,
        'theme'          => 'light',
        'font_display'   => 'Syne',
        'font_body'      => 'DM Sans',
        'meta_default'   => 'Eine moderne Website mit WebCMS.',
    ];
    foreach ($defaults as $k => $v) {
        $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?)')
            ->execute([$k, $v]);
    }
}


// ─── SETTINGS ───────────────────────────────────────────────

function setting(string $key, $default = null) {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (db()->query('SELECT skey, svalue FROM settings') as $r) {
                $cache[$r['skey']] = $r['svalue'];
            }
        } catch (Throwable $e) {}
    }
    return $cache[$key] ?? $default;
}

function set_setting(string $key, string $value): void {
    $stmt = db()->prepare('SELECT skey FROM settings WHERE skey = ?');
    $stmt->execute([$key]);
    if ($stmt->fetch()) {
        db()->prepare('UPDATE settings SET svalue = ? WHERE skey = ?')->execute([$value, $key]);
    } else {
        db()->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?)')->execute([$key, $value]);
    }
}

function logo_text(): string {
    return (string)setting('logo_text', SITE_NAME);
}

function logo_accent_text(): string {
    return (string)setting('logo_accent_text', '.');
}

function logo_text_after(): string {
    return (string)setting('logo_text_after', '');
}

// ─── PAGES ──────────────────────────────────────────────────

function fetch_pages(): array {
    return db()->query('SELECT * FROM pages ORDER BY sort_order ASC, id ASC')->fetchAll();
}

function fetch_page_by_id(int $id): ?array {
    $stmt = db()->prepare('SELECT * FROM pages WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $p = $stmt->fetch();
    return $p ?: null;
}

function fetch_page_by_slug(string $slug): ?array {
    $stmt = db()->prepare('SELECT * FROM pages WHERE slug = ? AND status = ? LIMIT 1');
    $stmt->execute([$slug, 'published']);
    $p = $stmt->fetch();
    if ($p) return $p;

    return fetch_page_by_tree_path($slug);
}

function fetch_page_by_tree_path(string $path): ?array {
    $segments = array_values(array_filter(explode('/', trim($path, '/')), static function ($part) {
        return $part !== '';
    }));
    if (count($segments) < 2) return null;

    $pages = db()->query("SELECT * FROM pages WHERE status = 'published' ORDER BY sort_order ASC, id ASC")->fetchAll();
    $byParent = [];
    foreach ($pages as $page) {
        $parentId = $page['parent_id'] !== null ? (int)$page['parent_id'] : null;
        $key = $parentId === null ? 'root' : (string)$parentId;
        $byParent[$key][] = $page;
    }

    $parentId = null;
    $matched = null;
    foreach ($segments as $segment) {
        $key = $parentId === null ? 'root' : (string)$parentId;
        $matched = null;
        foreach ($byParent[$key] ?? [] as $candidate) {
            $candidateSegments = explode('/', trim((string)$candidate['slug'], '/'));
            $localSlug = end($candidateSegments);
            if ($localSlug === $segment) {
                $matched = $candidate;
                break;
            }
        }
        if (!$matched) return null;
        $parentId = (int)$matched['id'];
    }

    return $matched;
}

function fetch_home_page(): ?array {
    $stmt = db()->query('SELECT * FROM pages WHERE is_home = 1 AND status = "published" LIMIT 1');
    $p = $stmt->fetch();
    return $p ?: null;
}

function slugify(string $text): string {
    $text = strtolower($text);
    $text = str_replace(['ä','ö','ü','ß'], ['ae','oe','ue','ss'], $text);
    $text = preg_replace('/[^a-z0-9\-\/]+/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-/');
}

function unique_slug(string $base, ?int $excludeId = null): string {
    $slug = $base ?: 'seite';
    $i = 1;
    while (true) {
        $stmt = db()->prepare('SELECT id FROM pages WHERE slug = ?' . ($excludeId ? ' AND id != ?' : ''));
        $params = [$slug];
        if ($excludeId) $params[] = $excludeId;
        $stmt->execute($params);
        if (!$stmt->fetch()) return $slug;
        $i++;
        $slug = $base . '-' . $i;
    }
}

function build_full_slug(string $localSlug, ?int $parentId): string {
    $local = trim($localSlug, '/');
    if (!$parentId) return $local;
    $parent = fetch_page_by_id($parentId);
    if (!$parent) return $local;
    return trim($parent['slug'] . '/' . $local, '/');
}

function save_page(array $data, ?int $id = null): int {
    $title = trim($data['title'] ?? '');
    if ($title === '') throw new RuntimeException('Titel darf nicht leer sein.');

    $parentId = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
    if ($parentId && $id && $parentId === $id) $parentId = null;
    // Prevent setting parent to a descendant
    if ($parentId && $id && is_descendant($parentId, $id)) {
        $parentId = null;
    }

    $localSlug = $data['slug_part'] ?? $data['slug'] ?? slugify($title);
    $localSlug = slugify($localSlug);
    $fullSlug  = build_full_slug($localSlug, $parentId);
    $fullSlug  = unique_slug($fullSlug, $id);

    $status   = in_array($data['status'] ?? 'draft', ['draft','published'], true) ? $data['status'] : 'draft';
    $isHome   = !empty($data['is_home']) ? 1 : 0;
    $metaDesc = trim((string)($data['meta_description'] ?? ''));
    $blocksJson = $data['blocks_json'] ?? '[]';
    // Validate JSON
    $decoded = json_decode($blocksJson, true);
    if (!is_array($decoded)) $decoded = [];
    $blocksJson = json_encode($decoded, JSON_UNESCAPED_UNICODE);

    // If this page is set as home, unset others
    if ($isHome) {
        $stmt = db()->prepare('UPDATE pages SET is_home = 0' . ($id ? ' WHERE id != ?' : ''));
        $id ? $stmt->execute([$id]) : $stmt->execute();
    }

    $now = date('Y-m-d H:i:s');

    if ($id) {
        $sql = 'UPDATE pages SET title=?, slug=?, parent_id=?, meta_description=?, status=?, is_home=?, blocks_json=?, updated_at=? WHERE id=?';
        db()->prepare($sql)->execute([$title, $fullSlug, $parentId, $metaDesc, $status, $isHome, $blocksJson, $now, $id]);
        // After updating slug, also update slugs of all descendants
        update_descendant_slugs($id);
        return $id;
    }

    // Determine sort_order: append at end of siblings
    $stmt = db()->prepare('SELECT COALESCE(MAX(sort_order),-1)+1 AS so FROM pages WHERE ' . ($parentId ? 'parent_id = ?' : 'parent_id IS NULL'));
    $parentId ? $stmt->execute([$parentId]) : $stmt->execute();
    $sortOrder = (int)$stmt->fetch()['so'];

    $sql = 'INSERT INTO pages (parent_id, title, slug, meta_description, status, is_home, sort_order, blocks_json, updated_at) VALUES (?,?,?,?,?,?,?,?,?)';
    db()->prepare($sql)->execute([$parentId, $title, $fullSlug, $metaDesc, $status, $isHome, $sortOrder, $blocksJson, $now]);
    return (int)db()->lastInsertId();
}

function delete_page(int $id): void {
    $page = fetch_page_by_id($id);
    if (!$page) return;
    if ($page['is_home']) throw new RuntimeException('Startseite kann nicht gelöscht werden.');
    // Detach children to root level
    db()->prepare('UPDATE pages SET parent_id = NULL WHERE parent_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM pages WHERE id = ?')->execute([$id]);
    update_descendant_slugs(null);
}

function is_descendant(int $maybeChildId, int $ancestorId): bool {
    $current = fetch_page_by_id($maybeChildId);
    while ($current && !empty($current['parent_id'])) {
        if ((int)$current['parent_id'] === $ancestorId) return true;
        $current = fetch_page_by_id((int)$current['parent_id']);
    }
    return false;
}

function update_descendant_slugs(?int $parentId): void {
    // For each page whose parent is $parentId, recompute slug from its parent + last segment
    $stmt = db()->prepare('SELECT id, slug FROM pages WHERE ' . ($parentId === null ? 'parent_id IS NULL' : 'parent_id = ?'));
    $parentId === null ? $stmt->execute() : $stmt->execute([$parentId]);
    foreach ($stmt->fetchAll() as $p) {
        $segments = explode('/', $p['slug']);
        $local = end($segments);
        $newSlug = build_full_slug($local, $parentId);
        $newSlug = unique_slug($newSlug, (int)$p['id']);
        db()->prepare('UPDATE pages SET slug = ? WHERE id = ?')->execute([$newSlug, $p['id']]);
        update_descendant_slugs((int)$p['id']);
    }
}

/**
 * Move a page relative to another.
 * @param string $mode 'before' | 'after' | 'inside'
 */
function move_page(int $movedId, int $targetId, string $mode): void {
    if ($movedId === $targetId) return;
    $moved  = fetch_page_by_id($movedId);
    $target = fetch_page_by_id($targetId);
    if (!$moved || !$target) throw new RuntimeException('Seite nicht gefunden.');

    // Prevent moving into own descendant
    if (is_descendant($targetId, $movedId)) {
        throw new RuntimeException('Seite kann nicht in eigene Unterseite verschoben werden.');
    }

    if ($mode === 'inside') {
        // Make moved a child of target, append at end
        $stmt = db()->prepare('SELECT COALESCE(MAX(sort_order),-1)+1 AS so FROM pages WHERE parent_id = ?');
        $stmt->execute([$targetId]);
        $so = (int)$stmt->fetch()['so'];
        db()->prepare('UPDATE pages SET parent_id = ?, sort_order = ? WHERE id = ?')
            ->execute([$targetId, $so, $movedId]);
    } else {
        $newParent = $target['parent_id'] !== null ? (int)$target['parent_id'] : null;
        // Get current siblings of target (without moved)
        $stmt = db()->prepare('SELECT id, sort_order FROM pages WHERE ' . ($newParent === null ? 'parent_id IS NULL' : 'parent_id = ?') . ' AND id != ? ORDER BY sort_order ASC, id ASC');
        $newParent === null ? $stmt->execute([$movedId]) : $stmt->execute([$newParent, $movedId]);
        $siblings = $stmt->fetchAll();
        $newOrder = [];
        foreach ($siblings as $sib) {
            if ((int)$sib['id'] === $targetId && $mode === 'before') $newOrder[] = $movedId;
            $newOrder[] = (int)$sib['id'];
            if ((int)$sib['id'] === $targetId && $mode === 'after')  $newOrder[] = $movedId;
        }
        db()->beginTransaction();
        try {
            if ($newParent === null) {
                db()->prepare('UPDATE pages SET parent_id = NULL WHERE id = ?')->execute([$movedId]);
            } else {
                db()->prepare('UPDATE pages SET parent_id = ? WHERE id = ?')->execute([$newParent, $movedId]);
            }
            $stmtSort = db()->prepare('UPDATE pages SET sort_order = ? WHERE id = ?');
            foreach ($newOrder as $i => $pid) $stmtSort->execute([$i, $pid]);
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            throw $e;
        }
    }
    // Refresh slugs (parent might have changed)
    update_descendant_slugs(null);
}

function page_url(array $page): string {
    if (!empty($page['is_home'])) return site_url('/');
    return site_url('/' . page_public_path($page));
}

function page_public_path(array $page): string {
    $segments = [];
    $current = $page;
    $seen = [];

    while ($current) {
        $id = (int)($current['id'] ?? 0);
        if ($id > 0) {
            if (isset($seen[$id])) break;
            $seen[$id] = true;
        }

        $slugParts = explode('/', trim((string)($current['slug'] ?? ''), '/'));
        $localSlug = end($slugParts);
        if ($localSlug !== false && $localSlug !== '') {
            array_unshift($segments, $localSlug);
        }

        $parentId = $current['parent_id'] !== null ? (int)$current['parent_id'] : null;
        $current = $parentId ? fetch_page_by_id($parentId) : null;
        if ($current && ($current['status'] ?? '') !== 'published') {
            $current = null;
        }
    }

    return implode('/', $segments);
}

// ─── DEFAULT BLOCKS ─────────────────────────────────────────

function default_blocks(string $title = 'Willkommen'): array {
    return [
        [
            'id' => uniqid('b', true),
            'type' => 'hero',
            'settings' => [
                'eyebrow' => 'Willkommen',
                'heading' => $title,
                'text'    => 'Bearbeiten Sie diesen Text direkt im Editor und gestalten Sie Ihre Seite ganz einfach mit Bausteinen.',
                'primaryLabel' => 'Loslegen',
                'primaryUrl'   => '#',
                'secondaryLabel' => 'Mehr erfahren',
                'secondaryUrl'   => '#',
            ],
        ],
        [
            'id' => uniqid('b', true),
            'type' => 'text',
            'settings' => [
                'label'   => 'Über uns',
                'heading' => 'Eine Überschrift',
                'text'    => 'Klicken Sie auf einen Block, um ihn zu bearbeiten. Über die Seitenleiste können Sie weitere Bausteine hinzufügen.',
                'align'   => 'left',
            ],
        ],
    ];
}

// ─── MEDIA ──────────────────────────────────────────────────

function media_items(): array {
    return db()->query('SELECT * FROM media ORDER BY uploaded_at DESC')->fetchAll();
}

function media_url(array $item): string {
    return site_url('/storage/uploads/' . $item['filename']);
}

function delete_media(int $id): void {
    $stmt = db()->prepare('SELECT filename FROM media WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) {
        $path = UPLOAD_DIR . '/' . $row['filename'];
        if (is_file($path)) @unlink($path);
        db()->prepare('DELETE FROM media WHERE id = ?')->execute([$id]);
    }
}

function format_bytes(int $bytes): string {
    $units = ['B','KB','MB','GB'];
    $i = 0;
    $val = (float)$bytes;
    while ($val >= 1024 && $i < count($units) - 1) { $val /= 1024; $i++; }
    return number_format($val, $i === 0 ? 0 : 1, ',', '.') . ' ' . $units[$i];
}

// ─── BLOCK DEFINITIONS ──────────────────────────────────────

function block_types(): array {
    return [
        'hero'         => ['label' => 'Hero',         'icon' => '🦸', 'desc' => 'Großer Einstiegsbereich'],
        'text'         => ['label' => 'Text',         'icon' => '📝', 'desc' => 'Textabsatz mit Überschrift'],
        'cards'        => ['label' => 'Karten',       'icon' => '🃏', 'desc' => 'Karten-Grid (2–6 Karten)'],
        'stats'        => ['label' => 'Statistiken',  'icon' => '📊', 'desc' => 'Kennzahlen-Kacheln'],
        'team'         => ['label' => 'Team',         'icon' => '👥', 'desc' => 'Team-Mitglieder'],
        'pricing'      => ['label' => 'Preise',       'icon' => '💰', 'desc' => 'Preisübersicht'],
        'testimonials' => ['label' => 'Bewertungen',  'icon' => '⭐', 'desc' => 'Kundenstimmen'],
        'accordion'    => ['label' => 'Akkordeon',    'icon' => '📋', 'desc' => 'Ein-/Ausklappbar'],
        'tabs'         => ['label' => 'Tabs',         'icon' => '🗂', 'desc' => 'Tab-Navigation'],
        'timeline'     => ['label' => 'Timeline',     'icon' => '📅', 'desc' => 'Zeitstrahl'],
        'cta'          => ['label' => 'CTA',          'icon' => '📣', 'desc' => 'Call-to-Action'],
        'image'        => ['label' => 'Bild',         'icon' => '🖼', 'desc' => 'Einzelbild'],
        'gallery'      => ['label' => 'Galerie',      'icon' => '🖼️', 'desc' => 'Bildergalerie'],
        'video'        => ['label' => 'Video',        'icon' => '🎬', 'desc' => 'Video-Embed'],
        'form'         => ['label' => 'Formular',     'icon' => '📬', 'desc' => 'Kontaktformular'],
        'code'         => ['label' => 'Code',         'icon' => '💻', 'desc' => 'Code-Block'],
        'quote'        => ['label' => 'Zitat',        'icon' => '❝',  'desc' => 'Zitat-Block'],
        'divider'      => ['label' => 'Trenner',      'icon' => '—',  'desc' => 'Horizontale Linie'],
        'spacer'       => ['label' => 'Abstand',      'icon' => '↕',  'desc' => 'Vertikaler Leerraum'],
        'html'         => ['label' => 'Custom HTML',  'icon' => '⚡', 'desc' => 'Beliebiges HTML'],
    ];
}

function block_default_settings(string $type): array {
    $defaults = [
        'hero' => ['eyebrow'=>'Willkommen','heading'=>'Ihre Vision. Unser Code.','text'=>'Wir bauen moderne Web-Erlebnisse.','primaryLabel'=>'Loslegen','primaryUrl'=>'#','secondaryLabel'=>'Mehr erfahren','secondaryUrl'=>'#'],
        'text' => ['label'=>'','heading'=>'Eine Überschrift','text'=>'Klicken Sie hier, um den Text zu bearbeiten.','align'=>'left'],
        'cards' => ['label'=>'Leistungen','heading'=>'Was wir anbieten','items'=>[
            ['icon'=>'🚀','title'=>'Schnell','text'=>'Maximale Performance.','url'=>'#','urlLabel'=>'Mehr'],
            ['icon'=>'🎨','title'=>'Schön','text'=>'Modernes Design.','url'=>'#','urlLabel'=>'Mehr'],
            ['icon'=>'🔒','title'=>'Sicher','text'=>'DSGVO-konform.','url'=>'#','urlLabel'=>'Mehr'],
        ]],
        'stats' => ['label'=>'Zahlen','heading'=>'In Zahlen','items'=>[
            ['value'=>'92K','label'=>'Downloads','delta'=>'24%','up'=>true,'color'=>'green','arrow'=>'up'],
            ['value'=>'98%','label'=>'Zufriedenheit','delta'=>'3%','up'=>true,'color'=>'green','arrow'=>'up'],
            ['value'=>'48','label'=>'Komponenten','delta'=>'12','up'=>true,'color'=>'green','arrow'=>'up'],
            ['value'=>'8.1K','label'=>'Stars','delta'=>'leicht','up'=>true,'color'=>'green','arrow'=>'up'],
        ]],
        'team' => ['label'=>'Team','heading'=>'Lernen Sie uns kennen','members'=>[
            ['initials'=>'ML','name'=>'Maria Lopez','role'=>'Lead Designer','color'=>'#6366f1'],
            ['initials'=>'AK','name'=>'Alex Klein','role'=>'Frontend Dev','color'=>'#22c55e'],
            ['initials'=>'PW','name'=>'Paula Werner','role'=>'Product Manager','color'=>'#ec4899'],
        ]],
        'pricing' => ['label'=>'Preise','heading'=>'Einfache Preise','plans'=>[
            ['name'=>'Free','price'=>'0€','period'=>'/Monat','features'=>[['text'=>'5 Projekte','icon'=>'check'],['text'=>'Basiskomponenten','icon'=>'check'],['text'=>'Priority Support','icon'=>'x']],'featured'=>false,'badgeLabel'=>'Beliebteste Wahl','buttonLabel'=>'Starten','buttonUrl'=>'#'],
            ['name'=>'Pro','price'=>'19€','period'=>'/Monat','features'=>[['text'=>'Unbegrenzt','icon'=>'check'],['text'=>'Alle Komponenten','icon'=>'check'],['text'=>'Priority Support','icon'=>'check'],['text'=>'Dark Mode','icon'=>'check']],'featured'=>true,'badgeLabel'=>'Beliebteste Wahl','buttonLabel'=>'Upgrade','buttonUrl'=>'#'],
            ['name'=>'Enterprise','price'=>'99€','period'=>'/Monat','features'=>[['text'=>'Alles aus Pro','icon'=>'check'],['text'=>'SLA-Garantie','icon'=>'check'],['text'=>'Dedicated Support','icon'=>'check']],'featured'=>false,'badgeLabel'=>'Beliebteste Wahl','buttonLabel'=>'Kontakt','buttonUrl'=>'#'],
        ]],
        'testimonials' => ['label'=>'Kundenstimmen','heading'=>'Was Kunden sagen','items'=>[
            ['stars'=>5,'text'=>'Herausragende Qualität.','author'=>'Anna M.','initials'=>'AM','color'=>'#6366f1'],
            ['stars'=>5,'text'=>'Das beste CMS, das wir je verwendet haben.','author'=>'Bernd K.','initials'=>'BK','color'=>'#22c55e'],
            ['stars'=>4,'text'=>'Tolle Erfahrung, super Support.','author'=>'Clara S.','initials'=>'CS','color'=>'#ec4899'],
        ]],
        'accordion' => ['label'=>'FAQ','heading'=>'Häufige Fragen','items'=>[
            ['title'=>'Wie lange dauert die Umsetzung?','body'=>'Je nach Projektumfang 2–8 Wochen.'],
            ['title'=>'Welche Technologien?','body'=>'Moderne Web-Technologien: PHP, JavaScript, CSS.'],
            ['title'=>'Gibt es Support?','body'=>'Ja, verschiedene Support-Pakete.'],
        ]],
        'tabs' => ['label'=>'Features','heading'=>'Funktionen','items'=>[
            ['title'=>'Übersicht','content'=>'Alles auf einen Blick.'],
            ['title'=>'Details','content'=>'Vertiefende Informationen.'],
            ['title'=>'Kontakt','content'=>'Treten Sie mit uns in Kontakt.'],
        ]],
        'timeline' => ['label'=>'Geschichte','heading'=>'Unsere Reise','items'=>[
            ['date'=>'2026','title'=>'Version 2.0','body'=>'Neue Features.'],
            ['date'=>'2025','title'=>'Beta','body'=>'250 Early Adopter.'],
            ['date'=>'2024','title'=>'Start','body'=>'Die Reise beginnt.'],
        ]],
        'cta' => ['heading'=>'Bereit loszulegen?','text'=>'Starten Sie jetzt.','buttonLabel'=>'Kostenlos starten','buttonUrl'=>'#'],
        'image' => ['url'=>'','alt'=>'','captionTitle'=>'','captionText'=>'','width'=>'full'],
        'gallery' => ['label'=>'Galerie','heading'=>'Eindrücke','items'=>[
            ['url'=>'','alt'=>'Bild 1'],
            ['url'=>'','alt'=>'Bild 2'],
            ['url'=>'','alt'=>'Bild 3'],
        ]],
        'video' => ['url'=>'','poster'=>'','captionTitle'=>'Video','captionText'=>''],
        'form' => ['label'=>'Kontakt','heading'=>'Schreiben Sie uns','fields'=>[
            ['type'=>'text','label'=>'Name','placeholder'=>'Ihr Name','required'=>true],
            ['type'=>'email','label'=>'E-Mail','placeholder'=>'ihre@email.de','required'=>true],
            ['type'=>'textarea','label'=>'Nachricht','placeholder'=>'Ihre Nachricht…','required'=>true],
        ],'buttonLabel'=>'Absenden','recipient'=>''],
        'code' => ['language'=>'css','code'=>"/* Beispiel CSS */\n:root { --accent: #1ae824; }"],
        'quote' => ['text'=>'Gutes Design ist so wenig Design wie möglich.','author'=>'Dieter Rams'],
        'divider' => ['text'=>'','style'=>'line'],
        'spacer' => ['size'=>'medium'],
        'html' => ['code'=>'<div>Ihr HTML hier</div>'],
    ];
    return $defaults[$type] ?? [];
}
