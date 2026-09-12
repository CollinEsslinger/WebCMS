<?php
/** Additive, restartable upgrades; never reinstall an existing database. */
const CMS_VERSION = '2.0.0';
const CMS_SCHEMA_VERSION = '2';

function cms_migrate(): void {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT svalue FROM settings WHERE skey = ?');
    $stmt->execute(['schema_version']);
    if ($stmt->fetchColumn() === CMS_SCHEMA_VERSION) return;
    $columns = [
        'pages' => [
            'version' => 'INTEGER NOT NULL DEFAULT 1', 'deleted_at' => 'VARCHAR(30) NULL',
            'publish_at' => 'VARCHAR(30) NULL', 'unpublish_at' => 'VARCHAR(30) NULL',
            'language' => "VARCHAR(12) NOT NULL DEFAULT 'de'", 'translation_of' => 'INTEGER NULL',
            'nav_hidden' => 'INTEGER NOT NULL DEFAULT 0', 'noindex' => 'INTEGER NOT NULL DEFAULT 0',
            'seo_title' => "VARCHAR(255) NOT NULL DEFAULT ''", 'og_image' => 'TEXT NULL',
            'access_role' => "VARCHAR(20) NOT NULL DEFAULT 'public'", 'owner_id' => 'INTEGER NULL',
            'published_json' => db_longtext() . ' NULL', 'tags' => 'TEXT NULL',
        ],
        'media' => ['folder' => "VARCHAR(120) NOT NULL DEFAULT ''", 'alt_text' => 'TEXT NULL', 'caption' => 'TEXT NULL', 'copyright' => 'TEXT NULL'],
    ];
    foreach ($columns as $table => $definitions) {
        $existing = DB_DRIVER === 'sqlite'
            ? array_column($pdo->query("PRAGMA table_info($table)")->fetchAll(), 'name')
            : array_column($pdo->query("SHOW COLUMNS FROM $table")->fetchAll(), 'Field');
        foreach ($definitions as $name => $sql) {
            if (!in_array($name, $existing, true)) $pdo->exec("ALTER TABLE $table ADD COLUMN $name $sql");
        }
    }
    $pk = db_pk(); $long = db_longtext();
    $tables = [
        'page_revisions' => "id $pk, page_id INTEGER NOT NULL, user_id INTEGER NULL, snapshot_json $long NOT NULL, note VARCHAR(255), created_at VARCHAR(30) NOT NULL",
        'activity_log' => "id $pk, user_id INTEGER NULL, action VARCHAR(80) NOT NULL, entity_type VARCHAR(30) NOT NULL, entity_id INTEGER NULL, description TEXT, created_at VARCHAR(30) NOT NULL",
        'tasks' => "id $pk, title VARCHAR(255) NOT NULL, body TEXT, page_id INTEGER NULL, assigned_to INTEGER NULL, status VARCHAR(20) NOT NULL DEFAULT 'open', due_at VARCHAR(30) NULL, created_by INTEGER NULL, created_at VARCHAR(30) NOT NULL",
        'page_comments' => "id $pk, page_id INTEGER NOT NULL, user_id INTEGER NULL, body TEXT NOT NULL, created_at VARCHAR(30) NOT NULL",
        'content_entries' => "id $pk, kind VARCHAR(40) NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL UNIQUE, summary TEXT, body $long, data_json $long, category VARCHAR(120), language VARCHAR(12) NOT NULL DEFAULT 'de', status VARCHAR(20) NOT NULL DEFAULT 'draft', starts_at VARCHAR(30) NULL, ends_at VARCHAR(30) NULL, created_by INTEGER NULL, updated_at VARCHAR(30) NOT NULL",
        'content_templates' => "id $pk, title VARCHAR(255) NOT NULL, description TEXT, blocks_json $long NOT NULL, created_by INTEGER NULL, updated_at VARCHAR(30) NOT NULL",
        'cms_forms' => "id $pk, title VARCHAR(255) NOT NULL, description TEXT, fields_json $long NOT NULL, success_message TEXT, status VARCHAR(20) NOT NULL DEFAULT 'draft', created_at VARCHAR(30) NOT NULL",
        'form_submissions' => "id $pk, form_id INTEGER NULL, page_id INTEGER NULL, title VARCHAR(255), data_json $long NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'new', created_at VARCHAR(30) NOT NULL",
        'redirects' => "id $pk, source_path VARCHAR(255) NOT NULL UNIQUE, target_path VARCHAR(255) NOT NULL, http_code INTEGER NOT NULL DEFAULT 301",
        'page_views' => "page_id INTEGER NOT NULL, view_date VARCHAR(10) NOT NULL, views INTEGER NOT NULL DEFAULT 0, PRIMARY KEY (page_id, view_date)",
        'user_scopes' => 'user_id INTEGER NOT NULL, page_id INTEGER NOT NULL, PRIMARY KEY (user_id, page_id)',
        'public_comments' => "id $pk, entry_id INTEGER NOT NULL, author VARCHAR(120) NOT NULL, body TEXT NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'pending', created_at VARCHAR(30) NOT NULL",
        'poll_votes' => "id $pk, entry_id INTEGER NOT NULL, option_key INTEGER NOT NULL, voter_hash VARCHAR(64) NOT NULL, created_at VARCHAR(30) NOT NULL, UNIQUE (entry_id, voter_hash)",
        'newsletter_subscribers' => "id $pk, email VARCHAR(180) NOT NULL UNIQUE, token VARCHAR(64) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'pending', created_at VARCHAR(30) NOT NULL",
    ];
    foreach ($tables as $name => $definition) $pdo->exec("CREATE TABLE IF NOT EXISTS $name ($definition)");
    // Preserve the existing public version when editors start a new draft.
    foreach ($pdo->query("SELECT * FROM pages WHERE status = 'published' AND published_json IS NULL")->fetchAll() as $page) {
        unset($page['published_json']);
        $pdo->prepare('UPDATE pages SET published_json = ? WHERE id = ?')->execute([cms_json($page), $page['id']]);
    }
    set_setting('schema_version', CMS_SCHEMA_VERSION);
}
