<?php
if (PHP_SAPI!=='cli') exit;
define('WEBCMS_TEST_SERVER',true);
$runtime=__DIR__.'/.runtime';
foreach ([$runtime,$runtime.'/sessions',$runtime.'/uploads'] as $dir) if (!is_dir($dir)) mkdir($dir,0755,true);
if (is_file($runtime.'/database.sqlite')) { echo "Existing isolated test database retained.\n"; exit; }
require __DIR__.'/bootstrap.php';
set_setting('site_name','WebCMS Studio');
$about=cms_save_page(['title'=>'Über uns','slug'=>'ueber-uns','status'=>'published','blocks_json'=>cms_json(default_blocks('Wir gestalten das Morgen.'))]);
$news=cms_save_page(['title'=>'Aktuelles','slug'=>'aktuelles','status'=>'published','blocks_json'=>cms_json([['id'=>'collection','type'=>'collection','settings'=>['kind'=>'news','heading'=>'Aktuelles','limit'=>6]]])]);
$contact=cms_save_page(['title'=>'Kontakt','slug'=>'kontakt','status'=>'review','blocks_json'=>cms_json([['id'=>'contact','type'=>'form','settings'=>block_default_settings('form')]])]);
cms_save_page(['title'=>'Unser Team','parent_id'=>$about,'status'=>'draft','blocks_json'=>cms_json([['id'=>'team','type'=>'team','settings'=>block_default_settings('team')]])]);
cms_save_page(['title'=>'Herbstprogramm','status'=>'published','publish_at'=>'2099-10-01T09:00','blocks_json'=>cms_json(default_blocks('Herbstprogramm'))]);
cms_save_entry(['kind'=>'news','title'=>'Willkommen in unserem neuen WebCMS','summary'=>'Ein gemeinsamer Arbeitsplatz für gute Inhalte.','body'=>'Die neue Redaktion ist startklar.','status'=>'published','category'=>'Neuigkeiten']);
cms_save_entry(['kind'=>'event','title'=>'Tag der offenen Tür','summary'=>'Lernen Sie uns kennen.','starts_at'=>'2026-10-15T10:00','ends_at'=>'2026-10-15T17:00','location'=>'Rathaus','status'=>'published','category'=>'Vor Ort']);
cms_save_entry(['kind'=>'directory','title'=>'Bürgerbüro','summary'=>'Ihr Kontakt für Fragen vor Ort.','address'=>'Marktplatz 1','email'=>'kontakt@example.org','status'=>'published']);
db()->prepare("INSERT INTO tasks (title,body,page_id,assigned_to,status,due_at,created_by,created_at) VALUES (?,?,?,1,'open',?,1,?)")->execute(['Kontaktseite gegenlesen','Kontaktdaten und Formular prüfen.',$contact,date('Y-m-d').' 18:00:00',cms_now()]);
db()->prepare("INSERT INTO tasks (title,body,assigned_to,status,due_at,created_by,created_at) VALUES (?,?,1,'doing',?,1,?)")->execute(['Herbstprogramm vorbereiten','Veranstaltungen für Oktober ergänzen.',date('Y-m-d',strtotime('+3 days')).' 12:00:00',cms_now()]);
foreach (['test-author'=>'author','test-member'=>'viewer','test-scoped'=>'editor'] as $name=>$role) db()->prepare('INSERT INTO users (username,password_hash,role) VALUES (?,?,?)')->execute([$name,password_hash(DEFAULT_ADMIN_PASS,PASSWORD_DEFAULT),$role]);
$scoped=cms_row('SELECT id FROM users WHERE username=?',['test-scoped']); db()->prepare('INSERT INTO user_scopes (user_id,page_id) VALUES (?,?)')->execute([$scoped['id'],$about]);
echo "Isolated demo seeded at tests/.runtime/database.sqlite.\nLogin: test-admin / local-test-only\n";
