<?php
require __DIR__.'/core/bootstrap.php'; require __DIR__.'/core/render.php';
$html=''; $title='Inhalte';
if (isset($_GET['form'])) {
    $form=cms_row("SELECT * FROM cms_forms WHERE id=? AND status='published'",[(int)$_GET['form']]);
    if (!$form) { http_response_code(404); $html='<p>Dieses Formular ist nicht verfügbar.</p>'; }
    else { $title=$form['title']; $html='<p>'.nl2br(e($form['description']??'')).'</p>'.cms_form_html(json_decode($form['fields_json'],true),['action'=>'form','form_id'=>$form['id']]); }
} elseif (isset($_GET['entry'])) {
    $entry=cms_row('SELECT * FROM content_entries WHERE id=?',[(int)$_GET['entry']]);
    if (!$entry || !cms_entry_visible($entry)) { http_response_code(404); $html='<p>Dieser Inhalt ist nicht verfügbar.</p>'; }
    else {
        $title=$entry['title']; $data=json_decode($entry['data_json']??'{}',true)?:[];
        $html='<p><a href="'.e(site_url('/modules.php?kind='.$entry['kind'])).'">← '.e(cms_entry_types()[$entry['kind']]).'</a></p><article class="entry-detail">';
        if (!empty($data['image'])) $html.='<img src="'.render_media_src($data['image']).'" alt="">';
        $html.='<p><strong>'.nl2br(e($entry['summary']??'')).'</strong></p><div>'.nl2br(e($entry['body']??'')).'</div><dl class="entry-meta">';
        $meta=['location'=>'Ort','address'=>'Adresse','organizer'=>'Veranstalter / Träger','price'=>'Preis / Vergütung','phone'=>'Telefon','email'=>'E-Mail'];
        if ($entry['kind']==='event') { if ($entry['starts_at']) $html.='<div><dt>Beginn</dt><dd>'.e(date('d.m.Y H:i',strtotime($entry['starts_at']))).'</dd></div>'; if ($entry['ends_at']) $html.='<div><dt>Ende</dt><dd>'.e(date('d.m.Y H:i',strtotime($entry['ends_at']))).'</dd></div>'; }
        foreach ($meta as $key=>$label) if (!empty($data[$key])) $html.='<div><dt>'.e($label).'</dt><dd>'.e($data[$key]).'</dd></div>';
        $html.='</dl>';
        if (!empty($data['url'])) $html.='<p><a class="btn btn-primary" href="'.render_href($data['url']).'">Weitere Informationen ↗</a></p>';
        if (!empty($data['address'])) $html.='<p><a href="'.e('https://www.openstreetmap.org/search?query='.rawurlencode($data['address'])).'" target="_blank" rel="noopener noreferrer">Adresse auf der Karte öffnen ↗</a></p>';
        if ($entry['kind']==='event') $html.='<p><a href="'.e(site_url('/feed.php?format=ics&id='.$entry['id'])).'">Zum Kalender hinzufügen (.ics)</a></p>';
        if ($entry['kind']==='poll') {
            $options=explode("\n",$data['options']??''); $counts=array_column(cms_rows('SELECT option_key,COUNT(*) AS total FROM poll_votes WHERE entry_id=? GROUP BY option_key',[$entry['id']]),'total','option_key'); $total=array_sum($counts);
            $html.='<h2>Abstimmen</h2>'.cms_form_html([['type'=>'select','label'=>'Ihre Antwort','required'=>true,'options'=>implode('|',$options)]],['action'=>'vote','entry_id'=>$entry['id']],'Abstimmen').'<h2>Ergebnis · '.$total.' Stimmen</h2><ul class="poll-results">';
            foreach ($options as $i=>$option) { $count=(int)($counts[$i]??0); $html.='<li>'.e($option).' · '.$count.'<meter min="0" max="'.max(1,$total).'" value="'.$count.'">'.$count.'</meter></li>'; } $html.='</ul>';
        }
        $html.='</article>';
        if (in_array($entry['kind'],['news','forum'],true)) {
            $html.='<h2>Kommentare</h2>';
            foreach (cms_rows("SELECT author,body,created_at FROM public_comments WHERE entry_id=? AND status='approved' ORDER BY id",[$entry['id']]) as $comment) $html.='<article class="public-comment"><strong>'.e($comment['author']).'</strong><p>'.nl2br(e($comment['body'])).'</p><small>'.e($comment['created_at']).'</small></article>';
            $html.='<p>Kommentare werden vor der Veröffentlichung geprüft.</p>'.cms_form_html([['label'=>'Name','type'=>'text','required'=>true],['label'=>'Kommentar','type'=>'textarea','required'=>true]],['action'=>'comment','entry_id'=>$entry['id']],'Kommentar einreichen');
        }
    }
} else {
    $kind=(string)($_GET['kind']??'news'); if (!isset(cms_entry_types()[$kind])) { http_response_code(404); echo cms_public_document('Nicht gefunden','<p>Unbekannte Sammlung.</p>'); exit; }
    $title=cms_entry_types()[$kind]; $q=trim((string)($_GET['q']??'')); $category=trim((string)($_GET['category']??''));
    $entries=cms_entries($kind,true); $categories=array_unique(array_filter(array_column($entries,'category'))); sort($categories);
    $html='<form class="public-search" method="get"><input type="hidden" name="kind" value="'.e($kind).'"><input class="input" name="q" aria-label="Suchbegriff" placeholder="Suchen …" value="'.e($q).'"><select class="select" name="category" aria-label="Kategorie"><option value="">Alle Kategorien</option>';
    foreach ($categories as $c) $html.='<option'.($category===$c?' selected':'').'>'.e($c).'</option>';
    $html.='</select><button class="btn btn-primary">Filtern</button></form>';
    $entries=array_values(array_filter($entries,fn($r)=>($category==='' || $r['category']===$category) && ($q==='' || stripos($r['title'].' '.$r['summary'].' '.$r['body'],$q)!==false)));
    if ($kind==='event') usort($entries,fn($a,$b)=>strcmp($a['starts_at']??'',$b['starts_at']??''));
    $offset=max(0,(int)($_GET['offset']??0)); $html.=cms_entry_cards(array_slice($entries,$offset,24));
    if ($offset+24<count($entries)) $html.='<a href="'.e(site_url('/modules.php?'.http_build_query(['kind'=>$kind,'q'=>$q,'category'=>$category,'offset'=>$offset+24]))).'">Weitere Einträge →</a>';
    if ($kind==='newsletter') $html.='<h2>Newsletter abonnieren</h2><p>Sie erhalten eine E-Mail, um Ihre Anmeldung zu bestätigen.</p>'.cms_form_html([['label'=>'E-Mail-Adresse','type'=>'email','required'=>true],['label'=>'Ich möchte den Newsletter erhalten.','type'=>'checkbox','required'=>true]],['action'=>'subscribe'],'Bestätigungslink anfordern');
}
echo cms_public_document($title,$html);
