<?php
require __DIR__.'/../core/bootstrap.php'; require __DIR__.'/../core/render.php'; require_login();
try { $page=cms_assert_page((int)($_GET['id']??0)); } catch (Throwable $e) { http_response_code(403); exit(e($e->getMessage())); }
header('Cache-Control: no-store'); header('X-Robots-Tag: noindex, nofollow');
$html=render_page_html($page);
$banner='<div class="preview-banner">Vorschau des gespeicherten Arbeitsstands · '.e(cms_statuses()[$page['status']]??$page['status']).' · <a href="'.e(site_url('/admin/editor.php?id='.$page['id'])).'">Zurück zum Editor</a></div>';
echo str_replace('<body>','<body>'.$banner,$html);
