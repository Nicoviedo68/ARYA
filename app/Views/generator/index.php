<?php
/** @var array|null $result */
/** @var array $stats */
/** @var list<array<string,mixed>> $recent */
$stats = $stats ?? ['total' => 0, 'today' => 0, 'week' => 0];
$recent = $recent ?? [];
?>

<div class="grid grid-4 stagger" style="margin-bottom:20px">
  <div class="panel kpi-card">
    <div class="kpi-label">Generados</div>
    <div class="kpi-value"><?= (int) ($stats['total'] ?? 0) ?></div>
  </div>
  <div class="panel kpi-card">
    <div class="kpi-label">Hoy</div>
    <div class="kpi-value"><?= (int) ($stats['today'] ?? 0) ?></div>
  </div>
  <div class="panel kpi-card">
    <div class="kpi-label">Últimos 7 días</div>
    <div class="kpi-value"><?= (int) ($stats['week'] ?? 0) ?></div>
  </div>
  <div class="panel kpi-card">
    <div class="kpi-label">Historial</div>
    <div class="kpi-value" style="font-size:1.2rem">DB</div>
    <div class="kpi-meta">arya_generated_content</div>
  </div>
</div>

<div class="grid grid-2" style="margin-bottom:24px">
  <div class="panel">
    <div class="panel-header">
      <h2>Crear contenido</h2>
      <span class="badge badge-gold">IA · Demo</span>
    </div>
    <div class="panel-body">
      <form method="POST" action="<?= url('generador/crear') ?>">
        <?= csrf_field() ?>
        <div class="form-group">
          <label class="form-label" for="platform">Plataforma</label>
          <select class="form-control" id="platform" name="platform">
            <option value="instagram">Instagram</option>
            <option value="facebook">Facebook</option>
            <option value="whatsapp">WhatsApp / Estado</option>
            <option value="linkedin">LinkedIn</option>
            <option value="tiktok">TikTok</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="tone">Tono</label>
          <select class="form-control" id="tone" name="tone">
            <option value="profesional">Profesional</option>
            <option value="cercano">Cercano</option>
            <option value="urgente">Urgente / CTA fuerte</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="topic">Tema / producto</label>
          <input class="form-control" type="text" id="topic" name="topic" placeholder="Ej: lanzamiento plan premium Arya">
        </div>
        <div class="form-group">
          <label class="form-label" for="cta">Llamado a la acción</label>
          <input class="form-control" type="text" id="cta" name="cta" placeholder="Ej: Agenda tu demo hoy">
        </div>
        <button type="submit" class="btn btn-primary btn-block">Generar contenido</button>
      </form>
    </div>
  </div>

  <div class="panel">
    <div class="panel-header">
      <h2>Resultado</h2>
      <?php if ($result): ?>
        <button type="button" class="btn btn-ghost btn-sm" data-copy="#genCaption">Copiar</button>
      <?php endif; ?>
    </div>
    <div class="panel-body">
      <?php if ($result): ?>
        <div style="margin-bottom:12px;display:flex;gap:8px;flex-wrap:wrap">
          <span class="badge badge-cyan"><?= e(ucfirst((string) $result['platform'])) ?></span>
          <span class="badge badge-gold"><?= e(ucfirst((string) $result['tone'])) ?></span>
        </div>
        <div class="gen-preview" id="genCaption"><?= e((string) $result['caption']) ?>

<?= e((string) $result['hashtags']) ?></div>

        <h3 style="margin:20px 0 10px;font-family:var(--font-display);font-size:0.9rem">Hooks alternativos</h3>
        <ul style="display:flex;flex-direction:column;gap:8px">
          <?php foreach (($result['hooks'] ?? []) as $hook): ?>
            <li style="padding:10px 14px;background:var(--bg-elevated);border:1px solid var(--border);border-radius:8px;font-size:0.88rem;color:var(--text-secondary)">
              <?= e((string) $hook) ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <div class="empty-state">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2l1.5 6.5L20 10l-6.5 1.5L12 18l-1.5-6.5L4 10l6.5-1.5L12 2z"/></svg>
          <p>Completa el formulario y genera tu primer post. Quedará guardado en historial.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="panel">
  <div class="panel-header">
    <h2>Historial reciente</h2>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Plataforma</th>
          <th>Tono</th>
          <th>Tema</th>
          <th>Vista previa</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$recent): ?>
          <tr>
            <td colspan="5" style="text-align:center;color:var(--text-secondary);padding:24px">
              Sin generaciones guardadas todavía.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($recent as $row): ?>
            <tr>
              <td><?= e((string) $row['time']) ?></td>
              <td><span class="badge badge-cyan"><?= e(ucfirst((string) $row['platform'])) ?></span></td>
              <td><?= e(ucfirst((string) $row['tone'])) ?></td>
              <td><?= e((string) ($row['topic'] !== '' ? $row['topic'] : '—')) ?></td>
              <td style="max-width:320px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e((string) $row['preview']) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
