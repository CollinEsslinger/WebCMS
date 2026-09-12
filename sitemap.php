<?php
require __DIR__.'/core/bootstrap.php'; header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
foreach (cms_public_pages(true) as $p) if (empty($p['noindex'])) echo '<url><loc>'.e(page_url($p)).'</loc>'.(!empty($p['updated_at'])?'<lastmod>'.e(date('c',strtotime($p['updated_at']))).'</lastmod>':'').'</url>';
foreach (cms_entry_types() as $kind=>$label) foreach (cms_entries($kind,true) as $entry) echo '<url><loc>'.e(site_url('/modules.php?entry='.$entry['id'])).'</loc><lastmod>'.e(date('c',strtotime($entry['updated_at']))).'</lastmod></url>';
echo '</urlset>';
