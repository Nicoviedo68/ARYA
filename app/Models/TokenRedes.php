<?php

declare(strict_types=1);

namespace Arya\Models;

use Arya\Core\Database;

/**
 * Tokens de otras redes (LinkedIn, TikTok, X, etc.) → tabla token_redes.
 */
final class TokenRedes
{
    private static bool $bootstrapped = false;

    /** @return array<string,string> codigo => etiqueta */
    public static function redOptions(): array
    {
        return [
            'linkedin' => 'LinkedIn',
            'tiktok'   => 'TikTok',
            'twitter'  => 'X (Twitter)',
            'youtube'  => 'YouTube',
            'telegram' => 'Telegram',
            'web'      => 'Web Widget',
            'otro'     => 'Otro',
        ];
    }

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
                'CREATE TABLE IF NOT EXISTS token_redes (
                    id              BIGSERIAL PRIMARY KEY,
                    red             VARCHAR(40)  NOT NULL,
                    nombre_cuenta   VARCHAR(150) NOT NULL,
                    identificador   VARCHAR(255),
                    token           TEXT         NOT NULL,
                    client_id       VARCHAR(255),
                    client_secret   TEXT,
                    estado          VARCHAR(30)  NOT NULL DEFAULT \'conectado\',
                    activo          BOOLEAN      NOT NULL DEFAULT TRUE,
                    created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                    updated_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW()
                )'
            );
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_token_redes_red ON token_redes (red)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_token_redes_activo ON token_redes (activo)');
            $pdo->exec(
                'CREATE UNIQUE INDEX IF NOT EXISTS uq_token_redes_red_ident
                 ON token_redes (red, COALESCE(identificador, nombre_cuenta))
                 WHERE activo = TRUE'
            );
        } catch (\Throwable) {
            // sin DB usable
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
            $pdo->query('SELECT 1 FROM token_redes LIMIT 1');
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
                'SELECT id, red, nombre_cuenta, identificador, estado,
                        CASE WHEN token IS NOT NULL AND token <> \'\' THEN TRUE ELSE FALSE END AS tiene_token,
                        CASE WHEN client_id IS NOT NULL AND client_id <> \'\' THEN TRUE ELSE FALSE END AS tiene_client,
                        created_at, updated_at
                 FROM token_redes
                 WHERE activo = TRUE
                 ORDER BY red ASC, nombre_cuenta ASC'
            );
            return $stmt ? $stmt->fetchAll() : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array{red:string,nombre_cuenta:string,identificador?:string,token?:string,client_id?:string,client_secret?:string,estado?:string} $data
     * @return array{ok:bool,message:string,id?:int}
     */
    public static function create(array $data): array
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return ['ok' => false, 'message' => 'Sin conexión a base de datos.'];
        }

        $parsed = self::normalize($data, true);
        if (!$parsed['ok']) {
            return ['ok' => false, 'message' => $parsed['message']];
        }
        $row = $parsed['row'];

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO token_redes
                    (red, nombre_cuenta, identificador, token, client_id, client_secret, estado, updated_at)
                 VALUES
                    (:red, :nombre_cuenta, :identificador, :token, :client_id, :client_secret, :estado, NOW())
                 RETURNING id'
            );
            $stmt->execute($row);
            $id = (int) $stmt->fetchColumn();

            return ['ok' => true, 'message' => 'Cuenta de red guardada.', 'id' => $id];
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'uq_token_redes') || str_contains($msg, 'duplicate')) {
                return ['ok' => false, 'message' => 'Ya existe una cuenta activa para esa red + identificador.'];
            }
            return ['ok' => false, 'message' => 'No se pudo guardar: ' . $msg];
        }
    }

    /**
     * @param array{red:string,nombre_cuenta:string,identificador?:string,token?:string,client_id?:string,client_secret?:string,estado?:string} $data
     * @return array{ok:bool,message:string}
     */
    public static function update(int $id, array $data): array
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo || $id < 1) {
            return ['ok' => false, 'message' => 'Datos inválidos.'];
        }

        $parsed = self::normalize($data, false);
        if (!$parsed['ok']) {
            return ['ok' => false, 'message' => $parsed['message']];
        }
        $row = $parsed['row'];

        try {
            if ($row['token'] !== '') {
                $stmt = $pdo->prepare(
                    'UPDATE token_redes SET
                        red = :red,
                        nombre_cuenta = :nombre_cuenta,
                        identificador = :identificador,
                        token = :token,
                        client_id = COALESCE(NULLIF(:client_id, \'\'), client_id),
                        client_secret = COALESCE(NULLIF(:client_secret, \'\'), client_secret),
                        estado = :estado,
                        updated_at = NOW()
                     WHERE id = :id AND activo = TRUE'
                );
                $stmt->execute([
                    'red'           => $row['red'],
                    'nombre_cuenta' => $row['nombre_cuenta'],
                    'identificador' => $row['identificador'],
                    'token'         => $row['token'],
                    'client_id'     => $row['client_id'] ?? '',
                    'client_secret' => $row['client_secret'] ?? '',
                    'estado'        => $row['estado'],
                    'id'            => $id,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE token_redes SET
                        red = :red,
                        nombre_cuenta = :nombre_cuenta,
                        identificador = :identificador,
                        client_id = COALESCE(NULLIF(:client_id, \'\'), client_id),
                        client_secret = COALESCE(NULLIF(:client_secret, \'\'), client_secret),
                        estado = :estado,
                        updated_at = NOW()
                     WHERE id = :id AND activo = TRUE'
                );
                $stmt->execute([
                    'red'           => $row['red'],
                    'nombre_cuenta' => $row['nombre_cuenta'],
                    'identificador' => $row['identificador'],
                    'client_id'     => $row['client_id'] ?? '',
                    'client_secret' => $row['client_secret'] ?? '',
                    'estado'        => $row['estado'],
                    'id'            => $id,
                ]);
            }

            return ['ok' => true, 'message' => 'Cuenta de red actualizada.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo actualizar: ' . $e->getMessage()];
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
                'UPDATE token_redes
                 SET activo = FALSE, estado = \'desconectado\', updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute(['id' => $id]);

            return ['ok' => true, 'message' => 'Cuenta desconectada.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo desconectar: ' . $e->getMessage()];
        }
    }

    /**
     * @param array{red:string,nombre_cuenta:string,identificador?:string,token?:string,client_id?:string,client_secret?:string,estado?:string} $data
     * @return array{ok:true,row:array}|array{ok:false,message:string}
     */
    private static function normalize(array $data, bool $requireToken): array
    {
        $red = strtolower(trim((string) ($data['red'] ?? '')));
        $nombre = trim((string) ($data['nombre_cuenta'] ?? ''));
        $identificador = trim((string) ($data['identificador'] ?? ''));
        $token = trim((string) ($data['token'] ?? ''));
        $clientId = trim((string) ($data['client_id'] ?? ''));
        $clientSecret = trim((string) ($data['client_secret'] ?? ''));
        $estado = trim((string) ($data['estado'] ?? 'conectado')) ?: 'conectado';

        if (!array_key_exists($red, self::redOptions())) {
            return ['ok' => false, 'message' => 'Red inválida.'];
        }
        if ($nombre === '') {
            return ['ok' => false, 'message' => 'El nombre de cuenta es obligatorio.'];
        }
        if ($requireToken && $token === '') {
            return ['ok' => false, 'message' => 'El token es obligatorio.'];
        }
        // LinkedIn suele admitir client_id / secret
        if ($red === 'linkedin' && $requireToken && $clientId === '') {
            // no forzar: a veces solo usan access token
        }

        return [
            'ok'  => true,
            'row' => [
                'red'           => $red,
                'nombre_cuenta' => $nombre,
                'identificador' => $identificador !== '' ? $identificador : null,
                'token'         => $token,
                'client_id'     => $clientId !== '' ? $clientId : null,
                'client_secret' => $clientSecret !== '' ? $clientSecret : null,
                'estado'        => $estado,
            ],
        ];
    }
}
