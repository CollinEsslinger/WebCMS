<?php
require __DIR__.'/core/bootstrap.php';
$resource=(string)($_GET['resource']??'pages'); $offset=max(0,(int)($_GET['offset']??0)); $limit=max(1,min(100,(int)($_GET['limit']??25)));
if ($resource==='pages') {
    $items=[]; foreach (cms_public_pages(true) as $p) if (empty($p['noindex'])) $items[]=['id'=>(int)$p['id'],'title'=>$p['title'],'url'=>page_url($p),'description'=>$p['meta_description'],'language'=>$p['language'],'updated_at'=>$p['updated_at']];
} elseif ($resource==='entries') {
    $kind=(string)($_GET['kind']??'news'); if (!isset(cms_entry_types()[$kind])) json_response(['error'=>'Unbekannter Inhaltstyp'],400);
    $items=array_map(function($e){return ['id'=>(int)$e['id'],'kind'=>$e['kind'],'title'=>$e['title'],'summary'=>$e['summary'],'body'=>$e['body'],'category'=>$e['category'],'language'=>$e['language'],'starts_at'=>$e['starts_at'],'ends_at'=>$e['ends_at'],'data'=>json_decode($e['data_json'],true),'url'=>site_url('/modules.php?entry='.$e['id'])];},cms_entries($kind,true));
} else json_response(['error'=>'Unbekannte Ressource'],400);
json_response(['version'=>CMS_VERSION,'total'=>count($items),'offset'=>$offset,'limit'=>$limit,'items'=>array_slice($items,$offset,$limit)]);
