<?php
$_user = current_user();
$_currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$activeMap = [
    'index.php'    => 'pages',
    'editor.php'   => 'editor',
    'media.php'    => 'media',
    'users.php'    => 'users',
    'settings.php' => 'settings',
];
$active = $activeMap[$_currentScript] ?? '';
?>
<aside class="cms-sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="sidebar-logo-mark">W</div>
        <span class="sidebar-logo-text">Web<span>CMS</span></span>
    </div>

    <div class="sidebar-section-label">Verwaltung</div>
    <nav class="cms-nav">
        <a href="<?= e(site_url('/admin/')) ?>" class="<?= $active==='pages' ? 'active' : '' ?>">
            <span class="nav-icon">📄</span> Seiten
        </a>
        <a href="<?= e(site_url('/admin/editor.php')) ?>" class="<?= $active==='editor' ? 'active' : '' ?>">
            <span class="nav-icon">✏️</span> Neue Seite
        </a>
        <a href="<?= e(site_url('/admin/media.php')) ?>" class="<?= $active==='media' ? 'active' : '' ?>">
            <span class="nav-icon">🖼</span> Mediathek
        </a>
        <a href="<?= e(site_url('/admin/users.php')) ?>" class="<?= $active==='users' ? 'active' : '' ?>">
            <span class="nav-icon">👥</span> Benutzer
        </a>
        <?php if (!empty($_user) && $_user['role'] === 'admin'): ?>
        <a href="<?= e(site_url('/admin/settings.php')) ?>" class="<?= $active==='settings' ? 'active' : '' ?>">
            <span class="nav-icon">⚙️</span> Einstellungen
        </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-section-label" style="margin-top:16px">Website</div>
    <nav class="cms-nav">
        <a href="<?= e(site_url('/')) ?>" target="_blank" rel="noopener">
            <span class="nav-icon">↗</span> Zur Website
        </a>
    </nav>

    <div class="sidebar-spacer"></div>
    <div class="sidebar-user">
        <div class="user-avatar"><?= e(strtoupper(substr($_user['username'], 0, 2))) ?></div>
        <div class="user-info">
            <strong><?= e($_user['username']) ?></strong>
            <span><?= e($_user['role']) ?></span>
        </div>
        <a class="sidebar-logout-btn" href="<?= e(site_url('/admin/logout.php')) ?>" title="Abmelden" aria-label="Abmelden">Abmelden</a>
    </div>
</aside>
