<?php
require __DIR__.'/core/bootstrap.php'; require __DIR__.'/core/render.php'; csrf_check();
$action=(string)($_POST['action']??''); $title='Vielen Dank'; $message='Ihre Eingabe wurde gespeichert.'; $back='/';
try {
    if (!empty($_POST['website'])) throw new RuntimeException('Die Eingabe konnte nicht verarbeitet werden.');
    $times=array_values(array_filter($_SESSION['public_submission_times']??[],fn($t)=>$t>time()-60));
    if (count($times)>=5) throw new RuntimeException('Bitte warten Sie eine Minute vor der nächsten Eingabe.');
    $times[]=time(); $_SESSION['public_submission_times']=$times;
    if ($action==='form' || $action==='block_form') {
        $formId=null; $pageId=null;
        if ($action==='form') {
            $formId=(int)($_POST['form_id']??0); $form=cms_row("SELECT * FROM cms_forms WHERE id=? AND status='published'",[$formId]);
            if (!$form) throw new RuntimeException('Dieses Formular ist nicht mehr verfügbar.');
            $fields=json_decode($form['fields_json'],true); $formTitle=$form['title']; $message=$form['success_message']?:$message; $back='/modules.php?form='.$formId;
        } else {
            $pageId=(int)($_POST['page_id']??0); $page=fetch_page_by_id($pageId); $page=$page?cms_public_page($page):null;
            if (!$page) throw new RuntimeException('Die Seite ist nicht mehr verfügbar.');
            $fields=null; $formTitle='';
            foreach (json_decode($page['blocks_json'],true)?:[] as $block) if ($block['type']==='form' && ($block['id']??'')===($_POST['block_id']??'')) { $fields=$block['settings']['fields']??[]; $formTitle=$block['settings']['heading']??$page['title']; break; }
            if ($fields===null) throw new RuntimeException('Formular nicht gefunden.');
            $back='/'.($page['is_home']?'':$page['slug']);
        }
        $values=cms_validate_submission($fields,$_POST);
        db()->prepare('INSERT INTO form_submissions (form_id,page_id,title,data_json,created_at) VALUES (?,?,?,?,?)')->execute([$formId,$pageId,$formTitle,cms_json($values),cms_now()]);
    } elseif ($action==='comment' || $action==='vote') {
        $entry=cms_row('SELECT * FROM content_entries WHERE id=?',[(int)($_POST['entry_id']??0)]);
        if (!$entry || !cms_entry_visible($entry)) throw new RuntimeException('Der Eintrag ist nicht mehr verfügbar.');
        $back='/modules.php?entry='.$entry['id'];
        if ($action==='comment') {
            if (!in_array($entry['kind'],['news','forum'],true)) throw new RuntimeException('Kommentare sind hier nicht möglich.');
            $data=cms_validate_submission([['type'=>'text','label'=>'Name','required'=>true],['type'=>'textarea','label'=>'Kommentar','required'=>true]],$_POST);
            if (strlen($data[0]['value'])>120) throw new RuntimeException('Der Name ist zu lang.');
            db()->prepare('INSERT INTO public_comments (entry_id,author,body,created_at) VALUES (?,?,?,?)')->execute([$entry['id'],$data[0]['value'],$data[1]['value'],cms_now()]);
            $message='Ihr Kommentar wurde eingereicht und wird vor der Veröffentlichung geprüft.';
        } else {
            if ($entry['kind']!=='poll') throw new RuntimeException('Dies ist keine Umfrage.');
            $options=explode("\n",json_decode($entry['data_json'],true)['options']??''); $choice=array_search($_POST['field_0']??'', $options,true);
            if ($choice===false) throw new RuntimeException('Bitte eine gültige Antwort wählen.');
            if (empty($_SESSION['poll_voter'])) $_SESSION['poll_voter']=bin2hex(random_bytes(32));
            $voter=hash_hmac('sha256',$_SESSION['poll_voter'],APP_SECRET);
            try { db()->prepare('INSERT INTO poll_votes (entry_id,option_key,voter_hash,created_at) VALUES (?,?,?,?)')->execute([$entry['id'],$choice,$voter,cms_now()]); }
            catch (PDOException $e) { if (cms_row('SELECT id FROM poll_votes WHERE entry_id=? AND voter_hash=?',[$entry['id'],$voter])) throw new RuntimeException('In dieser Sitzung wurde bereits abgestimmt.'); throw $e; }
            $message='Ihre Stimme wurde gezählt.';
        }
    } elseif ($action==='subscribe') {
        $data=cms_validate_submission([['label'=>'E-Mail','type'=>'email','required'=>true],['label'=>'Einwilligung','type'=>'checkbox','required'=>true]],$_POST);
        $email=strtolower($data[0]['value']); if (strlen($email)>180) throw new RuntimeException('Die E-Mail-Adresse ist zu lang.');
        $sender=(string)setting('newsletter_sender','');
        if (!filter_var($sender,FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/',$sender)) throw new RuntimeException('Die Newsletter-Anmeldung ist noch nicht eingerichtet. Bitte wenden Sie sich direkt an den Anbieter.');
        $existing=cms_row('SELECT * FROM newsletter_subscribers WHERE email=?',[$email]); $token=bin2hex(random_bytes(32));
        if (!$existing || $existing['status']!=='active') {
            if ($existing) db()->prepare("UPDATE newsletter_subscribers SET status='pending',token=?,created_at=? WHERE id=?")->execute([$token,cms_now(),$existing['id']]);
            else db()->prepare("INSERT INTO newsletter_subscribers (email,token,status,created_at) VALUES (?,?,'pending',?)")->execute([$email,$token,cms_now()]);
            $url=site_url('/newsletter.php?token='.$token);
            if (!mail($email,'Newsletter-Anmeldung bestaetigen',"Bitte bestaetigen Sie Ihre Anmeldung innerhalb von 48 Stunden:\n\n".$url,"From: ".$sender."\r\nContent-Type: text/plain; charset=UTF-8")) throw new RuntimeException('Die Bestätigungsmail konnte nicht versendet werden. Bitte versuchen Sie es später erneut.');
        }
        $message='Falls die Adresse noch nicht angemeldet ist, erhalten Sie eine E-Mail mit dem Bestätigungslink. Bitte prüfen Sie auch den Spamordner.'; $back='/modules.php?kind=newsletter';
    } else throw new RuntimeException('Unbekanntes Formular.');
    $_SESSION['public_success']=['message'=>$message,'back'=>$back];
    redirect('/submit_result.php');
} catch (Throwable $e) {
    http_response_code(422);
    echo cms_public_document('Bitte Eingabe prüfen','<div class="alert alert-danger" role="alert">'.e($e->getMessage()).'</div><p>Ihre Eingaben bleiben beim Zurückgehen im Browser erhalten.</p><p><a href="'.e(site_url($back)).'">Zum Formular</a></p>');
}
