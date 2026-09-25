<?php

declare(strict_types=1);

namespace Arya\Controllers;

use Arya\Core\Controller;
use Arya\Models\Omnichannel;
use Arya\Models\WaTemplates;
use Arya\Services\MetaCloud;
use Arya\Services\OpenAiAssist;

final class OmnichannelController extends Controller
{
    public function index(): void
    {
        $this->requireMenuAccess('omnichannel');
        Omnichannel::bootstrap();
        // Sync ligero de pendientes (incluye comentarios feed recién llegados)
        Omnichannel::syncPendingIfAny();
        // Unifica nombres Messenger/IG: comentario → DM del mismo usuario
        Omnichannel::syncMessengerDisplayNames();

        $viewer = auth();
        $channel = (string) ($_GET['canal'] ?? 'all');
        $selectedId = (int) ($_GET['chat'] ?? ($_GET['comentario'] ?? 0));

        $channels = Omnichannel::channels();
        $conversations = Omnichannel::conversations($channel, $viewer);

        $selected = null;
        if ($selectedId > 0) {
            $selected = Omnichannel::findConversation($selectedId);
            if ($selected && !Omnichannel::canViewConversation($selected, $viewer)) {
                $selected = null;
            }
        }
        if (!$selected && $conversations) {
            $selected = $conversations[0];
        }

        if ($selected && ($selected['thread_kind'] ?? '') === 'comment') {
            if (($selected['post_id'] ?? '') === '') {
                Omnichannel::seedCommentPostRefs((int) $selected['id']);
            }
            if (!Omnichannel::hasLocalPostMedia($selected)) {
                Omnichannel::enrichCommentMediaIfNeeded((int) $selected['id']);
            }
            $selected = Omnichannel::findConversation((int) $selected['id']) ?? $selected;
        }

        // Mensajes solo del chat activo (una query)
        $messages = $selected ? Omnichannel::messages((int) $selected['id']) : [];
        $window = null;
        if ($selected) {
            $window = Omnichannel::messagingWindow($selected);
            $selected['can_reply'] = $window['can_reply'];
            $selected['window_hours'] = $window['window_hours'];
            $selected['window_expires_at'] = $window['expires_at'];
            $selected['window_label'] = $window['label'];
            $selected['window_message'] = $window['message'];
            $selected['last_inbound_at'] = $window['last_inbound_at'];
            $selected['unread'] = 0;
        }

        $this->view('omnichannel/index', [
            'title'          => 'Omnicanalidad',
            'active'         => 'omnichannel',
            'channels'       => $channels,
            'conversations'  => $conversations,
            'currentChannel' => $channel,
            'selected'       => $selected,
            'messages'       => $messages,
            'window'         => $window,
            'waTemplates'    => WaTemplates::activeForCompose(),
            'viewer'         => $viewer,
        ]);
    }

    public function show(string $id): void
    {
        // Deep-link → misma shell SPA (sin doble carga pesada)
        $_GET['chat'] = $id;
        $_GET['canal'] = 'all';
        $this->index();
    }

    public function apiConversations(): void
    {
        $this->requireMenuAccess('omnichannel');
        Omnichannel::bootstrap();
        Omnichannel::syncPendingIfAny();
        Omnichannel::syncMessengerDisplayNames();

        $viewer = auth();
        $channel = (string) ($_GET['canal'] ?? 'all');
        $this->json([
            'ok'            => true,
            'channel'       => $channel,
            'channels'      => Omnichannel::channels(),
            'conversations' => Omnichannel::conversations($channel, $viewer),
        ]);
    }

    public function apiMessages(string $id): void
    {
        $this->requireMenuAccess('omnichannel');
        Omnichannel::bootstrap();

        $viewer = auth();
        $conv = Omnichannel::findConversation($id);
        if (!$conv || !Omnichannel::canViewConversation($conv, $viewer)) {
            $this->json(['ok' => false, 'message' => 'Conversación no encontrada'], 404);
        }

        $hadUnread = (int) ($conv['unread'] ?? 0) > 0;
        if (($conv['thread_kind'] ?? '') === 'comment') {
            if (($conv['post_id'] ?? '') === '') {
                Omnichannel::seedCommentPostRefs((int) $conv['id']);
            }
            if (!Omnichannel::hasLocalPostMedia($conv)) {
                Omnichannel::enrichCommentMediaIfNeeded((int) $conv['id']);
            }
            $conv = Omnichannel::findConversation($conv['id']) ?? $conv;
        }
        $messages = Omnichannel::messages((int) $conv['id']);
        $conv['unread'] = 0;

        $window = Omnichannel::messagingWindow($conv);
        $conv['can_reply'] = $window['can_reply'];
        $conv['window_hours'] = $window['window_hours'];
        $conv['window_expires_at'] = $window['expires_at'];
        $conv['window_label'] = $window['label'];
        $conv['window_message'] = $window['message'];
        $conv['last_inbound_at'] = $window['last_inbound_at'];

        $payload = [
            'ok'           => true,
            'conversation' => $conv,
            'messages'     => $messages,
            'window'       => $window,
        ];
        // Solo recalcular badges si había unread (ahorra 1 query)
        if ($hadUnread) {
            $payload['channels'] = Omnichannel::channels();
        }
        $this->json($payload);
    }

    /** Campana global + badge del menú Omnicanalidad. */
    public function apiNotifications(): void
    {
        $this->requireMenuAccess('omnichannel');
        Omnichannel::bootstrap();
        $this->json(Omnichannel::notificationSummary());
    }

    /**
     * Sirve la imagen del post de un comentario (proxy local).
     * Evita CDN Meta bloqueada en el navegador.
     */
    public function postMedia(string $id): void
    {
        $this->requireMenuAccess('omnichannel');
        Omnichannel::bootstrap();

        $viewer = auth();
        $conv = Omnichannel::findConversation($id);
        if (!$conv || !Omnichannel::canViewConversation($conv, $viewer)) {
            $this->servePostMediaPlaceholder(404);
        }
        if (($conv['thread_kind'] ?? '') !== 'comment') {
            $this->servePostMediaPlaceholder(404);
        }

        $path = Omnichannel::ensureLocalPostMedia((int) $id);
        if ($path !== null && is_file($path)) {
            $mime = @mime_content_type($path) ?: 'image/jpeg';
            if (!str_starts_with($mime, 'image/') && !str_starts_with($mime, 'video/')) {
                $mime = 'image/jpeg';
            }
            header('Content-Type: ' . $mime);
            header('Cache-Control: private, max-age=3600');
            header('X-Content-Type-Options: nosniff');
            readfile($path);
            exit;
        }

        $this->servePostMediaPlaceholder(404);
    }

    /** @return never */
    private function servePostMediaPlaceholder(int $status = 404): void
    {
        http_response_code($status);
        header('Content-Type: image/svg+xml; charset=UTF-8');
        header('Cache-Control: no-store');
        echo <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="480" height="480" viewBox="0 0 480 480" role="img" aria-label="Sin imagen">
  <rect width="480" height="480" fill="#14181f"/>
  <rect x="120" y="140" width="240" height="180" rx="16" fill="none" stroke="#3a4553" stroke-width="3"/>
  <circle cx="190" cy="200" r="22" fill="#3a4553"/>
  <path d="M145 290 L210 230 L250 270 L295 225 L335 290 Z" fill="#3a4553"/>
  <text x="240" y="360" text-anchor="middle" fill="#8b96a5" font-family="Segoe UI, Arial, sans-serif" font-size="18">Sin vista previa</text>
</svg>
SVG;
        exit;
    }

    public function send(): void
    {
        $this->validateCsrf();
        $this->requireMenuAccess('omnichannel');
        $ajax = $this->wantsJson();

        $conversationId = (int) ($_POST['conversation_id'] ?? 0);
        $text = trim((string) ($_POST['message'] ?? ''));
        $sendAs = strtolower(trim((string) ($_POST['send_as'] ?? 'text')));
        $templateName = trim((string) ($_POST['template_name'] ?? ''));
        $templateLang = trim((string) ($_POST['template_lang'] ?? 'es'));
        $viewer = auth();
        $conv = Omnichannel::findConversation($conversationId);

        if (!$conv || !Omnichannel::canViewConversation($conv, $viewer)) {
            $this->failSend('Conversación no encontrada.', $conversationId, $ajax, 404);
        }

        // Solo el asignado (o ADMIN) responde si está en modo humano
        if (!$this->canRespondToConversation($conv, $viewer)) {
            $mode = strtolower((string) ($conv['handling_mode'] ?? 'ai'));
            if ($mode !== 'human' && !empty($conv['attended_by_ai'])) {
                $this->failSend(
                    'Esta conversación la atiende la IA. Usa «Tomar conversación» para responder.',
                    $conversationId,
                    $ajax,
                    403
                );
            }
            $this->failSend('Solo el agente asignado puede responder esta conversación.', $conversationId, $ajax, 403);
        }

        $channel = strtolower((string) ($conv['channel'] ?? ''));
        if (!in_array($channel, ['whatsapp', 'messenger', 'instagram'], true)) {
            $this->failSend('Canal no soportado para envío.', $conversationId, $ajax);
        }

        // Plantilla HSM WhatsApp (abre conversación fuera de ventana 24h)
        if ($sendAs === 'template' || $templateName !== '') {
            if ($channel !== 'whatsapp') {
                $this->failSend('Las plantillas HSM solo aplican a WhatsApp.', $conversationId, $ajax);
            }
            if ($templateName === '') {
                $this->failSend('Selecciona una plantilla aprobada.', $conversationId, $ajax);
            }
            $sent = MetaCloud::sendTemplate($conv, $templateName, $templateLang !== '' ? $templateLang : 'es');
            if (!$sent['ok']) {
                $this->failSend('No se pudo enviar plantilla: ' . $sent['message'], $conversationId, $ajax);
            }
            $body = $text !== '' ? $text : ('[plantilla] ' . $templateName);
            $store = Omnichannel::storeOutbound(
                $conversationId,
                $body,
                'template',
                null,
                $sent['external_id'] ?? null
            );
            if (!$store['ok']) {
                $this->failSend('Plantilla enviada a Meta, pero no se guardó local: ' . $store['message'], $conversationId, $ajax);
            }
            if ($ajax) {
                $this->json([
                    'ok'      => true,
                    'message' => 'Plantilla enviada.',
                    'msg'     => [
                        'id'        => $store['id'] ?? null,
                        'from'      => 'agent',
                        'text'      => $body,
                        'type'      => 'template',
                        'media_url' => null,
                        'time'      => date('H:i'),
                    ],
                    'conversation' => Omnichannel::findConversation($conversationId),
                ]);
            }
            flash('success', 'Plantilla enviada.');
            redirect('omnicanalidad?chat=' . $conversationId);
        }

        $hasFile = isset($_FILES['media']) && (int) ($_FILES['media']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($text === '' && !$hasFile) {
            $this->failSend('Escribe un mensaje o adjunta un archivo.', $conversationId, $ajax);
        }

        // Meta: bloquear free-form fuera de ventana (24h DM / 7d comentario)
        $window = Omnichannel::messagingWindow($conv);
        if (!$window['can_reply']) {
            $hint = $channel === 'whatsapp'
                ? ' Fuera de la ventana 24h usa «Abrir por plantilla».'
                : '';
            $this->failSend(
                ($window['message'] !== ''
                    ? $window['message']
                    : 'Fuera de la ventana de mensajería Meta. El cliente debe escribir primero.') . $hint,
                $conversationId,
                $ajax
            );
        }

        if ($hasFile) {
            if ($channel !== 'whatsapp') {
                $this->failSend('Los adjuntos por ahora solo están en WhatsApp.', $conversationId, $ajax);
            }
            $this->sendMediaMessage($conv, $text, $ajax);
            return;
        }

        $sent = MetaCloud::sendForConversation($conv, $text);
        if (!$sent['ok']) {
            $this->failSend('No se pudo enviar: ' . $sent['message'], $conversationId, $ajax);
        }

        $msgType = ((string) ($conv['thread_kind'] ?? '')) === 'comment' ? 'comment' : 'text';
        $store = Omnichannel::storeOutbound(
            $conversationId,
            $text,
            $msgType,
            null,
            $sent['external_id'] ?? null
        );
        if (!$store['ok']) {
            $this->failSend('Enviado a Meta, pero no se guardó local: ' . $store['message'], $conversationId, $ajax);
        }

        if ($ajax) {
            $this->json([
                'ok'      => true,
                'message' => 'Mensaje enviado.',
                'msg'     => [
                    'id'        => $store['id'] ?? null,
                    'from'      => 'agent',
                    'text'      => $text,
                    'type'      => 'text',
                    'media_url' => null,
                    'time'      => date('H:i'),
                ],
                'conversation' => Omnichannel::findConversation($conversationId),
            ]);
        }

        flash('success', 'Mensaje enviado.');
        redirect('omnicanalidad?chat=' . $conversationId);
    }

    public function take(): void
    {
        $this->validateCsrf();
        $this->requireMenuAccess('omnichannel');
        $ajax = $this->wantsJson();
        $viewer = auth();
        if (!$viewer) {
            $this->failSend('Sesión expirada.', 0, $ajax, 401);
        }

        $conversationId = (int) ($_POST['conversation_id'] ?? 0);
        $result = Omnichannel::takeConversation($conversationId, $viewer);
        if ($ajax) {
            $this->json($result, $result['ok'] ? 200 : 422);
        }
        flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect($conversationId > 0 ? ('omnicanalidad?chat=' . $conversationId) : 'omnicanalidad');
    }

    public function close(): void
    {
        $this->validateCsrf();
        $this->requireMenuAccess('omnichannel');
        $ajax = $this->wantsJson();
        $viewer = auth();
        $conversationId = (int) ($_POST['conversation_id'] ?? 0);
        $conv = Omnichannel::findConversation($conversationId);

        if (!$conv || !Omnichannel::canViewConversation($conv, $viewer)) {
            $this->failSend('Conversación no encontrada.', $conversationId, $ajax, 404);
        }
        if (!$this->canRespondToConversation($conv, $viewer)) {
            $this->failSend('Solo el agente asignado o un ADMIN pueden cerrar esta conversación.', $conversationId, $ajax, 403);
        }

        $result = Omnichannel::closeConversation($conversationId);
        if ($ajax) {
            $this->json($result, $result['ok'] ? 200 : 422);
        }
        flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect($conversationId > 0 ? ('omnicanalidad?chat=' . $conversationId) : 'omnicanalidad');
    }

    /**
     * @param array<string,mixed> $conv
     * @param array{id?:int|string,role?:string}|null $viewer
     */
    /**
     * @param array<string,mixed> $conv
     * @param array{id?:int|string,role?:string}|null $viewer
     */
    private function canRespondToConversation(array $conv, ?array $viewer): bool
    {
        $mode = strtolower((string) ($conv['handling_mode'] ?? 'ai'));
        $assignedId = (int) ($conv['assigned_user_id'] ?? 0);

        // Atendida por IA activa → el humano debe tomar primero
        if ($mode !== 'human' && !empty($conv['attended_by_ai'])) {
            return false;
        }

        if ($mode !== 'human' || $assignedId < 1) {
            return true;
        }
        if (!$viewer) {
            return false;
        }
        $role = strtoupper((string) ($viewer['role'] ?? ''));
        if ($role === 'ADMIN') {
            return true;
        }
        return (int) ($viewer['id'] ?? 0) === $assignedId;
    }

    public function improve(): void
    {
        $this->validateCsrf();
        $this->requireMenuAccess('omnichannel');

        $draft = trim((string) ($_POST['message'] ?? ''));
        $conversationId = (int) ($_POST['conversation_id'] ?? 0);

        $context = '';
        if ($conversationId > 0) {
            $msgs = Omnichannel::messages($conversationId, false);
            foreach (array_slice($msgs, -6) as $m) {
                $who = ($m['from'] ?? '') === 'agent' ? 'Agente' : 'Cliente';
                $context .= $who . ': ' . ($m['text'] ?? '') . "\n";
            }
        }

        $this->json(OpenAiAssist::improveMessage($draft, $context));
    }

    /**
     * @param array<string,mixed> $conv
     */
    private function sendMediaMessage(array $conv, string $caption, bool $ajax): void
    {
        $conversationId = (int) $conv['id'];
        $file = $_FILES['media'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->failSend('Error al subir el archivo.', $conversationId, $ajax);
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        $orig = (string) ($file['name'] ?? 'file');
        $mime = (string) ($file['type'] ?? 'application/octet-stream');

        if ($size < 1 || $size > 16 * 1024 * 1024) {
            $this->failSend('El archivo debe pesar entre 1 byte y 16 MB.', $conversationId, $ajax);
        }

        $detected = $this->detectMediaType($mime, $orig);
        if ($detected === null) {
            $this->failSend('Solo imagen, video, audio o documento.', $conversationId, $ajax);
        }
        [$type, $mime] = $detected;

        $dir = public_path('uploads/omni');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $this->failSend('No se pudo crear carpeta de uploads.', $conversationId, $ajax);
        }

        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION) ?: 'bin');
        $safe = 'omni_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . preg_replace('/[^a-z0-9]/', '', $ext);
        $dest = $dir . DIRECTORY_SEPARATOR . $safe;
        if (!move_uploaded_file($tmp, $dest)) {
            $this->failSend('No se pudo guardar el archivo.', $conversationId, $ajax);
        }

        $publicUrl = rtrim(app_base_url(), '/') . '/uploads/omni/' . $safe;
        $to = (string) ($conv['external_contact'] ?? '');

        $sent = MetaCloud::sendMedia($to, $type, $dest, $mime, $caption !== '' ? $caption : null);
        if (!$sent['ok']) {
            $this->failSend('No se pudo enviar media: ' . $sent['message'], $conversationId, $ajax);
        }

        $body = $caption !== '' ? $caption : ('[' . $type . '] ' . $orig);
        $store = Omnichannel::storeOutbound(
            $conversationId,
            $body,
            $type,
            $publicUrl,
            $sent['external_id'] ?? null
        );
        if (!$store['ok']) {
            $this->failSend('Enviado, pero no se guardó local: ' . $store['message'], $conversationId, $ajax);
        }

        if ($ajax) {
            $this->json([
                'ok'      => true,
                'message' => ucfirst($type) . ' enviado.',
                'msg'     => [
                    'id'        => $store['id'] ?? null,
                    'from'      => 'agent',
                    'text'      => $body,
                    'type'      => $type,
                    'media_url' => $publicUrl,
                    'time'      => date('H:i'),
                ],
                'conversation' => Omnichannel::findConversation($conversationId),
            ]);
        }

        flash('success', ucfirst($type) . ' enviado.');
        redirect('omnicanalidad?chat=' . $conversationId);
    }

    private function failSend(string $message, int $conversationId, bool $ajax, int $status = 422): void
    {
        if ($ajax) {
            $this->json(['ok' => false, 'message' => $message], $status);
        }
        flash('error', $message);
        redirect($conversationId > 0 ? ('omnicanalidad?chat=' . $conversationId) : 'omnicanalidad');
    }

    private function wantsJson(): bool
    {
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        $xhr = (string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
        return str_contains($accept, 'application/json')
            || strcasecmp($xhr, 'XMLHttpRequest') === 0
            || (string) ($_POST['ajax'] ?? '') === '1';
    }

    /** @return array{0:string,1:string}|null */
    private function detectMediaType(string $mime, string $filename): ?array
    {
        $mime = strtolower($mime);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $map = [
            'jpg' => ['image', 'image/jpeg'], 'jpeg' => ['image', 'image/jpeg'],
            'png' => ['image', 'image/png'], 'webp' => ['image', 'image/webp'],
            'gif' => ['image', 'image/gif'], 'mp4' => ['video', 'video/mp4'],
            'webm' => ['video', 'video/webm'], '3gp' => ['video', 'video/3gpp'],
            'ogg' => ['audio', 'audio/ogg'], 'oga' => ['audio', 'audio/ogg'],
            'mp3' => ['audio', 'audio/mpeg'], 'm4a' => ['audio', 'audio/mp4'],
            'aac' => ['audio', 'audio/aac'], 'amr' => ['audio', 'audio/amr'],
            'pdf' => ['document', 'application/pdf'],
            'doc' => ['document', 'application/msword'],
            'docx' => ['document', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xls' => ['document', 'application/vnd.ms-excel'],
            'xlsx' => ['document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'txt' => ['document', 'text/plain'],
            'csv' => ['document', 'text/csv'],
        ];
        if (isset($map[$ext])) {
            return $map[$ext];
        }
        if (str_starts_with($mime, 'image/')) {
            return ['image', $mime];
        }
        if (str_starts_with($mime, 'video/')) {
            return ['video', $mime];
        }
        if (str_starts_with($mime, 'audio/')) {
            return ['audio', $mime];
        }
        if (
            str_starts_with($mime, 'application/pdf')
            || str_starts_with($mime, 'application/msword')
            || str_contains($mime, 'officedocument')
            || str_starts_with($mime, 'text/plain')
            || str_starts_with($mime, 'text/csv')
        ) {
            return ['document', $mime];
        }
        return null;
    }
}
