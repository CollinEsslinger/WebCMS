# WebCMS

WebCMS ist ein schlankes PHP-basiertes Content-Management-System fuer kleine bis mittlere Websites. Inhalte werden im Admin-Bereich mit einem visuellen Block-Editor gepflegt, Seiten koennen hierarchisch organisiert werden und Medien werden direkt in der integrierten Mediathek verwaltet.

## Funktionen

- Visueller Seiteneditor mit wiederverwendbaren Inhaltsbloecken
- Seitenverwaltung mit Entwurf-/Veroeffentlicht-Status
- Hierarchische Seitenstruktur mit Unterseiten und automatisch aufgebauten URLs
- Startseite frei festlegbar
- Navigation und Footer werden aus den veroeffentlichten Seiten generiert
- Mediathek fuer Bilder, Videos, SVGs und PDFs
- Benutzerverwaltung mit Rollen fuer Administratoren und Redakteure
- Login-System mit Session-Timeout und CSRF-Schutz
- Website-Einstellungen fuer Name, Logo, Tagline, Meta Description, Akzentfarbe, Theme und Schriften
- Unterstuetzung fuer MySQL und SQLite ueber PDO
- Saubere URLs ueber Apache `mod_rewrite`

## Voraussetzungen

- PHP 8.0 oder neuer
- PDO-Erweiterung fuer den gewaehlten Datenbanktreiber
- MySQL/MariaDB oder SQLite
- Apache-Webserver mit aktiviertem `mod_rewrite`
- Schreibrechte fuer den Ordner `storage/`

Fuer Uploads werden zusaetzlich die PHP-Funktionen fuer Datei-Uploads und `fileinfo` benoetigt.

## Installation

1. Projektdateien auf den Webserver kopieren.
2. `config.example.php` zu `config.php` kopieren.
3. Datenbank in `config.php` konfigurieren.
4. Einen sicheren Wert fuer `APP_SECRET` eintragen.
5. Sicherstellen, dass `storage/` vom Webserver beschreibbar ist.
6. Im Browser `/install.php` aufrufen.
7. Admin-Benutzername und Passwort festlegen.
8. Installation bestaetigen.
9. Danach unter `/admin/login.php` anmelden.

Wichtig: Die Installation setzt die Datenbank zurueck. Beim Ausfuehren von `install.php` werden vorhandene Tabellen in der konfigurierten Datenbank geloescht und neu angelegt.

## Konfiguration

Die zentrale Konfiguration liegt in `config.php`.

### MySQL

```php
const DB_DRIVER = 'mysql';
const DB_MYSQL_HOST = 'localhost';
const DB_MYSQL_PORT = 3306;
const DB_MYSQL_NAME = 'datenbankname';
const DB_MYSQL_USER = 'benutzer';
const DB_MYSQL_PASS = 'passwort';
const DB_MYSQL_CHARSET = 'utf8mb4';
```

### SQLite

```php
const DB_DRIVER = 'sqlite';
const DB_SQLITE_PATH = __DIR__ . '/storage/database.sqlite';
```

SQLite eignet sich fuer lokale Tests oder sehr kleine Websites. Fuer produktive Websites ist MySQL/MariaDB meist die bessere Wahl.

### Uploads

Uploads werden standardmaessig in `storage/uploads` gespeichert. Die erlaubten Dateitypen und die maximale Dateigroesse werden in `config.php` gesteuert:

```php
const UPLOAD_MAX_BYTES = 20 * 1024 * 1024;
const UPLOAD_ALLOWED_EXT = ['jpg','jpeg','png','webp','gif','svg','mp4','webm','pdf'];
```

## Verwendung

### Admin-Bereich

Der Admin-Bereich ist unter `/admin/` erreichbar. Nicht angemeldete Benutzer werden automatisch zum Login weitergeleitet.

### Seiten verwalten

Unter `/admin/` werden alle Seiten als Seitenbaum angezeigt. Dort koennen Seiten:

- erstellt und bearbeitet werden
- als Entwurf oder veroeffentlicht markiert werden
- als Startseite gesetzt werden
- verschoben, untergeordnet oder hochgestuft werden
- geloescht werden, sofern es nicht die Startseite ist

Veroeffentlichte Seiten sind oeffentlich sichtbar. Entwuerfe bleiben im Frontend unsichtbar.

### Seiten bearbeiten

Der Editor befindet sich unter `/admin/editor.php`. Jede Seite besteht aus Bloecken. Verfuegbare Blocktypen sind unter anderem:

- Hero
- Text
- Karten
- Statistiken
- Team
- Preise
- Bewertungen
- Akkordeon
- Tabs
- Timeline
- Call-to-Action
- Bild
- Galerie
- Video
- Formular
- Code
- Zitat
- Trenner
- Abstand
- Custom HTML

Pro Seite koennen Titel, URL-Slug, uebergeordnete Seite, Meta Description, Status und Startseiten-Markierung gepflegt werden.

### Mediathek

Unter `/admin/media.php` koennen Dateien hochgeladen, angesehen, kopiert und geloescht werden. Unterstuetzt werden standardmaessig:

- JPG/JPEG
- PNG
- WEBP
- GIF
- SVG
- MP4
- WEBM
- PDF

### Benutzer

Unter `/admin/users.php` koennen Benutzer ihr Profil und Passwort aendern. Administratoren koennen zusaetzlich neue Benutzer erstellen und andere Benutzer loeschen.

Rollen:

- `admin`: Zugriff auf Einstellungen und Benutzerverwaltung
- `editor`: Zugriff auf Seiten, Editor und Mediathek

### Website-Einstellungen

Unter `/admin/settings.php` koennen Administratoren zentrale Website-Daten pflegen:

- Website-Name
- Logo-Text
- Tagline
- Standard Meta Description
- Akzentfarbe
- helles oder dunkles Theme
- Display- und Fliesstext-Schrift

## Projektstruktur

```text
.
|-- admin/              # Admin-Oberflaeche, Editor, Medien und Benutzer
|-- assets/
|   |-- css/            # Styles fuer Website und Admin-Bereich
|   `-- js/             # JavaScript fuer Editor, Admin und Theme
|-- core/               # Bootstrap, Datenbank, Auth, Helper und Rendering
|-- storage/            # Uploads und optionale SQLite-Datenbank
|-- .htaccess           # Rewrite-Regeln und Schutzregeln
|-- config.example.php  # Beispielkonfiguration
|-- config.php          # Lokale Konfiguration, nicht veroeffentlichen
|-- index.php           # Startseite
|-- install.php         # Installationsroutine
`-- page.php            # Routing fuer Unterseiten
```

## Sicherheitshinweise

- `config.php` darf nicht oeffentlich in ein Repository mit echten Zugangsdaten eingecheckt werden.
- `APP_SECRET` muss in produktiven Installationen geaendert werden.
- Das Standardpasswort aus der Beispielkonfiguration darf nicht produktiv verwendet werden.
- `install.php` sollte nach erfolgreicher Installation entfernt oder serverseitig geschuetzt werden.
- Der Ordner `storage/` muss beschreibbar sein, sollte aber keine PHP-Ausfuehrung erlauben. Die mitgelieferte `.htaccess` blockiert PHP-Dateien in `storage/`.
- Regelmaessige Backups der Datenbank und des Ordners `storage/uploads` sind empfohlen.

## Lokale Entwicklung

Mit PHPs integriertem Webserver kann das Projekt lokal getestet werden:

```bash
php -S localhost:8000
```

Danach ist die Website unter `http://localhost:8000` erreichbar. Je nach Umgebung funktionieren Apache-spezifische `.htaccess`-Regeln im integrierten PHP-Server nicht identisch. Fuer realistische Tests sollte Apache mit `mod_rewrite` verwendet werden.

## Wartung

Wichtige Daten fuer Backups:

- Datenbanktabellen `users`, `pages`, `media` und `settings`
- Upload-Ordner `storage/uploads`
- lokale `config.php`

Bei einem Umzug auf einen anderen Server muessen Datenbank, Uploads und Konfiguration gemeinsam uebertragen werden.
