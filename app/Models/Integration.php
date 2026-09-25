<?php

declare(strict_types=1);

namespace Arya\Models;

use Arya\Core\Database;

/**
 * Integraciones del CRM (maestro_integraciones).
 */
final class Integration
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
                'CREATE TABLE IF NOT EXISTS maestro_integraciones (
                    id              BIGSERIAL PRIMARY KEY,
                    codigo          VARCHAR(60)  NOT NULL UNIQUE,
                    nombre          VARCHAR(120) NOT NULL,
                    tipo            VARCHAR(60)  NOT NULL DEFAULT \'servicio\',
                    estado          VARCHAR(30)  NOT NULL DEFAULT \'inactivo\',
                    endpoint_url    TEXT,
                    api_key         TEXT,
                    config          JSONB        NOT NULL DEFAULT \'{}\',
                    notas           TEXT,
                    activo          BOOLEAN      NOT NULL DEFAULT TRUE,
                    created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                    updated_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW()
                )'
            );
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_integraciones_activo ON maestro_integraciones (activo)');

            $count = (int) $pdo->query('SELECT COUNT(*) FROM maestro_integraciones')->fetchColumn();
            if ($count === 0) {
                self::seed($pdo);
            } else {
                self::ensureOpenAiRow($pdo);
            }
        } catch (\Throwable) {
            // sin DB usable
        }
    }

    private static function ensureOpenAiRow(\PDO $pdo): void
    {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO maestro_integraciones (codigo, nombre, tipo, estado, endpoint_url, notas)
                 VALUES (:codigo, :nombre, :tipo, :estado, :endpoint_url, :notas)
                 ON CONFLICT (codigo) DO NOTHING'
            );
            $stmt->execute([
                'codigo'       => 'openai',
                'nombre'       => 'OpenAI / Chat GPT',
                'tipo'         => 'api',
                'estado'       => 'inactivo',
                'endpoint_url' => 'https://api.openai.com/v1/chat/completions',
                'notas'        => 'API key para Chat GPT y mejora de mensajes omnicanal.',
            ]);
        } catch (\Throwable) {
            // ignore
        }
    }

    private static function seed(\PDO $pdo): void
    {
        $rows = [
            ['postgresql', 'PostgreSQL / EasyPanel', 'db', 'activo', null, 'Base de datos principal (maestrousuario).'],
            ['supabase', 'API Supabase', 'api', 'inactivo', null, 'REST PostgREST vía Kong (opcional).'],
            ['n8n', 'N8N Automatizaciones', 'webhook', 'inactivo', null, 'Webhooks 2FA y flujos omnicanal.'],
            ['openai', 'OpenAI / Chat GPT', 'api', 'inactivo', 'https://api.openai.com/v1/chat/completions', 'API key para Chat GPT y mejora de mensajes omnicanal.'],
        ];

        $stmt = $pdo->prepare(
            'INSERT INTO maestro_integraciones (codigo, nombre, tipo, estado, endpoint_url, notas)
             VALUES (:codigo, :nombre, :tipo, :estado, :endpoint_url, :notas)
             ON CONFLICT (codigo) DO NOTHING'
        );

        foreach ($rows as [$codigo, $nombre, $tipo, $estado, $url, $notas]) {
            $stmt->execute([
                'codigo'       => $codigo,
                'nombre'       => $nombre,
                'tipo'         => $tipo,
                'estado'       => $estado,
                'endpoint_url' => $url,
                'notas'        => $notas,
            ]);
        }
    }

    public static function tableExists(): bool
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return false;
        }
        try {
            $pdo->query('SELECT 1 FROM maestro_integraciones LIMIT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function allActive(): array
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return [];
        }

        try {
            $stmt = $pdo->query(
                'SELECT id, codigo, nombre, tipo, estado, endpoint_url,
                        CASE WHEN api_key IS NOT NULL AND api_key <> \'\' THEN TRUE ELSE FALSE END AS tiene_api_key,
                        config, notas, activo, created_at, updated_at
                 FROM maestro_integraciones
                 WHERE activo = TRUE
                 ORDER BY nombre ASC'
            );
            return $stmt ? $stmt->fetchAll() : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array{id:int,codigo:string,nombre:string,endpoint_url:?string,api_key:?string,estado:string}|null
     */
    public static function findByCodigo(string $codigo, bool $onlyActive = true): ?array
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return null;
        }

        try {
            $sql = 'SELECT id, codigo, nombre, endpoint_url, api_key, estado
                    FROM maestro_integraciones
                    WHERE lower(codigo) = lower(:codigo)';
            if ($onlyActive) {
                $sql .= ' AND activo = TRUE';
            }
            $sql .= ' ORDER BY activo DESC, id DESC LIMIT 1';

            $stmt = $pdo->prepare($sql);
            $stmt->execute(['codigo' => trim($codigo)]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array{nombre?:string,tipo?:string,estado?:string,endpoint_url?:string,api_key?:string,notas?:string} $data
     * @return array{ok:bool,message:string}
     */
    public static function update(int $id, array $data): array
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo || $id < 1) {
            return ['ok' => false, 'message' => 'Datos inválidos.'];
        }

        $nombre = trim((string) ($data['nombre'] ?? ''));
        $tipo = trim((string) ($data['tipo'] ?? 'servicio')) ?: 'servicio';
        $estado = trim((string) ($data['estado'] ?? 'inactivo')) ?: 'inactivo';
        $url = trim((string) ($data['endpoint_url'] ?? ''));
        $apiKey = trim((string) ($data['api_key'] ?? ''));
        $notas = trim((string) ($data['notas'] ?? ''));

        if ($nombre === '') {
            return ['ok' => false, 'message' => 'El nombre es obligatorio.'];
        }

        $activo = in_array(strtolower($estado), ['activo', 'active', 'ok'], true);

        try {
            if ($apiKey !== '') {
                $stmt = $pdo->prepare(
                    'UPDATE maestro_integraciones SET
                        nombre = :nombre,
                        tipo = :tipo,
                        estado = :estado,
                        activo = :activo,
                        endpoint_url = :endpoint_url,
                        api_key = :api_key,
                        notas = :notas,
                        updated_at = NOW()
                     WHERE id = :id'
                );
                $stmt->execute([
                    'nombre'       => $nombre,
                    'tipo'         => $tipo,
                    'estado'       => $estado,
                    'activo'       => $activo ? 't' : 'f',
                    'endpoint_url' => $url !== '' ? $url : null,
                    'api_key'      => $apiKey,
                    'notas'        => $notas !== '' ? $notas : null,
                    'id'           => $id,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE maestro_integraciones SET
                        nombre = :nombre,
                        tipo = :tipo,
                        estado = :estado,
                        activo = :activo,
                        endpoint_url = :endpoint_url,
                        notas = :notas,
                        updated_at = NOW()
                     WHERE id = :id'
                );
                $stmt->execute([
                    'nombre'       => $nombre,
                    'tipo'         => $tipo,
                    'estado'       => $estado,
                    'activo'       => $activo ? 't' : 'f',
                    'endpoint_url' => $url !== '' ? $url : null,
                    'notas'        => $notas !== '' ? $notas : null,
                    'id'           => $id,
                ]);
            }

            return ['ok' => true, 'message' => 'Integración actualizada.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo actualizar: ' . $e->getMessage()];
        }
    }

    /**
     * @param array{codigo:string,nombre:string,tipo?:string,estado?:string,endpoint_url?:string,api_key?:string,notas?:string} $data
     * @return array{ok:bool,message:string,id?:int}
     */
    public static function create(array $data): array
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return ['ok' => false, 'message' => 'Sin conexión a base de datos.'];
        }

        $codigo = strtolower(trim((string) ($data['codigo'] ?? '')));
        $codigo = preg_replace('/[^a-z0-9_\-]/', '', $codigo) ?? '';
        $nombre = trim((string) ($data['nombre'] ?? ''));
        $tipo = trim((string) ($data['tipo'] ?? 'servicio')) ?: 'servicio';
        $estado = trim((string) ($data['estado'] ?? 'inactivo')) ?: 'inactivo';
        $url = trim((string) ($data['endpoint_url'] ?? ''));
        $apiKey = trim((string) ($data['api_key'] ?? ''));
        $notas = trim((string) ($data['notas'] ?? ''));

        if ($codigo === '' || $nombre === '') {
            return ['ok' => false, 'message' => 'Código y nombre son obligatorios.'];
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO maestro_integraciones
                    (codigo, nombre, tipo, estado, endpoint_url, api_key, notas)
                 VALUES
                    (:codigo, :nombre, :tipo, :estado, :endpoint_url, :api_key, :notas)
                 RETURNING id'
            );
            $stmt->execute([
                'codigo'       => $codigo,
                'nombre'       => $nombre,
                'tipo'         => $tipo,
                'estado'       => $estado,
                'endpoint_url' => $url !== '' ? $url : null,
                'api_key'      => $apiKey !== '' ? $apiKey : null,
                'notas'        => $notas !== '' ? $notas : null,
            ]);
            $id = (int) $stmt->fetchColumn();

            return ['ok' => true, 'message' => 'Integración creada.', 'id' => $id];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo crear: ' . $e->getMessage()];
        }
    }

    public static function deactivate(int $id): array
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo || $id < 1) {
            return ['ok' => false, 'message' => 'Datos inválidos.'];
        }

        try {
            $stmt = $pdo->prepare(
                'UPDATE maestro_integraciones
                 SET activo = FALSE, estado = \'inactivo\', updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute(['id' => $id]);

            return ['ok' => true, 'message' => 'Integración desactivada.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo desactivar: ' . $e->getMessage()];
        }
    }

    /**
     * Guarda conexión n8n (URL base + API key) usada por Tareas programadas.
     *
     * @return array{ok:bool,message:string}
     */
    public static function saveN8nConnection(string $endpointUrl, string $apiKey, string $estado = 'activo'): array
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return ['ok' => false, 'message' => 'Sin conexión a base de datos.'];
        }

        $endpointUrl = trim($endpointUrl);
        $apiKey = trim($apiKey);
        $estado = strtolower(trim($estado)) ?: 'activo';
        if (!in_array($estado, ['activo', 'inactivo', 'pendiente', 'error'], true)) {
            $estado = 'activo';
        }

        if ($endpointUrl === '') {
            return ['ok' => false, 'message' => 'La URL base de n8n es obligatoria.'];
        }

        $row = self::findByCodigo('n8n', false);
        $hasKey = $row !== null && trim((string) ($row['api_key'] ?? '')) !== '';
        if ($apiKey === '' && !$hasKey) {
            return ['ok' => false, 'message' => 'La API key de n8n es obligatoria (Settings → API en n8n).'];
        }

        if ($row === null) {
            return self::create([
                'codigo'       => 'n8n',
                'nombre'       => 'N8N Automatizaciones',
                'tipo'         => 'api',
                'estado'       => $estado,
                'endpoint_url' => $endpointUrl,
                'api_key'      => $apiKey,
                'notas'        => 'Configurado desde Tareas programadas.',
            ]);
        }

        return self::update((int) $row['id'], [
            'nombre'       => (string) ($row['nombre'] ?? 'N8N Automatizaciones'),
            'tipo'         => 'api',
            'estado'       => $estado,
            'endpoint_url' => $endpointUrl,
            'api_key'      => $apiKey,
            'notas'        => 'Configurado desde Tareas programadas.',
        ]);
    }

    /**
     * Guarda conexión OpenAI usada por Chat GPT / omnicanal.
     *
     * @return array{ok:bool,message:string}
     */
    public static function saveOpenAiConnection(string $endpointUrl, string $apiKey, string $estado = 'activo'): array
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return ['ok' => false, 'message' => 'Sin conexión a base de datos.'];
        }

        $endpointUrl = trim($endpointUrl);
        $apiKey = trim($apiKey);
        $estado = strtolower(trim($estado)) ?: 'activo';
        if (!in_array($estado, ['activo', 'inactivo', 'pendiente', 'error'], true)) {
            $estado = 'activo';
        }

        if ($endpointUrl === '') {
            $endpointUrl = 'https://api.openai.com/v1/chat/completions';
        }
        if (!str_contains($endpointUrl, '/chat/completions')) {
            $endpointUrl = rtrim($endpointUrl, '/') . '/chat/completions';
        }

        $row = self::findByCodigo('openai', false);
        $hasKey = $row !== null && trim((string) ($row['api_key'] ?? '')) !== '';
        if ($apiKey === '' && !$hasKey) {
            return ['ok' => false, 'message' => 'La API key de OpenAI es obligatoria.'];
        }

        if ($row === null) {
            return self::create([
                'codigo'       => 'openai',
                'nombre'       => 'OpenAI / Chat GPT',
                'tipo'         => 'api',
                'estado'       => $estado,
                'endpoint_url' => $endpointUrl,
                'api_key'      => $apiKey,
                'notas'        => 'Configurado desde Chat GPT.',
            ]);
        }

        return self::update((int) $row['id'], [
            'nombre'       => (string) ($row['nombre'] ?? 'OpenAI / Chat GPT'),
            'tipo'         => 'api',
            'estado'       => $estado,
            'endpoint_url' => $endpointUrl,
            'api_key'      => $apiKey,
            'notas'        => 'Configurado desde Chat GPT / Integraciones.',
        ]);
    }

    /**
     * Sincroniza estado/URL desde .env + ping en vivo (no pisa notas del usuario).
     */
    public static function syncRuntimeStatus(array $dbPing, ?array $apiHealth): void
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return;
        }

        $supabaseUrl = trim((string) config('supabase.url', ''));

        $updates = [
            'postgresql' => [
                'estado' => !empty($dbPing['ok']) ? 'activo' : 'error',
                'url'    => null,
            ],
            'supabase' => [
                'estado' => $apiHealth === null
                    ? ($supabaseUrl !== '' ? 'pendiente' : 'inactivo')
                    : (!empty($apiHealth['ok']) ? 'activo' : 'error'),
                'url' => $supabaseUrl !== '' ? $supabaseUrl : null,
            ],
        ];

        // n8n se gestiona desde Configuración (URL base + API key).
        // No pisar estado/URL con el webhook del .env.

        try {
            $stmt = $pdo->prepare(
                'UPDATE maestro_integraciones SET
                    estado = :estado,
                    endpoint_url = COALESCE(:endpoint_url, endpoint_url),
                    updated_at = NOW()
                 WHERE codigo = :codigo AND activo = TRUE'
            );
            foreach ($updates as $codigo => $row) {
                $stmt->execute([
                    'estado'       => $row['estado'],
                    'endpoint_url' => $row['url'],
                    'codigo'       => $codigo,
                ]);
            }
        } catch (\Throwable) {
            // ignore
        }
    }
}
