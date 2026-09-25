(function () {
  'use strict';

  var bound = false;

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function nl2br(s) {
    return esc(s).replace(/\n/g, '<br>');
  }

  function root() {
    return document.getElementById('cgptRoot');
  }

  function state() {
    var r = root();
    if (!r) return null;
    return {
      r: r,
      sendUrl: r.dataset.sendUrl,
      newUrl: r.dataset.newUrl,
      configUrl: r.dataset.configUrl,
      showBase: (r.dataset.showBase || '').replace(/\/$/, ''),
      renameBase: (r.dataset.renameBase || r.dataset.showBase || '').replace(/\/$/, ''),
      deleteBase: (r.dataset.deleteBase || '').replace(/\/$/, ''),
      csrf: r.dataset.csrf,
      chatId: parseInt(r.dataset.chatId || '0', 10) || 0,
      configured: r.dataset.configured === '1'
    };
  }

  var ACCORDION_KEY = 'arya.cgpt.accordion';
  var RAIL_KEY = 'arya.cgpt.railCollapsed';

  var busy = false;
  var thinkingTimer = null;
  var thinkingIdx = 0;
  var THINKING_PHASES = [
    'Pensando',
    'Analizando tu mensaje',
    'Preparando la respuesta',
    'Analizando respuesta',
    'Casi listo'
  ];

  function els() {
    return {
      form: document.getElementById('cgptForm'),
      input: document.getElementById('cgptInput'),
      messagesEl: document.getElementById('cgptMessages'),
      welcomeEl: document.getElementById('cgptWelcome'),
      listEl: document.getElementById('cgptChatList'),
      titleEl: document.getElementById('cgptTitle'),
      modelEl: document.getElementById('cgptModel'),
      modelHidden: document.getElementById('cgptModelHidden'),
      convHidden: document.getElementById('cgptConversationId'),
      sendBtn: document.getElementById('cgptSendBtn'),
      modal: document.getElementById('cgptConfigModal'),
      configForm: document.getElementById('cgptConfigForm'),
      statusPill: document.getElementById('cgptStatusPill'),
      banner: document.getElementById('cgptBanner')
    };
  }

  function currentModel() {
    var e = els();
    return e.modelEl ? e.modelEl.value : ((root() && root().dataset.model) || 'gpt-5.6');
  }

  function syncModelHidden() {
    var e = els();
    if (e.modelHidden) e.modelHidden.value = currentModel();
  }

  function setBusy(on) {
    busy = !!on;
    var e = els();
    var st = state();
    var configured = st ? st.configured : false;
    if (e.sendBtn) e.sendBtn.disabled = on || !configured;
    if (e.input) e.input.disabled = on || !configured;
  }

  function scrollBottom() {
    var thread = document.getElementById('cgptThread');
    if (thread) thread.scrollTop = thread.scrollHeight;
  }

  function showWelcome(show) {
    var e = els();
    if (!e.welcomeEl) return;
    e.welcomeEl.classList.toggle('is-hidden', !show);
  }

  function renderMessages(messages) {
    var e = els();
    if (!e.messagesEl) return;
    messages = messages || [];
    showWelcome(messages.length === 0);
    e.messagesEl.innerHTML = messages.map(function (m) {
      var role = m.role === 'assistant' ? 'assistant' : 'user';
      return '<div class="cgpt-bubble cgpt-bubble-' + role + '"><div class="cgpt-bubble-inner">' +
        nl2br(m.content || '') + '</div></div>';
    }).join('');
    scrollBottom();
  }

  function stopThinkingTimer() {
    if (thinkingTimer) {
      clearInterval(thinkingTimer);
      thinkingTimer = null;
    }
    thinkingIdx = 0;
  }

  function thinkingLabel(phase) {
    return String(phase || THINKING_PHASES[0]);
  }

  function renderTyping() {
    var e = els();
    if (!e.messagesEl) return;
    stopThinkingTimer();
    showWelcome(false);

    var existing = document.getElementById('cgptTyping');
    if (existing) existing.remove();

    var el = document.createElement('div');
    el.className = 'cgpt-bubble cgpt-bubble-assistant is-typing';
    el.id = 'cgptTyping';
    el.setAttribute('aria-live', 'polite');
    el.setAttribute('aria-busy', 'true');
    el.innerHTML =
      '<div class="cgpt-bubble-inner cgpt-thinking">' +
        '<span class="cgpt-thinking-dots" aria-hidden="true"><i></i><i></i><i></i></span>' +
        '<span class="cgpt-thinking-text">' + esc(thinkingLabel(THINKING_PHASES[0])) + '</span>' +
        '<span class="cgpt-thinking-ellipsis" aria-hidden="true"></span>' +
      '</div>';
    e.messagesEl.appendChild(el);
    scrollBottom();

    // Rota el estado automáticamente mientras OpenAI responde (producción / latencia alta).
    thinkingTimer = setInterval(function () {
      var node = document.getElementById('cgptTyping');
      if (!node) {
        stopThinkingTimer();
        return;
      }
      thinkingIdx = (thinkingIdx + 1) % THINKING_PHASES.length;
      var text = node.querySelector('.cgpt-thinking-text');
      if (text) text.textContent = thinkingLabel(THINKING_PHASES[thinkingIdx]);
    }, 2200);
  }

  function clearTyping() {
    stopThinkingTimer();
    var t = document.getElementById('cgptTyping');
    if (t) t.remove();
  }

  function startOfDay(d) {
    return new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime();
  }

  function chatBucket(updatedAt) {
    var ts = Date.parse(updatedAt || '');
    if (!ts) return 'anteriores';
    var now = new Date();
    var day = startOfDay(now);
    var chatDay = startOfDay(new Date(ts));
    var diffDays = Math.round((day - chatDay) / 86400000);
    if (diffDays <= 0) return 'hoy';
    if (diffDays === 1) return 'ayer';
    if (diffDays <= 7) return 'semana';
    return 'anteriores';
  }

  function loadAccordionState() {
    try {
      var raw = localStorage.getItem(ACCORDION_KEY);
      var parsed = raw ? JSON.parse(raw) : null;
      if (parsed && typeof parsed === 'object') return parsed;
    } catch (err) { /* ignore */ }
    return { hoy: true, ayer: true, semana: true, anteriores: false };
  }

  function saveAccordionState(stateMap) {
    try { localStorage.setItem(ACCORDION_KEY, JSON.stringify(stateMap)); } catch (err) { /* ignore */ }
  }

  function renderChatItem(c, activeId) {
    var id = parseInt(c.id, 10) || 0;
    var title = c.title || 'Nuevo chat';
    return '<div class="cgpt-chat-item' + (id === activeId ? ' is-active' : '') +
      '" data-chat-id="' + id + '" title="' + esc(title) + '">' +
      '<button type="button" class="cgpt-chat-item-main" data-open-chat="' + id + '">' +
        '<span class="cgpt-chat-item-title">' + esc(title) + '</span>' +
      '</button>' +
      '<button type="button" class="cgpt-chat-item-act" data-rename-chat="' + id + '" title="Renombrar" aria-label="Renombrar">✎</button>' +
      '<button type="button" class="cgpt-chat-item-act is-danger" data-del-chat="' + id + '" title="Eliminar" aria-label="Eliminar">✕</button>' +
    '</div>';
  }

  function renderConversations(items, activeId) {
    var e = els();
    if (!e.listEl) return;
    items = items || [];
    if (!items.length) {
      e.listEl.innerHTML = '<p class="cgpt-empty-list">Aún no hay conversaciones.</p>';
      return;
    }

    var groups = {
      hoy: { label: 'Hoy', items: [] },
      ayer: { label: 'Ayer', items: [] },
      semana: { label: 'Últimos 7 días', items: [] },
      anteriores: { label: 'Anteriores', items: [] }
    };

    items.forEach(function (c) {
      var key = chatBucket(c.updated_at || c.created_at);
      if (!groups[key]) key = 'anteriores';
      groups[key].items.push(c);
    });

    var openMap = loadAccordionState();
    // Abrir el grupo que contiene el chat activo
    Object.keys(groups).forEach(function (key) {
      if (groups[key].items.some(function (c) { return parseInt(c.id, 10) === activeId; })) {
        openMap[key] = true;
      }
    });

    var html = '';
    ['hoy', 'ayer', 'semana', 'anteriores'].forEach(function (key) {
      var g = groups[key];
      if (!g.items.length) return;
      var open = openMap[key] !== false;
      html +=
        '<details class="cgpt-acc" data-acc-key="' + key + '"' + (open ? ' open' : '') + '>' +
          '<summary class="cgpt-acc-summary">' +
            '<span>' + esc(g.label) + '</span>' +
            '<span class="cgpt-acc-count">' + g.items.length + '</span>' +
          '</summary>' +
          '<div class="cgpt-acc-body">' +
            g.items.map(function (c) { return renderChatItem(c, activeId); }).join('') +
          '</div>' +
        '</details>';
    });

    e.listEl.innerHTML = html || '<p class="cgpt-empty-list">Aún no hay conversaciones.</p>';
  }

  async function api(url, options) {
    var st = state();
    options = options || {};
    options.credentials = 'same-origin';
    options.redirect = 'follow';
    options.headers = Object.assign({
      'Accept': 'application/json',
      'X-Requested-With': 'fetch',
      'X-CSRF-TOKEN': st ? st.csrf : ''
    }, options.headers || {});
    var res = await fetch(url, options);
    var ct = (res.headers.get('content-type') || '');
    if (!ct.includes('application/json')) {
      throw new Error('El servidor no devolvió JSON (¿sesión o permisos?). Recarga la página.');
    }
    var data = await res.json();
    if (!data || typeof data !== 'object') throw new Error('Respuesta inválida');
    return data;
  }

  function setChatId(id) {
    var r = root();
    var e = els();
    id = parseInt(id, 10) || 0;
    if (r) r.dataset.chatId = String(id);
    if (e.convHidden) e.convHidden.value = id > 0 ? String(id) : '';
  }

  async function loadChat(id) {
    var st = state();
    var e = els();
    if (!st) return;
    if (!id) {
      setChatId(0);
      if (e.titleEl) e.titleEl.textContent = 'Chat GPT';
      renderMessages([]);
      renderConversations((window.ARYA_CHATGPT && window.ARYA_CHATGPT.conversations) || [], 0);
      return;
    }
    var data = await api(st.showBase + '/' + encodeURIComponent(id), { method: 'GET' });
    if (!data.ok) throw new Error(data.message || 'No se pudo abrir el chat');
    setChatId(data.chat && data.chat.id ? data.chat.id : id);
    if (e.titleEl) e.titleEl.textContent = (data.chat && data.chat.title) || 'Chat GPT';
    if (e.modelEl && data.chat && data.chat.model) e.modelEl.value = data.chat.model;
    syncModelHidden();
    renderMessages(data.messages || []);
    if (data.conversations) {
      window.ARYA_CHATGPT = window.ARYA_CHATGPT || {};
      window.ARYA_CHATGPT.conversations = data.conversations;
      renderConversations(data.conversations, parseInt(root().dataset.chatId, 10) || 0);
    }
  }

  async function newChat() {
    var st = state();
    if (!st || busy) return;
    setBusy(true);
    try {
      var fd = new FormData();
      fd.append('_csrf', st.csrf);
      fd.append('model', currentModel());
      var data = await api(st.newUrl, { method: 'POST', body: fd });
      if (!data.ok) throw new Error(data.message || 'No se pudo crear');
      setChatId(data.conversation_id || 0);
      var e = els();
      if (e.titleEl) e.titleEl.textContent = 'Nuevo chat';
      renderMessages([]);
      if (data.conversations) {
        window.ARYA_CHATGPT = window.ARYA_CHATGPT || {};
        window.ARYA_CHATGPT.conversations = data.conversations;
        renderConversations(data.conversations, parseInt(root().dataset.chatId, 10) || 0);
      }
      if (e.input) e.input.focus();
    } catch (err) {
      alert(err.message || 'Error al crear chat');
    } finally {
      setBusy(false);
    }
  }

  async function renameChat(id) {
    var st = state();
    if (!st || !id) return;
    var list = (window.ARYA_CHATGPT && window.ARYA_CHATGPT.conversations) || [];
    var current = list.find(function (c) { return parseInt(c.id, 10) === id; });
    var prev = current ? String(current.title || 'Nuevo chat') : 'Nuevo chat';
    var next = window.prompt('Nuevo nombre del chat:', prev);
    if (next === null) return;
    next = String(next).trim();
    if (next === '') {
      alert('El nombre no puede estar vacío.');
      return;
    }
    if (next === prev) return;

    var fd = new FormData();
    fd.append('_csrf', st.csrf);
    fd.append('title', next);
    try {
      var data = await api(st.renameBase + '/' + encodeURIComponent(id) + '/renombrar', { method: 'POST', body: fd });
      if (!data.ok) {
        alert(data.message || 'No se pudo renombrar');
        return;
      }
      window.ARYA_CHATGPT = window.ARYA_CHATGPT || {};
      window.ARYA_CHATGPT.conversations = data.conversations || [];
      var activeId = parseInt(root().dataset.chatId, 10) || 0;
      renderConversations(data.conversations || [], activeId);
      if (activeId === id) {
        var e = els();
        if (e.titleEl) e.titleEl.textContent = (data.chat && data.chat.title) || next;
      }
    } catch (err) {
      alert(err.message || 'No se pudo renombrar');
    }
  }

  async function deleteChat(id) {
    var st = state();
    if (!st || !id || !confirm('¿Eliminar este chat?')) return;
    var fd = new FormData();
    fd.append('_csrf', st.csrf);
    var data = await api(st.deleteBase + '/' + encodeURIComponent(id) + '/eliminar', { method: 'POST', body: fd });
    if (!data.ok) {
      alert(data.message || 'No se pudo eliminar');
      return;
    }
    var cur = parseInt(root().dataset.chatId, 10) || 0;
    if (cur === id) {
      setChatId(0);
      var e = els();
      if (e.titleEl) e.titleEl.textContent = 'Chat GPT';
      renderMessages([]);
    }
    window.ARYA_CHATGPT = window.ARYA_CHATGPT || {};
    window.ARYA_CHATGPT.conversations = data.conversations || [];
    renderConversations(data.conversations || [], parseInt(root().dataset.chatId, 10) || 0);
  }

  async function sendMessage(text) {
    var st = state();
    var e = els();
    text = String(text || '').trim();
    if (!text || busy || !st || !st.configured) return;
    setBusy(true);
    syncModelHidden();

    var current = [];
    if (e.messagesEl) {
      e.messagesEl.querySelectorAll('.cgpt-bubble').forEach(function (b) {
        if (b.id === 'cgptTyping') return;
        var role = b.classList.contains('cgpt-bubble-assistant') ? 'assistant' : 'user';
        var content = b.querySelector('.cgpt-bubble-inner');
        current.push({ role: role, content: content ? content.innerText : '' });
      });
    }
    current.push({ role: 'user', content: text });
    renderMessages(current);
    renderTyping();

    try {
      var fd = new FormData();
      fd.append('_csrf', st.csrf);
      fd.append('message', text);
      fd.append('model', currentModel());
      var cid = parseInt(root().dataset.chatId, 10) || 0;
      if (cid > 0) fd.append('conversation_id', String(cid));

      var data = await api(st.sendUrl, { method: 'POST', body: fd });
      clearTyping();

      if (data.conversation_id) setChatId(data.conversation_id);
      if (data.messages) renderMessages(data.messages);
      else if (data.ok && data.reply) {
        current.push({ role: 'assistant', content: data.reply });
        renderMessages(current);
      }

      if (data.conversations) {
        window.ARYA_CHATGPT = window.ARYA_CHATGPT || {};
        window.ARYA_CHATGPT.conversations = data.conversations;
        renderConversations(data.conversations, parseInt(root().dataset.chatId, 10) || 0);
      }
      if (data.chat && e.titleEl) e.titleEl.textContent = data.chat.title || e.titleEl.textContent;

      if (!data.ok && e.messagesEl) {
        var errBubble = document.createElement('div');
        errBubble.className = 'cgpt-bubble cgpt-bubble-assistant';
        errBubble.innerHTML = '<div class="cgpt-bubble-inner" style="color:#ff9b9b">' +
          esc(data.message || 'Error de OpenAI') + '</div>';
        e.messagesEl.appendChild(errBubble);
        scrollBottom();
      }
    } catch (err) {
      clearTyping();
      alert(err.message || 'No se pudo enviar');
    } finally {
      setBusy(false);
      if (e.input) {
        e.input.value = '';
        e.input.style.height = 'auto';
        e.input.focus();
      }
    }
  }

  function openConfig() {
    var e = els();
    if (!e.modal) return;
    e.modal.hidden = false;
    document.body.style.overflow = 'hidden';
  }

  function closeConfig() {
    var e = els();
    if (!e.modal) return;
    e.modal.hidden = true;
    document.body.style.overflow = '';
  }

  async function saveConfig(ev) {
    if (ev) ev.preventDefault();
    var st = state();
    var e = els();
    if (!st || !e.configForm) return;
    var fd = new FormData(e.configForm);
    try {
      var data = await api(st.configUrl, { method: 'POST', body: fd });
      if (!data.ok) {
        alert(data.message || 'No se pudo guardar');
        return;
      }
      if (root()) root().dataset.configured = data.openaiStatus && data.openaiStatus.configured ? '1' : '0';
      if (e.statusPill) {
        e.statusPill.className = 'cgpt-status is-ok';
        e.statusPill.textContent = 'API OpenAI conectada';
        e.statusPill.removeAttribute('data-cgpt-config-open');
      }
      if (e.banner) e.banner.remove();
      if (e.input) e.input.disabled = false;
      if (e.sendBtn) e.sendBtn.disabled = false;
      closeConfig();
      alert(data.message || 'OpenAI guardado. Visible también en Integraciones.');
    } catch (err) {
      alert(err.message || 'Error al guardar');
    }
  }

  function onDocClick(ev) {
    if (!root()) return;
    var t = ev.target;
    if (t.closest('[data-cgpt-rail-toggle]')) {
      toggleRail(ev);
      return;
    }
    if (t.closest('[data-cgpt-config-open]')) {
      openConfig();
      return;
    }
    if (t.closest('[data-cgpt-config-close]')) {
      closeConfig();
      return;
    }
    if (t.closest('[data-cgpt-new]')) {
      newChat();
      return;
    }
    var suggest = t.closest('[data-suggest]');
    if (suggest) {
      var text = suggest.getAttribute('data-suggest') || '';
      var e = els();
      if (e.input) e.input.value = text;
      sendMessage(text);
      return;
    }
    var renameBtn = t.closest('[data-rename-chat]');
    if (renameBtn) {
      ev.preventDefault();
      ev.stopPropagation();
      renameChat(parseInt(renameBtn.getAttribute('data-rename-chat'), 10) || 0);
      return;
    }
    var del = t.closest('[data-del-chat]');
    if (del) {
      ev.preventDefault();
      ev.stopPropagation();
      deleteChat(parseInt(del.getAttribute('data-del-chat'), 10) || 0);
      return;
    }
    var openBtn = t.closest('[data-open-chat]');
    if (openBtn) {
      var id = parseInt(openBtn.getAttribute('data-open-chat'), 10) || 0;
      if (id) loadChat(id).catch(function (err) { alert(err.message || 'Error'); });
    }
  }

  function onAccordionToggle(ev) {
    var details = ev.target;
    if (!details || !details.classList || !details.classList.contains('cgpt-acc')) return;
    var key = details.getAttribute('data-acc-key');
    if (!key) return;
    var map = loadAccordionState();
    map[key] = !!details.open;
    saveAccordionState(map);
  }

  function isMobileRail() {
    return window.matchMedia && window.matchMedia('(max-width: 900px)').matches;
  }

  function loadRailCollapsed() {
    try { return localStorage.getItem(RAIL_KEY) === '1'; } catch (err) { return false; }
  }

  function saveRailCollapsed(collapsed) {
    try { localStorage.setItem(RAIL_KEY, collapsed ? '1' : '0'); } catch (err) { /* ignore */ }
  }

  function applyRailCollapsed(collapsed) {
    var r = root();
    if (!r) return;
    r.classList.toggle('is-rail-collapsed', !!collapsed);
    var toggle = r.querySelector('.cgpt-rail-toggle');
    var expandBtn = r.querySelector('.cgpt-rail-expand-btn');
    if (toggle) {
      toggle.setAttribute('title', collapsed ? 'Expandir panel' : 'Recoger panel');
      toggle.setAttribute('aria-label', collapsed ? 'Expandir panel de chats' : 'Recoger panel de chats');
      toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    }
    if (expandBtn) {
      if (isMobileRail()) {
        expandBtn.hidden = false;
        expandBtn.setAttribute('title', 'Mostrar chats');
        expandBtn.setAttribute('aria-label', 'Mostrar panel de chats');
      } else {
        expandBtn.hidden = !collapsed;
        expandBtn.setAttribute('title', 'Expandir panel');
        expandBtn.setAttribute('aria-label', 'Expandir panel de chats');
      }
    }
  }

  function toggleRail(ev) {
    if (ev) {
      ev.preventDefault();
      ev.stopPropagation();
    }
    var r = root();
    if (!r) return;
    if (isMobileRail()) {
      r.classList.toggle('is-rail-open');
      // En móvil siempre mostrar rail expandido al abrir
      if (r.classList.contains('is-rail-open')) {
        applyRailCollapsed(false);
      }
      return;
    }
    var next = !r.classList.contains('is-rail-collapsed');
    applyRailCollapsed(next);
    saveRailCollapsed(next);
  }

  function onSubmit(ev) {
    var form = ev.target;
    if (!form || form.id !== 'cgptForm') return;
    ev.preventDefault();
    ev.stopPropagation();
    var e = els();
    sendMessage(e.input ? e.input.value : '');
  }

  function onKeydown(ev) {
    if (!root()) return;
    if (ev.key === 'Escape') closeConfig();
    var e = els();
    if (ev.target === e.input && ev.key === 'Enter' && !ev.shiftKey) {
      ev.preventDefault();
      sendMessage(e.input.value);
    }
  }

  function onInput(ev) {
    if (ev.target && ev.target.id === 'cgptInput') {
      ev.target.style.height = 'auto';
      ev.target.style.height = Math.min(ev.target.scrollHeight, 160) + 'px';
    }
    if (ev.target && ev.target.id === 'cgptModel') syncModelHidden();
  }

  function onConfigSubmit(ev) {
    if (ev.target && ev.target.id === 'cgptConfigForm') {
      saveConfig(ev);
    }
  }

  function bindOnce() {
    if (bound) return;
    bound = true;
    document.addEventListener('click', onDocClick);
    document.addEventListener('submit', onSubmit, true);
    document.addEventListener('keydown', onKeydown);
    document.addEventListener('input', onInput);
    document.addEventListener('change', onInput);
    document.addEventListener('submit', onConfigSubmit, true);
    document.addEventListener('toggle', onAccordionToggle, true);
  }

  function init() {
    bindOnce();
    var r = root();
    if (!r) return;
    syncModelHidden();
    applyRailCollapsed(!isMobileRail() && loadRailCollapsed());
    var boot = window.ARYA_CHATGPT || {};
    var activeId = parseInt(r.dataset.chatId || boot.chatId || '0', 10) || 0;
    renderConversations(boot.conversations || [], activeId);
    if (boot.messages) showWelcome(!(boot.messages && boot.messages.length));
    scrollBottom();
    var e = els();
    var st = state();
    if (e.input && st && st.configured) e.input.focus();
    if (r.dataset.showConfig === '1') openConfig();
  }

  window.ARYA_CHATGPT_REINIT = init;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
