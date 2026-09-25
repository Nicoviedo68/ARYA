<?php
/** @var string $content */
/** @var string $title */
/** @var string $active */

use Arya\Helpers\Menu;
use Arya\Models\User;

$user = auth();
$profile = null;

// Evitar consultar maestrousuario en CADA request (Postgres remoto = lentitud)
$profileAge = time() - (int) ($_SESSION['profile_synced_at'] ?? 0);
$needsProfileRefresh = $user && isset($user['id']) && ($profileAge > 300 || empty($user['name']));
$userOnline = false;

if ($needsProfileRefresh && method_exists(User::class, 'find') && User::tableExists()) {
    $profile = User::find($user['id']);
    if ($profile) {
        $_SESSION['user'] = [
            'id'       => $profile['id'],
            'name'     => (string) $profile['nombre'],
            'email'    => (string) $profile['correo'],
            'usuario'  => (string) $profile['usuario'],
            'telefono' => $profile['telefono'] ?? null,
            'role'     => strtoupper((string) ($profile['alias'] ?? 'AGENT')),
            'avatar'   => !empty($profile['imagen']) ? (string) $profile['imagen'] : null,
        ];
        $_SESSION['profile_synced_at'] = time();
        $user = $_SESSION['user'];
        $userOnline = !empty($profile['activo']);
    }
} else {
    $profile = is_array($user) ? [
        'id'       => $user['id'] ?? null,
        'nombre'   => $user['name'] ?? '',
        'correo'   => $user['email'] ?? '',
        'usuario'  => $user['usuario'] ?? '',
        'telefono' => $user['telefono'] ?? null,
        'alias'    => $user['role'] ?? 'AGENT',
        'imagen'   => $user['avatar'] ?? null,
        'activo'   => true,
    ] : null;
    $userOnline = (bool) $user;
}

// Cuenta deshabilitada → no mantener presencia ni seguir en el shell
if ($user && isset($user['id']) && User::tableExists() && !User::isHabilitado($user['id'])) {
    User::logoutSession($user['id']);
    unset($_SESSION['user'], $_SESSION['online_touched_at'], $_SESSION['profile_synced_at'], $_SESSION['habilitado_checked_at']);
    session_regenerate_id(true);
    flash('error', 'Tu cuenta fue desactivada. Contacta al administrador.');
    redirect('login');
}

// Heartbeat: presencia online (activo) solo si la cuenta sigue habilitada
$onlineTouchAge = time() - (int) ($_SESSION['online_touched_at'] ?? 0);
if ($user && isset($user['id']) && User::tableExists() && $onlineTouchAge >= 60) {
    User::ensureActive($user['id']);
    $_SESSION['online_touched_at'] = time();
    $userOnline = true;
} elseif ($user) {
    $userOnline = true;
}

Menu::bootstrap();

$initials = $user ? mb_strtoupper(mb_substr((string) $user['name'], 0, 1)) : 'A';
$live = is_live_data();
$pageActive = (string) ($active ?? '');
?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#0A192F" id="themeColorMeta">
  <meta name="color-scheme" content="dark light">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="mobile-web-app-capable" content="yes">
  <title><?= e($title ?? 'Arya') ?> — Arya CRM</title>
  <script>
    (function () {
      try {
        var t = localStorage.getItem('arya.theme');
        if (t !== 'light' && t !== 'dark') t = 'dark';
        document.documentElement.setAttribute('data-theme', t);
      } catch (e) {
        document.documentElement.setAttribute('data-theme', 'dark');
      }
    })();
  </script>
  <link rel="icon" href="<?= asset('img/favicon.ico') ?>" type="image/x-icon" sizes="any">
  <link rel="icon" href="<?= asset('img/arya-logo.png') ?>" type="image/png" sizes="32x32">
  <link rel="apple-touch-icon" href="<?= asset('img/arya-logo.png') ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Sora:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset('css/app.css') ?>?v=topbar-fixed1">
  <?php if (in_array($pageActive, ['dashboard', 'reports'], true)): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
  <?php endif; ?>
</head>
<body data-active="<?= e($pageActive) ?>">
  <div class="app-shell">
    <?php /* Overlay dentro del shell: así no tapa el sidebar (mismo stacking context) */ ?>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <?php \Arya\Core\View::partial('partials/sidebar', [
        'active'   => $pageActive,
        'user'     => $user,
        'profile'  => $profile,
        'initials' => $initials,
    ]); ?>

    <div class="main-wrap">
      <header class="topbar">
        <div class="topbar-left">
          <button type="button" class="menu-toggle" id="menuToggle" aria-label="Menú">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
          </button>
          <h1 class="page-title" id="pageTitle"><?= e($title ?? '') ?></h1>
        </div>
        <div class="topbar-actions">
          <div class="notif-wrap" id="notifWrap">
            <button type="button" class="notif-bell" id="notifBellBtn" aria-label="Notificaciones omnicanal" aria-expanded="false" aria-controls="notifPanel" title="Mensajes omnicanal">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" width="20" height="20" aria-hidden="true">
                <path d="M18 8a6 6 0 1 0-12 0c0 7-3 7-3 7h18s-3 0-3-7"/>
                <path d="M13.7 21a2 2 0 0 1-3.4 0"/>
              </svg>
              <span class="notif-bell-dot" id="notifBellDot" hidden></span>
            </button>
            <div class="notif-panel" id="notifPanel" hidden>
              <div class="notif-panel-head">
                <div>
                  <strong>Inbox omnicanal</strong>
                  <p class="notif-panel-meta" id="notifPanelMeta">Sin mensajes nuevos</p>
                </div>
                <a class="btn btn-ghost btn-sm" href="<?= url('omnicanalidad') ?>" data-spa-nav>Abrir</a>
              </div>
              <div class="notif-panel-stats" id="notifPanelStats" hidden></div>
              <div class="notif-panel-list" id="notifPanelList">
                <p class="notif-empty">No hay conversaciones sin leer.</p>
              </div>
            </div>
          </div>

          <?php if (!$live): ?>
            <span class="status-pill status-pill-demo" title="Usando datos demo locales">
              <span class="status-dot"></span> Modo demo
            </span>
          <?php elseif ($userOnline): ?>
            <span class="status-pill status-pill-online" title="Sesión activa en maestrousuario">
              <span class="status-dot status-dot-pulse"></span>
              <span class="status-pill-label">En línea</span>
            </span>
          <?php else: ?>
            <span class="status-pill status-pill-offline" title="Sin presencia activa">
              <span class="status-dot"></span>
              <span class="status-pill-label">Fuera de línea</span>
            </span>
          <?php endif; ?>
          <form method="POST" action="<?= url('logout') ?>" class="inline-form">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-ghost btn-sm topbar-logout" title="Cerrar sesión">
              <span class="logout-full">Cerrar sesión</span>
              <span class="logout-short">Salir</span>
            </button>
          </form>
        </div>
      </header>

      <main class="content" id="content" data-spa-root>
        <?php if ($msg = flash('success')): ?>
          <div class="alert alert-success" data-auto-dismiss><?= e($msg) ?></div>
        <?php endif; ?>
        <?php if ($msg = flash('error')): ?>
          <div class="alert alert-error" data-auto-dismiss><?= e($msg) ?></div>
        <?php endif; ?>

        <?= $content ?>
      </main>
    </div>
  </div>

  <?php \Arya\Core\View::partial('partials/profile-modal', ['user' => $user, 'profile' => $profile]); ?>

  <script>
    window.ARYA_NOTIF = {
      url: <?= json_encode(url('omnicanalidad/api/notificaciones'), JSON_UNESCAPED_SLASHES) ?>,
      omniUrl: <?= json_encode(url('omnicanalidad'), JSON_UNESCAPED_SLASHES) ?>,
      pollMs: 30000
    };
  </script>
  <script src="<?= asset('js/app.js') ?>?v=icons2" defer></script>
  <?php if (!empty($chartsScript ?? null)): ?>
    <script><?= $chartsScript ?></script>
  <?php endif; ?>
</body>
</html>
