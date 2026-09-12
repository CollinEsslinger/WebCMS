<?php
require __DIR__.'/core/bootstrap.php'; require __DIR__.'/core/render.php';
$q=substr(trim((string)($_GET['q']??'')),0,200); $results=[];
if ($q!=='') {
    foreach (cms_public_pages() as $p) {
        if (!empty($p['noindex'])) continue;
        $blocks=json_decode($p['blocks_json']??'[]',true)?:[]; $text=[];
        foreach ($blocks as $block) if ($block['type']!=='html') array_walk_recursive($block['settings'],function($v)use(&$text){if (is_string($v)) $text[]=$v;});
        if (stripos($p['title'].' '.$p['meta_description'].' '.implode(' ',$text),$q)!==false) $results[]=['title'=>$p['title'],'summary'=>$p['meta_description']??'','url'=>page_url($p),'type'=>'Seite'];
    }
    foreach (cms_entry_types() as $kind=>$label) foreach (cms_entries($kind,true) as $entry) if (stripos($entry['title'].' '.$entry['summary'].' '.$entry['body'],$q)!==false) $results[]=['title'=>$entry['title'],'summary'=>$entry['summary']??'','url'=>site_url('/modules.php?entry='.$entry['id']),'type'=>$label];
}
$html='<form class="public-search" method="get"><label class="sr-only" for="q">Suchbegriff</label><input class="input" id="q" name="q" value="'.e($q).'" placeholder="Was suchen Sie?"><button class="btn btn-primary">Suchen</button></form>';
if ($q!=='') { $html.='<p>'.count($results).' Ergebnisse für „'.e($q).'“</p>'; foreach (array_slice($results,0,100) as $r) $html.='<article class="result-item"><small>'.e($r['type']).'</small><h2><a href="'.e($r['url']).'">'.e($r['title']).'</a></h2><p>'.e($r['summary']).'</p></article>'; }
header('X-Robots-Tag: noindex'); echo cms_public_document('Website durchsuchen',$html);
