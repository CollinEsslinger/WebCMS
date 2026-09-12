<?php
declare(strict_types=1);
require __DIR__ . '/core/bootstrap.php';
require __DIR__ . '/core/render.php';

$page = fetch_home_page();
if (!$page) {
    // No home page set – show the first published page
    $page = cms_public_pages()[0] ?? null;
}

if (!$page) {
    http_response_code(404);
    echo '<h1>Keine Seite gefunden</h1><p>Bitte legen Sie zuerst eine Seite im <a href="' . e(site_url('/admin/')) . '">Admin-Bereich</a> an.</p>';
    exit;
}

cms_count_view($page);
echo render_page_html($page);
