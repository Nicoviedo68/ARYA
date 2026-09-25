<?php
/** @var string $ocpUrl */
/** @var string $ocpLabel */
/** @var string $ocpStep */
/** @var array|null $ocpPending */
/** @var array|null $ocpChannels */
/** @var array|null $ocpHandoff */
$ocpUrl = rtrim($ocpUrl ?? ocp_public_url(), '/');
$ocpLabel = $ocpLabel ?? (string) config('ocp.label', 'OCP Arya');
$ocpStep = $ocpStep ?? 'credentials';
$ocpPending = $ocpPending ?? null;
$ocpChannels = $ocpChannels ?? null;
$ocpHandoff = $ocpHandoff ?? null;
if ($ocpUrl === '') {
    return;
}
?>
<div class="auth-face auth-face--ocp" id="authFaceOcp" data-auth-face="ocp" aria-hidden="true">
  <div class="auth-panel-inner">
    <?php if (is_array($ocpHandoff)): ?>
      <header class="auth-panel-header auth-panel-header--ocp">
        <p class="auth-panel-eyebrow auth-panel-eyebrow--gold">2FA verificado</p>
        <h1>Entrando a <?= e($ocpLabel) ?></h1>
        <p class="auth-panel-lead">Redirigiendo al panel gerencial…</p>
      </header>
      <form id="ocpHandoffForm" method="post" action="<?= e((string) $ocpHandoff['action']) ?>">
        <?php foreach (($ocpHandoff['payload'] ?? []) as $k => $v): ?>
          <input type="hidden" name="<?= e((string) $k) ?>" value="<?= e((string) $v) ?>">
        <?php endforeach; ?>
        <button type="submit" class="btn btn-ocp btn-block">Continuar a OCP</button>
      </form>
      <script>document.getElementById('ocpHandoffForm')?.submit();</script>

    <?php elseif ($ocpStep === 'channel' && is_array($ocpChannels)): ?>
      <button type="submit" form="ocpCancelForm" class="auth-back-crm" id="ocpBackBtn">
        <span class="auth-back-crm__arrow" aria-hidden="true">←</span>
        <span>Cancelar 2FA</span>
      </button>
      <form id="ocpCancelForm" method="post" action="<?= url('login/ocp/cancelar') ?>" hidden>
        <?= csrf_field() ?>
      </form>

      <header class="auth-panel-header auth-panel-header--ocp">
        <p class="auth-panel-eyebrow auth-panel-eyebrow--gold">Doble factor · canal</p>
        <h1>¿Cómo quieres recibir el código?</h1>
        <p class="auth-panel-lead">
          Hola <strong><?= e((string) $ocpChannels['nombre']) ?></strong>.
          Elige un canal. Los datos se muestran parciales por seguridad.
        </p>
      </header>

      <div class="ocp-channel-list">
        <?php if (!empty($ocpChannels['correo'])): ?>
          <form method="post" action="<?= url('login/ocp/canal') ?>" class="ocp-channel-form">
            <?= csrf_field() ?>
            <input type="hidden" name="tipo" value="correo">
            <button type="submit" class="ocp-channel-card">
              <span class="ocp-channel-card__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" width="20" height="20">
                  <path d="M4 6h16v12H4z"/><path d="M4 7l8 6 8-6"/>
                </svg>
              </span>
              <span class="ocp-channel-card__body">
                <span class="ocp-channel-card__title">Correo electrónico</span>
                <span class="ocp-channel-card__value"><?= e((string) $ocpChannels['correo']) ?></span>
              </span>
              <span class="ocp-channel-card__arrow" aria-hidden="true">→</span>
            </button>
          </form>
        <?php endif; ?>

        <?php if (!empty($ocpChannels['telefono'])): ?>
          <form method="post" action="<?= url('login/ocp/canal') ?>" class="ocp-channel-form">
            <?= csrf_field() ?>
            <input type="hidden" name="tipo" value="telefono">
            <button type="submit" class="ocp-channel-card">
              <span class="ocp-channel-card__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" width="20" height="20">
                  <path d="M7 3h4l1 4-2.5 1.5a12 12 0 0 0 5 5L16 11l4 1v4a2 2 0 0 1-2 2A14 14 0 0 1 5 5a2 2 0 0 1 2-2z"/>
                </svg>
              </span>
              <span class="ocp-channel-card__body">
                <span class="ocp-channel-card__title">Teléfono</span>
                <span class="ocp-channel-card__value"><?= e((string) $ocpChannels['telefono']) ?></span>
              </span>
              <span class="ocp-channel-card__arrow" aria-hidden="true">→</span>
            </button>
          </form>
        <?php endif; ?>
      </div>

    <?php elseif ($ocpStep === 'code' && is_array($ocpPending)): ?>
      <button type="submit" form="ocpCancelForm" class="auth-back-crm" id="ocpBackBtn">
        <span class="auth-back-crm__arrow" aria-hidden="true">←</span>
        <span>Cancelar 2FA</span>
      </button>
      <form id="ocpCancelForm" method="post" action="<?= url('login/ocp/cancelar') ?>" hidden>
        <?= csrf_field() ?>
      </form>

      <header class="auth-panel-header auth-panel-header--ocp">
        <p class="auth-panel-eyebrow auth-panel-eyebrow--gold">Doble factor · gerencial</p>
        <h1>Código de verificación</h1>
        <p class="auth-panel-lead">
          Enviamos un código por
          <strong><?= e(($ocpPending['tipo'] ?? '') === 'telefono' ? 'teléfono' : 'correo') ?></strong>
          a <strong><?= e((string) $ocpPending['nombre']) ?></strong>.
        </p>
      </header>

      <form method="post" action="<?= url('login/ocp/verificar') ?>" class="auth-form auth-form--ocp" id="ocp2faForm" autocomplete="one-time-code">
        <?= csrf_field() ?>
        <div class="form-group auth-field">
          <label class="form-label" for="ocp_codigo">Código 2FA</label>
          <input
            class="form-control auth-2fa-input"
            type="text"
            id="ocp_codigo"
            name="codigo"
            inputmode="numeric"
            pattern="[0-9 ]*"
            maxlength="8"
            placeholder="••••••"
            required
            autofocus
            autocomplete="one-time-code"
          >
        </div>
        <button type="submit" class="btn btn-ocp btn-block auth-submit auth-submit--ocp" id="ocp2faSubmitBtn">
          <span class="auth-submit-label">Verificar y entrar</span>
        </button>
      </form>

    <?php else: ?>
      <button type="button" class="auth-back-crm" data-ocp-close id="ocpBackBtn">
        <span class="auth-back-crm__arrow" aria-hidden="true">←</span>
        <span>Volver al CRM</span>
      </button>

      <header class="auth-panel-header auth-panel-header--ocp">
        <p class="auth-panel-eyebrow auth-panel-eyebrow--gold">Ingreso gerencial · administrativo</p>
        <h1 id="ocpLoginTitle">Acceso <?= e($ocpLabel) ?></h1>
        <p class="auth-panel-lead">Panel de supervisión y control. Requiere doble factor (n8n).</p>
      </header>

      <form
        class="auth-form auth-form--ocp"
        id="ocpLoginForm"
        method="post"
        action="<?= url('login/ocp') ?>"
        autocomplete="on"
      >
        <?= csrf_field() ?>
        <div class="form-group auth-field">
          <label class="form-label" for="ocp_usuario">Usuario</label>
          <input
            class="form-control"
            type="text"
            id="ocp_usuario"
            name="usuario"
            placeholder="Usuario gerencial"
            required
            spellcheck="false"
            autocomplete="username"
            inputmode="text"
          >
        </div>
        <div class="form-group auth-field">
          <label class="form-label" for="ocp_password">Contraseña</label>
          <input
            class="form-control"
            type="password"
            id="ocp_password"
            name="password"
            placeholder="••••••••"
            required
            autocomplete="current-password"
          >
        </div>
        <button type="submit" class="btn btn-ocp btn-block auth-submit auth-submit--ocp" id="ocpSubmitBtn">
          <span class="auth-submit-label">Continuar</span>
        </button>
      </form>
    <?php endif; ?>

    <p class="auth-copyright">
      <a href="https://app.aisscol.com/CV/NicolasCortesOviedo.php" target="_blank" rel="noopener noreferrer">
        © 2026 Nicolas Cortes Oviedo. Todos los derechos reservados.
      </a>
    </p>
  </div>
</div>
