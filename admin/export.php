<?php
require __DIR__.'/../core/bootstrap.php'; require_login();
$type=(string)($_GET['type']??'content');
header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff');
if ($type==='content') {
    require_admin(); $data=['version'=>CMS_VERSION,'exported_at'=>cms_now()];
    foreach (['pages'=>'pages','revisions'=>'page_revisions','entries'=>'content_entries','templates'=>'content_templates','forms'=>'cms_forms','redirects'=>'redirects'] as $key=>$table) $data[$key]=cms_rows('SELECT * FROM '.$table);
    header('Content-Disposition: attachment; filename="webcms-content-'.date('Y-m-d').'.json"'); header('Content-Type: application/json; charset=utf-8'); echo cms_json($data); exit;
}
if ($type==='subscribers') {
    cms_require('modules'); $rows=[['E-Mail','Abmeldelink']];
    foreach (cms_rows("SELECT email,token FROM newsletter_subscribers WHERE status='active'") as $r) $rows[]=[$r['email'],site_url('/newsletter.php?mode=unsubscribe&token='.$r['token'])];
} elseif ($type==='submissions') {
    cms_require('forms'); $rows=[['ID','Formular','Datum','Status','Feld','Wert']];
    foreach (cms_rows('SELECT * FROM form_submissions ORDER BY id') as $r) foreach (json_decode($r['data_json'],true)?:[] as $field) $rows[]=[$r['id'],$r['title'],$r['created_at'],$r['status'],$field['label'],$field['value']];
} else { http_response_code(400); exit('Unbekannter Export.'); }
header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="webcms-'.$type.'-'.date('Y-m-d').'.csv"');
$out=fopen('php://output','wb'); fwrite($out,"\xEF\xBB\xBF"); foreach ($rows as $row) fputcsv($out,array_map('cms_csv_cell',$row),';','"',''); fclose($out);
