<?php
/** @var array $clients */
/** @var array $filters */

$filters = $filters ?? ['name' => '', 'phone' => '', 'channel' => '', 'q' => ''];
$openNew = (string) ($_GET['nuevo'] ?? '') === '1';

$channelIcon = static function (string $id): string {
    return match ($id) {
        'whatsapp' => '<svg class="ch-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="#25D366" d="M12.04 2C6.58 2 2.15 6.4 2.15 11.83c0 1.99.57 3.84 1.56 5.43L2 22l4.9-1.61a10 10 0 0 0 5.14 1.42h.01c5.46 0 9.89-4.4 9.89-9.83C21.94 6.4 17.5 2 12.04 2zm5.75 13.99c-.24.68-1.4 1.25-1.93 1.33-.49.07-1.12.1-1.81-.11-.42-.13-.95-.31-1.64-.6-2.89-1.25-4.77-4.16-4.92-4.35-.14-.19-1.18-1.57-1.18-3 0-1.42.74-2.12 1-2.41.26-.29.57-.36.76-.36h.55c.17 0 .4-.06.63.48.24.55.81 1.9.88 2.04.07.14.12.31.02.5-.1.19-.14.31-.28.48-.14.17-.29.37-.42.5-.14.14-.28.29-.12.57.16.28.71 1.17 1.52 1.9 1.05.93 1.93 1.22 2.21 1.36.28.14.44.12.6-.07.16-.19.69-.8.88-1.08.19-.28.37-.23.63-.14.26.1 1.64.77 1.92.91.28.14.47.21.54.33.07.12.07.68-.17 1.36z"/></svg>',
        'messenger' => '<svg class="ch-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="#0084FF" d="M12 2C6.36 2 2 6.13 2 11.7c0 2.91 1.19 5.44 3.14 7.17V22l2.87-1.58c.9.25 1.85.38 2.99.38 5.64 0 10-4.13 10-9.7S17.64 2 12 2zm1.01 13.08-2.55-2.72-4.98 2.72 5.47-5.81 2.61 2.72 4.92-2.72-5.47 5.81z"/></svg>',
        'instagram' => '<svg class="ch-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="#E4405F" d="M7.8 2h8.4C19.4 2 22 4.6 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8C4.6 22 2 19.4 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2zm0 2A3.8 3.8 0 0 0 4 7.8v8.4A3.8 3.8 0 0 0 7.8 20h8.4a3.8 3.8 0 0 0 3.8-3.8V7.8A3.8 3.8 0 0 0 16.2 4H7.8zm9.65 1.5a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5zM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/></svg>',
        'web' => '<svg class="ch-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="#00D4E8" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm7.9 9h-3.17a15.4 15.4 0 0 0-1.3-5.3A8.03 8.03 0 0 1 19.9 11zM12 4c.9 0 2.3 1.8 3.05 5H8.95C9.7 5.8 11.1 4 12 4zM4.1 13h3.17c.2 1.9.7 3.7 1.3 5.3A8.03 8.03 0 0 1 4.1 13zm3.17-2H4.1a8.03 8.03 0 0 1 4.47-5.3A15.4 15.4 0 0 0 7.27 11zM12 20c-.9 0-2.3-1.8-3.05-5h6.1C14.3 18.2 12.9 20 12 20zm3.43-2.7c.6-1.6 1.1-3.4 1.3-5.3h3.17a8.03 8.03 0 0 1-4.47 5.3zM9.55 13c.2 1.7.6 3.3 1.2 4.6.4.8.8 1.4 1.25 1.4s.85-.6 1.25-1.4c.6-1.3 1-2.9 1.2-4.6H9.55zm0-2h4.9c-.2-1.7-.6-3.3-1.2-4.6C12.85 5.6 12.45 5 12 5s-.85.6-1.25 1.4c-.6 1.3-1 2.9-1.2 4.6z"/></svg>',
        default => '<svg class="ch-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M4 6h16v2H4V6zm0 5h16v2H4v-2zm0 5h10v2H4v-2z"/></svg>',
    };
};
?>

<div class="clients-toolbar">
  <form method="GET" action="<?= url('clientes') ?>" class="clients-filters">
    <div class="form-group" style="margin:0;min-width:160px;flex:1.2">
      <label class="form-label" for="fName">Nombre</label>
      <input class="form-control" id="fName" type="search" name="nombre" value="<?= e($filters['name'] ?? '') ?>" placeholder="Nombre del cliente">
    </div>
    <div class="form-group" style="margin:0;min-width:140px;flex:1">
      <label class="form-label" for="fPhone">Teléfono</label>
      <input class="form-control" id="fPhone" type="search" name="telefono" value="<?= e($filters['phone'] ?? '') ?>" placeholder="+57…">
    </div>
    <div class="form-group" style="margin:0;min-width:140px">
      <label class="form-label" for="fChannel">Canal</label>
      <select class="form-control" id="fChannel" name="canal">
        <?php
          $chSel = (string) ($filters['channel'] ?? '');
          $channels = [
            '' => 'Todos',
            'whatsapp' => 'WhatsApp',
            'messenger' => 'Messenger',
            'instagram' => 'Instagram',
            'web' => 'Web Chat',
          ];
          foreach ($channels as $val => $txt):
        ?>
          <option value="<?= e($val) ?>" <?= $chSel === $val ? 'selected' : '' ?>><?= e($txt) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="clients-filters-actions">
      <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
      <?php if (($filters['name'] ?? '') !== '' || ($filters['phone'] ?? '') !== '' || ($filters['channel'] ?? '') !== ''): ?>
        <a href="<?= url('clientes') ?>" class="btn btn-ghost btn-sm">Limpiar</a>
      <?php endif; ?>
    </div>
  </form>

  <div class="clients-toolbar-right">
    <span class="badge badge-cyan"><?= count($clients) ?> contacto<?= count($clients) === 1 ? '' : 's' ?></span>
    <button type="button" class="btn btn-gold" id="btnNewClient">+ Nuevo cliente</button>
  </div>
</div>

<div class="panel client-create-panel" id="newClientPanel" <?= $openNew ? '' : 'hidden' ?>>
  <div class="panel-header" style="display:flex;justify-content:space-between;align-items:center;gap:12px">
    <h3>Crear cliente</h3>
    <button type="button" class="btn btn-ghost btn-sm" id="btnCloseNewClient">Cerrar</button>
  </div>
  <div class="panel-body">
    <form method="POST" action="<?= url('clientes') ?>" class="client-create-form">
      <?= csrf_field() ?>
      <div class="grid grid-2" style="gap:12px">
        <div class="form-group" style="margin:0">
          <label class="form-label" for="nName">Nombre completo *</label>
          <input class="form-control" id="nName" name="name" required placeholder="Nombre y apellido" autocomplete="name">
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label" for="nPhone">Teléfono</label>
          <input class="form-control" id="nPhone" name="phone" placeholder="+57300…" autocomplete="tel">
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label" for="nEmail">Correo</label>
          <input class="form-control" id="nEmail" type="email" name="email" placeholder="correo@empresa.com" autocomplete="email">
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label" for="nCompany">Empresa</label>
          <input class="form-control" id="nCompany" name="company" placeholder="Empresa / razón social">
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label" for="nChannel">Canal</label>
          <select class="form-control" id="nChannel" name="channel">
            <option value="whatsapp">WhatsApp</option>
            <option value="messenger">Messenger</option>
            <option value="instagram">Instagram</option>
            <option value="web">Web Chat</option>
          </select>
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label" for="nStatus">Estado</label>
          <select class="form-control" id="nStatus" name="status">
            <option value="prospecto">Prospecto</option>
            <option value="activo">Activo</option>
            <option value="inactivo">Inactivo</option>
          </select>
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label" for="nCity">Ciudad</label>
          <input class="form-control" id="nCity" name="city" placeholder="Ciudad">
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label" for="nAddress">Dirección</label>
          <input class="form-control" id="nAddress" name="address" placeholder="Dirección">
        </div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px">
        <button type="button" class="btn btn-ghost btn-sm" id="btnCancelNewClient">Cancelar</button>
        <button type="submit" class="btn btn-primary">Crear cliente</button>
      </div>
    </form>
  </div>
</div>

<div class="panel">
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Cliente</th>
          <th>Origen</th>
          <th>Canal</th>
          <th>Último contacto</th>
          <th>Estado</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$clients): ?>
          <tr>
            <td colspan="6">
              <div class="empty-state">
                <p>No hay clientes con esos filtros.</p>
                <p style="font-size:0.85rem;color:var(--text-muted)">Crea uno con “+ Nuevo cliente” o espera un mensaje en Omnicanalidad.</p>
              </div>
            </td>
          </tr>
        <?php endif; ?>
        <?php foreach ($clients as $c): ?>
          <?php
            $ch = (string) ($c['channel'] ?? 'whatsapp');
            $label = (string) ($c['channel_label'] ?? ucfirst($ch));
            $sub = $c['email'] !== '' ? $c['email'] : ($c['phone'] !== '' ? $c['phone'] : ($c['external_contact'] ?? ''));
            $badge = match ($c['status']) {
              'activo' => 'success',
              'prospecto' => 'warning',
              default => 'muted',
            };
          ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:12px">
                <div class="client-avatar"><?= e(mb_strtoupper(mb_substr($c['name'], 0, 1))) ?></div>
                <div>
                  <div style="font-weight:600"><?= e($c['name']) ?></div>
                  <div style="font-size:0.78rem;color:var(--text-muted)"><?= e($sub) ?></div>
                </div>
              </div>
            </td>
            <td><?= e($c['company'] !== '' ? $c['company'] : ('Omnicanal · ' . $label)) ?></td>
            <td>
              <span class="ch-badge ch-badge-<?= e($ch) ?>">
                <?= $channelIcon($ch) ?>
                <span><?= e($label) ?></span>
              </span>
            </td>
            <td style="font-size:0.85rem;color:var(--text-secondary)"><?= e($c['last_contact'] ?? '—') ?></td>
            <td>
              <span class="badge badge-<?= $badge ?>"><?= e(ucfirst($c['status'])) ?></span>
            </td>
            <td class="client-actions">
              <?php if (!empty($c['omni_conversation_id'])): ?>
                <a href="<?= url('omnicanalidad?chat=' . (int) $c['omni_conversation_id'] . '&canal=' . urlencode($ch)) ?>" class="btn btn-client-chat btn-sm">
                  <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12a8.5 8.5 0 0 1-8.5 8.5H5l-2 2V12A8.5 8.5 0 1 1 21 12z"/></svg>
                  Chat
                </a>
              <?php endif; ?>
              <a href="<?= url('clientes/' . $c['id']) ?>" class="btn btn-client-view btn-sm">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                Ver cliente
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
(() => {
  const panel = document.getElementById('newClientPanel');
  const openBtn = document.getElementById('btnNewClient');
  const closeBtn = document.getElementById('btnCloseNewClient');
  const cancelBtn = document.getElementById('btnCancelNewClient');
  const open = () => { if (panel) panel.hidden = false; document.getElementById('nName')?.focus(); };
  const close = () => { if (panel) panel.hidden = true; };
  openBtn?.addEventListener('click', open);
  closeBtn?.addEventListener('click', close);
  cancelBtn?.addEventListener('click', close);
})();
</script>
