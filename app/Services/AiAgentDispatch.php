<?php

declare(strict_types=1);

namespace Arya\Services;

use Arya\Core\Database;
use Arya\Models\Omnichannel;

/**
 * Despacha cada inbound nuevo al webhook del Agente IA (n8n).
 *
 * Producción:
 * - Un mensaje cliente = un despacho (dedupe por mid / inbound_id).
 * - No bloquea follow-ups del cliente (nada de cooldown 45s).
 * - Lock con espera: no se pierde el mensaje si hay otro en curso.
 * - Respuesta n8n: reply|text|message|output → Meta + omni (sender=ai).
 */
final class AiAgentDispatch
{
    private const LOCK_WAIT_MS = 25000;
    private const SOFT_DEDUP_SEC = 6;

    /**
     * @param array<string,mixed> $conv  conversación mapeada o parcial
     */
    public static function maybeReply(int $conversationId, string $inboundText, array $conv = []): void
    {
        $pdo = Database::connection();
        if (!$pdo || $conversationId < 1) {
            return;
        }

        $inboundText = trim($inboundText);
        if ($inboundText === '') {
            return;
        }
        // Placeholder vacío real (sin media) — no gastar n8n
        if ($inboundText === '[mensaje]' && empty($conv['inbound_has_media'])) {
            return;
        }
        if (str_starts_with($inboundText, '={{') || preg_match('/^=\s*¿/', $inboundText)) {
            return;
        }

        $lockFp = null;
        $inboundId = trim((string) ($conv['inbound_external_id'] ?? ''));

        try {
            self::ensureTables($pdo);

            if ($conv === []) {
                $found = Omnichannel::findConversation($conversationId);
                $conv = is_array($found) ? $found : [];
            } else {
                $found = Omnichannel::findConversation($conversationId);
                if (is_array($found)) {
                    $conv = array_merge($found, $conv);
                }
            }

            // Humano tomó la conversación → no despachar
            // Si se "cierra", vuelve a mode=ai y la IA sí responde al siguiente mensaje
            $mode = strtolower((string) ($conv['handling_mode'] ?? 'ai'));
            if ($mode === 'human') {
                return;
            }

            $channel = strtolower(trim((string) ($conv['channel'] ?? '')));
            if ($channel === '') {
                return;
            }

            $cfg = self::configForChannel($pdo, $channel);
            if ($cfg === null || empty($cfg['activo'])) {
                return;
            }
            $url = trim((string) ($cfg['webhook_url'] ?? ''));
            if ($url === '' || !extension_loaded('curl')) {
                return;
            }

            // Dedupe duro por mid Meta (reentregas webhook)
            if ($inboundId !== '' && self::isInboundAlreadyDispatched($pdo, $inboundId)) {
                return;
            }

            // Lock con espera: procesar en serie sin tirar el mensaje
            $lockFp = self::acquireLock($conversationId, self::LOCK_WAIT_MS);
            if ($lockFp === null) {
                self::log('lock_timeout', $conversationId, $inboundId, $inboundText);
                return;
            }

            // Re-check tras lock
            if ($inboundId !== '' && self::isInboundAlreadyDispatched($pdo, $inboundId)) {
                return;
            }
            // Soft dedupe solo sin mid (reentrega rara): mismo texto en pocos segundos
            if ($inboundId === '' && self::isSoftDuplicate($pdo, $conversationId, $inboundText)) {
                return;
            }

            // Claim antes del HTTP → evita doble curl si Meta reentrega en paralelo
            // Sin mid: id único por intento (el soft-dedupe de texto ya cubre reentregas)
            $claimId = $inboundId !== ''
                ? $inboundId
                : ('soft:' . $conversationId . ':' . bin2hex(random_bytes(8)));
            if (!self::claimInbound($pdo, $conversationId, $claimId, $inboundText)) {
                return;
            }
            $claimedKey = $claimId;

            $eventType = strtolower(trim((string) ($conv['event_type'] ?? '')));
            if ($eventType === '') {
                $eventType = strtolower((string) ($conv['thread_kind'] ?? '')) === 'comment'
                    ? 'comment'
                    : 'message';
            }

            $payload = [
                'type'            => 'inbound_message',
                'event_type'      => $eventType,
                'source'          => 'arya_crm',
                'conversation_id' => $conversationId,
                'channel'         => $channel,
                'contact'         => (string) ($conv['external_contact'] ?? $conv['phone'] ?? ''),
                'contact_name'    => (string) ($conv['client'] ?? $conv['contact_name'] ?? ''),
                'text'            => $inboundText,
                'message_type'    => (string) ($conv['inbound_message_type'] ?? ($eventType === 'comment' ? 'comment' : 'text')),
                'thread_kind'     => (string) ($conv['thread_kind'] ?? ($eventType === 'comment' ? 'comment' : 'message')),
                'comment_id'      => (string) ($conv['comment_id'] ?? ''),
                'post_id'         => (string) ($conv['post_id'] ?? ''),
                'agent_name'      => (string) ($cfg['nombre'] ?? 'Agente IA'),
                'agent_canal'     => (string) ($cfg['canal'] ?? $channel),
                'inbound_id'      => $inboundId,
            ];

            $timeoutMs = max(5000, min(60000, (int) ($cfg['timeout_ms'] ?? 25000)));
            $ch = curl_init($url);
            if ($ch === false) {
                self::log('curl_init_fail', $conversationId, $inboundId, $inboundText);
                self::releaseClaim($pdo, $claimedKey);
                return;
            }
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT_MS => 4000,
                CURLOPT_TIMEOUT_MS     => $timeoutMs,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'X-Arya-Conversation: ' . $conversationId,
                    'X-Arya-Inbound: ' . $inboundId,
                ],
                CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);
            $body = (string) curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $cerr = curl_error($ch);
            curl_close($ch);

            if ($status < 200 || $status >= 300 || $body === '') {
                self::log('n8n_http_' . $status, $conversationId, $inboundId, $cerr !== '' ? $cerr : mb_substr($body, 0, 200));
                self::releaseClaim($pdo, $claimedKey);
                return;
            }

            $reply = self::extractReplyText($body);
            if ($reply === '') {
                self::log('n8n_empty_reply', $conversationId, $inboundId, mb_substr($body, 0, 240));
                // Mantener claim para no reintentar vacío en loop; el cliente puede reenviar
                return;
            }

            $sent = MetaCloud::sendForConversation($conv, $reply);
            if (!empty($sent['ok'])) {
                Omnichannel::storeOutbound(
                    $conversationId,
                    $reply,
                    'text',
                    null,
                    $sent['external_id'] ?? null,
                    'ai'
                );
                self::markDispatchedOk($pdo, $claimedKey, $reply);
            } else {
                self::log('meta_send_fail', $conversationId, $inboundId, (string) ($sent['message'] ?? ''));
                self::releaseClaim($pdo, $claimedKey);
            }
        } catch (\Throwable $e) {
            self::log('exception', $conversationId, $inboundId ?? '', $e->getMessage());
        } finally {
            if (is_resource($lockFp)) {
                flock($lockFp, LOCK_UN);
                fclose($lockFp);
            }
        }
    }

    /** Extrae texto de respuesta n8n: reply | text | message | output (+ anidados). */
    public static function extractReplyText(string $body): string
    {
        $body = trim($body);
        if ($body === '') {
            return '';
        }

        $json = json_decode($body, true);
        if (!is_array($json)) {
            if (str_starts_with($body, '{') || str_starts_with($body, '[')) {
                return '';
            }
            return self::sanitizeReply($body);
        }

        $candidates = [
            $json['reply'] ?? null,
            $json['text'] ?? null,
            $json['message'] ?? null,
            $json['output'] ?? null,
            is_array($json['data'] ?? null) ? ($json['data']['reply'] ?? $json['data']['output'] ?? $json['data']['text'] ?? null) : null,
            is_array($json['json'] ?? null) ? ($json['json']['reply'] ?? $json['json']['output'] ?? null) : null,
        ];

        foreach ($candidates as $c) {
            if (is_string($c)) {
                $t = self::sanitizeReply($c);
                if ($t !== '') {
                    return $t;
                }
            }
        }

        return '';
    }

    private static function sanitizeReply(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        if (str_starts_with($text, '={{')) {
            return '';
        }
        if (str_starts_with($text, '=') && !str_starts_with($text, '==')) {
            $text = ltrim(substr($text, 1));
        }
        if ($text === '' || str_starts_with($text, '{')) {
            return '';
        }
        return $text;
    }

    private static function isInboundAlreadyDispatched(\PDO $pdo, string $inboundId): bool
    {
        try {
            $stmt = $pdo->prepare(
                'SELECT 1 FROM omni_ai_dispatches WHERE inbound_external_id = :id LIMIT 1'
            );
            $stmt->execute(['id' => $inboundId]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /** Claim atómico; false si otro worker ya lo tomó. */
    private static function claimInbound(\PDO $pdo, int $conversationId, string $inboundId, string $text): bool
    {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO omni_ai_dispatches
                    (inbound_external_id, conversation_id, inbound_text, status, created_at)
                 VALUES
                    (:id, :cid, :text, \'claimed\', NOW())
                 ON CONFLICT (inbound_external_id) DO NOTHING
                 RETURNING id'
            );
            $stmt->execute([
                'id'   => $inboundId,
                'cid'  => $conversationId,
                'text' => mb_substr($text, 0, 500),
            ]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    private static function releaseClaim(\PDO $pdo, string $inboundId): void
    {
        try {
            $pdo->prepare(
                "DELETE FROM omni_ai_dispatches
                 WHERE inbound_external_id = :id AND status = 'claimed'"
            )->execute(['id' => $inboundId]);
        } catch (\Throwable) {
        }
    }

    private static function markDispatchedOk(\PDO $pdo, string $inboundId, string $reply): void
    {
        try {
            $pdo->prepare(
                "UPDATE omni_ai_dispatches
                 SET status = 'ok', reply_preview = :r, updated_at = NOW()
                 WHERE inbound_external_id = :id"
            )->execute([
                'r'  => mb_substr($reply, 0, 280),
                'id' => $inboundId,
            ]);
        } catch (\Throwable) {
        }
    }

    /** Sin mid: evita doble fire del mismo texto en pocos segundos. */
    private static function isSoftDuplicate(\PDO $pdo, int $conversationId, string $inboundText): bool
    {
        try {
            $stmt = $pdo->prepare(
                "SELECT 1 FROM omni_ai_dispatches
                 WHERE conversation_id = :cid
                   AND inbound_text = :text
                   AND created_at > NOW() - INTERVAL '" . self::SOFT_DEDUP_SEC . " seconds'
                 LIMIT 1"
            );
            $stmt->execute([
                'cid'  => $conversationId,
                'text' => mb_substr($inboundText, 0, 500),
            ]);
            if ($stmt->fetchColumn()) {
                return true;
            }
        } catch (\Throwable) {
        }
        return false;
    }

    /** @return resource|null */
    private static function acquireLock(int $conversationId, int $waitMs)
    {
        $dir = sys_get_temp_dir();
        $path = $dir . DIRECTORY_SEPARATOR . 'arya_ai_dispatch_' . $conversationId . '.lock';
        $deadline = microtime(true) + max(0.5, $waitMs / 1000);

        while (microtime(true) < $deadline) {
            $fp = @fopen($path, 'c+');
            if ($fp === false) {
                usleep(120000);
                continue;
            }
            if (flock($fp, LOCK_EX | LOCK_NB)) {
                ftruncate($fp, 0);
                fwrite($fp, (string) time());
                fflush($fp);
                return $fp;
            }
            fclose($fp);
            usleep(120000);
        }
        return null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function configForChannel(\PDO $pdo, string $channel): ?array
    {
        try {
            $stmt = $pdo->prepare(
                'SELECT canal, activo, nombre, webhook_url, timeout_ms
                 FROM ocp_ai_channels WHERE canal = :c LIMIT 1'
            );
            $stmt->execute(['c' => $channel]);
            $row = $stmt->fetch();
            if (is_array($row)) {
                return $row;
            }
        } catch (\Throwable) {
        }

        if (in_array($channel, ['whatsapp', 'instagram', 'messenger'], true)) {
            try {
                $legacy = $pdo->query(
                    'SELECT activo, nombre, webhook_url, timeout_ms FROM ocp_ai_agent WHERE id = 1'
                )->fetch();
                if (is_array($legacy) && !empty($legacy['activo'])) {
                    $legacy['canal'] = $channel;
                    return $legacy;
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }

    private static function ensureTables(\PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS ocp_ai_channels (
                id           BIGSERIAL PRIMARY KEY,
                canal        VARCHAR(80) NOT NULL UNIQUE,
                tipo         VARCHAR(40) NOT NULL DEFAULT \'meta\',
                etiqueta     VARCHAR(120) NOT NULL,
                activo       BOOLEAN NOT NULL DEFAULT FALSE,
                nombre       VARCHAR(120) NOT NULL DEFAULT \'Agente IA\',
                webhook_url  TEXT,
                timeout_ms   INT NOT NULL DEFAULT 15000,
                updated_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_by   VARCHAR(120)
            )'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS ocp_ai_agent (
                id           SMALLINT PRIMARY KEY DEFAULT 1,
                activo       BOOLEAN NOT NULL DEFAULT FALSE,
                nombre       VARCHAR(120) NOT NULL DEFAULT \'Agente IA Arya\',
                webhook_url  TEXT,
                timeout_ms   INT NOT NULL DEFAULT 15000,
                updated_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_by   VARCHAR(120)
            )'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS omni_ai_dispatches (
                id                   BIGSERIAL PRIMARY KEY,
                inbound_external_id  VARCHAR(200) NOT NULL UNIQUE,
                conversation_id      BIGINT NOT NULL,
                inbound_text         TEXT,
                reply_preview        TEXT,
                status               VARCHAR(20) NOT NULL DEFAULT \'claimed\',
                created_at           TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at           TIMESTAMPTZ
            )'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_omni_ai_disp_conv_created
             ON omni_ai_dispatches (conversation_id, created_at DESC)'
        );
    }

    private static function log(string $code, int $conversationId, string $inboundId, string $detail): void
    {
        error_log(sprintf(
            '[Arya AI dispatch] %s conv=%d inbound=%s detail=%s',
            $code,
            $conversationId,
            $inboundId,
            mb_substr($detail, 0, 300)
        ));
    }
}
