<?php

declare(strict_types=1);

namespace Arya\Services;

use Arya\Core\Database;

/**
 * Webhook Meta Business (WhatsApp / Messenger / Instagram).
 * Callback URL + verify token para configurar en Meta Developers / Business.
 */
final class MetaWebhook
{
    private static bool $bootstrapped = false;

    public static function bootstrap(): void
    {
        if (self::$bootstrapped) {
            return;
        }
        self::$bootstrapped = true;

        $pdo = Database::connection();
        if (!$pdo) {
            return;
        }

        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS meta_webhook_config (
                    id            SMALLINT PRIMARY KEY DEFAULT 1 CHECK (id = 1),
                    verify_token  TEXT NOT NULL,
                    updated_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
                )'
            );
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS meta_webhook_inbox (
                    id            BIGSERIAL PRIMARY KEY,
                    object_type   VARCHAR(60),
                    page_id       VARCHAR(120),
                    canal         VARCHAR(40),
                    event_type    VARCHAR(80),
                    payload       JSONB NOT NULL,
                    created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
                )'
            );
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_meta_inbox_created ON meta_webhook_inbox (created_at DESC)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_meta_inbox_page ON meta_webhook_inbox (page_id)');

            $count = (int) $pdo->query('SELECT COUNT(*) FROM meta_webhook_config')->fetchColumn();
            if ($count === 0) {
                $token = self::generateToken();
                $stmt = $pdo->prepare(
                    'INSERT INTO meta_webhook_config (id, verify_token, updated_at)
                     VALUES (1, :token, NOW())'
                );
                $stmt->execute(['token' => $token]);
            }
        } catch (\Throwable) {
            // sin DB
        }
    }

    /**
     * URL pública HTTPS para pegar en Meta Business / Developers.
     * Siempre producción: Meta no puede alcanzar localhost.
     */
    public static function callbackUrl(): string
    {
        $fromEnv = trim((string) env('META_WEBHOOK_URL', ''));
        if ($fromEnv !== '') {
            return rtrim($fromEnv, '/');
        }

        return 'https://app.aisscol.com/Arya/public/api/meta-webhook.php';
    }

    public static function verifyToken(): string
    {
        self::bootstrap();

        $fromEnv = trim((string) env('META_VERIFY_TOKEN', ''));
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        $pdo = Database::connection();
        if (!$pdo) {
            return 'arya-meta-verify';
        }

        try {
            $token = (string) $pdo->query('SELECT verify_token FROM meta_webhook_config WHERE id = 1')->fetchColumn();
            if ($token !== '') {
                return $token;
            }
        } catch (\Throwable) {
            // fallthrough
        }

        return self::rotateVerifyToken();
    }

    public static function rotateVerifyToken(): string
    {
        self::bootstrap();
        $token = self::generateToken();
        $pdo = Database::connection();
        if (!$pdo) {
            return $token;
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO meta_webhook_config (id, verify_token, updated_at)
                 VALUES (1, :token, NOW())
                 ON CONFLICT (id) DO UPDATE SET
                    verify_token = EXCLUDED.verify_token,
                    updated_at = NOW()'
            );
            $stmt->execute(['token' => $token]);
        } catch (\Throwable) {
            // ignore
        }

        return $token;
    }

    /**
     * Verificación GET de Meta (hub.mode / hub.verify_token / hub.challenge).
     */
    public static function handleVerification(string $mode, string $token, string $challenge): void
    {
        $expected = self::verifyToken();

        if ($mode === 'subscribe' && $token !== '' && hash_equals($expected, $token)) {
            http_response_code(200);
            header('Content-Type: text/plain; charset=utf-8');
            echo $challenge;
            exit;
        }

        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Forbidden';
        exit;
    }

    /**
     * Recibe POST de eventos Meta y los guarda en inbox.
     *
     * @return array{ok:bool, stored:int, message:string}
     */
    public static function handleEvent(string $rawBody): array
    {
        self::bootstrap();

        $data = json_decode($rawBody, true);
        if (!is_array($data)) {
            return ['ok' => false, 'stored' => 0, 'message' => 'JSON inválido'];
        }

        $pdo = Database::connection();
        if (!$pdo) {
            return ['ok' => false, 'stored' => 0, 'message' => 'Sin DB'];
        }

        $object = (string) ($data['object'] ?? '');
        $entries = $data['entry'] ?? [];
        if (!is_array($entries)) {
            $entries = [];
        }

        $stored = 0;
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO meta_webhook_inbox (object_type, page_id, canal, event_type, payload)
                 VALUES (:object_type, :page_id, :canal, :event_type, CAST(:payload AS JSONB))'
            );

            if ($entries === []) {
                $stmt->execute([
                    'object_type' => $object !== '' ? $object : null,
                    'page_id'     => null,
                    'canal'       => self::canalFromObject($object),
                    'event_type'  => 'raw',
                    'payload'     => $rawBody,
                ]);
                $stored = 1;
            } else {
                foreach ($entries as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }
                    $pageId = (string) ($entry['id'] ?? '');
                    $eventType = self::detectEventType($entry);
                    $stmt->execute([
                        'object_type' => $object !== '' ? $object : null,
                        'page_id'     => $pageId !== '' ? $pageId : null,
                        'canal'       => self::canalFromObject($object),
                        'event_type'  => $eventType,
                        'payload'     => json_encode($entry, JSON_UNESCAPED_UNICODE) ?: '{}',
                    ]);
                    $stored++;
                }
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'stored' => 0, 'message' => $e->getMessage()];
        }

        // Sincronizar a Omnicanalidad (omni_conversations / omni_messages)
        try {
            \Arya\Models\Omnichannel::syncFromMetaInbox();
        } catch (\Throwable) {
            // no bloquear respuesta a Meta
        }

        return ['ok' => true, 'stored' => $stored, 'message' => 'Eventos recibidos'];
    }

    /** @return list<array<string,mixed>> */
    public static function recentInbox(int $limit = 8): array
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return [];
        }

        $limit = max(1, min(50, $limit));
        try {
            $stmt = $pdo->query(
                "SELECT id, object_type, page_id, canal, event_type, created_at
                 FROM meta_webhook_inbox
                 ORDER BY created_at DESC
                 LIMIT {$limit}"
            );
            return $stmt ? $stmt->fetchAll() : [];
        } catch (\Throwable) {
            return [];
        }
    }

    public static function inboxCount(): int
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return 0;
        }
        try {
            return (int) $pdo->query('SELECT COUNT(*) FROM meta_webhook_inbox')->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    private static function generateToken(): string
    {
        return 'arya-meta-' . bin2hex(random_bytes(12));
    }

    private static function canalFromObject(string $object): ?string
    {
        $object = strtolower($object);
        return match ($object) {
            'whatsapp_business_account', 'whatsapp' => 'whatsapp',
            'page' => 'messenger',
            'instagram' => 'instagram',
            default => null,
        };
    }

    /** @param array<string,mixed> $entry */
    private static function detectEventType(array $entry): string
    {
        if (isset($entry['messaging']) && is_array($entry['messaging'])) {
            return 'messaging';
        }
        if (isset($entry['changes']) && is_array($entry['changes'])) {
            $field = $entry['changes'][0]['field'] ?? 'changes';
            return is_string($field) ? $field : 'changes';
        }
        if (isset($entry['standby'])) {
            return 'standby';
        }
        return 'event';
    }
}
