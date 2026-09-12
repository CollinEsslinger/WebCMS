<?php
$_user = current_user();
$_script = basename($_SERVER['SCRIPT_NAME'] ?? '');
$_view = $_GET['view'] ?? '';
$_links = [
    'Arbeitsplatz' => [
        ['dashboard.php','Übersicht','◈',''],['index.php','Seiten','▤','edit'],['workspace.php?view=tasks','Aufgaben & Freigaben','✓','edit'],
    ],
    'Inhalte' => [
        ['content.php','Sammlungen','▦','modules'],['media.php','Mediathek','▧','media'],['forms.php','Formulare','☷','forms'],
        ['workspace.php?view=inbox','Posteingang','↙','forms'],['workspace.php?view=templates','Vorlagen','◇','edit'],
    ],
    'Verwalten' => [
        ['workspace.php?view=seo','Suche & Weiterleitungen','⌕','edit'],['workspace.php?view=analytics','Statistik','↗','edit'],
        ['workspace.php?view=trash','Papierkorb','↺','edit'],['users.php','Team & Rechte','◎',''],
        ['workspace.php?view=system','Daten & Schnittstellen','⇄','admin'],['settings.php','Einstellungen','⚙','admin'],
    ],
];
?>
<aside class="cms-sidebar" id="sidebar"><a class="sidebar-logo" href="<?= e(site_url('/admin/dashboard.php')) ?>"><div class="sidebar-logo-mark">w</div><span class="sidebar-logo-text">Web<span>CMS</span><small>2.0</small></span></a>
<a class="workspace-site" href="<?= e(site_url('/')) ?>" target="_blank" rel="noopener"><span class="site-dot"></span><?= e(setting('site_name',SITE_NAME)) ?><span>↗</span></a>
<?php foreach ($_links as $_group=>$_items): ?><div class="sidebar-section-label"><?= e($_group) ?></div><nav class="cms-nav" aria-label="<?= e($_group) ?>"><?php foreach ($_items as [$_path,$_label,$_icon,$_cap]):
if ($_cap === 'admin' ? !is_admin() : ($_cap && !cms_can($_cap))) continue;
$_selected = str_starts_with($_path,$_script) && ($_script!=='workspace.php' || str_contains($_path,'view='.$_view)); ?>
<a href="<?= e(site_url('/admin/'.$_path)) ?>" <?= $_selected?'class="active" aria-current="page"':'' ?>><span class="nav-icon" aria-hidden="true"><?= e($_icon) ?></span><?= e($_label) ?></a>
<?php endforeach; ?></nav><?php endforeach; ?>
<div class="sidebar-spacer"></div><div class="sidebar-user"><div class="user-avatar"><?= e(strtoupper(substr($_user['username']??'',0,2))) ?></div><div class="user-info"><strong><?= e($_user['username']??'') ?></strong><span><?= e(['admin'=>'Administration','editor'=>'Redaktion','publisher'=>'Freigabe','author'=>'Autor/in','viewer'=>'Mitglied'][$_user['role']]??'') ?></span></div><a class="sidebar-logout-btn" href="<?= e(site_url('/admin/logout.php')) ?>" aria-label="Abmelden">↪</a></div></aside>
