<div class="empty-state panel" style="max-width:520px;margin:80px auto">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
  <h2 style="font-family:var(--font-display);margin-bottom:8px">404</h2>
  <p>La página que buscas no existe.</p>

  <?php if (!empty($debug)): ?>
    <div style="text-align:left;margin-top:20px;font-size:0.78rem;color:var(--text-secondary);background:rgba(0,0,0,.35);padding:12px;border-radius:8px;border:1px solid var(--border)">
      <div><strong style="color:var(--cyan)">Ruta resuelta:</strong> <?= e($resolvedUri ?? '') ?></div>
      <div><strong>REQUEST_URI:</strong> <?= e($requestUri ?? '') ?></div>
      <div><strong>SCRIPT_NAME:</strong> <?= e($scriptName ?? '') ?></div>
      <div><strong>PATH_INFO:</strong> <?= e((string) ($pathInfo ?? '')) ?></div>
      <div><strong>GET r:</strong> <?= e((string) ($queryR ?? '')) ?></div>
    </div>
  <?php endif; ?>

  <a href="<?= url('dashboard') ?>" class="btn btn-primary" style="margin-top:20px">Volver al dashboard</a>
  <div style="margin-top:12px">
    <a href="<?= url('diagnostico') ?>" style="color:var(--cyan);font-size:0.85rem">Abrir diagnóstico</a>
  </div>
</div>
