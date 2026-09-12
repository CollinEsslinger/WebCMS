<?php
require_once __DIR__ . '/schema.php';

function cms_json($value): string {
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}
function cms_rows(string $sql, array $args = []): array {
    $s = db()->prepare($sql); $s->execute($args); return $s->fetchAll();
}
function cms_row(string $sql, array $args = []): ?array { return cms_rows($sql, $args)[0] ?? null; }
function cms_now(): string { return date('Y-m-d H:i:s'); }
function cms_audit(string $action, string $type, ?int $id, string $description): void {
    db()->prepare('INSERT INTO activity_log (user_id, action, entity_type, entity_id, description, created_at) VALUES (?,?,?,?,?,?)')
        ->execute([current_user()['id'] ?? null, $action, $type, $id, $description, cms_now()]);
}
function cms_can(string $capability): bool {
    $role = current_user()['role'] ?? '';
    if ($role === 'admin') return true;
    $map = ['editor' => ['edit','publish','media','modules','forms'], 'publisher' => ['edit','publish','media','modules','forms'], 'author' => ['edit','media'], 'viewer' => []];
    return in_array($capability, $map[$role] ?? [], true);
}
function cms_require(string $capability): void {
    require_login();
    if (!cms_can($capability)) { http_response_code(403); exit('Für diese Aktion fehlen die erforderlichen Rechte.'); }
}
function cms_can_page(int $id): bool {
    if (!cms_can('edit')) return false;
    if (is_admin()) return true;
    $scopes = cms_rows('SELECT page_id FROM user_scopes WHERE user_id = ?', [current_user()['id']]);
    if (!$scopes) return true;
    foreach ($scopes as $scope) if ($id === (int)$scope['page_id'] || is_descendant($id, (int)$scope['page_id'])) return true;
    return false;
}
function cms_assert_page(int $id): array {
    $page = fetch_page_by_id($id);
    if (!$page || !cms_can_page($id)) throw new RuntimeException('Seite nicht gefunden oder keine Bearbeitungsrechte.');
    return $page;
}
function cms_statuses(): array { return ['draft'=>'Entwurf', 'review'=>'Zur Freigabe', 'published'=>'Veröffentlicht', 'archived'=>'Offline']; }
function cms_datetime($value): ?string {
    $value = trim((string)$value);
    if ($value === '') return null;
    foreach (['Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d'] as $format) {
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
        if ($date && $date->format($format) === $value) return $date->format('Y-m-d H:i:s');
    }
    throw new RuntimeException('Datum oder Uhrzeit ist ungültig.');
}
function cms_language($value): string {
    $value = (string)$value;
    if (!preg_match('/^[a-z]{2,3}(-[A-Z]{2})?$/D', $value)) throw new RuntimeException('Sprache als Kürzel angeben, zum Beispiel de oder en.');
    return $value;
}
function cms_validate_blocks(string $json): array {
    if (strlen($json) > 2000000) throw new RuntimeException('Seiteninhalt ist zu groß.');
    $blocks = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($blocks) || ($blocks && array_keys($blocks) !== range(0, count($blocks)-1)) || count($blocks) > 250) throw new RuntimeException('Ungültige Blockliste.');
    foreach ($blocks as $block) {
        if (!is_array($block) || !isset($block['type']) || !is_array($block['settings'] ?? null)) throw new RuntimeException('Ungültiger Inhaltsblock.');
        if (!array_key_exists($block['type'], block_types())) throw new RuntimeException('Dieser Blocktyp ist nicht erlaubt.');
    }
    return $blocks;
}
function cms_revision(array $page, string $note): void {
    unset($page['published_json']);
    db()->prepare('INSERT INTO page_revisions (page_id,user_id,snapshot_json,note,created_at) VALUES (?,?,?,?,?)')
        ->execute([$page['id'], current_user()['id'] ?? null, cms_json($page), $note, cms_now()]);
}
function cms_save_page(array $data, ?int $id = null, string $note = 'Gespeichert'): int {
    if (!cms_can('edit')) throw new RuntimeException('Keine Bearbeitungsrechte.');
    $old = $id ? cms_assert_page($id) : null;
    $live = $old ? cms_public_page($old) : null;
    if ($old && $old['deleted_at']) throw new RuntimeException('Seite zuerst aus dem Papierkorb wiederherstellen.');
    $status = (string)($data['status'] ?? 'draft');
    if (!isset(cms_statuses()[$status])) throw new RuntimeException('Ungültiger Status.');
    if (in_array($status, ['published','archived'], true) && !cms_can('publish')) throw new RuntimeException('Bitte die Seite zur Freigabe einreichen.');
    if (!cms_can('publish') && (int)!empty($data['is_home']) !== (int)!empty($old['is_home'])) throw new RuntimeException('Nur freigabeberechtigte Benutzer können die Startseite ändern.');
    if (strlen(trim((string)($data['title'] ?? ''))) > 255) throw new RuntimeException('Der Seitentitel ist zu lang.');
    $parent = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
    if ($parent) {
        $p = cms_assert_page($parent);
        if ($p['deleted_at'] || $parent === $id || ($id && is_descendant($parent, $id))) throw new RuntimeException('Die übergeordnete Seite ist ungültig.');
    } elseif (!is_admin() && cms_rows('SELECT page_id FROM user_scopes WHERE user_id = ?', [current_user()['id']])) {
        throw new RuntimeException('Bitte eine übergeordnete Seite aus Ihrem Bereich wählen.');
    }
    $from = cms_datetime($data['publish_at'] ?? null); $until = cms_datetime($data['unpublish_at'] ?? null);
    if ($from && $until && $until <= $from) throw new RuntimeException('Das Ende muss nach dem Veröffentlichungsbeginn liegen.');
    $language = cms_language($data['language'] ?? 'de');
    $translation = !empty($data['translation_of']) ? (int)$data['translation_of'] : null;
    if ($translation && ($translation === $id || !fetch_page_by_id($translation))) throw new RuntimeException('Ungültige Übersetzungsreferenz.');
    $access = ($data['access_role'] ?? '') === 'members' ? 'members' : 'public';
    $data['blocks_json'] = cms_json(cms_validate_blocks((string)($data['blocks_json'] ?? '[]')));
    $data['status'] = $status === 'published' ? 'published' : 'draft';
    db()->beginTransaction();
    try {
        if ($old) {
            $s = db()->prepare('UPDATE pages SET version = version + 1 WHERE id = ? AND version = ?');
            $s->execute([$id, (int)($data['version'] ?? 0)]);
            if ($s->rowCount() !== 1) throw new RuntimeException('Die Seite wurde inzwischen geändert. Bitte Ihren lokalen Entwurf sichern und den aktuellen Stand neu laden.');
            cms_revision($old, 'Stand vor: ' . $note);
        }
        $id = save_page($data, $id);
        if ($live) {
            unset($live['published_json']);
            db()->prepare('UPDATE pages SET published_json=? WHERE id=?')->execute([cms_json($live),$id]);
        }
        db()->prepare('UPDATE pages SET status=?, publish_at=?, unpublish_at=?, language=?, translation_of=?, nav_hidden=?, noindex=?, seo_title=?, og_image=?, access_role=?, owner_id=?, tags=? WHERE id=?')
            ->execute([$status,$from,$until,$language,$translation,empty($data['nav_hidden'])?0:1,empty($data['noindex'])?0:1,
                substr(trim((string)($data['seo_title'] ?? '')),0,255),safe_media_url($data['og_image'] ?? ''),$access,
                $old['owner_id'] ?? current_user()['id'],substr(trim((string)($data['tags'] ?? '')),0,2000),$id]);
        $page = fetch_page_by_id($id);
        if ($status === 'published' && (!$from || $from <= cms_now())) {
            unset($page['published_json']);
            db()->prepare('UPDATE pages SET published_json=? WHERE id=?')->execute([cms_json($page),$id]);
        } elseif ($status === 'archived') {
            db()->prepare('UPDATE pages SET published_json=NULL WHERE id=?')->execute([$id]);
        }
        cms_audit('page.save','page',$id,$note . ': ' . $page['title']);
        db()->commit(); return $id;
    } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); throw $e; }
}
/** A draft never replaces an already published version. Scheduling is evaluated on requests. */
function cms_public_page(array $page, bool $anonymous = false): ?array {
    if (!empty($page['deleted_at']) || ($page['status'] ?? '') === 'archived') return null;
    $now = cms_now();
    if (($page['status'] ?? '') === 'published' && (empty($page['publish_at']) || $page['publish_at'] <= $now)) {
        $visible = $page;
    } else {
        $visible = json_decode($page['published_json'] ?? 'null', true);
    }
    if (!$visible || (!empty($visible['unpublish_at']) && $visible['unpublish_at'] <= $now)) return null;
    if (($visible['access_role'] ?? 'public') === 'members' && ($anonymous || !current_user())) return null;
    $visible['id'] = $page['id'];
    // Home and tree placement belong to the current structure, content to the approved version.
    $visible['is_home'] = $page['is_home'];
    return $visible;
}
function cms_public_pages(bool $anonymous = false): array {
    $out = [];
    foreach (db()->query('SELECT * FROM pages ORDER BY sort_order, id')->fetchAll() as $page) {
        $visible = cms_public_page($page, $anonymous);
        if ($visible) $out[] = $visible;
    }
    return $out;
}
function cms_trash_page(int $id): void {
    $page = cms_assert_page($id);
    if ($page['is_home']) throw new RuntimeException('Die Startseite kann nicht in den Papierkorb verschoben werden.');
    $ids = [$id];
    foreach (fetch_pages() as $child) if (is_descendant((int)$child['id'], $id)) $ids[] = (int)$child['id'];
    foreach ($ids as $childId) {
        $child = cms_assert_page($childId);
        if ($child['is_home']) throw new RuntimeException('Der Bereich enthält die Startseite.');
        if (!cms_can('publish') && cms_public_page($child)) throw new RuntimeException('Eine veröffentlichte Seite darf nur mit Freigaberecht entfernt werden.');
    }
    db()->beginTransaction();
    try {
        foreach ($ids as $childId) db()->prepare('UPDATE pages SET deleted_at=?, version=version+1 WHERE id=?')->execute([cms_now(),$childId]);
        cms_audit('page.trash','page',$id,'In Papierkorb: ' . $page['title']); db()->commit();
    } catch (Throwable $e) { db()->rollBack(); throw $e; }
}
function cms_entry_types(): array {
    return ['news'=>'Nachrichten','event'=>'Veranstaltungen','directory'=>'Verzeichnisse','job'=>'Stellen & Ehrenamt','accommodation'=>'Unterkünfte','service'=>'Bürgerservice','poll'=>'Umfragen','forum'=>'Forum','newsletter'=>'Newsletter'];
}
function cms_entries(string $kind, bool $public = false): array {
    $rows = cms_rows('SELECT * FROM content_entries WHERE kind=? ORDER BY starts_at DESC, updated_at DESC',[$kind]);
    return $public ? array_values(array_filter($rows, 'cms_entry_visible')) : $rows;
}
function cms_entry_visible(array $entry): bool {
    return $entry['status'] === 'published' && ($entry['kind'] === 'event' || ((!$entry['starts_at'] || $entry['starts_at'] <= cms_now()) && (!$entry['ends_at'] || $entry['ends_at'] > cms_now())));
}
function cms_save_entry(array $data, ?int $id = null): int {
    if (!cms_can('modules')) throw new RuntimeException('Keine Modulrechte.');
    $kind = (string)($data['kind'] ?? 'news');
    if (!isset(cms_entry_types()[$kind])) throw new RuntimeException('Unbekannter Inhaltstyp.');
    $title = trim((string)($data['title'] ?? ''));
    if ($title === '' || strlen($title) > 255) throw new RuntimeException('Bitte einen Titel mit höchstens 255 Bytes eingeben.');
    if ($id && !cms_row('SELECT id FROM content_entries WHERE id=? AND kind=?',[$id,$kind])) throw new RuntimeException('Eintrag nicht gefunden.');
    $slug = slugify((string)($data['slug'] ?? $title)) ?: slugify($title);
    if ($slug === '') $slug = 'eintrag';
    $base = $slug; $i = 1;
    while (cms_row('SELECT id FROM content_entries WHERE slug=? AND id<>?',[$slug,$id ?? 0])) $slug = $base . '-' . ++$i;
    $from = cms_datetime($data['starts_at'] ?? null); $until = cms_datetime($data['ends_at'] ?? null);
    if ($from && $until && $until < $from) throw new RuntimeException('Das Ende muss nach dem Beginn liegen.');
    $extra = [];
    foreach (['location','address','email','phone','price','organizer','options','latitude','longitude'] as $key) $extra[$key] = substr(trim((string)($data[$key] ?? '')),0,4000);
    $extra['url'] = safe_url($data['url'] ?? '', ''); $extra['image'] = safe_media_url($data['image'] ?? '');
    if ($kind === 'poll') {
        $options = array_values(array_filter(array_map('trim', explode("\n",$extra['options']))));
        if (count($options) < 2 || count($options) > 20) throw new RuntimeException('Eine Umfrage benötigt 2 bis 20 Antwortoptionen.');
        $extra['options'] = implode("\n", $options);
        if ($id && cms_row('SELECT id FROM poll_votes WHERE entry_id=?',[$id])) {
            $previous = json_decode(cms_row('SELECT data_json FROM content_entries WHERE id=?',[$id])['data_json'], true);
            if ($previous['options'] !== $extra['options']) throw new RuntimeException('Antwortoptionen können nach der ersten Stimme nicht mehr geändert werden.');
        }
    }
    $values = [$kind,$title,$slug,trim((string)($data['summary'] ?? '')),trim((string)($data['body'] ?? '')),cms_json($extra),trim((string)($data['category'] ?? '')),cms_language($data['language'] ?? 'de'),($data['status'] ?? '') === 'published' ? 'published':'draft',$from,$until,cms_now()];
    if ($id) { $values[]=$id; db()->prepare('UPDATE content_entries SET kind=?,title=?,slug=?,summary=?,body=?,data_json=?,category=?,language=?,status=?,starts_at=?,ends_at=?,updated_at=? WHERE id=?')->execute($values); }
    else { $values[]=current_user()['id']; db()->prepare('INSERT INTO content_entries (kind,title,slug,summary,body,data_json,category,language,status,starts_at,ends_at,updated_at,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute($values); $id=(int)db()->lastInsertId(); }
    cms_audit('entry.save','entry',$id,$title); return $id;
}
function cms_form_fields(array $fields): array {
    if (!$fields || count($fields)>50) throw new RuntimeException('Ein Formular benötigt 1 bis 50 Felder.');
    $out=[];
    foreach ($fields as $i=>$f) {
        if (!is_array($f)) throw new RuntimeException('Ungültiges Formularfeld.');
        $type=(string)($f['type']??'text'); $label=trim((string)($f['label']??''));
        if (!in_array($type,['text','email','tel','number','date','textarea','select','checkbox'],true) || $label==='') throw new RuntimeException('Bitte Feldtyp und Beschriftung prüfen.');
        $options=array_values(array_filter(array_map('trim',explode('|',(string)($f['options']??'')))));
        if ($type==='select' && !$options) throw new RuntimeException('Auswahlfelder benötigen Optionen, getrennt mit |.');
        $out[]=['name'=>'field_'.$i,'type'=>$type,'label'=>substr($label,0,255),'required'=>!empty($f['required']),'placeholder'=>(string)($f['placeholder']??''),'options'=>implode('|',$options)];
    }
    return $out;
}
function cms_validate_submission(array $fields,array $input): array {
    $out=[];
    foreach (cms_form_fields($fields) as $i=>$f) {
        $v=$input['field_'.$i]??'';
        if (!is_scalar($v)) throw new RuntimeException('Ungültige Eingabe.');
        $v=trim((string)$v);
        if ($f['required'] && $v==='') throw new RuntimeException('Bitte ausfüllen: '.$f['label']);
        if (strlen($v)>10000) throw new RuntimeException('Die Eingabe ist zu lang: '.$f['label']);
        if ($v!=='' && $f['type']==='email' && !filter_var($v,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Bitte eine gültige E-Mail-Adresse eingeben.');
        if ($v!=='' && $f['type']==='number' && !is_numeric($v)) throw new RuntimeException('Bitte eine Zahl eingeben.');
        if ($v!=='' && $f['type']==='date') cms_datetime($v);
        if ($v!=='' && $f['type']==='select' && !in_array($v,explode('|',$f['options']),true)) throw new RuntimeException('Ungültige Auswahl.');
        if ($f['type']==='checkbox' && !in_array($v,['','1'],true)) throw new RuntimeException('Ungültiges Kontrollkästchen.');
        $out[]=['label'=>$f['label'],'value'=>$f['type']==='checkbox'?($v?'Ja':'Nein'):$v];
    }
    return $out;
}
function cms_csv_cell($value): string {
    $value=(string)$value;
    return preg_match('/^[\s]*[=+@\-\t\r]/u',$value) ? "'".$value : $value;
}
