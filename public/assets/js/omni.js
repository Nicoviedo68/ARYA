window.ARYA_OMNI_REINIT = () => {
  const cfg = window.ARYA_OMNI;
  if (!cfg?.urls) return;
  if (!document.getElementById('omniApp')) return;

  const app = document.getElementById('omniApp');
  const convList = document.getElementById('omniConvList');
  const convCount = document.getElementById('omniConvCount');
  const collapseChannelsBtn = document.getElementById('omniCollapseChannels');
  const OMNI_CHANNELS_KEY = 'arya.omni.channelsCollapsed';
  const chatEmpty = document.getElementById('omniChatEmpty');
  const chatActive = document.getElementById('omniChatActive');
  const chatBox = document.getElementById('chatMessages');
  const chatName = document.getElementById('omniChatName');
  const chatMeta = document.getElementById('omniChatMeta');
  const chatStatus = document.getElementById('omniChatStatus');
  const kindBadgeEl = document.getElementById('omniKindBadge');
  const postCard = document.getElementById('omniPostCard');
  const postMedia = document.getElementById('omniPostMedia');
  const postCaption = document.getElementById('omniPostCaption');
  const postLink = document.getElementById('omniPostLink');
  const captionBlock = document.getElementById('omniCaptionBlock');
  const pageNameEl = document.getElementById('omniPageName');
  const igAvatar = document.getElementById('omniIgAvatar');
  const igFollowing = document.getElementById('omniIgFollowing');
  const igActions = document.getElementById('omniIgActions');
  const composeAvatar = document.getElementById('omniComposeAvatar');
  const composeTools = document.getElementById('chatComposeTools');
  const convIdInput = document.getElementById('omniConvId');
  const composeForm = document.getElementById('chatCompose');
  const chatMedia = document.getElementById('chatMedia');
  const chatPhotoInput = document.getElementById('chatPhotoInput');
  const chatVideoInput = document.getElementById('chatVideoInput');
  const chatDocInput = document.getElementById('chatDocInput');
  const chatFileName = document.getElementById('chatFileName');
  const chatAudioStatus = document.getElementById('chatAudioStatus');
  const chatAudioBtn = document.getElementById('chatAudioBtn');
  const chatPhotoBtn = document.getElementById('chatPhotoBtn');
  const chatVideoBtn = document.getElementById('chatVideoBtn');
  const chatDocBtn = document.getElementById('chatDocBtn');
  const chatEmojiBtn = document.getElementById('chatEmojiBtn');
  const chatAiBtn = document.getElementById('chatAiBtn');
  const chatTemplateBtn = document.getElementById('chatTemplateBtn');
  const chatEmojiPanel = document.getElementById('chatEmojiPanel');
  const chatEmojiGrid = document.getElementById('chatEmojiGrid');
  const chatTemplatePanel = document.getElementById('chatTemplatePanel');
  const chatMessage = document.getElementById('chatMessage');
  const chatSendBtn = document.getElementById('chatSendBtn');
  const composeToolBtns = [
    chatAudioBtn, chatPhotoBtn, chatVideoBtn, chatDocBtn,
    chatEmojiBtn, chatAiBtn, chatTemplateBtn,
  ].filter(Boolean);

  let currentChannel = cfg.channel || 'all';
  let selectedId = Number(cfg.selectedId || 0);
  let currentConv = null;
  let sending = false;
  let convAbort = null;
  let msgAbort = null;
  let listReq = 0;
  let msgReq = 0;
  let mediaRecorder = null;
  let audioChunks = [];
  let audioStream = null;

  const EMOJIS = [
    '😀','😁','😂','😊','😍','😘','😎','🤔','😮','😢','😡','👍','👎','🙏','👏',
    '🔥','✨','✅','❌','❤️','💙','💚','💛','🎉','📌','📎','📷','🎥','📄','💬',
    '👋','🤝','🙌','💯','⭐','🚀','⏰','📍','🛒','📦',
  ];

  const esc = (s) => String(s ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

  const channelIcon = (id) => {
    const key = String(id || 'web').toLowerCase();
    const icons = {
      whatsapp: '<svg class="ch-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="#25D366" d="M12.04 2C6.58 2 2.15 6.4 2.15 11.83c0 1.99.57 3.84 1.56 5.43L2 22l4.9-1.61a10 10 0 0 0 5.14 1.42h.01c5.46 0 9.89-4.4 9.89-9.83C21.94 6.4 17.5 2 12.04 2zm5.75 13.99c-.24.68-1.4 1.25-1.93 1.33-.49.07-1.12.1-1.81-.11-.42-.13-.95-.31-1.64-.6-2.89-1.25-4.77-4.16-4.92-4.35-.14-.19-1.18-1.57-1.18-3 0-1.42.74-2.12 1-2.41.26-.29.57-.36.76-.36h.55c.17 0 .4-.06.63.48.24.55.81 1.9.88 2.04.07.14.12.31.02.5-.1.19-.14.31-.28.48-.14.17-.29.37-.42.5-.14.14-.28.29-.12.57.16.28.71 1.17 1.52 1.9 1.05.93 1.93 1.22 2.21 1.36.28.14.44.12.6-.07.16-.19.69-.8.88-1.08.19-.28.37-.23.63-.14.26.1 1.64.77 1.92.91.28.14.47.21.54.33.07.12.07.68-.17 1.36z"/></svg>',
      messenger: '<svg class="ch-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="#0084FF" d="M12 2C6.36 2 2 6.13 2 11.7c0 2.91 1.19 5.44 3.14 7.17V22l2.87-1.58c.9.25 1.85.38 2.99.38 5.64 0 10-4.13 10-9.7S17.64 2 12 2zm1.01 13.08-2.55-2.72-4.98 2.72 5.47-5.81 2.61 2.72 4.92-2.72-5.47 5.81z"/></svg>',
      facebook: '<svg class="ch-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="#1877F2" d="M13.5 22v-8h2.7l.4-3.1h-3.1V9c0-.9.3-1.5 1.6-1.5H16.8V4.7c-.3 0-1.2-.1-2.3-.1-2.3 0-3.9 1.4-3.9 4v2.3H8.1V14h2.5v8h2.9z"/></svg>',
      instagram: '<svg class="ch-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="#E4405F" d="M7.8 2h8.4C19.4 2 22 4.6 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8C4.6 22 2 19.4 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2zm0 2A3.8 3.8 0 0 0 4 7.8v8.4A3.8 3.8 0 0 0 7.8 20h8.4a3.8 3.8 0 0 0 3.8-3.8V7.8A3.8 3.8 0 0 0 16.2 4H7.8zm9.65 1.5a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5zM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/></svg>',
      web: '<svg class="ch-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="#14D4E8" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm7.9 9h-3.17a15.4 15.4 0 0 0-1.3-5.3A8.03 8.03 0 0 1 19.9 11zM12 4c.9 0 2.3 1.8 3.05 5H8.95C9.7 5.8 11.1 4 12 4zM4.1 13h3.17c.2 1.9.7 3.7 1.3 5.3A8.03 8.03 0 0 1 4.1 13zm3.17-2H4.1a8.03 8.03 0 0 1 4.47-5.3A15.4 15.4 0 0 0 7.27 11zM12 20c-.9 0-2.3-1.8-3.05-5h6.1C14.3 18.2 12.9 20 12 20zm3.43-2.7c.6-1.6 1.1-3.4 1.3-5.3h3.17a8.03 8.03 0 0 1-4.47 5.3zM9.55 13c.2 1.7.6 3.3 1.2 4.6.4.8.8 1.4 1.25 1.4s.85-.6 1.25-1.4c.6-1.3 1-2.9 1.2-4.6H9.55zm0-2h4.9c-.2-1.7-.6-3.3-1.2-4.6C12.85 5.6 12.45 5 12 5s-.85.6-1.25 1.4c-.6 1.3-1 2.9-1.2 4.6z"/></svg>',
      email: '<svg class="ch-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="#C5A059" d="M3 5.5A1.5 1.5 0 0 1 4.5 4h15A1.5 1.5 0 0 1 21 5.5v13a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 18.5v-13zm1.7.5 7.3 5.1L19.3 6H4.7zM20 7.8l-7.4 5.2a1 1 0 0 1-1.2 0L4 7.8V18h16V7.8z"/></svg>',
    };
    if (key === 'fb') return icons.facebook;
    if (key === 'wa') return icons.whatsapp;
    if (key === 'ig') return icons.instagram;
    if (key === 'mail') return icons.email;
    return icons[key] || icons.web;
  };

  const channelBadge = (channel) => {
    const labels = { whatsapp: 'WhatsApp', messenger: 'Messenger', instagram: 'Instagram', web: 'Web' };
    const id = String(channel || 'web');
    const label = labels[id] || (id.charAt(0).toUpperCase() + id.slice(1));
    return `<span class="ch-badge ch-badge-${esc(id)}">${channelIcon(id)}<span>${esc(label)}</span></span>`;
  };

  const kindBadge = (kind) => {
    if (String(kind) !== 'comment') return '';
    return '<span class="badge badge-comment" title="Comentario en publicación">💬 Comentario</span>';
  };

  const handlingBadge = (c) => {
    if (String(c?.handling_mode || '') === 'human') {
      const name = c.assigned_name || 'Humano';
      return `<span class="badge badge-human" title="Atendida por humano">${esc(name)}</span>`;
    }
    if (c?.attended_by_ai || String(c?.handling_mode || 'ai') === 'ai') {
      return '<span class="badge badge-ai" title="Atendido por IA">IA</span>';
    }
    return '';
  };

  const viewer = cfg.viewer || { id: 0, role: '', name: '' };
  const takeBtn = document.getElementById('omniTakeBtn');
  const closeBtn = document.getElementById('omniCloseBtn');
  const aiBadge = document.getElementById('omniAiBadge');
  const humanBadge = document.getElementById('omniHumanBadge');
  const humanNameEl = document.getElementById('omniHumanName');
  const templateHint = document.getElementById('chatTemplateHint');
  let windowCanReply = true;

  let kindFilter = 'all';
  let statusFilter = 'all';
  let lastConvItems = [];

  const convBucket = (c) => {
    if (String(c?.handling_mode || 'ai').toLowerCase() === 'human') return 'human';
    return 'ai';
  };

  const computeConvStats = (items) => {
    const stats = { all: 0, ai: 0, human: 0, message: 0, comment: 0 };
    (items || []).forEach((c) => {
      stats.all += 1;
      stats[convBucket(c)] += 1;
      if (String(c?.thread_kind || 'message') === 'comment') stats.comment += 1;
      else stats.message += 1;
    });
    return stats;
  };

  const paintConvStats = (items) => {
    const stats = computeConvStats(items);
    document.querySelectorAll('[data-count-for]').forEach((el) => {
      const key = el.getAttribute('data-count-for');
      if (key && Object.prototype.hasOwnProperty.call(stats, key)) {
        el.textContent = String(stats[key]);
      }
    });
    document.querySelectorAll('[data-kind-count]').forEach((el) => {
      const key = el.getAttribute('data-kind-count');
      if (key && Object.prototype.hasOwnProperty.call(stats, key)) {
        el.textContent = String(stats[key]);
      }
    });
    return stats;
  };

  const setBusy = (on) => {
    if (app) app.dataset.busy = on ? '1' : '0';
  };

  // Solo acordeón de Canales (Conversaciones siempre visible)
  const readChannelsCollapsed = () => {
    try {
      // limpia estado viejo que también colapsaba conversaciones
      localStorage.removeItem('arya.omni.panels');
      return localStorage.getItem(OMNI_CHANNELS_KEY) === '1';
    } catch (_) {
      return false;
    }
  };

  const applyChannelsCollapsed = (collapsed) => {
    if (!app) return;
    app.classList.toggle('is-channels-collapsed', !!collapsed);
    app.classList.remove('is-list-collapsed');
    if (collapseChannelsBtn) {
      collapseChannelsBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      collapseChannelsBtn.title = collapsed ? 'Expandir canales' : 'Recoger canales';
    }
    try {
      localStorage.setItem(OMNI_CHANNELS_KEY, collapsed ? '1' : '0');
    } catch (_) {
      /* ignore */
    }
  };

  applyChannelsCollapsed(readChannelsCollapsed());
  collapseChannelsBtn?.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    applyChannelsCollapsed(!app?.classList.contains('is-channels-collapsed'));
  });

  const nowTime = () => new Date().toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' });

  const apiUrl = (base, params = {}) => {
    const u = new URL(base, window.location.href);
    Object.entries(params).forEach(([k, v]) => {
      if (v == null || v === '') u.searchParams.delete(k);
      else u.searchParams.set(k, String(v));
    });
    return u.toString();
  };

  const fetchJson = async (url, opts = {}, signal) => {
    const res = await fetch(url, {
      credentials: 'same-origin',
      ...opts,
      signal,
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(opts.headers || {}),
      },
    });
    const data = await res.json();
    if (!res.ok || data?.ok === false) {
      throw new Error(data?.message || ('HTTP ' + res.status));
    }
    return data;
  };

  const scrollChat = () => {
    if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;
  };

  const initialOf = (name) => {
    const clean = String(name || '').replace(/^@/, '').trim();
    return clean ? clean.charAt(0).toUpperCase() : '?';
  };

  const pageLabelFor = (channel) => {
    const ch = String(channel || '');
    if (ch === 'instagram') return 'cortech_col';
    return 'Tu página';
  };

  const formatCaptionHtml = (text) => esc(text).replace(
    /(#[\wáéíóúüñÁÉÍÓÚÜÑ]+)/gi,
    '<span class="ig-tag">$1</span>'
  );

  const renderCommentMessage = (m, conv) => {
    const from = m.from || 'client';
    const isAgent = from === 'agent';
    const authorRaw = isAgent
      ? pageLabelFor(conv?.channel)
      : (conv?.client || 'Usuario');
    const author = String(authorRaw).replace(/^@/, '');
    const type = m.type || 'text';
    const media = m.media_url || null;
    let mediaHtml = '';
    if (media && type === 'image') {
      mediaHtml = `<a href="${esc(media)}" target="_blank" rel="noopener" class="msg-media"><img src="${esc(media)}" alt="imagen" loading="lazy"></a>`;
    }
    const textHtml = m.text ? `<div class="msg-text">${esc(m.text)}</div>` : '';
    return `<article class="ig-comment msg msg-${esc(from)}">
      <div class="ig-avatar ig-avatar-sm" aria-hidden="true">${esc(initialOf(author))}</div>
      <div class="ig-comment-body">
        <div class="ig-comment-head">
          <strong class="ig-comment-user">${esc(author)}</strong>
          <span class="msg-time">${esc(m.time || '')}</span>
        </div>
        ${mediaHtml}${textHtml}
        <button type="button" class="ig-reply-link" data-reply-to="${esc(author)}">Responder</button>
      </div>
      <span class="ig-heart" aria-hidden="true">♡</span>
    </article>`;
  };

  const renderMessage = (m) => {
    const isComment = String(currentConv?.thread_kind || '') === 'comment'
      || chatActive?.classList.contains('is-comment');
    if (isComment) return renderCommentMessage(m, currentConv);

    const type = m.type || 'text';
    const media = m.media_url || null;
    let mediaHtml = '';
    if (media && (type === 'image' || type === 'sticker')) {
      mediaHtml = `<a href="${esc(media)}" target="_blank" rel="noopener" class="msg-media"><img src="${esc(media)}" alt="imagen" loading="lazy"></a>`;
    } else if (media && type === 'video') {
      mediaHtml = `<video class="msg-media" controls preload="metadata" src="${esc(media)}"></video>`;
    } else if (media && (type === 'audio' || type === 'voice')) {
      mediaHtml = `<audio class="msg-audio" controls preload="metadata" src="${esc(media)}"></audio>`;
    } else if (media && type === 'document') {
      const label = String(m.text || 'Documento')
        .replace(/^\[documento\]\s*/i, '')
        .replace(/^\[document\]\s*/i, '') || 'Documento';
      mediaHtml = `<a href="${esc(media)}" target="_blank" rel="noopener" class="msg-doc" download>📄 ${esc(label)}</a>`;
    }
    let text = String(m.text || '');
    const placeholderOnly = [
      '[mensaje]', '[imagen]', '[video]', '[audio]', '[documento]', '[sticker]',
    ].includes(text) || /^\[documento\]/i.test(text);
    if (media && placeholderOnly) text = '';
    const textHtml = text ? `<div class="msg-text">${esc(text)}</div>` : '';
    const from = String(m.from || 'client');
    const role = from === 'ai' ? 'ai' : (from === 'agent' ? 'agent' : 'client');
    const label = role === 'ai' ? 'IA' : (role === 'agent' ? 'Agente' : '');
    const labelHtml = label ? `<span class="msg-label">${esc(label)}</span>` : '';
    return `<div class="msg msg-${esc(role)}" data-from="${esc(role)}">${labelHtml}${mediaHtml}${textHtml}<span class="msg-time">${esc(m.time || '')}</span></div>`;
  };

  const applyListFilters = (items) => {
    let out = items || [];
    if (statusFilter === 'ai' || statusFilter === 'human') {
      out = out.filter((c) => convBucket(c) === statusFilter);
    }
    if (kindFilter === 'comment') {
      out = out.filter((c) => String(c.thread_kind) === 'comment');
    } else if (kindFilter === 'message') {
      out = out.filter((c) => String(c.thread_kind || 'message') !== 'comment');
    }
    return out;
  };

  const emptyFilterMessage = () => {
    if (statusFilter === 'ai') return 'Sin conversaciones con IA en este canal.';
    if (statusFilter === 'human') return 'Sin conversaciones tomadas por agentes.';
    if (kindFilter === 'comment') return 'Sin comentarios en este canal.';
    if (kindFilter === 'message') return 'Sin chats en este canal.';
    return 'Sin conversaciones en este canal.';
  };

  const renderConversations = (items, activeId) => {
    if (!convList) return;
    lastConvItems = items || [];
    paintConvStats(lastConvItems);
    const filtered = applyListFilters(lastConvItems);
    if (!filtered.length) {
      convList.innerHTML = `<div class="empty-state"><p>${emptyFilterMessage()}</p></div>`;
      if (convCount) convCount.textContent = '0';
      return;
    }
    convList.innerHTML = filtered.map((c) => `
      <button type="button" class="conv-item ${String(c.id) === String(activeId) ? 'active' : ''}"
        data-conv-id="${esc(c.id)}"
        data-client="${esc(c.client)}"
        data-phone="${esc(c.phone || '')}"
        data-channel="${esc(c.channel || '')}"
        data-status="${esc(c.status || 'open')}"
        data-preview="${esc(c.preview || '')}"
        data-thread-kind="${esc(c.thread_kind || 'message')}">
        <div class="conv-top">
          <span class="conv-name">${esc(c.client)}</span>
          <span class="conv-time">${esc(c.time)}</span>
        </div>
        <div class="conv-preview">${esc(c.preview)}</div>
        <div class="conv-meta">
          ${channelBadge(c.channel)}
          ${kindBadge(c.thread_kind)}
          ${handlingBadge(c)}
          ${Number(c.unread) > 0 ? `<span class="badge badge-gold">${esc(c.unread)}</span>` : ''}
        </div>
      </button>
    `).join('');
    if (convCount) convCount.textContent = String(filtered.length);
  };

  const updateChannelUnreads = (channels) => {
    (channels || []).forEach((ch) => {
      const el = document.querySelector(`[data-unread-for="${ch.id}"]`);
      if (!el) return;
      const n = Number(ch.unread || 0);
      if (n > 0) {
        el.hidden = false;
        el.textContent = String(n);
      } else {
        el.hidden = true;
      }
    });
  };

  const patchListPreview = (id, preview, time) => {
    const btn = convList?.querySelector(`.conv-item[data-conv-id="${id}"]`);
    if (!btn) return;
    const prev = btn.querySelector('.conv-preview');
    const t = btn.querySelector('.conv-time');
    if (prev) prev.textContent = preview;
    if (t) t.textContent = time;
    btn.dataset.preview = preview;
    // mover al tope
    if (convList.firstElementChild !== btn) {
      convList.prepend(btn);
    }
  };

  const showChatShell = (conv) => {
    if (!conv) {
      if (chatEmpty) chatEmpty.hidden = false;
      if (chatActive) chatActive.hidden = true;
      selectedId = 0;
      currentConv = null;
      return;
    }
    selectedId = Number(conv.id);
    currentConv = conv;
    if (chatEmpty) chatEmpty.hidden = true;
    if (chatActive) chatActive.hidden = false;
    const isComment = String(conv.thread_kind || '') === 'comment';
    const pageLabel = pageLabelFor(conv.channel);
    chatActive?.classList.toggle('is-comment', isComment);
    if (chatActive) chatActive.dataset.pageLabel = pageLabel;
    if (kindBadgeEl) kindBadgeEl.hidden = !isComment;
    if (igFollowing) igFollowing.hidden = !isComment;
    if (igActions) igActions.hidden = !isComment;
    if (captionBlock) captionBlock.hidden = !isComment;
    if (composeAvatar) {
      composeAvatar.hidden = !isComment;
      composeAvatar.textContent = initialOf(pageLabel);
    }
    if (composeTools) composeTools.hidden = isComment;

    if (chatName) chatName.textContent = isComment ? pageLabel : (conv.client || '');
    if (igAvatar) igAvatar.textContent = initialOf(isComment ? pageLabel : (conv.client || '?'));
    if (pageNameEl) pageNameEl.textContent = pageLabel;
    if (chatMeta) {
      const ch = String(conv.channel || '');
      const chLabel = ch ? ch.charAt(0).toUpperCase() + ch.slice(1) : '';
      chatMeta.textContent = isComment
        ? `${chLabel} · Comentario`
        : `${conv.phone || ''} · ${chLabel}`;
    }
    if (chatMessage) {
      chatMessage.placeholder = isComment
        ? 'Agrega un comentario…'
        : 'Escribe un mensaje…';
    }
    if (chatSendBtn) chatSendBtn.textContent = isComment ? 'Publicar' : 'Enviar';

    if (postCard) {
      postCard.hidden = !isComment;
      if (isComment && postMedia) {
        const permalink = conv.post_permalink || '#';
        const proxyTpl = cfg.urls.postMediaTpl || '';
        const proxyUrl = proxyTpl
          ? proxyTpl.replace('__ID__', String(conv.id)) + (proxyTpl.includes('?') ? '&' : '?') + 't=' + Date.now()
          : (conv.post_media_url || '');
        postMedia.classList.remove('is-broken');
        if (proxyUrl) {
          postMedia.innerHTML = `<a href="${esc(permalink !== '#' ? permalink : proxyUrl)}" target="_blank" rel="noopener" class="comment-post-thumb-link"><img src="${esc(proxyUrl)}" alt="Publicación" width="112" height="112" loading="lazy" data-fallback="1"></a>`;
          const img = postMedia.querySelector('img[data-fallback]');
          if (img) {
            img.addEventListener('error', () => {
              postMedia.classList.add('is-broken');
              img.classList.add('is-broken');
            }, { once: true });
          }
        } else {
          postMedia.innerHTML = '<div class="comment-post-thumb-empty" aria-hidden="true"></div>';
          postMedia.classList.add('is-broken');
        }
        if (postCaption) {
          const cap = conv.post_caption || 'Responde al comentario sobre esta publicación.';
          postCaption.innerHTML = formatCaptionHtml(cap);
        }
        if (postLink) {
          if (conv.post_permalink) {
            postLink.hidden = false;
            postLink.href = conv.post_permalink;
          } else {
            postLink.hidden = true;
          }
        }
      }
    }
    if (captionBlock) {
      captionBlock.hidden = !isComment;
    }
    if (chatStatus) {
      const st = String(conv.status || 'open');
      chatStatus.textContent = st.charAt(0).toUpperCase() + st.slice(1);
    }
    if (convIdInput) convIdInput.value = String(conv.id);
    document.querySelectorAll('.conv-item').forEach((el) => {
      el.classList.toggle('active', String(el.dataset.convId) === String(conv.id));
    });
    const url = new URL(window.location.href);
    url.searchParams.set('chat', String(conv.id));
    if (currentChannel && currentChannel !== 'all') url.searchParams.set('canal', currentChannel);
    else url.searchParams.delete('canal');
    history.replaceState({ chat: conv.id, canal: currentChannel }, '', url.toString());
  };

  const windowBanner = document.getElementById('chatWindowBanner');

  const applyHandlingUi = (conv) => {
    const mode = String(conv?.handling_mode || 'ai').toLowerCase();
    const isHuman = mode === 'human' && Number(conv?.assigned_user_id || 0) > 0;
    const attendedAi = !!conv?.attended_by_ai;
    const assignedId = Number(conv?.assigned_user_id || 0);
    const isAssignee = isHuman && assignedId === Number(viewer.id || 0);
    const isAdmin = String(viewer.role || '').toUpperCase() === 'ADMIN';
    const canTake = !!conv && (!isHuman || (isAdmin && !isAssignee));
    const canClose = !!conv && isHuman && (isAssignee || isAdmin);

    if (aiBadge) aiBadge.hidden = !attendedAi;
    if (humanBadge) {
      humanBadge.hidden = !isHuman;
      if (humanNameEl) humanNameEl.textContent = conv?.assigned_name || 'agente';
    }
    if (takeBtn) {
      takeBtn.hidden = !canTake;
      takeBtn.classList.toggle('is-pulse', !!attendedAi && canTake);
    }
    if (closeBtn) closeBtn.hidden = !canClose;
  };

  const applyComposeLock = (conv, metaWindowOpen) => {
    const mode = String(conv?.handling_mode || 'ai').toLowerCase();
    const attendedAi = !!conv?.attended_by_ai;
    const isHuman = mode === 'human' && Number(conv?.assigned_user_id || 0) > 0;
    const assignedId = Number(conv?.assigned_user_id || 0);
    const isAssignee = isHuman && assignedId === Number(viewer.id || 0);
    const isAdmin = String(viewer.role || '').toUpperCase() === 'ADMIN';
    const channel = String(conv?.channel || '').toLowerCase();
    const isWa = channel === 'whatsapp' || channel === 'wa';

    // Con IA activa: nadie escribe hasta tomar
    const lockedByAi = !!conv && attendedAi;
    // Humano de otro agente: tampoco
    const lockedByOwner = isHuman && !isAssignee && !isAdmin;
    const lockedByMeta = !!conv && ['whatsapp', 'messenger', 'instagram'].includes(channel) && metaWindowOpen === false;
    const locked = !conv || lockedByAi || lockedByOwner || lockedByMeta;

    composeForm?.classList.toggle('is-window-locked', locked);
    composeForm?.classList.toggle('is-ai-locked', lockedByAi);
    composeForm?.classList.toggle('is-owner-locked', lockedByOwner && !lockedByAi);

    if (chatSendBtn) chatSendBtn.disabled = locked;
    if (chatMessage) {
      chatMessage.disabled = locked;
      if (lockedByAi) {
        chatMessage.placeholder = 'Toma la conversación para responder…';
      } else if (lockedByOwner) {
        chatMessage.placeholder = 'Solo el agente asignado puede responder…';
      } else if (lockedByMeta) {
        chatMessage.placeholder = isWa
          ? 'Ventana Meta cerrada. Usa Abrir por plantilla…'
          : 'Ventana Meta cerrada…';
      } else {
        const isComment = String(conv?.thread_kind || '') === 'comment';
        chatMessage.placeholder = isComment ? 'Agrega un comentario…' : 'Escribe un mensaje…';
      }
    }

    composeToolBtns.forEach((btn) => {
      if (!btn) return;
      // Plantilla WA solo si no hay lock de IA/owner y (ventana abierta o bypass plantilla)
      if (btn === chatTemplateBtn && isWa && !lockedByAi && !lockedByOwner) {
        btn.disabled = false;
        const label = btn.querySelector('.compose-tool-label');
        if (label) label.textContent = lockedByMeta ? 'Abrir plantilla' : 'Plantilla';
        return;
      }
      btn.disabled = locked;
    });

    if (templateHint) {
      if (lockedByAi) {
        templateHint.textContent = 'La IA está atendiendo. Toma la conversación para poder escribir o usar plantillas.';
      } else if (lockedByMeta && isWa) {
        templateHint.textContent = 'Ventana cerrada: al elegir una plantilla se enviará la HSM aprobada en Meta.';
      } else {
        templateHint.textContent = 'Dentro de la ventana 24h puedes insertar el texto. Fuera de ventana, envía la plantilla HSM aprobada en Meta.';
      }
    }

    // Banner específico IA
    if (windowBanner && lockedByAi) {
      windowBanner.hidden = false;
      windowBanner.classList.remove('is-open');
      windowBanner.classList.add('is-closed', 'is-ai-handoff');
      windowBanner.textContent = 'Atendido por IA · Toma la conversación para escribir o enviar archivos.';
    } else if (windowBanner) {
      windowBanner.classList.remove('is-ai-handoff');
    }

    if (locked) {
      closeComposePopovers();
      stopAudioRecording(false);
    }
  };

  const applyMessagingWindow = (conv, windowInfo) => {
    const win = windowInfo || {
      can_reply: conv?.can_reply !== false,
      label: conv?.window_label || '',
      message: conv?.window_message || '',
    };
    const channel = String(conv?.channel || '').toLowerCase();
    const isMeta = ['whatsapp', 'messenger', 'instagram'].includes(channel);
    const can = !isMeta || win.can_reply !== false;
    const isWa = channel === 'whatsapp' || channel === 'wa';
    const attendedAi = !!conv?.attended_by_ai;
    windowCanReply = can;

    if (windowBanner && !attendedAi) {
      if (!isMeta) {
        windowBanner.hidden = true;
        windowBanner.textContent = '';
        windowBanner.classList.remove('is-open', 'is-closed', 'is-ai-handoff');
      } else if (can) {
        windowBanner.hidden = false;
        windowBanner.classList.add('is-open');
        windowBanner.classList.remove('is-closed', 'is-ai-handoff');
        windowBanner.textContent = win.label
          ? `Meta · ${win.label}. Puedes responder con mensaje libre.`
          : 'Meta · Ventana de mensajería abierta. Puedes responder.';
      } else {
        windowBanner.hidden = false;
        windowBanner.classList.add('is-closed');
        windowBanner.classList.remove('is-open', 'is-ai-handoff');
        windowBanner.textContent = isWa
          ? ((win.message || 'Fuera de la ventana 24h.') + ' Usa «Abrir por plantilla» para contactar con una HSM aprobada.')
          : (win.message || 'Fuera de la ventana Meta. El cliente debe escribir primero.');
      }
    }

    applyHandlingUi(conv);
    applyComposeLock(conv, can);
  };

  const showChat = (conv, messages, windowInfo, opts = {}) => {
    showChatShell(conv);
    if (!conv) {
      applyMessagingWindow(null);
      lastMsgFp = '';
      return;
    }
    if (chatBox) {
      chatBox.innerHTML = (messages || []).map(renderMessage).join('');
      if (opts.scroll !== false) scrollChat();
    }
    applyMessagingWindow(conv, windowInfo || {
      can_reply: conv.can_reply,
      label: conv.window_label,
      message: conv.window_message,
    });
  };

  const msgFingerprint = (messages) => (messages || [])
    .map((m) => `${m.id}:${m.from}:${m.type || ''}:${String(m.text || '').length}:${m.media_url ? 1 : 0}`)
    .join('|');

  const isChatNearBottom = () => {
    if (!chatBox) return true;
    return (chatBox.scrollHeight - chatBox.scrollTop - chatBox.clientHeight) < 100;
  };

  let lastMsgFp = '';
  let lastListFp = '';
  let pollTimer = null;
  let pollInFlight = false;

  const loadConversations = async (channel, keepSelected = true, opts = {}) => {
    const silent = !!opts.silent;
    let myReq = listReq;
    currentChannel = channel || currentChannel || 'all';
    if (!silent) {
      if (convAbort) convAbort.abort();
      convAbort = new AbortController();
      myReq = ++listReq;
      setBusy(true);
      document.querySelectorAll('.channel-btn').forEach((btn) => {
        btn.classList.toggle('active', btn.dataset.channel === currentChannel);
      });
      if (convList) {
        convList.innerHTML = '<div class="empty-state"><p>Cargando…</p></div>';
      }
    }
    try {
      const data = await fetchJson(
        apiUrl(cfg.urls.conversations, { canal: currentChannel }),
        {},
        silent ? undefined : convAbort?.signal
      );
      if (!silent && myReq !== listReq) return;
      updateChannelUnreads(data.channels);
      const items = data.conversations || [];
      const listFp = items.map((c) => [
        c.id, c.preview, c.time, c.unread, c.status, c.handling_mode, c.assigned_user_id, c.attended_by_ai,
      ].join(':')).join('|');

      if (silent && listFp === lastListFp) {
        return items;
      }
      lastListFp = listFp;

      let nextId = keepSelected ? selectedId : 0;
      if (!items.some((c) => String(c.id) === String(nextId))) {
        nextId = silent ? selectedId : (items[0]?.id || 0);
      }
      renderConversations(items, nextId || selectedId);

      const selectedItem = items.find((c) => String(c.id) === String(selectedId));
      if (silent && selectedItem && currentConv) {
        currentConv = { ...currentConv, ...selectedItem };
        applyHandlingUi(currentConv);
        applyComposeLock(currentConv, windowCanReply);
      }

      if (!silent) {
        if (nextId) {
          const item = items.find((c) => String(c.id) === String(nextId));
          if (item) showChatShell(item);
          await loadMessages(nextId, { soft: true });
        } else {
          showChat(null, []);
        }
      }
      return items;
    } catch (e) {
      if (e.name === 'AbortError') return;
      if (!silent) {
        console.error(e);
        if (convList) convList.innerHTML = '<div class="empty-state"><p>Error al cargar conversaciones.</p></div>';
      }
    } finally {
      if (!silent && myReq === listReq) setBusy(false);
    }
  };

  const loadMessages = async (id, { soft = false, silent = false } = {}) => {
    if (!id) return;
    let myReq = msgReq;
    if (!silent) {
      if (msgAbort) msgAbort.abort();
      msgAbort = new AbortController();
      myReq = ++msgReq;
      selectedId = Number(id);
      if (!soft) setBusy(true);

      const btn = convList?.querySelector(`.conv-item[data-conv-id="${id}"]`);
      if (btn) {
        showChatShell({
          id,
          client: btn.dataset.client || btn.querySelector('.conv-name')?.textContent || '',
          phone: btn.dataset.phone || '',
          channel: btn.dataset.channel || '',
          status: btn.dataset.status || 'open',
          thread_kind: btn.dataset.threadKind || 'message',
        });
        btn.querySelectorAll('.badge-gold').forEach((el) => {
          if (el.textContent && /^\d+$/.test(el.textContent.trim())) el.remove();
        });
      } else if (chatBox && !soft) {
        chatBox.innerHTML = '<div class="empty-state"><p>Cargando mensajes…</p></div>';
      }
    } else {
      selectedId = Number(selectedId || id);
    }

    try {
      const endpoint = String(cfg.urls.messagesTpl || '').replace('__ID__', encodeURIComponent(id));
      const data = await fetchJson(endpoint, {}, silent ? undefined : msgAbort?.signal);
      if (!silent && myReq !== msgReq) return;
      if (silent && Number(id) !== Number(selectedId)) return;

      const msgs = data.messages || [];
      const fp = msgFingerprint(msgs);
      const conv = data.conversation || null;

      if (silent && fp === lastMsgFp) {
        if (conv) {
          currentConv = conv;
          applyMessagingWindow(conv, data.window || null);
        }
        return;
      }

      const shouldScroll = !silent || isChatNearBottom() || fp.split('|').length > (lastMsgFp ? lastMsgFp.split('|').length : 0);
      lastMsgFp = fp;
      showChat(conv, msgs, data.window || null, { scroll: shouldScroll });
      if (data.channels) updateChannelUnreads(data.channels);
      if (typeof window.ARYA_REFRESH_OMNI_NOTIFS === 'function') {
        window.ARYA_REFRESH_OMNI_NOTIFS();
      }
    } catch (e) {
      if (e.name === 'AbortError') return;
      if (!silent) console.error(e);
    } finally {
      if (!silent && myReq === msgReq && !soft) setBusy(false);
    }
  };

  const stopLivePoll = () => {
    if (pollTimer) {
      clearTimeout(pollTimer);
      pollTimer = null;
    }
  };

  const scheduleLivePoll = (ms) => {
    stopLivePoll();
    pollTimer = setTimeout(() => { livePollTick(); }, ms);
  };

  const livePollTick = async () => {
    if (!document.getElementById('omniApp')) {
      stopLivePoll();
      return;
    }
    const aiFast = !!currentConv?.attended_by_ai;
    const nextMs = document.visibilityState !== 'visible'
      ? 8000
      : (aiFast ? 1500 : 2500);

    if (document.visibilityState === 'visible' && !sending && !pollInFlight && app?.dataset.busy !== '1') {
      pollInFlight = true;
      try {
        await loadConversations(currentChannel, true, { silent: true });
        if (selectedId) {
          await loadMessages(selectedId, { soft: true, silent: true });
        }
      } catch (_) {
        /* ignore poll errors */
      } finally {
        pollInFlight = false;
      }
    }
    scheduleLivePoll(nextMs);
  };

  if (typeof window.__ARYA_OMNI_POLL_STOP === 'function') {
    window.__ARYA_OMNI_POLL_STOP();
  }
  window.__ARYA_OMNI_POLL_STOP = stopLivePoll;
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      livePollTick();
    }
  });
  scheduleLivePoll(1000);

  document.getElementById('omniStatusFilters')?.addEventListener('click', (e) => {
    const btn = e.target.closest('.omni-stat');
    if (!btn) return;
    statusFilter = btn.dataset.statusFilter || 'all';
    document.querySelectorAll('#omniStatusFilters .omni-stat').forEach((el) => {
      const on = el === btn;
      el.classList.toggle('active', on);
      el.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    renderConversations(lastConvItems, selectedId);
  });

  document.getElementById('omniKindFilters')?.addEventListener('click', (e) => {
    const btn = e.target.closest('.kind-filter');
    if (!btn) return;
    kindFilter = btn.dataset.kind || 'all';
    document.querySelectorAll('#omniKindFilters .kind-filter').forEach((el) => {
      el.classList.toggle('active', el === btn);
    });
    renderConversations(lastConvItems, selectedId);
  });

  // Eventos canal / conversación (sin reload)
  document.getElementById('omniChannels')?.addEventListener('click', (e) => {
    const btn = e.target.closest('.channel-btn');
    if (!btn) return;
    e.preventDefault();
    const ch = btn.dataset.channel || 'all';
    if (ch === currentChannel) return;
    loadConversations(ch, false);
  });

  convList?.addEventListener('click', (e) => {
    const btn = e.target.closest('.conv-item');
    if (!btn) return;
    e.preventDefault();
    const id = btn.dataset.convId;
    if (!id) return;
    if (String(id) === String(selectedId) && chatActive && !chatActive.hidden) return;
    loadMessages(id);
  });

  chatBox?.addEventListener('click', (e) => {
    const replyBtn = e.target.closest('.ig-reply-link');
    if (!replyBtn || !chatMessage) return;
    const who = replyBtn.dataset.replyTo || '';
    const mention = who ? `@${who.replace(/^@/, '')} ` : '';
    if (mention && !chatMessage.value.includes(mention)) {
      chatMessage.value = mention + chatMessage.value;
    }
    chatMessage.focus();
    chatMessage.dispatchEvent(new Event('input'));
  });

  const setSelectedFile = (file) => {
    if (!chatMedia || !file) return;
    const dt = new DataTransfer();
    dt.items.add(file);
    chatMedia.files = dt.files;
    if (chatFileName) {
      chatFileName.hidden = false;
      chatFileName.textContent = file.name;
    }
  };

  const clearSelectedFile = () => {
    if (chatMedia) chatMedia.value = '';
    if (chatPhotoInput) chatPhotoInput.value = '';
    if (chatVideoInput) chatVideoInput.value = '';
    if (chatDocInput) chatDocInput.value = '';
    if (chatFileName) {
      chatFileName.hidden = true;
      chatFileName.textContent = '';
    }
  };

  const closeComposePopovers = () => {
    if (chatEmojiPanel) chatEmojiPanel.hidden = true;
    if (chatTemplatePanel) chatTemplatePanel.hidden = true;
    chatEmojiBtn?.setAttribute('aria-expanded', 'false');
    chatTemplateBtn?.setAttribute('aria-expanded', 'false');
  };

  const insertAtCursor = (text) => {
    if (!chatMessage) return;
    const start = chatMessage.selectionStart ?? chatMessage.value.length;
    const end = chatMessage.selectionEnd ?? chatMessage.value.length;
    const before = chatMessage.value.slice(0, start);
    const after = chatMessage.value.slice(end);
    chatMessage.value = before + text + after;
    const pos = start + text.length;
    chatMessage.focus();
    chatMessage.setSelectionRange(pos, pos);
    chatMessage.dispatchEvent(new Event('input'));
  };

  const stopAudioRecording = (keepBlob) => {
    const recorder = mediaRecorder;
    if (recorder && recorder.state !== 'inactive') {
      try { recorder.stop(); } catch (_) { /* ignore */ }
    } else if (!keepBlob) {
      audioChunks = [];
      if (audioStream) {
        audioStream.getTracks().forEach((t) => t.stop());
        audioStream = null;
      }
      chatAudioBtn?.classList.remove('is-recording');
      if (chatAudioStatus) {
        chatAudioStatus.hidden = true;
        chatAudioStatus.textContent = '';
      }
    }
    if (!keepBlob) {
      mediaRecorder = null;
    }
  };

  const startAudioRecording = async () => {
    if (!navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === 'undefined') {
      alert('Tu navegador no permite grabar audio.');
      return;
    }
    try {
      audioStream = await navigator.mediaDevices.getUserMedia({ audio: true });
      audioChunks = [];
      const mime = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
        ? 'audio/webm;codecs=opus'
        : (MediaRecorder.isTypeSupported('audio/ogg;codecs=opus') ? 'audio/ogg;codecs=opus' : '');
      mediaRecorder = mime ? new MediaRecorder(audioStream, { mimeType: mime }) : new MediaRecorder(audioStream);
      const blobType = mediaRecorder.mimeType || 'audio/webm';
      mediaRecorder.ondataavailable = (ev) => {
        if (ev.data && ev.data.size > 0) audioChunks.push(ev.data);
      };
      mediaRecorder.onstop = () => {
        const chunks = audioChunks.slice();
        audioChunks = [];
        mediaRecorder = null;
        if (audioStream) {
          audioStream.getTracks().forEach((t) => t.stop());
          audioStream = null;
        }
        chatAudioBtn?.classList.remove('is-recording');
        if (!chunks.length) {
          if (chatAudioStatus) {
            chatAudioStatus.hidden = true;
            chatAudioStatus.textContent = '';
          }
          return;
        }
        const ext = blobType.includes('ogg') ? 'ogg' : 'webm';
        const file = new File(chunks, `audio_${Date.now()}.${ext}`, { type: blobType.split(';')[0] });
        setSelectedFile(file);
        if (chatAudioStatus) {
          chatAudioStatus.hidden = false;
          chatAudioStatus.textContent = 'Audio listo para enviar';
        }
      };
      mediaRecorder.start();
      chatAudioBtn?.classList.add('is-recording');
      if (chatAudioStatus) {
        chatAudioStatus.hidden = false;
        chatAudioStatus.textContent = 'Grabando… toca de nuevo para detener';
      }
    } catch (err) {
      stopAudioRecording(false);
      alert(err?.message || 'No se pudo acceder al micrófono.');
    }
  };

  if (chatEmojiGrid && !chatEmojiGrid.childElementCount) {
    chatEmojiGrid.innerHTML = EMOJIS.map((e) => `<button type="button" data-emoji="${e}">${e}</button>`).join('');
  }

  chatPhotoBtn?.addEventListener('click', () => {
    closeComposePopovers();
    chatPhotoInput?.click();
  });
  chatVideoBtn?.addEventListener('click', () => {
    closeComposePopovers();
    chatVideoInput?.click();
  });
  chatDocBtn?.addEventListener('click', () => {
    closeComposePopovers();
    chatDocInput?.click();
  });

  const wireFilePicker = (input) => {
    input?.addEventListener('change', () => {
      const file = input.files?.[0];
      if (file) setSelectedFile(file);
      if (chatAudioStatus) {
        chatAudioStatus.hidden = true;
        chatAudioStatus.textContent = '';
      }
    });
  };
  wireFilePicker(chatPhotoInput);
  wireFilePicker(chatVideoInput);
  wireFilePicker(chatDocInput);
  chatMedia?.addEventListener('change', () => {
    const file = chatMedia.files?.[0];
    if (!chatFileName) return;
    if (file) {
      chatFileName.hidden = false;
      chatFileName.textContent = file.name;
    } else {
      chatFileName.hidden = true;
      chatFileName.textContent = '';
    }
  });

  chatAudioBtn?.addEventListener('click', async () => {
    closeComposePopovers();
    if (mediaRecorder && mediaRecorder.state === 'recording') {
      stopAudioRecording(true);
      return;
    }
    await startAudioRecording();
  });

  chatEmojiBtn?.addEventListener('click', () => {
    const open = !!chatEmojiPanel && chatEmojiPanel.hidden;
    closeComposePopovers();
    if (open && chatEmojiPanel) {
      chatEmojiPanel.hidden = false;
      chatEmojiBtn.setAttribute('aria-expanded', 'true');
    }
  });

  chatEmojiGrid?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-emoji]');
    if (!btn) return;
    insertAtCursor(btn.dataset.emoji || '');
  });

  chatTemplateBtn?.addEventListener('click', () => {
    const ch = String(currentConv?.channel || '').toLowerCase();
    if (ch && ch !== 'whatsapp' && ch !== 'wa') {
      alert('Las plantillas WhatsApp solo aplican a conversaciones de WhatsApp.');
      return;
    }
    const open = !!chatTemplatePanel && chatTemplatePanel.hidden;
    closeComposePopovers();
    if (open && chatTemplatePanel) {
      chatTemplatePanel.hidden = false;
      chatTemplateBtn.setAttribute('aria-expanded', 'true');
    }
  });

  chatTemplatePanel?.addEventListener('click', async (e) => {
    const btn = e.target.closest('.template-item');
    if (!btn) return;
    const tpl = btn.dataset.template || btn.textContent || '';
    const tplName = (btn.dataset.templateName || '').trim();
    const tplLang = (btn.dataset.templateLang || 'es').trim() || 'es';
    const ch = String(currentConv?.channel || '').toLowerCase();
    const outsideWindow = !windowCanReply && (ch === 'whatsapp' || ch === 'wa');

    if (outsideWindow) {
      if (!tplName) {
        alert('Esta plantilla no tiene meta_name. Sincronízala desde OCP / Meta.');
        return;
      }
      if (!convIdInput?.value || !cfg.urls?.send) return;
      closeComposePopovers();
      sending = true;
      try {
        const body = new FormData();
        body.set('_csrf', cfg.csrf || '');
        body.set('ajax', '1');
        body.set('conversation_id', String(convIdInput.value));
        body.set('send_as', 'template');
        body.set('template_name', tplName);
        body.set('template_lang', tplLang);
        body.set('message', tpl || ('[plantilla] ' + tplName));
        const data = await fetchJson(cfg.urls.send, { method: 'POST', body });
        const t = nowTime();
        if (data.msg && chatBox) {
          chatBox.insertAdjacentHTML('beforeend', renderMessage(data.msg));
          scrollChat();
          patchListPreview(convIdInput.value, data.msg.text || tplName, data.msg.time || t);
        }
        if (data.conversation) {
          currentConv = data.conversation;
          applyMessagingWindow(data.conversation, {
            can_reply: data.conversation.can_reply,
            label: data.conversation.window_label,
            message: data.conversation.window_message,
          });
        }
      } catch (err) {
        alert(err?.message || 'No se pudo enviar la plantilla.');
      } finally {
        sending = false;
      }
      return;
    }

    if (chatMessage) {
      chatMessage.value = tpl;
      chatMessage.dispatchEvent(new Event('input'));
      chatMessage.focus();
    }
    closeComposePopovers();
  });

  const postOwnership = async (url) => {
    if (!url || !convIdInput?.value) return;
    const body = new FormData();
    body.set('_csrf', cfg.csrf || '');
    body.set('ajax', '1');
    body.set('conversation_id', String(convIdInput.value));
    const data = await fetchJson(url, { method: 'POST', body });
    if (data.conversation) {
      currentConv = data.conversation;
      applyHandlingUi(data.conversation);
      // refrescar lista para ocultar/mostrar según visibilidad
      await loadConversations(currentChannel, true);
      await loadMessages(selectedId);
    } else {
      alert(data.message || 'Listo.');
    }
  };

  takeBtn?.addEventListener('click', async () => {
    try {
      await postOwnership(cfg.urls.take);
    } catch (err) {
      alert(err?.message || 'No se pudo tomar la conversación.');
    }
  });

  closeBtn?.addEventListener('click', async () => {
    if (!confirm('¿Liberar esta conversación? Volverá a la IA y, si el cliente escribe luego, la IA responderá.')) return;
    try {
      await postOwnership(cfg.urls.close);
    } catch (err) {
      alert(err?.message || 'No se pudo cerrar la conversación.');
    }
  });

  document.addEventListener('click', (e) => {
    if (!composeTools) return;
    if (composeTools.contains(e.target)) return;
    closeComposePopovers();
  });

  chatMessage?.addEventListener('input', () => {
    chatMessage.style.height = 'auto';
    chatMessage.style.height = Math.min(chatMessage.scrollHeight, 120) + 'px';
  });

  chatMessage?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      composeForm?.requestSubmit();
    }
  });

  composeForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (sending) return;
    const text = (chatMessage?.value || '').trim();
    const hasFile = !!(chatMedia?.files && chatMedia.files[0]);
    if (!text && !hasFile) return;
    if (!convIdInput?.value) return;

    sending = true;
    chatSendBtn?.classList.add('is-loading');
    chatSendBtn?.setAttribute('disabled', 'disabled');
    closeComposePopovers();

    // FormData ANTES de limpiar el input (si no, el servidor recibe message vacío)
    const body = new FormData(composeForm);
    body.set('ajax', '1');
    body.set('message', text);
    body.set('conversation_id', String(convIdInput.value));

    const t = nowTime();
    let optimistic = false;
    if (text && !hasFile && chatBox) {
      chatBox.insertAdjacentHTML('beforeend', renderMessage({
        from: 'agent', text, type: 'text', time: t,
      }));
      scrollChat();
      patchListPreview(convIdInput.value, text, t);
      chatMessage.value = '';
      chatMessage.style.height = 'auto';
      optimistic = true;
    }

    try {
      const data = await fetchJson(cfg.urls.send, { method: 'POST', body });
      if (hasFile && data.msg && chatBox) {
        chatBox.insertAdjacentHTML('beforeend', renderMessage(data.msg));
        scrollChat();
        clearSelectedFile();
        if (chatAudioStatus) {
          chatAudioStatus.hidden = true;
          chatAudioStatus.textContent = '';
        }
        if (chatMessage) { chatMessage.value = ''; chatMessage.style.height = 'auto'; }
        patchListPreview(convIdInput.value, data.msg.text || '[archivo]', data.msg.time || t);
      }
    } catch (err) {
      if (optimistic && chatMessage) {
        chatMessage.value = text;
        chatMessage.dispatchEvent(new Event('input'));
      }
      alert(err.message || 'No se pudo enviar');
      await loadMessages(convIdInput.value);
    } finally {
      sending = false;
      chatSendBtn?.classList.remove('is-loading');
      chatSendBtn?.removeAttribute('disabled');
    }
  });

  chatAiBtn?.addEventListener('click', async () => {
    closeComposePopovers();
    const draft = (chatMessage?.value || '').trim();
    if (!draft) { chatMessage?.focus(); return; }
    const label = chatAiBtn.querySelector('.compose-tool-label');
    chatAiBtn.classList.add('is-loading');
    if (label) label.textContent = 'IA…';
    try {
      const body = new FormData();
      body.append('_csrf', cfg.csrf || '');
      body.append('message', draft);
      body.append('conversation_id', convIdInput?.value || '');
      const data = await fetchJson(cfg.urls.improve, { method: 'POST', body });
      if (data.text && chatMessage) {
        chatMessage.value = data.text;
        chatMessage.dispatchEvent(new Event('input'));
      } else {
        alert(data.message || 'No se pudo mejorar');
      }
    } catch (err) {
      alert(err.message || 'Error al contactar OpenAI');
    } finally {
      chatAiBtn.classList.remove('is-loading');
      if (label) label.textContent = 'IA';
    }
  });

  // Seed lista SSR para filtros Todos/Chats/Comentarios
  convList?.querySelectorAll('.conv-item').forEach((btn) => {
    if (!btn.dataset.client) {
      btn.dataset.client = btn.querySelector('.conv-name')?.textContent?.trim() || '';
    }
    lastConvItems.push({
      id: btn.dataset.convId,
      client: btn.dataset.client || '',
      phone: btn.dataset.phone || '',
      channel: btn.dataset.channel || '',
      status: btn.dataset.status || 'open',
      preview: btn.dataset.preview || btn.querySelector('.conv-preview')?.textContent || '',
      time: btn.querySelector('.conv-time')?.textContent || '',
      unread: 0,
      thread_kind: btn.dataset.threadKind || 'message',
    });
  });

  scrollChat();
};
window.ARYA_OMNI_REINIT();
