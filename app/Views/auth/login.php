<?php
/** @var string $ocpUrl */
/** @var string $ocpLabel */
/** @var string $ocpStep */
/** @var array|null $ocpPending */
/** @var array|null $ocpChannels */
/** @var array|null $ocpHandoff */
/** @var bool $openOcp */
$ocpUrl = $ocpUrl ?? ocp_public_url();
$ocpLabel = $ocpLabel ?? (string) config('ocp.label', 'OCP Arya');
$ocpStep = $ocpStep ?? 'credentials';
$ocpPending = $ocpPending ?? null;
$ocpChannels = $ocpChannels ?? null;
$ocpHandoff = $ocpHandoff ?? null;
$openOcp = !empty($openOcp);
$hasOcp = $ocpUrl !== '';
$authMode = ($hasOcp && $openOcp) ? 'ocp' : 'crm';
?>
<div class="auth-shell<?= $hasOcp ? '' : ' auth-shell--crm-only' ?>" id="authShell" data-auth-mode="<?= e($authMode) ?>">
  <aside class="auth-visual">
    <div class="auth-visual-glow" aria-hidden="true"></div>
    <div class="auth-visual-grid" aria-hidden="true"></div>
    <div class="auth-visual-content">
      <div class="auth-hero-logo">
        <span class="auth-hero-aura" aria-hidden="true"></span>
        <span class="auth-hero-ring" aria-hidden="true"></span>
        <img
          src="<?= asset('img/arya-logo.png') ?>"
          alt="Arya"
          width="512"
          height="512"
          decoding="async"
          fetchpriority="high"
        >
      </div>
      <div class="auth-hero-copy">
        <p class="auth-hero-mark">ARYA</p>
        <div class="auth-hero-modes">
          <p class="auth-hero-mode auth-hero-mode--crm">CRM</p>
          <?php if ($hasOcp): ?>
            <p class="auth-hero-mode auth-hero-mode--ocp">OCP</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </aside>

  <section class="auth-panel">
    <div class="auth-panel-viewport">
      <div class="auth-panel-track" id="authPanelTrack">
        <div class="auth-face auth-face--crm" id="authFaceCrm" data-auth-face="crm">
          <div class="auth-panel-inner">
            <header class="auth-panel-header">
              <p class="auth-panel-eyebrow">Arya CRM</p>
              <h1>Gestión comercial</h1>
              <p class="auth-panel-lead">Acceso al CRM omnicanal para clientes, conversaciones e inteligencia comercial.</p>
            </header>

            <form method="POST" action="<?= url('login') ?>" class="auth-form" id="authLoginForm">
              <?= csrf_field() ?>
              <div class="form-group auth-field">
                <label class="form-label" for="email">Usuario</label>
                <input class="form-control" type="text" id="email" name="email" value="<?= e((string) old('email')) ?>" placeholder="Usuario" required autofocus autocomplete="username" spellcheck="false" inputmode="text">
              </div>
              <div class="form-group auth-field">
                <label class="form-label" for="password">Contraseña</label>
                <input class="form-control" type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
              </div>
              <button type="submit" class="btn btn-primary btn-block auth-submit" id="authSubmitBtn">
                <span class="auth-submit-label">Entrar al CRM</span>
              </button>
            </form>

            <?php if ($hasOcp): ?>
              <div class="auth-access-divider" role="separator">
                <span>Ingreso gerencial</span>
              </div>

              <button
                type="button"
                class="auth-ocp-link"
                id="ocpOpenBtn"
                data-ocp-open
                aria-controls="authFaceOcp"
              >
                <span class="auth-ocp-link__icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" width="15" height="15">
                    <rect x="5" y="11" width="14" height="10" rx="2"/>
                    <path d="M8 11V8a4 4 0 0 1 8 0v3"/>
                  </svg>
                </span>
                <span class="auth-ocp-link__label">Acceder a <?= e($ocpLabel) ?></span>
              </button>
            <?php endif; ?>

            <p class="auth-copyright">
              <a href="https://app.aisscol.com/CV/NicolasCortesOviedo.php" target="_blank" rel="noopener noreferrer">
                © 2026 Nicolas Cortes Oviedo. Todos los derechos reservados.
              </a>
            </p>
          </div>
        </div>

        <?php if ($hasOcp): ?>
          <?php require __DIR__ . '/partials/ocp-login-modal.php'; ?>
        <?php endif; ?>
      </div>
    </div>
  </section>
</div>
<?php unset($_SESSION['_old']); ?>
