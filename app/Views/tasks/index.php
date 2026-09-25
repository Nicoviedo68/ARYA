<?php
/** @var list<array<string,mixed>> $workflows */
/** @var array{total:int,active:int,inactive:int} $counts */
/** @var bool $n8nOk */
/** @var string $n8nError */
/** @var string $n8nBase */
/** @var array{id:int,endpoint_url:string,base_url:string,estado:string,tiene_api_key:bool,ready:bool} $n8nConfig */
/** @var bool $showConfig */
/** @var bool $isAdmin */

$workflows = $workflows ?? [];
$counts = $counts ?? ['total' => 0, 'active' => 0, 'inactive' => 0];
$n8nOk = !empty($n8nOk);
$n8nError = (string) ($n8nError ?? '');
$n8nBase = (string) ($n8nBase ?? '');
$n8nConfig = $n8nConfig ?? [
    'id' => 0,
    'endpoint_url' => '',
    'base_url' => '',
    'estado' => 'inactivo',
    'tiene_api_key' => false,
    'ready' => false,
];
$showConfig = !empty($showConfig);
$isAdmin = !empty($isAdmin);
$endpointValue = (string) ($n8nConfig['base_url'] !== '' ? $n8nConfig['base_url'] : $n8nConfig['endpoint_url']);
$estadoActual = strtolower((string) ($n8nConfig['estado'] ?? 'inactivo'));
$estadoActivo = $estadoActual === 'activo';
$editorBase = rtrim($n8nBase !== '' ? $n8nBase : (string) ($n8nConfig['base_url'] ?? ''), '/');
$n8nLogoUrl = asset('img/n8n.svg') . '?v=brand1';
$csrf = csrf_token();
?>

<div
  id="tasksLiveRoot"
  data-api-url="<?= e(url('tareas/api')) ?>"
  data-refresh-url="<?= e(url('tareas/refresh')) ?>"
  data-toggle-base="<?= e(url('tareas')) ?>"
  data-csrf="<?= e($csrf) ?>"
  data-is-admin="<?= $isAdmin ? '1' : '0' ?>"
  data-n8n-ok="<?= $n8nOk ? '1' : '0' ?>"
  data-editor-base="<?= e($editorBase) ?>"
  data-logo-url="<?= e($n8nLogoUrl) ?>"
  data-poll-ms="12000"
>
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <p style="color:var(--text-secondary);font-size:0.95rem;margin:0;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <code id="tasksN8nUrl" style="font-size:0.85rem"><?= $n8nBase !== '' ? e($n8nBase) : 'Sin URL configurada' ?></code>
      <span id="tasksN8nEstado" class="badge <?= $estadoActivo ? 'badge-success' : 'badge-muted' ?>">
        <span class="task-status-dot dot-<?= $estadoActivo ? 'activo' : 'pausado' ?>"></span>
        <span data-estado-text><?= $estadoActivo ? 'Activo' : 'Inactivo' ?></span>
      </span>
      <span id="tasksLiveHint" class="form-hint" style="margin:0">Auto-actualiza</span>
    </p>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <button type="button" class="btn btn-sm tasks-refresh-btn" id="tasksRefreshBtn" title="Recargar workflows desde n8n ahora">
        <svg class="tasks-refresh-ico" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M21 12a9 9 0 1 1-2.64-6.36"/>
          <polyline points="21 3 21 9 15 9"/>
        </svg>
        <span>Recargar</span>
      </button>
      <button type="button" class="btn btn-ghost btn-sm" id="n8nConfigOpenBtn" data-n8n-config-open>
        Configurar
      </button>
    </div>
  </div>

  <div id="tasksErrorPanel" class="panel" style="margin-bottom:20px;border-color:rgba(255,107,107,0.35);<?= $n8nOk ? 'display:none' : '' ?>">
    <div class="panel-body">
      <p id="tasksErrorText" style="margin:0;color:var(--danger)"><?= e($n8nError) ?></p>
      <p class="form-hint" style="margin:8px 0 0">
        Abre <strong>Configurar</strong> y guarda la URL base + API key de n8n
        (Settings → API en n8n). El login de n8n no sirve como API key.
      </p>
    </div>
  </div>

  <div id="tasksToast" class="tasks-toast" hidden></div>

  <div class="grid grid-3 stagger" style="margin-bottom:24px">
    <div class="panel kpi-card">
      <div class="kpi-label">Activos</div>
      <div class="kpi-value" id="kpiActive"><?= (int) ($counts['active'] ?? 0) ?></div>
    </div>
    <div class="panel kpi-card">
      <div class="kpi-label">Inactivos</div>
      <div class="kpi-value" id="kpiInactive"><?= (int) ($counts['inactive'] ?? 0) ?></div>
    </div>
    <div class="panel kpi-card">
      <div class="kpi-label">Total</div>
      <div class="kpi-value" id="kpiTotal"><?= (int) ($counts['total'] ?? 0) ?></div>
    </div>
  </div>

  <div class="panel">
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Workflow</th>
            <th>Tipo de entrada</th>
            <th>Actualizado</th>
            <th>ID</th>
            <th>Estado</th>
            <th>Acción</th>
          </tr>
        </thead>
        <tbody id="tasksWorkflowBody">
          <!-- render inicial vía JS para un solo motor de UI -->
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="profile-modal n8n-config-modal" id="n8nConfigModal" <?= $showConfig ? '' : 'hidden' ?>>
  <div class="profile-modal-backdrop" data-n8n-config-close></div>
  <div class="profile-modal-panel n8n-config-panel" role="dialog" aria-modal="true" aria-labelledby="n8nConfigModalTitle">
    <header class="profile-modal-header">
      <div>
        <p class="profile-modal-eyebrow">Integración n8n</p>
        <h2 id="n8nConfigModalTitle">Configurar conexión</h2>
        <p class="n8n-config-lead">URL base + API key para listar y activar workflows desde Arya.</p>
      </div>
      <button type="button" class="btn btn-ghost btn-sm" data-n8n-config-close aria-label="Cerrar">✕</button>
    </header>

    <div class="n8n-config-status-row">
      <span class="badge <?= !empty($n8nConfig['ready']) ? 'badge-success' : 'badge-warning' ?>">
        <?= !empty($n8nConfig['ready']) ? 'Lista' : 'Pendiente' ?>
      </span>
      <span class="badge <?= $estadoActivo ? 'badge-success' : 'badge-muted' ?>">
        <?= $estadoActivo ? 'Activo' : 'Inactivo' ?>
      </span>
      <?php if (!empty($n8nConfig['tiene_api_key'])): ?>
        <span class="badge badge-cyan">API key guardada</span>
      <?php endif; ?>
    </div>

    <form method="POST" action="<?= url('tareas/config') ?>" class="profile-modal-form" autocomplete="off">
      <?= csrf_field() ?>

      <div class="form-group">
        <label class="form-label" for="n8n_endpoint">URL base de n8n <span style="color:var(--danger)">*</span></label>
        <input
          class="form-control"
          type="url"
          id="n8n_endpoint"
          name="endpoint_url"
          required
          inputmode="url"
          placeholder="https://tu-n8n.host"
          value="<?= e($endpointValue) ?>"
        >
        <p class="form-hint" style="margin-top:6px">
          Solo el host. No uses <code>/webhook/…</code>.
        </p>
      </div>

      <div class="form-group">
        <label class="form-label" for="n8n_api_key">
          API key de n8n
          <?php if (!empty($n8nConfig['tiene_api_key'])): ?>
            <span style="color:var(--text-secondary);font-weight:400">(deja vacío para no cambiar)</span>
          <?php else: ?>
            <span style="color:var(--danger)">*</span>
          <?php endif; ?>
        </label>
        <input
          class="form-control"
          type="password"
          id="n8n_api_key"
          name="api_key"
          autocomplete="new-password"
          <?= empty($n8nConfig['tiene_api_key']) ? 'required' : '' ?>
          placeholder="<?= !empty($n8nConfig['tiene_api_key']) ? '••••••••••••' : 'eyJhbGciOi…' ?>"
        >
        <p class="form-hint" style="margin-top:6px">
          En n8n: Settings → API → Create API key
          (<code>workflow:list</code>, <code>activate</code>, <code>deactivate</code>).
        </p>
      </div>

      <div class="form-group">
        <label class="form-label" for="n8n_estado">Estado</label>
        <select class="form-control" id="n8n_estado" name="estado">
          <?php foreach (['activo', 'inactivo'] as $st): ?>
            <option value="<?= $st ?>" <?= $estadoActual === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="profile-modal-actions">
        <button type="button" class="btn btn-ghost" data-n8n-config-close>Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar y cargar</button>
      </div>
    </form>
  </div>
</div>

<style>
  .n8n-config-panel {
    width: min(480px, 100%);
    border-top: 3px solid var(--cyan);
  }
  .n8n-config-lead {
    margin: 6px 0 0;
    color: var(--text-secondary);
    font-size: 0.9rem;
    line-height: 1.4;
    max-width: 34ch;
  }
  .n8n-config-status-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 0 0 16px;
  }
  .tasks-n8n-id-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    font-size: 0.78rem;
    min-width: 96px;
    border-color: rgba(234, 75, 113, 0.35);
  }
  .tasks-n8n-id-btn:hover:not(.is-locked) {
    border-color: rgba(234, 75, 113, 0.7);
    background: rgba(234, 75, 113, 0.1);
  }
  .tasks-n8n-id-btn .n8n-logo {
    flex-shrink: 0;
    display: block;
    width: 20px;
    height: 14px;
    object-fit: contain;
  }
  .tasks-n8n-id-btn.is-locked {
    opacity: 0.5;
    cursor: not-allowed;
    filter: grayscale(0.35);
  }
  .tasks-refresh-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: 1px solid rgba(0, 212, 232, 0.45);
    background: linear-gradient(135deg, rgba(0, 212, 232, 0.18), rgba(0, 212, 232, 0.06));
    color: var(--cyan, #00d4e8);
    font-weight: 600;
    letter-spacing: 0.01em;
    box-shadow: 0 0 0 1px rgba(0, 212, 232, 0.08), 0 6px 16px rgba(0, 0, 0, 0.18);
    transition: transform 0.15s ease, border-color 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
  }
  .tasks-refresh-btn:hover {
    border-color: rgba(0, 212, 232, 0.8);
    background: linear-gradient(135deg, rgba(0, 212, 232, 0.28), rgba(0, 212, 232, 0.1));
    transform: translateY(-1px);
    box-shadow: 0 0 0 1px rgba(0, 212, 232, 0.16), 0 10px 22px rgba(0, 0, 0, 0.22);
  }
  .tasks-refresh-btn:active { transform: translateY(0); }
  .tasks-refresh-btn.is-loading { opacity: 0.7; pointer-events: none; }
  .tasks-refresh-btn.is-loading .tasks-refresh-ico {
    animation: tasksRefreshSpin 0.7s linear infinite;
  }
  .tasks-refresh-btn:hover .tasks-refresh-ico {
    animation: tasksRefreshSpin 0.7s ease;
  }
  @keyframes tasksRefreshSpin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
  }
  .tasks-toast {
    position: sticky;
    top: 12px;
    z-index: 40;
    margin-bottom: 14px;
    padding: 10px 14px;
    border-radius: 10px;
    font-size: 0.9rem;
    border: 1px solid transparent;
  }
  .tasks-toast.is-ok {
    background: rgba(46, 204, 113, 0.12);
    border-color: rgba(46, 204, 113, 0.35);
    color: #7dffa8;
  }
  .tasks-toast.is-err {
    background: rgba(255, 107, 107, 0.12);
    border-color: rgba(255, 107, 107, 0.35);
    color: #ff9b9b;
  }
  html[data-theme="light"] .tasks-toast.is-ok { color: #157347; }
  html[data-theme="light"] .tasks-toast.is-err { color: #b42318; }
</style>

<script>
(function () {
  var root = document.getElementById('tasksLiveRoot');
  if (!root) return;

  var apiUrl = root.dataset.apiUrl;
  var refreshUrl = root.dataset.refreshUrl;
  var toggleBase = root.dataset.toggleBase.replace(/\/$/, '');
  var csrf = root.dataset.csrf;
  var isAdmin = root.dataset.isAdmin === '1';
  var n8nOk = root.dataset.n8nOk === '1';
  var editorBase = (root.dataset.editorBase || '').replace(/\/$/, '');
  var logoUrl = root.dataset.logoUrl || '';
  var pollMs = parseInt(root.dataset.pollMs || '12000', 10) || 12000;

  var bodyEl = document.getElementById('tasksWorkflowBody');
  var toastEl = document.getElementById('tasksToast');
  var refreshBtn = document.getElementById('tasksRefreshBtn');
  var busy = false;
  var pollTimer = null;

  var initialWorkflows = <?= json_encode($workflows, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var initialCounts = <?= json_encode($counts, JSON_UNESCAPED_UNICODE) ?>;

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function showToast(msg, ok) {
    if (!toastEl) return;
    toastEl.hidden = false;
    toastEl.textContent = msg;
    toastEl.className = 'tasks-toast ' + (ok ? 'is-ok' : 'is-err');
    clearTimeout(showToast._t);
    showToast._t = setTimeout(function () { toastEl.hidden = true; }, 4200);
  }

  function triggerBadge(tr) {
    var cls = 'badge-warning';
    if (tr === 'Webhook') cls = 'badge-cyan';
    else if (tr === 'Schedule Trigger' || tr === 'Cron') cls = 'badge-gold';
    else if (tr === 'Manual') cls = 'badge-muted';
    return '<span class="badge ' + cls + '" style="margin:0 4px 4px 0">' + esc(tr) + '</span>';
  }

  function idCell(wf) {
    var id = String(wf.id || '');
    if (!id) return '<span style="color:var(--text-secondary)">—</span>';
    var logo = logoUrl
      ? '<img class="n8n-logo" src="' + esc(logoUrl) + '" width="20" height="14" alt="n8n" aria-hidden="true">'
      : '';
    if (isAdmin && editorBase) {
      return '<a class="btn btn-ghost btn-sm tasks-n8n-id-btn" href="' + esc(editorBase + '/workflow/' + encodeURIComponent(id)) + '" target="_blank" rel="noopener noreferrer" title="Abrir workflow en n8n">' + logo + '<span>Abrir</span></a>';
    }
    return '<button type="button" class="btn btn-ghost btn-sm tasks-n8n-id-btn is-locked" disabled title="Solo usuarios ADMIN pueden abrir el workflow en n8n">' + logo + '<span>Bloqueado</span></button>';
  }

  function actionCell(wf) {
    var id = String(wf.id || '');
    if (!id || !n8nOk) return '<span style="color:var(--text-secondary)">—</span>';
    var active = !!wf.active;
    var archived = !!wf.archived;
    var action = active ? 'deactivate' : 'activate';
    var label = active ? 'Desactivar' : 'Activar';
    var cls = active ? 'btn btn-sm btn-ghost' : 'btn btn-sm btn-primary';
    return '<button type="button" class="' + cls + '" data-toggle-wf="' + esc(id) + '" data-action="' + action + '"' +
      (archived ? ' disabled title="Workflow archivado"' : '') + '>' + label + '</button>';
  }

  function renderRows(workflows) {
    if (!bodyEl) return;
    if (!workflows || !workflows.length) {
      bodyEl.innerHTML = '<tr><td colspan="6" style="color:var(--text-secondary);text-align:center;padding:28px 16px">' +
        (n8nOk ? 'No hay workflows en esta instancia de n8n.' : 'No se pudieron cargar workflows. Usa Configurar para completar n8n.') +
        '</td></tr>';
      return;
    }

    bodyEl.innerHTML = workflows.map(function (wf) {
      var active = !!wf.active;
      var archived = !!wf.archived;
      var triggers = Array.isArray(wf.triggers) ? wf.triggers : [];
      var triggersHtml = triggers.length
        ? triggers.map(triggerBadge).join('')
        : '<span style="color:var(--text-secondary)">' + esc(wf.trigger_label || '—') + '</span>';

      return '<tr data-wf-id="' + esc(wf.id || '') + '">' +
        '<td style="font-weight:600">' + esc(wf.name || '') +
          (archived ? ' <span class="badge badge-muted" style="margin-left:6px">Archivado</span>' : '') +
        '</td>' +
        '<td>' + triggersHtml + '</td>' +
        '<td>' + esc(wf.updated_at || '—') + '</td>' +
        '<td>' + idCell(wf) + '</td>' +
        '<td><span class="badge ' + (active ? 'badge-success' : 'badge-muted') + '">' +
          '<span class="task-status-dot dot-' + (active ? 'activo' : 'pausado') + '"></span> ' +
          (active ? 'Activo' : 'Inactivo') +
        '</span></td>' +
        '<td>' + actionCell(wf) + '</td>' +
      '</tr>';
    }).join('');
  }

  function applyCounts(counts) {
    counts = counts || {};
    var a = document.getElementById('kpiActive');
    var i = document.getElementById('kpiInactive');
    var t = document.getElementById('kpiTotal');
    if (a) a.textContent = String(counts.active || 0);
    if (i) i.textContent = String(counts.inactive || 0);
    if (t) t.textContent = String(counts.total || 0);
  }

  function applyMeta(data) {
    if (data.n8nBase) {
      var urlEl = document.getElementById('tasksN8nUrl');
      if (urlEl) urlEl.textContent = data.n8nBase;
      editorBase = String(data.n8nBase).replace(/\/$/, '');
      root.dataset.editorBase = editorBase;
    }
    if (typeof data.isAdmin === 'boolean') {
      isAdmin = data.isAdmin;
      root.dataset.isAdmin = isAdmin ? '1' : '0';
    }
    if (data.estado) {
      var activo = String(data.estado).toLowerCase() === 'activo';
      var badge = document.getElementById('tasksN8nEstado');
      if (badge) {
        badge.className = 'badge ' + (activo ? 'badge-success' : 'badge-muted');
        badge.innerHTML = '<span class="task-status-dot dot-' + (activo ? 'activo' : 'pausado') + '"></span> ' +
          '<span data-estado-text>' + (activo ? 'Activo' : 'Inactivo') + '</span>';
      }
    }
    n8nOk = !!data.ok;
    root.dataset.n8nOk = n8nOk ? '1' : '0';
    var errPanel = document.getElementById('tasksErrorPanel');
    var errText = document.getElementById('tasksErrorText');
    if (errPanel) errPanel.style.display = n8nOk ? 'none' : '';
    if (errText && !n8nOk && data.message) errText.textContent = data.message;
  }

  function applyPayload(data) {
    applyMeta(data);
    applyCounts(data.counts);
    renderRows(data.workflows || []);
    var hint = document.getElementById('tasksLiveHint');
    if (hint) {
      var d = new Date();
      hint.textContent = 'Actualizado ' + d.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }
  }

  async function fetchJson(url, options) {
    options = options || {};
    options.headers = Object.assign({
      'Accept': 'application/json',
      'X-Requested-With': 'fetch',
      'X-CSRF-TOKEN': csrf
    }, options.headers || {});
    var res = await fetch(url, options);
    var data = null;
    try { data = await res.json(); } catch (e) { data = null; }
    if (!data) throw new Error('Respuesta inválida del servidor');
    return { res: res, data: data };
  }

  async function poll(fresh) {
    if (busy) return;
    try {
      var url = apiUrl + (apiUrl.indexOf('?') >= 0 ? '&' : '?') + 'fresh=' + (fresh ? '1' : '0');
      var out = await fetchJson(url, { method: 'GET', credentials: 'same-origin' });
      applyPayload(out.data);
    } catch (e) {
      // silencioso en poll; el toast solo en acciones manuales
    }
  }

  async function doRefresh() {
    if (busy) return;
    busy = true;
    if (refreshBtn) refreshBtn.classList.add('is-loading');
    try {
      var fd = new FormData();
      fd.append('_csrf', csrf);
      var out = await fetchJson(refreshUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
      applyPayload(out.data);
      showToast(out.data.message || (out.data.ok ? 'Recargado' : 'Error al recargar'), !!out.data.ok);
    } catch (e) {
      showToast(e.message || 'No se pudo recargar', false);
    } finally {
      busy = false;
      if (refreshBtn) refreshBtn.classList.remove('is-loading');
    }
  }

  async function doToggle(id, action, btn) {
    if (busy) return;
    var label = action === 'activate' ? 'Activar' : 'Desactivar';
    if (!confirm('¿' + label + ' este workflow en n8n?')) return;

    busy = true;
    if (btn) {
      btn.disabled = true;
      btn.textContent = action === 'activate' ? 'Activando…' : 'Desactivando…';
    }
    try {
      var fd = new FormData();
      fd.append('_csrf', csrf);
      fd.append('action', action);
      var url = toggleBase + '/' + encodeURIComponent(id) + '/toggle';
      var out = await fetchJson(url, { method: 'POST', body: fd, credentials: 'same-origin' });
      if (out.data.workflows) {
        applyPayload(out.data);
      } else {
        await poll(true);
      }
      showToast(out.data.message || (out.data.ok ? 'OK' : 'Error'), !!out.data.ok);
    } catch (e) {
      showToast(e.message || 'No se pudo actualizar el workflow', false);
      await poll(true);
    } finally {
      busy = false;
    }
  }

  if (bodyEl) {
    bodyEl.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-toggle-wf]');
      if (!btn) return;
      doToggle(btn.getAttribute('data-toggle-wf'), btn.getAttribute('data-action'), btn);
    });
  }
  if (refreshBtn) refreshBtn.addEventListener('click', doRefresh);

  applyPayload({
    ok: n8nOk,
    workflows: initialWorkflows,
    counts: initialCounts,
    n8nBase: editorBase,
    estado: <?= json_encode($estadoActual) ?>,
    message: <?= json_encode($n8nError, JSON_UNESCAPED_UNICODE) ?>
  });

  pollTimer = setInterval(function () { poll(true); }, pollMs);
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) poll(true);
  });

  // Modal config
  var modal = document.getElementById('n8nConfigModal');
  if (modal) {
    function openModal() {
      modal.hidden = false;
      document.body.style.overflow = 'hidden';
      var first = modal.querySelector('#n8n_endpoint');
      if (first) setTimeout(function () { first.focus(); }, 40);
    }
    function closeModal() {
      modal.hidden = true;
      document.body.style.overflow = '';
    }
    document.querySelectorAll('[data-n8n-config-open]').forEach(function (el) {
      el.addEventListener('click', openModal);
    });
    document.querySelectorAll('[data-n8n-config-close]').forEach(function (el) {
      el.addEventListener('click', closeModal);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !modal.hidden) closeModal();
    });
    <?php if ($showConfig): ?>
    openModal();
    <?php endif; ?>
  }
})();
</script>
