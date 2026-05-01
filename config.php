<?php
/**
 * WebCMS Configuration
 * Edit these values to match your setup.
 */

// ─── DATABASE ───────────────────────────────────────────────
// Driver: 'sqlite' (no setup needed) or 'mysql'
const DB_DRIVER = 'mysql';

// SQLite settings (used when DB_DRIVER = 'sqlite')
const DB_SQLITE_PATH = __DIR__ . '/storage/database.sqlite';

// MySQL settings (used when DB_DRIVER = 'mysql')
const DB_MYSQL_HOST = 'rdbms.strato.de';
const DB_MYSQL_PORT = 3306;
const DB_MYSQL_NAME = 'Datenbank-Name';
const DB_MYSQL_USER = 'Datenbank-User';
const DB_MYSQL_PASS = 'Datenbank-Passwort';
const DB_MYSQL_CHARSET = 'utf8mb4';

// ─── SITE ───────────────────────────────────────────────────
const SITE_NAME = 'WebCMS';
const SITE_TAGLINE = 'Visuelles Content-Management';
const SITE_LANG = 'de';

// ─── SECURITY ───────────────────────────────────────────────
// Used for CSRF tokens and session salt. CHANGE THIS in production!
const APP_SECRET = 'mysqldatabase-madebyclaude_aifor!collinesslinger-2009!';

// Default admin login (only used during install)
const DEFAULT_ADMIN_USER = 'admin';
const DEFAULT_ADMIN_PASS = 'admin123';

// Session lifetime in seconds (default: 8 hours)
const SESSION_LIFETIME = 28800;

// ─── UPLOADS ────────────────────────────────────────────────
const UPLOAD_DIR = __DIR__ . '/storage/uploads';
const UPLOAD_MAX_BYTES = 20 * 1024 * 1024; // 20 MB
const UPLOAD_ALLOWED_EXT = ['jpg','jpeg','png','webp','gif','svg','mp4','webm','pdf'];

// ─── DESIGN ─────────────────────────────────────────────────
// Default accent color (can be changed in CMS settings)
const DEFAULT_ACCENT = '#1ae824';

// ─── DEBUG ──────────────────────────────────────────────────
const DEBUG = false;
