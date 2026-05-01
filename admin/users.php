<?php
declare(strict_types=1);
require __DIR__ . '/../core/bootstrap.php';
require_login();

$user = current_user();
$isAdmin = $user && $user['role'] === 'admin';
$users = $isAdmin ? db()->query('SELECT id, username, email, role, created_at, last_login FROM users ORDER BY id ASC')->fetchAll() : [];
$flash = $_SESSION['flash'] ?? '';
$flashErr = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash'], $_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="de" data-theme="<?= e(setting('theme','light')) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Benutzer – WebCMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(site_url('/assets/css/site.css')) ?>">
    <link rel="stylesheet" href="<?= e(site_url('/assets/css/admin.css')) ?>">
    <style>:root{--accent:<?= e(setting('accent_color', DEFAULT_ACCENT)) ?>;--accent-light:color-mix(in srgb,var(--accent) 14%,transparent);--accent-dark:color-mix(in srgb,var(--accent) 75%,#000)}</style>
</head>
<body class="admin-body">
<?php include __DIR__ . '/_sidebar.php'; ?>

<main class="cms-main">
    <div class="cms-topbar">
        <div class="topbar-left">
            <div class="view-header-line"></div>
            <h1>Benutzerverwaltung</h1>
            <p>Konten, Rollen und Passwörter</p>
        </div>
        <div class="topbar-actions">
            <button class="theme-toggle-btn cms-theme-toggle" type="button" data-theme-toggle aria-pressed="false">
                <span class="theme-icon theme-icon-sun" aria-hidden="true">☀</span>
                <span class="theme-icon theme-icon-moon" aria-hidden="true">☾</span>
                <span class="sr-only" data-theme-label>Mond</span>
            </button>
        </div>
    </div>

    <?php if ($flash): ?><div class="alert alert-success" style="margin-bottom:16px"><span class="alert-icon">✓</span><div><p class="alert-body"><?= e($flash) ?></p></div></div><?php endif; ?>
    <?php if ($flashErr): ?><div class="alert alert-danger" style="margin-bottom:16px"><span class="alert-icon">✕</span><div><p class="alert-body"><?= e($flashErr) ?></p></div></div><?php endif; ?>

    <div class="settings-grid">
        <?php if ($isAdmin): ?>
        <div class="panel">
            <div class="panel-header"><h3>Neuen Benutzer anlegen</h3></div>
            <div class="panel-body">
                <form method="post" action="<?= e(site_url('/admin/user_create.php')) ?>">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <div class="form-group"><label class="form-label">Benutzername</label><input class="input" name="username" required minlength="3"></div>
                    <div class="form-group"><label class="form-label">E-Mail</label><input class="input" type="email" name="email"></div>
                    <div class="form-group"><label class="form-label">Passwort</label><input class="input" type="password" name="password" required minlength="6"></div>
                    <div class="form-group"><label class="form-label">Rolle</label>
                        <select class="select" name="role">
                            <option value="editor">Redakteur</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    <button class="btn btn-primary" type="submit">+ Benutzer anlegen</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="panel">
            <div class="panel-header"><h3>Meine Daten ändern</h3></div>
            <div class="panel-body">
                <form method="post" action="<?= e(site_url('/admin/user_profile.php')) ?>">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                    <div class="form-group"><label class="form-label">Benutzername</label><input class="input" name="username" value="<?= e($user['username']) ?>" required minlength="3"></div>
                    <div class="form-group"><label class="form-label">E-Mail</label><input class="input" type="email" name="email" value="<?= e($user['email'] ?? '') ?>"></div>
                    <button class="btn btn-primary" type="submit">Daten speichern</button>
                </form>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header"><h3>Mein Passwort ändern</h3></div>
            <div class="panel-body">
                <form method="post" action="<?= e(site_url('/admin/user_password.php')) ?>">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                    <div class="form-group"><label class="form-label">Aktuelles Passwort</label><input class="input" type="password" name="current_password" required></div>
                    <div class="form-group"><label class="form-label">Neues Passwort</label><input class="input" type="password" name="new_password" required minlength="6"></div>
                    <div class="form-group"><label class="form-label">Bestätigen</label><input class="input" type="password" name="new_password2" required minlength="6"></div>
                    <button class="btn btn-primary" type="submit">Passwort ändern</button>
                </form>
            </div>
        </div>
    </div>

    <?php if ($isAdmin): ?>
    <div class="panel" style="margin-top:20px">
        <div class="panel-header"><h3>Alle Benutzer</h3></div>
        <div class="pages-table-wrap">
            <table class="pages-table">
                <thead>
                    <tr><th>Benutzer</th><th>E-Mail</th><th>Rolle</th><th>Letzter Login</th><th>Aktionen</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u):
                        $color = '#' . substr(md5($u['username']), 0, 6);
                        $initials = strtoupper(substr($u['username'], 0, 2));
                        $isCurrent = (int)$u['id'] === (int)$user['id'];
                    ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px">
                                    <div class="user-row-avatar" style="background:<?= e($color) ?>"><?= e($initials) ?></div>
                                    <div>
                                        <strong style="font-size:.875rem"><?= e($u['username']) ?> <?= $isCurrent ? '<span class="badge badge-accent" style="font-size:.6rem;margin-left:4px">Du</span>' : '' ?></strong>
                                        <div style="font-size:.72rem;color:var(--text-subtle)">ID #<?= (int)$u['id'] ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="font-size:.82rem;color:var(--text-muted)"><?= e($u['email'] ?? '—') ?></td>
                            <td><span class="badge <?= $u['role']==='admin' ? 'badge-accent' : 'badge-neutral' ?>"><?= $u['role']==='admin' ? 'Administrator' : 'Redakteur' ?></span></td>
                            <td style="font-size:.78rem;color:var(--text-subtle)"><?= e($u['last_login'] ? date('d.m.Y H:i', strtotime($u['last_login'])) : 'nie') ?></td>
                            <td>
                                <div class="action-row">
                                    <?php if (!$isCurrent): ?>
                                        <form method="post" action="<?= e(site_url('/admin/user_delete.php')) ?>" data-confirm="Benutzer wirklich löschen?" data-confirm-title="Benutzer löschen" style="display:inline">
                                            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                            <button class="btn btn-danger btn-sm" type="submit">Löschen</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="font-size:.75rem;color:var(--text-subtle)">eigenes Konto</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</main>

<button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
<div class="toast-stack" id="toastStack"></div>
<script src="<?= e(site_url('/assets/js/theme.js')) ?>"></script>
<script src="<?= e(site_url('/assets/js/admin.js')) ?>"></script>
</body>
</html>
