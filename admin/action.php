<?php
require __DIR__.'/../core/bootstrap.php'; require_login(); csrf_check();
$action=(string)($_POST['action']??''); $id=(int)($_POST['id']??0);
$return=(string)($_POST['return_to']??'/admin/dashboard.php');
if (!preg_match('#^/admin/(dashboard|workspace|content|forms|history|editor|permissions|media_details)\.php(?:\?[^\r\n]*)?$#D',$return)) $return='/admin/dashboard.php';
try {
    switch ($action) {
        case 'entry.save':
            cms_require('modules'); $newId=cms_save_entry($_POST,$id?:null);
            $return='/admin/content.php?kind='.urlencode($_POST['kind']).'&id='.$newId; break;
        case 'entry.copy':
            cms_require('modules'); $entry=cms_row('SELECT * FROM content_entries WHERE id=?',[$id]);
            if (!$entry) throw new RuntimeException('Eintrag nicht gefunden.');
            $data=array_merge($entry,json_decode($entry['data_json'],true)?:[]); $data['title'].=' (Kopie)'; $data['status']='draft'; $data['slug'].='-kopie';
            $newId=cms_save_entry($data); $return='/admin/content.php?kind='.$entry['kind'].'&id='.$newId; break;
        case 'form.save':
            cms_require('forms'); $title=trim((string)($_POST['title']??''));
            if ($title==='' || strlen($title)>255) throw new RuntimeException('Bitte einen gültigen Formulartitel eingeben.');
            if ($id && !cms_row('SELECT id FROM cms_forms WHERE id=?',[$id])) throw new RuntimeException('Formular nicht gefunden.');
            $fields=cms_form_fields($_POST['fields']??[]);
            $values=[$title,trim((string)($_POST['description']??'')),cms_json($fields),trim((string)($_POST['success_message']??'')),($_POST['status']??'')==='published'?'published':'draft'];
            if ($id) { $values[]=$id; db()->prepare('UPDATE cms_forms SET title=?,description=?,fields_json=?,success_message=?,status=? WHERE id=?')->execute($values); }
            else { $values[]=cms_now(); db()->prepare('INSERT INTO cms_forms (title,description,fields_json,success_message,status,created_at) VALUES (?,?,?,?,?,?)')->execute($values); $id=(int)db()->lastInsertId(); }
            cms_audit('form.save','form',$id,$title); $return='/admin/forms.php?id='.$id; break;
        case 'page.copy':
        case 'page.translate':
            $page=cms_assert_page($id); $page['id']=null; $page['is_home']=0; $page['status']='draft'; $page['publish_at']=null; $page['unpublish_at']=null;
            if ($action==='page.translate') { $page['language']=cms_language($_POST['language']??'en'); $page['translation_of']=$page['translation_of']?:$id; $page['title'].=' ('.$page['language'].')'; }
            else $page['title'].=' (Kopie)';
            $page['slug_part']=basename($page['slug']).'-'.($action==='page.translate'?$page['language']:'kopie');
            $newId=cms_save_page($page,null,'Kopie angelegt'); $return='/admin/editor.php?id='.$newId; break;
        case 'page.restore':
            $page=cms_assert_page($id);
            if ($page['parent_id'] && !empty(fetch_page_by_id((int)$page['parent_id'])['deleted_at'])) throw new RuntimeException('Bitte zuerst die übergeordnete Seite wiederherstellen.');
            db()->prepare("UPDATE pages SET deleted_at=NULL,status='draft',published_json=NULL,version=version+1 WHERE id=?")->execute([$id]);
            cms_audit('page.restore','page',$id,$page['title']); break;
        case 'revision.restore':
            $revision=cms_row('SELECT * FROM page_revisions WHERE id=?',[$id]);
            if (!$revision) throw new RuntimeException('Version nicht gefunden.');
            $current=cms_assert_page((int)$revision['page_id']);
            $data=json_decode($revision['snapshot_json'],true); $data['version']=(int)($_POST['version']??0); $data['status']='draft'; $data['is_home']=$current['is_home'];
            $data['slug_part']=basename($data['slug']); cms_save_page($data,(int)$current['id'],'Version wiederhergestellt');
            $return='/admin/editor.php?id='.$current['id']; break;
        case 'page.comment':
            cms_assert_page($id); $body=trim((string)($_POST['body']??''));
            if ($body==='' || strlen($body)>10000) throw new RuntimeException('Bitte einen Kommentar mit höchstens 10.000 Zeichen eingeben.');
            db()->prepare('INSERT INTO page_comments (page_id,user_id,body,created_at) VALUES (?,?,?,?)')->execute([$id,current_user()['id'],$body,cms_now()]); break;
        case 'task.save':
            cms_require('edit'); $title=trim((string)($_POST['title']??''));
            if ($title==='' || strlen($title)>255) throw new RuntimeException('Bitte einen gültigen Aufgabentitel eingeben.');
            $pageId=(int)($_POST['page_id']??0); if ($pageId) cms_assert_page($pageId);
            $assignee=(int)($_POST['assigned_to']??0); if ($assignee && !cms_row('SELECT id FROM users WHERE id=?',[$assignee])) throw new RuntimeException('Benutzer nicht gefunden.');
            db()->prepare("INSERT INTO tasks (title,body,page_id,assigned_to,status,due_at,created_by,created_at) VALUES (?,?,?,?,'open',?,?,?)")
                ->execute([$title,trim((string)($_POST['body']??'')),$pageId?:null,$assignee?:null,cms_datetime($_POST['due_at']??''),current_user()['id'],cms_now()]); break;
        case 'task.status':
            cms_require('edit'); $task=cms_row('SELECT * FROM tasks WHERE id=?',[$id]);
            if (!$task || (!is_admin() && (int)$task['created_by']!==(int)current_user()['id'] && (int)$task['assigned_to']!==(int)current_user()['id'])) throw new RuntimeException('Keine Berechtigung für diese Aufgabe.');
            $status=(string)($_POST['status']??''); if (!in_array($status,['open','doing','done'],true)) throw new RuntimeException('Ungültiger Aufgabenstatus.');
            db()->prepare('UPDATE tasks SET status=? WHERE id=?')->execute([$status,$id]); break;
        case 'template.save':
            $page=cms_assert_page((int)($_POST['page_id']??0)); $title=trim((string)($_POST['title']??''));
            if ($title==='' || strlen($title)>255) throw new RuntimeException('Bitte einen Vorlagentitel eingeben.');
            cms_validate_blocks($page['blocks_json']);
            db()->prepare('INSERT INTO content_templates (title,description,blocks_json,created_by,updated_at) VALUES (?,?,?,?,?)')->execute([$title,$_POST['description']??'',$page['blocks_json'],current_user()['id'],cms_now()]); break;
        case 'template.use':
            cms_require('edit'); $template=cms_row('SELECT * FROM content_templates WHERE id=?',[$id]);
            if (!$template) throw new RuntimeException('Vorlage nicht gefunden.');
            $newId=cms_save_page(['title'=>$template['title'],'blocks_json'=>$template['blocks_json'],'status'=>'draft','parent_id'=>$_POST['parent_id']??null]);
            $return='/admin/editor.php?id='.$newId; break;
        case 'redirect.save':
            require_admin(); $source=trim((string)($_POST['source_path']??'')); $target=trim((string)($_POST['target_path']??''));
            foreach ([$source,$target] as $path) if (!preg_match('#^/(?!/)[a-zA-Z0-9/_\-.~%]*$#D',$path) || strlen($path)>255 || str_contains($path,'..') || preg_match('/%0[ad]/i',$path)) throw new RuntimeException('Bitte interne Pfade wie /alter-name angeben.');
            if ($source===$target || $source==='/' || str_starts_with($source,'/admin')) throw new RuntimeException('Diese Weiterleitung ist nicht zulässig.');
            $seen=[$source=>true]; $next=$target;
            while ($next) { if (isset($seen[$next])) throw new RuntimeException('Die Weiterleitung würde eine Schleife erzeugen.'); $seen[$next]=true; $next=cms_row('SELECT target_path FROM redirects WHERE source_path=?',[$next])['target_path']??null; }
            db()->prepare('INSERT INTO redirects (source_path,target_path,http_code) VALUES (?,?,?)')->execute([$source,$target,($_POST['http_code']??'')==='302'?302:301]); break;
        case 'redirect.delete': require_admin(); db()->prepare('DELETE FROM redirects WHERE id=?')->execute([$id]); break;
        case 'submission.status':
            cms_require('forms'); $status=(string)($_POST['status']??'');
            if (!in_array($status,['new','read','done','spam'],true)) throw new RuntimeException('Ungültiger Status.');
            db()->prepare('UPDATE form_submissions SET status=? WHERE id=?')->execute([$status,$id]); break;
        case 'submission.delete': cms_require('forms'); db()->prepare('DELETE FROM form_submissions WHERE id=?')->execute([$id]); cms_audit('submission.delete','submission',$id,'Einsendung entfernt'); break;
        case 'comment.moderate':
            cms_require('modules'); $status=($_POST['status']??'')==='approved'?'approved':'spam'; db()->prepare('UPDATE public_comments SET status=? WHERE id=?')->execute([$status,$id]); break;
        case 'subscriber.remove': cms_require('modules'); db()->prepare("UPDATE newsletter_subscribers SET status='unsubscribed' WHERE id=?")->execute([$id]); break;
        case 'media.save':
            cms_require('media'); if (!cms_row('SELECT id FROM media WHERE id=?',[$id])) throw new RuntimeException('Datei nicht gefunden.');
            db()->prepare('UPDATE media SET folder=?,alt_text=?,caption=?,copyright=? WHERE id=?')->execute([substr(trim($_POST['folder']??''),0,120),trim($_POST['alt_text']??''),trim($_POST['caption']??''),trim($_POST['copyright']??''),$id]); break;
        case 'permissions.save':
            require_admin(); $user=cms_row('SELECT id,role FROM users WHERE id=?',[$id]); $role=(string)($_POST['role']??'');
            if (!$user || !in_array($role,['admin','editor','publisher','author','viewer'],true)) throw new RuntimeException('Ungültiger Benutzer oder Rolle.');
            if ($id===(int)current_user()['id'] && $role!=='admin') throw new RuntimeException('Die eigene Administratorrolle kann hier nicht entfernt werden.');
            if ($user['role']==='admin' && $role!=='admin' && count(cms_rows("SELECT id FROM users WHERE role='admin'"))<2) throw new RuntimeException('Der letzte Administrator muss erhalten bleiben.');
            $scopeIds=array_unique(array_map('intval',$_POST['scopes']??[]));
            foreach ($scopeIds as $pid) if (!fetch_page_by_id($pid)) throw new RuntimeException('Seitenbereich nicht gefunden.');
            db()->beginTransaction();
            db()->prepare('UPDATE users SET role=? WHERE id=?')->execute([$role,$id]); db()->prepare('DELETE FROM user_scopes WHERE user_id=?')->execute([$id]);
            foreach ($scopeIds as $pid) db()->prepare('INSERT INTO user_scopes (user_id,page_id) VALUES (?,?)')->execute([$id,$pid]);
            cms_audit('permissions.save','user',$id,'Rolle und Seitenbereiche aktualisiert'); db()->commit(); break;
        case 'system.save':
            require_admin(); $timezone=(string)($_POST['timezone']??'Europe/Berlin');
            if (!in_array($timezone,timezone_identifiers_list(),true)) throw new RuntimeException('Unbekannte Zeitzone.');
            $sender=trim((string)($_POST['newsletter_sender']??''));
            if ($sender!=='' && (!filter_var($sender,FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/',$sender))) throw new RuntimeException('Bitte eine gültige Newsletter-Absenderadresse angeben.');
            set_setting('newsletter_sender',$sender);
            set_setting('timezone',$timezone); set_setting('analytics_enabled',empty($_POST['analytics_enabled'])?'0':'1'); break;
        case 'content.import':
            require_admin(); $json=(string)($_POST['json']??''); if (strlen($json)>3000000) throw new RuntimeException('Import ist zu groß.');
            $items=json_decode($json,true,64,JSON_THROW_ON_ERROR); $items=$items['entries']??$items;
            if (!is_array($items) || count($items)>500) throw new RuntimeException('Maximal 500 Einträge pro Import.');
            db()->beginTransaction(); $count=0;
            foreach ($items as $item) {
                if (!is_array($item)) throw new RuntimeException('Ungültiger Datensatz.');
                $data=array_merge($item,json_decode($item['data_json']??'{}',true)?:[]); $data['status']='draft'; cms_save_entry($data); $count++;
            }
            db()->commit(); $_SESSION['flash']="$count Einträge als Entwurf importiert."; break;
        case 'calendar.import':
            require_admin(); require_once __DIR__.'/../core/feeds.php'; $events=cms_parse_ics((string)($_POST['ics']??''));
            db()->beginTransaction(); foreach ($events as $event) cms_save_entry($event); db()->commit();
            $_SESSION['flash']=count($events).' Termine als Entwurf importiert.'; break;
        default: throw new RuntimeException('Unbekannte Aktion.');
    }
    $_SESSION['flash']=$_SESSION['flash']??'Änderungen gespeichert.';
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    $_SESSION['flash_error']=$e->getMessage();
    if ($action==='entry.save') $_SESSION['failed_entry']=$_POST;
    if ($action==='form.save') $_SESSION['failed_form']=$_POST;
}
redirect($return);
