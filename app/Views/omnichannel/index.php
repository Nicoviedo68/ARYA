<?php
/** @var array $channels */
/** @var array $conversations */
/** @var string $currentChannel */
/** @var array|null $selected */
/** @var array $messages */

$channelIcon = static function (string $id): string {
    return match ($id) {
        'whatsapp' => '<svg class="ch-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="#25D366" d="M12.04 2C6.58 2 2.15 6.4 2.15 11.83c0 1.99.57 3.84 1.56 5.43L2 22l4.9-1.61a10 10 0 0 0 5.14 1.42h.01c5.46 0 9.89-4.4 9.89-9.83C21.94 6.4 17.5 2 12.04 2zm5.75 13.99c-.24.68-1.4 1.25-1.93 1.33-.49.07-1.12.1-1.81-.11-.42-.13-.95-.31-1.64-.6-2.89-1.25-4.77-4.16-4.92-4.35-.14-.19-1.18-1.57-1.18-3 0-1.42.74-2.12 1-2.41.26-.29.57-.36.76-.36h.55c.17 0 .4-.06.63.48.24.55.81 1.9.88 2.04.07.14.12.31.02.5-.1.19-.14.31-.28.48-.14.17-.29.37-.42.5-.14.14-.28.29-.12.57.16.28.71 1.17 1.52 1.9 1.05.93 1.93 1.22 2.21 1.36.28.14.44.12.6-.07.16-.19.69-.8.88-1.08.19-.28.37-.23.63-.14.26.1 1.64.77 1.92.91.28.14.47.21.54.33.07.12.07.68-.17 1.36z"/></svg>',
        'messenger' => '<svg class="ch-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="#0084FF" d="M12 2C6.36 2 2 6.13 2 11.7c0 2.91 1.19 5.44 3.14 7.17V22l2.87-1.58c.9.25 1.85.38 2.99.38 5.64 0 10-4.13 10-9.7S17.64 2 12 2zm1.01 13.08-2.55-2.72-4.98 2.72 5.47-5.81 2.61 2.72 4.92-2.72-5.47 5.81z"/></svg>',
        'instagram' => '<svg class="ch-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="#E4405F" d="M7.8 2h8.4C19.4 2 22 4.6 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8C4.6 22 2 19.4 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2zm0 2A3.8 3.8 0 0 0 4 7.8v8.4A3.8 3.8 0 0 0 7.8 20h8.4a3.8 3.8 0 0 0 3.8-3.8V7.8A3.8 3.8 0 0 0 16.2 4H7.8zm9.65 1.5a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5zM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/></svg>',
        'web' => '<svg class="ch-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="#00D4E8" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm7.9 9h-3.17a15.4 15.4 0 0 0-1.3-5.3A8.03 8.03 0 0 1 19.9 11zM12 4c.9 0 2.3 1.8 3.05 5H8.95C9.7 5.8 11.1 4 12 4zM4.1 13h3.17c.2 1.9.7 3.7 1.3 5.3A8.03 8.03 0 0 1 4.1 13zm3.17-2H4.1a8.03 8.03 0 0 1 4.47-5.3A15.4 15.4 0 0 0 7.27 11zM12 20c-.9 0-2.3-1.8-3.05-5h6.1C14.3 18.2 12.9 20 12 20zm3.43-2.7c.6-1.6 1.1-3.4 1.3-5.3h3.17a8.03 8.03 0 0 1-4.47 5.3zM9.55 13c.2 1.7.6 3.3 1.2 4.6.4.8.8 1.4 1.25 1.4s.85-.6 1.25-1.4c.6-1.3 1-2.9 1.2-4.6H9.55zm0-2h4.9c-.2-1.7-.6-3.3-1.2-4.6C12.85 5.6 12.45 5 12 5s-.85.6-1.25 1.4c-.6 1.3-1 2.9-1.2 4.6z"/></svg>',
        default => '<svg class="ch-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M4 6h16v2H4V6zm0 5h16v2H4v-2zm0 5h10v2H4v-2z"/></svg>',
    };
};

$channelBadge = static function (string $channel) use ($channelIcon): string {
    $label = match ($channel) {
        'whatsapp' => 'WhatsApp',
        'messenger' => 'Messenger',
        'instagram' => 'Instagram',
        'web' => 'Web',
        default => ucfirst($channel),
    };
    return '<span class="ch-badge ch-badge-' . e($channel) . '">' . $channelIcon($channel) . '<span>' . e($label) . '</span></span>';
};
?>

<div class="omni-layout" id="omniApp" data-busy="0">
  <aside class="omni-channels" id="omniChannels">
    <header class="omni-section-head">
      <h2 class="omni-section-title">Canales</h2>
      <button type="button" class="omni-toggle" id="omniCollapseChannels" data-omni-collapse="channels" aria-expanded="true" title="Recoger canales" aria-label="Recoger o expandir canales">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" width="14" height="14" aria-hidden="true">
          <path d="M15 6l-6 6 6 6"/>
        </svg>
      </button>
    </header>
    <div class="omni-channels-body" id="omniChannelsBody">
      <button type="button" class="channel-btn <?= $currentChannel === 'all' ? 'active' : '' ?>" data-channel="all" title="Todos">
        <?= $channelIcon('all') ?>
        <span class="channel-btn-label">Todos</span>
      </button>
      <?php foreach ($channels as $ch): ?>
        <button type="button" class="channel-btn <?= $currentChannel === $ch['id'] ? 'active' : '' ?>" data-channel="<?= e($ch['id']) ?>" title="<?= e($ch['name']) ?>">
          <?= $channelIcon((string) $ch['id']) ?>
          <span class="channel-btn-label"><?= e($ch['name']) ?></span>
          <span class="channel-unread" data-unread-for="<?= e($ch['id']) ?>" <?= ((int) $ch['unread'] < 1) ? 'hidden' : '' ?>>
            <?= (int) $ch['unread'] ?>
          </span>
        </button>
      <?php endforeach; ?>
    </div>
  </aside>

  <section class="omni-list" id="omniList">
    <?php
      $omniStats = ['all' => 0, 'ai' => 0, 'human' => 0, 'message' => 0, 'comment' => 0];
      foreach ($conversations as $statConv) {
          $omniStats['all']++;
          $mode = strtolower((string) ($statConv['handling_mode'] ?? 'ai'));
          if ($mode === 'human') {
              $omniStats['human']++;
          } else {
              $omniStats['ai']++;
          }
          if (($statConv['thread_kind'] ?? '') === 'comment') {
              $omniStats['comment']++;
          } else {
              $omniStats['message']++;
          }
      }
    ?>
    <div class="omni-list-header">
      <div class="omni-list-title-row">
        <h2 class="omni-section-title omni-section-title--list">Conversaciones</h2>
        <span class="omni-list-visible" title="Visibles con el filtro actual">
          <span id="omniConvCount"><?= (int) $omniStats['all'] ?></span> visibles
        </span>
      </div>
      <div class="omni-stats-filters" id="omniStatusFilters" role="tablist" aria-label="Quién atiende">
        <button type="button" class="omni-stat active" data-status-filter="all" role="tab" aria-selected="true">
          <span class="omni-stat-n" data-count-for="all"><?= (int) $omniStats['all'] ?></span>
          <span class="omni-stat-l">Total</span>
        </button>
        <button type="button" class="omni-stat omni-stat-ai" data-status-filter="ai" role="tab" aria-selected="false">
          <span class="omni-stat-n" data-count-for="ai"><?= (int) $omniStats['ai'] ?></span>
          <span class="omni-stat-l">IA</span>
        </button>
        <button type="button" class="omni-stat omni-stat-human" data-status-filter="human" role="tab" aria-selected="false">
          <span class="omni-stat-n" data-count-for="human"><?= (int) $omniStats['human'] ?></span>
          <span class="omni-stat-l">Agentes</span>
        </button>
      </div>
      <div class="omni-kind-filters" id="omniKindFilters" aria-label="Tipo de hilo">
        <button type="button" class="kind-filter active" data-kind="all">
          Tipo · todos <span class="kind-count" data-kind-count="all"><?= (int) $omniStats['all'] ?></span>
        </button>
        <button type="button" class="kind-filter" data-kind="message">
          Chats <span class="kind-count" data-kind-count="message"><?= (int) $omniStats['message'] ?></span>
        </button>
        <button type="button" class="kind-filter" data-kind="comment">
          Comentarios <span class="kind-count" data-kind-count="comment"><?= (int) $omniStats['comment'] ?></span>
        </button>
      </div>
    </div>
    <div class="omni-list-body" id="omniConvList">
      <?php if (!$conversations): ?>
        <div class="empty-state"><p>Sin conversaciones en este canal.</p></div>
      <?php endif; ?>
      <?php foreach ($conversations as $c): ?>
        <button type="button" class="conv-item <?= ($selected && (string) $selected['id'] === (string) $c['id']) ? 'active' : '' ?>"
          data-conv-id="<?= (int) $c['id'] ?>"
          data-client="<?= e($c['client']) ?>"
          data-phone="<?= e($c['phone'] ?? '') ?>"
          data-channel="<?= e($c['channel'] ?? '') ?>"
          data-status="<?= e($c['status'] ?? 'open') ?>"
          data-preview="<?= e($c['preview'] ?? '') ?>"
          data-thread-kind="<?= e($c['thread_kind'] ?? 'message') ?>">
          <div class="conv-top">
            <span class="conv-name"><?= e($c['client']) ?></span>
            <span class="conv-time"><?= e($c['time']) ?></span>
          </div>
          <div class="conv-preview"><?= e($c['preview']) ?></div>
          <div class="conv-meta">
            <?= $channelBadge((string) ($c['channel'] ?? '')) ?>
            <?php if (($c['thread_kind'] ?? '') === 'comment'): ?>
              <span class="badge badge-comment" title="Comentario en publicación">💬 Comentario</span>
            <?php endif; ?>
            <?php if (($c['handling_mode'] ?? '') === 'human'): ?>
              <span class="badge badge-human" title="Atendida por humano"><?= e((string) ($c['assigned_name'] ?: 'Humano')) ?></span>
            <?php elseif (!empty($c['attended_by_ai']) || ($c['handling_mode'] ?? 'ai') === 'ai'): ?>
              <span class="badge badge-ai" title="Atendido por IA">IA</span>
            <?php endif; ?>
            <?php if ($c['unread'] > 0): ?>
              <span class="badge badge-gold"><?= (int) $c['unread'] ?></span>
            <?php endif; ?>
          </div>
        </button>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="omni-chat" id="omniChat">
    <div id="omniChatEmpty" class="empty-state omni-chat-empty" <?= $selected ? 'hidden' : '' ?>>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 12a8.5 8.5 0 0 1-8.5 8.5H5l-2 2V12A8.5 8.5 0 1 1 21 12z"/></svg>
      <p>Selecciona una conversación</p>
    </div>

    <?php
      $isCommentView = is_array($selected) && ($selected['thread_kind'] ?? '') === 'comment';
      $pageLabel = 'Tu página';
      $selChannel = (string) ($selected['channel'] ?? '');
      if ($selChannel === 'instagram') {
          $pageLabel = 'cortech_col';
      } elseif ($selChannel === 'facebook' || $selChannel === 'messenger') {
          $pageLabel = 'Tu página';
      }
      $formatIgCaption = static function (string $text): string {
          $esc = e($text);
          return (string) preg_replace('/(#[\wáéíóúüñÁÉÍÓÚÜÑ]+)/u', '<span class="ig-tag">$1</span>', $esc);
      };
      $igInitial = static function (string $name): string {
          $clean = ltrim(trim($name), '@');
          return $clean !== '' ? mb_strtoupper(mb_substr($clean, 0, 1)) : '?';
      };
    ?>
    <div id="omniChatActive" class="omni-chat-active<?= $isCommentView ? ' is-comment' : '' ?>" <?= $selected ? '' : 'hidden' ?>
      data-page-label="<?= e($pageLabel) ?>">

      <div class="omni-chat-panel">
        <div class="chat-header">
          <div class="ig-header-user">
            <div class="ig-avatar" id="omniIgAvatar" aria-hidden="true"><?= e($igInitial($isCommentView ? $pageLabel : (string) ($selected['client'] ?? '?'))) ?></div>
            <div class="ig-header-text">
              <div class="ig-header-name-row">
                <strong id="omniChatName"><?= e($isCommentView ? $pageLabel : (string) ($selected['client'] ?? '')) ?></strong>
                <span class="ig-following" id="omniIgFollowing" <?= $isCommentView ? '' : 'hidden' ?>>Seguidos</span>
              </div>
              <div id="omniChatMeta" class="ig-header-meta">
                <?php if ($selected && !$isCommentView): ?>
                  <?= e($selected['phone']) ?> · <?= e(ucfirst($selected['channel'])) ?>
                <?php elseif ($selected && $isCommentView): ?>
                  <?= e(ucfirst($selChannel)) ?> · Comentario
                <?php endif; ?>
              </div>
            </div>
          </div>
          <div class="chat-header-actions">
            <span class="badge badge-comment" id="omniKindBadge" <?= $isCommentView ? '' : 'hidden' ?>>Comentario</span>
            <?php
              $selMode = strtolower((string) ($selected['handling_mode'] ?? 'ai'));
              $selAi = !empty($selected['attended_by_ai']);
              $selHuman = $selMode === 'human';
              $viewerId = (int) (($viewer['id'] ?? 0));
              $viewerRole = strtoupper((string) ($viewer['role'] ?? ''));
              $isAssignee = $selHuman && (int) ($selected['assigned_user_id'] ?? 0) === $viewerId;
              $canClose = $selHuman && ($isAssignee || $viewerRole === 'ADMIN');
              $canTake = !$selHuman || ($viewerRole === 'ADMIN' && !$isAssignee);
              $postMediaUrl = $isCommentView && $selected
                  ? url('omnicanalidad/post-media/' . (int) $selected['id'])
                  : '';
            ?>
            <span class="badge badge-ai" id="omniAiBadge" <?= $selAi ? '' : 'hidden' ?>>Atendido por IA</span>
            <span class="badge badge-human" id="omniHumanBadge" <?= $selHuman ? '' : 'hidden' ?>>
              Atendida por <span id="omniHumanName"><?= e((string) ($selected['assigned_name'] ?? 'agente')) ?></span>
            </span>
            <button type="button" class="btn btn-ghost btn-sm" id="omniTakeBtn" <?= $canTake && $selected ? '' : 'hidden' ?>>Tomar conversación</button>
            <button type="button" class="btn btn-ghost btn-sm" id="omniCloseBtn" <?= $canClose ? '' : 'hidden' ?>>Devolver a IA</button>
            <span class="badge badge-success" id="omniChatStatus"><?= e(ucfirst((string) ($selected['status'] ?? 'open'))) ?></span>
          </div>
        </div>

        <section class="comment-post-card" id="omniPostCard" <?= $isCommentView ? '' : 'hidden' ?>>
          <div class="comment-post-thumb" id="omniPostMedia">
            <?php if ($isCommentView && $postMediaUrl !== ''): ?>
              <a href="<?= e($selected['post_permalink'] ?: $postMediaUrl) ?>" target="_blank" rel="noopener" class="comment-post-thumb-link">
                <img src="<?= e($postMediaUrl) ?>" alt="Publicación" loading="lazy" width="112" height="112"
                  onerror="this.classList.add('is-broken'); this.closest('.comment-post-thumb')?.classList.add('is-broken');">
              </a>
            <?php else: ?>
              <div class="comment-post-thumb-empty" aria-hidden="true"></div>
            <?php endif; ?>
          </div>
          <div class="comment-post-body" id="omniCaptionBlock">
            <div class="comment-post-kicker">
              <span class="ig-avatar ig-avatar-sm" aria-hidden="true"><?= e($igInitial($pageLabel)) ?></span>
              <span class="ig-caption-user" id="omniPageName"><?= e($pageLabel) ?></span>
              <?php if ($isCommentView && !empty($selected['post_permalink'])): ?>
                <a class="comment-post-link" id="omniPostLink" href="<?= e($selected['post_permalink']) ?>" target="_blank" rel="noopener">Ver publicación</a>
              <?php else: ?>
                <a class="comment-post-link" id="omniPostLink" href="#" target="_blank" rel="noopener" hidden>Ver publicación</a>
              <?php endif; ?>
            </div>
            <p class="ig-caption-text" id="omniPostCaption"><?php
              if ($isCommentView && ($selected['post_caption'] ?? '') !== '') {
                  echo $formatIgCaption((string) $selected['post_caption']);
              } elseif ($isCommentView) {
                  echo e('Responde al comentario sobre esta publicación.');
              }
            ?></p>
          </div>
        </section>

        <div class="chat-messages" id="chatMessages">
          <?php foreach ($messages as $m): ?>
            <?php
              $type = (string) ($m['type'] ?? 'text');
              $media = $m['media_url'] ?? null;
              $from = (string) ($m['from'] ?? 'client');
              $isAgent = $from === 'agent' || $from === 'ai';
              $author = $from === 'ai' ? 'IA' : ($isAgent ? $pageLabel : (string) ($selected['client'] ?? 'Usuario'));
            ?>
            <?php if ($isCommentView): ?>
              <article class="ig-comment msg msg-<?= e($from) ?>">
                <div class="ig-avatar ig-avatar-sm" aria-hidden="true"><?= e($igInitial($author)) ?></div>
                <div class="ig-comment-body">
                  <div class="ig-comment-head">
                    <strong class="ig-comment-user"><?= e(ltrim($author, '@')) ?></strong>
                    <span class="msg-time"><?= e($m['time'] ?? '') ?></span>
                  </div>
                  <?php if ($media && $type === 'image'): ?>
                    <a href="<?= e($media) ?>" target="_blank" rel="noopener" class="msg-media"><img src="<?= e($media) ?>" alt="imagen" loading="lazy"></a>
                  <?php endif; ?>
                  <?php if (($m['text'] ?? '') !== ''): ?>
                    <div class="msg-text"><?= e($m['text']) ?></div>
                  <?php endif; ?>
                  <button type="button" class="ig-reply-link" data-reply-to="<?= e(ltrim($author, '@')) ?>">Responder</button>
                </div>
                <span class="ig-heart" aria-hidden="true">♡</span>
              </article>
            <?php else: ?>
              <?php
                $bubbleRole = match ($from) {
                    'ai' => 'ai',
                    'agent' => 'agent',
                    default => 'client',
                };
                $bubbleLabel = match ($bubbleRole) {
                    'ai' => 'IA',
                    'agent' => 'Agente',
                    default => '',
                };
              ?>
              <div class="msg msg-<?= e($bubbleRole) ?>" data-from="<?= e($bubbleRole) ?>">
                <?php if ($bubbleLabel !== ''): ?>
                  <span class="msg-label"><?= e($bubbleLabel) ?></span>
                <?php endif; ?>
                <?php if ($media && ($type === 'image' || $type === 'sticker')): ?>
                  <a href="<?= e($media) ?>" target="_blank" rel="noopener" class="msg-media"><img src="<?= e($media) ?>" alt="imagen" loading="lazy"></a>
                <?php elseif ($media && $type === 'video'): ?>
                  <video class="msg-media" controls preload="metadata" src="<?= e($media) ?>"></video>
                <?php elseif ($media && ($type === 'audio' || $type === 'voice')): ?>
                  <audio class="msg-audio" controls preload="metadata" src="<?= e($media) ?>"></audio>
                <?php elseif ($media && $type === 'document'): ?>
                  <?php
                    $docLabel = (string) ($m['text'] ?? 'Documento');
                    $docLabel = preg_replace('/^\[documento\]\s*/iu', '', $docLabel) ?: 'Documento';
                  ?>
                  <a href="<?= e($media) ?>" target="_blank" rel="noopener" class="msg-doc" download>📄 <?= e($docLabel) ?></a>
                <?php endif; ?>
                <?php
                  $showText = (string) ($m['text'] ?? '');
                  $placeholderOnly = in_array($showText, ['[mensaje]', '[imagen]', '[video]', '[audio]', '[documento]', '[sticker]'], true)
                    || preg_match('/^\[documento\]/iu', $showText);
                  if ($media && $placeholderOnly) {
                      $showText = '';
                  }
                ?>
                <?php if ($showText !== ''): ?>
                  <div class="msg-text"><?= e($showText) ?></div>
                <?php endif; ?>
                <span class="msg-time"><?= e($m['time']) ?></span>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>

        <div class="ig-actions" id="omniIgActions" <?= $isCommentView ? '' : 'hidden' ?> aria-hidden="true">
          <div class="ig-actions-left">
            <span>♡</span><span>💬</span><span>➤</span>
          </div>
          <span>🔖</span>
        </div>

        <?php
          $win = is_array($window ?? null) ? $window : null;
          $winChannel = strtolower((string) ($selected['channel'] ?? ''));
          $winIsMeta = in_array($winChannel, ['whatsapp', 'messenger', 'instagram'], true);
          $winCan = !$winIsMeta || !empty($win['can_reply']);
          $attendedAi = !empty($selected['attended_by_ai']);
          $composeLocked = $attendedAi || ($winIsMeta && !$winCan);
          $winText = '';
          if ($attendedAi) {
              $winText = 'Atendido por IA · Toma la conversación para escribir o enviar archivos.';
          } elseif ($winIsMeta && $win) {
              if ($winCan) {
                  $winText = 'Meta · ' . (($win['label'] ?? '') !== '' ? $win['label'] . '. ' : '') . 'Puedes responder con mensaje libre.';
              } elseif ($winChannel === 'whatsapp') {
                  $winText = (string) ($win['message'] ?? 'Fuera de la ventana 24h.') . ' Usa «Abrir por plantilla» para contactar con una HSM aprobada.';
              } else {
                  $winText = (string) ($win['message'] ?? 'Fuera de la ventana Meta. El cliente debe escribir primero.');
              }
          }
          $bannerClass = '';
          if ($attendedAi) {
              $bannerClass = ' is-closed is-ai-handoff';
          } elseif ($winIsMeta) {
              $bannerClass = $winCan ? ' is-open' : ' is-closed';
          }
        ?>
        <div class="chat-window-banner<?= e($bannerClass) ?>" id="chatWindowBanner"<?= ($winText !== '') ? '' : ' hidden' ?>><?= e($winText) ?></div>
        <form class="chat-compose<?= $composeLocked ? ' is-window-locked' : '' ?><?= $attendedAi ? ' is-ai-locked' : '' ?>" id="chatCompose" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <input type="hidden" name="conversation_id" id="omniConvId" value="<?= (int) ($selected['id'] ?? 0) ?>">
          <input type="hidden" name="ajax" value="1">
          <input type="file" id="chatMedia" name="media" accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv" hidden>
          <input type="file" id="chatPhotoInput" accept="image/*" hidden>
          <input type="file" id="chatVideoInput" accept="video/*" hidden>
          <input type="file" id="chatDocInput" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,application/pdf" hidden>

          <div class="chat-compose-tools" id="chatComposeTools">
            <div class="compose-toolbar" role="toolbar" aria-label="Acciones de mensaje">
              <button type="button" class="compose-tool" id="chatAudioBtn" title="Grabar audio" aria-label="Grabar audio">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="18" height="18" aria-hidden="true"><path d="M12 3a3 3 0 0 0-3 3v6a3 3 0 0 0 6 0V6a3 3 0 0 0-3-3z"/><path d="M19 10v1a7 7 0 0 1-14 0v-1"/><path d="M12 18v3"/></svg>
                <span class="compose-tool-label">Audio</span>
              </button>
              <button type="button" class="compose-tool" id="chatPhotoBtn" title="Subir foto" aria-label="Subir foto">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="18" height="18" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="M21 16l-5.5-5.5L8 18"/></svg>
                <span class="compose-tool-label">Foto</span>
              </button>
              <button type="button" class="compose-tool" id="chatVideoBtn" title="Subir video" aria-label="Subir video">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="18" height="18" aria-hidden="true"><rect x="3" y="6" width="13" height="12" rx="2"/><path d="M16 10l5-3v10l-5-3v-4z"/></svg>
                <span class="compose-tool-label">Video</span>
              </button>
              <button type="button" class="compose-tool" id="chatDocBtn" title="Subir documento" aria-label="Subir documento">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="18" height="18" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h4"/></svg>
                <span class="compose-tool-label">Doc</span>
              </button>
              <button type="button" class="compose-tool" id="chatEmojiBtn" title="Emojis" aria-label="Emojis" aria-expanded="false" aria-controls="chatEmojiPanel">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="18" height="18" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8.5 10h.01M15.5 10h.01"/><path d="M8.2 14c1 1.4 2.3 2.1 3.8 2.1s2.8-.7 3.8-2.1"/></svg>
                <span class="compose-tool-label">Emoji</span>
              </button>
              <button type="button" class="compose-tool" id="chatAiBtn" title="Mejorar con IA" aria-label="Mejorar con IA">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="18" height="18" aria-hidden="true"><path d="M12 3l1.4 4.2L18 9l-4.6 1.8L12 15l-1.4-4.2L6 9l4.6-1.8L12 3z"/><path d="M19 14l.7 2.1L22 17l-2.3.9L19 20l-.7-2.1L16 17l2.3-.9L19 14z"/></svg>
                <span class="compose-tool-label">IA</span>
              </button>
              <button type="button" class="compose-tool compose-tool--wa" id="chatTemplateBtn" title="Plantilla WhatsApp" aria-label="Plantilla WhatsApp" aria-expanded="false" aria-controls="chatTemplatePanel">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="18" height="18" aria-hidden="true"><path d="M8 7h8M8 11h8M8 15h5"/><rect x="4" y="3" width="16" height="18" rx="2"/></svg>
                <span class="compose-tool-label">Plantilla</span>
              </button>
            </div>
            <span class="chat-file-name" id="chatFileName" hidden></span>
            <span class="chat-audio-status" id="chatAudioStatus" hidden></span>

            <div class="compose-popover" id="chatEmojiPanel" hidden>
              <div class="compose-popover-head">Emojis</div>
              <div class="emoji-grid" id="chatEmojiGrid"></div>
            </div>

            <div class="compose-popover compose-popover--wide" id="chatTemplatePanel" hidden>
              <div class="compose-popover-head">Plantillas WhatsApp</div>
              <p class="compose-popover-hint" id="chatTemplateHint">
                Dentro de la ventana 24h puedes insertar el texto. Fuera de ventana, envía la plantilla HSM aprobada en Meta.
              </p>
              <div class="template-list" id="chatTemplateList">
                <?php foreach (($waTemplates ?? []) as $tpl): ?>
                  <?php
                    $metaName = trim((string) ($tpl['meta_name'] ?? $tpl['codigo'] ?? ''));
                    $tplLang = trim((string) ($tpl['idioma'] ?? 'es')) ?: 'es';
                  ?>
                  <button type="button" class="template-item"
                    data-template="<?= e((string) ($tpl['body'] ?? '')) ?>"
                    data-template-name="<?= e($metaName) ?>"
                    data-template-lang="<?= e($tplLang) ?>">
                    <?= e((string) ($tpl['nombre'] ?? $tpl['codigo'] ?? 'Plantilla')) ?>
                  </button>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <div class="ig-compose-avatar ig-avatar ig-avatar-sm" id="omniComposeAvatar" <?= $isCommentView ? '' : 'hidden' ?> aria-hidden="true"><?= e($igInitial($pageLabel)) ?></div>
          <textarea class="form-control chat-input" name="message" id="chatMessage" rows="1"
            placeholder="<?= $attendedAi ? 'Toma la conversación para responder…' : ($isCommentView ? 'Agrega un comentario…' : 'Escribe un mensaje…') ?>"
            autocomplete="off"<?= $composeLocked ? ' disabled' : '' ?>></textarea>
          <button type="submit" class="btn btn-primary" id="chatSendBtn"<?= $composeLocked ? ' disabled' : '' ?>><?= $isCommentView ? 'Publicar' : 'Enviar' ?></button>
        </form>
      </div>
    </div>
  </section>
</div>

<script>
window.ARYA_OMNI = {
  csrf: <?= json_encode(csrf_token()) ?>,
  channel: <?= json_encode($currentChannel) ?>,
  selectedId: <?= (int) ($selected['id'] ?? 0) ?>,
  viewer: {
    id: <?= (int) (($viewer['id'] ?? 0)) ?>,
    role: <?= json_encode(strtoupper((string) ($viewer['role'] ?? ''))) ?>,
    name: <?= json_encode((string) ($viewer['name'] ?? '')) ?>
  },
  urls: {
    conversations: <?= json_encode(url('omnicanalidad/api/conversaciones'), JSON_UNESCAPED_SLASHES) ?>,
    messagesTpl: <?= json_encode(url('omnicanalidad/api/mensajes/__ID__'), JSON_UNESCAPED_SLASHES) ?>,
    postMediaTpl: <?= json_encode(url('omnicanalidad/post-media/__ID__'), JSON_UNESCAPED_SLASHES) ?>,
    send: <?= json_encode(url('omnicanalidad/enviar'), JSON_UNESCAPED_SLASHES) ?>,
    take: <?= json_encode(url('omnicanalidad/tomar'), JSON_UNESCAPED_SLASHES) ?>,
    close: <?= json_encode(url('omnicanalidad/cerrar'), JSON_UNESCAPED_SLASHES) ?>,
    improve: <?= json_encode(url('omnicanalidad/ia/mejorar'), JSON_UNESCAPED_SLASHES) ?>
  }
};
</script>
<script src="<?= asset('js/omni.js') ?>?v=omni-no-closed1"></script>
