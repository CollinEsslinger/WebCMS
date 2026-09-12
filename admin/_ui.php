<?php
require_once __DIR__ . '/../core/bootstrap.php';
require_login();
function ui_start(string $title, string $subtitle = '', string $action = ''): void {
    ?><!doctype html><html lang="de" data-theme="<?= e(safe_theme(setting('theme','light'))) ?>"><head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title) ?> · WebCMS</title><link rel="stylesheet" href="<?= e(site_url('/assets/css/site.css')) ?>"><link rel="stylesheet" href="<?= e(site_url('/assets/css/admin.css')) ?>">
    <link rel="stylesheet" href="<?= e(site_url('/assets/css/workspace.css')) ?>"><style>:root{<?= theme_css_vars() ?>}</style></head><body class="admin-body">
    <?php include __DIR__.'/_sidebar.php'; ?><main class="cms-main workspace-main"><div class="workspace-breadcrumb">ARBEITSPLATZ <span>/</span> <?= e($title) ?></div>
    <header class="workspace-header"><div><h1><?= e($title) ?></h1><p><?= e($subtitle) ?></p></div><div class="action-row"><?= $action ?></div></header>
    <?php foreach (['flash'=>'success','flash_error'=>'danger'] as $key=>$type) if (!empty($_SESSION[$key])) { ?><div class="alert alert-<?= $type ?>" role="status"><?= e($_SESSION[$key]) ?></div><?php unset($_SESSION[$key]); }
}
function ui_end(): void { ?></main><div class="toast-stack" id="toastStack" aria-live="polite"></div><script src="<?= e(site_url('/assets/js/theme.js')) ?>"></script><script src="<?= e(site_url('/assets/js/admin.js')) ?>"></script><script src="<?= e(site_url('/assets/js/workspace.js')) ?>"></script></body></html><?php }
function ui_link(string $label,string $path,string $class='btn-secondary'): string { return '<a class="btn '.$class.'" href="'.e(site_url($path)).'">'.e($label).'</a>'; }
function ui_field(string $name,string $label,$value='',string $type='text',string $hint='',bool $required=false): void {
    $id='f_'.preg_replace('/[^a-z0-9_]/i','_',$name);
    ?><div class="form-group"><label class="form-label" for="<?= e($id) ?>"><?= e($label) ?></label><?php
    if ($type==='textarea') { ?><textarea class="textarea" id="<?= e($id) ?>" name="<?= e($name) ?>" rows="5" <?= $required?'required':'' ?>><?= e((string)$value) ?></textarea><?php }
    else { if ($type==='datetime-local' && $value) $value=str_replace(' ','T',substr((string)$value,0,16)); ?><input class="input" id="<?= e($id) ?>" name="<?= e($name) ?>" type="<?= e($type) ?>" value="<?= e((string)$value) ?>" <?= $required?'required':'' ?>><?php }
    if ($hint) { ?><span class="form-hint"><?= e($hint) ?></span><?php } ?></div><?php
}
function ui_select(string $name,string $label,array $options,$value=''): void { ?><div class="form-group"><label class="form-label" for="f_<?= e($name) ?>"><?= e($label) ?></label><select class="select" id="f_<?= e($name) ?>" name="<?= e($name) ?>"><?php foreach ($options as $k=>$v) { ?><option value="<?= e((string)$k) ?>" <?= (string)$k===(string)$value?'selected':'' ?>><?= e($v) ?></option><?php } ?></select></div><?php }
function ui_form_start(string $action, string $returnTo, array $hidden=[]): void { ?><form method="post" action="<?= e(site_url('/admin/action.php')) ?>" class="stack-form"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="<?= e($action) ?>"><input type="hidden" name="return_to" value="<?= e($returnTo) ?>"><?php foreach ($hidden as $k=>$v) { ?><input type="hidden" name="<?= e($k) ?>" value="<?= e((string)$v) ?>"><?php } }
function ui_submit(string $label='Speichern'): void { ?><button class="btn btn-primary" type="submit"><?= e($label) ?></button></form><?php }
function ui_empty(string $title,string $text): void { ?><div class="workspace-empty"><div class="empty-symbol" aria-hidden="true">＋</div><h3><?= e($title) ?></h3><p><?= e($text) ?></p></div><?php }
function ui_badge(string $status): string {
    $labels=cms_statuses()+['open'=>'Offen','doing'=>'In Arbeit','done'=>'Erledigt','new'=>'Neu','read'=>'Gelesen','pending'=>'Ausstehend','approved'=>'Freigegeben','spam'=>'Spam'];
    return '<span class="badge '.(in_array($status,['published','approved','done'],true)?'badge-success':'badge-warning').'">'.e($labels[$status]??$status).'</span>';
}
