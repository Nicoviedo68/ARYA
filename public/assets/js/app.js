/**
 * Arya CRM — Frontend interactions
 */
(() => {
  'use strict';

  // ---------- CRM: limpiar fugas de menú OCP (el SPA no recarga el sidebar) ----------
  const purgeOcpMenuFromCrmSidebar = () => {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;
    let removed = 0;
    sidebar.querySelectorAll('[data-nav-key^="ocp_"]').forEach((el) => {
      el.remove();
      removed += 1;
    });
    sidebar.querySelectorAll('[data-nav-group]').forEach((section) => {
      if (!section.querySelector('[data-nav-key]')) section.remove();
    });
    // Si el shell SPA quedó con menú OCP antiguo, forzar recarga limpia una vez
    if (removed > 0 && sidebar.getAttribute('data-menu-ver') !== '4') {
      try {
        if (!sessionStorage.getItem('arya.menu.purged.reload')) {
          sessionStorage.setItem('arya.menu.purged.reload', '1');
          window.location.reload();
        }
      } catch (_) {
        window.location.reload();
      }
    }
  };
  purgeOcpMenuFromCrmSidebar();

  // ---------- Tema Oscuro / Claro ----------
  const THEME_KEY = 'arya.theme';
  const themeBtns = document.querySelectorAll('[data-theme-set]');

  const readTheme = () => {
    try {
      const t = localStorage.getItem(THEME_KEY);
      return t === 'light' ? 'light' : 'dark';
    } catch (_) {
      return 'dark';
    }
  };

  const applyTheme = (theme) => {
    const next = theme === 'light' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    try {
      localStorage.setItem(THEME_KEY, next);
    } catch (_) {
      /* ignore */
    }
    const meta = document.getElementById('themeColorMeta');
    if (meta) {
      const cssMeta = getComputedStyle(document.documentElement).getPropertyValue('--theme-meta').trim();
      meta.setAttribute('content', cssMeta || (next === 'light' ? '#F3F5F9' : '#0A192F'));
    }
    themeBtns.forEach((btn) => {
      const active = btn.getAttribute('data-theme-set') === next;
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    document.dispatchEvent(new CustomEvent('arya:themechange', { detail: { theme: next } }));
  };

  // Login: siempre oscuro. CRM: respeta preferencia del usuario.
  const authFixedDark = document.body?.dataset?.authFixedTheme === 'dark';
  if (!authFixedDark) {
    applyTheme(readTheme());
    themeBtns.forEach((btn) => {
      btn.addEventListener('click', () => {
        applyTheme(btn.getAttribute('data-theme-set') || 'dark');
      });
    });
  } else {
    document.documentElement.setAttribute('data-theme', 'dark');
  }

  // Sidebar mobile toggle
  const toggle = document.getElementById('menuToggle');
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  const collapseBtn = document.getElementById('sidebarCollapseBtn');
  const isMobileNav = () => window.matchMedia('(max-width: 992px)').matches;

  const closeSidebar = () => {
    sidebar?.classList.remove('open');
    overlay?.classList.remove('show');
    document.body.classList.remove('sidebar-open');
    // Forzar cierre visual (evita overlay “pegado” en móvil al cambiar de módulo)
    if (overlay) {
      overlay.style.display = 'none';
      overlay.style.pointerEvents = 'none';
      overlay.setAttribute('aria-hidden', 'true');
    }
  };

  const openSidebar = () => {
    sidebar?.classList.add('open');
    overlay?.classList.add('show');
    document.body.classList.add('sidebar-open');
    if (overlay) {
      overlay.style.display = 'block';
      overlay.style.pointerEvents = 'auto';
      overlay.setAttribute('aria-hidden', 'false');
    }
  };

  toggle?.addEventListener('click', () => {
    if (sidebar?.classList.contains('open')) closeSidebar();
    else openSidebar();
  });

  overlay?.addEventListener('click', closeSidebar);

  // Cerrar menú al navegar en móvil (antes de SPA / full reload)
  sidebar?.querySelectorAll('a.nav-item, a.quick-tab').forEach((link) => {
    link.addEventListener('click', () => {
      if (isMobileNav()) closeSidebar();
    }, { capture: true });
  });

  // bfcache / volver atrás: nunca dejar overlay activo
  window.addEventListener('pageshow', () => {
    if (isMobileNav()) closeSidebar();
  });

  // Desktop: recoger / expandir sidebar (nunca en móvil: deja labels invisibles)
  const COLLAPSE_KEY = 'arya.sidebar.collapsed';
  const applyCollapsed = (collapsed) => {
    if (isMobileNav()) {
      document.body.classList.remove('sidebar-collapsed');
      return;
    }
    document.body.classList.toggle('sidebar-collapsed', collapsed);
    collapseBtn?.setAttribute('aria-label', collapsed ? 'Expandir menú' : 'Recoger menú');
  };

  const syncSidebarMode = () => {
    if (isMobileNav()) {
      document.body.classList.remove('sidebar-collapsed');
      closeSidebar();
      return;
    }
    try {
      applyCollapsed(localStorage.getItem(COLLAPSE_KEY) === '1');
    } catch (_) {
      applyCollapsed(false);
    }
  };

  syncSidebarMode();
  window.addEventListener('resize', () => {
    // debounce ligero
    clearTimeout(window.__aryaSidebarResize);
    window.__aryaSidebarResize = setTimeout(syncSidebarMode, 120);
  });

  collapseBtn?.addEventListener('click', () => {
    if (isMobileNav()) {
      closeSidebar();
      return;
    }
    const next = !document.body.classList.contains('sidebar-collapsed');
    applyCollapsed(next);
    try {
      localStorage.setItem(COLLAPSE_KEY, next ? '1' : '0');
    } catch (_) {
      /* ignore */
    }
  });

  // Acordeón de grupos del menú
  document.querySelectorAll('[data-nav-group]').forEach((group) => {
    const btn = group.querySelector('.nav-group-toggle');
    const body = group.querySelector('.nav-group-body');
    if (!btn || !body) return;

    btn.addEventListener('click', () => {
      if (document.body.classList.contains('sidebar-collapsed')) return;
      const open = group.classList.toggle('is-open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      body.hidden = !open;
    });
  });

  // Modal de perfil (chip del usuario)
  const profileModal = document.getElementById('profileModal');
  const openProfile = () => {
    if (!profileModal) return;
    profileModal.hidden = false;
    document.getElementById('pf_nombre')?.focus();
  };
  const closeProfile = () => {
    if (!profileModal) return;
    profileModal.hidden = true;
  };

  document.querySelectorAll('[data-profile-open]').forEach((el) => {
    el.addEventListener('click', openProfile);
  });
  document.querySelectorAll('[data-profile-close]').forEach((el) => {
    el.addEventListener('click', closeProfile);
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && profileModal && !profileModal.hidden) {
      closeProfile();
    }
  });

  // Auto-dismiss alerts
  document.querySelectorAll('.alert[data-auto-dismiss]').forEach((el) => {
    setTimeout(() => {
      el.style.transition = 'opacity 0.4s, transform 0.4s';
      el.style.opacity = '0';
      el.style.transform = 'translateY(-8px)';
      setTimeout(() => el.remove(), 400);
    }, 4500);
  });

  // Copy to clipboard (generator)
  document.querySelectorAll('[data-copy]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const target = document.querySelector(btn.getAttribute('data-copy'));
      if (!target) return;
      const text = target.innerText || target.value;
      try {
        await navigator.clipboard.writeText(text);
        const prev = btn.textContent;
        btn.textContent = '¡Copiado!';
        setTimeout(() => { btn.textContent = prev; }, 1800);
      } catch (_) {
        /* ignore */
      }
    });
  });

  // Settings tabs
  document.querySelectorAll('.settings-tab').forEach((tab) => {
    tab.addEventListener('click', () => {
      const id = tab.dataset.tab;
      document.querySelectorAll('.settings-tab').forEach((t) => t.classList.remove('active'));
      document.querySelectorAll('.settings-pane').forEach((p) => p.hidden = true);
      tab.classList.add('active');
      const pane = document.getElementById(`pane-${id}`);
      if (pane) pane.hidden = false;
    });
  });

  // Copiar callback / verify token Meta
  document.querySelectorAll('[data-copy]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const sel = btn.getAttribute('data-copy');
      const input = sel ? document.querySelector(sel) : null;
      if (!input || !('value' in input)) return;
      const value = String(input.value || '');
      try {
        await navigator.clipboard.writeText(value);
        const prev = btn.textContent;
        btn.textContent = 'Copiado';
        setTimeout(() => { btn.textContent = prev || 'Copiar'; }, 1400);
      } catch {
        input.focus();
        input.select?.();
      }
    });
  });

  // token_meta: WhatsApp pide identificador; Messenger/Instagram no
  const canalSelect = document.getElementById('canal');
  const identGroup = document.getElementById('group-identificador');
  const identInput = document.getElementById('identificador');
  const tokenHint = document.getElementById('tokenMetaHint');
  const syncTokenMetaFields = () => {
    if (!canalSelect || !identGroup) return;
    const canal = canalSelect.value;
    const isWa = canal === 'whatsapp';
    identGroup.hidden = !isWa;
    if (identInput) {
      identInput.required = isWa;
      if (!isWa) identInput.value = '';
    }
    if (tokenHint) {
      tokenHint.textContent = isWa
        ? 'WhatsApp: page_id + identificador + token'
        : 'Messenger / Instagram: page_id + token';
    }
  };
  canalSelect?.addEventListener('change', syncTokenMetaFields);
  syncTokenMetaFields();

  // token_redes: LinkedIn resalta client_id / secret
  const redSelect = document.getElementById('red');
  const redesHint = document.getElementById('tokenRedesHint');
  const syncTokenRedesFields = () => {
    if (!redSelect || !redesHint) return;
    const red = redSelect.value;
    if (red === 'linkedin') {
      redesHint.textContent = 'LinkedIn: nombre + identificador + token (+ client_id / secret si aplica)';
    } else if (red === 'tiktok') {
      redesHint.textContent = 'TikTok: nombre + identificador + token';
    } else if (red === 'twitter') {
      redesHint.textContent = 'X (Twitter): nombre + @handle + token';
    } else {
      redesHint.textContent = 'Nombre de cuenta + identificador + token';
    }
  };
  redSelect?.addEventListener('change', syncTokenRedesFields);
  syncTokenRedesFields();

  // Omnicanalidad: lógica en assets/js/omni.js (SPA sin reload)

  // Login: feedback fluido al enviar
  const authForm = document.getElementById('authLoginForm');
  const authBtn = document.getElementById('authSubmitBtn');
  authForm?.addEventListener('submit', () => {
    if (!authForm.checkValidity()) return;
    authBtn?.classList.add('is-loading');
    authBtn?.setAttribute('disabled', 'disabled');
  });

  // Deslizamiento CRM ↔ OCP (mismo panel)
  const authShell = document.getElementById('authShell');
  const authFaceCrm = document.getElementById('authFaceCrm');
  const authFaceOcp = document.getElementById('authFaceOcp');
  const ocpForm = document.getElementById('ocpLoginForm');
  const ocpSubmitBtn = document.getElementById('ocpSubmitBtn');

  const setAuthMode = (mode) => {
    if (!authShell || !authFaceOcp) return;
    const next = mode === 'ocp' ? 'ocp' : 'crm';
    authShell.setAttribute('data-auth-mode', next);
    authFaceCrm?.setAttribute('aria-hidden', next === 'ocp' ? 'true' : 'false');
    authFaceOcp.setAttribute('aria-hidden', next === 'crm' ? 'true' : 'false');

    if (next === 'ocp') {
      window.setTimeout(() => document.getElementById('ocp_usuario')?.focus(), 380);
    } else {
      window.setTimeout(() => document.getElementById('email')?.focus(), 380);
    }
  };

  document.querySelectorAll('[data-ocp-open]').forEach((btn) => {
    btn.addEventListener('click', () => setAuthMode('ocp'));
  });
  document.querySelectorAll('[data-ocp-close]').forEach((el) => {
    el.addEventListener('click', () => setAuthMode('crm'));
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && authShell?.getAttribute('data-auth-mode') === 'ocp') {
      setAuthMode('crm');
    }
  });

  ocpForm?.addEventListener('submit', () => {
    if (!ocpForm.checkValidity()) return;
    // Solo feedback visual; no deshabilitar el botón (rompe el POST en algunos navegadores)
    ocpSubmitBtn?.classList.add('is-loading');
  });

  // Abrir cara OCP si llegan con #ocp o ?ocp=1 (ya puede venir en data-auth-mode)
  if ((window.location.hash === '#ocp' || /[?&]ocp=1\b/.test(window.location.search)) && authFaceOcp) {
    setAuthMode('ocp');
  }

  if (authShell?.getAttribute('data-auth-mode') === 'ocp') {
    setAuthMode('ocp');
  }

  // ---------- SPA shell: navegación sin recargar sidebar ----------
  const contentRoot = document.getElementById('content');
  const pageTitleEl = document.getElementById('pageTitle');
  let spaBusy = false;
  const loadedScripts = new Set(
    Array.from(document.querySelectorAll('script[src]')).map((s) => s.src)
  );

  const withPartialParam = (href) => {
    try {
      const u = new URL(href, window.location.origin);
      u.searchParams.set('partial', '1');
      return u.toString();
    } catch {
      return href + (href.includes('?') ? '&' : '?') + 'partial=1';
    }
  };

  const ensureChartJs = () => new Promise((resolve) => {
    if (typeof window.Chart !== 'undefined') {
      resolve();
      return;
    }
    const existing = document.querySelector('script[data-arya-chart]');
    if (existing) {
      existing.addEventListener('load', () => resolve(), { once: true });
      return;
    }
    const s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
    s.dataset.aryaChart = '1';
    s.onload = () => resolve();
    s.onerror = () => resolve();
    document.head.appendChild(s);
  });

  const applyPageStyles = (styles) => {
    const wanted = new Set();
    const list = Array.isArray(styles) ? styles : [];
    list.forEach((href) => {
      if (!href) return;
      try {
        wanted.add(new URL(href, window.location.origin).href);
      } catch (_) {
        wanted.add(href);
      }
    });

    document.querySelectorAll('link[data-arya-page-css]').forEach((el) => {
      const abs = el.href || '';
      if (!wanted.has(abs)) el.remove();
    });

    wanted.forEach((abs) => {
      const exists = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
        .some((el) => el.href === abs);
      if (exists) return;
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = abs;
      link.setAttribute('data-arya-page-css', '1');
      document.head.appendChild(link);
    });
  };

  const runScripts = async (scripts, needsChart) => {
    if (needsChart) {
      await ensureChartJs();
    }
    const list = Array.isArray(scripts) ? scripts : [];
    for (const item of list) {
      if (!item || typeof item !== 'object') continue;
      if (item.type === 'src' && item.src) {
        const abs = new URL(item.src, window.location.origin).href;
        if (loadedScripts.has(abs)) {
          // Scripts de módulo ya cargados: re-inicializar contra el nuevo DOM (SPA)
          if (abs.includes('omni.js') && typeof window.ARYA_OMNI_REINIT === 'function') {
            window.ARYA_OMNI_REINIT();
          }
          if (abs.includes('chatgpt.js') && typeof window.ARYA_CHATGPT_REINIT === 'function') {
            window.ARYA_CHATGPT_REINIT();
          }
          continue;
        }
        await new Promise((resolve) => {
          const s = document.createElement('script');
          s.src = item.src;
          s.onload = () => {
            loadedScripts.add(abs);
            resolve();
          };
          s.onerror = () => resolve();
          document.body.appendChild(s);
        });
      } else if (item.type === 'inline' && item.code) {
        try {
          const s = document.createElement('script');
          s.textContent = item.code;
          document.body.appendChild(s);
          s.remove();
        } catch (_) {
          /* ignore */
        }
      }
    }
  };

  const setActiveNav = (key) => {
    document.body.dataset.active = key || '';
    document.querySelectorAll('[data-nav-key]').forEach((el) => {
      el.classList.toggle('active', key && el.getAttribute('data-nav-key') === key);
    });
  };

  const bindContentHelpers = () => {
    document.querySelectorAll('#content .alert[data-auto-dismiss]').forEach((el) => {
      setTimeout(() => {
        el.style.transition = 'opacity 0.4s, transform 0.4s';
        el.style.opacity = '0';
        el.style.transform = 'translateY(-8px)';
        setTimeout(() => el.remove(), 400);
      }, 4500);
    });
    document.querySelectorAll('#content [data-copy]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const sel = btn.getAttribute('data-copy');
        const target = sel ? document.querySelector(sel) : null;
        if (!target) return;
        const text = target.innerText || target.value || '';
        try {
          await navigator.clipboard.writeText(text);
          const prev = btn.textContent;
          btn.textContent = '¡Copiado!';
          setTimeout(() => { btn.textContent = prev; }, 1600);
        } catch (_) {
          /* ignore */
        }
      });
    });
    document.querySelectorAll('#content .settings-tab').forEach((tab) => {
      tab.addEventListener('click', () => {
        const id = tab.dataset.tab;
        document.querySelectorAll('#content .settings-tab').forEach((t) => t.classList.remove('active'));
        document.querySelectorAll('#content .settings-pane').forEach((p) => { p.hidden = true; });
        tab.classList.add('active');
        const pane = document.getElementById(`pane-${id}`);
        if (pane) pane.hidden = false;
      });
    });
  };

  const navigateSpa = async (href, push = true) => {
    if (!contentRoot || spaBusy) return;
    spaBusy = true;
    contentRoot.classList.add('is-spa-loading');
    closeSidebar();

    try {
      const res = await fetch(withPartialParam(href), {
        headers: {
          'X-Arya-Partial': '1',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
      });

      if (res.status === 401) {
        window.location.href = href;
        return;
      }

      const ct = res.headers.get('content-type') || '';
      if (!res.ok || !ct.includes('application/json')) {
        window.location.href = href;
        return;
      }

      const data = await res.json();
      if (!data || !data.ok || typeof data.html !== 'string') {
        window.location.href = href;
        return;
      }

      applyPageStyles(data.styles || []);
      contentRoot.innerHTML = data.html;
      contentRoot.style.opacity = '';
      contentRoot.classList.remove('is-spa-loading');
      // Evitar que :has(.omni-layout) / animaciones dejen el panel “en blanco”
      contentRoot.querySelectorAll('.stagger > *').forEach((el) => {
        el.style.opacity = '1';
        el.style.transform = 'none';
        el.style.animation = 'none';
      });

      if (pageTitleEl && data.title) {
        pageTitleEl.textContent = data.title;
      }
      document.title = (data.title || 'Arya') + ' — Arya CRM';
      setActiveNav(data.active || '');

      if (push) {
        history.pushState({ spa: true, href }, data.title || '', href);
      }

      await runScripts(data.scripts || [], !!data.needsChart);
      bindContentHelpers();
      window.scrollTo(0, 0);
      requestAnimationFrame(() => {
        contentRoot.style.minHeight = '';
        closeSidebar();
      });
      if (typeof window.ARYA_REFRESH_OMNI_NOTIFS === 'function') {
        window.ARYA_REFRESH_OMNI_NOTIFS();
      }
    } catch (_) {
      window.location.href = href;
    } finally {
      contentRoot?.classList.remove('is-spa-loading');
      closeSidebar();
      spaBusy = false;
    }
  };

  document.addEventListener('click', (e) => {
    const a = e.target.closest('a[data-spa-nav]');
    if (!a || e.defaultPrevented || e.button !== 0) return;
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    const href = a.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;
    // Logout / externos: no SPA
    if (a.target === '_blank' || a.querySelector('form')) return;
    // Móvil: navegación completa (evita contenido invisible tras SPA + flex/omni)
    if (isMobileNav()) {
      closeSidebar();
      return;
    }
    e.preventDefault();
    navigateSpa(href, true);
  });

  window.addEventListener('popstate', () => {
    navigateSpa(window.location.href, false);
  });

  // ---------- Notificaciones omnicanal (global, todos los módulos) ----------
  const notifCfg = window.ARYA_NOTIF || {};
  const notifBellBtn = document.getElementById('notifBellBtn');
  const notifPanel = document.getElementById('notifPanel');
  const notifBellDot = document.getElementById('notifBellDot');
  const notifPanelMeta = document.getElementById('notifPanelMeta');
  const notifPanelStats = document.getElementById('notifPanelStats');
  const notifPanelList = document.getElementById('notifPanelList');
  const omniBadges = () => document.querySelectorAll('[data-omni-badge]');
  let notifTimer = null;
  let lastNotifSig = '';

  const escHtml = (s) => String(s ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

  const formatBadge = (n) => (n > 99 ? '99+' : String(n));

  const applyOmniBadges = (unreadTotal, openTotal) => {
    const show = unreadTotal > 0 ? unreadTotal : openTotal;
    const useUnread = unreadTotal > 0;
    omniBadges().forEach((el) => {
      if (show > 0) {
        el.hidden = false;
        el.textContent = formatBadge(show);
        el.title = useUnread
          ? `${unreadTotal} mensaje(s) sin leer`
          : `${openTotal} conversación(es) abiertas`;
        el.classList.toggle('is-open-count', !useUnread);
      } else {
        el.hidden = true;
        el.textContent = '0';
      }
    });
  };

  const renderNotifPanel = (data) => {
    const unread = Number(data.unread_total || 0);
    const open = Number(data.open_total || 0);
    const items = Array.isArray(data.items) ? data.items : [];
    const winOpen = Number(data.window_open || 0);
    const winClosed = Number(data.window_closed || 0);

    if (notifBellDot) notifBellDot.hidden = unread < 1;
    if (notifPanelMeta) {
      notifPanelMeta.textContent = unread > 0
        ? `${unread} sin leer · ${open} abiertas`
        : (open > 0 ? `${open} conversaciones abiertas` : 'Sin mensajes nuevos');
    }
    if (notifPanelStats) {
      if (unread > 0 || winOpen > 0 || winClosed > 0) {
        notifPanelStats.hidden = false;
        notifPanelStats.innerHTML = [
          `<span class="notif-chip">Abiertas ${open}</span>`,
          winOpen > 0 ? `<span class="notif-chip is-ok">Ventana Meta ${winOpen}</span>` : '',
          winClosed > 0 ? `<span class="notif-chip is-warn">Fuera 24h ${winClosed}</span>` : '',
        ].join('');
      } else {
        notifPanelStats.hidden = true;
        notifPanelStats.innerHTML = '';
      }
    }
    if (!notifPanelList) return;
    if (!items.length) {
      notifPanelList.innerHTML = '<p class="notif-empty">No hay conversaciones sin leer.</p>';
      return;
    }
    notifPanelList.innerHTML = items.map((it) => {
      const can = !!it.can_reply;
      const ch = escHtml(it.channel || '');
      const href = `${notifCfg.omniUrl || '/omnicanalidad'}?chat=${encodeURIComponent(it.id)}`;
      return `
        <a class="notif-item" href="${escHtml(href)}" data-spa-nav>
          <span class="notif-item-name">${escHtml(it.client || 'Contacto')}</span>
          <span class="notif-item-time">${escHtml(it.time || '')}</span>
          <span class="notif-item-preview">${escHtml(it.preview || 'Nuevo mensaje')}</span>
          <span class="notif-item-meta">
            <span class="ch-badge ch-badge-${ch}">${ch}</span>
            <span class="notif-window ${can ? 'is-open' : 'is-closed'}">${escHtml(it.window_label || (can ? '24h' : 'Cerrada'))}</span>
            ${it.unread > 0 ? `<span class="badge badge-gold">${escHtml(it.unread)}</span>` : ''}
          </span>
        </a>`;
    }).join('');
  };

  const refreshOmniNotifications = async () => {
    if (!notifCfg.url) return;
    try {
      const res = await fetch(notifCfg.url, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      if (!res.ok) return;
      const data = await res.json();
      if (!data || data.ok === false) return;
      const unread = Number(data.unread_total || 0);
      const open = Number(data.open_total || 0);
      applyOmniBadges(unread, open);
      renderNotifPanel(data);
      const sig = `${unread}:${open}:${(data.items || []).map((i) => i.id).join(',')}`;
      if (lastNotifSig && sig !== lastNotifSig && unread > 0 && document.visibilityState === 'visible') {
        // leve feedback si llegaron nuevos mientras estás en otro módulo
        notifBellBtn?.classList.add('is-pulse');
        setTimeout(() => notifBellBtn?.classList.remove('is-pulse'), 1200);
      }
      lastNotifSig = sig;
    } catch (_) {
      /* silencioso */
    }
  };

  window.ARYA_REFRESH_OMNI_NOTIFS = refreshOmniNotifications;

  const closeNotifPanel = () => {
    if (!notifPanel) return;
    notifPanel.hidden = true;
    notifBellBtn?.setAttribute('aria-expanded', 'false');
  };

  notifBellBtn?.addEventListener('click', (e) => {
    e.stopPropagation();
    if (!notifPanel) return;
    const open = notifPanel.hidden;
    notifPanel.hidden = !open;
    notifBellBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) refreshOmniNotifications();
  });

  document.addEventListener('click', (e) => {
    if (!notifPanel || notifPanel.hidden) return;
    if (e.target.closest('#notifWrap')) return;
    closeNotifPanel();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeNotifPanel();
  });

  if (notifCfg.url) {
    refreshOmniNotifications();
    const ms = Math.max(15000, Number(notifCfg.pollMs) || 30000);
    notifTimer = setInterval(() => {
      if (document.visibilityState === 'visible') refreshOmniNotifications();
    }, ms);
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') refreshOmniNotifications();
    });
  }
})();

