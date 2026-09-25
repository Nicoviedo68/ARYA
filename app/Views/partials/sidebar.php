<?php
/** @var string $active */
/** @var array|null $user */
/** @var string $initials */
/** @var array|null $profile */

use Arya\Helpers\Icons;
use Arya\Helpers\Menu;

$alias = strtoupper((string) ($profile['alias'] ?? $user['role'] ?? 'AGENT'));
$displayName = (string) ($profile['nombre'] ?? $user['name'] ?? 'Usuario');
$displayRole = $alias;
$avatarUrl = (string) ($profile['imagen'] ?? $user['avatar'] ?? '');
$groups = Menu::groupedForAlias($alias, 'crm');
// Blindaje final: el CRM nunca pinta módulos OCP (ni aunque la DB/caché fallen)
foreach ($groups as $gName => $gItems) {
    $groups[$gName] = array_values(array_filter(
        $gItems,
        static fn (array $it): bool => !str_starts_with((string) ($it['key'] ?? ''), 'ocp_')
    ));
    if ($groups[$gName] === []) {
        unset($groups[$gName]);
    }
}
$menuFromDb = Menu::isFromDatabase();

$menuIcon = static fn (array $item): string => Icons::svg(
    (string) ($item['icon'] ?? ''),
    (string) ($item['key'] ?? '')
);

$flatMenu = [];
foreach ($groups as $grupoNombre => $items) {
    foreach ($items as $item) {
        $flatMenu[$item['key']] = $item;
    }
}

// Accesos rápidos siempre visibles (orden fijo; solo si el alias los tiene)
// Principales: operación diaria. Chat GPT y Generador van en Automatización.
$quickOrder = ['dashboard', 'omnichannel', 'clients', 'reports'];
$quickKeys = [];
$quickTabs = [];
foreach ($quickOrder as $qk) {
    if (isset($flatMenu[$qk])) {
        $quickTabs[] = $flatMenu[$qk];
        $quickKeys[$qk] = true;
    }
}

// Menú agrupado: sin duplicar lo que ya está en accesos rápidos
$menuGroups = [];
$activeGroup = '';
foreach ($groups as $grupoNombre => $items) {
    $filtered = [];
    foreach ($items as $item) {
        if (isset($quickKeys[$item['key']])) {
            continue;
        }
        $filtered[] = $item;
        if ($active === $item['key']) {
            $activeGroup = $grupoNombre;
        }
    }
    if ($filtered !== []) {
        $menuGroups[$grupoNombre] = $filtered;
    }
}
?>
<aside class="sidebar" id="sidebar" data-alias="<?= e($alias) ?>" data-menu-source="<?= $menuFromDb ? 'db' : 'empty' ?>" data-menu-scope="crm" data-menu-ver="8">
  <div class="sidebar-brand">
    <div class="sidebar-brand-main">
      <img src="<?= asset('img/arya-logo.png') ?>" alt="Arya" width="40" height="40">
      <div class="brand-text">
        <span class="brand-name">ARYA</span>
        <span class="brand-tag">CRM Omnicanal</span>
      </div>
    </div>
    <button type="button" class="sidebar-collapse-btn" id="sidebarCollapseBtn" aria-label="Recoger menú" title="Recoger menú">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" aria-hidden="true">
        <path d="M15 6l-6 6 6 6"/>
      </svg>
    </button>
  </div>

  <?php if ($quickTabs !== []): ?>
    <nav class="quick-tabs" aria-label="Accesos rápidos" id="quickTabs">
      <p class="quick-tabs-label">Principales</p>
      <?php foreach ($quickTabs as $item): ?>
        <a
          href="<?= url($item['href']) ?>"
          class="quick-tab<?= ($active === $item['key']) ? ' active' : '' ?>"
          title="<?= e($item['label']) ?>"
          data-nav-key="<?= e($item['key']) ?>"
          data-spa-nav
        >
          <span class="quick-tab-icon"><?= $menuIcon($item) ?></span>
          <span class="quick-tab-label"><?= e($item['label']) ?></span>
          <?php if ($item['key'] === 'omnichannel'): ?>
            <span class="nav-badge" data-omni-badge hidden aria-label="Conversaciones sin leer">0</span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>

  <nav class="sidebar-nav" aria-label="Menú principal">
    <?php if ($groups === [] && $quickTabs === []): ?>
      <p class="nav-empty">No hay módulos para el alias <strong><?= e($alias) ?></strong>.</p>
    <?php elseif ($menuGroups === []): ?>
      <!-- Solo accesos rápidos; no hay ítems extra en grupos -->
    <?php else: ?>
      <?php foreach ($menuGroups as $grupoNombre => $items): ?>
        <?php
          $groupId = 'nav-group-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($grupoNombre));
          $isOpen = ($activeGroup === '' || $activeGroup === $grupoNombre);
        ?>
        <section class="nav-group <?= $isOpen ? 'is-open' : '' ?>" data-nav-group>
          <button type="button" class="nav-group-toggle" aria-expanded="<?= $isOpen ? 'true' : 'false' ?>" aria-controls="<?= e($groupId) ?>">
            <span><?= e($grupoNombre) ?></span>
            <svg class="nav-group-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" aria-hidden="true">
              <path d="M6 9l6 6 6-6"/>
            </svg>
          </button>
          <div class="nav-group-body" id="<?= e($groupId) ?>"<?= $isOpen ? '' : ' hidden' ?>>
            <?php foreach ($items as $item): ?>
              <a
                href="<?= url($item['href']) ?>"
                class="nav-item<?= ($active === $item['key']) ? ' active' : '' ?>"
                title="<?= e($item['label']) ?>"
                data-nav-key="<?= e($item['key']) ?>"
                data-spa-nav
              >
                <span class="nav-item-icon"><?= $menuIcon($item) ?></span>
                <span class="nav-item-label"><?= e($item['label']) ?></span>
                <?php if ($item['key'] === 'omnichannel'): ?>
                  <span class="nav-badge" data-omni-badge hidden aria-label="Conversaciones sin leer">0</span>
                <?php endif; ?>
              </a>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endforeach; ?>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="theme-switch" role="group" aria-label="Tema de apariencia">
      <span class="theme-switch-label">Apariencia</span>
      <div class="theme-switch-track">
        <button type="button" class="theme-btn is-active" data-theme-set="dark" title="Tema oscuro" aria-pressed="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
            <path d="M21 14.5A8.5 8.5 0 0 1 9.5 3 7 7 0 1 0 21 14.5z"/>
          </svg>
          <span class="theme-btn-text">Oscuro</span>
        </button>
        <button type="button" class="theme-btn" data-theme-set="light" title="Tema claro" aria-pressed="false">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
            <circle cx="12" cy="12" r="4"/>
            <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
          </svg>
          <span class="theme-btn-text">Claro</span>
        </button>
      </div>
    </div>

    <button type="button" class="user-chip" id="profileOpenBtn" data-profile-open aria-haspopup="dialog">
      <span class="user-avatar" aria-hidden="true">
        <?php if ($avatarUrl !== ''): ?>
          <img src="<?= e($avatarUrl) ?>" alt="">
        <?php else: ?>
          <?= e($initials) ?>
        <?php endif; ?>
      </span>
      <span class="user-meta">
        <span class="name"><?= e($displayName) ?></span>
        <span class="role"><?= e($displayRole) ?></span>
        <span class="user-hint">Editar perfil</span>
      </span>
      <span class="user-chip-edit" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" width="15" height="15">
          <path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
        </svg>
      </span>
    </button>
  </div>
</aside>
