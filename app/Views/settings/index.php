<?php
/** @var list<array<string,mixed>> $accounts */
/** @var list<string> $canales */
/** @var list<array<string,mixed>> $otrasRedes */
/** @var array<string,string> $redesOptions */
/** @var list<array<string,mixed>> $integrations */
/** @var array $db */
/** @var array|null $api */
/** @var bool $tableExists */
/** @var bool $accountsTableOk */
/** @var bool $redesTableOk */
/** @var bool $integrationsTableOk */
/** @var string $metaWebhookUrl */
/** @var string $metaVerifyToken */
/** @var list<array<string,mixed>> $metaInbox */
/** @var int $metaInboxCount */
/** @var string $tab */

$tab = in_array($tab ?? '', ['accounts', 'redes', 'integrations'], true) ? $tab : 'accounts';
$canalLabels = [
    'whatsapp'  => 'WhatsApp',
    'messenger' => 'Messenger',
    'instagram' => 'Instagram',
];
$redesOptions = $redesOptions ?? [];
$estadoBadge = static function (string $estado): string {
    $e = strtolower($estado);
    if (in_array($e, ['conectado', 'activo'], true)) {
        return 'badge-success';
    }
    if (in_array($e, ['error'], true)) {
        return 'badge-danger';
    }
    if (in_array($e, ['pendiente'], true)) {
        return 'badge-warning';
    }
    return 'badge-muted';
};
?>

<div class="settings-tabs">
  <button type="button" class="settings-tab <?= $tab === 'accounts' ? 'active' : '' ?>" data-tab="accounts">Cuentas Meta</button>
  <button type="button" class="settings-tab <?= $tab === 'redes' ? 'active' : '' ?>" data-tab="redes">Otras redes</button>
  <button type="button" class="settings-tab <?= $tab === 'integrations' ? 'active' : '' ?>" data-tab="integrations">Integraciones</button>
</div>

<div class="settings-pane" id="pane-accounts" <?= $tab !== 'accounts' ? 'hidden' : '' ?>>
  <div class="grid grid-2 settings-grid">
    <div class="panel">
      <div class="panel-header">
        <h2>Tokens guardados</h2>
        <span class="badge badge-<?= !empty($accountsTableOk) ? 'success' : 'warning' ?>">
          <?= !empty($accountsTableOk) ? 'token_meta' : 'tabla pendiente' ?>
        </span>
      </div>
      <div class="panel-body">
        <?php if (empty($accounts)): ?>
          <p class="settings-empty">No hay tokens Meta. Agrega WhatsApp, Messenger o Instagram a la derecha.</p>
        <?php else: ?>
          <?php
            $short = static function (?string $value, int $head = 8, int $tail = 6): string {
                $value = trim((string) $value);
                if ($value === '') {
                    return '—';
                }
                $len = mb_strlen($value);
                if ($len <= ($head + $tail + 1)) {
                    return $value;
                }
                return mb_substr($value, 0, $head) . '…' . mb_substr($value, -$tail);
            };
          ?>
          <?php foreach ($accounts as $acc): ?>
            <?php
              $estado = (string) ($acc['estado'] ?? 'desconectado');
              $accId = (int) ($acc['id'] ?? 0);
              $canal = (string) ($acc['canal'] ?? '');
              $canalLabel = $canalLabels[$canal] ?? ucfirst($canal);
              $pageId = (string) ($acc['page_id'] ?? '');
              $ident = (string) ($acc['identificador'] ?? '');
            ?>
            <div class="account-row account-row--token">
              <div class="account-row-main">
                <div class="account-row-title"><?= e($canalLabel) ?></div>
                <div class="account-row-meta" title="<?= e($pageId) ?>">
                  <span class="label-k">page_id</span>
                  <code class="mono-truncate"><?= e($short($pageId, 10, 6)) ?></code>
                </div>
                <?php if ($canal === 'whatsapp' && $ident !== ''): ?>
                  <div class="account-row-id" title="<?= e($ident) ?>">
                    <span class="label-k">identificador</span>
                    <code class="mono-truncate"><?= e($short($ident, 10, 6)) ?></code>
                  </div>
                <?php endif; ?>
              </div>
              <div class="account-row-actions">
                <span class="badge <?= $estadoBadge($estado) ?>"><?= e(ucfirst($estado)) ?></span>
                <?php if (!empty($acc['tiene_token'])): ?>
                  <span class="badge badge-muted" title="Token almacenado">token</span>
                <?php endif; ?>
                <form method="POST" action="<?= url('configuracion/cuentas/eliminar') ?>" class="inline-form" onsubmit="return confirm('¿Desconectar este token Meta?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= $accId ?>">
                  <button type="submit" class="btn btn-ghost btn-sm">Desconectar</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="panel">
      <div class="panel-header"><h2>Agregar token Meta</h2></div>
      <div class="panel-body">
        <form method="POST" action="<?= url('configuracion/cuentas/guardar') ?>" class="settings-form" id="tokenMetaForm" autocomplete="off">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="0">
          <div class="form-group">
            <label class="form-label" for="canal">Canal</label>
            <select class="form-control" id="canal" name="canal" required>
              <?php foreach ($canales as $c): ?>
                <option value="<?= e($c) ?>"><?= e($canalLabels[$c] ?? $c) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="page_id">page_id (WABA ID)</label>
            <input class="form-control" type="text" id="page_id" name="page_id" required placeholder="Ej. 578881904471475">
            <p class="form-hint">WhatsApp Business Account ID (Meta → API Setup).</p>
          </div>
          <div class="form-group" id="group-identificador">
            <label class="form-label" for="identificador">identificador (Phone number ID)</label>
            <input class="form-control" type="text" id="identificador" name="identificador" placeholder="Ej. 585313634670751">
            <p class="form-hint">Phone number ID del número <strong>+57 312 5927598</strong>. El envío usa este ID + token.</p>
          </div>
          <div class="form-group">
            <label class="form-label" for="token">access token</label>
            <input class="form-control" type="password" id="token" name="token" required placeholder="Empieza con EAA… (token permanente)">
            <p class="form-hint">Debe ser token de la app Cortech con permiso sobre ese Phone number ID.</p>
          </div>
          <div class="form-group">
            <label class="form-label" for="estado_cuenta">Estado</label>
            <select class="form-control" id="estado_cuenta" name="estado">
              <option value="conectado">Conectado</option>
              <option value="pendiente">Pendiente</option>
              <option value="desconectado">Desconectado</option>
              <option value="error">Error</option>
            </select>
          </div>
          <p class="form-hint" id="tokenMetaHint">WhatsApp Cortech: WABA 578881904471475 · Phone number ID 585313634670751 · token EAA… de ESA app</p>
          <button type="submit" class="btn btn-primary">Guardar en token_meta</button>
        </form>
      </div>
    </div>
  </div>

  <div class="panel meta-webhook-panel">
    <div class="panel-header">
      <h2>Webhook Meta Business</h2>
      <span class="badge badge-success">Callback URL</span>
    </div>
    <div class="panel-body">
      <p class="form-hint" style="margin-bottom:14px">
        Pega esta URL en <strong>Meta Developers → tu App → Webhooks → Callback URL</strong>
        (WhatsApp / Messenger / Instagram). Meta enviará aquí los mensajes entrantes.
      </p>

      <div class="form-group">
        <label class="form-label">Callback URL</label>
        <div class="copy-field">
          <input class="form-control" type="text" id="metaWebhookUrl" value="<?= e($metaWebhookUrl ?? '') ?>" readonly>
          <button type="button" class="btn btn-ghost btn-sm" data-copy="#metaWebhookUrl">Copiar</button>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Verify token</label>
        <div class="copy-field">
          <input class="form-control" type="text" id="metaVerifyToken" value="<?= e($metaVerifyToken ?? '') ?>" readonly>
          <button type="button" class="btn btn-ghost btn-sm" data-copy="#metaVerifyToken">Copiar</button>
        </div>
        <p class="form-hint">Debe coincidir exactamente con el “Verify token” que configures en Meta.</p>
      </div>

      <div class="meta-webhook-actions">
        <form method="POST" action="<?= url('configuracion/meta/verify-rotar') ?>" onsubmit="return confirm('¿Regenerar verify token? Tendrás que actualizarlo en Meta Developers.');">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-ghost btn-sm">Regenerar verify token</button>
        </form>
        <span class="badge badge-muted"><?= (int) ($metaInboxCount ?? 0) ?> eventos recibidos</span>
      </div>

      <div class="meta-webhook-fields">
        <p class="form-hint"><strong>Campos a suscribir en Meta:</strong></p>
        <ul class="meta-subscribe-list">
          <li><strong>WhatsApp:</strong> messages</li>
          <li><strong>Messenger (Page):</strong> messages, messaging_postbacks, message_deliveries</li>
          <li><strong>Instagram:</strong> messages</li>
        </ul>
      </div>

      <?php if (!empty($metaInbox)): ?>
        <div class="meta-inbox-preview">
          <p class="form-hint" style="margin-bottom:8px"><strong>Últimos eventos</strong> (tabla meta_webhook_inbox)</p>
          <?php foreach ($metaInbox as $ev): ?>
            <div class="account-row">
              <div>
                <div class="account-row-title"><?= e((string) ($ev['canal'] ?? $ev['object_type'] ?? 'evento')) ?></div>
                <div class="account-row-meta">
                  <?= e((string) ($ev['event_type'] ?? '')) ?>
                  <?php if (!empty($ev['page_id'])): ?> · page_id <?= e((string) $ev['page_id']) ?><?php endif; ?>
                </div>
              </div>
              <span class="account-row-id"><?= e((string) ($ev['created_at'] ?? '')) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="settings-pane" id="pane-redes" <?= $tab !== 'redes' ? 'hidden' : '' ?>>
  <div class="grid grid-2 settings-grid">
    <div class="panel">
      <div class="panel-header">
        <h2>Cuentas conectadas</h2>
        <span class="badge badge-<?= !empty($redesTableOk) ? 'success' : 'warning' ?>">
          <?= !empty($redesTableOk) ? 'token_redes' : 'tabla pendiente' ?>
        </span>
      </div>
      <div class="panel-body">
        <?php if (empty($otrasRedes)): ?>
          <p class="settings-empty">No hay otras redes. Agrega LinkedIn, TikTok, X, YouTube, etc. a la derecha.</p>
        <?php else: ?>
          <?php
            $shortRed = static function (?string $value, int $head = 10, int $tail = 6): string {
                $value = trim((string) $value);
                if ($value === '') {
                    return '—';
                }
                $len = mb_strlen($value);
                if ($len <= ($head + $tail + 1)) {
                    return $value;
                }
                return mb_substr($value, 0, $head) . '…' . mb_substr($value, -$tail);
            };
          ?>
          <?php foreach ($otrasRedes as $redRow): ?>
            <?php
              $estado = (string) ($redRow['estado'] ?? 'desconectado');
              $redId = (int) ($redRow['id'] ?? 0);
              $redCode = (string) ($redRow['red'] ?? '');
              $redLabel = $redesOptions[$redCode] ?? ucfirst($redCode);
              $identRed = (string) ($redRow['identificador'] ?? '');
            ?>
            <div class="account-row account-row--token">
              <div class="account-row-main">
                <div class="account-row-title"><?= e($redLabel) ?></div>
                <div class="account-row-meta"><?= e((string) ($redRow['nombre_cuenta'] ?? '')) ?></div>
                <?php if ($identRed !== ''): ?>
                  <div class="account-row-id" title="<?= e($identRed) ?>">
                    <span class="label-k">id</span>
                    <code class="mono-truncate"><?= e($shortRed($identRed)) ?></code>
                  </div>
                <?php endif; ?>
              </div>
              <div class="account-row-actions">
                <span class="badge <?= $estadoBadge($estado) ?>"><?= e(ucfirst($estado)) ?></span>
                <?php if (!empty($redRow['tiene_token'])): ?>
                  <span class="badge badge-muted">token</span>
                <?php endif; ?>
                <?php if (!empty($redRow['tiene_client'])): ?>
                  <span class="badge badge-muted">client</span>
                <?php endif; ?>
                <form method="POST" action="<?= url('configuracion/redes/eliminar') ?>" class="inline-form" onsubmit="return confirm('¿Desconectar esta cuenta?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= $redId ?>">
                  <button type="submit" class="btn btn-ghost btn-sm">Desconectar</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="panel">
      <div class="panel-header"><h2>Agregar otra red</h2></div>
      <div class="panel-body">
        <form method="POST" action="<?= url('configuracion/redes/guardar') ?>" class="settings-form" id="tokenRedesForm" autocomplete="off">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="0">
          <div class="form-group">
            <label class="form-label" for="red">Red</label>
            <select class="form-control" id="red" name="red" required>
              <?php foreach ($redesOptions as $code => $label): ?>
                <option value="<?= e($code) ?>"><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="nombre_cuenta_red">Nombre de cuenta</label>
            <input class="form-control" type="text" id="nombre_cuenta_red" name="nombre_cuenta" required placeholder="Ej. Arya LinkedIn">
          </div>
          <div class="form-group">
            <label class="form-label" for="identificador_red">Identificador</label>
            <input class="form-control" type="text" id="identificador_red" name="identificador" placeholder="@handle, org id, URL o channel id">
          </div>
          <div class="form-group" id="group-client-id">
            <label class="form-label" for="client_id">client_id</label>
            <input class="form-control" type="text" id="client_id" name="client_id" placeholder="Opcional (recomendado en LinkedIn)">
          </div>
          <div class="form-group" id="group-client-secret">
            <label class="form-label" for="client_secret">client_secret</label>
            <input class="form-control" type="password" id="client_secret" name="client_secret" placeholder="Opcional">
          </div>
          <div class="form-group">
            <label class="form-label" for="token_red">token</label>
            <input class="form-control" type="password" id="token_red" name="token" required placeholder="Access token">
          </div>
          <div class="form-group">
            <label class="form-label" for="estado_red">Estado</label>
            <select class="form-control" id="estado_red" name="estado">
              <option value="conectado">Conectado</option>
              <option value="pendiente">Pendiente</option>
              <option value="desconectado">Desconectado</option>
              <option value="error">Error</option>
            </select>
          </div>
          <p class="form-hint" id="tokenRedesHint">LinkedIn: nombre + identificador + token (+ client_id / secret si aplica)</p>
          <button type="submit" class="btn btn-primary">Guardar en token_redes</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="settings-pane" id="pane-integrations" <?= $tab !== 'integrations' ? 'hidden' : '' ?>>
  <div class="grid grid-2 settings-grid">
    <div class="panel">
      <div class="panel-header">
        <h2>Integraciones</h2>
        <span class="badge badge-<?= !empty($integrationsTableOk) ? 'success' : 'warning' ?>">
          <?= !empty($integrationsTableOk) ? 'maestro_integraciones' : 'tabla pendiente' ?>
        </span>
      </div>
      <div class="panel-body">
        <div class="settings-live-strip">
          <div class="info-item">
            <label>PostgreSQL</label>
            <span class="badge badge-<?= !empty($db['ok']) ? 'success' : 'danger' ?>">
              <?= !empty($db['ok']) ? 'DB conectada' : 'sin conexión' ?>
            </span>
          </div>
          <div class="info-item">
            <label>maestrousuario</label>
            <span class="badge badge-<?= !empty($tableExists) ? 'success' : 'warning' ?>">
              <?= !empty($tableExists) ? 'OK' : 'pendiente' ?>
            </span>
          </div>
          <div class="info-item">
            <label>Supabase API</label>
            <?php if ($api === null): ?>
              <span class="badge badge-muted">sin configurar</span>
            <?php else: ?>
              <span class="badge badge-<?= !empty($api['ok']) ? 'success' : 'danger' ?>">
                <?= !empty($api['ok']) ? 'OK' : 'error' ?>
              </span>
            <?php endif; ?>
          </div>
          <a href="<?= url('diagnostico') ?>" class="btn btn-ghost btn-sm">Diagnóstico</a>
        </div>

        <?php if (empty($integrations)): ?>
          <p class="settings-empty">No hay integraciones. Se crearán al conectar la DB.</p>
        <?php else: ?>
          <?php foreach ($integrations as $integ): ?>
            <?php
              $estado = (string) ($integ['estado'] ?? 'inactivo');
              $integId = (int) ($integ['id'] ?? 0);
            ?>
            <details class="integration-card">
              <summary>
                <span>
                  <strong><?= e((string) ($integ['nombre'] ?? '')) ?></strong>
                  <span class="account-row-meta"><?= e((string) ($integ['codigo'] ?? '')) ?> · <?= e((string) ($integ['tipo'] ?? '')) ?></span>
                </span>
                <span class="badge <?= $estadoBadge($estado) ?>"><?= e(ucfirst($estado)) ?></span>
              </summary>
              <form method="POST" action="<?= url('configuracion/integraciones/guardar') ?>" class="settings-form" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $integId ?>">
                <div class="form-group">
                  <label class="form-label">Nombre</label>
                  <input class="form-control" type="text" name="nombre" value="<?= e((string) ($integ['nombre'] ?? '')) ?>" required>
                </div>
                <div class="form-group">
                  <label class="form-label">Tipo</label>
                  <select class="form-control" name="tipo">
                    <?php foreach (['db', 'api', 'webhook', 'oauth', 'servicio'] as $t): ?>
                      <option value="<?= $t ?>" <?= (($integ['tipo'] ?? '') === $t) ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Estado</label>
                  <select class="form-control" name="estado">
                    <?php foreach (['activo', 'inactivo', 'pendiente', 'error'] as $st): ?>
                      <option value="<?= $st ?>" <?= ($estado === $st) ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Endpoint / URL</label>
                  <input class="form-control" type="text" name="endpoint_url" value="<?= e((string) ($integ['endpoint_url'] ?? '')) ?>" placeholder="https://…">
                  <?php if (strtolower((string) ($integ['codigo'] ?? '')) === 'n8n'): ?>
                    <p class="form-hint" style="margin-top:6px">
                      Para <strong>Tareas programadas</strong> usa la <strong>URL base</strong> del host
                      (ej. <code>https://tu-n8n.host</code>), no el webhook
                      <code>/webhook/…</code>. También editable desde Tareas → Configurar.
                    </p>
                  <?php elseif (strtolower((string) ($integ['codigo'] ?? '')) === 'openai'): ?>
                    <p class="form-hint" style="margin-top:6px">
                      Endpoint Chat Completions:
                      <code>https://api.openai.com/v1/chat/completions</code>.
                      Usado por <strong>Chat GPT</strong> (GPT-5.6) y mejora de mensajes en Omnicanalidad.
                      También editable desde Chat GPT → Configurar.
                    </p>
                  <?php endif; ?>
                </div>
                <div class="form-group">
                  <label class="form-label">API key <?= !empty($integ['tiene_api_key']) ? '(ya guardada — deja vacío para no cambiar)' : '' ?></label>
                  <input class="form-control" type="password" name="api_key" placeholder="<?= !empty($integ['tiene_api_key']) ? '••••••••' : (strtolower((string) ($integ['codigo'] ?? '')) === 'openai' ? 'sk-…' : 'Opcional') ?>">
                </div>
                <div class="form-group">
                  <label class="form-label">Notas</label>
                  <textarea class="form-control" name="notas" rows="2"><?= e((string) ($integ['notas'] ?? '')) ?></textarea>
                </div>
                <div class="settings-form-actions">
                  <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
                </div>
              </form>
              <?php if (!in_array((string) ($integ['codigo'] ?? ''), ['postgresql', 'supabase', 'n8n', 'openai'], true)): ?>
                <form method="POST" action="<?= url('configuracion/integraciones/eliminar') ?>" class="inline-form" onsubmit="return confirm('¿Desactivar esta integración?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= $integId ?>">
                  <button type="submit" class="btn btn-ghost btn-sm">Desactivar</button>
                </form>
              <?php endif; ?>
            </details>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="panel">
      <div class="panel-header"><h2>Nueva integración</h2></div>
      <div class="panel-body">
        <form method="POST" action="<?= url('configuracion/integraciones/guardar') ?>" class="settings-form" autocomplete="off">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="0">
          <div class="form-group">
            <label class="form-label" for="codigo_new">Código</label>
            <input class="form-control" type="text" id="codigo_new" name="codigo" required placeholder="ej. openai" pattern="[a-z0-9_\-]+">
          </div>
          <div class="form-group">
            <label class="form-label" for="nombre_new">Nombre</label>
            <input class="form-control" type="text" id="nombre_new" name="nombre" required placeholder="Ej. OpenAI">
          </div>
          <div class="form-group">
            <label class="form-label" for="tipo_new">Tipo</label>
            <select class="form-control" id="tipo_new" name="tipo">
              <option value="api">api</option>
              <option value="webhook">webhook</option>
              <option value="oauth">oauth</option>
              <option value="db">db</option>
              <option value="servicio">servicio</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="estado_new">Estado</label>
            <select class="form-control" id="estado_new" name="estado">
              <option value="activo">Activo</option>
              <option value="inactivo" selected>Inactivo</option>
              <option value="pendiente">Pendiente</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="url_new">Endpoint / URL</label>
            <input class="form-control" type="text" id="url_new" name="endpoint_url" placeholder="https://…">
          </div>
          <div class="form-group">
            <label class="form-label" for="key_new">API key</label>
            <input class="form-control" type="password" id="key_new" name="api_key" placeholder="Opcional">
          </div>
          <div class="form-group">
            <label class="form-label" for="notas_new">Notas</label>
            <textarea class="form-control" id="notas_new" name="notas" rows="2"></textarea>
          </div>
          <button type="submit" class="btn btn-primary">Crear en base de datos</button>
        </form>
      </div>
    </div>
  </div>
</div>
