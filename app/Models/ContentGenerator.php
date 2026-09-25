<?php

declare(strict_types=1);

namespace Arya\Models;

use Arya\Core\Database;

/**
 * Historial del Generador de contenido (`arya_generated_content`).
 */
final class ContentGenerator
{
    public static function bootstrap(): void
    {
        $pdo = Database::connection();
        if (!$pdo) {
            return;
        }
        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS arya_generated_content (
                    id           BIGSERIAL PRIMARY KEY,
                    platform     VARCHAR(40) NOT NULL,
                    tone         VARCHAR(40) NOT NULL DEFAULT \'profesional\',
                    topic        TEXT,
                    cta          TEXT,
                    caption      TEXT NOT NULL,
                    hashtags     TEXT,
                    hooks        JSONB DEFAULT \'[]\',
                    source       VARCHAR(40) NOT NULL DEFAULT \'demo\',
                    created_by   BIGINT,
                    created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
                )'
            );
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_arya_gen_created ON arya_generated_content (created_at DESC)');
        } catch (\Throwable) {
            // ignore
        }
    }

    /**
     * @param array{platform:string,tone:string,topic?:string,cta?:string,caption:string,hashtags?:string,hooks?:list<string>,source?:string,created_by?:?int} $data
     */
    public static function save(array $data): ?int
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return null;
        }

        try {
            $hooks = $data['hooks'] ?? [];
            if (!is_array($hooks)) {
                $hooks = [];
            }
            $stmt = $pdo->prepare(
                'INSERT INTO arya_generated_content
                    (platform, tone, topic, cta, caption, hashtags, hooks, source, created_by)
                 VALUES
                    (:platform, :tone, :topic, :cta, :caption, :hashtags, CAST(:hooks AS JSONB), :source, :created_by)
                 RETURNING id'
            );
            $stmt->execute([
                'platform'   => trim((string) ($data['platform'] ?? 'instagram')),
                'tone'       => trim((string) ($data['tone'] ?? 'profesional')),
                'topic'      => trim((string) ($data['topic'] ?? '')) ?: null,
                'cta'        => trim((string) ($data['cta'] ?? '')) ?: null,
                'caption'    => (string) ($data['caption'] ?? ''),
                'hashtags'   => trim((string) ($data['hashtags'] ?? '')) ?: null,
                'hooks'      => json_encode(array_values($hooks), JSON_UNESCAPED_UNICODE) ?: '[]',
                'source'     => trim((string) ($data['source'] ?? 'demo')),
                'created_by' => isset($data['created_by']) ? (int) $data['created_by'] : null,
            ]);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{total:int,today:int,week:int,by_platform:list<array{platform:string,n:int}>}
     */
    public static function stats(): array
    {
        self::bootstrap();
        $pdo = Database::connection();
        $empty = ['total' => 0, 'today' => 0, 'week' => 0, 'by_platform' => []];
        if (!$pdo) {
            return $empty;
        }

        try {
            $total = (int) $pdo->query('SELECT COUNT(*) FROM arya_generated_content')->fetchColumn();
            $today = (int) $pdo->query(
                "SELECT COUNT(*) FROM arya_generated_content
                 WHERE created_at >= (date_trunc('day', NOW() AT TIME ZONE 'America/Bogota') AT TIME ZONE 'America/Bogota')"
            )->fetchColumn();
            $week = (int) $pdo->query(
                "SELECT COUNT(*) FROM arya_generated_content
                 WHERE created_at >= NOW() - INTERVAL '7 days'"
            )->fetchColumn();
            $by = $pdo->query(
                'SELECT platform, COUNT(*)::int AS n
                 FROM arya_generated_content
                 GROUP BY platform
                 ORDER BY n DESC'
            )->fetchAll() ?: [];

            return [
                'total'       => $total,
                'today'       => $today,
                'week'        => $week,
                'by_platform' => array_map(static fn ($r) => [
                    'platform' => (string) $r['platform'],
                    'n'        => (int) $r['n'],
                ], $by),
            ];
        } catch (\Throwable) {
            return $empty;
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function recent(int $limit = 5): array
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return [];
        }
        $limit = max(1, min(30, $limit));
        try {
            $stmt = $pdo->query(
                "SELECT id, platform, tone, topic, caption, created_at
                 FROM arya_generated_content
                 ORDER BY created_at DESC, id DESC
                 LIMIT {$limit}"
            );
            $out = [];
            foreach ($stmt->fetchAll() as $row) {
                $ts = (string) ($row['created_at'] ?? '');
                $time = $ts;
                try {
                    $dt = new \DateTimeImmutable($ts);
                    $time = $dt->setTimezone(new \DateTimeZone('America/Bogota'))->format('d/m H:i');
                } catch (\Throwable) {
                    // keep
                }
                $caption = (string) ($row['caption'] ?? '');
                $out[] = [
                    'id'       => (int) $row['id'],
                    'platform' => (string) $row['platform'],
                    'tone'     => (string) $row['tone'],
                    'topic'    => (string) ($row['topic'] ?? ''),
                    'preview'  => mb_substr($caption, 0, 90) . (mb_strlen($caption) > 90 ? '…' : ''),
                    'time'     => $time,
                ];
            }
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }
}
