<?php

declare(strict_types=1);

namespace Arya\Models;

use Arya\Core\Database;

/**
 * Conversaciones y mensajes omnicanal (producción).
 */
final class Omnichannel
{
    private static bool $bootstrapped = false;

    private const SCHEMA_SESSION_KEY = 'schema_ready_omni_v4';

    public static function bootstrap(): void
    {
        if (self::$bootstrapped) {
            return;
        }
        self::$bootstrapped = true;

        // Ya migrado en esta sesión → no repetir ALTER (Postgres remoto)
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION[self::SCHEMA_SESSION_KEY])) {
            return;
        }

        $pdo = Database::connection();
        if (!$pdo) {
            return;
        }

        try {
            $ready = false;
            try {
                $pdo->query('SELECT 1 FROM omni_conversations LIMIT 1');
                $pdo->query('SELECT 1 FROM omni_messages LIMIT 1');
                $ready = true;
            } catch (\Throwable) {
                $ready = false;
            }

            if (!$ready) {
                $pdo->exec(
                    'CREATE TABLE IF NOT EXISTS omni_conversations (
                        id                 BIGSERIAL PRIMARY KEY,
                        channel            VARCHAR(40)  NOT NULL,
                        external_contact   VARCHAR(120) NOT NULL,
                        contact_name       VARCHAR(150),
                        phone              VARCHAR(80),
                        page_id            VARCHAR(120),
                        phone_number_id    VARCHAR(120),
                        preview            TEXT,
                        status             VARCHAR(30)  NOT NULL DEFAULT \'open\',
                        unread_count       INT          NOT NULL DEFAULT 0,
                        last_message_at    TIMESTAMPTZ,
                        created_at         TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                        updated_at         TIMESTAMPTZ  NOT NULL DEFAULT NOW()
                    )'
                );
                $pdo->exec(
                    'CREATE UNIQUE INDEX IF NOT EXISTS uq_omni_conv_channel_contact
                     ON omni_conversations (channel, external_contact)'
                );
                $pdo->exec('CREATE INDEX IF NOT EXISTS idx_omni_conv_last ON omni_conversations (last_message_at DESC NULLS LAST)');

                $pdo->exec(
                    'CREATE TABLE IF NOT EXISTS omni_messages (
                        id                 BIGSERIAL PRIMARY KEY,
                        conversation_id    BIGINT NOT NULL REFERENCES omni_conversations(id) ON DELETE CASCADE,
                        direction          VARCHAR(20) NOT NULL DEFAULT \'inbound\',
                        sender             VARCHAR(20) NOT NULL DEFAULT \'client\',
                        body               TEXT NOT NULL,
                        message_type       VARCHAR(40) NOT NULL DEFAULT \'text\',
                        external_id        VARCHAR(200),
                        media_url          TEXT,
                        raw                JSONB,
                        created_at         TIMESTAMPTZ NOT NULL DEFAULT NOW()
                    )'
                );
                $pdo->exec(
                    'CREATE UNIQUE INDEX IF NOT EXISTS uq_omni_msg_external
                     ON omni_messages (external_id) WHERE external_id IS NOT NULL'
                );
                $pdo->exec('CREATE INDEX IF NOT EXISTS idx_omni_msg_conv ON omni_messages (conversation_id, created_at)');

                try {
                    $pdo->exec('ALTER TABLE meta_webhook_inbox ADD COLUMN IF NOT EXISTS processed_at TIMESTAMPTZ');
                    $pdo->exec('ALTER TABLE omni_messages ADD COLUMN IF NOT EXISTS media_url TEXT');
                    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_meta_inbox_unprocessed ON meta_webhook_inbox (id) WHERE processed_at IS NULL');
                } catch (\Throwable) {
                    // ignore
                }
            }

            // Columnas DM/comentario + ownership IA/humano (una vez por sesión)
            try {
                $pdo->exec("ALTER TABLE omni_conversations ADD COLUMN IF NOT EXISTS thread_kind VARCHAR(20) NOT NULL DEFAULT 'message'");
                $pdo->exec('ALTER TABLE omni_conversations ADD COLUMN IF NOT EXISTS comment_id VARCHAR(120)');
                $pdo->exec('ALTER TABLE omni_conversations ADD COLUMN IF NOT EXISTS post_id VARCHAR(120)');
                $pdo->exec('ALTER TABLE omni_conversations ADD COLUMN IF NOT EXISTS post_media_url TEXT');
                $pdo->exec('ALTER TABLE omni_conversations ADD COLUMN IF NOT EXISTS post_permalink TEXT');
                $pdo->exec('ALTER TABLE omni_conversations ADD COLUMN IF NOT EXISTS post_caption TEXT');
                $pdo->exec("ALTER TABLE omni_conversations ADD COLUMN IF NOT EXISTS handling_mode VARCHAR(20) NOT NULL DEFAULT 'ai'");
                $pdo->exec('ALTER TABLE omni_conversations ADD COLUMN IF NOT EXISTS assigned_user_id BIGINT');
                $pdo->exec('ALTER TABLE omni_conversations ADD COLUMN IF NOT EXISTS assigned_at TIMESTAMPTZ');
                $pdo->exec('ALTER TABLE omni_conversations ADD COLUMN IF NOT EXISTS assigned_name VARCHAR(150)');
                $pdo->exec('ALTER TABLE omni_messages ADD COLUMN IF NOT EXISTS media_url TEXT');
                $pdo->exec('ALTER TABLE meta_webhook_inbox ADD COLUMN IF NOT EXISTS processed_at TIMESTAMPTZ');
                // No hay estado "cerrado": liberar = volver a IA (reabre residuales antiguos)
                $pdo->exec(
                    "UPDATE omni_conversations
                     SET status = 'open',
                         handling_mode = 'ai',
                         assigned_user_id = NULL,
                         assigned_name = NULL,
                         assigned_at = NULL,
                         updated_at = NOW()
                     WHERE LOWER(COALESCE(status, 'open')) = 'closed'"
                );
            } catch (\Throwable) {
                // ignore
            }

            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION[self::SCHEMA_SESSION_KEY] = 1;
            }
        } catch (\Throwable) {
            // sin DB
        }
    }

    /** Sync ligero: solo si hay pendientes. */
    public static function syncPendingIfAny(): int
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return 0;
        }
        try {
            $hasPending = (bool) $pdo->query(
                'SELECT EXISTS(SELECT 1 FROM meta_webhook_inbox WHERE processed_at IS NULL)'
            )->fetchColumn();
            if (!$hasPending) {
                return 0;
            }
        } catch (\Throwable) {
            return 0;
        }

        $result = self::syncFromMetaInbox();
        return (int) ($result['synced'] ?? 0);
    }

    /**
     * @return list<array{id:string,name:string,icon:string,color:string,unread:int,online:bool}>
     */
    public static function channels(): array
    {
        self::bootstrap();
        $unread = self::unreadByChannel();

        $defs = [
            ['id' => 'whatsapp',  'name' => 'WhatsApp',  'icon' => 'whatsapp',  'color' => '#25D366'],
            ['id' => 'messenger', 'name' => 'Messenger', 'icon' => 'facebook',  'color' => '#1877F2'],
            ['id' => 'instagram', 'name' => 'Instagram', 'icon' => 'instagram', 'color' => '#E4405F'],
            ['id' => 'web',       'name' => 'Web Chat',  'icon' => 'globe',     'color' => '#00F0FF'],
        ];

        $out = [];
        foreach ($defs as $d) {
            $out[] = [
                'id'     => $d['id'],
                'name'   => $d['name'],
                'icon'   => $d['icon'],
                'color'  => $d['color'],
                'unread' => (int) ($unread[$d['id']] ?? 0),
                'online' => true,
            ];
        }
        return $out;
    }

    /**
     * Filas de conversación para contadores (sin mapear).
     *
     * @return list<array<string,mixed>>
     */
    private static function conversationStatRows(\PDO $pdo, bool $withLastInbound = false): array
    {
        $inboundSql = $withLastInbound
            ? ", (
                  SELECT MAX(m.created_at)
                  FROM omni_messages m
                  WHERE m.conversation_id = c.id
                    AND (m.direction = 'inbound' OR m.sender = 'client')
                ) AS last_inbound_at"
            : '';

        $stmt = $pdo->query(
            "SELECT c.id, c.channel, c.external_contact, c.contact_name, c.phone, c.preview,
                    c.status, c.unread_count, c.last_message_at, c.page_id, c.phone_number_id,
                    c.thread_kind, c.comment_id, c.post_id, c.post_media_url, c.post_permalink, c.post_caption,
                    c.handling_mode, c.assigned_user_id, c.assigned_at, c.assigned_name
                    {$inboundSql}
             FROM omni_conversations c
             ORDER BY c.last_message_at DESC NULLS LAST, c.id DESC"
        );

        return $stmt ? $stmt->fetchAll() : [];
    }

    /**
     * @return array<string,int>
     */
    public static function unreadByChannel(): array
    {
        $pdo = Database::connection();
        if (!$pdo) {
            return [];
        }
        try {
            $viewer = function_exists('auth') ? auth() : null;
            $map = [];
            foreach (self::conversationStatRows($pdo) as $row) {
                $mapped = self::mapConversation($row);
                if (self::isOwnAccountConversation($mapped)) {
                    continue;
                }
                if (!self::canViewConversation($mapped, $viewer)) {
                    continue;
                }
                $ch = (string) ($mapped['channel'] ?? '');
                if ($ch === '') {
                    continue;
                }
                $map[$ch] = ($map[$ch] ?? 0) + (int) ($mapped['unread'] ?? 0);
            }
            return $map;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Resumen para campana + badge del menú (polling global).
     * Incluye estado de ventana Meta (24h DM / 7d comentarios).
     * Omite hilos de la propia cuenta (mismo page_id / @handle).
     *
     * @return array{
     *   ok:bool,
     *   unread_total:int,
     *   unread_conversations:int,
     *   open_total:int,
     *   by_channel:array<string,int>,
     *   window_open:int,
     *   window_closed:int,
     *   items:list<array<string,mixed>>
     * }
     */
    public static function notificationSummary(): array
    {
        self::bootstrap();
        $empty = [
            'ok'                   => true,
            'unread_total'         => 0,
            'unread_conversations' => 0,
            'open_total'           => 0,
            'by_channel'           => [
                'whatsapp'  => 0,
                'messenger' => 0,
                'instagram' => 0,
                'web'       => 0,
            ],
            'window_open'          => 0,
            'window_closed'        => 0,
            'items'                => [],
        ];

        $pdo = Database::connection();
        if (!$pdo) {
            return $empty;
        }

        try {
            self::syncPendingIfAny();

            $unreadTotal = 0;
            $unreadConversations = 0;
            $openTotal = 0;
            $windowOpen = 0;
            $windowClosed = 0;
            $items = [];
            $viewer = function_exists('auth') ? auth() : null;

            foreach (self::conversationStatRows($pdo, true) as $row) {
                $mapped = self::mapConversation($row);
                if (self::isOwnAccountConversation($mapped)) {
                    continue;
                }
                if (!self::canViewConversation($mapped, $viewer)) {
                    continue;
                }

                $ch = strtolower((string) ($mapped['channel'] ?? ''));
                $unread = (int) ($mapped['unread'] ?? 0);
                if (isset($empty['by_channel'][$ch])) {
                    $empty['by_channel'][$ch] += $unread;
                }
                $unreadTotal += $unread;
                if ($unread > 0) {
                    $unreadConversations++;
                }
                if (strtolower((string) ($mapped['status'] ?? '')) === 'open') {
                    $openTotal++;
                }

                // Items del panel: solo sin leer (máx. 15), ya sin cuenta propia
                if ($unread > 0 && count($items) < 15) {
                    $window = self::messagingWindowFromRow($row);
                    $mapped['can_reply'] = $window['can_reply'];
                    $mapped['window_hours'] = $window['window_hours'];
                    $mapped['window_expires_at'] = $window['expires_at'];
                    $mapped['window_label'] = $window['label'];
                    $mapped['last_inbound_at'] = $window['last_inbound_at'];
                    $items[] = $mapped;

                    if (in_array($ch, ['whatsapp', 'messenger', 'instagram'], true)) {
                        if ($window['can_reply']) {
                            $windowOpen++;
                        } else {
                            $windowClosed++;
                        }
                    }
                }
            }

            $empty['unread_total'] = $unreadTotal;
            $empty['unread_conversations'] = $unreadConversations;
            $empty['open_total'] = $openTotal;
            $empty['window_open'] = $windowOpen;
            $empty['window_closed'] = $windowClosed;
            $empty['items'] = $items;

            return $empty;
        } catch (\Throwable) {
            return $empty;
        }
    }

    /**
     * Ventana de mensajería Meta:
     * - DM WhatsApp / Messenger / Instagram: 24h tras último inbound del cliente
     * - Comentarios IG: respuesta privada ~7 días (Meta Private Replies)
     * - Web: siempre abierta
     *
     * @param array<string,mixed> $conv Conversación mapeada o fila cruda
     * @return array{can_reply:bool,window_hours:int,last_inbound_at:?string,expires_at:?string,label:string,message:string}
     */
    public static function messagingWindow(array $conv): array
    {
        self::bootstrap();
        $channel = strtolower((string) ($conv['channel'] ?? ''));
        $kind = strtolower((string) ($conv['thread_kind'] ?? 'message'));
        $id = (int) ($conv['id'] ?? 0);

        if ($channel === 'web' || $channel === '') {
            return [
                'can_reply'        => true,
                'window_hours'     => 0,
                'last_inbound_at'  => null,
                'expires_at'       => null,
                'label'            => 'Abierta',
                'message'          => '',
            ];
        }

        $lastInbound = $conv['last_inbound_at'] ?? null;
        if (($lastInbound === null || $lastInbound === '') && $id > 0) {
            $lastInbound = self::lastInboundAt($id);
        }

        $hours = ($kind === 'comment') ? 168 : 24; // 7d comentarios · 24h DM
        $row = [
            'channel'          => $channel,
            'thread_kind'      => $kind,
            'last_inbound_at'  => $lastInbound,
        ];

        return self::messagingWindowFromRow($row, $hours);
    }

    public static function lastInboundAt(int $conversationId): ?string
    {
        $pdo = Database::connection();
        if (!$pdo || $conversationId < 1) {
            return null;
        }
        try {
            $stmt = $pdo->prepare(
                "SELECT MAX(created_at) AS last_inbound_at
                 FROM omni_messages
                 WHERE conversation_id = :id
                   AND (direction = 'inbound' OR sender = 'client')"
            );
            $stmt->execute(['id' => $conversationId]);
            $val = $stmt->fetchColumn();
            return $val !== false && $val !== null && $val !== '' ? (string) $val : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string,mixed> $row
     * @return array{can_reply:bool,window_hours:int,last_inbound_at:?string,expires_at:?string,label:string,message:string}
     */
    private static function messagingWindowFromRow(array $row, ?int $hoursOverride = null): array
    {
        $channel = strtolower((string) ($row['channel'] ?? ''));
        $kind = strtolower((string) ($row['thread_kind'] ?? 'message'));
        $hours = $hoursOverride ?? (($kind === 'comment') ? 168 : 24);
        $lastInbound = isset($row['last_inbound_at']) && $row['last_inbound_at'] !== null && $row['last_inbound_at'] !== ''
            ? (string) $row['last_inbound_at']
            : null;

        if ($channel === 'web') {
            return [
                'can_reply'        => true,
                'window_hours'     => 0,
                'last_inbound_at'  => $lastInbound,
                'expires_at'       => null,
                'label'            => 'Abierta',
                'message'          => '',
            ];
        }

        if ($lastInbound === null) {
            return [
                'can_reply'        => false,
                'window_hours'     => $hours,
                'last_inbound_at'  => null,
                'expires_at'       => null,
                'label'            => 'Cerrada',
                'message'          => $kind === 'comment'
                    ? 'Sin comentario del cliente. No se puede responder aún.'
                    : 'Fuera de la ventana de mensajería (24h). El cliente debe escribir primero.',
            ];
        }

        try {
            $inbound = (new \DateTimeImmutable((string) $lastInbound))->setTimezone(new \DateTimeZone('UTC'));
            $expires = $inbound->modify('+' . $hours . ' hours');
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $can = $now <= $expires;
            $hoursLeft = max(0, (int) floor(($expires->getTimestamp() - $now->getTimestamp()) / 3600));

            if ($can) {
                $label = $hours === 168
                    ? ('Ventana comentario · ' . max(1, (int) ceil($hoursLeft / 24)) . 'd')
                    : ('Ventana 24h · ' . max(1, $hoursLeft) . 'h');
                return [
                    'can_reply'        => true,
                    'window_hours'     => $hours,
                    'last_inbound_at'  => $inbound->format(\DateTimeInterface::ATOM),
                    'expires_at'       => $expires->format(\DateTimeInterface::ATOM),
                    'label'            => $label,
                    'message'          => '',
                ];
            }

            return [
                'can_reply'        => false,
                'window_hours'     => $hours,
                'last_inbound_at'  => $inbound->format(\DateTimeInterface::ATOM),
                'expires_at'       => $expires->format(\DateTimeInterface::ATOM),
                'label'            => 'Cerrada',
                'message'          => $kind === 'comment'
                    ? 'Pasaron más de 7 días desde el comentario. Meta ya no permite respuesta privada.'
                    : 'Fuera de la ventana de mensajería (24h). El cliente debe escribir primero o usa una plantilla aprobada.',
            ];
        } catch (\Throwable) {
            return [
                'can_reply'        => false,
                'window_hours'     => $hours,
                'last_inbound_at'  => $lastInbound,
                'expires_at'       => null,
                'label'            => 'Cerrada',
                'message'          => 'No se pudo validar la ventana de mensajería Meta.',
            ];
        }
    }

    /**
     * @param array{id?:int|string,role?:string}|null $viewer
     * @return list<array<string,mixed>>
     */
    public static function conversations(?string $channel = null, ?array $viewer = null): array
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return [];
        }

        try {
            self::releaseExpiredAssignments();

            if ($channel && $channel !== 'all') {
                $stmt = $pdo->prepare(
                    'SELECT id, channel, external_contact, contact_name, phone, preview, status,
                            unread_count, last_message_at, page_id, phone_number_id,
                            thread_kind, comment_id, post_id, post_media_url, post_permalink, post_caption,
                            handling_mode, assigned_user_id, assigned_at, assigned_name
                     FROM omni_conversations
                     WHERE channel = :channel
                     ORDER BY last_message_at DESC NULLS LAST, id DESC
                     LIMIT 80'
                );
                $stmt->execute(['channel' => $channel]);
            } else {
                $stmt = $pdo->query(
                    'SELECT id, channel, external_contact, contact_name, phone, preview, status,
                            unread_count, last_message_at, page_id, phone_number_id,
                            thread_kind, comment_id, post_id, post_media_url, post_permalink, post_caption,
                            handling_mode, assigned_user_id, assigned_at, assigned_name
                     FROM omni_conversations
                     ORDER BY last_message_at DESC NULLS LAST, id DESC
                     LIMIT 80'
                );
            }

            $rows = $stmt ? $stmt->fetchAll() : [];
            $aiActive = self::aiActiveByChannel();
            $mapped = array_map(
                static fn (array $row): array => self::mapConversation($row, $aiActive),
                $rows
            );
            // Omitir hilos de la propia cuenta (page_id / IGSID / PSID de token_meta)
            $mapped = array_values(array_filter(
                $mapped,
                static fn (array $c): bool => !self::isOwnAccountConversation($c)
            ));

            $viewer = $viewer ?? (function_exists('auth') ? auth() : null);
            return array_values(array_filter(
                $mapped,
                static fn (array $c): bool => self::canViewConversation($c, $viewer)
            ));
        } catch (\Throwable) {
            return [];
        }
    }

    public static function findConversation(int|string $id): ?array
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return null;
        }
        try {
            $stmt = $pdo->prepare('SELECT * FROM omni_conversations WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();
            return $row ? self::mapConversation($row, self::aiActiveByChannel()) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array{id?:int|string,role?:string,name?:string}|null $viewer
     */
    public static function canViewConversation(array $conv, ?array $viewer = null): bool
    {
        $mode = strtolower((string) ($conv['handling_mode'] ?? 'ai'));
        $assignedId = (int) ($conv['assigned_user_id'] ?? 0);
        if ($mode !== 'human' || $assignedId < 1) {
            return true;
        }

        $viewer = $viewer ?? (function_exists('auth') ? auth() : null);
        if (!$viewer) {
            return false;
        }
        $role = strtoupper((string) ($viewer['role'] ?? ''));
        if ($role === 'ADMIN') {
            return true;
        }
        return (int) ($viewer['id'] ?? 0) === $assignedId;
    }

    /**
     * @param array{id?:int|string,name?:string,role?:string} $user
     * @return array{ok:bool,message:string,conversation?:array<string,mixed>}
     */
    public static function takeConversation(int $conversationId, array $user): array
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo || $conversationId < 1) {
            return ['ok' => false, 'message' => 'Sin DB o conversación inválida.'];
        }

        $userId = (int) ($user['id'] ?? 0);
        $userName = trim((string) ($user['name'] ?? $user['email'] ?? 'Agente'));
        if ($userId < 1) {
            return ['ok' => false, 'message' => 'Usuario no válido.'];
        }

        $conv = self::findConversation($conversationId);
        if (!$conv) {
            return ['ok' => false, 'message' => 'Conversación no encontrada.'];
        }

        $mode = strtolower((string) ($conv['handling_mode'] ?? 'ai'));
        $assignedId = (int) ($conv['assigned_user_id'] ?? 0);
        if ($mode === 'human' && $assignedId > 0 && $assignedId !== $userId) {
            $role = strtoupper((string) ($user['role'] ?? ''));
            if ($role !== 'ADMIN') {
                return ['ok' => false, 'message' => 'Esta conversación ya la atiende ' . ($conv['assigned_name'] ?: 'otro agente') . '.'];
            }
        }

        try {
            $stmt = $pdo->prepare(
                "UPDATE omni_conversations SET
                    status = 'open',
                    handling_mode = 'human',
                    assigned_user_id = :uid,
                    assigned_name = :uname,
                    assigned_at = NOW(),
                    updated_at = NOW()
                 WHERE id = :id"
            );
            $stmt->execute([
                'uid'   => $userId,
                'uname' => mb_substr($userName, 0, 150),
                'id'    => $conversationId,
            ]);
            $fresh = self::findConversation($conversationId);
            return [
                'ok'           => true,
                'message'      => 'Conversación tomada. La IA ya no responderá aquí.',
                'conversation' => $fresh ?? $conv,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Libera asignación humana y vuelve a modo IA (si el agente del canal está activo).
     *
     * @return array{ok:bool,message:string,conversation?:array<string,mixed>}
     */
    public static function closeConversation(int $conversationId): array
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo || $conversationId < 1) {
            return ['ok' => false, 'message' => 'Sin DB o conversación inválida.'];
        }

        $conv = self::findConversation($conversationId);
        if (!$conv) {
            return ['ok' => false, 'message' => 'Conversación no encontrada.'];
        }

        $channel = strtolower((string) ($conv['channel'] ?? ''));
        $aiOn = self::isAiAgentActiveForChannel($channel);
        $mode = $aiOn ? 'ai' : 'ai';

        try {
            // "Cerrar" = liberar al humano y devolver a IA (sigue abierta; el cliente puede escribir luego)
            $stmt = $pdo->prepare(
                "UPDATE omni_conversations SET
                    status = 'open',
                    handling_mode = :mode,
                    assigned_user_id = NULL,
                    assigned_name = NULL,
                    assigned_at = NULL,
                    updated_at = NOW()
                 WHERE id = :id"
            );
            $stmt->execute([
                'mode' => $mode,
                'id'   => $conversationId,
            ]);
            $fresh = self::findConversation($conversationId);
            $msg = $aiOn
                ? 'Conversación liberada. Vuelve a atención por IA; si el cliente escribe, la IA responde.'
                : 'Conversación liberada. El agente IA de este canal está inactivo.';
            return [
                'ok'           => true,
                'message'      => $msg,
                'conversation' => $fresh ?? $conv,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /** Auto-libera conversaciones humanas cuya ventana Meta ya venció. */
    public static function releaseExpiredAssignments(): int
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return 0;
        }

        $released = 0;
        try {
            $stmt = $pdo->query(
                "SELECT id, channel, thread_kind, handling_mode, assigned_user_id
                 FROM omni_conversations
                 WHERE handling_mode = 'human'
                   AND assigned_user_id IS NOT NULL
                 ORDER BY id DESC
                 LIMIT 200"
            );
            $rows = $stmt ? $stmt->fetchAll() : [];
            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id < 1) {
                    continue;
                }
                $lastInbound = self::lastInboundAt($id);
                $window = self::messagingWindowFromRow([
                    'channel'         => $row['channel'] ?? '',
                    'thread_kind'     => $row['thread_kind'] ?? 'message',
                    'last_inbound_at' => $lastInbound,
                ]);
                if (!empty($window['can_reply'])) {
                    continue;
                }
                $result = self::closeConversation($id);
                if (!empty($result['ok'])) {
                    $released++;
                }
            }
        } catch (\Throwable) {
            return $released;
        }

        return $released;
    }

    public static function isAiAgentActiveForChannel(string $channel): bool
    {
        $channel = strtolower(trim($channel));
        if ($channel === '') {
            return false;
        }
        $map = self::aiActiveByChannel();
        return !empty($map[$channel]);
    }

    /**
     * @return array<string,bool>
     */
    public static function aiActiveByChannel(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }
        $cache = [];
        $pdo = Database::connection();
        if (!$pdo) {
            return $cache;
        }
        try {
            $stmt = $pdo->query(
                'SELECT canal, activo FROM ocp_ai_channels WHERE COALESCE(activo, FALSE) = TRUE'
            );
            foreach ($stmt ? $stmt->fetchAll() : [] as $row) {
                $c = strtolower(trim((string) ($row['canal'] ?? '')));
                if ($c !== '') {
                    $cache[$c] = true;
                }
            }
        } catch (\Throwable) {
            // tabla puede no existir aún
        }
        return $cache;
    }

    /**
     * @return list<array{id:int|string,from:string,text:string,time:string,type:string,media_url:?string}>
     */
    public static function messages(int|string $conversationId, bool $markRead = true): array
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return [];
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT id, sender, body, message_type, media_url, raw, created_at
                 FROM omni_messages
                 WHERE conversation_id = :id
                 ORDER BY created_at ASC, id ASC'
            );
            $stmt->execute(['id' => $conversationId]);
            $out = [];
            foreach ($stmt->fetchAll() as $row) {
                $hydrated = self::hydrateInboundMediaIfNeeded($pdo, $row);
                $out[] = [
                    'id'        => $hydrated['id'],
                    'from'      => (string) $hydrated['sender'],
                    'text'      => (string) $hydrated['body'],
                    'type'      => (string) ($hydrated['message_type'] ?? 'text'),
                    'media_url' => $hydrated['media_url'] !== null && $hydrated['media_url'] !== ''
                        ? (string) $hydrated['media_url']
                        : null,
                    'time'      => self::formatTime((string) $hydrated['created_at']),
                ];
            }

            if ($markRead) {
                $pdo->prepare('UPDATE omni_conversations SET unread_count = 0, updated_at = NOW() WHERE id = :id')
                    ->execute(['id' => $conversationId]);
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Recupera media faltante desde el JSON raw (mensajes antiguos con [mensaje]).
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function hydrateInboundMediaIfNeeded(\PDO $pdo, array $row): array
    {
        $mediaUrl = trim((string) ($row['media_url'] ?? ''));
        $type = strtolower((string) ($row['message_type'] ?? 'text'));
        $body = (string) ($row['body'] ?? '');
        $needs = $mediaUrl === '' && (
            in_array($type, ['image', 'video', 'audio', 'voice', 'document', 'sticker'], true)
            || in_array($body, ['[mensaje]', '[imagen]', '[video]', '[audio]', '[documento]', '[sticker]'], true)
            || str_starts_with($body, '[documento]')
        );
        if (!$needs) {
            return $row;
        }

        $raw = $row['raw'] ?? null;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }
        if (!is_array($raw)) {
            return $row;
        }

        $resolvedType = $type;
        $resolvedUrl = null;
        $resolvedBody = $body;

        // WhatsApp Cloud payload (type + image/video/...)
        $waType = strtolower((string) ($raw['type'] ?? ''));
        $isWhatsAppMedia = ($waType !== '' && isset($raw[$waType]))
            || isset($raw['image']) || isset($raw['video'])
            || isset($raw['audio']) || isset($raw['document']) || isset($raw['voice']) || isset($raw['sticker']);
        if ($isWhatsAppMedia) {
            $useType = $waType !== '' ? $waType : (
                isset($raw['image']) ? 'image' : (
                    isset($raw['video']) ? 'video' : (
                        isset($raw['audio']) || isset($raw['voice']) ? 'audio' : (
                            isset($raw['document']) ? 'document' : (
                                isset($raw['sticker']) ? 'sticker' : $type
                            )
                        )
                    )
                )
            );
            $resolvedUrl = self::resolveWhatsAppMediaUrl($raw, $useType);
            if ($resolvedUrl) {
                $resolvedType = match ($useType) {
                    'voice' => 'audio',
                    'sticker' => 'image',
                    default => $useType,
                };
                $newBody = self::extractWhatsAppBody($raw, $useType);
                if ($newBody !== '') {
                    $resolvedBody = $newBody;
                }
            }
        }

        // Messenger / Instagram DM — recupera media Y texto desde raw
        if (isset($raw['message']) && is_array($raw['message'])) {
            $content = self::extractMessengerContent($raw, 'instagram');
            if (!empty($content['media_url']) && $resolvedUrl === null) {
                $resolvedUrl = $content['media_url'];
                $resolvedType = $content['type'];
            }
            $contentText = trim((string) ($content['text'] ?? ''));
            if (
                $contentText !== ''
                && $contentText !== '[mensaje]'
                && (
                    $body === ''
                    || $body === '[mensaje]'
                    || in_array($body, ['[imagen]', '[video]', '[audio]', '[documento]', '[sticker]'], true)
                )
            ) {
                $resolvedBody = $contentText;
                if ($resolvedUrl === null && ($content['type'] ?? '') === 'text') {
                    $resolvedType = 'text';
                }
            }
            // Texto recuperado sin media: persistir igual
            if ($resolvedUrl === null && $resolvedBody !== $body && $resolvedBody !== '' && $resolvedBody !== '[mensaje]') {
                try {
                    $updText = $pdo->prepare(
                        'UPDATE omni_messages
                         SET body = :body,
                             message_type = COALESCE(NULLIF(:message_type, \'\'), message_type)
                         WHERE id = :id'
                    );
                    $updText->execute([
                        'body'         => $resolvedBody,
                        'message_type' => $resolvedType !== '' ? $resolvedType : 'text',
                        'id'           => $row['id'],
                    ]);
                } catch (\Throwable) {
                }
                $row['body'] = $resolvedBody;
                $row['message_type'] = $resolvedType !== '' ? $resolvedType : ($row['message_type'] ?? 'text');
                return $row;
            }
        }

        if ($resolvedUrl === null || $resolvedUrl === '') {
            return $row;
        }

        try {
            $upd = $pdo->prepare(
                'UPDATE omni_messages
                 SET media_url = :media_url,
                     message_type = :message_type,
                     body = CASE
                       WHEN body IN (\'[mensaje]\', \'[imagen]\', \'[video]\', \'[audio]\', \'[documento]\', \'[sticker]\')
                         OR body = \'\' THEN :body
                       ELSE body
                     END
                 WHERE id = :id'
            );
            $upd->execute([
                'media_url'    => $resolvedUrl,
                'message_type' => $resolvedType,
                'body'         => $resolvedBody,
                'id'           => $row['id'],
            ]);
        } catch (\Throwable) {
            // seguir con datos en memoria aunque falle el UPDATE
        }

        $row['media_url'] = $resolvedUrl;
        $row['message_type'] = $resolvedType;
        if ($body === '' || $body === '[mensaje]' || in_array($body, ['[imagen]', '[video]', '[audio]', '[documento]', '[sticker]'], true)) {
            $row['body'] = $resolvedBody;
        }

        return $row;
    }

    /**
     * Guarda mensaje saliente del agente o IA.
     *
     * @return array{ok:bool,message:string,id?:int}
     */
    public static function storeOutbound(
        int $conversationId,
        string $body,
        string $type = 'text',
        ?string $mediaUrl = null,
        ?string $externalId = null,
        string $sender = 'agent'
    ): array {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return ['ok' => false, 'message' => 'Sin DB'];
        }

        $sender = strtolower(trim($sender));
        if (!in_array($sender, ['agent', 'ai'], true)) {
            $sender = 'agent';
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO omni_messages
                    (conversation_id, direction, sender, body, message_type, media_url, external_id, created_at)
                 VALUES
                    (:cid, \'outbound\', :sender, :body, :type, :media_url, :external_id, NOW())
                 RETURNING id'
            );
            $stmt->execute([
                'cid'         => $conversationId,
                'sender'      => $sender,
                'body'        => $body,
                'type'        => $type,
                'media_url'   => $mediaUrl,
                'external_id' => $externalId,
            ]);
            $id = (int) $stmt->fetchColumn();

            $preview = $body !== '' ? $body : ('[' . $type . ']');
            $pdo->prepare(
                'UPDATE omni_conversations SET
                    preview = :preview,
                    last_message_at = NOW(),
                    status = \'open\',
                    updated_at = NOW()
                 WHERE id = :id'
            )->execute([
                'preview' => mb_substr($preview, 0, 280),
                'id'      => $conversationId,
            ]);

            return ['ok' => true, 'message' => 'Mensaje guardado', 'id' => $id];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Sincroniza eventos pendientes de meta_webhook_inbox → omni_*.
     *
     * @return array{ok:bool, synced:int, message:string}
     */
    public static function syncFromMetaInbox(): array
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return ['ok' => false, 'synced' => 0, 'message' => 'Sin DB'];
        }

        $synced = 0;
        try {
            $stmt = $pdo->query(
                "SELECT id, object_type, page_id, canal, event_type, payload, created_at
                 FROM meta_webhook_inbox
                 WHERE processed_at IS NULL
                 ORDER BY id ASC
                 LIMIT 200"
            );
            $rows = $stmt ? $stmt->fetchAll() : [];

            foreach ($rows as $row) {
                $n = self::ingestInboxRow($pdo, $row);
                $synced += $n;

                // Comentarios IG/FB: si falló el ingest pero el payload es usable, reintentar luego.
                // (messaging/otros se marcan siempre para no atascar la cola con receipts).
                $eventType = strtolower((string) ($row['event_type'] ?? ''));
                if (
                    $n === 0
                    && in_array($eventType, ['comments', 'live_comments', 'feed'], true)
                    && self::inboxCommentShouldRetry($row)
                ) {
                    continue;
                }

                $pdo->prepare('UPDATE meta_webhook_inbox SET processed_at = NOW() WHERE id = :id')
                    ->execute(['id' => $row['id']]);
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'synced' => $synced, 'message' => $e->getMessage()];
        }

        return ['ok' => true, 'synced' => $synced, 'message' => "Sincronizados {$synced} mensajes"];
    }

    /**
     * ¿El inbox de comentario tiene datos mínimos y aún no existe en omni?
     *
     * @param array<string,mixed> $row
     */
    private static function inboxCommentShouldRetry(array $row): bool
    {
        $payload = $row['payload'] ?? null;
        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }
        if (!is_array($payload)) {
            return false;
        }
        $change = $payload['changes'][0] ?? null;
        if (!is_array($change)) {
            return false;
        }
        $value = $change['value'] ?? null;
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        if (!is_array($value)) {
            return false;
        }

        $field = strtolower((string) ($change['field'] ?? ($row['event_type'] ?? '')));
        if ($field === 'feed' && strtolower((string) ($value['item'] ?? '')) !== 'comment') {
            return false;
        }
        if (strtolower((string) ($value['verb'] ?? 'add')) === 'remove') {
            return false;
        }

        $commentId = trim((string) ($value['id'] ?? $value['comment_id'] ?? ''));
        if ($commentId === '') {
            return false;
        }

        try {
            $pdo = Database::connection();
            if (!$pdo) {
                return true;
            }
            $stmt = $pdo->prepare(
                "SELECT 1 FROM omni_conversations
                 WHERE comment_id = :cid OR external_contact = :ext
                 LIMIT 1"
            );
            $stmt->execute(['cid' => $commentId, 'ext' => 'cmt:' . $commentId]);
            if ($stmt->fetchColumn()) {
                return false; // ya está en omni → no reintentar
            }
        } catch (\Throwable) {
            return true;
        }

        return true;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function ingestInboxRow(\PDO $pdo, array $row): int
    {
        $payload = $row['payload'] ?? null;
        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }
        if (!is_array($payload)) {
            return 0;
        }

        $canal = strtolower((string) ($row['canal'] ?? ''));
        $pageId = (string) ($row['page_id'] ?? ($payload['id'] ?? ''));
        $createdAt = (string) ($row['created_at'] ?? date('c'));
        $count = 0;

        // Messenger / Instagram DM: entry.messaging[]
        $messaging = $payload['messaging'] ?? null;
        if (is_array($messaging)) {
            $dmCanal = $canal !== '' ? $canal : 'messenger';
            if ($dmCanal === 'page') {
                $dmCanal = 'messenger';
            }
            foreach ($messaging as $msg) {
                if (!is_array($msg)) {
                    continue;
                }
                $count += self::ingestMessengerItem($pdo, $msg, $pageId, $dmCanal, $createdAt) ? 1 : 0;
            }
        }

        // changes[]: WhatsApp messages | IG comments | FB feed comments
        $changes = $payload['changes'] ?? null;
        if (is_array($changes)) {
            foreach ($changes as $change) {
                if (!is_array($change)) {
                    continue;
                }
                $field = strtolower((string) ($change['field'] ?? ($row['event_type'] ?? '')));
                $value = $change['value'] ?? null;
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    $value = is_array($decoded) ? $decoded : null;
                }
                if (!is_array($value)) {
                    continue;
                }

                if ($field === 'messages' || ($canal === 'whatsapp' && isset($value['messages']))) {
                    $count += self::ingestWhatsAppValue($pdo, $value, $pageId, 'whatsapp', $createdAt);
                    continue;
                }

                // Instagram comments (object=instagram, field=comments|live_comments)
                // Importante: no capturar field=comments de Page/Messenger (van a feed FB)
                $isIgCanal = $canal === 'instagram' || $canal === 'ig';
                $looksLikeIgComment = isset($value['media']['id'])
                    && (isset($value['text']) || isset($value['message']))
                    && !isset($value['post_id']);
                if (
                    ($isIgCanal || $looksLikeIgComment)
                    && (
                        $field === 'comments'
                        || $field === 'live_comments'
                        || $looksLikeIgComment
                        || (
                            $isIgCanal
                            && (isset($value['id']) || isset($value['comment_id']))
                            && (isset($value['text']) || isset($value['message']))
                            && !isset($value['post_id'])
                        )
                    )
                ) {
                    $count += self::ingestInstagramComment($pdo, $value, $pageId, $createdAt) ? 1 : 0;
                    continue;
                }

                // Facebook Page feed comment (field=feed item=comment; a veces field=comments)
                $item = strtolower((string) ($value['item'] ?? ''));
                $isFbCanal = in_array($canal, ['messenger', 'facebook', 'page', 'fb', ''], true);
                if (
                    ($field === 'feed' && $item === 'comment')
                    || ($field === 'comments' && $isFbCanal && (isset($value['comment_id']) || isset($value['post_id'])))
                    || ($item === 'comment' && $isFbCanal && (isset($value['comment_id']) || isset($value['post_id'])))
                ) {
                    $count += self::ingestFacebookComment($pdo, $value, $pageId, $createdAt) ? 1 : 0;
                }
            }
        }

        return $count;
    }

    /**
     * @param array<string,mixed> $value
     */
    private static function ingestWhatsAppValue(
        \PDO $pdo,
        array $value,
        string $pageId,
        string $canal,
        string $fallbackAt
    ): int {
        $messages = $value['messages'] ?? null;
        if (!is_array($messages) || $messages === []) {
            return 0; // statuses, etc.
        }

        $contacts = $value['contacts'] ?? [];
        $contactName = '';
        $waId = '';
        if (is_array($contacts) && isset($contacts[0]) && is_array($contacts[0])) {
            $waId = (string) ($contacts[0]['wa_id'] ?? '');
            $contactName = (string) ($contacts[0]['profile']['name'] ?? '');
        }

        $meta = is_array($value['metadata'] ?? null) ? $value['metadata'] : [];
        $phoneNumberId = (string) ($meta['phone_number_id'] ?? '');
        $displayPhone = (string) ($meta['display_phone_number'] ?? '');

        $count = 0;
        foreach ($messages as $msg) {
            if (!is_array($msg)) {
                continue;
            }
            $from = (string) ($msg['from'] ?? $waId);
            if ($from === '') {
                continue;
            }
            $externalId = (string) ($msg['id'] ?? '');
            $type = (string) ($msg['type'] ?? 'text');
            $body = self::extractWhatsAppBody($msg, $type);
            if ($body === '') {
                $body = '[' . $type . ']';
            }
            $mediaUrl = self::resolveWhatsAppMediaUrl($msg, $type);
            if ($type === 'sticker' && $mediaUrl) {
                $type = 'image';
            }
            if ($type === 'voice') {
                $type = 'audio';
            }

            $ts = (string) ($msg['timestamp'] ?? '');
            $at = $fallbackAt;
            if ($ts !== '' && ctype_digit($ts)) {
                $at = gmdate('Y-m-d H:i:sP', (int) $ts);
            }

            $name = $contactName !== '' ? $contactName : ('+' . $from);
            $convId = self::upsertConversation($pdo, [
                'channel'          => 'whatsapp',
                'external_contact' => $from,
                'contact_name'     => $name,
                'phone'            => '+' . ltrim($from, '+'),
                'page_id'          => $pageId !== '' ? $pageId : null,
                'phone_number_id'  => $phoneNumberId !== '' ? $phoneNumberId : null,
                'preview'          => $body,
                'at'               => $at,
                'thread_kind'      => 'message',
                'comment_id'       => null,
            ]);

            if (self::insertMessage($pdo, $convId, [
                'direction'    => 'inbound',
                'sender'       => 'client',
                'body'         => $body,
                'message_type' => $type,
                'media_url'    => $mediaUrl,
                'external_id'  => $externalId !== '' ? $externalId : null,
                'raw'          => $msg,
                'at'           => $at,
            ])) {
                $count++;
                try {
                    $full = self::findConversation($convId) ?? [];
                    \Arya\Services\AiAgentDispatch::maybeReply($convId, $body, array_merge($full, [
                        'channel'          => 'whatsapp',
                        'external_contact' => $from,
                        'contact_name'     => $name,
                        'phone'            => '+' . ltrim($from, '+'),
                        'inbound_external_id' => $externalId,
                        'inbound_message_type' => $type,
                        'inbound_has_media' => $mediaUrl !== null && $mediaUrl !== '',
                    ]));
                } catch (\Throwable) {
                }
            }
        }

        // silence unused
        unset($displayPhone);

        return $count;
    }

    /**
     * @param array<string,mixed> $msg
     */
    private static function extractWhatsAppBody(array $msg, string $type): string
    {
        if ($type === 'text' && isset($msg['text']['body'])) {
            return trim((string) $msg['text']['body']);
        }
        if ($type === 'button' && isset($msg['button']['text'])) {
            return trim((string) $msg['button']['text']);
        }
        if ($type === 'interactive') {
            $inter = $msg['interactive'] ?? [];
            if (isset($inter['button_reply']['title'])) {
                return trim((string) $inter['button_reply']['title']);
            }
            if (isset($inter['list_reply']['title'])) {
                return trim((string) $inter['list_reply']['title']);
            }
        }
        if ($type === 'image') {
            $cap = trim((string) ($msg['image']['caption'] ?? ''));
            return $cap !== '' ? $cap : '[imagen]';
        }
        if ($type === 'video') {
            $cap = trim((string) ($msg['video']['caption'] ?? ''));
            return $cap !== '' ? $cap : '[video]';
        }
        if ($type === 'audio' || $type === 'voice') {
            return '[audio]';
        }
        if ($type === 'document') {
            $name = trim((string) ($msg['document']['filename'] ?? ''));
            $cap = trim((string) ($msg['document']['caption'] ?? ''));
            if ($cap !== '') {
                return $cap;
            }
            return $name !== '' ? ('[documento] ' . $name) : '[documento]';
        }
        if ($type === 'sticker') {
            return '[sticker]';
        }
        return '';
    }

    /**
     * Descarga media WA y devuelve URL pública local, o null.
     *
     * @param array<string,mixed> $msg
     */
    private static function resolveWhatsAppMediaUrl(array $msg, string $type): ?string
    {
        $type = strtolower($type);
        if ($type === 'voice') {
            $type = 'audio';
        }
        if (!in_array($type, ['image', 'video', 'audio', 'document', 'sticker'], true)) {
            return null;
        }
        $block = $msg[$type] ?? ($type === 'audio' ? ($msg['voice'] ?? null) : null);
        if (!is_array($block)) {
            return null;
        }
        $mediaId = trim((string) ($block['id'] ?? ''));
        if ($mediaId === '') {
            return null;
        }
        $preferred = trim((string) ($block['filename'] ?? ''));
        if ($preferred === '') {
            $preferred = null;
        }
        $dl = \Arya\Services\MetaCloud::downloadWhatsAppMedia($mediaId, $preferred);
        if (!empty($dl['ok']) && !empty($dl['public_url'])) {
            return (string) $dl['public_url'];
        }
        return null;
    }

    /**
     * Extrae tipo/texto/URL de adjuntos Messenger / Instagram DM.
     *
     * @param array<string,mixed> $msg webhook messaging item
     * @return array{type:string,text:string,media_url:?string}
     */
    private static function extractMessengerContent(array $msg, string $canal = 'instagram'): array
    {
        $message = is_array($msg['message'] ?? null) ? $msg['message'] : [];
        // Texto: paths habituales Meta IG/Messenger
        $text = trim((string) (
            $message['text']
            ?? $msg['text']
            ?? $message['message']
            ?? ''
        ));
        if ($text === '' && isset($msg['postback']['title'])) {
            $text = trim((string) $msg['postback']['title']);
        }
        if ($text === '' && isset($message['quick_reply']['payload'])) {
            $text = trim((string) $message['quick_reply']['payload']);
        }

        $atts = $message['attachments'] ?? $msg['attachments'] ?? [];
        if (is_array($atts) && $atts !== [] && !array_is_list($atts)) {
            $atts = [$atts];
        }
        if (!is_array($atts) || $atts === []) {
            return [
                'type'      => 'text',
                'text'      => $text !== '' ? $text : '[mensaje]',
                'media_url' => null,
            ];
        }

        $att = is_array($atts[0] ?? null) ? $atts[0] : [];
        $attType = strtolower((string) ($att['type'] ?? 'file'));
        $payload = is_array($att['payload'] ?? null) ? $att['payload'] : [];
        $url = trim((string) (
            $payload['url']
            ?? $att['url']
            ?? $payload['src']
            ?? ''
        ));

        $type = match ($attType) {
            'image', 'sticker', 'story_mention', 'ig_reel', 'reel', 'ephemeral' => 'image',
            'video' => 'video',
            'audio', 'voice', 'ig_audio' => 'audio',
            'file', 'document', 'fallback' => 'document',
            'share', 'story_reply' => self::guessMediaTypeFromUrl($url) ?? 'image',
            default => self::guessMediaTypeFromUrl($url) ?? ($text !== '' ? 'text' : 'document'),
        };

        $mediaUrl = null;
        if ($url !== '') {
            $canal = $canal === 'messenger' ? 'messenger' : 'instagram';
            $creds = TokenMeta::channelCredentials($canal)
                ?? TokenMeta::channelCredentials($canal === 'instagram' ? 'messenger' : 'instagram');
            $token = $creds['token'] ?? null;
            $preferred = !empty($payload['title']) ? (string) $payload['title'] : null;
            $dl = \Arya\Services\MetaCloud::downloadRemoteMedia($url, $token, $preferred);
            if (!empty($dl['ok']) && !empty($dl['public_url'])) {
                $mediaUrl = (string) $dl['public_url'];
            } else {
                $mediaUrl = $url;
            }
        }

        if ($text === '') {
            $text = match ($type) {
                'image' => '[imagen]',
                'video' => '[video]',
                'audio' => '[audio]',
                'document' => '[documento]',
                default => '[mensaje]',
            };
        }

        return [
            'type'      => ($type === 'text' && $mediaUrl) ? 'document' : $type,
            'text'      => $text,
            'media_url' => $mediaUrl,
        ];
    }

    private static function guessMediaTypeFromUrl(string $url): ?string
    {
        $path = strtolower(parse_url($url, PHP_URL_PATH) ?: '');
        if (preg_match('/\.(jpe?g|png|gif|webp)(?:$|\?)/', $path)) {
            return 'image';
        }
        if (preg_match('/\.(mp4|webm|mov|3gp)(?:$|\?)/', $path)) {
            return 'video';
        }
        if (preg_match('/\.(mp3|ogg|oga|m4a|aac|amr|wav)(?:$|\?)/', $path)) {
            return 'audio';
        }
        if (preg_match('/\.(pdf|docx?|xlsx?|txt|csv|zip)(?:$|\?)/', $path)) {
            return 'document';
        }
        return null;
    }

    /**
     * @param array<string,mixed> $msg
     */
    /**
     * IDs propios de Meta (page_id + identificador de token_meta activos).
     *
     * @return array<string,true>
     */
    private static function ownMetaActorIds(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }
        $cache = [];
        try {
            foreach (TokenMeta::allActive() as $acc) {
                $pid = trim((string) ($acc['page_id'] ?? ''));
                $ident = trim((string) ($acc['identificador'] ?? ''));
                if ($pid !== '') {
                    $cache[$pid] = true;
                }
                // WhatsApp: Phone number ID; IG/Messenger: a veces IGSID/PSID
                if ($ident !== '' && preg_match('/^\d{6,30}$/', $ident)) {
                    $cache[$ident] = true;
                }
            }
        } catch (\Throwable) {
        }
        return $cache;
    }

    /**
     * Usernames propios (@cuenta) para omitir ecos de la misma página.
     *
     * @return array<string,true> keys en minúsculas sin @
     */
    private static function ownMetaUsernames(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }
        $cache = [];

        $add = static function (string $u) use (&$cache): void {
            $u = strtolower(ltrim(trim($u), '@'));
            if ($u !== '' && !preg_match('/^\d+$/', $u)) {
                $cache[$u] = true;
            }
        };

        // config / env: META_OWN_USERNAMES=cortech_col,otra_cuenta
        $envList = '';
        if (function_exists('config')) {
            $envList = (string) config('meta.own_usernames', '');
        }
        if ($envList === '' && function_exists('env')) {
            $envList = (string) env('META_OWN_USERNAMES', '');
        }
        foreach (preg_split('/[\s,;]+/', $envList) ?: [] as $part) {
            $add((string) $part);
        }

        try {
            foreach (TokenMeta::allActive() as $acc) {
                $ident = trim((string) ($acc['identificador'] ?? ''));
                // identificador no numérico = username / handle
                if ($ident !== '' && !preg_match('/^\d{6,30}$/', $ident)) {
                    $add($ident);
                }
            }
        } catch (\Throwable) {
        }

        // Handle de la cuenta IG del CRM (vista omnicanal / ecos Meta)
        $add('cortech_col');

        return $cache;
    }

    /** ¿El actor (from/sender) es la propia página / cuenta IG Business? */
    private static function isOwnMetaActor(string $actorId, string $pageId = ''): bool
    {
        $actorId = trim($actorId);
        if ($actorId === '') {
            return false;
        }
        if ($pageId !== '' && $actorId === trim($pageId)) {
            return true;
        }
        return isset(self::ownMetaActorIds()[$actorId]);
    }

    private static function isOwnMetaUsername(string $username): bool
    {
        $u = strtolower(ltrim(trim($username), '@'));
        if ($u === '') {
            return false;
        }
        return isset(self::ownMetaUsernames()[$u]);
    }

    /**
     * Conversación generada por la propia cuenta (no debe listarse en omnicanal).
     *
     * @param array<string,mixed> $conv
     */
    private static function isOwnAccountConversation(array $conv): bool
    {
        $pageId = trim((string) ($conv['page_id'] ?? ''));
        $phone = trim((string) ($conv['phone'] ?? ''));
        $ext = trim((string) ($conv['external_contact'] ?? ''));
        $client = trim((string) ($conv['client'] ?? $conv['contact_name'] ?? ''));

        // mapConversation a veces pone phone = cmt:…; ignorar ese fallback
        if (str_starts_with($phone, 'cmt:')) {
            $phone = '';
        }

        // Comentarios: phone = from.id del autor
        if ($phone !== '' && self::isOwnMetaActor($phone, $pageId)) {
            return true;
        }
        // DMs: external_contact = PSID/IGSID
        if ($ext !== '' && !str_starts_with($ext, 'cmt:') && self::isOwnMetaActor($ext, $pageId)) {
            return true;
        }
        // Mismo handle de la página (@cortech_col, etc.)
        if ($client !== '' && self::isOwnMetaUsername($client)) {
            return true;
        }
        return false;
    }

    private static function ingestMessengerItem(
        \PDO $pdo,
        array $msg,
        string $pageId,
        string $canal,
        string $fallbackAt
    ): bool {
        $senderId = (string) (($msg['sender']['id'] ?? '') ?: '');
        $recipientId = (string) (($msg['recipient']['id'] ?? '') ?: '');
        $message = is_array($msg['message'] ?? null) ? $msg['message'] : [];

        // Eco de mensajes enviados por la página / agente → NUNCA re-despachar a n8n
        if (!empty($message['is_echo']) || !empty($message['is_deleted']) || !empty($msg['read']) || !empty($msg['delivery'])) {
            return false;
        }
        // Sin cuerpo de mensaje (postback/recibo) → omitir
        if ($message === [] && empty($msg['postback'])) {
            return false;
        }
        // Echo / mensajes de la propia página o cuenta vinculada → omitir
        if ($senderId === '' || self::isOwnMetaActor($senderId, $pageId)) {
            return false;
        }
        // Seguridad: sender == recipient no es inbound real
        if ($recipientId !== '' && $senderId === $recipientId) {
            return false;
        }

        $canal = $canal === 'instagram' ? 'instagram' : 'messenger';
        $content = self::extractMessengerContent($msg, $canal);
        $text = $content['text'];
        $msgType = $content['type'];
        $mediaUrl = $content['media_url'];

        $externalId = (string) ($message['mid'] ?? $msg['message']['mid'] ?? '');
        $ts = (string) ($msg['timestamp'] ?? '');
        $at = $fallbackAt;
        if ($ts !== '' && ctype_digit($ts)) {
            // Messenger timestamps are ms
            $n = (int) $ts;
            if ($n > 20000000000) {
                $n = (int) floor($n / 1000);
            }
            $at = gmdate('Y-m-d H:i:sP', $n);
        }

        $name = self::resolveContactDisplayName($pdo, $canal, $senderId, $pageId);

        $convId = self::upsertConversation($pdo, [
            'channel'          => $canal,
            'external_contact' => $senderId,
            'contact_name'     => $name,
            'phone'            => $senderId,
            'page_id'          => $pageId !== '' ? $pageId : null,
            'phone_number_id'  => null,
            'preview'          => $text,
            'at'               => $at,
            'thread_kind'      => 'message',
            'comment_id'       => null,
        ]);

        if (!self::isPlaceholderName($name)) {
            self::propagateContactName($pdo, $canal, $senderId, $name);
        }

        $ok = self::insertMessage($pdo, $convId, [
            'direction'    => 'inbound',
            'sender'       => 'client',
            'body'         => $text,
            'message_type' => $msgType,
            'media_url'    => $mediaUrl,
            'external_id'  => $externalId !== '' ? $externalId : null,
            'raw'          => $msg,
            'at'           => $at,
        ]);
        // Solo despachar a n8n si el mensaje es NUEVO (no duplicado por mid)
        if ($ok) {
            try {
                $full = self::findConversation($convId) ?? [];
                \Arya\Services\AiAgentDispatch::maybeReply($convId, $text, array_merge($full, [
                    'channel'          => $canal,
                    'external_contact' => $senderId,
                    'contact_name'     => $name,
                    'phone'            => $senderId,
                    'inbound_external_id' => $externalId,
                    'inbound_message_type' => $msgType,
                    'inbound_has_media' => $mediaUrl !== null && $mediaUrl !== '',
                ]));
            } catch (\Throwable) {
            }
        }
        return $ok;
    }

    /** ¿Nombre genérico tipo "Usuario 112434"? */
    private static function isPlaceholderName(string $name): bool
    {
        $name = trim($name);
        if ($name === '') {
            return true;
        }
        return (bool) preg_match('/^Usuario\s+\d+$/iu', $name);
    }

    /**
     * Busca nombre real: otros hilos (comentario/DM) → Graph API → fallback.
     */
    private static function resolveContactDisplayName(\PDO $pdo, string $channel, string $psid, string $pageId = ''): string
    {
        $known = self::findKnownContactName($pdo, $channel, $psid);
        if ($known !== null && !self::isPlaceholderName($known)) {
            return $known;
        }

        $fromGraph = null;
        if ($channel === 'instagram') {
            $fromGraph = \Arya\Services\MetaCloud::fetchInstagramProfileName($psid);
        } else {
            $fromGraph = \Arya\Services\MetaCloud::fetchMessengerProfileName($psid, $pageId !== '' ? $pageId : null);
        }
        if (is_string($fromGraph) && trim($fromGraph) !== '' && !self::isPlaceholderName($fromGraph)) {
            return trim($fromGraph);
        }

        return $known ?? ('Usuario ' . substr($psid, -6));
    }

    private static function findKnownContactName(\PDO $pdo, string $channel, string $psid): ?string
    {
        try {
            $stmt = $pdo->prepare(
                "SELECT contact_name FROM omni_conversations
                 WHERE channel = :channel
                   AND (external_contact = :psid OR phone = :psid)
                   AND contact_name IS NOT NULL
                   AND TRIM(contact_name) <> ''
                   AND contact_name NOT LIKE 'Usuario %'
                 ORDER BY
                   CASE WHEN thread_kind = 'comment' THEN 0 ELSE 1 END,
                   updated_at DESC NULLS LAST
                 LIMIT 1"
            );
            $stmt->execute(['channel' => $channel, 'psid' => $psid]);
            $name = $stmt->fetchColumn();
            return is_string($name) && trim($name) !== '' ? trim($name) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Propaga un nombre real a todos los hilos del mismo PSID (DM + comentarios).
     */
    private static function propagateContactName(\PDO $pdo, string $channel, string $psid, string $name): void
    {
        $name = trim($name);
        $psid = trim($psid);
        if ($name === '' || $psid === '' || self::isPlaceholderName($name)) {
            return;
        }
        try {
            $stmt = $pdo->prepare(
                "UPDATE omni_conversations SET
                    contact_name = :name,
                    updated_at = NOW()
                 WHERE channel = :channel
                   AND (
                        external_contact = :psid
                     OR phone = :psid
                   )
                   AND (
                        contact_name IS NULL
                     OR TRIM(contact_name) = ''
                     OR contact_name LIKE 'Usuario %'
                   )"
            );
            $stmt->execute([
                'name'    => $name,
                'channel' => $channel,
                'psid'    => $psid,
            ]);

            // También actualiza cliente CRM si existe
            try {
                $pdo->prepare(
                    "UPDATE arya_clients SET
                        name = CASE
                            WHEN name IS NULL OR TRIM(name) = '' OR name LIKE 'Usuario %' THEN :name
                            ELSE name
                        END,
                        updated_at = NOW()
                     WHERE channel = :channel
                       AND (
                            external_contact = :psid
                         OR phone = :psid
                         OR regexp_replace(COALESCE(phone, ''), '\\D', '', 'g') = :psid
                       )"
                )->execute(['name' => $name, 'channel' => $channel, 'psid' => $psid]);
            } catch (\Throwable) {
                // tabla clientes opcional
            }
        } catch (\Throwable) {
            // ignore
        }
    }

    /**
     * Backfill: aplica nombres de comentarios a DMs del mismo PSID.
     * Solo corre si hay DMs con nombre placeholder que ya tienen comentario con nombre real.
     */
    public static function syncMessengerDisplayNames(int $ttlSeconds = 90): int
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $last = (int) ($_SESSION['omni_names_sync_at'] ?? 0);
            if ($last > 0 && (time() - $last) < max(15, $ttlSeconds)) {
                return 0;
            }
        }

        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return 0;
        }
        $n = 0;
        try {
            $needs = (bool) $pdo->query(
                "SELECT EXISTS (
                    SELECT 1
                    FROM omni_conversations dm
                    INNER JOIN omni_conversations cmt
                      ON cmt.channel = dm.channel
                     AND cmt.thread_kind = 'comment'
                     AND cmt.phone IS NOT NULL
                     AND TRIM(cmt.phone) <> ''
                     AND (cmt.phone = dm.external_contact OR cmt.phone = dm.phone)
                     AND cmt.contact_name IS NOT NULL
                     AND cmt.contact_name NOT LIKE 'Usuario %'
                    WHERE dm.channel IN ('messenger', 'instagram')
                      AND (dm.thread_kind = 'message' OR dm.thread_kind IS NULL)
                      AND (dm.contact_name IS NULL OR dm.contact_name LIKE 'Usuario %')
                 )"
            )->fetchColumn();
            if (!$needs) {
                if (session_status() === PHP_SESSION_ACTIVE) {
                    $_SESSION['omni_names_sync_at'] = time();
                }
                return 0;
            }

            $rows = $pdo->query(
                "SELECT DISTINCT channel, phone, contact_name
                 FROM omni_conversations
                 WHERE thread_kind = 'comment'
                   AND phone IS NOT NULL AND TRIM(phone) <> ''
                   AND contact_name IS NOT NULL
                   AND contact_name NOT LIKE 'Usuario %'
                   AND channel IN ('messenger', 'instagram')"
            )->fetchAll();
            foreach ($rows as $row) {
                self::propagateContactName(
                    $pdo,
                    (string) $row['channel'],
                    (string) $row['phone'],
                    (string) $row['contact_name']
                );
                $n++;
            }
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['omni_names_sync_at'] = time();
            }
        } catch (\Throwable) {
            return $n;
        }
        return $n;
    }

    /**
     * @param array<string,mixed> $value
     */
    private static function ingestInstagramComment(\PDO $pdo, array $value, string $pageId, string $fallbackAt): bool
    {
        $commentId = trim((string) ($value['id'] ?? $value['comment_id'] ?? ''));
        if ($commentId === '') {
            return false;
        }
        // Borrados / edits sin texto útil
        if (strtolower((string) ($value['verb'] ?? 'add')) === 'remove') {
            return true; // consumir evento sin crear hilo
        }

        $text = trim((string) ($value['text'] ?? $value['message'] ?? ''));
        if ($text === '') {
            $text = '[comentario]';
        }
        $from = is_array($value['from'] ?? null) ? $value['from'] : [];
        $fromId = trim((string) ($from['id'] ?? ''));
        $fromName = trim((string) ($from['username'] ?? $from['name'] ?? ''));
        $rawUsername = $fromName;

        // Comentario / reply de la propia cuenta (mismo page_id o @handle) → omitir hilo
        if (
            ($fromId !== '' && self::isOwnMetaActor($fromId, $pageId))
            || ($rawUsername !== '' && self::isOwnMetaUsername($rawUsername))
        ) {
            // Si es reply a un comentario de cliente, anexar como outbound en ese hilo
            $parentId = trim((string) ($value['parent_id'] ?? ''));
            if ($parentId === '' && is_array($value['parent'] ?? null)) {
                $parentId = trim((string) ($value['parent']['id'] ?? ''));
            }
            if ($parentId !== '' && $parentId !== $commentId) {
                self::attachOwnCommentReplyToParent($pdo, $parentId, $text, $commentId, $value, $fallbackAt);
            }
            return true; // consumir evento sin listar cuenta propia
        }
        if ($fromName !== '' && !str_starts_with($fromName, '@') && !str_contains($fromName, ' ')) {
            // username IG → @user
            $fromName = '@' . ltrim($fromName, '@');
        }
        if ($fromName === '') {
            $fromName = $fromId !== '' ? ('@' . substr($fromId, -6)) : ('Comentario ' . substr($commentId, -6));
        }

        // Primero crear hilo (Graph media no debe bloquear el comentario)
        $mediaId = (string) (
            (is_array($value['media'] ?? null) ? ($value['media']['id'] ?? '') : '')
            ?: ($value['media_id'] ?? '')
        );
        $convId = self::upsertConversation($pdo, [
            'channel'          => 'instagram',
            'external_contact' => 'cmt:' . $commentId,
            'contact_name'     => $fromName,
            'phone'            => $fromId !== '' ? $fromId : $commentId,
            'page_id'          => $pageId !== '' ? $pageId : null,
            'phone_number_id'  => null,
            'preview'          => $text,
            'at'               => $fallbackAt,
            'thread_kind'      => 'comment',
            'comment_id'       => $commentId,
            'post_id'          => $mediaId !== '' ? $mediaId : null,
            'post_media_url'   => null,
            'post_permalink'   => null,
            'post_caption'     => null,
        ]);

        if ($fromId !== '' && !self::isPlaceholderName($fromName)) {
            self::propagateContactName($pdo, 'instagram', $fromId, $fromName);
        }

        $ok = self::insertMessage($pdo, $convId, [
            'direction'    => 'inbound',
            'sender'       => 'client',
            'body'         => $text,
            'message_type' => 'comment',
            'external_id'  => $commentId,
            'raw'          => $value,
            'at'           => $fallbackAt,
        ]);

        // Enrichment opcional (token IG / Graph); no tumba el ingest
        try {
            self::enrichCommentMediaIfNeeded($convId);
        } catch (\Throwable) {
            // ignore
        }

        if ($ok) {
            try {
                $full = self::findConversation($convId) ?? [];
                \Arya\Services\AiAgentDispatch::maybeReply($convId, $text, array_merge($full, [
                    'channel'              => 'instagram',
                    'external_contact'     => $fromId !== '' ? $fromId : ('cmt:' . $commentId),
                    'contact_name'         => $fromName,
                    'phone'                => $fromId,
                    'thread_kind'          => 'comment',
                    'comment_id'           => $commentId,
                    'post_id'              => $mediaId,
                    'inbound_external_id'  => $commentId,
                    'inbound_message_type' => 'comment',
                    'event_type'           => 'comment',
                ]));
            } catch (\Throwable) {
            }
        }

        // true si el hilo existe (aunque el mensaje sea duplicado del mismo comment_id)
        return $ok || $convId > 0;
    }

    /**
     * Reply de la propia página a un comentario de cliente → outbound en el hilo padre (sin nuevo inbox).
     *
     * @param array<string,mixed> $raw
     */
    private static function attachOwnCommentReplyToParent(
        \PDO $pdo,
        string $parentCommentId,
        string $text,
        string $replyCommentId,
        array $raw,
        string $at
    ): void {
        $parentCommentId = trim($parentCommentId);
        $replyCommentId = trim($replyCommentId);
        if ($parentCommentId === '') {
            return;
        }
        try {
            $stmt = $pdo->prepare(
                "SELECT id FROM omni_conversations
                 WHERE thread_kind = 'comment'
                   AND (comment_id = :cid OR external_contact = :ext)
                 ORDER BY id DESC
                 LIMIT 1"
            );
            $stmt->execute([
                'cid' => $parentCommentId,
                'ext' => 'cmt:' . $parentCommentId,
            ]);
            $convId = (int) ($stmt->fetchColumn() ?: 0);
            if ($convId < 1) {
                return;
            }
            self::insertMessage($pdo, $convId, [
                'direction'    => 'outbound',
                'sender'       => 'agent',
                'body'         => $text,
                'message_type' => 'comment',
                'external_id'  => $replyCommentId !== '' ? $replyCommentId : null,
                'raw'          => $raw,
                'at'           => $at,
            ]);
            $upd = $pdo->prepare(
                'UPDATE omni_conversations
                 SET preview = :preview, last_message_at = CAST(:at AS TIMESTAMPTZ), updated_at = NOW()
                 WHERE id = :id'
            );
            $upd->execute([
                'preview' => $text,
                'at'      => $at,
                'id'      => $convId,
            ]);
        } catch (\Throwable) {
            // ignore
        }
    }

    /**
     * @param array<string,mixed> $value
     */
    private static function ingestFacebookComment(\PDO $pdo, array $value, string $pageId, string $fallbackAt): bool
    {
        if (strtolower((string) ($value['verb'] ?? 'add')) === 'remove') {
            return true;
        }
        $commentId = (string) ($value['comment_id'] ?? $value['id'] ?? '');
        if ($commentId === '') {
            return false;
        }
        $text = trim((string) ($value['message'] ?? ''));
        if ($text === '') {
            $text = '[comentario]';
        }
        $from = is_array($value['from'] ?? null) ? $value['from'] : [];
        $fromId = trim((string) ($from['id'] ?? ''));
        $fromName = trim((string) ($from['name'] ?? ''));
        // Comentario de la propia Page → anexar reply al hilo del cliente si aplica
        if (
            ($fromId !== '' && self::isOwnMetaActor($fromId, $pageId))
            || ($fromName !== '' && self::isOwnMetaUsername($fromName))
        ) {
            $parentId = trim((string) ($value['parent_id'] ?? ''));
            if ($parentId === '' && is_array($value['parent'] ?? null)) {
                $parentId = trim((string) ($value['parent']['id'] ?? ''));
            }
            // En feed FB, parent_id a veces es el post; solo anexar si hay hilo con ese comment_id
            if ($parentId !== '' && $parentId !== $commentId) {
                self::attachOwnCommentReplyToParent($pdo, $parentId, $text, $commentId, $value, $fallbackAt);
            }
            return true;
        }
        if ($fromName === '') {
            $fromName = $fromId !== '' ? ('Usuario ' . substr($fromId, -6)) : ('Comentario ' . substr($commentId, -6));
        }

        $postId = trim((string) ($value['post_id'] ?? $value['parent_id'] ?? ''));
        $convId = self::upsertConversation($pdo, [
            'channel'          => 'messenger',
            'external_contact' => 'cmt:' . $commentId,
            'contact_name'     => $fromName,
            'phone'            => $fromId !== '' ? $fromId : $commentId,
            'page_id'          => $pageId !== '' ? $pageId : null,
            'phone_number_id'  => null,
            'preview'          => $text,
            'at'               => $fallbackAt,
            'thread_kind'      => 'comment',
            'comment_id'       => $commentId,
            'post_id'          => $postId !== '' ? $postId : null,
            'post_media_url'   => null,
            'post_permalink'   => null,
            'post_caption'     => null,
        ]);

        // Mismo PSID que el DM de Messenger → unificar nombre en todos los hilos
        if ($fromId !== '' && !self::isPlaceholderName($fromName)) {
            self::propagateContactName($pdo, 'messenger', $fromId, $fromName);
        }

        $ok = self::insertMessage($pdo, $convId, [
            'direction'    => 'inbound',
            'sender'       => 'client',
            'body'         => $text,
            'message_type' => 'comment',
            'external_id'  => $commentId,
            'raw'          => $value,
            'at'           => $fallbackAt,
        ]);

        try {
            self::enrichCommentMediaIfNeeded($convId);
        } catch (\Throwable) {
            // ignore
        }

        if ($ok) {
            try {
                $full = self::findConversation($convId) ?? [];
                \Arya\Services\AiAgentDispatch::maybeReply($convId, $text, array_merge($full, [
                    'channel'              => 'messenger',
                    'external_contact'     => $fromId !== '' ? $fromId : ('cmt:' . $commentId),
                    'contact_name'         => $fromName,
                    'phone'                => $fromId,
                    'thread_kind'          => 'comment',
                    'comment_id'           => $commentId,
                    'post_id'              => $postId,
                    'inbound_external_id'  => $commentId,
                    'inbound_message_type' => 'comment',
                    'event_type'           => 'comment',
                ]));
            } catch (\Throwable) {
            }
        }

        return $ok || $convId > 0;
    }

    /** ¿La conversación ya tiene copia local de la imagen del post? */
    public static function hasLocalPostMedia(array $conv): bool
    {
        $url = trim((string) ($conv['post_media_url'] ?? ''));
        if ($url === '') {
            return false;
        }
        return self::localPathFromMediaUrl($url) !== null;
    }

    /** Resuelve ruta absoluta en disco desde post_media_url (relativa o absoluta). */
    public static function localPathFromMediaUrl(string $mediaUrl): ?string
    {
        $mediaUrl = trim($mediaUrl);
        if ($mediaUrl === '') {
            return null;
        }
        if (preg_match('#/uploads/omni/([A-Za-z0-9._-]+)$#', $mediaUrl, $m)) {
            $path = public_path('uploads/omni/' . $m[1]);
            return is_file($path) ? $path : null;
        }
        return null;
    }

    /**
     * Asegura imagen local del post (enrich + download). Devuelve path en disco o null.
     */
    public static function ensureLocalPostMedia(int|string $conversationId): ?string
    {
        self::bootstrap();
        $conv = self::findConversation($conversationId);
        if (!is_array($conv) || (string) ($conv['thread_kind'] ?? '') !== 'comment') {
            return null;
        }

        $existing = self::localPathFromMediaUrl((string) ($conv['post_media_url'] ?? ''));
        if ($existing !== null) {
            return $existing;
        }

        self::enrichCommentMediaIfNeeded($conversationId);
        $conv = self::findConversation($conversationId) ?? $conv;
        return self::localPathFromMediaUrl((string) ($conv['post_media_url'] ?? ''));
    }

    /**
     * Si un comentario aún no tiene imagen del post, intenta enriquecerla vía Graph.
     */
    public static function enrichCommentMediaIfNeeded(int|string $conversationId): void
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return;
        }
        try {
            $stmt = $pdo->prepare(
                'SELECT id, channel, comment_id, post_id, post_media_url, thread_kind
                 FROM omni_conversations WHERE id = :id LIMIT 1'
            );
            $stmt->execute(['id' => $conversationId]);
            $row = $stmt->fetch();
            if (!$row || (string) ($row['thread_kind'] ?? '') !== 'comment') {
                return;
            }

            $existingMedia = trim((string) ($row['post_media_url'] ?? ''));
            $isLocalUpload = $existingMedia !== '' && str_contains($existingMedia, '/uploads/');
            // Si ya hay copia local, no repetir Graph
            if ($isLocalUpload) {
                return;
            }

            $channel = (string) $row['channel'];
            $value = [
                'comment_id' => (string) ($row['comment_id'] ?? ''),
                'post_id'    => (string) ($row['post_id'] ?? ''),
                'id'         => (string) ($row['comment_id'] ?? ''),
            ];
            // Intentar raw del primer mensaje inbound
            $rawStmt = $pdo->prepare(
                'SELECT raw FROM omni_messages
                 WHERE conversation_id = :id AND direction = \'inbound\'
                 ORDER BY id ASC LIMIT 1'
            );
            $rawStmt->execute(['id' => $conversationId]);
            $raw = $rawStmt->fetchColumn();
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $value = array_merge($value, $decoded);
                }
            }

            $ctx = \Arya\Services\MetaCloud::resolveCommentPostContext($channel, $value);
            if (!$ctx['ok'] && $existingMedia === '') {
                return;
            }

            $mediaUrl = trim((string) ($ctx['media_url'] ?? $existingMedia));
            // Descargar a /uploads para que el navegador no rompa CDN Meta (expira / hotlink)
            if ($mediaUrl !== '' && !str_contains($mediaUrl, '/uploads/')) {
                $creds = TokenMeta::channelCredentials($channel === 'instagram' ? 'instagram' : 'messenger');
                $token = is_array($creds) ? (string) ($creds['token'] ?? '') : '';
                $dl = \Arya\Services\MetaCloud::downloadRemoteMedia(
                    $mediaUrl,
                    $token !== '' ? $token : null,
                    'post_' . preg_replace('/\W+/', '_', (string) ($ctx['post_id'] ?? $conversationId))
                );
                if (!empty($dl['ok']) && !empty($dl['public_url'])) {
                    $mediaUrl = (string) $dl['public_url'];
                } else {
                    // CDN Meta suele fallar en el browser: mejor vacío + permalink
                    $mediaUrl = '';
                }
            }

            if ($mediaUrl === '' && empty($ctx['permalink']) && empty($ctx['post_id'])) {
                return;
            }

            $upd = $pdo->prepare(
                'UPDATE omni_conversations SET
                    post_id = COALESCE(NULLIF(:post_id, \'\'), post_id),
                    post_media_url = CASE
                        WHEN :media_set <> \'\' THEN :media_val
                        WHEN post_media_url LIKE \'%/uploads/%\' THEN post_media_url
                        ELSE NULL
                    END,
                    post_permalink = COALESCE(NULLIF(:permalink, \'\'), post_permalink),
                    post_caption = COALESCE(NULLIF(:caption, \'\'), post_caption),
                    updated_at = NOW()
                 WHERE id = :id'
            );
            $upd->execute([
                'id'        => $conversationId,
                'post_id'   => (string) ($ctx['post_id'] ?? ''),
                'media_set' => $mediaUrl,
                'media_val' => $mediaUrl,
                'permalink' => (string) ($ctx['permalink'] ?? ''),
                'caption'   => (string) ($ctx['caption'] ?? ''),
            ]);
        } catch (\Throwable) {
            // ignore
        }
    }

    /** Fuerza guardar post_id/permalink mínimos desde raw aunque Graph falle. */
    public static function seedCommentPostRefs(int|string $conversationId): void
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return;
        }
        try {
            $rawStmt = $pdo->prepare(
                'SELECT raw FROM omni_messages
                 WHERE conversation_id = :id AND direction = \'inbound\'
                 ORDER BY id ASC LIMIT 1'
            );
            $rawStmt->execute(['id' => $conversationId]);
            $raw = $rawStmt->fetchColumn();
            $value = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
            if (!is_array($value)) {
                return;
            }
            $postId = trim((string) ($value['post_id'] ?? $value['parent_id'] ?? ''));
            if ($postId === '') {
                return;
            }
            $link = 'https://www.facebook.com/' . $postId;
            $pdo->prepare(
                'UPDATE omni_conversations SET
                    post_id = COALESCE(post_id, :post_id),
                    post_permalink = COALESCE(post_permalink, :link),
                    updated_at = NOW()
                 WHERE id = :id AND thread_kind = \'comment\''
            )->execute(['id' => $conversationId, 'post_id' => $postId, 'link' => $link]);
        } catch (\Throwable) {
            // ignore
        }
    }

    /**
     * @param array{channel:string,external_contact:string,contact_name:string,phone:?string,page_id:?string,phone_number_id:?string,preview:string,at:string,thread_kind?:string,comment_id?:?string,post_id?:?string,post_media_url?:?string,post_permalink?:?string,post_caption?:?string} $data
     */
    private static function upsertConversation(\PDO $pdo, array $data): int
    {
        $threadKind = (string) ($data['thread_kind'] ?? 'message');
        if ($threadKind !== 'comment') {
            $threadKind = 'message';
        }
        $commentId = $data['comment_id'] ?? null;
        if ($commentId !== null) {
            $commentId = trim((string) $commentId);
            if ($commentId === '') {
                $commentId = null;
            }
        }
        $postId = isset($data['post_id']) ? trim((string) $data['post_id']) : '';
        $postMedia = isset($data['post_media_url']) ? trim((string) $data['post_media_url']) : '';
        $postLink = isset($data['post_permalink']) ? trim((string) $data['post_permalink']) : '';
        $postCap = isset($data['post_caption']) ? trim((string) $data['post_caption']) : '';

        $stmt = $pdo->prepare(
            'INSERT INTO omni_conversations
                (channel, external_contact, contact_name, phone, page_id, phone_number_id, preview, status,
                 unread_count, last_message_at, updated_at, thread_kind, comment_id,
                 post_id, post_media_url, post_permalink, post_caption)
             VALUES
                (:channel, :external_contact, :contact_name, :phone, :page_id, :phone_number_id, :preview, \'open\',
                 1, CAST(:at AS TIMESTAMPTZ), NOW(), :thread_kind, :comment_id,
                 NULLIF(:post_id, \'\'), NULLIF(:post_media_url, \'\'), NULLIF(:post_permalink, \'\'), NULLIF(:post_caption, \'\'))
             ON CONFLICT (channel, external_contact) DO UPDATE SET
                contact_name = CASE
                    WHEN omni_conversations.contact_name IS NULL OR TRIM(omni_conversations.contact_name) = \'\'
                        THEN EXCLUDED.contact_name
                    WHEN omni_conversations.contact_name LIKE \'Usuario %\'
                         AND EXCLUDED.contact_name IS NOT NULL
                         AND EXCLUDED.contact_name NOT LIKE \'Usuario %\'
                        THEN EXCLUDED.contact_name
                    WHEN EXCLUDED.contact_name LIKE \'Usuario %\'
                        THEN omni_conversations.contact_name
                    ELSE COALESCE(NULLIF(TRIM(EXCLUDED.contact_name), \'\'), omni_conversations.contact_name)
                END,
                phone = COALESCE(EXCLUDED.phone, omni_conversations.phone),
                page_id = COALESCE(EXCLUDED.page_id, omni_conversations.page_id),
                phone_number_id = COALESCE(EXCLUDED.phone_number_id, omni_conversations.phone_number_id),
                preview = EXCLUDED.preview,
                status = \'open\',
                unread_count = omni_conversations.unread_count + 1,
                last_message_at = EXCLUDED.last_message_at,
                thread_kind = COALESCE(EXCLUDED.thread_kind, omni_conversations.thread_kind),
                comment_id = COALESCE(EXCLUDED.comment_id, omni_conversations.comment_id),
                post_id = COALESCE(EXCLUDED.post_id, omni_conversations.post_id),
                post_media_url = COALESCE(EXCLUDED.post_media_url, omni_conversations.post_media_url),
                post_permalink = COALESCE(EXCLUDED.post_permalink, omni_conversations.post_permalink),
                post_caption = COALESCE(EXCLUDED.post_caption, omni_conversations.post_caption),
                updated_at = NOW()
             RETURNING id'
        );
        $stmt->execute([
            'channel'          => $data['channel'],
            'external_contact' => $data['external_contact'],
            'contact_name'     => $data['contact_name'],
            'phone'            => $data['phone'],
            'page_id'          => $data['page_id'],
            'phone_number_id'  => $data['phone_number_id'],
            'preview'          => mb_substr($data['preview'], 0, 280),
            'at'               => $data['at'],
            'thread_kind'      => $threadKind,
            'comment_id'       => $commentId,
            'post_id'          => $postId,
            'post_media_url'   => $postMedia,
            'post_permalink'   => $postLink,
            'post_caption'     => mb_substr($postCap, 0, 500),
        ]);

        $convId = (int) $stmt->fetchColumn();

        // Alta/actualización automática en Gestión de clientes
        try {
            // CRM unifica por actor (PSID/IGSID/tel), no por cmt:commentId
            Client::upsertFromOmni([
                'channel'              => (string) $data['channel'],
                'external_contact'     => (string) $data['external_contact'],
                'contact_name'         => (string) ($data['contact_name'] ?? ''),
                'phone'                => $data['phone'] !== null ? (string) $data['phone'] : null,
                'omni_conversation_id' => $convId,
                'status'               => 'open',
                'at'                   => (string) $data['at'],
            ]);
        } catch (\Throwable) {
            // no bloquear inbox si falla el CRM
        }

        return $convId;
    }

    /**
     * @param array{direction:string,sender:string,body:string,message_type:string,media_url?:?string,external_id:?string,raw:array,at:string} $data
     */
    private static function insertMessage(\PDO $pdo, int $conversationId, array $data): bool
    {
        try {
            if (!empty($data['external_id'])) {
                $check = $pdo->prepare('SELECT 1 FROM omni_messages WHERE external_id = :eid LIMIT 1');
                $check->execute(['eid' => $data['external_id']]);
                if ($check->fetchColumn()) {
                    return false;
                }
            }

            $stmt = $pdo->prepare(
                'INSERT INTO omni_messages
                    (conversation_id, direction, sender, body, message_type, media_url, external_id, raw, created_at)
                 VALUES
                    (:conversation_id, :direction, :sender, :body, :message_type, :media_url, :external_id, CAST(:raw AS JSONB), CAST(:at AS TIMESTAMPTZ))'
            );
            $stmt->execute([
                'conversation_id' => $conversationId,
                'direction'       => $data['direction'],
                'sender'          => $data['sender'],
                'body'            => $data['body'],
                'message_type'    => $data['message_type'],
                'media_url'       => $data['media_url'] ?? null,
                'external_id'     => $data['external_id'],
                'raw'             => json_encode($data['raw'], JSON_UNESCAPED_UNICODE) ?: '{}',
                'at'              => $data['at'],
            ]);

            return $stmt->rowCount() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,bool>|null $aiActive
     * @return array<string,mixed>
     */
    private static function mapConversation(array $row, ?array $aiActive = null): array
    {
        $kind = (string) ($row['thread_kind'] ?? 'message');
        if ($kind !== 'comment') {
            $kind = 'message';
        }

        $channel = (string) ($row['channel'] ?? '');
        $mode = strtolower((string) ($row['handling_mode'] ?? 'ai'));
        if ($mode !== 'human') {
            $mode = 'ai';
        }
        $assignedId = isset($row['assigned_user_id']) && $row['assigned_user_id'] !== null && $row['assigned_user_id'] !== ''
            ? (int) $row['assigned_user_id']
            : null;
        $aiActive = $aiActive ?? self::aiActiveByChannel();
        $aiOn = !empty($aiActive[strtolower($channel)]);

        $mapped = [
            'id'      => (int) $row['id'],
            'channel' => $channel,
            'client'  => (string) ($row['contact_name'] ?: $row['external_contact']),
            'phone'   => (string) ($row['phone'] ?: $row['external_contact']),
            'preview' => (string) ($row['preview'] ?? ''),
            'time'    => self::formatTime((string) ($row['last_message_at'] ?? $row['created_at'] ?? '')),
            'unread'  => (int) ($row['unread_count'] ?? 0),
            'status'  => (string) ($row['status'] ?? 'open'),
            'page_id' => (string) ($row['page_id'] ?? ''),
            'phone_number_id' => (string) ($row['phone_number_id'] ?? ''),
            'external_contact'=> (string) ($row['external_contact'] ?? ''),
            'thread_kind'     => $kind,
            'comment_id'      => (string) ($row['comment_id'] ?? ''),
            'post_id'         => (string) ($row['post_id'] ?? ''),
            'post_media_url'  => (string) ($row['post_media_url'] ?? ''),
            'post_permalink'  => (string) ($row['post_permalink'] ?? ''),
            'post_caption'    => (string) ($row['post_caption'] ?? ''),
            'handling_mode'   => $mode,
            'assigned_user_id'=> $assignedId,
            'assigned_name'   => (string) ($row['assigned_name'] ?? ''),
            'assigned_at'     => isset($row['assigned_at']) && $row['assigned_at'] !== null && $row['assigned_at'] !== ''
                ? (string) $row['assigned_at']
                : null,
            'ai_agent_active' => $aiOn,
            'attended_by_ai'  => $mode === 'ai' && $aiOn,
        ];

        if (array_key_exists('last_inbound_at', $row)) {
            $window = self::messagingWindowFromRow($row);
            $mapped['can_reply'] = $window['can_reply'];
            $mapped['window_hours'] = $window['window_hours'];
            $mapped['window_expires_at'] = $window['expires_at'];
            $mapped['window_label'] = $window['label'];
            $mapped['last_inbound_at'] = $window['last_inbound_at'];
            $mapped['window_message'] = $window['message'];
        }

        return $mapped;
    }

    private static function formatTime(string $ts): string
    {
        if ($ts === '') {
            return '';
        }
        try {
            $dt = new \DateTimeImmutable($ts);
            $dt = $dt->setTimezone(new \DateTimeZone('America/Bogota'));
            $today = new \DateTimeImmutable('today', new \DateTimeZone('America/Bogota'));
            if ($dt->format('Y-m-d') === $today->format('Y-m-d')) {
                return $dt->format('H:i');
            }
            return $dt->format('d/m H:i');
        } catch (\Throwable) {
            return $ts;
        }
    }
}
