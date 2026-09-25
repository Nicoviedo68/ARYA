<?php

declare(strict_types=1);

namespace Arya\Models;

use Arya\Core\Database;

/**
 * Clientes CRM. Se sincronizan automáticamente desde omnicanalidad.
 */
final class Client
{
    private static bool $bootstrapped = false;
    private static bool $schemaReady = false;
    private const SCHEMA_SESSION_KEY = 'schema_ready_clients_v3';

    public static function bootstrap(): void
    {
        if (self::$bootstrapped) {
            return;
        }
        self::$bootstrapped = true;

        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION[self::SCHEMA_SESSION_KEY])) {
            self::$schemaReady = true;
            return;
        }

        $pdo = Database::connection();
        if (!$pdo) {
            return;
        }

        try {
            $ready = false;
            try {
                $pdo->query('SELECT id FROM arya_clients LIMIT 1');
                $ready = true;
            } catch (\Throwable) {
                $ready = false;
            }

            if (!$ready) {
                $pdo->exec(
                    'CREATE TABLE IF NOT EXISTS arya_clients (
                        id                   BIGSERIAL PRIMARY KEY,
                        name                 TEXT NOT NULL,
                        email                TEXT,
                        phone                TEXT,
                        company              TEXT,
                        status               TEXT NOT NULL DEFAULT \'prospecto\',
                        channel              TEXT DEFAULT \'whatsapp\',
                        lifetime_value       NUMERIC(14,2) NOT NULL DEFAULT 0,
                        last_contact_at      TIMESTAMPTZ,
                        tags                 TEXT[] DEFAULT \'{}\',
                        external_contact     VARCHAR(120),
                        omni_conversation_id BIGINT,
                        source               VARCHAR(40) NOT NULL DEFAULT \'manual\',
                        document_type        VARCHAR(20),
                        document_number      VARCHAR(60),
                        address              TEXT,
                        city                 VARCHAR(120),
                        notes                TEXT,
                        created_at           TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                        updated_at           TIMESTAMPTZ NOT NULL DEFAULT NOW()
                    )'
                );
            }

            if (!self::$schemaReady) {
                try {
                    $pdo->exec('ALTER TABLE arya_clients ADD COLUMN IF NOT EXISTS external_contact VARCHAR(120)');
                    $pdo->exec('ALTER TABLE arya_clients ADD COLUMN IF NOT EXISTS omni_conversation_id BIGINT');
                    $pdo->exec('ALTER TABLE arya_clients ADD COLUMN IF NOT EXISTS source VARCHAR(40) NOT NULL DEFAULT \'manual\'');
                    $pdo->exec('ALTER TABLE arya_clients ADD COLUMN IF NOT EXISTS document_type VARCHAR(20)');
                    $pdo->exec('ALTER TABLE arya_clients ADD COLUMN IF NOT EXISTS document_number VARCHAR(60)');
                    $pdo->exec('ALTER TABLE arya_clients ADD COLUMN IF NOT EXISTS address TEXT');
                    $pdo->exec('ALTER TABLE arya_clients ADD COLUMN IF NOT EXISTS city VARCHAR(120)');
                    $pdo->exec('ALTER TABLE arya_clients ADD COLUMN IF NOT EXISTS notes TEXT');
                    $pdo->exec(
                        'CREATE UNIQUE INDEX IF NOT EXISTS uq_arya_clients_channel_external
                         ON arya_clients (channel, external_contact)
                         WHERE external_contact IS NOT NULL'
                    );
                    self::$schemaReady = true;
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        $_SESSION[self::SCHEMA_SESSION_KEY] = 1;
                    }
                    // Una vez por migración: unificar duplicados cmt: → actor
                    try {
                        self::dedupeOmniActors();
                    } catch (\Throwable) {
                        // ignore
                    }
                } catch (\Throwable) {
                    // ignore
                }
            }
        } catch (\Throwable) {
            // sin DB
        }
    }

    /**
     * Sync ligero (throttled). Ideal al listar clientes.
     */
    public static function syncIfNeeded(int $ttlSeconds = 45): int
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $last = (int) ($_SESSION['clients_omni_sync_at'] ?? 0);
            if ($last > 0 && (time() - $last) < $ttlSeconds) {
                return 0;
            }
        }

        $n = self::syncFromOmniFast();
        try {
            $n += self::dedupeOmniActors();
        } catch (\Throwable) {
            // ignore
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['clients_omni_sync_at'] = time();
        }
        return $n;
    }

    /**
     * Clave CRM del actor (PSID / IGSID / teléfono), nunca el id del comentario cmt:…
     */
    public static function resolveCrmExternal(string $external, ?string $phone = null): string
    {
        $external = trim($external);
        $phone = trim((string) $phone);
        if ($external !== '' && str_starts_with(strtolower($external), 'cmt:')) {
            if (
                $phone !== ''
                && !str_starts_with(strtolower($phone), 'cmt:')
                && $phone !== $external
                && (preg_match('/^\d{5,}$/', $phone) === 1 || preg_match('/^[A-Za-z0-9._-]{5,}$/', $phone) === 1)
            ) {
                return $phone;
            }
        }
        return $external;
    }

    /**
     * Upsert cliente desde una conversación omnicanal (no pisa datos CRM editados).
     *
     * @param array{channel:string,external_contact:string,contact_name?:?string,phone?:?string,omni_conversation_id?:?int,preview?:?string,at?:?string,status?:?string} $data
     */
    public static function upsertFromOmni(array $data): ?int
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return null;
        }

        $channel = self::normalizeChannel((string) ($data['channel'] ?? ''));
        $rawExternal = trim((string) ($data['external_contact'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        $external = self::resolveCrmExternal($rawExternal, $phone);
        if ($channel === '' || $external === '') {
            return null;
        }

        $name = trim((string) ($data['contact_name'] ?? ''));
        if ($name === '' || str_starts_with(strtolower($name), 'cmt:')) {
            $name = $external;
        }

        if ($phone === '' || str_starts_with(strtolower($phone), 'cmt:')) {
            $phone = $channel === 'whatsapp' ? ('+' . ltrim($external, '+')) : $external;
        }

        $company = self::channelCompanyLabel($channel);
        $status = self::mapOmniStatus((string) ($data['status'] ?? 'open'));
        $at = trim((string) ($data['at'] ?? ''));
        if ($at === '') {
            $at = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('c');
        }
        $omniId = isset($data['omni_conversation_id']) ? (int) $data['omni_conversation_id'] : null;
        $tags = '{' . self::channelTag($channel) . ',Omnicanal}';

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO arya_clients
                    (name, email, phone, company, status, channel, lifetime_value, last_contact_at,
                     tags, external_contact, omni_conversation_id, source, created_at, updated_at)
                 VALUES
                    (:name, NULL, :phone, :company, :status, :channel, 0,
                     CAST(:at AS TIMESTAMPTZ),
                     CAST(:tags AS TEXT[]), :external_contact, :omni_id, \'omni\', NOW(), NOW())
                 ON CONFLICT (channel, external_contact) WHERE external_contact IS NOT NULL
                 DO UPDATE SET
                    name = CASE
                        WHEN arya_clients.name IS NULL OR TRIM(arya_clients.name) = \'\'
                          OR arya_clients.name = arya_clients.external_contact
                          OR arya_clients.name LIKE \'Usuario %\'
                          OR arya_clients.name LIKE \'Comentario %\'
                          OR (arya_clients.name LIKE \'@%\' AND EXCLUDED.name NOT LIKE \'@%\' AND EXCLUDED.name NOT LIKE \'cmt:%\')
                        THEN COALESCE(NULLIF(EXCLUDED.name, \'\'), arya_clients.name)
                        ELSE arya_clients.name
                    END,
                    phone = COALESCE(NULLIF(arya_clients.phone, \'\'), EXCLUDED.phone),
                    company = CASE
                        WHEN arya_clients.company IS NULL OR arya_clients.company = \'\'
                          OR arya_clients.company LIKE \'Omnicanal · %\'
                        THEN EXCLUDED.company
                        ELSE arya_clients.company
                    END,
                    omni_conversation_id = COALESCE(EXCLUDED.omni_conversation_id, arya_clients.omni_conversation_id),
                    last_contact_at = GREATEST(arya_clients.last_contact_at, EXCLUDED.last_contact_at),
                    status = CASE
                        WHEN LOWER(COALESCE(EXCLUDED.status, \'\')) = \'activo\' THEN \'activo\'
                        ELSE arya_clients.status
                    END,
                    source = COALESCE(arya_clients.source, \'omni\'),
                    updated_at = NOW()
                 RETURNING id'
            );
            $stmt->execute([
                'name'             => $name,
                'phone'            => $phone,
                'company'          => $company,
                'status'           => $status,
                'channel'          => $channel,
                'at'               => $at,
                'tags'             => $tags,
                'external_contact' => $external,
                'omni_id'          => $omniId,
            ]);

            $id = $stmt->fetchColumn();
            return $id !== false ? (int) $id : null;
        } catch (\Throwable) {
            return self::upsertFromOmniFallback($pdo, $channel, $external, $name, $phone, $company, $status, $at, $omniId, $tags);
        }
    }

    /** Sync en 1-2 queries (rápido). */
    public static function syncFromOmniFast(): int
    {
        self::bootstrap();
        Omnichannel::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return 0;
        }

        $inserted = 0;
        try {
            // Actor CRM: PSID/IGSID/tel (no cmt:commentId)
            $sqlInsert = <<<'SQL'
INSERT INTO arya_clients (
    name, phone, company, status, channel, lifetime_value, last_contact_at,
    tags, external_contact, omni_conversation_id, source
)
SELECT DISTINCT ON (actor_channel, actor_external)
    COALESCE(NULLIF(TRIM(c.contact_name), ''), actor_external),
    COALESCE(
        NULLIF(TRIM(c.phone), ''),
        CASE WHEN actor_channel = 'whatsapp'
             THEN '+' || LTRIM(actor_external, '+')
             ELSE actor_external
        END
    ),
    'Omnicanal · ' || CASE actor_channel
        WHEN 'whatsapp' THEN 'WhatsApp'
        WHEN 'messenger' THEN 'Messenger'
        WHEN 'instagram' THEN 'Instagram'
        WHEN 'web' THEN 'Web Chat'
        ELSE INITCAP(actor_channel)
    END,
    CASE LOWER(COALESCE(c.status, 'open'))
        WHEN 'open' THEN 'activo'
        WHEN 'abierto' THEN 'activo'
        WHEN 'activo' THEN 'activo'
        WHEN 'closed' THEN 'inactivo'
        WHEN 'cerrado' THEN 'inactivo'
        WHEN 'resolved' THEN 'inactivo'
        ELSE 'prospecto'
    END,
    actor_channel,
    0,
    COALESCE(c.last_message_at, NOW()),
    ARRAY[
        CASE actor_channel
            WHEN 'whatsapp' THEN 'WhatsApp'
            WHEN 'messenger' THEN 'Messenger'
            WHEN 'instagram' THEN 'Instagram'
            WHEN 'web' THEN 'Web'
            ELSE 'Canal'
        END,
        'Omnicanal'
    ]::TEXT[],
    actor_external,
    c.id,
    'omni'
FROM (
    SELECT
        c.*,
        CASE LOWER(c.channel)
            WHEN 'facebook' THEN 'messenger'
            WHEN 'fb' THEN 'messenger'
            WHEN 'ig' THEN 'instagram'
            WHEN 'wa' THEN 'whatsapp'
            WHEN 'webchat' THEN 'web'
            ELSE LOWER(c.channel)
        END AS actor_channel,
        CASE
            WHEN c.external_contact LIKE 'cmt:%'
             AND NULLIF(TRIM(COALESCE(c.phone, '')), '') IS NOT NULL
             AND TRIM(c.phone) NOT LIKE 'cmt:%'
             AND TRIM(c.phone) <> TRIM(c.external_contact)
            THEN TRIM(c.phone)
            ELSE TRIM(c.external_contact)
        END AS actor_external
    FROM omni_conversations c
) c
WHERE NULLIF(actor_external, '') IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM arya_clients a
    WHERE a.external_contact = c.actor_external
      AND a.channel = c.actor_channel
)
ORDER BY actor_channel, actor_external, c.last_message_at DESC NULLS LAST, c.id DESC
SQL;
            $inserted = (int) $pdo->exec($sqlInsert);
            if ($inserted < 0) {
                $inserted = 0;
            }
        } catch (\Throwable) {
            // fallback al método fila a fila
            return self::syncFromOmni();
        }

        try {
            $pdo->exec(
                'UPDATE arya_clients a
                 SET omni_conversation_id = x.id,
                     last_contact_at = GREATEST(a.last_contact_at, x.last_message_at),
                     phone = COALESCE(NULLIF(a.phone, \'\'), x.phone),
                     updated_at = NOW()
                 FROM (
                    SELECT DISTINCT ON (actor_channel, actor_external)
                        c.id,
                        c.phone,
                        c.last_message_at,
                        CASE LOWER(c.channel)
                            WHEN \'facebook\' THEN \'messenger\'
                            WHEN \'fb\' THEN \'messenger\'
                            WHEN \'ig\' THEN \'instagram\'
                            WHEN \'wa\' THEN \'whatsapp\'
                            WHEN \'webchat\' THEN \'web\'
                            ELSE LOWER(c.channel)
                        END AS actor_channel,
                        CASE
                            WHEN c.external_contact LIKE \'cmt:%\'
                             AND NULLIF(TRIM(COALESCE(c.phone, \'\')), \'\') IS NOT NULL
                             AND TRIM(c.phone) NOT LIKE \'cmt:%\'
                             AND TRIM(c.phone) <> TRIM(c.external_contact)
                            THEN TRIM(c.phone)
                            ELSE TRIM(c.external_contact)
                        END AS actor_external
                    FROM omni_conversations c
                    ORDER BY actor_channel, actor_external, c.last_message_at DESC NULLS LAST, c.id DESC
                 ) x
                 WHERE a.external_contact = x.actor_external
                   AND a.channel = x.actor_channel'
            );
        } catch (\Throwable) {
            // ignore soft update errors
        }

        return $inserted;
    }

    /**
     * Fusiona filas CRM duplicadas creadas por comentarios (cmt:…) hacia el actor real.
     */
    public static function dedupeOmniActors(): int
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return 0;
        }

        $merged = 0;
        try {
            $stmt = $pdo->query(
                "SELECT id, channel, external_contact, phone, name, omni_conversation_id, last_contact_at
                 FROM arya_clients
                 WHERE external_contact LIKE 'cmt:%'
                 ORDER BY id ASC"
            );
            $rows = $stmt ? $stmt->fetchAll() : [];
            foreach ($rows as $row) {
                $dropId = (int) ($row['id'] ?? 0);
                if ($dropId < 1) {
                    continue;
                }
                $channel = self::normalizeChannel((string) ($row['channel'] ?? ''));
                $actor = self::resolveCrmExternal(
                    (string) ($row['external_contact'] ?? ''),
                    (string) ($row['phone'] ?? '')
                );
                if ($actor === '' || str_starts_with(strtolower($actor), 'cmt:')) {
                    continue;
                }

                $find = $pdo->prepare(
                    'SELECT id FROM arya_clients
                     WHERE channel = :ch AND external_contact = :ext AND id <> :id
                     LIMIT 1'
                );
                $find->execute(['ch' => $channel, 'ext' => $actor, 'id' => $dropId]);
                $keepId = (int) ($find->fetchColumn() ?: 0);

                if ($keepId < 1) {
                    try {
                        $ren = $pdo->prepare(
                            'UPDATE arya_clients
                             SET external_contact = :ext,
                                 phone = COALESCE(NULLIF(phone, \'\'), :phone),
                                 updated_at = NOW()
                             WHERE id = :id'
                        );
                        $ren->execute([
                            'ext'   => $actor,
                            'phone' => $actor,
                            'id'    => $dropId,
                        ]);
                        continue;
                    } catch (\Throwable) {
                        $find->execute(['ch' => $channel, 'ext' => $actor, 'id' => $dropId]);
                        $keepId = (int) ($find->fetchColumn() ?: 0);
                        if ($keepId < 1) {
                            continue;
                        }
                    }
                }

                if (self::mergeClientInto($pdo, $keepId, $dropId, $row)) {
                    $merged++;
                }
            }

            // Segundo pase: mismo canal + mismo phone (actor) con externos distintos
            $dupes = $pdo->query(
                "SELECT channel, phone, array_agg(id ORDER BY
                    CASE WHEN external_contact LIKE 'cmt:%' THEN 1 ELSE 0 END,
                    id ASC
                 ) AS ids
                 FROM arya_clients
                 WHERE NULLIF(TRIM(phone), '') IS NOT NULL
                   AND phone NOT LIKE 'cmt:%'
                 GROUP BY channel, phone
                 HAVING COUNT(*) > 1"
            );
            $groups = $dupes ? $dupes->fetchAll() : [];
            foreach ($groups as $g) {
                $idsRaw = $g['ids'] ?? null;
                $ids = [];
                if (is_string($idsRaw)) {
                    $ids = array_values(array_filter(array_map('intval', explode(',', trim($idsRaw, '{}')))));
                } elseif (is_array($idsRaw)) {
                    $ids = array_values(array_map('intval', $idsRaw));
                }
                if (count($ids) < 2) {
                    continue;
                }
                $keepId = $ids[0];
                foreach (array_slice($ids, 1) as $dropId) {
                    if (self::mergeClientInto($pdo, $keepId, $dropId)) {
                        $merged++;
                    }
                }
            }
        } catch (\Throwable) {
            return $merged;
        }

        return $merged;
    }

    /**
     * @param array<string,mixed>|null $dropRow
     */
    private static function mergeClientInto(\PDO $pdo, int $keepId, int $dropId, ?array $dropRow = null): bool
    {
        if ($keepId < 1 || $dropId < 1 || $keepId === $dropId) {
            return false;
        }
        try {
            if ($dropRow === null) {
                $s = $pdo->prepare(
                    'SELECT id, name, phone, company, omni_conversation_id, last_contact_at, status
                     FROM arya_clients WHERE id = :id LIMIT 1'
                );
                $s->execute(['id' => $dropId]);
                $dropRow = $s->fetch() ?: null;
            }
            if (!$dropRow) {
                return false;
            }

            $upd = $pdo->prepare(
                'UPDATE arya_clients AS k SET
                    name = CASE
                        WHEN k.name IS NULL OR TRIM(k.name) = \'\' OR k.name = k.external_contact
                          OR k.name LIKE \'Usuario %\' OR k.name LIKE \'Comentario %\'
                          OR (k.name LIKE \'@%\' AND :dname NOT LIKE \'@%\')
                        THEN COALESCE(NULLIF(:dname, \'\'), k.name)
                        ELSE k.name
                    END,
                    phone = COALESCE(NULLIF(k.phone, \'\'), NULLIF(:dphone, \'\')),
                    company = CASE
                        WHEN k.company IS NULL OR k.company = \'\' OR k.company LIKE \'Omnicanal · %\'
                        THEN COALESCE(NULLIF(:dcompany, \'\'), k.company)
                        ELSE k.company
                    END,
                    omni_conversation_id = COALESCE(k.omni_conversation_id, :domni),
                    last_contact_at = GREATEST(
                        k.last_contact_at,
                        COALESCE(CAST(NULLIF(:dlast, \'\') AS TIMESTAMPTZ), k.last_contact_at)
                    ),
                    status = CASE
                        WHEN LOWER(COALESCE(k.status, \'\')) = \'inactivo\'
                         AND LOWER(COALESCE(:dstatus, \'\')) = \'activo\'
                        THEN \'activo\'
                        ELSE k.status
                    END,
                    updated_at = NOW()
                 WHERE k.id = :kid'
            );
            $upd->execute([
                'kid'      => $keepId,
                'dname'    => (string) ($dropRow['name'] ?? ''),
                'dphone'   => (string) ($dropRow['phone'] ?? ''),
                'dcompany' => (string) ($dropRow['company'] ?? ''),
                'domni'    => isset($dropRow['omni_conversation_id']) && $dropRow['omni_conversation_id'] !== null
                    ? (int) $dropRow['omni_conversation_id']
                    : null,
                'dlast'    => trim((string) ($dropRow['last_contact_at'] ?? '')),
                'dstatus'  => (string) ($dropRow['status'] ?? ''),
            ]);

            $del = $pdo->prepare('DELETE FROM arya_clients WHERE id = :id');
            $del->execute(['id' => $dropId]);
            return $del->rowCount() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Cuántas veces escribió el cliente (inbound) por día — para ficha Ver cliente.
     *
     * @return array{today:int,days7:int,days30:int,by_day:list<array{date:string,count:int,label:string}>}
     */
    public static function inboundActivityByDay(int $clientId, int $days = 14): array
    {
        $empty = ['today' => 0, 'days7' => 0, 'days30' => 0, 'by_day' => []];
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo || $clientId < 1) {
            return $empty;
        }

        $client = self::find($clientId);
        if (!$client) {
            return $empty;
        }

        $channel = self::normalizeChannel((string) ($client['channel'] ?? ''));
        $ext = trim((string) ($client['external_contact'] ?? ''));
        $phone = trim((string) ($client['phone'] ?? ''));
        if ($channel === '' || ($ext === '' && $phone === '')) {
            return $empty;
        }

        $days = max(7, min(60, $days));
        try {
            Omnichannel::bootstrap();
            $stmt = $pdo->prepare(
                "SELECT
                    TO_CHAR((m.created_at AT TIME ZONE 'UTC') AT TIME ZONE 'America/Bogota', 'YYYY-MM-DD') AS day,
                    COUNT(*)::int AS writes
                 FROM omni_messages m
                 INNER JOIN omni_conversations c ON c.id = m.conversation_id
                 WHERE (m.direction = 'inbound' OR m.sender = 'client')
                   AND LOWER(c.channel) = :channel
                   AND (
                        c.external_contact = :ext
                     OR c.phone = :ext
                     OR (:phone <> '' AND (c.phone = :phone OR c.external_contact = :phone))
                   )
                   AND m.created_at >= (NOW() AT TIME ZONE 'UTC') - (:days || ' days')::interval
                 GROUP BY 1
                 ORDER BY 1 DESC"
            );
            $stmt->execute([
                'channel' => $channel,
                'ext'     => $ext,
                'phone'   => $phone,
                'days'    => (string) $days,
            ]);
            $rows = $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return $empty;
        }

        $tz = new \DateTimeZone('America/Bogota');
        $today = (new \DateTimeImmutable('now', $tz))->format('Y-m-d');
        $map = [];
        foreach ($rows as $r) {
            $d = (string) ($r['day'] ?? '');
            $map[$d] = (int) ($r['writes'] ?? 0);
        }

        $byDay = [];
        $days7 = 0;
        $days30 = 0;
        $todayCount = (int) ($map[$today] ?? 0);
        for ($i = 0; $i < $days; $i++) {
            $d = (new \DateTimeImmutable('now', $tz))->modify('-' . $i . ' days')->format('Y-m-d');
            $n = (int) ($map[$d] ?? 0);
            if ($i < 7) {
                $days7 += $n;
            }
            if ($i < 30) {
                $days30 += $n;
            }
            try {
                $label = (new \DateTimeImmutable($d, $tz))->format('d/m');
            } catch (\Throwable) {
                $label = $d;
            }
            $byDay[] = [
                'date'  => $d,
                'count' => $n,
                'label' => $label,
            ];
        }

        return [
            'today'  => $todayCount,
            'days7'  => $days7,
            'days30' => $days30,
            'by_day' => $byDay,
        ];
    }

    /** @deprecated usar syncFromOmniFast */
    public static function syncFromOmni(): int
    {
        self::bootstrap();
        Omnichannel::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return 0;
        }

        try {
            $rows = $pdo->query(
                'SELECT id, channel, external_contact, contact_name, phone, status, last_message_at
                 FROM omni_conversations
                 ORDER BY id DESC
                 LIMIT 200'
            )->fetchAll();
        } catch (\Throwable) {
            return 0;
        }

        $n = 0;
        foreach ($rows as $row) {
            if (self::upsertFromOmni([
                'channel'              => (string) $row['channel'],
                'external_contact'     => (string) $row['external_contact'],
                'contact_name'         => $row['contact_name'] !== null ? (string) $row['contact_name'] : null,
                'phone'                => $row['phone'] !== null ? (string) $row['phone'] : null,
                'omni_conversation_id' => (int) $row['id'],
                'status'               => (string) ($row['status'] ?? 'open'),
                'at'                   => (string) ($row['last_message_at'] ?? ''),
            ])) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * Actualiza datos CRM editables (no toca canal/external omni).
     *
     * @param array<string,mixed> $data
     * @return array{ok:bool,message:string}
     */
    public static function update(int $id, array $data): array
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return ['ok' => false, 'message' => 'Sin conexión a base de datos.'];
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'message' => 'El nombre es obligatorio.'];
        }

        $email = trim((string) ($data['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'El correo no es válido.'];
        }

        $status = strtolower(trim((string) ($data['status'] ?? 'prospecto')));
        if (!in_array($status, ['activo', 'prospecto', 'inactivo'], true)) {
            $status = 'prospecto';
        }

        $docType = strtoupper(trim((string) ($data['document_type'] ?? '')));
        if ($docType !== '' && !in_array($docType, ['CC', 'NIT', 'CE', 'PAS', 'PPT', 'OTRO'], true)) {
            $docType = 'OTRO';
        }

        try {
            $stmt = $pdo->prepare(
                'UPDATE arya_clients SET
                    name = :name,
                    email = NULLIF(:email, \'\'),
                    phone = NULLIF(:phone, \'\'),
                    company = NULLIF(:company, \'\'),
                    status = :status,
                    document_type = NULLIF(:document_type, \'\'),
                    document_number = NULLIF(:document_number, \'\'),
                    address = NULLIF(:address, \'\'),
                    city = NULLIF(:city, \'\'),
                    notes = NULLIF(:notes, \'\'),
                    updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute([
                'id'              => $id,
                'name'            => $name,
                'email'           => $email,
                'phone'           => trim((string) ($data['phone'] ?? '')),
                'company'         => trim((string) ($data['company'] ?? '')),
                'status'          => $status,
                'document_type'   => $docType,
                'document_number' => trim((string) ($data['document_number'] ?? '')),
                'address'         => trim((string) ($data['address'] ?? '')),
                'city'            => trim((string) ($data['city'] ?? '')),
                'notes'           => trim((string) ($data['notes'] ?? '')),
            ]);

            if ($stmt->rowCount() < 1) {
                // puede ser 0 si valores idénticos
                $exists = $pdo->prepare('SELECT 1 FROM arya_clients WHERE id = :id');
                $exists->execute(['id' => $id]);
                if (!$exists->fetchColumn()) {
                    return ['ok' => false, 'message' => 'Cliente no encontrado.'];
                }
            }

            return ['ok' => true, 'message' => 'Cliente actualizado.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo guardar: ' . $e->getMessage()];
        }
    }

    /**
     * Filtros combinables (AND): name, phone, channel. `q` = búsqueda general opcional.
     *
     * @param array{name?:string,phone?:string,channel?:string,q?:string} $filters
     * @return list<array<string,mixed>>
     */
    public static function search(array $filters = []): array
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return [];
        }

        $name = trim((string) ($filters['name'] ?? ''));
        $phone = trim((string) ($filters['phone'] ?? ''));
        $channelRaw = trim((string) ($filters['channel'] ?? ''));
        $channel = ($channelRaw !== '' && $channelRaw !== 'all')
            ? self::normalizeChannel($channelRaw)
            : '';
        $q = trim((string) ($filters['q'] ?? ''));

        $where = [];
        $params = [];

        if ($name !== '') {
            $where[] = 'name ILIKE :name';
            $params['name'] = '%' . $name . '%';
        }

        if ($phone !== '') {
            $digits = preg_replace('/\D+/', '', $phone) ?? '';
            $where[] = '(
                COALESCE(phone, \'\') ILIKE :phone
                OR COALESCE(external_contact, \'\') ILIKE :phone
                OR regexp_replace(COALESCE(phone, \'\'), \'\\D\', \'\', \'g\') LIKE :phone_digits
                OR regexp_replace(COALESCE(external_contact, \'\'), \'\\D\', \'\', \'g\') LIKE :phone_digits
            )';
            $params['phone'] = '%' . $phone . '%';
            $params['phone_digits'] = '%' . $digits . '%';
        }

        if ($channel !== '') {
            $where[] = 'channel = :channel';
            $params['channel'] = $channel;
        }

        if ($q !== '') {
            $where[] = '(
                name ILIKE :q
                OR COALESCE(email, \'\') ILIKE :q
                OR COALESCE(phone, \'\') ILIKE :q
                OR COALESCE(company, \'\') ILIKE :q
                OR COALESCE(external_contact, \'\') ILIKE :q
                OR COALESCE(channel, \'\') ILIKE :q
                OR COALESCE(document_number, \'\') ILIKE :q
                OR COALESCE(city, \'\') ILIKE :q
            )';
            $params['q'] = '%' . $q . '%';
        }

        $sql = 'SELECT * FROM arya_clients';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY COALESCE(last_contact_at, updated_at, created_at) DESC, id DESC LIMIT 200';

        try {
            if ($params) {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll();
            } else {
                $rows = $pdo->query($sql)->fetchAll();
            }
            return array_map([self::class, 'mapRow'], $rows ?: []);
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<array<string,mixed>> */
    public static function all(?string $q = null): array
    {
        return self::search($q !== null && $q !== '' ? ['q' => $q] : []);
    }

    /**
     * Alta manual de cliente (agente).
     *
     * @param array<string,mixed> $data
     * @return array{ok:bool,message:string,id?:int}
     */
    public static function create(array $data): array
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return ['ok' => false, 'message' => 'Sin conexión a base de datos.'];
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'message' => 'El nombre es obligatorio.'];
        }

        $email = trim((string) ($data['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'El correo no es válido.'];
        }

        $phone = trim((string) ($data['phone'] ?? ''));
        $channel = self::normalizeChannel((string) ($data['channel'] ?? 'whatsapp'));
        $status = strtolower(trim((string) ($data['status'] ?? 'prospecto')));
        if (!in_array($status, ['activo', 'prospecto', 'inactivo'], true)) {
            $status = 'prospecto';
        }

        $company = trim((string) ($data['company'] ?? ''));
        if ($company === '') {
            $company = 'Manual · ' . self::channelLabel($channel);
        }

        $external = $phone !== '' ? preg_replace('/\D+/', '', $phone) : null;
        if ($external === '') {
            $external = null;
        }

        $tags = '{' . self::channelTag($channel) . ',Manual}';

        try {
            // Evitar duplicado obvio por teléfono+canal
            if ($phone !== '') {
                $dup = $pdo->prepare(
                    'SELECT id FROM arya_clients
                     WHERE channel = :channel
                       AND (
                            phone = :phone
                         OR regexp_replace(COALESCE(phone, \'\'), \'\\D\', \'\', \'g\') = :digits
                       )
                     LIMIT 1'
                );
                $dup->execute([
                    'channel' => $channel,
                    'phone'   => $phone,
                    'digits'  => preg_replace('/\D+/', '', $phone) ?? '',
                ]);
                $existingId = $dup->fetchColumn();
                if ($existingId) {
                    return [
                        'ok'      => false,
                        'message' => 'Ya existe un cliente con ese teléfono en ' . self::channelLabel($channel) . '.',
                        'id'      => (int) $existingId,
                    ];
                }
            }

            $stmt = $pdo->prepare(
                'INSERT INTO arya_clients
                    (name, email, phone, company, status, channel, lifetime_value, last_contact_at,
                     tags, external_contact, source, document_type, document_number, address, city, notes)
                 VALUES
                    (:name, NULLIF(:email, \'\'), NULLIF(:phone, \'\'), :company, :status, :channel, 0, NOW(),
                     CAST(:tags AS TEXT[]), :external, \'manual\',
                     NULLIF(:document_type, \'\'), NULLIF(:document_number, \'\'),
                     NULLIF(:address, \'\'), NULLIF(:city, \'\'), NULLIF(:notes, \'\'))
                 RETURNING id'
            );
            $docType = strtoupper(trim((string) ($data['document_type'] ?? '')));
            if ($docType !== '' && !in_array($docType, ['CC', 'NIT', 'CE', 'PAS', 'PPT', 'OTRO'], true)) {
                $docType = 'OTRO';
            }
            $stmt->execute([
                'name'            => $name,
                'email'           => $email,
                'phone'           => $phone,
                'company'         => $company,
                'status'          => $status,
                'channel'         => $channel,
                'tags'            => $tags,
                'external'        => $external,
                'document_type'   => $docType,
                'document_number' => trim((string) ($data['document_number'] ?? '')),
                'address'         => trim((string) ($data['address'] ?? '')),
                'city'            => trim((string) ($data['city'] ?? '')),
                'notes'           => trim((string) ($data['notes'] ?? '')),
            ]);

            $id = (int) $stmt->fetchColumn();
            return ['ok' => true, 'message' => 'Cliente creado.', 'id' => $id];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo crear: ' . $e->getMessage()];
        }
    }

    public static function find(int|string $id): ?array
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return null;
        }

        try {
            $stmt = $pdo->prepare('SELECT * FROM arya_clients WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();
            return $row ? self::mapRow($row) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function mapRow(array $row): array
    {
        $tags = $row['tags'] ?? [];
        if (is_string($tags)) {
            $tags = trim($tags, '{}');
            $tags = $tags === '' ? [] : array_map(
                static fn (string $t): string => trim($t, '"'),
                explode(',', $tags)
            );
        }
        if (!is_array($tags)) {
            $tags = [];
        }

        $channel = self::normalizeChannel((string) ($row['channel'] ?? 'whatsapp'));

        return [
            'id'                   => (int) $row['id'],
            'name'                 => (string) $row['name'],
            'email'                => (string) ($row['email'] ?? ''),
            'phone'                => (string) ($row['phone'] ?? ''),
            'company'              => (string) ($row['company'] ?? self::channelCompanyLabel($channel)),
            'status'               => (string) ($row['status'] ?? 'prospecto'),
            'channel'              => $channel,
            'channel_label'        => self::channelLabel($channel),
            'orders'               => 0,
            'lifetime'             => (int) round((float) ($row['lifetime_value'] ?? 0)),
            'last_contact'         => self::formatContactAt((string) ($row['last_contact_at'] ?? '')),
            'tags'                 => array_values($tags),
            'external_contact'     => (string) ($row['external_contact'] ?? ''),
            'omni_conversation_id' => isset($row['omni_conversation_id']) && $row['omni_conversation_id'] !== null
                ? (int) $row['omni_conversation_id'] : null,
            'source'               => (string) ($row['source'] ?? 'manual'),
            'document_type'        => (string) ($row['document_type'] ?? ''),
            'document_number'      => (string) ($row['document_number'] ?? ''),
            'address'              => (string) ($row['address'] ?? ''),
            'city'                 => (string) ($row['city'] ?? ''),
            'notes'                => (string) ($row['notes'] ?? ''),
        ];
    }

    private static function normalizeChannel(string $channel): string
    {
        $channel = strtolower(trim($channel));
        return match ($channel) {
            'facebook', 'fb', 'messenger' => 'messenger',
            'ig', 'instagram' => 'instagram',
            'wa', 'whatsapp' => 'whatsapp',
            'web', 'webchat', 'chat' => 'web',
            default => $channel !== '' ? $channel : 'whatsapp',
        };
    }

    public static function channelLabel(string $channel): string
    {
        return match (self::normalizeChannel($channel)) {
            'whatsapp' => 'WhatsApp',
            'messenger' => 'Messenger',
            'instagram' => 'Instagram',
            'web' => 'Web Chat',
            default => ucfirst($channel),
        };
    }

    private static function channelCompanyLabel(string $channel): string
    {
        return 'Omnicanal · ' . self::channelLabel($channel);
    }

    private static function channelTag(string $channel): string
    {
        return match (self::normalizeChannel($channel)) {
            'whatsapp' => 'WhatsApp',
            'messenger' => 'Messenger',
            'instagram' => 'Instagram',
            'web' => 'Web',
            default => 'Canal',
        };
    }

    private static function mapOmniStatus(string $status): string
    {
        return match (strtolower($status)) {
            'open', 'abierto', 'activo' => 'activo',
            'closed', 'cerrado', 'resolved' => 'inactivo',
            default => 'prospecto',
        };
    }

    private static function formatContactAt(string $ts): string
    {
        if ($ts === '') {
            return '—';
        }
        try {
            $dt = new \DateTimeImmutable($ts);
            $dt = $dt->setTimezone(new \DateTimeZone('America/Bogota'));
            return $dt->format('Y-m-d H:i');
        } catch (\Throwable) {
            return $ts;
        }
    }

    private static function upsertFromOmniFallback(
        \PDO $pdo,
        string $channel,
        string $external,
        string $name,
        string $phone,
        string $company,
        string $status,
        string $at,
        ?int $omniId,
        string $tags
    ): ?int {
        try {
            $find = $pdo->prepare(
                'SELECT id, name, company, phone FROM arya_clients
                 WHERE channel = :channel AND external_contact = :external
                 LIMIT 1'
            );
            $find->execute(['channel' => $channel, 'external' => $external]);
            $existing = $find->fetch();

            if ($existing) {
                $keepName = (string) ($existing['name'] ?? '');
                $keepCompany = (string) ($existing['company'] ?? '');
                $keepPhone = (string) ($existing['phone'] ?? '');
                $newName = ($keepName === '' || $keepName === $external) ? $name : $keepName;
                $newCompany = ($keepCompany === '' || str_starts_with($keepCompany, 'Omnicanal · ')) ? $company : $keepCompany;
                $newPhone = $keepPhone !== '' ? $keepPhone : $phone;

                $upd = $pdo->prepare(
                    'UPDATE arya_clients SET
                        name = :name,
                        phone = :phone,
                        company = :company,
                        omni_conversation_id = COALESCE(:omni_id, omni_conversation_id),
                        last_contact_at = CAST(:at AS TIMESTAMPTZ),
                        updated_at = NOW()
                     WHERE id = :id'
                );
                $upd->execute([
                    'name' => $newName,
                    'phone' => $newPhone,
                    'company' => $newCompany,
                    'omni_id' => $omniId,
                    'at' => $at,
                    'id' => $existing['id'],
                ]);
                return (int) $existing['id'];
            }

            $ins = $pdo->prepare(
                'INSERT INTO arya_clients
                    (name, phone, company, status, channel, lifetime_value, last_contact_at,
                     tags, external_contact, omni_conversation_id, source)
                 VALUES
                    (:name, :phone, :company, :status, :channel, 0,
                     CAST(:at AS TIMESTAMPTZ),
                     CAST(:tags AS TEXT[]), :external, :omni_id, \'omni\')
                 RETURNING id'
            );
            $ins->execute([
                'name' => $name,
                'phone' => $phone,
                'company' => $company,
                'status' => $status,
                'channel' => $channel,
                'at' => $at,
                'tags' => $tags,
                'external' => $external,
                'omni_id' => $omniId,
            ]);
            return (int) $ins->fetchColumn();
        } catch (\Throwable) {
            return null;
        }
    }
}
