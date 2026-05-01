<?php
declare(strict_types=1);
require __DIR__ . '/../core/bootstrap.php';
require_admin();

$flash = $_SESSION['flash'] ?? '';
$flashErr = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $logoParts = [
            trim((string)($_POST['logo_text'] ?? '')),
            trim((string)($_POST['logo_accent_text'] ?? '')),
            trim((string)($_POST['logo_text_after'] ?? '')),
        ];
        if (implode('', $logoParts) === '') {
            throw new RuntimeException('Mindestens ein Logo-Feld muss ausgefüllt sein.');
        }

        $allowed = ['site_name','logo_text','logo_accent_text','logo_text_after','site_tagline','accent_color','theme','font_display','font_body','meta_default'];
        foreach ($allowed as $key) {
            if (isset($_POST[$key])) set_setting($key, (string)$_POST[$key]);
        }
        $_SESSION['flash'] = 'Einstellungen gespeichert.';
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = $e->getMessage();
    }
    redirect('/admin/settings.php');
}
?>
<!DOCTYPE html>
<html lang="de" data-theme="<?= e(setting('theme','light')) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Einstellungen – WebCMS</title>
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
            <h1>Einstellungen</h1>
            <p>Website-Konfiguration und Design</p>
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

    <form method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <div class="settings-grid">
            <div class="panel">
                <div class="panel-header"><h3>🌐 Allgemein</h3></div>
                <div class="panel-body">
                    <div class="form-group"><label class="form-label">Website-Name</label><input class="input" name="site_name" value="<?= e(setting('site_name', SITE_NAME)) ?>"></div>
                    <div class="form-group"><label class="form-label">Logo Text vor Akzent</label><input class="input" name="logo_text" value="<?= e(logo_text()) ?>"></div>
                    <div class="form-group"><label class="form-label">Logo Akzent-Text</label><input class="input" name="logo_accent_text" value="<?= e(logo_accent_text()) ?>"></div>
                    <div class="form-group"><label class="form-label">Logo Text nach Akzent</label><input class="input" name="logo_text_after" value="<?= e(logo_text_after()) ?>"></div>
                    <div class="form-group"><label class="form-label">Tagline</label><input class="input" name="site_tagline" value="<?= e(setting('site_tagline', SITE_TAGLINE)) ?>"></div>
                    <div class="form-group"><label class="form-label">Standard Meta Description</label><textarea class="textarea" name="meta_default" rows="2"><?= e(setting('meta_default', '')) ?></textarea></div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header"><h3>🎨 Design</h3></div>
                <div class="panel-body">
                    <div class="form-group">
                        <label class="form-label">Akzentfarbe</label>
                        <div style="display:flex;gap:8px;align-items:center">
                            <input type="color" name="accent_color" value="<?= e(setting('accent_color', DEFAULT_ACCENT)) ?>"
                                   style="width:40px;height:40px;border:1.5px solid var(--border);border-radius:8px;cursor:pointer;padding:0">
                            <input class="input font-mono" id="accentText" value="<?= e(setting('accent_color', DEFAULT_ACCENT)) ?>" style="width:130px" readonly>
                        </div>
                    </div>
                    <div class="form-group"><label class="form-label">Theme</label>
                        <select class="select" name="theme">
                            <option value="light" <?= setting('theme','light')==='light'?'selected':'' ?>>Hell</option>
                            <option value="dark" <?= setting('theme','light')==='dark'?'selected':'' ?>>Dunkel</option>
                        </select>
                    </div>
                    <div class="form-group"><label class="form-label">Schrift (Display)</label>
                        <select class="select" name="font_display">
                            <option <?= setting('font_display')==='Syne'?'selected':'' ?>>Syne</option>
                            <option <?= setting('font_display')==='Inter'?'selected':'' ?>>Inter</option>
                            <option <?= setting('font_display')==='Playfair Display'?'selected':'' ?>>Playfair Display</option>
                        </select>
                    </div>
                    <div class="form-group"><label class="form-label">Schrift (Fließtext)</label>
                        <select class="select" name="font_body">
                            <option <?= setting('font_body')==='DM Sans'?'selected':'' ?>>DM Sans</option>
                            <option <?= setting('font_body')==='Inter'?'selected':'' ?>>Inter</option>
                            <option <?= setting('font_body')==='Roboto'?'selected':'' ?>>Roboto</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top:20px;display:flex;gap:8px">
            <button class="btn btn-primary" type="submit">💾  Einstellungen speichern</button>
            <a class="btn btn-secondary" href="<?= e(site_url('/')) ?>" target="_blank" rel="noopener">Vorschau ↗</a>
        </div>
    </form>
</main>

<button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
<div class="toast-stack" id="toastStack"></div>
<script src="<?= e(site_url('/assets/js/theme.js')) ?>"></script>
<script src="<?= e(site_url('/assets/js/admin.js')) ?>"></script>
<script>
document.querySelector('input[name=accent_color]')?.addEventListener('input', function() {
    document.getElementById('accentText').value = this.value;
});
</script>
</body>
</html>
