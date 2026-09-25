<?php
/** @var array $db */
/** @var array|null $api */
/** @var bool $tableExists */
/** @var array|null $installResult */
/** @var array $php */
?>
<div class="auth-card" style="width:min(640px,100%)">
  <div class="auth-brand">
    <img src="<?= asset('img/arya-logo.png') ?>" alt="Arya">
    <h1>Diagnóstico</h1>
    <p>Rutas · PHP · Supabase</p>
  </div>

  <?php if ($installResult): ?>
    <div class="alert alert-<?= $installResult['ok'] ? 'success' : 'error' ?>">
      <?= e($installResult['message']) ?>
    </div>
  <?php endif; ?>

  <div class="panel" style="margin-bottom:16px;box-shadow:none">
    <div class="panel-header"><h3>Enrutamiento</h3></div>
    <div class="panel-body" style="font-size:0.85rem;color:var(--text-secondary)">
      <div class="info-item" style="margin-bottom:8px"><label>Ruta resuelta</label><span style="color:var(--cyan)"><?= e($php['resolved']) ?></span></div>
      <div class="info-item" style="margin-bottom:8px"><label>PATH_INFO</label><span><?= e((string) $php['path_info']) ?></span></div>
      <div class="info-item" style="margin-bottom:8px"><label>REQUEST_URI</label><span><?= e($php['request']) ?></span></div>
      <div class="info-item"><label>SCRIPT_NAME</label><span><?= e($php['script']) ?></span></div>
      <p style="margin-top:12px">Usa siempre URLs con <code style="color:var(--gold)">index.php</code>:</p>
      <ul style="margin-top:8px;padding-left:18px;list-style:disc">
        <li><a href="<?= url('login') ?>" style="color:var(--cyan)"><?= e(url('login')) ?></a></li>
        <li><a href="<?= url('dashboard') ?>" style="color:var(--cyan)"><?= e(url('dashboard')) ?></a></li>
      </ul>
    </div>
  </div>

  <div class="panel" style="margin-bottom:16px;box-shadow:none">
    <div class="panel-header"><h3>PHP</h3></div>
    <div class="panel-body" style="font-size:0.85rem">
      <div>Versión: <strong><?= e($php['version']) ?></strong></div>
      <div>pdo_pgsql: <span class="badge badge-<?= $php['pdo_pgsql'] ? 'success' : 'danger' ?>"><?= $php['pdo_pgsql'] ? 'OK' : 'FALTA' ?></span></div>
      <div>curl: <span class="badge badge-<?= $php['curl'] ? 'success' : 'danger' ?>"><?= $php['curl'] ? 'OK' : 'FALTA' ?></span></div>
      <div>mbstring: <span class="badge badge-<?= $php['mbstring'] ? 'success' : 'warning' ?>"><?= $php['mbstring'] ? 'OK' : 'FALTA' ?></span></div>
    </div>
  </div>

  <div class="panel" style="margin-bottom:16px;box-shadow:none">
    <div class="panel-header"><h3>PostgreSQL (DATABASE_URL)</h3></div>
    <div class="panel-body" style="font-size:0.85rem">
      <div class="alert alert-<?= $db['ok'] ? 'success' : 'error' ?>" style="margin-bottom:12px"><?= e($db['message']) ?></div>
      <?php foreach ($db['details'] as $k => $v): ?>
        <div class="info-item" style="margin-bottom:6px">
          <label><?= e((string) $k) ?></label>
          <span><?= e(is_scalar($v) || $v === null ? (string) $v : json_encode($v)) ?></span>
        </div>
      <?php endforeach; ?>
      <div style="margin-top:10px">Tabla <code>maestrousuario</code>:
        <span class="badge badge-<?= $tableExists ? 'success' : 'warning' ?>"><?= $tableExists ? 'existe' : 'no existe' ?></span>
      </div>
    </div>
  </div>

  <div class="panel" style="margin-bottom:16px;box-shadow:none">
    <div class="panel-header"><h3>API Supabase (Kong :8000)</h3></div>
    <div class="panel-body" style="font-size:0.85rem">
      <?php if ($api): ?>
        <div class="alert alert-<?= $api['ok'] ? 'success' : 'error' ?>"><?= e($api['message']) ?> (HTTP <?= (int) $api['status'] ?>)</div>
      <?php else: ?>
        <div class="alert alert-error">Falta SUPABASE_URL o las API keys en .env</div>
      <?php endif; ?>
    </div>
  </div>

  <form method="POST" action="<?= url('diagnostico') ?>">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-primary btn-block" <?= $db['ok'] ? '' : 'disabled' ?>>
      Instalar esquema + crear admin
    </button>
  </form>

  <p style="text-align:center;margin-top:16px;font-size:0.8rem;color:var(--text-muted)">
    <a href="<?= url('login') ?>" style="color:var(--cyan)">Ir al login</a>
  </p>
</div>
