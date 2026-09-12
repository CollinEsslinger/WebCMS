<?php
require __DIR__.'/core/bootstrap.php'; require __DIR__.'/core/render.php';
header('Referrer-Policy: no-referrer'); header('Cache-Control: no-store'); header('X-Robots-Tag: noindex');
$token=(string)($_POST['token']??$_GET['token']??'');
$subscriber=preg_match('/^[a-f0-9]{64}$/D',$token)?cms_row('SELECT * FROM newsletter_subscribers WHERE token=?',[$token]):null;
if (!$subscriber) { http_response_code(404); echo cms_public_document('Link ungültig','<p>Dieser Link ist nicht gültig.</p>'); exit; }
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check(); $status=($_POST['mode']??'')==='unsubscribe'?'unsubscribed':'active';
    if ($status==='active' && ($subscriber['status']!=='pending' || strtotime($subscriber['created_at'])<time()-172800)) $error='Der Bestätigungslink ist abgelaufen. Bitte melden Sie sich erneut an.';
    else { db()->prepare('UPDATE newsletter_subscribers SET status=? WHERE id=?')->execute([$status,$subscriber['id']]); echo cms_public_document('Newsletter',$status==='active'?'<p>Ihre Anmeldung ist bestätigt.</p>':'<p>Sie wurden vom Newsletter abgemeldet.</p>'); exit; }
}
$unsubscribe=($_GET['mode']??'')==='unsubscribe' || $subscriber['status']==='active';
$html=$error?'<p role="alert">'.e($error).'</p>':'<p>'.($unsubscribe?'Newsletter abbestellen?':'Bitte bestätigen Sie Ihre Newsletter-Anmeldung.').'</p><form method="post"><input type="hidden" name="_csrf" value="'.e(csrf_token()).'"><input type="hidden" name="token" value="'.e($token).'"><input type="hidden" name="mode" value="'.($unsubscribe?'unsubscribe':'confirm').'"><button class="btn btn-primary">'.($unsubscribe?'Abmelden':'Anmeldung bestätigen').'</button></form>';
echo cms_public_document('Newsletter',$html);
