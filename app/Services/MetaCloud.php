<?php

declare(strict_types=1);

namespace Arya\Services;

use Arya\Models\TokenMeta;

/**
 * Envío Meta: WhatsApp + Messenger + Instagram (DM y comentarios).
 * Alineado a los nodos n8n Enviar Mensaje / Enviar Comentario.
 */
final class MetaCloud
{
    private const GRAPH_FB = 'https://graph.facebook.com/v24.0';
    private const GRAPH_IG = 'https://graph.instagram.com/v24.0';
    private const GRAPH_WA = 'https://graph.facebook.com/v25.0';

    /**
     * Despacha envío según canal y tipo de hilo (mensaje / comentario).
     *
     * @param array<string,mixed> $conv
     * @return array{ok:bool,message:string,external_id?:?string,raw?:array}
     */
    public static function sendForConversation(array $conv, string $text): array
    {
        $channel = strtolower((string) ($conv['channel'] ?? ''));
        $kind = strtolower((string) ($conv['thread_kind'] ?? 'message'));
        $text = trim($text);
        if ($text === '') {
            return ['ok' => false, 'message' => 'Mensaje vacío.'];
        }

        if ($kind === 'comment') {
            $commentId = trim((string) ($conv['comment_id'] ?? $conv['external_contact'] ?? ''));
            $commentId = preg_replace('/^cmt:/', '', $commentId) ?? $commentId;
            if ($commentId === '') {
                return ['ok' => false, 'message' => 'Falta el ID del comentario.'];
            }
            return match ($channel) {
                'instagram' => self::replyInstagramComment($commentId, $text),
                'messenger' => self::replyMessengerComment($commentId, $text),
                default     => ['ok' => false, 'message' => 'Comentarios no soportados en este canal.'],
            };
        }

        $to = trim((string) ($conv['external_contact'] ?? ''));
        if ($to === '') {
            return ['ok' => false, 'message' => 'Sin destinatario.'];
        }

        return match ($channel) {
            'whatsapp'  => self::sendWhatsAppText($to, $text),
            'messenger' => self::sendMessengerText($to, $text),
            'instagram' => self::sendInstagramText($to, $text),
            default     => ['ok' => false, 'message' => 'Canal no soportado para envío.'],
        };
    }

    /** @return array{ok:bool,message:string,external_id?:?string,raw?:array} */
    public static function sendText(string $to, string $text, ?string $phoneNumberId = null, ?string $pageId = null): array
    {
        return self::sendWhatsAppText($to, $text);
    }

    /** @return array{ok:bool,message:string,external_id?:?string,raw?:array} */
    public static function sendWhatsAppText(string $to, string $text): array
    {
        $creds = TokenMeta::whatsappCredentials();
        if (!$creds) {
            return ['ok' => false, 'message' => 'No hay WhatsApp activo en token_meta (page_id + token EAA…).'];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => preg_replace('/\D+/', '', $to) ?: $to,
            'type'              => 'text',
            'text'              => [
                'preview_url' => false,
                'body'        => $text,
            ],
        ];

        return self::postJson(
            self::GRAPH_WA . '/' . rawurlencode($creds['phone_number_id']) . '/messages',
            $creds['token'],
            $payload,
            'whatsapp'
        );
    }

    /**
     * Envía plantilla HSM aprobada (abre / reabre ventana WhatsApp).
     *
     * @param array<string,mixed> $conv
     * @param list<array<string,mixed>>|null $components
     * @return array{ok:bool,message:string,external_id?:?string,raw?:array}
     */
    public static function sendTemplate(
        array $conv,
        string $templateName,
        string $language = 'es',
        ?array $components = null
    ): array {
        $to = trim((string) ($conv['external_contact'] ?? $conv['phone'] ?? ''));
        $templateName = trim($templateName);
        if ($to === '' || $templateName === '') {
            return ['ok' => false, 'message' => 'Falta destinatario o nombre de plantilla.'];
        }

        $creds = TokenMeta::whatsappCredentials();
        if (!$creds) {
            return ['ok' => false, 'message' => 'No hay WhatsApp activo en token_meta (page_id + token EAA…).'];
        }

        $lang = trim($language) !== '' ? trim($language) : 'es';
        // Meta suele usar es / es_CO / es_MX
        if (!str_contains($lang, '_') && strlen($lang) === 2) {
            // dejar código corto; Graph acepta "es"
        }

        $template = [
            'name'     => $templateName,
            'language' => ['code' => $lang],
        ];
        if (is_array($components) && $components !== []) {
            $template['components'] = $components;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => preg_replace('/\D+/', '', $to) ?: $to,
            'type'              => 'template',
            'template'          => $template,
        ];

        return self::postJson(
            self::GRAPH_WA . '/' . rawurlencode($creds['phone_number_id']) . '/messages',
            $creds['token'],
            $payload,
            'whatsapp-template'
        );
    }

    /**
     * Messenger DM — n8n: POST graph.facebook.com/v24.0/me/messages
     * @return array{ok:bool,message:string,external_id?:?string,raw?:array}
     */
    public static function sendMessengerText(string $recipientId, string $text): array
    {
        $creds = TokenMeta::channelCredentials('messenger');
        if (!$creds) {
            return ['ok' => false, 'message' => 'No hay Messenger activo en token_meta (token de página EAA…).'];
        }

        $pageId = $creds['page_id'];
        $endpoint = $pageId !== ''
            ? self::GRAPH_FB . '/' . rawurlencode($pageId) . '/messages'
            : self::GRAPH_FB . '/me/messages';

        return self::postJson($endpoint, $creds['token'], [
            'recipient' => ['id' => $recipientId],
            'messaging_type' => 'RESPONSE',
            'message'   => ['text' => $text],
        ], 'messenger');
    }

    /**
     * Instagram DM — n8n: POST graph.instagram.com/v24.0/me/messages
     * @return array{ok:bool,message:string,external_id?:?string,raw?:array}
     */
    public static function sendInstagramText(string $recipientId, string $text): array
    {
        $creds = TokenMeta::channelCredentials('instagram');
        if (!$creds) {
            return ['ok' => false, 'message' => 'No hay Instagram activo en token_meta (token IG…).'];
        }

        return self::postJson(self::GRAPH_IG . '/me/messages', $creds['token'], [
            'recipient' => ['id' => $recipientId],
            'message'   => ['text' => $text],
        ], 'instagram');
    }

    /**
     * Respuesta a comentario Instagram — n8n: POST graph.instagram.com/{id}/replies
     * @return array{ok:bool,message:string,external_id?:?string,raw?:array}
     */
    public static function replyInstagramComment(string $commentId, string $text): array
    {
        $creds = TokenMeta::channelCredentials('instagram');
        if (!$creds) {
            return ['ok' => false, 'message' => 'No hay Instagram activo en token_meta.'];
        }

        return self::postJson(
            self::GRAPH_IG . '/' . rawurlencode($commentId) . '/replies',
            $creds['token'],
            ['message' => $text],
            'instagram-comment'
        );
    }

    /**
     * Respuesta a comentario Facebook/Messenger — n8n: POST graph.facebook.com/v24.0/{id}/comments
     * @return array{ok:bool,message:string,external_id?:?string,raw?:array}
     */
    public static function replyMessengerComment(string $commentId, string $text): array
    {
        $creds = TokenMeta::channelCredentials('messenger');
        if (!$creds) {
            return ['ok' => false, 'message' => 'No hay Messenger activo en token_meta.'];
        }

        return self::postJson(
            self::GRAPH_FB . '/' . rawurlencode($commentId) . '/comments',
            $creds['token'],
            ['message' => $text],
            'messenger-comment'
        );
    }

    /**
     * Nombre de perfil Messenger (PSID) vía Graph.
     */
    public static function fetchMessengerProfileName(string $psid, ?string $pageId = null): ?string
    {
        $psid = trim($psid);
        if ($psid === '' || !preg_match('/^\d{5,}$/', $psid)) {
            return null;
        }
        $creds = TokenMeta::channelCredentials('messenger', $pageId);
        if (!$creds) {
            return null;
        }
        $url = self::GRAPH_FB . '/' . rawurlencode($psid)
            . '?fields=' . rawurlencode('name,first_name,last_name')
            . '&access_token=' . rawurlencode($creds['token']);
        $data = self::getJson($url);
        if (!$data['ok']) {
            return null;
        }
        $json = $data['raw'] ?? [];
        $name = trim((string) ($json['name'] ?? ''));
        if ($name === '') {
            $first = trim((string) ($json['first_name'] ?? ''));
            $last = trim((string) ($json['last_name'] ?? ''));
            $name = trim($first . ' ' . $last);
        }
        return $name !== '' ? $name : null;
    }

    /**
     * Nombre/username Instagram (IGSID).
     */
    public static function fetchInstagramProfileName(string $igsid): ?string
    {
        $igsid = trim($igsid);
        if ($igsid === '') {
            return null;
        }
        $creds = TokenMeta::channelCredentials('instagram');
        if (!$creds) {
            return null;
        }
        $url = self::GRAPH_IG . '/' . rawurlencode($igsid)
            . '?fields=' . rawurlencode('name,username')
            . '&access_token=' . rawurlencode($creds['token']);
        $data = self::getJson($url);
        if (!$data['ok']) {
            $url = self::GRAPH_FB . '/' . rawurlencode($igsid)
                . '?fields=' . rawurlencode('name,username')
                . '&access_token=' . rawurlencode($creds['token']);
            $data = self::getJson($url);
            if (!$data['ok']) {
                return null;
            }
        }
        $json = $data['raw'] ?? [];
        $name = trim((string) ($json['name'] ?? ''));
        if ($name === '') {
            $user = trim((string) ($json['username'] ?? ''));
            $name = $user !== '' ? ('@' . ltrim($user, '@')) : '';
        }
        return $name !== '' ? $name : null;
    }

    /**
     * Obtiene imagen/enlace de la publicación comentada (Facebook Page).
     *
     * @return array{ok:bool,post_id?:string,media_url?:?string,permalink?:?string,caption?:?string,message?:string}
     */
    public static function fetchFacebookPostContext(string $postId, ?string $pageId = null): array
    {
        $postId = trim($postId);
        if ($postId === '') {
            return ['ok' => false, 'message' => 'Sin post_id'];
        }
        if (($pageId === null || $pageId === '') && str_contains($postId, '_')) {
            $pageId = explode('_', $postId, 2)[0];
        }
        $creds = TokenMeta::channelCredentials('messenger', $pageId);
        if (!$creds) {
            return ['ok' => false, 'message' => 'Sin token Messenger'];
        }

        $fields = 'full_picture,permalink_url,message,attachments{media_type,media,url,title,description,type}';
        $url = self::GRAPH_FB . '/' . rawurlencode($postId)
            . '?fields=' . rawurlencode($fields)
            . '&access_token=' . rawurlencode($creds['token']);

        $data = self::getJson($url);
        if (!$data['ok']) {
            // Aun sin Graph, devolvemos permalink público útil
            return [
                'ok'        => false,
                'message'   => (string) ($data['message'] ?? 'Graph error'),
                'post_id'   => $postId,
                'media_url' => null,
                'permalink' => 'https://www.facebook.com/' . rawurlencode($postId),
                'caption'   => null,
            ];
        }
        $json = $data['raw'] ?? [];
        $media = null;
        if (!empty($json['full_picture'])) {
            $media = (string) $json['full_picture'];
        } elseif (!empty($json['attachments']['data'][0]['media']['image']['src'])) {
            $media = (string) $json['attachments']['data'][0]['media']['image']['src'];
        } elseif (!empty($json['attachments']['data'][0]['url'])) {
            $media = (string) $json['attachments']['data'][0]['url'];
        }

        return [
            'ok'        => true,
            'post_id'   => $postId,
            'media_url' => $media,
            'permalink' => isset($json['permalink_url'])
                ? (string) $json['permalink_url']
                : ('https://www.facebook.com/' . $postId),
            'caption'   => isset($json['message']) ? (string) $json['message'] : null,
        ];
    }

    /** Resuelve media_id de un comentario IG (útil en replies donde el webhook no trae media). */
    public static function fetchInstagramCommentMediaId(string $commentId): string
    {
        $commentId = trim($commentId);
        if ($commentId === '') {
            return '';
        }
        $creds = TokenMeta::channelCredentials('instagram');
        if (!$creds) {
            return '';
        }
        foreach ([self::GRAPH_IG, self::GRAPH_FB] as $base) {
            $url = $base . '/' . rawurlencode($commentId)
                . '?fields=' . rawurlencode('media{id},parent_id')
                . '&access_token=' . rawurlencode($creds['token']);
            $data = self::getJson($url);
            if (!$data['ok']) {
                continue;
            }
            $json = $data['raw'] ?? [];
            $mid = trim((string) ($json['media']['id'] ?? ''));
            if ($mid !== '') {
                return $mid;
            }
        }
        return '';
    }

    /**
     * Obtiene media de publicación Instagram comentada.
     *
     * @return array{ok:bool,post_id?:string,media_url?:?string,permalink?:?string,caption?:?string,message?:string}
     */
    public static function fetchInstagramMediaContext(string $mediaId): array
    {
        $mediaId = trim($mediaId);
        if ($mediaId === '') {
            return ['ok' => false, 'message' => 'Sin media_id'];
        }
        $creds = TokenMeta::channelCredentials('instagram');
        if (!$creds) {
            return ['ok' => false, 'message' => 'Sin token Instagram'];
        }

        $fields = 'media_url,thumbnail_url,permalink,caption,media_type';
        $url = self::GRAPH_IG . '/' . rawurlencode($mediaId)
            . '?fields=' . rawurlencode($fields)
            . '&access_token=' . rawurlencode($creds['token']);

        $data = self::getJson($url);
        if (!$data['ok']) {
            // fallback graph.facebook.com (algunas cuentas IG Business)
            $urlFb = self::GRAPH_FB . '/' . rawurlencode($mediaId)
                . '?fields=' . rawurlencode($fields)
                . '&access_token=' . rawurlencode($creds['token']);
            $data = self::getJson($urlFb);
            if (!$data['ok']) {
                return $data;
            }
        }
        $json = $data['raw'] ?? [];
        $media = (string) ($json['media_url'] ?? $json['thumbnail_url'] ?? '');
        if ($media === '') {
            $media = null;
        }

        return [
            'ok'        => true,
            'post_id'   => $mediaId,
            'media_url' => $media,
            'permalink' => isset($json['permalink']) ? (string) $json['permalink'] : null,
            'caption'   => isset($json['caption']) ? (string) $json['caption'] : null,
        ];
    }

    /**
     * Resuelve post/media desde el payload webhook de comentario.
     *
     * @param array<string,mixed> $value
     * @return array{ok:bool,post_id?:string,media_url?:?string,permalink?:?string,caption?:?string,message?:string}
     */
    public static function resolveCommentPostContext(string $channel, array $value): array
    {
        $channel = strtolower($channel);
        if ($channel === 'instagram') {
            $mediaId = '';
            if (is_array($value['media'] ?? null)) {
                $mediaId = trim((string) ($value['media']['id'] ?? ''));
            }
            if ($mediaId === '') {
                $mediaId = trim((string) ($value['media_id'] ?? ''));
            }
            // parent_id en replies suele ser otro comentario, no el media
            if ($mediaId === '') {
                $commentId = trim((string) ($value['id'] ?? $value['comment_id'] ?? ''));
                if ($commentId !== '') {
                    $fromComment = self::fetchInstagramCommentMediaId($commentId);
                    if ($fromComment !== '') {
                        $mediaId = $fromComment;
                    }
                }
            }
            if ($mediaId === '') {
                $mediaId = trim((string) ($value['parent_id'] ?? ''));
            }
            return self::fetchInstagramMediaContext($mediaId);
        }

        $postId = (string) ($value['post_id'] ?? $value['parent_id'] ?? '');
        if ($postId === '' && !empty($value['photo_id'])) {
            $postId = (string) $value['photo_id'];
        }
        $pageHint = '';
        if ($postId !== '' && str_contains($postId, '_')) {
            $pageHint = explode('_', $postId, 2)[0];
        }

        $ctx = self::fetchFacebookPostContext($postId, $pageHint !== '' ? $pageHint : null);
        if ($ctx['ok'] && !empty($ctx['media_url'])) {
            return $ctx;
        }

        // Fallback: pedir attachment del comentario con token de esa página
        $commentId = (string) ($value['comment_id'] ?? $value['id'] ?? '');
        $creds = TokenMeta::channelCredentials('messenger', $pageHint !== '' ? $pageHint : null);
        if ($commentId !== '' && $creds) {
            $url = self::GRAPH_FB . '/' . rawurlencode($commentId)
                . '?fields=' . rawurlencode('attachment,permalink_url,parent')
                . '&access_token=' . rawurlencode($creds['token']);
            $data = self::getJson($url);
            if ($data['ok']) {
                $json = $data['raw'] ?? [];
                $media = null;
                if (!empty($json['attachment']['media']['image']['src'])) {
                    $media = (string) $json['attachment']['media']['image']['src'];
                }
                $parentId = (string) ($json['parent']['id'] ?? $postId);
                if ($media !== null) {
                    return [
                        'ok'        => true,
                        'post_id'   => $parentId !== '' ? $parentId : $postId,
                        'media_url' => $media,
                        'permalink' => isset($json['permalink_url'])
                            ? (string) $json['permalink_url']
                            : ($postId !== '' ? ('https://www.facebook.com/' . $postId) : null),
                        'caption'   => null,
                    ];
                }
                if ($parentId !== '' && $parentId !== $postId) {
                    $again = self::fetchFacebookPostContext($parentId, $pageHint !== '' ? $pageHint : null);
                    if ($again['ok'] && !empty($again['media_url'])) {
                        return $again;
                    }
                }
            }
        }

        // Sin imagen Graph: igual guardamos post_id + link para el diseño
        if ($postId !== '') {
            return [
                'ok'        => true,
                'post_id'   => $postId,
                'media_url' => $ctx['media_url'] ?? null,
                'permalink' => $ctx['permalink'] ?? ('https://www.facebook.com/' . $postId),
                'caption'   => $ctx['caption'] ?? null,
            ];
        }

        return $ctx;
    }

    /**
     * @return array{ok:bool,message?:string,raw?:array}
     */
    private static function getJson(string $url): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'message' => 'cURL init failed'];
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPGET        => true,
        ]);
        $body = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err !== '') {
            return ['ok' => false, 'message' => 'cURL: ' . $err];
        }
        $data = json_decode($body, true);
        if (!is_array($data) || $status >= 400 || isset($data['error'])) {
            $msg = is_array($data) ? (string) ($data['error']['message'] ?? ('HTTP ' . $status)) : ('HTTP ' . $status);
            return ['ok' => false, 'message' => $msg, 'raw' => is_array($data) ? $data : null];
        }
        return ['ok' => true, 'raw' => $data];
    }

    /**
     * @return array{ok:bool,message:string,external_id?:string,media_id?:string,raw?:array}
     */
    public static function sendMedia(
        string $to,
        string $type,
        string $localPath,
        string $mime,
        ?string $caption = null,
        ?string $phoneNumberId = null,
        ?string $pageId = null
    ): array {
        $creds = TokenMeta::whatsappCredentials();
        if (!$creds) {
            return ['ok' => false, 'message' => 'No hay WhatsApp activo en token_meta (page_id + token).'];
        }

        $type = strtolower($type);
        if (!in_array($type, ['image', 'video', 'audio', 'document'], true)) {
            return ['ok' => false, 'message' => 'Tipo de media no soportado.'];
        }

        $upload = self::uploadMedia($creds['phone_number_id'], $creds['token'], $localPath, $mime);
        if (!$upload['ok']) {
            return $upload;
        }

        $mediaId = (string) ($upload['media_id'] ?? '');
        $toClean = preg_replace('/\D+/', '', $to) ?: $to;

        $mediaBody = ['id' => $mediaId];
        if ($caption !== null && $caption !== '' && in_array($type, ['image', 'video', 'document'], true)) {
            $mediaBody['caption'] = $caption;
        }
        if ($type === 'document') {
            $mediaBody['filename'] = basename($localPath);
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $toClean,
            'type'              => $type,
            $type               => $mediaBody,
        ];

        $sent = self::postJson(
            self::GRAPH_WA . '/' . rawurlencode($creds['phone_number_id']) . '/messages',
            $creds['token'],
            $payload,
            'whatsapp'
        );
        if ($sent['ok']) {
            $sent['media_id'] = $mediaId;
        }
        return $sent;
    }

    /**
     * @return array{ok:bool,message:string,media_id?:string}
     */
    private static function uploadMedia(string $phoneNumberId, string $token, string $path, string $mime): array
    {
        if (!is_file($path)) {
            return ['ok' => false, 'message' => 'Archivo no encontrado.'];
        }

        $url = self::GRAPH_WA . '/' . rawurlencode($phoneNumberId) . '/media';
        $cfile = new \CURLFile($path, $mime, basename($path));

        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'message' => 'No se pudo iniciar cURL'];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
            ],
            CURLOPT_POSTFIELDS     => [
                'messaging_product' => 'whatsapp',
                'type'              => $mime,
                'file'              => $cfile,
            ],
        ]);

        $body = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err !== '') {
            return ['ok' => false, 'message' => 'cURL: ' . $err];
        }

        $data = json_decode($body, true);
        if ($status >= 200 && $status < 300 && is_array($data) && !empty($data['id'])) {
            return ['ok' => true, 'message' => 'Media subida', 'media_id' => (string) $data['id']];
        }

        $msg = is_array($data) ? (string) ($data['error']['message'] ?? $body) : $body;
        return ['ok' => false, 'message' => 'Meta media error: ' . $msg];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{ok:bool,message:string,external_id?:?string,raw?:array}
     */
    private static function postJson(string $url, string $token, array $payload, string $context): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'message' => 'No se pudo iniciar cURL'];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        $body = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err !== '') {
            return ['ok' => false, 'message' => 'cURL: ' . $err];
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            return ['ok' => false, 'message' => 'Respuesta inválida de Meta (' . $context . ')'];
        }

        if ($status >= 200 && $status < 300) {
            $ext = (string) (
                $data['message_id']
                ?? $data['id']
                ?? $data['messages'][0]['id']
                ?? ''
            );
            return [
                'ok'          => true,
                'message'     => 'Enviado',
                'external_id' => $ext !== '' ? $ext : null,
                'raw'         => $data,
            ];
        }

        $msg = (string) ($data['error']['message'] ?? ('HTTP ' . $status));
        $code = (int) ($data['error']['code'] ?? 0);

        if ($code === 190 || stripos($msg, 'OAuth') !== false || stripos($msg, 'session') !== false) {
            $msg = 'Token inválido o vencido. Actualiza el token en Configuración → Cuentas Meta (' . $context . ').';
        } elseif ($code === 10 || stripos($msg, 'permission') !== false) {
            $msg = 'Sin permiso Meta para este envío (' . $context . '). Revisa scopes de la app / página.';
        } elseif (stripos($msg, 'outside') !== false || stripos($msg, '24 hour') !== false || stripos($msg, 'messaging window') !== false) {
            $msg = 'Fuera de la ventana de mensajería (24h). El cliente debe escribir primero.';
        }

        return ['ok' => false, 'message' => $msg, 'raw' => $data];
    }

    /**
     * Descarga media entrante de WhatsApp (media id → archivo local).
     *
     * @return array{ok:bool,message:string,public_url?:string,local_path?:string,mime?:string,filename?:string}
     */
    public static function downloadWhatsAppMedia(string $mediaId, ?string $preferredName = null): array
    {
        $mediaId = trim($mediaId);
        if ($mediaId === '') {
            return ['ok' => false, 'message' => 'Sin media_id'];
        }

        $creds = TokenMeta::whatsappCredentials();
        if (!$creds) {
            return ['ok' => false, 'message' => 'No hay WhatsApp activo en token_meta.'];
        }

        $meta = self::getJsonAuth(
            self::GRAPH_WA . '/' . rawurlencode($mediaId),
            $creds['token']
        );
        if (!$meta['ok'] || empty($meta['data']['url'])) {
            return ['ok' => false, 'message' => $meta['message'] ?? 'No se pudo resolver media WhatsApp.'];
        }

        $url = (string) $meta['data']['url'];
        $mime = (string) ($meta['data']['mime_type'] ?? 'application/octet-stream');
        $ext = self::extFromMime($mime, $preferredName);
        $filename = $preferredName !== null && trim($preferredName) !== ''
            ? trim($preferredName)
            : ('wa_' . $mediaId . '.' . $ext);

        return self::persistRemoteBinary($url, $creds['token'], $mime, $filename);
    }

    /**
     * Descarga / copia una URL de media (Messenger / Instagram CDN u otra).
     *
     * @return array{ok:bool,message:string,public_url?:string,local_path?:string,mime?:string,filename?:string}
     */
    public static function downloadRemoteMedia(string $url, ?string $token = null, ?string $preferredName = null, ?string $mimeHint = null): array
    {
        $url = trim($url);
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return ['ok' => false, 'message' => 'URL de media inválida.'];
        }

        $mime = $mimeHint ?: 'application/octet-stream';
        $ext = self::extFromMime($mime, $preferredName);
        $filename = $preferredName !== null && trim($preferredName) !== ''
            ? trim($preferredName)
            : ('remote_' . substr(sha1($url), 0, 12) . '.' . $ext);

        // 1) Bearer + URL original
        $first = self::persistRemoteBinary($url, $token, $mime, $filename);
        if (!empty($first['ok'])) {
            return $first;
        }

        // 2) Meta CDN: access_token en query (suele funcionar cuando Bearer no)
        if ($token !== null && $token !== '') {
            $sep = str_contains($url, '?') ? '&' : '?';
            $withToken = $url . $sep . 'access_token=' . rawurlencode($token);
            $second = self::persistRemoteBinary($withToken, null, $mime, $filename);
            if (!empty($second['ok'])) {
                return $second;
            }
        }

        return $first;
    }

    /**
     * @return array{ok:bool,message:string,data?:array}
     */
    private static function getJsonAuth(string $url, string $token): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'message' => 'No se pudo iniciar cURL'];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
            ],
        ]);

        $body = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err !== '') {
            return ['ok' => false, 'message' => 'cURL: ' . $err];
        }

        $data = json_decode($body, true);
        if ($status >= 200 && $status < 300 && is_array($data)) {
            return ['ok' => true, 'message' => 'OK', 'data' => $data];
        }

        $msg = is_array($data) ? (string) ($data['error']['message'] ?? $body) : $body;
        return ['ok' => false, 'message' => $msg !== '' ? $msg : ('HTTP ' . $status)];
    }

    /**
     * @return array{ok:bool,message:string,public_url?:string,local_path?:string,mime?:string,filename?:string}
     */
    private static function persistRemoteBinary(string $url, ?string $token, string $mimeHint, string $filename): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'message' => 'No se pudo iniciar cURL'];
        }

        $headers = ['User-Agent: AryaCRM/1.0'];
        if ($token !== null && $token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 90,
            CURLOPT_HTTPHEADER     => $headers,
        ]);

        $binary = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err !== '') {
            return ['ok' => false, 'message' => 'cURL: ' . $err];
        }
        if (!is_string($binary) || $binary === '' || $status < 200 || $status >= 300) {
            return ['ok' => false, 'message' => 'No se pudo descargar media (HTTP ' . $status . ').'];
        }

        $mime = $mimeHint;
        if ($contentType !== '') {
            $mime = trim(explode(';', $contentType)[0]);
        }
        if ($mime === '' || $mime === 'application/octet-stream') {
            $mime = $mimeHint !== '' ? $mimeHint : 'application/octet-stream';
        }

        $dir = public_path('uploads/omni');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['ok' => false, 'message' => 'No se pudo crear carpeta de uploads.'];
        }

        $safeBase = preg_replace('/[^a-zA-Z0-9._-]+/', '_', basename($filename)) ?: 'file.bin';
        $ext = strtolower(pathinfo($safeBase, PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = self::extFromMime($mime, null);
            $safeBase .= '.' . $ext;
        }
        $safe = 'omni_in_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . preg_replace('/[^a-z0-9]/', '', $ext);
        $dest = $dir . DIRECTORY_SEPARATOR . $safe;
        if (file_put_contents($dest, $binary) === false) {
            return ['ok' => false, 'message' => 'No se pudo guardar el archivo.'];
        }

        // Ruta relativa: evita localhost/APP_URL rotos al guardar desde webhook/CLI
        $publicUrl = '/uploads/omni/' . $safe;

        return [
            'ok'         => true,
            'message'    => 'Media guardada',
            'public_url' => $publicUrl,
            'local_path' => $dest,
            'mime'       => $mime,
            'filename'   => $safeBase,
        ];
    }

    private static function extFromMime(string $mime, ?string $preferredName): string
    {
        if ($preferredName !== null) {
            $ext = strtolower(pathinfo($preferredName, PATHINFO_EXTENSION));
            if ($ext !== '') {
                return $ext;
            }
        }

        $mime = strtolower(trim(explode(';', $mime)[0]));
        return match (true) {
            str_contains($mime, 'jpeg'), str_contains($mime, 'jpg') => 'jpg',
            str_contains($mime, 'png') => 'png',
            str_contains($mime, 'webp') => 'webp',
            str_contains($mime, 'gif') => 'gif',
            str_contains($mime, 'mp4') => 'mp4',
            str_contains($mime, 'webm') => 'webm',
            str_contains($mime, 'ogg') => 'ogg',
            str_contains($mime, 'mpeg'), str_contains($mime, 'mp3') => 'mp3',
            str_contains($mime, 'aac') => 'aac',
            str_contains($mime, 'pdf') => 'pdf',
            str_contains($mime, 'msword') => 'doc',
            str_contains($mime, 'officedocument.word') => 'docx',
            str_contains($mime, 'ms-excel') => 'xls',
            str_contains($mime, 'officedocument.spreadsheet') => 'xlsx',
            str_contains($mime, 'text/plain') => 'txt',
            str_contains($mime, 'audio') => 'ogg',
            str_contains($mime, 'video') => 'mp4',
            str_contains($mime, 'image') => 'jpg',
            default => 'bin',
        };
    }
}
