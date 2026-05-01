<?php
declare(strict_types=1);
require __DIR__ . '/core/bootstrap.php';
require __DIR__ . '/core/render.php';

$page = fetch_home_page();
if (!$page) {
    // No home page set – show the first published page
    $stmt = db()->query("SELECT * FROM pages WHERE status = 'published' ORDER BY sort_order ASC LIMIT 1");
    $page = $stmt->fetch();
}

if (!$page) {
    http_response_code(404);
    echo '<h1>Keine Seite gefunden</h1><p>Bitte legen Sie zuerst eine Seite im <a href="' . e(site_url('/admin/')) . '">Admin-Bereich</a> an.</p>';
    exit;
}

echo render_page_html($page);
