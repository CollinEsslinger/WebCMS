<?php
/** Shared public modules and server-rendered blocks. */
function cms_public_document(string $title,string $html): string {
    $vars=theme_css_vars();
    return '<!doctype html><html lang="'.e(SITE_LANG).'" data-theme="'.e(safe_theme(setting('theme','light'))).'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title.' · '.setting('site_name',SITE_NAME)).'</title><link rel="stylesheet" href="'.e(site_url('/assets/css/site.css')).'"><link rel="stylesheet" href="'.e(site_url('/assets/css/modules.css')).'"><style>:root{'.$vars.'}</style><script src="'.e(site_url('/assets/js/theme.js')).'" defer></script></head><body>'.render_navigation().'<main class="site-main"><section class="section"><div class="container"><h1>'.e($title).'</h1>'.$html.'</div></section></main>'.render_footer().'</body></html>';
}
function cms_form_html(array $fields,array $hidden,string $button='Absenden',array $values=[]): string {
    $out='<form class="public-form" method="post" action="'.e(site_url('/submit.php')).'"><input type="hidden" name="_csrf" value="'.e(csrf_token()).'">';
    foreach ($hidden as $key=>$value) $out.='<input type="hidden" name="'.e($key).'" value="'.e((string)$value).'">';
    $out.='<div class="form-trap" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>';
    $prefix='field_'.bin2hex(random_bytes(4));
    foreach (cms_form_fields($fields) as $i=>$f) {
        $name='field_'.$i; $id=$prefix.'_'.$i; $value=(string)($values[$name]??''); $required=$f['required']?' required':'';
        $label=e($f['label']).($f['required']?' <span aria-label="Pflichtfeld">*</span>':'');
        $out.='<div class="form-group">';
        if ($f['type']==='checkbox') $out.='<label class="check-wrap" for="'.$id.'"><input id="'.$id.'" type="checkbox" name="'.$name.'" value="1"'.($value?' checked':'').$required.'> '.$label.'</label>';
        else {
            $out.='<label class="form-label" for="'.$id.'">'.$label.'</label>';
            if ($f['type']==='textarea') $out.='<textarea class="textarea" id="'.$id.'" name="'.$name.'" rows="5" placeholder="'.e($f['placeholder']).'"'.$required.'>'.e($value).'</textarea>';
            elseif ($f['type']==='select') { $out.='<select class="select" id="'.$id.'" name="'.$name.'"'.$required.'><option value="">Bitte wählen</option>'; foreach (explode('|',$f['options']) as $option) $out.='<option'.($option===$value?' selected':'').'>'.e($option).'</option>'; $out.='</select>'; }
            else $out.='<input class="input" id="'.$id.'" name="'.$name.'" type="'.e($f['type']).'" value="'.e($value).'" placeholder="'.e($f['placeholder']).'"'.$required.'>';
        }
        $out.='</div>';
    }
    return $out.'<p class="form-hint">Mit * gekennzeichnete Felder sind erforderlich.</p><button class="btn btn-primary" type="submit">'.e($button).'</button></form>';
}
function render_block_collection(array $s): string {
    $kind=(string)($s['kind']??'news'); if (!isset(cms_entry_types()[$kind])) return '';
    $entries=cms_entries($kind,true);
    if (!empty($s['category'])) $entries=array_values(array_filter($entries,fn($r)=>$r['category']===$s['category']));
    if (!empty($s['language'])) $entries=array_values(array_filter($entries,fn($r)=>$r['language']===$s['language']));
    if ($kind==='event') usort($entries,fn($a,$b)=>strcmp($a['starts_at']??'',$b['starts_at']??''));
    $entries=array_slice($entries,0,max(1,min(50,(int)($s['limit']??6))));
    return '<section class="section"><div class="container"><h2>'.e($s['heading']??cms_entry_types()[$kind]).'</h2>'.cms_entry_cards($entries).'<p><a href="'.e(site_url('/modules.php?kind='.$kind)).'">Alle anzeigen →</a></p></div></section>';
}
function cms_entry_cards(array $entries): string {
    $html='<div class="public-entry-grid">';
    foreach ($entries as $r) {
        $data=json_decode($r['data_json']??'{}',true)?:[];
        $html.='<article class="card"><span class="section-label">'.e($r['category']?:cms_entry_types()[$r['kind']]).'</span>';
        if (!empty($data['image'])) $html.='<img class="entry-image" src="'.render_media_src($data['image']).'" alt="" loading="lazy">';
        $html.='<h2><a href="'.e(site_url('/modules.php?entry='.$r['id'])).'">'.e($r['title']).'</a></h2>';
        if ($r['kind']==='event' && $r['starts_at']) $html.='<p class="form-hint">'.e(date('d.m.Y H:i',strtotime($r['starts_at']))).' · '.e($data['location']??'').'</p>';
        $html.='<p>'.nl2br(e($r['summary']??'')).'</p></article>';
    }
    return $html.'</div>'.(!$entries?'<p class="module-empty">Zurzeit sind keine Einträge verfügbar.</p>':'');
}
function render_block_managed_form(array $s): string {
    $form=cms_row("SELECT * FROM cms_forms WHERE id=? AND status='published'",[(int)($s['form_id']??0)]);
    if (!$form) return '';
    return '<section class="section"><div class="container"><h2>'.e($s['heading']??$form['title']).'</h2><p>'.nl2br(e($form['description']??'')).'</p>'.cms_form_html(json_decode($form['fields_json'],true),['action'=>'form','form_id'=>$form['id']]).'</div></section>';
}
function render_block_shared(array $s): string {
    static $stack=[]; $id=(int)($s['page_id']??0);
    if (!$id || isset($stack[$id]) || count($stack)>8) return '';
    $page=fetch_page_by_id($id); $page=$page?cms_public_page($page):null;
    if (!$page) return '';
    $stack[$id]=true;
    $previous=$GLOBALS['cms_render_page']??null; $GLOBALS['cms_render_page']=$page;
    try { return render_blocks(json_decode($page['blocks_json']??'[]',true)?:[]); }
    finally { unset($stack[$id]); $GLOBALS['cms_render_page']=$previous; }
}
function render_block_downloads(array $s): string {
    $html='<section class="section"><div class="container"><h2>'.e($s['heading']??'Downloads').'</h2><ul class="download-list">';
    foreach (cms_rows('SELECT * FROM media WHERE folder=? ORDER BY original_name',[(string)($s['folder']??'')]) as $m) $html.='<li><a href="'.e(media_url($m)).'" download>'.e($m['original_name']).'</a><span>'.e(format_bytes((int)$m['size'])).'</span></li>';
    return $html.'</ul></div></section>';
}
function render_block_search(array $s): string {
    return '<section class="section"><div class="container"><h2>'.e($s['heading']??'Website durchsuchen').'</h2><form class="public-search" method="get" action="'.e(site_url('/search.php')).'"><label class="sr-only" for="search-block">Suchbegriff</label><input class="input" id="search-block" name="q" placeholder="Was suchen Sie?" required><button class="btn btn-primary">Suchen</button></form></div></section>';
}
function render_block_map(array $s): string {
    $address=trim((string)($s['address']??'')); $url='https://www.openstreetmap.org/search?query='.rawurlencode($address);
    return '<section class="section"><div class="container"><h2>'.e($s['heading']??'Hier finden Sie uns').'</h2><address>'.nl2br(e($address)).'</address><p><a class="btn btn-secondary" href="'.e($url).'" target="_blank" rel="noopener noreferrer">Karte bei OpenStreetMap öffnen ↗</a></p></div></section>';
}
function cms_count_view(array $page): void {
    if (current_user() || ($_SERVER['REQUEST_METHOD']??'GET')!=='GET' || setting('analytics_enabled','0')!=='1') return;
    $args=[(int)$page['id'],date('Y-m-d')];
    if (DB_DRIVER==='sqlite') db()->prepare('INSERT INTO page_views (page_id,view_date,views) VALUES (?,?,1) ON CONFLICT(page_id,view_date) DO UPDATE SET views=views+1')->execute($args);
    else db()->prepare('INSERT INTO page_views (page_id,view_date,views) VALUES (?,?,1) ON DUPLICATE KEY UPDATE views=views+1')->execute($args);
}
