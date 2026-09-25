(() => {
  'use strict';

  const SESSION = 'arya.prueba.session';
  const STORE = 'arya.prueba.v1';
  const titles = {
    dashboard: 'Dashboard',
    omnicanalidad: 'Omnicanalidad',
    clientes: 'Gestión clientes',
    informes: 'Informes',
    agenda: 'Agenda / citas',
    tareas: 'Tareas programadas',
    chatgpt: 'Chat GPT',
    generador: 'Generador',
    configuracion: 'Configuración',
  };
  const navKey = {
    dashboard: 'dashboard',
    omnicanalidad: 'omnichannel',
    clientes: 'clients',
    informes: 'reports',
    agenda: 'appointments',
    tareas: 'tasks',
    chatgpt: 'chatgpt',
    generador: 'generator',
    configuracion: 'settings',
  };

  const $ = (sel, root = document) => root.querySelector(sel);
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[c]));
  const nowTime = () => new Date().toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' });
  const toast = (msg) => {
    document.querySelectorAll('.prueba-toast').forEach((n) => n.remove());
    const el = document.createElement('div');
    el.className = 'prueba-toast';
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 2400);
  };

  const seed = () => ({
    clients: [
      { id: 1, name: 'María Gómez', email: 'maria.gomez@email.com', phone: '+57 300 123 4567', company: 'Gómez Retail', status: 'activo', channel: 'whatsapp', city: 'Bogotá', last_contact: 'Hoy 10:42', tags: ['VIP', 'Premium'] },
      { id: 2, name: 'Carlos Ruiz', email: 'carlos.ruiz@email.com', phone: '+57 301 555 8899', company: 'Ruiz & Co', status: 'activo', channel: 'instagram', city: 'Medellín', last_contact: 'Hoy 10:18', tags: ['Lead'] },
      { id: 3, name: 'Ana Torres', email: 'ana.torres@empresa.co', phone: '+57 315 222 3344', company: 'Torres Digital', status: 'prospecto', channel: 'messenger', city: 'Cali', last_contact: 'Hoy 09:55', tags: ['Cotización'] },
      { id: 4, name: 'Luis Mendoza', email: 'lmendoza@correo.com', phone: '+57 310 987 6543', company: 'Mendoza SAS', status: 'activo', channel: 'whatsapp', city: 'Barranquilla', last_contact: 'Ayer', tags: ['VIP'] },
      { id: 5, name: 'Sofía Vargas', email: 'sofia.v@mail.com', phone: '+57 320 111 2233', company: 'Vargas Studio', status: 'inactivo', channel: 'instagram', city: 'Bogotá', last_contact: '28 jun', tags: ['Reactivar'] },
    ],
    convs: [
      { id: 1, channel: 'whatsapp', client: 'María Gómez', phone: '+57 300 123 4567', preview: 'Hola, quiero información del plan premium…', time: '10:42', unread: 2, status: 'open', handling: 'ai' },
      { id: 2, channel: 'instagram', client: 'Carlos Ruiz', phone: '@carlos.ruiz', preview: '¿Tienen envío a Medellín?', time: '10:18', unread: 1, status: 'open', handling: 'ai' },
      { id: 3, channel: 'messenger', client: 'Ana Torres', phone: 'Ana Torres', preview: 'Gracias por la cotización, la reviso…', time: '09:55', unread: 0, status: 'pending', handling: 'human' },
      { id: 4, channel: 'web', client: 'Visitante #4821', phone: 'chat.web', preview: '¿Cuál es el horario de atención?', time: '09:30', unread: 1, status: 'open', handling: 'ai' },
      { id: 5, channel: 'whatsapp', client: 'Luis Mendoza', phone: '+57 310 987 6543', preview: 'Perfecto, confirmo el pedido #ORD-8842', time: 'Ayer', unread: 0, status: 'resolved', handling: 'human' },
    ],
    messages: {
      1: [
        { from: 'client', text: 'Hola, buenos días. Quiero información del plan premium.', time: '10:40' },
        { from: 'ai', text: '¡Hola María! El plan premium incluye omnicanalidad, informes y soporte prioritario.', time: '10:41' },
        { from: 'client', text: '¿Cuál es el valor mensual?', time: '10:42' },
      ],
      2: [
        { from: 'client', text: '¿Tienen envío a Medellín?', time: '10:18' },
        { from: 'ai', text: 'Sí, cubrimos Medellín en 24–48 horas hábiles.', time: '10:18' },
      ],
      3: [
        { from: 'client', text: 'Gracias por la cotización, la reviso…', time: '09:55' },
        { from: 'agent', text: 'Cuando quieras la vemos juntas. Quedo atenta.', time: '09:56' },
      ],
      4: [
        { from: 'client', text: '¿Cuál es el horario de atención?', time: '09:30' },
        { from: 'ai', text: 'Atendemos de lunes a sábado, 8:00 a 18:00.', time: '09:30' },
      ],
      5: [
        { from: 'client', text: 'Perfecto, confirmo el pedido #ORD-8842', time: 'Ayer' },
        { from: 'agent', text: 'Pedido confirmado. Te avisamos al despachar.', time: 'Ayer' },
      ],
    },
    tasks: [
      { id: 1, name: 'Recordatorio follow-up WhatsApp', workflow: 'WF-FollowUp-01', next: 'Mañana 09:00', status: 'activo', success: 98.2 },
      { id: 2, name: 'Sincronizar leads Instagram', workflow: 'WF-IG-Sync', next: 'En 30 min', status: 'activo', success: 99.1 },
      { id: 3, name: 'Reporte semanal KPI', workflow: 'WF-Report-Weekly', next: 'Lunes 08:00', status: 'activo', success: 100 },
      { id: 4, name: 'Limpieza conversaciones resueltas', workflow: 'WF-Cleanup', next: '—', status: 'pausado', success: 95 },
      { id: 5, name: 'Alerta chats sin respuesta >1h', workflow: 'WF-SLA-Alert', next: 'En 15 min', status: 'error', success: 82.4 },
    ],
    agenda: [
      { id: 1, title: 'Demo Arya · Gómez Retail', when: 'Hoy 15:00', person: 'María Gómez', status: 'confirmada' },
      { id: 2, title: 'Seguimiento cotización', when: 'Mañana 10:30', person: 'Ana Torres', status: 'pendiente' },
      { id: 3, title: 'Onboarding Ruiz & Co', when: 'Vie 09:00', person: 'Carlos Ruiz', status: 'confirmada' },
    ],
    gpt: [
      { id: 1, title: 'Resumen de inbox', messages: [
        { role: 'user', content: 'Resúmeme los chats abiertos de hoy.' },
        { role: 'assistant', content: 'Hay 12 chats abiertos: 5 en WhatsApp, 3 en Instagram, 2 en Messenger y 1 en web. María Gómez pregunta por el plan premium y Carlos Ruiz por envíos a Medellín.' },
      ] },
    ],
    generated: [
      { topic: 'Promo plan premium', platform: 'instagram', tone: 'cercano', text: 'Tu operación comercial, en un solo inbox. Arya conecta WhatsApp, Instagram y Messenger. Agenda tu demo hoy.', time: '11:00' },
    ],
    gptActive: 1,
    omniChannel: 'all',
    omniSelected: 1,
    settings: { n8n: true, openai: true, meta: true },
  });

  const load = () => {
    try {
      const raw = localStorage.getItem(STORE);
      if (!raw) return seed();
      return { ...seed(), ...JSON.parse(raw) };
    } catch {
      return seed();
    }
  };
  const save = (state) => localStorage.setItem(STORE, JSON.stringify(state));
  let state = load();

  let charts = [];
  const killCharts = () => {
    charts.forEach((c) => { try { c.destroy(); } catch (_) {} });
    charts = [];
  };

  const chLabel = (id) => ({
    whatsapp: 'WhatsApp', instagram: 'Instagram', messenger: 'Messenger',
    facebook: 'Messenger', web: 'Web Chat',
  }[id] || id);

  const badgeCh = (id) => `<span class="ch-badge ch-badge-${id === 'facebook' ? 'messenger' : id}">${esc(chLabel(id))}</span>`;

  const unreadTotal = () => state.convs.reduce((n, c) => n + (c.unread || 0), 0);

  const updateBadge = () => {
    const n = unreadTotal();
    document.querySelectorAll('[data-omni-badge]').forEach((el) => {
      el.hidden = n < 1;
      el.textContent = String(n);
    });
  };

  const logged = () => localStorage.getItem(SESSION) === '1';

  const showViews = () => {
    const auth = $('#authView');
    const app = $('#appView');
    if (logged()) {
      auth.hidden = true;
      app.hidden = false;
      document.body.classList.remove('auth-body');
      route();
    } else {
      auth.hidden = false;
      app.hidden = true;
      document.body.classList.add('auth-body');
      document.body.dataset.active = 'login';
    }
  };

  const parseHash = () => {
    const raw = (location.hash || '#/dashboard').replace(/^#\/?/, '');
    const [mod, id] = raw.split('/');
    return { mod: titles[mod] ? mod : 'dashboard', id: id || '' };
  };

  const setNav = (mod) => {
    document.body.dataset.active = mod;
    const title = $('#pageTitle');
    if (title) title.textContent = titles[mod] || 'ARYA';
    const key = navKey[mod];
    document.querySelectorAll('[data-nav-key]').forEach((el) => {
      el.classList.toggle('active', el.dataset.navKey === key);
    });
  };

  const gptReply = (q) => {
    const t = q.toLowerCase();
    if (t.includes('cliente') || t.includes('inbox')) {
      return 'En modo Prueba hay 5 clientes y 5 conversaciones. María Gómez (VIP) pregunta por premium y Carlos Ruiz por envío a Medellín.';
    }
    if (t.includes('precio') || t.includes('plan')) {
      return 'El plan premium de Arya cubre inbox omnicanal, n8n, informes y agentes de IA. En esta demo el valor de referencia es $299.000 COP/mes.';
    }
    return 'Entendido. En modo Prueba Arya responde con datos locales: inbox, clientes, tareas n8n y generador funcionan aquí mismo, sin servidor PHP.';
  };

  const renderDashboard = (root) => {
    const open = state.convs.filter((c) => c.status === 'open' || c.status === 'pending').length;
    const activos = state.clients.filter((c) => c.status === 'activo').length;
    root.innerHTML = `
      <div class="grid grid-4 stagger" style="margin-bottom:16px">
        <div class="panel kpi-card"><div class="kpi-label">Conversaciones hoy</div><div class="kpi-value">47</div><div class="kpi-meta">+18% vs ayer</div></div>
        <div class="panel kpi-card"><div class="kpi-label">Chats abiertos</div><div class="kpi-value">${open}</div><div class="kpi-meta gold">${unreadTotal()} sin leer</div></div>
        <div class="panel kpi-card"><div class="kpi-label">Clientes activos</div><div class="kpi-value">${activos}</div><div class="kpi-meta">${state.clients.length} en total</div></div>
        <div class="panel kpi-card"><div class="kpi-label">Tasa de respuesta</div><div class="kpi-value">24.6%</div><div class="kpi-meta">Avg 2.4 min</div></div>
      </div>
      <div class="grid grid-4 dash-channel-kpis" style="margin-bottom:24px">
        ${['whatsapp','instagram','messenger','web'].map((id) => {
          const list = state.convs.filter((c) => c.channel === id);
          const msgs = { whatsapp: 28, instagram: 18, messenger: 10, web: 8 }[id];
          return `<a href="#/omnicanalidad" class="panel kpi-card kpi-channel" style="--ch-color:#00D4E8">
            <div class="kpi-channel-head"><span class="kpi-channel-name">${chLabel(id)}</span></div>
            <div class="kpi-value kpi-channel-value">${msgs}</div>
            <div class="kpi-meta">${list.length} chats · ${list.filter((c) => c.status==='open').length} abiertos</div>
          </a>`;
        }).join('')}
      </div>
      <div class="grid grid-2" style="margin-bottom:24px">
        <div class="panel"><div class="panel-header"><h2>Conversaciones por canal</h2><a class="btn btn-ghost btn-sm" href="#/informes">Ver informes</a></div>
          <div class="panel-body"><div class="chart-box"><canvas id="dashChart"></canvas></div></div></div>
        <div class="panel"><div class="panel-header"><h2>Inbox reciente</h2><a class="btn btn-ghost btn-sm" href="#/omnicanalidad">Abrir inbox</a></div>
          <div class="panel-body" style="padding:0">${state.convs.slice(0,5).map((c) => `
            <a class="conv-item" href="#/omnicanalidad">
              <div class="conv-top"><span class="conv-name">${esc(c.client)}</span><span class="conv-time">${esc(c.time)}</span></div>
              <div class="conv-preview">${esc(c.preview)}</div>
              <div class="conv-meta">${badgeCh(c.channel)}${c.unread ? `<span class="badge badge-gold">${c.unread} nuevo</span>` : ''}</div>
            </a>`).join('')}</div></div>
      </div>`;
    const ctx = $('#dashChart');
    if (ctx && window.Chart) {
      charts.push(new Chart(ctx, {
        type: 'line',
        data: {
          labels: ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'],
          datasets: [
            { label: 'WhatsApp', data: [42,55,48,61,58,30,22], borderColor: '#25D366', tension: 0.4, fill: false },
            { label: 'Instagram', data: [18,22,25,20,28,35,40], borderColor: '#E4405F', tension: 0.4, fill: false },
            { label: 'Messenger', data: [12,15,10,18,14,8,6], borderColor: '#0084FF', tension: 0.4, fill: false },
            { label: 'Web', data: [8,10,12,9,11,5,4], borderColor: '#00D4E8', tension: 0.4, fill: false },
          ],
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { color: '#8892B0' } } }, scales: { x: { ticks: { color: '#5A6A85' } }, y: { ticks: { color: '#5A6A85' }, beginAtZero: true } } },
      }));
    }
  };

  const renderOmni = (root) => {
    const ch = state.omniChannel || 'all';
    const selectedId = state.omniSelected;
    const list = state.convs.filter((c) => ch === 'all' || c.channel === ch);
    const sel = state.convs.find((c) => c.id === selectedId) || list[0];
    if (sel) state.omniSelected = sel.id;
    const msgs = (sel && state.messages[sel.id]) || [];
    root.innerHTML = `
      <div class="omni-layout">
        <aside class="omni-channels">
          <header class="omni-section-head"><h2 class="omni-section-title">Canales</h2></header>
          <div class="omni-channels-body">
            ${['all','whatsapp','instagram','messenger','web'].map((id) => `
              <button type="button" class="channel-btn ${ch===id?'active':''}" data-ch="${id}">
                <span class="channel-btn-label">${id==='all'?'Todos':chLabel(id)}</span>
              </button>`).join('')}
          </div>
        </aside>
        <section class="omni-list">
          <div class="omni-list-header"><h2 class="omni-section-title">Conversaciones</h2>
            <span class="omni-list-visible">${list.length} visibles</span></div>
          <div class="omni-list-body">
            ${list.map((c) => `
              <button type="button" class="conv-item ${sel && sel.id===c.id?'active':''}" data-id="${c.id}">
                <div class="conv-top"><span class="conv-name">${esc(c.client)}</span><span class="conv-time">${esc(c.time)}</span></div>
                <div class="conv-preview">${esc(c.preview)}</div>
                <div class="conv-meta">${badgeCh(c.channel)}<span class="badge ${c.handling==='ai'?'badge-ai':'badge-human'}">${c.handling==='ai'?'IA':'Agente'}</span>${c.unread?`<span class="badge badge-gold">${c.unread}</span>`:''}</div>
              </button>`).join('')}
          </div>
        </section>
        <section class="omni-chat">
          ${!sel ? '<div class="empty-state"><p>Selecciona una conversación</p></div>' : `
            <div class="omni-chat-active">
              <div class="chat-header">
                <div><strong>${esc(sel.client)}</strong><div class="ig-header-meta">${esc(sel.phone)} · ${esc(chLabel(sel.channel))}</div></div>
                <div class="chat-header-actions">
                  <span class="badge ${sel.handling==='ai'?'badge-ai':'badge-human'}">${sel.handling==='ai'?'Atendido por IA':'Agente'}</span>
                  <button type="button" class="btn btn-ghost btn-sm" id="takeBtn">${sel.handling==='ai'?'Tomar conversación':'Devolver a IA'}</button>
                </div>
              </div>
              <div class="chat-messages" id="chatMessages">
                ${msgs.map((m) => `<div class="msg msg-${m.from}"><div class="msg-text">${esc(m.text)}</div><span class="msg-time">${esc(m.time)} · ${m.from==='ai'?'IA':m.from==='agent'?'Agente':'Cliente'}</span></div>`).join('')}
              </div>
              <form class="chat-composer" id="omniSend">
                <input class="form-control" id="omniInput" placeholder="Escribe un mensaje…" autocomplete="off">
                <button class="btn btn-primary" type="submit">Enviar</button>
              </form>
            </div>`}
        </section>
      </div>`;
    root.querySelectorAll('[data-ch]').forEach((b) => b.addEventListener('click', () => {
      state.omniChannel = b.dataset.ch;
      save(state); renderOmni(root);
    }));
    root.querySelectorAll('[data-id]').forEach((b) => b.addEventListener('click', () => {
      const id = Number(b.dataset.id);
      const conv = state.convs.find((c) => c.id === id);
      if (conv) conv.unread = 0;
      state.omniSelected = id;
      save(state); updateBadge(); renderOmni(root);
    }));
    $('#takeBtn', root)?.addEventListener('click', () => {
      if (!sel) return;
      sel.handling = sel.handling === 'ai' ? 'human' : 'ai';
      save(state); toast(sel.handling === 'human' ? 'Conversación tomada' : 'Devuelta a IA'); renderOmni(root);
    });
    $('#omniSend', root)?.addEventListener('submit', (e) => {
      e.preventDefault();
      const input = $('#omniInput', root);
      const text = (input.value || '').trim();
      if (!text || !sel) return;
      state.messages[sel.id] = state.messages[sel.id] || [];
      state.messages[sel.id].push({ from: 'agent', text, time: nowTime() });
      sel.preview = text; sel.time = nowTime(); sel.handling = 'human'; sel.unread = 0;
      save(state);
      renderOmni(root);
      setTimeout(() => {
        state.messages[sel.id].push({ from: 'ai', text: 'Recibido. En modo Prueba Arya confirma el mensaje y deja el hilo listo para seguimiento.', time: nowTime() });
        sel.preview = 'Recibido. En modo Prueba Arya confirma…';
        save(state); renderOmni(root);
      }, 700);
    });
  };

  const renderClients = (root, id) => {
    if (id) {
      const c = state.clients.find((x) => String(x.id) === String(id));
      if (!c) { location.hash = '#/clientes'; return; }
      root.innerHTML = `
        <div style="margin-bottom:12px"><a class="btn btn-ghost btn-sm" href="#/clientes">← Volver a clientes</a>
          <a class="btn btn-primary btn-sm" href="#/omnicanalidad" style="margin-left:8px">Abrir chat</a></div>
        <div class="panel" style="margin-bottom:16px"><div class="panel-body">
          <h2>${esc(c.name)}</h2>
          <p style="color:var(--text-secondary)">${esc(c.company)} · ${esc(c.email)}</p>
          ${c.tags.map((t) => `<span class="badge badge-gold">${esc(t)}</span>`).join(' ')} ${badgeCh(c.channel)}
        </div></div>
        <form class="panel" id="editClient"><div class="panel-header"><h3>Datos del cliente</h3></div>
          <div class="panel-body grid grid-2" style="gap:12px">
            <div class="form-group"><label class="form-label">Nombre</label><input class="form-control" name="name" value="${esc(c.name)}" required></div>
            <div class="form-group"><label class="form-label">Empresa</label><input class="form-control" name="company" value="${esc(c.company)}"></div>
            <div class="form-group"><label class="form-label">Correo</label><input class="form-control" name="email" value="${esc(c.email)}"></div>
            <div class="form-group"><label class="form-label">Teléfono</label><input class="form-control" name="phone" value="${esc(c.phone)}"></div>
            <div class="form-group"><label class="form-label">Ciudad</label><input class="form-control" name="city" value="${esc(c.city||'')}"></div>
            <div class="form-group"><label class="form-label">Estado</label>
              <select class="form-control" name="status">
                ${['prospecto','activo','inactivo'].map((s) => `<option ${c.status===s?'selected':''}>${s}</option>`).join('')}
              </select></div>
          </div>
          <div class="panel-body"><button class="btn btn-primary" type="submit">Guardar cambios</button></div>
        </form>`;
      $('#editClient', root).addEventListener('submit', (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        Object.assign(c, Object.fromEntries(fd.entries()));
        save(state); toast('Cliente actualizado');
      });
      return;
    }
    root.innerHTML = `
      <div class="clients-toolbar">
        <form class="clients-filters" id="clientFilter">
          <div class="form-group" style="margin:0;flex:1"><label class="form-label">Nombre</label><input class="form-control" name="q" placeholder="Buscar"></div>
          <div class="form-group" style="margin:0"><label class="form-label">Canal</label>
            <select class="form-control" name="channel"><option value="">Todos</option><option value="whatsapp">WhatsApp</option><option value="instagram">Instagram</option><option value="messenger">Messenger</option><option value="web">Web Chat</option></select></div>
          <button class="btn btn-primary btn-sm" type="submit">Filtrar</button>
        </form>
        <div class="clients-toolbar-right"><span class="badge badge-cyan" id="cliCount">${state.clients.length} contactos</span>
          <button type="button" class="btn btn-gold" id="btnNewClient">+ Nuevo cliente</button></div>
      </div>
      <div class="panel" id="newClientPanel" hidden>
        <div class="panel-header"><h3>Crear cliente</h3></div>
        <form class="panel-body" id="newClientForm">
          <div class="grid grid-2" style="gap:12px">
            <div class="form-group"><label class="form-label">Nombre *</label><input class="form-control" name="name" required></div>
            <div class="form-group"><label class="form-label">Teléfono</label><input class="form-control" name="phone"></div>
            <div class="form-group"><label class="form-label">Correo</label><input class="form-control" name="email"></div>
            <div class="form-group"><label class="form-label">Empresa</label><input class="form-control" name="company"></div>
            <div class="form-group"><label class="form-label">Canal</label><select class="form-control" name="channel"><option value="whatsapp">WhatsApp</option><option value="instagram">Instagram</option><option value="messenger">Messenger</option><option value="web">Web Chat</option></select></div>
            <div class="form-group"><label class="form-label">Estado</label><select class="form-control" name="status"><option value="prospecto">Prospecto</option><option value="activo">Activo</option></select></div>
          </div>
          <div style="margin-top:12px;display:flex;gap:8px;justify-content:flex-end">
            <button type="button" class="btn btn-ghost" id="btnCancelNew">Cancelar</button>
            <button class="btn btn-primary" type="submit">Crear cliente</button>
          </div>
        </form>
      </div>
      <div class="panel"><div class="table-wrap"><table class="data-table"><thead><tr><th>Cliente</th><th>Origen</th><th>Canal</th><th>Último contacto</th><th>Estado</th><th></th></tr></thead>
        <tbody id="clientRows"></tbody></table></div></div>`;
    const paint = (rows) => {
      $('#cliCount', root).textContent = `${rows.length} contacto${rows.length===1?'':'s'}`;
      $('#clientRows', root).innerHTML = rows.map((c) => `
        <tr>
          <td><div style="display:flex;align-items:center;gap:12px"><div class="client-avatar">${esc(c.name[0]||'C')}</div><div><div style="font-weight:600">${esc(c.name)}</div><div style="font-size:.78rem;color:var(--text-muted)">${esc(c.email||c.phone)}</div></div></div></td>
          <td>${esc(c.company)}</td><td>${badgeCh(c.channel)}</td><td>${esc(c.last_contact)}</td>
          <td><span class="badge badge-${c.status==='activo'?'success':c.status==='prospecto'?'warning':'muted'}">${esc(c.status)}</span></td>
          <td class="client-actions"><a class="btn btn-client-view btn-sm" href="#/clientes/${c.id}">Ver cliente</a></td>
        </tr>`).join('');
    };
    paint(state.clients);
    $('#btnNewClient', root).onclick = () => { $('#newClientPanel', root).hidden = false; };
    $('#btnCancelNew', root).onclick = () => { $('#newClientPanel', root).hidden = true; };
    $('#clientFilter', root).onsubmit = (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      const q = String(fd.get('q') || '').toLowerCase();
      const channel = String(fd.get('channel') || '');
      paint(state.clients.filter((c) => (!q || c.name.toLowerCase().includes(q)) && (!channel || c.channel === channel)));
    };
    $('#newClientForm', root).onsubmit = (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      const client = Object.fromEntries(fd.entries());
      client.id = Date.now();
      client.tags = ['Nuevo'];
      client.last_contact = 'Ahora';
      client.city = '';
      state.clients.unshift(client);
      save(state); toast('Cliente creado'); location.hash = '#/clientes/' + client.id;
    };
  };

  const renderReports = (root) => {
    root.innerHTML = `
      <div style="display:flex;justify-content:space-between;margin-bottom:20px;gap:12px;flex-wrap:wrap">
        <p style="color:var(--text-secondary)">KPIs, interacción y exportación de reportes.</p>
        <button class="btn btn-gold" id="exportBtn">Exportar CSV</button>
      </div>
      <div class="grid grid-4" style="margin-bottom:20px">
        <div class="panel kpi-card"><div class="kpi-label">Conversaciones</div><div class="kpi-value">1248</div><div class="kpi-meta">1089 resueltas</div></div>
        <div class="panel kpi-card"><div class="kpi-label">Mensajes enviados</div><div class="kpi-value">8934</div></div>
        <div class="panel kpi-card"><div class="kpi-label">Tiempo respuesta</div><div class="kpi-value" style="font-size:1.4rem">2.4 min</div></div>
        <div class="panel kpi-card"><div class="kpi-label">Clientes nuevos</div><div class="kpi-value">86</div></div>
      </div>
      <div class="grid grid-2"><div class="panel"><div class="panel-header"><h2>Por canal</h2></div><div class="panel-body"><div class="chart-box"><canvas id="reportLine"></canvas></div></div></div>
        <div class="panel"><div class="panel-header"><h2>Inbox por red</h2></div><div class="panel-body"><div class="chart-box"><canvas id="reportDonut"></canvas></div></div></div></div>`;
    $('#exportBtn', root).onclick = () => {
      const csv = 'cliente,canal,estado\n' + state.clients.map((c) => `${c.name},${c.channel},${c.status}`).join('\n');
      const a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
      a.download = 'arya-prueba-informe.csv';
      a.click();
      toast('CSV descargado');
    };
    if (window.Chart) {
      charts.push(new Chart($('#reportLine'), {
        type: 'bar',
        data: { labels: ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'], datasets: [
          { label: 'WhatsApp', data: [42,55,48,61,58,30,22], backgroundColor: 'rgba(37,211,102,.7)' },
          { label: 'Instagram', data: [18,22,25,20,28,35,40], backgroundColor: 'rgba(228,64,95,.7)' },
        ] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { color: '#8892B0' } } } },
      }));
      charts.push(new Chart($('#reportDonut'), {
        type: 'doughnut',
        data: { labels: ['WhatsApp','Instagram','Messenger','Web'], datasets: [{ data: [5,3,2,1], backgroundColor: ['#25D366','#E4405F','#0084FF','#00D4E8'], borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { labels: { color: '#8892B0' } } } },
      }));
    }
  };

  const renderAgenda = (root) => {
    root.innerHTML = `
      <div class="panel" style="margin-bottom:16px"><div class="panel-header"><h2>Nueva cita</h2></div>
        <form class="panel-body grid grid-2" id="newAppt" style="gap:12px">
          <div class="form-group"><label class="form-label">Título</label><input class="form-control" name="title" required placeholder="Demo Arya"></div>
          <div class="form-group"><label class="form-label">Persona</label><input class="form-control" name="person" required placeholder="Cliente"></div>
          <div class="form-group"><label class="form-label">Cuándo</label><input class="form-control" name="when" required placeholder="Hoy 16:00"></div>
          <div class="form-group" style="align-self:end"><button class="btn btn-primary" type="submit">Agendar</button></div>
        </form></div>
      <div class="panel"><div class="panel-header"><h2>Citas</h2></div><div class="panel-body" id="apptList"></div></div>`;
    const paint = () => {
      $('#apptList', root).innerHTML = state.agenda.map((a) => `
        <div class="agenda-item"><div><strong>${esc(a.title)}</strong><div style="color:var(--text-secondary)">${esc(a.person)} · ${esc(a.when)}</div></div>
          <div><span class="badge ${a.status==='confirmada'?'badge-success':'badge-warning'}">${esc(a.status)}</span>
          <button class="btn btn-ghost btn-sm" data-del="${a.id}">Quitar</button></div></div>`).join('');
      root.querySelectorAll('[data-del]').forEach((b) => b.onclick = () => {
        state.agenda = state.agenda.filter((x) => x.id !== Number(b.dataset.del));
        save(state); paint();
      });
    };
    paint();
    $('#newAppt', root).onsubmit = (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      state.agenda.unshift({ id: Date.now(), title: fd.get('title'), person: fd.get('person'), when: fd.get('when'), status: 'confirmada' });
      save(state); e.target.reset(); toast('Cita agendada'); paint();
    };
  };

  const renderTasks = (root) => {
    root.innerHTML = `
      <div style="display:flex;justify-content:space-between;margin-bottom:16px;gap:8px;flex-wrap:wrap">
        <p><code>https://n8n.prueba.local</code> <span class="badge badge-success">Activo</span></p>
        <button class="btn btn-sm" id="reloadTasks">Recargar</button>
      </div>
      <div class="panel"><div class="panel-body" id="taskList"></div></div>`;
    const paint = () => {
      $('#taskList', root).innerHTML = state.tasks.map((t) => `
        <div class="task-row">
          <div><strong>${esc(t.name)}</strong><div class="dash-list-meta"><code>${esc(t.workflow)}</code> · ${esc(t.next)} · ${t.success}%</div></div>
          <div><span class="badge badge-${t.status==='activo'?'success':t.status==='error'?'danger':'warning'}">${esc(t.status)}</span>
            <button class="btn btn-ghost btn-sm" data-toggle="${t.id}">${t.status==='activo'?'Pausar':'Activar'}</button></div>
        </div>`).join('');
      root.querySelectorAll('[data-toggle]').forEach((b) => b.onclick = () => {
        const t = state.tasks.find((x) => x.id === Number(b.dataset.toggle));
        if (!t) return;
        t.status = t.status === 'activo' ? 'pausado' : 'activo';
        save(state); toast('Workflow actualizado'); paint();
      });
    };
    paint();
    $('#reloadTasks', root).onclick = () => toast('Workflows recargados desde n8n (simulado)');
  };

  const renderGpt = (root) => {
    let chat = state.gpt.find((c) => c.id === state.gptActive) || state.gpt[0];
    root.innerHTML = `
      <div class="cgpt-shell">
        <aside class="cgpt-rail">
          <div class="cgpt-rail-top"><div class="cgpt-brand"><div class="cgpt-brand-text"><strong>Arya GPT</strong><small>Modo Prueba</small></div></div>
            <button class="btn btn-primary btn-sm" id="newGpt">Nuevo chat</button></div>
          <div class="cgpt-rail-section" id="gptList"></div>
        </aside>
        <section style="display:flex;flex-direction:column;min-height:60vh">
          <div class="chat-messages" id="gptMsgs" style="flex:1"></div>
          <form class="chat-composer" id="gptForm">
            <input class="form-control" id="gptInput" placeholder="Pregunta a Arya GPT…">
            <button class="btn btn-primary" type="submit">Enviar</button>
          </form>
        </section>
      </div>`;
    const paint = () => {
      chat = state.gpt.find((c) => c.id === state.gptActive) || state.gpt[0];
      $('#gptList', root).innerHTML = state.gpt.map((c) => `<button type="button" class="nav-item ${c.id===chat.id?'active':''}" data-gpt="${c.id}">${esc(c.title)}</button>`).join('');
      $('#gptMsgs', root).innerHTML = chat.messages.map((m) => `<div class="msg msg-${m.role==='user'?'client':'ai'}"><div class="msg-text">${esc(m.content)}</div></div>`).join('');
      root.querySelectorAll('[data-gpt]').forEach((b) => b.onclick = () => { state.gptActive = Number(b.dataset.gpt); save(state); paint(); });
    };
    paint();
    $('#newGpt', root).onclick = () => {
      const c = { id: Date.now(), title: 'Nuevo chat', messages: [] };
      state.gpt.unshift(c); state.gptActive = c.id; save(state); paint();
    };
    $('#gptForm', root).onsubmit = (e) => {
      e.preventDefault();
      const input = $('#gptInput', root);
      const q = input.value.trim();
      if (!q) return;
      chat.messages.push({ role: 'user', content: q });
      if (chat.title === 'Nuevo chat') chat.title = q.slice(0, 28);
      input.value = '';
      save(state); paint();
      setTimeout(() => {
        chat.messages.push({ role: 'assistant', content: gptReply(q) });
        save(state); paint();
      }, 500);
    };
  };

  const renderGen = (root) => {
    root.innerHTML = `
      <div class="grid grid-4" style="margin-bottom:20px">
        <div class="panel kpi-card"><div class="kpi-label">Generados</div><div class="kpi-value">${state.generated.length}</div></div>
        <div class="panel kpi-card"><div class="kpi-label">Hoy</div><div class="kpi-value">${state.generated.length}</div></div>
        <div class="panel kpi-card"><div class="kpi-label">Historial</div><div class="kpi-value" style="font-size:1.1rem">Local</div></div>
        <div class="panel kpi-card"><div class="kpi-label">Modo</div><div class="kpi-value" style="font-size:1.1rem">Prueba</div></div>
      </div>
      <div class="grid grid-2">
        <div class="panel"><div class="panel-header"><h2>Crear contenido</h2><span class="badge badge-gold">IA · Demo</span></div>
          <form class="panel-body" id="genForm">
            <div class="form-group"><label class="form-label">Plataforma</label><select class="form-control" name="platform"><option value="instagram">Instagram</option><option value="facebook">Facebook</option><option value="whatsapp">WhatsApp</option><option value="linkedin">LinkedIn</option></select></div>
            <div class="form-group"><label class="form-label">Tono</label><select class="form-control" name="tone"><option value="profesional">Profesional</option><option value="cercano">Cercano</option><option value="urgente">Urgente</option></select></div>
            <div class="form-group"><label class="form-label">Tema</label><input class="form-control" name="topic" required placeholder="Lanzamiento plan premium"></div>
            <div class="form-group"><label class="form-label">CTA</label><input class="form-control" name="cta" placeholder="Agenda tu demo hoy"></div>
            <button class="btn btn-primary btn-block" type="submit">Generar contenido</button>
          </form></div>
        <div class="panel"><div class="panel-header"><h2>Resultado</h2></div><div class="panel-body" id="genOut">${state.generated[0] ? `<p>${esc(state.generated[0].text)}</p>` : '<p class="dash-empty">Genera el primero.</p>'}</div></div>
      </div>`;
    $('#genForm', root).onsubmit = (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      const topic = fd.get('topic'); const cta = fd.get('cta') || 'Escríbenos hoy';
      const text = `${topic}: Arya concentra WhatsApp, Instagram y Messenger en un solo CRM. ${cta}.`;
      state.generated.unshift({ topic, platform: fd.get('platform'), tone: fd.get('tone'), text, time: nowTime() });
      save(state);
      $('#genOut', root).innerHTML = `<span class="badge badge-cyan">${esc(fd.get('platform'))}</span> <span class="badge badge-gold">${esc(fd.get('tone'))}</span><p style="margin-top:12px">${esc(text)}</p>`;
      toast('Contenido generado');
    };
  };

  const renderSettings = (root) => {
    root.innerHTML = `
      <div class="settings-tabs">
        <button class="settings-tab active" data-tab="accounts">Cuentas Meta</button>
        <button class="settings-tab" data-tab="integrations">Integraciones</button>
      </div>
      <div id="setPanes">
        <div class="panel" data-pane="accounts">
          <div class="panel-header"><h2>Cuentas conectadas</h2></div>
          <div class="panel-body">
            <p>WhatsApp Business · +57 300 000 0000 <span class="badge badge-success">conectado</span></p>
            <p>Instagram · @arya.crm <span class="badge badge-success">conectado</span></p>
            <p>Facebook Page · Arya Oficial <span class="badge badge-success">conectado</span></p>
          </div>
        </div>
        <div class="panel" data-pane="integrations" hidden>
          <div class="panel-header"><h2>Integraciones</h2></div>
          <div class="panel-body">
            <p>n8n <button class="btn btn-ghost btn-sm" data-int="n8n">${state.settings.n8n?'Desactivar':'Activar'}</button> <span class="badge ${state.settings.n8n?'badge-success':'badge-muted'}">${state.settings.n8n?'activo':'inactivo'}</span></p>
            <p>OpenAI <button class="btn btn-ghost btn-sm" data-int="openai">${state.settings.openai?'Desactivar':'Activar'}</button> <span class="badge ${state.settings.openai?'badge-success':'badge-muted'}">${state.settings.openai?'activo':'inactivo'}</span></p>
            <p>Meta <button class="btn btn-ghost btn-sm" data-int="meta">${state.settings.meta?'Desactivar':'Activar'}</button> <span class="badge ${state.settings.meta?'badge-success':'badge-muted'}">${state.settings.meta?'activo':'inactivo'}</span></p>
          </div>
        </div>
      </div>`;
    root.querySelectorAll('.settings-tab').forEach((t) => t.onclick = () => {
      root.querySelectorAll('.settings-tab').forEach((x) => x.classList.toggle('active', x === t));
      root.querySelectorAll('[data-pane]').forEach((p) => { p.hidden = p.dataset.pane !== t.dataset.tab; });
    });
    root.querySelectorAll('[data-int]').forEach((b) => b.onclick = () => {
      const k = b.dataset.int;
      state.settings[k] = !state.settings[k];
      save(state); toast('Integración actualizada'); renderSettings(root);
    });
  };

  const renderers = {
    dashboard: renderDashboard,
    omnicanalidad: renderOmni,
    clientes: renderClients,
    informes: renderReports,
    agenda: renderAgenda,
    tareas: renderTasks,
    chatgpt: renderGpt,
    generador: renderGen,
    configuracion: renderSettings,
  };

  const route = () => {
    if (!logged()) return;
    const { mod, id } = parseHash();
    setNav(mod);
    updateBadge();
    killCharts();
    const root = $('#content');
    if (!root) return;
    root.innerHTML = '';
    renderers[mod](root, id);
  };

  document.addEventListener('DOMContentLoaded', () => {
    $('#pruebaLoginForm')?.addEventListener('submit', (e) => {
      e.preventDefault();
      localStorage.setItem(SESSION, '1');
      location.hash = '#/dashboard';
      showViews();
      toast('Sesión Prueba iniciada');
    });
    $('#logoutBtn')?.addEventListener('click', () => {
      localStorage.removeItem(SESSION);
      location.hash = '';
      showViews();
    });
    window.addEventListener('hashchange', route);
    document.querySelectorAll('.nav-group-toggle').forEach((btn) => {
      btn.addEventListener('click', () => {
        const group = btn.closest('.nav-group');
        group?.classList.toggle('is-open');
      });
    });
    showViews();
  });
})();
