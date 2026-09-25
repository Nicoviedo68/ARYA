<?php
/** @var array $stats */
/** @var list<array<string,mixed>> $channelKpis */
/** @var array $conversations */
/** @var array $chart */
/** @var array $clientsMod */
/** @var array $tasksMod */
/** @var array $generatorMod */
/** @var array $reportKpis */

$channelKpis = $channelKpis ?? [];
$clientsMod = $clientsMod ?? [];
$tasksMod = $tasksMod ?? [];
$generatorMod = $generatorMod ?? [];
$reportKpis = $reportKpis ?? [];

$channelIcons = [
    'whatsapp' => '<svg class="ch-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="#25D366" d="M12.04 2C6.58 2 2.15 6.4 2.15 11.83c0 1.99.57 3.84 1.56 5.43L2 22l4.9-1.61a10 10 0 0 0 5.14 1.42h.01c5.46 0 9.89-4.4 9.89-9.83C21.94 6.4 17.5 2 12.04 2zm5.75 13.99c-.24.68-1.4 1.25-1.93 1.33-.49.07-1.12.1-1.81-.11-.42-.13-.95-.31-1.64-.6-2.89-1.25-4.77-4.16-4.92-4.35-.14-.19-1.18-1.57-1.18-3 0-1.42.74-2.12 1-2.41.26-.29.57-.36.76-.36h.55c.17 0 .4-.06.63.48.24.55.81 1.9.88 2.04.07.14.12.31.02.5-.1.19-.14.31-.28.48-.14.17-.29.37-.42.5-.14.14-.28.29-.12.57.16.28.71 1.17 1.52 1.9 1.05.93 1.93 1.22 2.21 1.36.28.14.44.12.6-.07.16-.19.69-.8.88-1.08.19-.28.37-.23.63-.14.26.1 1.64.77 1.92.91.28.14.47.21.54.33.07.12.07.68-.17 1.36z"/></svg>',
    'messenger' => '<svg class="ch-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="#0084FF" d="M12 2C6.36 2 2 6.13 2 11.7c0 2.91 1.19 5.44 3.14 7.17V22l2.87-1.58c.9.25 1.85.38 2.99.38 5.64 0 10-4.13 10-9.7S17.64 2 12 2zm1.01 13.08-2.55-2.72-4.98 2.72 5.47-5.81 2.61 2.72 4.92-2.72-5.47 5.81z"/></svg>',
    'instagram' => '<svg class="ch-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="url(#igGradDash)" d="M7.8 2h8.4C19.4 2 22 4.6 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8C4.6 22 2 19.4 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2zm0 2A3.8 3.8 0 0 0 4 7.8v8.4A3.8 3.8 0 0 0 7.8 20h8.4a3.8 3.8 0 0 0 3.8-3.8V7.8A3.8 3.8 0 0 0 16.2 4H7.8zm9.65 1.5a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5zM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/><defs><linearGradient id="igGradDash" x1="2" y1="22" x2="22" y2="2"><stop stop-color="#F58529"/><stop offset=".5" stop-color="#DD2A7B"/><stop offset="1" stop-color="#515BD4"/></linearGradient></defs></svg>',
    'web' => '<svg class="ch-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="#00D4E8" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm7.9 9h-3.17a15.4 15.4 0 0 0-1.3-5.3A8.03 8.03 0 0 1 19.9 11zM12 4c.9 0 2.3 1.8 3.05 5H8.95C9.7 5.8 11.1 4 12 4zM4.1 13h3.17c.2 1.9.7 3.7 1.3 5.3A8.03 8.03 0 0 1 4.1 13zm3.17-2H4.1a8.03 8.03 0 0 1 4.47-5.3A15.4 15.4 0 0 0 7.27 11zM12 20c-.9 0-2.3-1.8-3.05-5h6.1C14.3 18.2 12.9 20 12 20zm3.43-2.7c.6-1.6 1.1-3.4 1.3-5.3h3.17a8.03 8.03 0 0 1-4.47 5.3zM9.55 13c.2 1.7.6 3.3 1.2 4.6.4.8.8 1.4 1.25 1.4s.85-.6 1.25-1.4c.6-1.3 1-2.9 1.2-4.6H9.55zm0-2h4.9c-.2-1.7-.6-3.3-1.2-4.6C12.85 5.6 12.45 5 12 5s-.85.6-1.25 1.4c-.6 1.3-1 2.9-1.2 4.6z"/></svg>',
    'facebook' => '<svg class="ch-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="#1877F2" d="M13.5 22v-8h2.7l.4-3.1h-3.1V9c0-.9.3-1.5 1.6-1.5H16.8V4.7c-.3 0-1.2-.1-2.3-.1-2.3 0-3.9 1.4-3.9 4v2.3H8.1V14h2.5v8h2.9z"/></svg>',
    'linkedin' => '<svg class="ch-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="#0A66C2" d="M6.5 21H3.7V9.5h2.8V21zM5.1 8.1A1.6 1.6 0 1 1 5.1 4.9a1.6 1.6 0 0 1 0 3.2zM21 21h-2.8v-5.6c0-1.3 0-3-1.8-3s-2.1 1.4-2.1 2.9V21H11.5V9.5h2.7v1.6c.4-.7 1.3-1.8 3.3-1.8 3.5 0 4.2 2.3 4.2 5.3V21z"/></svg>',
    'tiktok' => '<svg class="ch-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="#25F4EE" d="M19.6 7.4a5.4 5.4 0 0 1-3.2-1.1v6.3a5.3 5.3 0 1 1-4.5-5.2v2.5a2.8 2.8 0 1 0 2 2.7V2.8h2.5a5.4 5.4 0 0 0 3.2 3.1v1.5z"/></svg>',
];

$channelKey = static function (string $channel): string {
    return match (strtolower($channel)) {
        'whatsapp', 'wa' => 'whatsapp',
        'instagram', 'ig' => 'instagram',
        'messenger' => 'messenger',
        'facebook', 'fb' => 'facebook',
        'linkedin' => 'linkedin',
        'tiktok' => 'tiktok',
        'web', 'webchat', 'widget' => 'web',
        default => 'web',
    };
};

$channelLabel = static function (string $channel) use ($channelKey): string {
    return match ($channelKey($channel)) {
        'whatsapp' => 'WhatsApp',
        'instagram' => 'Instagram',
        'messenger' => 'Messenger',
        'facebook' => 'Facebook',
        'linkedin' => 'LinkedIn',
        'tiktok' => 'TikTok',
        default => 'Web Chat',
    };
};

$delta = $stats['conversations_delta_pct'] ?? null;
$deltaMeta = 'Sin datos de ayer';
$deltaClass = '';
if ($delta !== null) {
    $sign = $delta > 0 ? '+' : '';
    $deltaMeta = $sign . $delta . '% vs ayer';
    $deltaClass = $delta < 0 ? 'down' : '';
}

$unread = (int) ($stats['unread_total'] ?? 0);
$avgResp = $stats['avg_response_min'] ?? null;

$chartsScript = "
document.addEventListener('DOMContentLoaded', () => {
  const ctx = document.getElementById('dashChart');
  if (!ctx || typeof Chart === 'undefined') return;
  const css = getComputedStyle(document.documentElement);
  const legend = (css.getPropertyValue('--chart-legend') || '#8892B0').trim();
  const tick = (css.getPropertyValue('--chart-tick') || '#5A6A85').trim();
  const grid = (css.getPropertyValue('--chart-grid') || 'rgba(0,240,255,0.06)').trim();
  const chart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: " . json_encode($chart['labels'] ?? [], JSON_UNESCAPED_UNICODE) . ",
      datasets: [
        { label: 'WhatsApp', data: " . json_encode($chart['whatsapp'] ?? []) . ", borderColor: '#25D366', backgroundColor: 'rgba(37,211,102,0.12)', pointBackgroundColor: '#25D366', tension: 0.4, fill: true },
        { label: 'Instagram', data: " . json_encode($chart['instagram'] ?? []) . ", borderColor: '#E4405F', backgroundColor: 'rgba(228,64,95,0.1)', pointBackgroundColor: '#E4405F', tension: 0.4, fill: true },
        { label: 'Messenger', data: " . json_encode($chart['facebook'] ?? []) . ", borderColor: '#0084FF', backgroundColor: 'rgba(0,132,255,0.08)', pointBackgroundColor: '#0084FF', tension: 0.4, fill: false },
        { label: 'Web', data: " . json_encode($chart['web'] ?? []) . ", borderColor: '#00D4E8', backgroundColor: 'rgba(0,212,232,0.08)', pointBackgroundColor: '#00D4E8', tension: 0.4, fill: false }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { labels: { color: legend, boxWidth: 12, usePointStyle: true, font: { family: 'Outfit' } } } },
      scales: {
        x: { ticks: { color: tick }, grid: { color: grid } },
        y: { ticks: { color: tick }, grid: { color: grid }, beginAtZero: true }
      }
    }
  });
  document.addEventListener('arya:themechange', () => {
    const c = getComputedStyle(document.documentElement);
    const l = (c.getPropertyValue('--chart-legend') || legend).trim();
    const t = (c.getPropertyValue('--chart-tick') || tick).trim();
    const g = (c.getPropertyValue('--chart-grid') || grid).trim();
    chart.options.plugins.legend.labels.color = l;
    chart.options.scales.x.ticks.color = t;
    chart.options.scales.y.ticks.color = t;
    chart.options.scales.x.grid.color = g;
    chart.options.scales.y.grid.color = g;
    chart.update('none');
  });
});
";
?>

<!-- Fila 1: KPIs operativos -->
<div class="grid grid-4 stagger" style="margin-bottom:16px">
  <div class="panel kpi-card">
    <div class="kpi-label">Conversaciones hoy</div>
    <div class="kpi-value"><?= (int) ($stats['conversations_today'] ?? 0) ?></div>
    <div class="kpi-meta <?= e($deltaClass) ?>"><?= e($deltaMeta) ?></div>
  </div>
  <div class="panel kpi-card">
    <div class="kpi-label">Chats abiertos</div>
    <div class="kpi-value"><?= (int) ($stats['open_chats'] ?? 0) ?></div>
    <div class="kpi-meta gold"><?= $unread > 0 ? ((int) $unread . ' sin leer') : 'Sin pendientes' ?></div>
  </div>
  <div class="panel kpi-card">
    <div class="kpi-label">Clientes activos</div>
    <div class="kpi-value"><?= (int) ($clientsMod['activo'] ?? $stats['clients_active'] ?? 0) ?></div>
    <div class="kpi-meta"><?= (int) ($clientsMod['new_week'] ?? 0) > 0 ? ('+' . (int) $clientsMod['new_week'] . ' esta semana') : ((int) ($clientsMod['total'] ?? 0) . ' en total') ?></div>
  </div>
  <div class="panel kpi-card">
    <div class="kpi-label">Tasa de respuesta</div>
    <div class="kpi-value"><?= e((string) ($stats['conversion_rate'] ?? 0)) ?>%</div>
    <div class="kpi-meta"><?= $avgResp !== null ? ('Avg ' . $avgResp . ' min') : 'Sin respuestas aún' ?></div>
  </div>
</div>

<!-- Fila 2: KPIs por canal -->
<div class="grid grid-4 stagger dash-channel-kpis" style="margin-bottom:24px">
  <?php foreach ($channelKpis as $ck):
      $cid = (string) ($ck['id'] ?? 'web');
      $color = (string) ($ck['color'] ?? '#00D4E8');
      $icon = $channelIcons[$cid] ?? $channelIcons['web'];
  ?>
    <a href="<?= url('omnicanalidad') ?>?canal=<?= urlencode($cid === 'messenger' ? 'messenger' : $cid) ?>"
       class="panel kpi-card kpi-channel kpi-channel-<?= e($cid) ?>"
       style="--ch-color: <?= e($color) ?>">
      <div class="kpi-channel-head">
        <span class="kpi-channel-logo"><?= $icon ?></span>
        <span class="kpi-channel-name"><?= e((string) $ck['name']) ?></span>
      </div>
      <div class="kpi-value kpi-channel-value"><?= (int) ($ck['messages_today'] ?? 0) ?></div>
      <div class="kpi-meta kpi-channel-meta">
        <?= (int) ($ck['conversations'] ?? 0) ?> chats · <?= (int) ($ck['open'] ?? 0) ?> abiertos
      </div>
    </a>
  <?php endforeach; ?>
</div>

<div class="grid grid-2" style="margin-bottom:24px">
  <div class="panel">
    <div class="panel-header">
      <h2>Conversaciones por canal</h2>
      <a href="<?= url('informes') ?>" class="btn btn-ghost btn-sm">Ver informes</a>
    </div>
    <div class="panel-body">
      <div class="chart-box"><canvas id="dashChart"></canvas></div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-header">
      <h2>Inbox reciente</h2>
      <a href="<?= url('omnicanalidad') ?>" class="btn btn-ghost btn-sm">Abrir inbox</a>
    </div>
    <div class="panel-body" style="padding:0">
      <?php if (!$conversations): ?>
        <div class="dash-empty">Aún no hay conversaciones omnicanal.</div>
      <?php else: ?>
        <?php foreach ($conversations as $c):
            $ck = $channelKey((string) ($c['channel'] ?? ''));
            $icon = $channelIcons[$ck] ?? $channelIcons['web'];
        ?>
          <a href="<?= url('omnicanalidad/chat/' . $c['id']) ?>" class="conv-item">
            <div class="conv-top">
              <span class="conv-name"><?= e((string) ($c['client'] ?? 'Contacto')) ?></span>
              <span class="conv-time"><?= e((string) ($c['time'] ?? '')) ?></span>
            </div>
            <div class="conv-preview"><?= e((string) ($c['preview'] ?? '')) ?></div>
            <div class="conv-meta">
              <span class="ch-badge ch-badge-<?= e($ck === 'facebook' ? 'messenger' : $ck) ?>">
                <?= $icon ?>
                <?= e($channelLabel((string) ($c['channel'] ?? ''))) ?>
              </span>
              <?php if (!empty($c['thread_kind']) && $c['thread_kind'] === 'comment'): ?>
                <span class="badge badge-comment">Comentario</span>
              <?php endif; ?>
              <?php if ((int) ($c['unread'] ?? 0) > 0): ?>
                <span class="badge badge-gold"><?= (int) $c['unread'] ?> nuevo<?= (int) $c['unread'] > 1 ? 's' : '' ?></span>
              <?php endif; ?>
            </div>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Módulos: Clientes · Tareas · Generador -->
<div class="grid grid-3" style="margin-bottom:24px">
  <!-- Clientes -->
  <div class="panel">
    <div class="panel-header">
      <h2>Gestión clientes</h2>
      <a href="<?= url('clientes') ?>" class="btn btn-ghost btn-sm">Ver todos</a>
    </div>
    <div class="panel-body">
      <div class="dash-mini-kpis">
        <div><strong><?= (int) ($clientsMod['total'] ?? 0) ?></strong><span>Total</span></div>
        <div><strong><?= (int) ($clientsMod['activo'] ?? 0) ?></strong><span>Activos</span></div>
        <div><strong><?= (int) ($clientsMod['prospecto'] ?? 0) ?></strong><span>Prospectos</span></div>
        <div><strong><?= (int) ($clientsMod['stale'] ?? 0) ?></strong><span>Sin contacto 14d</span></div>
      </div>
      <?php if (empty($clientsMod['recent'])): ?>
        <div class="dash-empty">Sin clientes aún. Se crean desde omnicanalidad o alta manual.</div>
      <?php else: ?>
        <ul class="dash-list">
          <?php foreach ($clientsMod['recent'] as $cl):
              $ck = $channelKey((string) ($cl['channel'] ?? ''));
          ?>
            <li>
              <a href="<?= url('clientes/' . $cl['id']) ?>">
                <span class="dash-list-title"><?= e((string) $cl['name']) ?></span>
                <span class="dash-list-meta">
                  <span class="ch-badge ch-badge-<?= e($ck === 'facebook' ? 'messenger' : $ck) ?>"><?= e($channelLabel((string) $cl['channel'])) ?></span>
                  <?= e((string) $cl['status']) ?> · <?= e((string) $cl['time']) ?>
                </span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>

  <!-- Tareas -->
  <div class="panel">
    <div class="panel-header">
      <h2>Tareas N8N</h2>
      <a href="<?= url('tareas') ?>" class="btn btn-ghost btn-sm">Ver todas</a>
    </div>
    <div class="panel-body">
      <div class="dash-mini-kpis">
        <div><strong><?= (int) ($tasksMod['activo'] ?? 0) ?></strong><span>Activas</span></div>
        <div><strong><?= (int) ($tasksMod['pausado'] ?? 0) ?></strong><span>Pausadas</span></div>
        <div><strong><?= (int) ($tasksMod['error'] ?? 0) ?></strong><span>Error</span></div>
        <div><strong><?= $tasksMod['avg_success'] !== null ? e((string) $tasksMod['avg_success']) . '%' : '—' ?></strong><span>Éxito avg</span></div>
      </div>
      <div style="margin-bottom:12px">
        <span class="badge badge-<?= !empty($tasksMod['n8n_configured']) ? 'success' : 'warning' ?>">
          N8N: <?= e((string) ($tasksMod['n8n_estado'] ?? 'sin configurar')) ?>
        </span>
      </div>
      <?php if (empty($tasksMod['items'])): ?>
        <div class="dash-empty">Sin flujos en <code>arya_tasks</code>. Cuando N8N registre tareas aparecerán aquí.</div>
      <?php else: ?>
        <ul class="dash-list">
          <?php foreach ($tasksMod['items'] as $t): ?>
            <li>
              <span class="dash-list-title"><?= e((string) $t['name']) ?></span>
              <span class="dash-list-meta">
                <code><?= e((string) $t['workflow']) ?></code>
                · <?= e((string) $t['next_run']) ?>
                · <span class="badge badge-<?= $t['status'] === 'activo' ? 'success' : ($t['status'] === 'error' ? 'danger' : 'warning') ?>"><?= e(ucfirst((string) $t['status'])) ?></span>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>

  <!-- Generador -->
  <div class="panel">
    <div class="panel-header">
      <h2>Generador</h2>
      <a href="<?= url('generador') ?>" class="btn btn-ghost btn-sm">Abrir</a>
    </div>
    <div class="panel-body">
      <div class="dash-mini-kpis">
        <div><strong><?= (int) ($generatorMod['total'] ?? 0) ?></strong><span>Total</span></div>
        <div><strong><?= (int) ($generatorMod['today'] ?? 0) ?></strong><span>Hoy</span></div>
        <div><strong><?= (int) ($generatorMod['week'] ?? 0) ?></strong><span>7 días</span></div>
        <div><strong><?= count($generatorMod['by_platform'] ?? []) ?></strong><span>Plataformas</span></div>
      </div>
      <?php if (empty($generatorMod['recent'])): ?>
        <div class="dash-empty">Aún no hay contenidos. Genera el primero en el módulo Generador.</div>
      <?php else: ?>
        <ul class="dash-list">
          <?php foreach ($generatorMod['recent'] as $g):
              $ck = $channelKey((string) ($g['platform'] ?? ''));
              $icon = $channelIcons[$ck] ?? $channelIcons['web'];
          ?>
            <li>
              <span class="dash-list-title"><?= e((string) ($g['topic'] !== '' ? $g['topic'] : $g['preview'])) ?></span>
              <span class="dash-list-meta">
                <span class="ch-badge ch-badge-<?= e(in_array($ck, ['whatsapp','instagram','messenger','web'], true) ? $ck : 'web') ?>">
                  <?= $icon ?> <?= e($channelLabel((string) $g['platform'])) ?>
                </span>
                <?= e((string) $g['tone']) ?> · <?= e((string) $g['time']) ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Informes (resumen, sin Configuración) -->
<div class="panel">
  <div class="panel-header">
    <h2>Informes · resumen</h2>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a href="<?= url('informes/exportar') ?>" class="btn btn-ghost btn-sm">Exportar CSV</a>
      <a href="<?= url('informes') ?>" class="btn btn-ghost btn-sm">Ver informes</a>
    </div>
  </div>
  <div class="panel-body">
    <div class="grid grid-4 dash-report-strip">
      <div class="dash-report-item">
        <div class="kpi-label">Conversaciones</div>
        <div class="kpi-value" style="font-size:1.4rem"><?= (int) ($reportKpis['total_conversations'] ?? 0) ?></div>
        <div class="kpi-meta"><?= (int) ($reportKpis['resolved'] ?? 0) ?> resueltas</div>
      </div>
      <div class="dash-report-item">
        <div class="kpi-label">Mensajes enviados</div>
        <div class="kpi-value" style="font-size:1.4rem"><?= (int) ($reportKpis['messages_sent'] ?? 0) ?></div>
        <div class="kpi-meta"><?= (int) ($reportKpis['messages_today'] ?? 0) ?> hoy</div>
      </div>
      <div class="dash-report-item">
        <div class="kpi-label">Tiempo respuesta</div>
        <div class="kpi-value" style="font-size:1.4rem"><?= e((string) ($reportKpis['avg_response'] ?? '—')) ?></div>
        <div class="kpi-meta">1ª respuesta</div>
      </div>
      <div class="dash-report-item">
        <div class="kpi-label">Clientes nuevos</div>
        <div class="kpi-value" style="font-size:1.4rem"><?= (int) ($reportKpis['new_clients'] ?? 0) ?></div>
        <div class="kpi-meta">Últimos 7 días</div>
      </div>
    </div>
  </div>
</div>
