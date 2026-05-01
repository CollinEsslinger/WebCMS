<?php
declare(strict_types=1);
require __DIR__ . '/core/bootstrap.php';
require __DIR__ . '/core/render.php';

$slug = trim($_GET['slug'] ?? '', '/');
if ($slug === '') {
    redirect('/');
}

$page = fetch_page_by_slug($slug);
if (!$page) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Seite nicht gefunden</title>';
    echo '<link rel="stylesheet" href="' . e(site_url('/assets/css/site.css')) . '"></head><body>';
    echo '<div class="container" style="padding:80px 0;text-align:center"><h1>404 – Seite nicht gefunden</h1>';
    echo '<p style="margin:16px 0">Die Seite "<code>/' . e($slug) . '</code>" existiert nicht.</p>';
    echo '<a class="btn btn-primary" href="' . e(site_url('/')) . '">Zur Startseite</a></div></body></html>';
    exit;
}

echo render_page_html($page);
