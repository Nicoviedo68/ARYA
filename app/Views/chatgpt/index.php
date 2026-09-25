<?php
/** @var list<array<string,mixed>> $conversations */
/** @var array<string,mixed>|null $activeChat */
/** @var list<array{id:int,role:string,content:string,created_at:string}> $messages */
/** @var list<array{id:string,label:string,hint:string}> $models */
/** @var string $defaultModel */
/** @var array{ok:bool,message:string,configured?:bool,endpoint?:string,estado?:string,tiene_api_key?:bool} $openaiStatus */
/** @var bool $showConfig */
/** @var string $userName */

$conversations = $conversations ?? [];
$activeChat = $activeChat ?? null;
$messages = $messages ?? [];
$models = $models ?? [];
$defaultModel = (string) ($defaultModel ?? 'gpt-5.6');
$openaiStatus = $openaiStatus ?? ['ok' => false, 'message' => '', 'configured' => false, 'endpoint' => '', 'estado' => 'inactivo', 'tiene_api_key' => false];
$showConfig = !empty($showConfig);
$userName = (string) ($userName ?? '');
$activeId = (int) ($activeChat['id'] ?? 0);
$activeModel = (string) ($activeChat['model'] ?? $defaultModel);
$configured = !empty($openaiStatus['configured']) || !empty($openaiStatus['ok']);
$endpointValue = (string) ($openaiStatus['endpoint'] ?? 'https://api.openai.com/v1/chat/completions');
$estadoActual = strtolower((string) ($openaiStatus['estado'] ?? 'inactivo'));
$tieneKey = !empty($openaiStatus['tiene_api_key']);
$csrf = csrf_token();
$sendUrl = url('chat-gpt/enviar');
?>

<div
  class="cgpt-shell"
  id="cgptRoot"
  data-send-url="<?= e($sendUrl) ?>"
  data-new-url="<?= e(url('chat-gpt/nuevo')) ?>"
  data-config-url="<?= e(url('chat-gpt/config')) ?>"
  data-show-base="<?= e(url('chat-gpt/chat')) ?>"
  data-rename-base="<?= e(url('chat-gpt/chat')) ?>"
  data-delete-base="<?= e(url('chat-gpt/chat')) ?>"
  data-csrf="<?= e($csrf) ?>"
  data-chat-id="<?= $activeId ?>"
  data-model="<?= e($activeModel) ?>"
  data-configured="<?= $configured ? '1' : '0' ?>"
  data-show-config="<?= $showConfig ? '1' : '0' ?>"
>
  <aside class="cgpt-rail" id="cgptRail" aria-label="Historial Chat GPT">
    <div class="cgpt-rail-top">
      <div class="cgpt-brand-row">
        <div class="cgpt-brand">
          <span class="cgpt-brand-mark" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8">
              <rect x="4" y="7" width="16" height="12" rx="3"/>
              <path d="M9 7V5a3 3 0 0 1 6 0v2"/><circle cx="9" cy="13" r="1.1" fill="currentColor"/><circle cx="15" cy="13" r="1.1" fill="currentColor"/>
            </svg>
          </span>
          <div class="cgpt-brand-text">
            <strong>Arya GPT</strong>
            <small>OpenAI · GPT-5.6</small>
          </div>
        </div>
        <button type="button" class="cgpt-rail-toggle" data-cgpt-rail-toggle title="Recoger panel" aria-label="Recoger panel de chats">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M15 6l-6 6 6 6"/>
          </svg>
        </button>
      </div>

      <div class="cgpt-rail-actions">
        <button type="button" class="cgpt-new-btn" data-cgpt-new title="Nuevo chat">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14"/></svg>
          <span class="cgpt-rail-label-txt">Nuevo chat</span>
        </button>
        <button type="button" class="cgpt-config-btn" data-cgpt-config-open title="Configurar OpenAI">
          <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <circle cx="12" cy="12" r="3"/><path d="M12 2v2.5M12 19.5V22M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M2 12h2.5M19.5 12H22M4.9 19.1l1.8-1.8M17.3 6.7l1.8-1.8"/>
          </svg>
          <span class="cgpt-rail-label-txt">Configurar OpenAI</span>
        </button>
      </div>
    </div>

    <div class="cgpt-rail-section">
      <p class="cgpt-rail-label">Chats</p>
      <div class="cgpt-chat-list" id="cgptChatList" aria-label="Historial de chats agrupado">
        <p class="cgpt-empty-list">Cargando chats…</p>
      </div>
    </div>

    <div class="cgpt-rail-foot">
      <?php if ($configured): ?>
        <span class="cgpt-status is-ok" id="cgptStatusPill">API OpenAI conectada</span>
      <?php else: ?>
        <button type="button" class="cgpt-status is-warn" id="cgptStatusPill" data-cgpt-config-open>Configurar OpenAI</button>
      <?php endif; ?>
    </div>
  </aside>

  <section class="cgpt-main">
    <header class="cgpt-main-head">
      <div class="cgpt-main-title-wrap">
        <button type="button" class="cgpt-rail-expand-btn" data-cgpt-rail-toggle title="Mostrar chats" aria-label="Expandir panel de chats" hidden>
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M9 6l6 6-6 6"/>
          </svg>
        </button>
        <div>
          <h2 id="cgptTitle"><?= e((string) ($activeChat['title'] ?? 'Chat GPT')) ?></h2>
          <p class="cgpt-sub">Asistente Arya con la API de OpenAI</p>
        </div>
      </div>
      <div class="cgpt-head-actions">
        <button type="button" class="btn btn-ghost btn-sm" data-cgpt-config-open>Configurar</button>
        <div class="cgpt-model-wrap">
          <label class="cgpt-model-label" for="cgptModel">Modelo</label>
          <select id="cgptModel" class="cgpt-model-select">
            <?php foreach ($models as $m): ?>
              <option value="<?= e($m['id']) ?>" <?= $activeModel === $m['id'] ? 'selected' : '' ?>>
                <?= e($m['label']) ?><?= !empty($m['hint']) ? ' · ' . e($m['hint']) : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </header>

    <?php if (!$configured): ?>
      <div class="cgpt-banner" id="cgptBanner">
        Falta la API key de OpenAI.
        <button type="button" class="cgpt-linkish" data-cgpt-config-open>Configúrala aquí</button>
        — también queda en Configuración → Integraciones.
      </div>
    <?php endif; ?>

    <div class="cgpt-thread" id="cgptThread" aria-live="polite">
      <div class="cgpt-welcome<?= $messages ? ' is-hidden' : '' ?>" id="cgptWelcome">
        <div class="cgpt-welcome-glow"></div>
        <p class="cgpt-hello">Hola<?= $userName !== '' ? ', ' . e(explode(' ', $userName)[0]) : '' ?>.</p>
        <h3>¿En qué te ayudo?</h3>
        <div class="cgpt-suggestions">
          <button type="button" class="cgpt-chip" data-suggest="Redacta un mensaje de WhatsApp para recuperar un lead frío de forma amable.">Redactar mensaje</button>
          <button type="button" class="cgpt-chip" data-suggest="Explícame cómo activar un workflow de n8n desde Arya y qué permisos necesita la API key.">Ayuda con n8n</button>
          <button type="button" class="cgpt-chip" data-suggest="Dame un resumen de buenas prácticas para atención omnicanal en un CRM.">Omnicanalidad</button>
        </div>
      </div>
      <div id="cgptMessages" class="cgpt-messages">
        <?php foreach ($messages as $msg): ?>
          <div class="cgpt-bubble cgpt-bubble-<?= e($msg['role']) ?>">
            <div class="cgpt-bubble-inner"><?= nl2br(e($msg['content'])) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <footer class="cgpt-composer-wrap">
      <form
        id="cgptForm"
        class="cgpt-composer"
        method="POST"
        action="<?= e($sendUrl) ?>"
        autocomplete="off"
      >
        <?= csrf_field() ?>
        <input type="hidden" name="conversation_id" id="cgptConversationId" value="<?= $activeId > 0 ? $activeId : '' ?>">
        <input type="hidden" name="model" id="cgptModelHidden" value="<?= e($activeModel) ?>">
        <button type="button" class="cgpt-plus" data-cgpt-new title="Nuevo chat" aria-label="Nuevo chat">+</button>
        <textarea
          id="cgptInput"
          class="cgpt-input"
          name="message"
          rows="1"
          placeholder="Pregunta a Arya GPT…"
          <?= $configured ? '' : 'disabled' ?>
        ></textarea>
        <button type="submit" class="cgpt-send" id="cgptSendBtn" title="Enviar" <?= $configured ? '' : 'disabled' ?>>
          <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M3.4 11.2 20.1 3.5c.7-.3 1.4.4 1.1 1.1l-7.7 16.7c-.3.7-1.3.7-1.6 0l-2.4-6.3-6.1-2.3c-.7-.3-.7-1.3 0-1.5z"/></svg>
        </button>
      </form>
      <p class="cgpt-disclaimer">Arya GPT puede cometer errores. Verifica la información importante. Modelo por defecto: GPT-5.6.</p>
    </footer>
  </section>
</div>

<div class="profile-modal n8n-config-modal" id="cgptConfigModal" <?= $showConfig ? '' : 'hidden' ?>>
  <div class="profile-modal-backdrop" data-cgpt-config-close></div>
  <div class="profile-modal-panel n8n-config-panel" role="dialog" aria-modal="true" aria-labelledby="cgptConfigTitle">
    <header class="profile-modal-header">
      <div>
        <p class="profile-modal-eyebrow">Integración OpenAI</p>
        <h2 id="cgptConfigTitle">Configurar Chat GPT</h2>
        <p class="n8n-config-lead">Se guarda en Configuración → Integraciones (código <code>openai</code>).</p>
      </div>
      <button type="button" class="btn btn-ghost btn-sm" data-cgpt-config-close aria-label="Cerrar">✕</button>
    </header>

    <form method="POST" action="<?= url('chat-gpt/config') ?>" class="profile-modal-form" id="cgptConfigForm" autocomplete="off">
      <?= csrf_field() ?>
      <div class="form-group">
        <label class="form-label" for="cgpt_endpoint">Endpoint OpenAI <span style="color:var(--danger)">*</span></label>
        <input class="form-control" type="url" id="cgpt_endpoint" name="endpoint_url" required
               value="<?= e($endpointValue) ?>"
               placeholder="https://api.openai.com/v1/chat/completions">
      </div>
      <div class="form-group">
        <label class="form-label" for="cgpt_api_key">
          API key
          <?php if ($tieneKey): ?>
            <span style="color:var(--text-secondary);font-weight:400">(ya guardada — deja vacío para no cambiar)</span>
          <?php else: ?>
            <span style="color:var(--danger)">*</span>
          <?php endif; ?>
        </label>
        <input class="form-control" type="password" id="cgpt_api_key" name="api_key" autocomplete="new-password"
               <?= $tieneKey ? '' : 'required' ?>
               placeholder="<?= $tieneKey ? '••••••••••••' : 'sk-…' ?>">
      </div>
      <div class="form-group">
        <label class="form-label" for="cgpt_estado">Estado</label>
        <select class="form-control" id="cgpt_estado" name="estado">
          <?php foreach (['activo', 'inactivo'] as $st): ?>
            <option value="<?= $st ?>" <?= $estadoActual === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="profile-modal-actions">
        <a class="btn btn-ghost" href="<?= url('configuracion?tab=integrations') ?>">Ver en Integraciones</a>
        <button type="button" class="btn btn-ghost" data-cgpt-config-close>Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>

<link rel="stylesheet" href="<?= asset('css/chatgpt.css') ?>?v=cgpt8">
<script src="<?= asset('js/chatgpt.js') ?>?v=cgpt8" defer></script>
<script>
  window.ARYA_CHATGPT = {
    conversations: <?= json_encode($conversations, JSON_UNESCAPED_UNICODE) ?>,
    messages: <?= json_encode($messages, JSON_UNESCAPED_UNICODE) ?>,
    chatId: <?= (int) $activeId ?>,
    model: <?= json_encode($activeModel, JSON_UNESCAPED_UNICODE) ?>
  };
</script>
