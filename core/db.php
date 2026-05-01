<?php
/**
 * Database connection (PDO).
 * Returns a singleton PDO instance configured for SQLite or MySQL.
 */

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    if (DB_DRIVER === 'sqlite') {
        $dir = dirname(DB_SQLITE_PATH);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $pdo = new PDO('sqlite:' . DB_SQLITE_PATH);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
    } else {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_MYSQL_HOST, DB_MYSQL_PORT, DB_MYSQL_NAME, DB_MYSQL_CHARSET
        );
        $pdo = new PDO($dsn, DB_MYSQL_USER, DB_MYSQL_PASS);
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    return $pdo;
}

/** Helper: AUTOINCREMENT keyword that works in both SQLite & MySQL */
function db_pk(): string {
    return DB_DRIVER === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
}

/** Helper: TEXT vs LONGTEXT */
function db_longtext(): string {
    return DB_DRIVER === 'sqlite' ? 'TEXT' : 'LONGTEXT';
}

/** Helper: current timestamp default */
function db_now_default(): string {
    return DB_DRIVER === 'sqlite' ? "DEFAULT CURRENT_TIMESTAMP" : "DEFAULT CURRENT_TIMESTAMP";
}
