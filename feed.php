<?php
require __DIR__.'/core/bootstrap.php'; require __DIR__.'/core/feeds.php';
if (($_GET['format']??'rss')==='ics') {
    $events=cms_entries('event',true); if (isset($_GET['id'])) $events=array_values(array_filter($events,fn($e)=>(int)$e['id']===(int)$_GET['id']));
    header('Content-Type: text/calendar; charset=utf-8'); header('Content-Disposition: attachment; filename="veranstaltungen.ics"'); echo cms_ics_export($events); exit;
}
header('Content-Type: application/rss+xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>'.e(setting('site_name',SITE_NAME)).'</title><link>'.e(site_url('/')).'</link><description>Aktuelle Nachrichten</description>';
foreach (array_slice(cms_entries('news',true),0,50) as $entry) echo '<item><title>'.e($entry['title']).'</title><link>'.e(site_url('/modules.php?entry='.$entry['id'])).'</link><guid isPermaLink="false">webcms-entry-'.(int)$entry['id'].'</guid><description>'.e($entry['summary']??'').'</description><pubDate>'.e(date(DATE_RSS,strtotime($entry['updated_at']))).'</pubDate></item>';
echo '</channel></rss>';
