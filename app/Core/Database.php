<?php

declare(strict_types=1);

namespace Arya\Core;

/**
 * Conexión PDO a PostgreSQL (EasyPanel).
 */
final class Database
{
    private static ?\PDO $pdo = null;
    private static ?string $lastError = null;

    public static function connection(): ?\PDO
    {
        if (self::$pdo instanceof \PDO) {
            return self::$pdo;
        }

        $cfg = self::resolveConfig();
        if ($cfg === null) {
            self::$lastError = 'Faltan credenciales de base de datos en .env';
            return null;
        }

        try {
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
                $cfg['host'],
                $cfg['port'],
                $cfg['database'],
                $cfg['sslmode']
            );

            self::$pdo = new \PDO($dsn, $cfg['username'], $cfg['password'], [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
                \PDO::ATTR_TIMEOUT            => 8,
            ]);

            self::$lastError = null;
            return self::$pdo;
        } catch (\Throwable $e) {
            self::$lastError = $e->getMessage();
            self::$pdo = null;
            return null;
        }
    }

    public static function connected(): bool
    {
        return self::connection() instanceof \PDO;
    }

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    /**
     * @return array{ok:bool, message:string, details:array}
     */
    public static function ping(): array
    {
        $cfg = self::resolveConfig();
        $details = [
            'host'     => $cfg['host'] ?? null,
            'port'     => $cfg['port'] ?? null,
            'database' => $cfg['database'] ?? null,
            'username' => $cfg['username'] ?? null,
            'sslmode'  => $cfg['sslmode'] ?? null,
            'driver'   => extension_loaded('pdo_pgsql') ? 'pdo_pgsql OK' : 'pdo_pgsql NO INSTALADO',
        ];

        if (!extension_loaded('pdo_pgsql')) {
            return [
                'ok'      => false,
                'message' => 'Falta la extensión PHP pdo_pgsql en el servidor.',
                'details' => $details,
            ];
        }

        $pdo = self::connection();
        if (!$pdo) {
            return [
                'ok'      => false,
                'message' => self::$lastError ?? 'No se pudo conectar a PostgreSQL.',
                'details' => $details,
            ];
        }

        try {
            $version = (string) $pdo->query('SELECT version()')->fetchColumn();
            $details['postgres'] = $version;
            return [
                'ok'      => true,
                'message' => 'Conexión exitosa a PostgreSQL (EasyPanel).',
                'details' => $details,
            ];
        } catch (\Throwable $e) {
            return [
                'ok'      => false,
                'message' => $e->getMessage(),
                'details' => $details,
            ];
        }
    }

    /**
     * @return array{host:string,port:string,database:string,username:string,password:string,sslmode:string}|null
     */
    private static function resolveConfig(): ?array
    {
        $host = trim((string) config('database.host', ''));
        $user = trim((string) config('database.username', ''));
        $pass = (string) config('database.password', '');
        $name = trim((string) config('database.name', 'postgres'));
        $ssl  = trim((string) config('database.sslmode', 'disable')) ?: 'disable';

        if ($host !== '' && $user !== '' && $pass !== '') {
            return [
                'host'     => $host,
                'port'     => (string) config('database.port', '5432'),
                'database' => $name !== '' ? $name : 'postgres',
                'username' => $user,
                'password' => $pass,
                'sslmode'  => $ssl,
            ];
        }

        $url = trim((string) config('database.url', ''));
        if ($url !== '') {
            $parsed = self::parseDatabaseUrl($url);
            if ($parsed !== null) {
                $parsed['sslmode'] = $parsed['sslmode'] ?: $ssl;
                return $parsed;
            }
        }

        return null;
    }

    /**
     * @return array{host:string,port:string,database:string,username:string,password:string,sslmode:string}|null
     */
    public static function parseDatabaseUrl(string $url): ?array
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return null;
        }

        $database = isset($parts['path']) ? ltrim($parts['path'], '/') : 'postgres';
        if ($database === '') {
            $database = 'postgres';
        }
        // Quitar query accidental del path
        if (str_contains($database, '?')) {
            $database = strstr($database, '?', true) ?: $database;
        }

        $sslmode = 'disable';
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $q);
            if (!empty($q['sslmode'])) {
                $sslmode = (string) $q['sslmode'];
            }
        }

        return [
            'host'     => $parts['host'],
            'port'     => (string) ($parts['port'] ?? 5432),
            'database' => $database,
            'username' => isset($parts['user']) ? rawurldecode($parts['user']) : 'postgres',
            'password' => isset($parts['pass']) ? rawurldecode($parts['pass']) : '',
            'sslmode'  => $sslmode,
        ];
    }
}
