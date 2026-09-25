<?php
/** @var array $client */
/** @var array $orders */
/** @var array $activity */

$ch = (string) ($client['channel'] ?? 'whatsapp');
$label = (string) ($client['channel_label'] ?? ucfirst($ch));
$activity = is_array($activity ?? null) ? $activity : ['today' => 0, 'days7' => 0, 'days30' => 0, 'by_day' => []];
$maxDay = 1;
foreach (($activity['by_day'] ?? []) as $dayRow) {
    $maxDay = max($maxDay, (int) ($dayRow['count'] ?? 0));
}
?>

<div style="margin-bottom:12px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
  <a href="<?= url('clientes') ?>" class="btn btn-ghost btn-sm">← Volver a clientes</a>
  <?php if (!empty($client['omni_conversation_id'])): ?>
    <a class="btn btn-primary btn-sm" href="<?= url('omnicanalidad?chat=' . (int) $client['omni_conversation_id'] . '&canal=' . urlencode($ch)) ?>">
      Abrir chat
    </a>
  <?php endif; ?>
</div>

<div class="panel profile-hero" style="margin-bottom:16px">
  <div class="profile-avatar-lg"><?= e(mb_strtoupper(mb_substr($client['name'], 0, 1))) ?></div>
  <div class="profile-info">
    <h2><?= e($client['name']) ?></h2>
    <div style="color:var(--text-secondary)">
      <?= e(($client['company'] ?? '') !== '' ? $client['company'] : ('Omnicanal · ' . $label)) ?>
      <?php if (($client['email'] ?? '') !== ''): ?>
        · <?= e($client['email']) ?>
      <?php endif; ?>
    </div>
    <div class="tag-list">
      <?php foreach (($client['tags'] ?? []) as $tag): ?>
        <span class="badge badge-gold"><?= e($tag) ?></span>
      <?php endforeach; ?>
      <span class="ch-badge ch-badge-<?= e($ch) ?>"><span><?= e($label) ?></span></span>
      <span class="badge badge-<?= match ($client['status']) { 'activo' => 'success', 'prospecto' => 'warning', default => 'muted' } ?>">
        <?= e(ucfirst($client['status'])) ?>
      </span>
    </div>
  </div>
</div>

<form method="POST" action="<?= url('clientes/' . (int) $client['id']) ?>" class="client-edit-form">
  <?= csrf_field() ?>

  <div class="grid grid-2" style="margin-bottom:16px">
    <div class="panel">
      <div class="panel-header"><h3>Datos del cliente</h3></div>
      <div class="panel-body">
        <div class="grid grid-2" style="gap:12px">
          <div class="form-group" style="margin:0">
            <label class="form-label" for="cliName">Nombre completo *</label>
            <input class="form-control" id="cliName" name="name" required value="<?= e($client['name']) ?>" autocomplete="name">
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label" for="cliCompany">Empresa / razón social</label>
            <input class="form-control" id="cliCompany" name="company" value="<?= e($client['company'] ?? '') ?>" autocomplete="organization">
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label" for="cliEmail">Correo electrónico</label>
            <input class="form-control" id="cliEmail" type="email" name="email" value="<?= e($client['email'] ?? '') ?>" placeholder="correo@empresa.com" autocomplete="email">
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label" for="cliPhone">Teléfono</label>
            <input class="form-control" id="cliPhone" name="phone" value="<?= e($client['phone'] ?? '') ?>" placeholder="+57…" autocomplete="tel">
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label" for="cliDocType">Tipo documento</label>
            <select class="form-control" id="cliDocType" name="document_type">
              <?php
                $doc = strtoupper((string) ($client['document_type'] ?? ''));
                $docs = ['' => '—', 'CC' => 'CC', 'NIT' => 'NIT', 'CE' => 'CE', 'PAS' => 'Pasaporte', 'PPT' => 'PPT', 'OTRO' => 'Otro'];
                foreach ($docs as $val => $txt):
              ?>
                <option value="<?= e($val) ?>" <?= $doc === $val ? 'selected' : '' ?>><?= e($txt) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label" for="cliDocNum">Número documento</label>
            <input class="form-control" id="cliDocNum" name="document_number" value="<?= e($client['document_number'] ?? '') ?>" placeholder="Documento">
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label" for="cliStatus">Estado</label>
            <select class="form-control" id="cliStatus" name="status">
              <?php foreach (['activo' => 'Activo', 'prospecto' => 'Prospecto', 'inactivo' => 'Inactivo'] as $val => $txt): ?>
                <option value="<?= e($val) ?>" <?= ($client['status'] ?? '') === $val ? 'selected' : '' ?>><?= e($txt) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label" for="cliCity">Ciudad</label>
            <input class="form-control" id="cliCity" name="city" value="<?= e($client['city'] ?? '') ?>" placeholder="Bogotá, Medellín…" autocomplete="address-level2">
          </div>
          <div class="form-group" style="margin:0;grid-column:1 / -1">
            <label class="form-label" for="cliAddress">Dirección</label>
            <input class="form-control" id="cliAddress" name="address" value="<?= e($client['address'] ?? '') ?>" placeholder="Calle, número, barrio…" autocomplete="street-address">
          </div>
          <div class="form-group" style="margin:0;grid-column:1 / -1">
            <label class="form-label" for="cliNotes">Notas / observaciones</label>
            <textarea class="form-control" id="cliNotes" name="notes" rows="3" placeholder="Preferencias, horarios, observaciones internas…"><?= e($client['notes'] ?? '') ?></textarea>
          </div>
        </div>

        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;flex-wrap:wrap">
          <button type="submit" class="btn btn-primary">Guardar cambios</button>
        </div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-header"><h3>Origen omnicanal</h3></div>
      <div class="panel-body">
        <div class="info-grid">
          <div class="info-item">
            <label>Canal</label>
            <span><?= e($label) ?></span>
          </div>
          <div class="info-item">
            <label>ID externo</label>
            <span><?= e(($client['external_contact'] ?? '') !== '' ? $client['external_contact'] : '—') ?></span>
          </div>
          <div class="info-item">
            <label>Último contacto</label>
            <span><?= e($client['last_contact'] ?? '—') ?></span>
          </div>
          <div class="info-item">
            <label>Origen</label>
            <span><?= e(($client['source'] ?? '') === 'omni' ? 'Omnicanalidad' : 'Manual') ?></span>
          </div>
          <div class="info-item">
            <label>Pedidos</label>
            <span><?= (int) ($client['orders'] ?? 0) ?></span>
          </div>
          <div class="info-item">
            <label>Lifetime</label>
            <span style="color:var(--gold-light)"><?= money_cop((int) ($client['lifetime'] ?? 0)) ?></span>
          </div>
        </div>
        <p class="form-hint" style="margin-top:14px">
          El canal y el ID externo vienen de Omnicanalidad y no se editan aquí.
          Correo, dirección y documento sí se guardan en el CRM.
        </p>
      </div>
    </div>
  </div>
</form>

<div class="panel client-activity-panel">
  <div class="panel-header">
    <h3>Actividad de mensajes</h3>
    <span class="form-hint" style="margin:0">Veces que el cliente escribió (inbound) por día</span>
  </div>
  <div class="panel-body">
    <div class="client-activity-kpis">
      <div class="client-activity-kpi">
        <strong><?= (int) ($activity['today'] ?? 0) ?></strong>
        <span>Hoy</span>
      </div>
      <div class="client-activity-kpi">
        <strong><?= (int) ($activity['days7'] ?? 0) ?></strong>
        <span>Últimos 7 días</span>
      </div>
      <div class="client-activity-kpi">
        <strong><?= (int) ($activity['days30'] ?? 0) ?></strong>
        <span>Últimos 30 días</span>
      </div>
    </div>
    <?php if (!empty($activity['by_day'])): ?>
      <div class="client-activity-bars" aria-label="Mensajes por día">
        <?php foreach (array_reverse($activity['by_day']) as $dayRow): ?>
          <?php
            $n = (int) ($dayRow['count'] ?? 0);
            $pct = $maxDay > 0 ? (int) round(($n / $maxDay) * 100) : 0;
          ?>
          <div class="client-activity-day" title="<?= e(($dayRow['date'] ?? '') . ': ' . $n . ' mensaje(s)') ?>">
            <div class="client-activity-bar-wrap">
              <div class="client-activity-bar" style="height: <?= max(4, $pct) ?>%"></div>
            </div>
            <span class="client-activity-n"><?= $n ?></span>
            <span class="client-activity-label"><?= e((string) ($dayRow['label'] ?? '')) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-state"><p>Aún no hay mensajes inbound registrados para este contacto.</p></div>
    <?php endif; ?>
  </div>
</div>

<div class="panel">
  <div class="panel-header"><h3>Pedidos</h3></div>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Orden</th>
          <th>Fecha</th>
          <th>Items</th>
          <th>Total</th>
          <th>Estado</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$orders): ?>
          <tr><td colspan="5"><div class="empty-state"><p>Sin pedidos registrados para este contacto.</p></div></td></tr>
        <?php endif; ?>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td><?= e($o['id']) ?></td>
            <td><?= e($o['date']) ?></td>
            <td><?= (int) $o['items'] ?></td>
            <td style="color:var(--gold-light);font-weight:600"><?= money_cop((int) $o['total']) ?></td>
            <td><span class="badge badge-cyan"><?= e(ucfirst($o['status'])) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
