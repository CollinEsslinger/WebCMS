<?php
declare(strict_types=1);
require __DIR__ . '/../core/bootstrap.php';

$error = '';
$installed = !empty($_GET['installed']);

if (is_logged_in()) {
    redirect('/admin/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Benutzername und Passwort erforderlich.';
    } elseif (login_user($username, $password)) {
        redirect('/admin/');
    } else {
        // Small delay to discourage brute force
        usleep(400000);
        $error = 'Ungültige Anmeldedaten.';
    }
}
?>
<!DOCTYPE html>
<html lang="de" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anmelden - WebCMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="<?= e(google_fonts_url()) ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(site_url('/assets/css/site.css')) ?>">
    <link rel="stylesheet" href="<?= e(site_url('/assets/css/admin.css')) ?>">
    <style>:root{<?= theme_css_vars() ?>}</style>
</head>
<body class="auth-body">
    <div class="auth-screen">
        <div class="auth-card">
            <div class="auth-logo">
                <div class="auth-logo-mark">W</div>
                <span class="auth-logo-text">Web<span>CMS</span></span>
            </div>
            <h1>Anmelden</h1>
            <p class="text-muted" style="margin-bottom:20px">Melden Sie sich mit Ihren Zugangsdaten an.</p>

            <?php if ($installed): ?>
                <div class="alert alert-success" role="alert" style="margin-bottom:16px">
                    <span class="alert-icon">✓</span>
                    <div><p class="alert-body">Installation erfolgreich. Sie können sich jetzt anmelden.</p></div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert" style="margin-bottom:16px">
                    <span class="alert-icon">✕</span>
                    <div><p class="alert-body"><?= e($error) ?></p></div>
                </div>
            <?php endif; ?>

            <form method="post" class="auth-form" autocomplete="on">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <div class="form-group">
                    <label class="form-label" for="u">Benutzername</label>
                    <input class="input" id="u" name="username" required autofocus
                           value="<?= e($_POST['username'] ?? '') ?>" autocomplete="username">
                </div>
                <div class="form-group">
                    <label class="form-label" for="p">Passwort</label>
                    <input class="input" id="p" type="password" name="password" required autocomplete="current-password">
                </div>
                <button class="btn btn-primary btn-lg" type="submit" style="width:100%">Anmelden →</button>
            </form>

            <p class="auth-note" style="margin-top:18px">
                <a href="<?= e(site_url('/')) ?>">← Zur Website</a>
            </p>
            <button class="theme-toggle-btn auth-theme-toggle" type="button" data-theme-toggle aria-pressed="false">
                <span class="theme-icon theme-icon-sun" aria-hidden="true">☀</span>
                <span class="theme-icon theme-icon-moon" aria-hidden="true">☾</span>
                <span class="sr-only" data-theme-label>Mond</span>
            </button>
        </div>
    </div>
<script src="<?= e(site_url('/assets/js/theme.js')) ?>"></script>
</body>
</html>
