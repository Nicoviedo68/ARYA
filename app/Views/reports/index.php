<?php
/** @var array $kpis */
/** @var array $chart */
/** @var array $channels */

$chartsScript = "
document.addEventListener('DOMContentLoaded', () => {
  if (typeof Chart === 'undefined') return;
  const css = getComputedStyle(document.documentElement);
  const legend = (css.getPropertyValue('--chart-legend') || '#8892B0').trim();
  const tick = (css.getPropertyValue('--chart-tick') || '#5A6A85').trim();
  const grid = (css.getPropertyValue('--chart-grid') || 'rgba(0,240,255,0.06)').trim();
  const charts = [];

  const line = document.getElementById('reportLine');
  if (line) {
    charts.push(new Chart(line, {
      type: 'bar',
      data: {
        labels: " . json_encode($chart['labels']) . ",
        datasets: [
          { label: 'WhatsApp', data: " . json_encode($chart['whatsapp']) . ", backgroundColor: 'rgba(37,211,102,0.7)', borderRadius: 6 },
          { label: 'Instagram', data: " . json_encode($chart['instagram']) . ", backgroundColor: 'rgba(228,64,95,0.7)', borderRadius: 6 },
          { label: 'Facebook', data: " . json_encode($chart['facebook']) . ", backgroundColor: 'rgba(24,119,242,0.7)', borderRadius: 6 },
          { label: 'Web', data: " . json_encode($chart['web']) . ", backgroundColor: 'rgba(0,240,255,0.7)', borderRadius: 6 }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { labels: { color: legend, boxWidth: 12 } } },
        scales: {
          x: { stacked: true, ticks: { color: tick }, grid: { display: false } },
          y: { stacked: true, ticks: { color: tick }, grid: { color: grid } }
        }
      }
    }));
  }

  const doughnut = document.getElementById('reportDonut');
  if (doughnut) {
    charts.push(new Chart(doughnut, {
      type: 'doughnut',
      data: {
        labels: " . json_encode(array_column($channels, 'name')) . ",
        datasets: [{
          data: " . json_encode(array_column($channels, 'unread')) . ",
          backgroundColor: " . json_encode(array_column($channels, 'color')) . ",
          borderWidth: 0
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '68%',
        plugins: { legend: { position: 'bottom', labels: { color: legend, boxWidth: 12, padding: 16 } } }
      }
    }));
  }

  document.addEventListener('arya:themechange', () => {
    const c = getComputedStyle(document.documentElement);
    const l = (c.getPropertyValue('--chart-legend') || legend).trim();
    const t = (c.getPropertyValue('--chart-tick') || tick).trim();
    const g = (c.getPropertyValue('--chart-grid') || grid).trim();
    charts.forEach((ch) => {
      if (ch.options.plugins?.legend?.labels) ch.options.plugins.legend.labels.color = l;
      if (ch.options.scales?.x?.ticks) ch.options.scales.x.ticks.color = t;
      if (ch.options.scales?.y?.ticks) ch.options.scales.y.ticks.color = t;
      if (ch.options.scales?.y?.grid) ch.options.scales.y.grid.color = g;
      ch.update('none');
    });
  });
});
";
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px">
  <p style="color:var(--text-secondary)">KPIs, interacción y exportación de reportes.</p>
  <a href="<?= url('informes/exportar') ?>" class="btn btn-gold">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M12 3v12M7 10l5 5 5-5M4 21h16"/></svg>
    Descargar Excel (CSV)
  </a>
</div>

<div class="grid grid-3 stagger" style="margin-bottom:24px">
  <div class="panel kpi-card">
    <div class="kpi-label">Conversaciones</div>
    <div class="kpi-value"><?= number_format((int) $kpis['total_conversations']) ?></div>
    <div class="kpi-meta"><?= number_format((int) $kpis['resolved']) ?> resueltas</div>
  </div>
  <div class="panel kpi-card">
    <div class="kpi-label">Tiempo respuesta</div>
    <div class="kpi-value" style="font-size:1.5rem"><?= e($kpis['avg_response']) ?></div>
    <div class="kpi-meta">Promedio del periodo</div>
  </div>
  <div class="panel kpi-card">
    <div class="kpi-label">Tasa de respuesta</div>
    <div class="kpi-value"><?= e((string) ($kpis['response_rate'] ?? $kpis['satisfaction'] ?? 0)) ?>%</div>
    <div class="kpi-meta">Chats con reply agente</div>
  </div>
  <div class="panel kpi-card">
    <div class="kpi-label">Mensajes enviados</div>
    <div class="kpi-value"><?= number_format((int) $kpis['messages_sent']) ?></div>
  </div>
  <div class="panel kpi-card">
    <div class="kpi-label">Clientes nuevos</div>
    <div class="kpi-value"><?= (int) $kpis['new_clients'] ?></div>
    <div class="kpi-meta gold">Últimos 7 días</div>
  </div>
  <div class="panel kpi-card">
    <div class="kpi-label">Tasa resolución</div>
    <div class="kpi-value"><?= round(((int) $kpis['resolved'] / max(1, (int) $kpis['total_conversations'])) * 100, 1) ?>%</div>
  </div>
</div>

<div class="grid grid-2">
  <div class="panel">
    <div class="panel-header"><h2>Interacciones por canal</h2></div>
    <div class="panel-body"><div class="chart-box"><canvas id="reportLine"></canvas></div></div>
  </div>
  <div class="panel">
    <div class="panel-header"><h2>Inbox pendiente por canal</h2></div>
    <div class="panel-body"><div class="chart-box"><canvas id="reportDonut"></canvas></div></div>
  </div>
</div>

<div class="panel" style="margin-top:20px">
  <div class="panel-header"><h2>Resumen de conversaciones</h2></div>
  <div class="panel-body">
    <p style="color:var(--text-secondary);font-size:0.9rem;margin-bottom:12px">
      El botón <strong style="color:var(--gold-light)">Descargar Excel</strong> exporta el detalle de conversaciones en formato CSV compatible con Excel.
    </p>
    <div class="grid grid-4">
      <?php foreach ($channels as $ch): ?>
        <div style="padding:14px;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--bg-deep)">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
            <span class="channel-dot" style="background:<?= e($ch['color']) ?>"></span>
            <strong><?= e($ch['name']) ?></strong>
          </div>
          <div style="font-size:0.8rem;color:var(--text-muted)">
            <?= $ch['online'] ? '● Online' : '○ Offline' ?> · <?= (int) $ch['unread'] ?> sin leer
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
