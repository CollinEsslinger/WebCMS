<?php
declare(strict_types=1);
require __DIR__ . '/core/bootstrap.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['admin_user'] ?? '');
    $pass = $_POST['admin_pass'] ?? '';
    $pass2 = $_POST['admin_pass2'] ?? '';
    $confirmReset = !empty($_POST['confirm_reset']);

    if (!$confirmReset) $error = 'Bitte bestätigen Sie, dass alle bestehenden Tabellen und Daten in dieser Datenbank gelöscht werden dürfen.';
    elseif (strlen($user) < 3) $error = 'Benutzername muss mindestens 3 Zeichen lang sein.';
    elseif (strlen($pass) < 6) $error = 'Passwort muss mindestens 6 Zeichen lang sein.';
    elseif ($pass !== $pass2) $error = 'Passwörter stimmen nicht überein.';
    else {
        try {
            run_install($user, $pass);
            redirect('/admin/login.php?installed=1');
        } catch (Throwable $e) {
            $error = 'Installations-Fehler: ' . $e->getMessage();
        }
    }
}

$installed = is_installed();
$dbError = database_connection_error();
if ($installed) {
    $message = 'Das CMS ist bereits installiert. Eine erneute Installation löscht alle Tabellen und Daten in dieser Datenbank unwiderruflich und legt sie neu an.';
} elseif ($dbError !== null && $error === '') {
    $error = 'Datenbankverbindung fehlgeschlagen: ' . $dbError;
}
?>
<!DOCTYPE html>
<html lang="de" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebCMS – Installation</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(site_url('/assets/css/site.css')) ?>">
    <link rel="stylesheet" href="<?= e(site_url('/assets/css/admin.css')) ?>">
</head>
<body class="auth-body">
    <div class="auth-screen">
        <div class="auth-card">
            <div class="auth-logo">
                <div class="auth-logo-dot">W</div>
                web<span>CMS</span>
            </div>
            <h1>Installation</h1>
            <p class="text-muted" style="margin-bottom:20px">Erstellen Sie das Admin-Konto, um zu starten.</p>

            <?php if ($message): ?>
                <div class="alert alert-danger" role="alert" style="margin-bottom:16px"><span class="alert-icon">!</span><div><?= e($message) ?></div></div>
            <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert" style="margin-bottom:16px"><span class="alert-icon">✕</span><div><p class="alert-body"><?= e($error) ?></p></div></div>
                <?php endif; ?>

                <form method="post" class="auth-form">
                    <div class="form-group">
                        <label class="form-label">Admin-Benutzername</label>
                        <input class="input" name="admin_user" value="<?= e($_POST['admin_user'] ?? 'admin') ?>" required minlength="3">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Passwort</label>
                        <input class="input" type="password" name="admin_pass" required minlength="6">
                        <span class="form-hint">Mindestens 6 Zeichen</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Passwort wiederholen</label>
                        <input class="input" type="password" name="admin_pass2" required minlength="6">
                    </div>
                    <label class="checkbox-row" style="display:flex;gap:10px;align-items:flex-start;margin:10px 0 18px">
                        <input type="checkbox" name="confirm_reset" value="1" required>
                        <span>Ich bestätige, dass <strong>alle bestehenden Tabellen und Daten in dieser Datenbank</strong> gelöscht und neu angelegt werden dürfen.</span>
                    </label>
                    <button class="btn btn-primary btn-lg" type="submit" style="width:100%">Datenbank zurücksetzen und CMS installieren</button>
                </form>

                <p class="auth-note" style="margin-top:18px">
                    Datenbank-Treiber: <strong><?= DB_DRIVER === 'sqlite' ? 'SQLite (eingebettet)' : 'MySQL' ?></strong>
                </p>
        </div>
    </div>
</body>
</html>
