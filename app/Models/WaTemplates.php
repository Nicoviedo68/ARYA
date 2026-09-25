<?php

declare(strict_types=1);

namespace Arya\Models;

use Arya\Core\Database;

final class WaTemplates
{
    /**
     * Plantillas para el compose: solo las sincronizadas desde Meta (source=meta).
     *
     * @return list<array{codigo:string,nombre:string,body:string,meta_name:?string,status?:?string,idioma?:string}>
     */
    public static function activeForCompose(): array
    {
        $pdo = Database::connection();
        if (!$pdo) {
            return [];
        }
        try {
            self::ensureTable($pdo);
            $stmt = $pdo->query(
                "SELECT codigo, nombre, body, meta_name, status, idioma
                 FROM wa_templates
                 WHERE activo = TRUE
                   AND canal = 'whatsapp'
                   AND COALESCE(source, 'local') = 'meta'
                 ORDER BY orden ASC, id ASC"
            );
            $rows = $stmt->fetchAll() ?: [];
            return is_array($rows) ? $rows : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private static function ensureTable(\PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS wa_templates (
                id          BIGSERIAL PRIMARY KEY,
                codigo      VARCHAR(160) NOT NULL UNIQUE,
                nombre      VARCHAR(120) NOT NULL,
                body        TEXT NOT NULL,
                idioma      VARCHAR(20) NOT NULL DEFAULT \'es\',
                meta_name   VARCHAR(120),
                canal       VARCHAR(40) NOT NULL DEFAULT \'whatsapp\',
                activo      BOOLEAN NOT NULL DEFAULT TRUE,
                orden       INT NOT NULL DEFAULT 0,
                source      VARCHAR(20) DEFAULT \'local\',
                status      VARCHAR(60),
                category    VARCHAR(60),
                meta_id     VARCHAR(80),
                created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )'
        );
        foreach (['source VARCHAR(20) DEFAULT \'local\'', 'status VARCHAR(60)', 'category VARCHAR(60)', 'meta_id VARCHAR(80)'] as $col) {
            try {
                $pdo->exec('ALTER TABLE wa_templates ADD COLUMN IF NOT EXISTS ' . $col);
            } catch (\Throwable) {
            }
        }
    }
}
