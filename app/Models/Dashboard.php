<?php

declare(strict_types=1);

namespace Arya\Models;

use Arya\Core\Database;

/**
 * Métricas del dashboard desde PostgreSQL (omni + clientes + tareas).
 */
final class Dashboard
{
    private const TZ = 'America/Bogota';

    /**
     * @return array{
     *   conversations_today:int,
     *   conversations_yesterday:int,
     *   conversations_delta_pct:?float,
     *   open_chats:int,
     *   unread_total:int,
     *   clients_active:int,
     *   clients_new_week:int,
     *   conversion_rate:float,
     *   avg_response_min:?float,
     *   messages_today:int
     * }
     */
    public static function stats(): array
    {
        Omnichannel::bootstrap();
        Client::bootstrap();
        $pdo = Database::connection();

        $empty = [
            'conversations_today'     => 0,
            'conversations_yesterday' => 0,
            'conversations_delta_pct' => null,
            'open_chats'              => 0,
            'unread_total'            => 0,
            'clients_active'          => 0,
            'clients_new_week'        => 0,
            'conversion_rate'         => 0.0,
            'avg_response_min'        => null,
            'messages_today'          => 0,
            'comments_total'          => 0,
            'inbound_today'           => 0,
            'outbound_today'          => 0,
        ];

        if (!$pdo) {
            return $empty;
        }

        try {
            $today = (int) $pdo->query(
                "SELECT COUNT(*) FROM omni_conversations
                 WHERE last_message_at >= (date_trunc('day', NOW() AT TIME ZONE '" . self::TZ . "') AT TIME ZONE '" . self::TZ . "')"
            )->fetchColumn();

            $yesterday = (int) $pdo->query(
                "SELECT COUNT(*) FROM omni_conversations
                 WHERE last_message_at >= (date_trunc('day', NOW() AT TIME ZONE '" . self::TZ . "') AT TIME ZONE '" . self::TZ . "' - INTERVAL '1 day')
                   AND last_message_at <  (date_trunc('day', NOW() AT TIME ZONE '" . self::TZ . "') AT TIME ZONE '" . self::TZ . "')"
            )->fetchColumn();

            $delta = null;
            if ($yesterday > 0) {
                $delta = round((($today - $yesterday) / $yesterday) * 100, 1);
            } elseif ($today > 0) {
                $delta = 100.0;
            }

            $open = (int) $pdo->query(
                "SELECT COUNT(*) FROM omni_conversations
                 WHERE LOWER(COALESCE(status, 'open')) IN ('open', 'pending', 'abierto')"
            )->fetchColumn();

            $unread = (int) $pdo->query(
                'SELECT COALESCE(SUM(unread_count), 0) FROM omni_conversations'
            )->fetchColumn();

            $clientsActive = (int) $pdo->query(
                "SELECT COUNT(*) FROM arya_clients
                 WHERE LOWER(COALESCE(status, '')) IN ('activo', 'active', 'open')"
            )->fetchColumn();

            $clientsWeek = (int) $pdo->query(
                "SELECT COUNT(*) FROM arya_clients
                 WHERE created_at >= NOW() - INTERVAL '7 days'"
            )->fetchColumn();

            $msgsToday = (int) $pdo->query(
                "SELECT COUNT(*) FROM omni_messages
                 WHERE created_at >= (date_trunc('day', NOW() AT TIME ZONE '" . self::TZ . "') AT TIME ZONE '" . self::TZ . "')"
            )->fetchColumn();

            $totalConvs = (int) $pdo->query('SELECT COUNT(*) FROM omni_conversations')->fetchColumn();
            $answered = (int) $pdo->query(
                "SELECT COUNT(DISTINCT conversation_id) FROM omni_messages
                 WHERE LOWER(COALESCE(sender, '')) IN ('agent', 'bot', 'system')
                    OR LOWER(COALESCE(direction, '')) = 'outbound'"
            )->fetchColumn();
            $conversion = $totalConvs > 0
                ? round(($answered / $totalConvs) * 100, 1)
                : 0.0;

            $avgResp = null;
            try {
                $avgRaw = $pdo->query(
                    "WITH first_in AS (
                        SELECT conversation_id, MIN(created_at) AS t
                        FROM omni_messages
                        WHERE LOWER(COALESCE(sender, '')) = 'client'
                           OR LOWER(COALESCE(direction, '')) = 'inbound'
                        GROUP BY conversation_id
                     ),
                     first_out AS (
                        SELECT conversation_id, MIN(created_at) AS t
                        FROM omni_messages
                        WHERE LOWER(COALESCE(sender, '')) IN ('agent', 'bot')
                           OR LOWER(COALESCE(direction, '')) = 'outbound'
                        GROUP BY conversation_id
                     )
                     SELECT AVG(EXTRACT(EPOCH FROM (o.t - i.t)) / 60.0)
                     FROM first_in i
                     INNER JOIN first_out o ON o.conversation_id = i.conversation_id
                     WHERE o.t > i.t
                       AND o.t - i.t < INTERVAL '24 hours'"
                )->fetchColumn();
                if ($avgRaw !== false && $avgRaw !== null) {
                    $avgResp = round((float) $avgRaw, 1);
                }
            } catch (\Throwable) {
                $avgResp = null;
            }

            $comments = (int) $pdo->query(
                "SELECT COUNT(*) FROM omni_conversations
                 WHERE thread_kind = 'comment'"
            )->fetchColumn();

            $inboundToday = (int) $pdo->query(
                "SELECT COUNT(*) FROM omni_messages
                 WHERE created_at >= (date_trunc('day', NOW() AT TIME ZONE '" . self::TZ . "') AT TIME ZONE '" . self::TZ . "')
                   AND (
                        LOWER(COALESCE(sender, '')) = 'client'
                     OR LOWER(COALESCE(direction, '')) = 'inbound'
                   )"
            )->fetchColumn();

            $outboundToday = (int) $pdo->query(
                "SELECT COUNT(*) FROM omni_messages
                 WHERE created_at >= (date_trunc('day', NOW() AT TIME ZONE '" . self::TZ . "') AT TIME ZONE '" . self::TZ . "')
                   AND (
                        LOWER(COALESCE(sender, '')) IN ('agent', 'bot')
                     OR LOWER(COALESCE(direction, '')) = 'outbound'
                   )"
            )->fetchColumn();

            return [
                'conversations_today'     => $today,
                'conversations_yesterday' => $yesterday,
                'conversations_delta_pct' => $delta,
                'open_chats'              => $open,
                'unread_total'            => $unread,
                'clients_active'          => $clientsActive,
                'clients_new_week'        => $clientsWeek,
                'conversion_rate'         => $conversion,
                'avg_response_min'        => $avgResp,
                'messages_today'          => $msgsToday,
                'comments_total'          => $comments,
                'inbound_today'           => $inboundToday,
                'outbound_today'          => $outboundToday,
            ];
        } catch (\Throwable) {
            return $empty;
        }
    }

    /**
     * KPIs por red social (fila de canales).
     *
     * @return list<array{id:string,name:string,color:string,conversations:int,messages_today:int,unread:int,open:int}>
     */
    public static function channelKpis(): array
    {
        Omnichannel::bootstrap();
        $pdo = Database::connection();

        $defs = [
            ['id' => 'whatsapp',  'name' => 'WhatsApp',  'color' => '#25D366', 'aliases' => ['whatsapp', 'wa']],
            ['id' => 'instagram', 'name' => 'Instagram', 'color' => '#E4405F', 'aliases' => ['instagram', 'ig']],
            ['id' => 'messenger', 'name' => 'Messenger', 'color' => '#0084FF', 'aliases' => ['messenger', 'facebook', 'fb']],
            ['id' => 'web',       'name' => 'Web Chat',  'color' => '#00D4E8', 'aliases' => ['web', 'webchat', 'widget']],
        ];

        $out = [];
        foreach ($defs as $d) {
            $out[$d['id']] = [
                'id'              => $d['id'],
                'name'            => $d['name'],
                'color'           => $d['color'],
                'conversations'   => 0,
                'messages_today'  => 0,
                'unread'          => 0,
                'open'            => 0,
            ];
        }

        if (!$pdo) {
            return array_values($out);
        }

        try {
            $aliasMap = [];
            foreach ($defs as $d) {
                foreach ($d['aliases'] as $a) {
                    $aliasMap[$a] = $d['id'];
                }
            }

            $convRows = $pdo->query(
                "SELECT LOWER(channel) AS channel,
                        COUNT(*)::int AS conversations,
                        COALESCE(SUM(unread_count), 0)::int AS unread,
                        COUNT(*) FILTER (
                          WHERE LOWER(COALESCE(status, 'open')) IN ('open', 'pending', 'abierto')
                        )::int AS open_n
                 FROM omni_conversations
                 GROUP BY 1"
            )->fetchAll();

            foreach ($convRows as $row) {
                $id = $aliasMap[(string) $row['channel']] ?? null;
                if ($id === null) {
                    continue;
                }
                $out[$id]['conversations'] += (int) $row['conversations'];
                $out[$id]['unread'] += (int) $row['unread'];
                $out[$id]['open'] += (int) $row['open_n'];
            }

            $msgRows = $pdo->query(
                "SELECT LOWER(c.channel) AS channel, COUNT(*)::int AS n
                 FROM omni_messages m
                 INNER JOIN omni_conversations c ON c.id = m.conversation_id
                 WHERE m.created_at >= (date_trunc('day', NOW() AT TIME ZONE '" . self::TZ . "') AT TIME ZONE '" . self::TZ . "')
                 GROUP BY 1"
            )->fetchAll();

            foreach ($msgRows as $row) {
                $id = $aliasMap[(string) $row['channel']] ?? null;
                if ($id === null) {
                    continue;
                }
                $out[$id]['messages_today'] += (int) $row['n'];
            }
        } catch (\Throwable) {
            // keep zeros
        }

        return array_values($out);
    }

    /**
     * Serie de mensajes por canal (últimos 7 días, zona Bogotá).
     *
     * @return array{labels:list<string>,whatsapp:list<int>,instagram:list<int>,facebook:list<int>,web:list<int>}
     */
    public static function chartConversations(): array
    {
        Omnichannel::bootstrap();
        $pdo = Database::connection();

        $labels = [];
        $days = [];
        $tz = new \DateTimeZone(self::TZ);
        $cursor = new \DateTimeImmutable('today', $tz);
        $cursor = $cursor->modify('-6 days');
        $dayNames = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
        for ($i = 0; $i < 7; $i++) {
            $key = $cursor->format('Y-m-d');
            $days[] = $key;
            $labels[] = $dayNames[(int) $cursor->format('w')];
            $cursor = $cursor->modify('+1 day');
        }

        $series = [
            'whatsapp'  => array_fill(0, 7, 0),
            'instagram' => array_fill(0, 7, 0),
            'facebook'  => array_fill(0, 7, 0),
            'web'       => array_fill(0, 7, 0),
        ];

        if (!$pdo) {
            return ['labels' => $labels] + $series;
        }

        try {
            $stmt = $pdo->query(
                "SELECT
                    LOWER(c.channel) AS channel,
                    TO_CHAR((m.created_at AT TIME ZONE '" . self::TZ . "'), 'YYYY-MM-DD') AS day,
                    COUNT(*)::int AS n
                 FROM omni_messages m
                 INNER JOIN omni_conversations c ON c.id = m.conversation_id
                 WHERE m.created_at >= (date_trunc('day', NOW() AT TIME ZONE '" . self::TZ . "') AT TIME ZONE '" . self::TZ . "' - INTERVAL '6 days')
                 GROUP BY 1, 2"
            );
            $index = array_flip($days);
            foreach ($stmt->fetchAll() as $row) {
                $day = (string) $row['day'];
                if (!isset($index[$day])) {
                    continue;
                }
                $i = $index[$day];
                $ch = (string) $row['channel'];
                $bucket = match ($ch) {
                    'whatsapp', 'wa' => 'whatsapp',
                    'instagram', 'ig' => 'instagram',
                    'messenger', 'facebook', 'fb' => 'facebook',
                    'web', 'webchat', 'widget' => 'web',
                    default => null,
                };
                if ($bucket === null) {
                    continue;
                }
                $series[$bucket][$i] = (int) $row['n'];
            }
        } catch (\Throwable) {
            // keep zeros
        }

        return [
            'labels'    => $labels,
            'whatsapp'  => $series['whatsapp'],
            'instagram' => $series['instagram'],
            'facebook'  => $series['facebook'],
            'web'       => $series['web'],
        ];
    }

    /**
     * Inbox reciente (mismas formas que la vista del dashboard).
     *
     * @return list<array<string,mixed>>
     */
    public static function recentInbox(int $limit = 5): array
    {
        Omnichannel::bootstrap();
        $all = Omnichannel::conversations('all');
        return array_slice($all, 0, max(1, min(20, $limit)));
    }

    /**
     * Tareas N8N desde arya_tasks (crea tabla si falta).
     *
     * @return list<array<string,mixed>>
     */
    public static function recentTasks(int $limit = 4): array
    {
        return self::tasks($limit);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function tasks(int $limit = 50): array
    {
        self::bootstrapTasks();
        $pdo = Database::connection();
        if (!$pdo) {
            return [];
        }

        $limit = max(1, min(200, $limit));
        try {
            $stmt = $pdo->query(
                "SELECT id, name, workflow, schedule_cron, status, last_run_at, next_run_at, success_rate
                 FROM arya_tasks
                 ORDER BY
                   CASE LOWER(status)
                     WHEN 'error' THEN 0
                     WHEN 'activo' THEN 1
                     ELSE 2
                   END,
                   next_run_at ASC NULLS LAST,
                   id DESC
                 LIMIT {$limit}"
            );
            $rows = $stmt ? $stmt->fetchAll() : [];
            return array_map([self::class, 'mapTask'], $rows);
        } catch (\Throwable) {
            return [];
        }
    }

    public static function bootstrapTasks(): void
    {
        $pdo = Database::connection();
        if (!$pdo) {
            return;
        }
        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS arya_tasks (
                    id              BIGSERIAL PRIMARY KEY,
                    name            TEXT NOT NULL,
                    workflow        TEXT,
                    schedule_cron   TEXT,
                    status          TEXT NOT NULL DEFAULT \'activo\',
                    last_run_at     TIMESTAMPTZ,
                    next_run_at     TIMESTAMPTZ,
                    success_rate    NUMERIC(5,2) DEFAULT 100,
                    n8n_payload     JSONB DEFAULT \'{}\',
                    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
                )'
            );
        } catch (\Throwable) {
            // ignore
        }
    }

    /**
     * @param array<string,mixed> $row
     * @return array{id:int,name:string,workflow:string,schedule:string,status:string,last_run:string,next_run:string,success_rate:float}
     */
    private static function mapTask(array $row): array
    {
        return [
            'id'           => (int) $row['id'],
            'name'         => (string) ($row['name'] ?? ''),
            'workflow'     => (string) ($row['workflow'] ?? '—'),
            'schedule'     => (string) ($row['schedule_cron'] ?? '—'),
            'status'       => strtolower((string) ($row['status'] ?? 'activo')),
            'last_run'     => self::formatTs($row['last_run_at'] ?? null),
            'next_run'     => self::formatTs($row['next_run_at'] ?? null),
            'success_rate' => (float) ($row['success_rate'] ?? 0),
        ];
    }

    private static function formatTs(mixed $ts): string
    {
        if ($ts === null || $ts === '') {
            return '—';
        }
        try {
            $dt = new \DateTimeImmutable((string) $ts);
            return $dt->setTimezone(new \DateTimeZone(self::TZ))->format('Y-m-d H:i');
        } catch (\Throwable) {
            return (string) $ts;
        }
    }

    /**
     * Módulo Clientes.
     *
     * @return array{
     *   total:int,activo:int,prospecto:int,inactivo:int,new_week:int,stale:int,
     *   by_channel:list<array{channel:string,n:int}>,
     *   recent:list<array<string,mixed>>
     * }
     */
    public static function clientModule(): array
    {
        Client::bootstrap();
        $pdo = Database::connection();
        $empty = [
            'total' => 0, 'activo' => 0, 'prospecto' => 0, 'inactivo' => 0,
            'new_week' => 0, 'stale' => 0, 'by_channel' => [], 'recent' => [],
        ];
        if (!$pdo) {
            return $empty;
        }

        try {
            $total = (int) $pdo->query('SELECT COUNT(*) FROM arya_clients')->fetchColumn();
            $counts = $pdo->query(
                "SELECT LOWER(COALESCE(status, 'prospecto')) AS status, COUNT(*)::int AS n
                 FROM arya_clients GROUP BY 1"
            )->fetchAll();
            $byStatus = ['activo' => 0, 'prospecto' => 0, 'inactivo' => 0];
            foreach ($counts as $row) {
                $s = (string) $row['status'];
                if (isset($byStatus[$s])) {
                    $byStatus[$s] = (int) $row['n'];
                }
            }
            $newWeek = (int) $pdo->query(
                "SELECT COUNT(*) FROM arya_clients WHERE created_at >= NOW() - INTERVAL '7 days'"
            )->fetchColumn();
            $stale = (int) $pdo->query(
                "SELECT COUNT(*) FROM arya_clients
                 WHERE last_contact_at IS NULL
                    OR last_contact_at < NOW() - INTERVAL '14 days'"
            )->fetchColumn();
            $byCh = $pdo->query(
                "SELECT LOWER(COALESCE(channel, 'otros')) AS channel, COUNT(*)::int AS n
                 FROM arya_clients GROUP BY 1 ORDER BY n DESC"
            )->fetchAll() ?: [];
            $recentRows = $pdo->query(
                "SELECT id, name, channel, status, last_contact_at
                 FROM arya_clients
                 ORDER BY last_contact_at DESC NULLS LAST, id DESC
                 LIMIT 5"
            )->fetchAll() ?: [];

            $recent = [];
            foreach ($recentRows as $row) {
                $recent[] = [
                    'id'      => (int) $row['id'],
                    'name'    => (string) $row['name'],
                    'channel' => (string) ($row['channel'] ?? ''),
                    'status'  => (string) ($row['status'] ?? 'prospecto'),
                    'time'    => self::formatTs($row['last_contact_at'] ?? null),
                ];
            }

            return [
                'total'      => $total,
                'activo'     => $byStatus['activo'],
                'prospecto'  => $byStatus['prospecto'],
                'inactivo'   => $byStatus['inactivo'],
                'new_week'   => $newWeek,
                'stale'      => $stale,
                'by_channel' => array_map(static fn ($r) => [
                    'channel' => (string) $r['channel'],
                    'n'       => (int) $r['n'],
                ], $byCh),
                'recent'     => $recent,
            ];
        } catch (\Throwable) {
            return $empty;
        }
    }

    /**
     * Módulo Tareas + estado N8N (sin Configuración).
     *
     * @return array{
     *   total:int,activo:int,pausado:int,error:int,avg_success:?float,
     *   n8n_estado:string,n8n_configured:bool,
     *   items:list<array<string,mixed>>
     * }
     */
    public static function taskModule(): array
    {
        self::bootstrapTasks();
        $items = self::tasks(5);
        $pdo = Database::connection();

        $activo = 0;
        $pausado = 0;
        $error = 0;
        $avg = null;
        $total = 0;

        if ($pdo) {
            try {
                $rows = $pdo->query(
                    "SELECT LOWER(status) AS status, COUNT(*)::int AS n, AVG(success_rate) AS avg_sr
                     FROM arya_tasks GROUP BY 1"
                )->fetchAll() ?: [];
                foreach ($rows as $row) {
                    $n = (int) $row['n'];
                    $total += $n;
                    match ((string) $row['status']) {
                        'activo' => $activo = $n,
                        'pausado' => $pausado = $n,
                        'error' => $error = $n,
                        default => null,
                    };
                }
                $avgRaw = $pdo->query(
                    "SELECT AVG(success_rate) FROM arya_tasks WHERE LOWER(status) = 'activo'"
                )->fetchColumn();
                if ($avgRaw !== false && $avgRaw !== null) {
                    $avg = round((float) $avgRaw, 1);
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        $n8nEstado = 'sin configurar';
        $n8nConfigured = false;
        try {
            $n8n = Integration::findByCodigo('n8n');
            if ($n8n) {
                $n8nEstado = (string) ($n8n['estado'] ?? 'inactivo');
                $url = trim((string) ($n8n['endpoint_url'] ?? ''));
                $n8nConfigured = $url !== '' || strtolower($n8nEstado) === 'conectado';
            }
            $envUrl = trim((string) config('n8n.webhook_url', env('N8N_WEBHOOK_URL', '')));
            if ($envUrl !== '') {
                $n8nConfigured = true;
                if ($n8nEstado === 'sin configurar' || $n8nEstado === 'inactivo') {
                    $n8nEstado = 'webhook listo';
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        return [
            'total'          => $total,
            'activo'         => $activo,
            'pausado'        => $pausado,
            'error'          => $error,
            'avg_success'    => $avg,
            'n8n_estado'     => $n8nEstado,
            'n8n_configured' => $n8nConfigured,
            'items'          => $items,
        ];
    }

    /**
     * Módulo Generador.
     *
     * @return array{total:int,today:int,week:int,by_platform:list<array{platform:string,n:int}>,recent:list<array<string,mixed>>}
     */
    public static function generatorModule(): array
    {
        $stats = ContentGenerator::stats();
        return [
            'total'       => $stats['total'],
            'today'       => $stats['today'],
            'week'        => $stats['week'],
            'by_platform' => $stats['by_platform'],
            'recent'      => ContentGenerator::recent(5),
        ];
    }

    /**
     * KPIs para pantalla Informes (datos reales).
     *
     * @return array<string,mixed>
     */
    public static function reportKpis(): array
    {
        $s = self::stats();
        $pdo = Database::connection();
        $total = 0;
        $resolved = 0;
        $messagesSent = (int) ($s['outbound_today'] ?? 0);
        $messagesAll = 0;

        if ($pdo) {
            try {
                $total = (int) $pdo->query('SELECT COUNT(*) FROM omni_conversations')->fetchColumn();
                $resolved = (int) $pdo->query(
                    "SELECT COUNT(*) FROM omni_conversations
                     WHERE LOWER(COALESCE(status, '')) IN ('resolved', 'closed', 'resuelto', 'cerrado')"
                )->fetchColumn();
                $messagesAll = (int) $pdo->query(
                    "SELECT COUNT(*) FROM omni_messages
                     WHERE LOWER(COALESCE(sender, '')) IN ('agent', 'bot')
                        OR LOWER(COALESCE(direction, '')) = 'outbound'"
                )->fetchColumn();
                if ($messagesAll > 0) {
                    $messagesSent = $messagesAll;
                }
            } catch (\Throwable) {
                $total = (int) ($s['open_chats'] ?? 0);
            }
        }

        $avg = $s['avg_response_min'] ?? null;

        return [
            'total_conversations' => $total,
            'resolved'            => $resolved,
            'avg_response'        => $avg !== null ? ($avg . ' min') : '—',
            'satisfaction'        => (float) ($s['conversion_rate'] ?? 0), // tasa de respuesta como proxy real
            'messages_sent'       => $messagesSent,
            'new_clients'         => (int) ($s['clients_new_week'] ?? 0),
            'response_rate'       => (float) ($s['conversion_rate'] ?? 0),
            'messages_today'      => (int) ($s['messages_today'] ?? 0),
            'open_chats'          => (int) ($s['open_chats'] ?? 0),
        ];
    }

    /**
     * Canales para donut de informes (mensajes hoy / unread).
     *
     * @return list<array{id:string,name:string,color:string,unread:int}>
     */
    public static function reportChannels(): array
    {
        $kpis = self::channelKpis();
        $out = [];
        foreach ($kpis as $c) {
            $out[] = [
                'id'     => (string) $c['id'],
                'name'   => (string) $c['name'],
                'color'  => (string) $c['color'],
                'unread' => max((int) $c['messages_today'], (int) $c['conversations']),
            ];
        }
        return $out;
    }
}
