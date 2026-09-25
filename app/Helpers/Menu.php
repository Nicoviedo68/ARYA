<?php

declare(strict_types=1);

namespace Arya\Helpers;

use Arya\Core\Database;

/**
 * Menú del sidebar desde tabla maestro_menu, filtrado por alias y app_scope.
 */
final class Menu
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
            $exists = false;
            try {
                $pdo->query('SELECT 1 FROM maestro_menu LIMIT 1');
                $exists = true;
            } catch (\Throwable) {
                $exists = false;
            }

            if (!$exists) {
                $pdo->exec(
                    'CREATE TABLE IF NOT EXISTS maestro_menu (
                        id              BIGSERIAL PRIMARY KEY,
                        codigo          VARCHAR(50)  NOT NULL UNIQUE,
                        titulo          VARCHAR(100) NOT NULL,
                        ruta            VARCHAR(120) NOT NULL,
                        icono           VARCHAR(40)  NOT NULL DEFAULT \'home\',
                        grupo           VARCHAR(60)  NOT NULL DEFAULT \'principal\',
                        grupo_orden     INT          NOT NULL DEFAULT 0,
                        orden           INT          NOT NULL DEFAULT 0,
                        aliases         VARCHAR(120) NOT NULL DEFAULT \'ADMIN,AGENT\',
                        app_scope       VARCHAR(20)  NOT NULL DEFAULT \'crm\',
                        activo          BOOLEAN      NOT NULL DEFAULT TRUE,
                        created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW()
                    )'
                );
                $pdo->exec('CREATE INDEX IF NOT EXISTS idx_maestro_menu_orden ON maestro_menu (grupo_orden, orden)');
            }

            try {
                $pdo->exec('ALTER TABLE maestro_menu ADD COLUMN IF NOT EXISTS app_scope VARCHAR(20) NOT NULL DEFAULT \'crm\'');
            } catch (\Throwable) {
            }

            $count = (int) $pdo->query('SELECT COUNT(*) FROM maestro_menu')->fetchColumn();
            if ($count === 0) {
                self::seed($pdo);
            } else {
                self::seedCrmExtras($pdo);
                self::seedOcpRows($pdo);
            }
        } catch (\Throwable) {
            // sin DB usable
        }
    }

    public static function isFromDatabase(): bool
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return false;
        }
        try {
            return (int) $pdo->query('SELECT COUNT(*) FROM maestro_menu WHERE activo = TRUE')->fetchColumn() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function clearCache(?string $alias = null): void
    {
        if ($alias !== null) {
            $alias = strtoupper($alias);
            foreach (array_keys($_SESSION ?? []) as $k) {
                if (!is_string($k)) {
                    continue;
                }
                if ($k === 'menu_alias_' . $alias || str_starts_with($k, 'menu_alias_' . $alias . '_')) {
                    unset($_SESSION[$k]);
                }
            }
            unset($_SESSION['menu_cached_at'][$alias]);
            if (isset($_SESSION['menu_cached_at']) && is_array($_SESSION['menu_cached_at'])) {
                foreach (array_keys($_SESSION['menu_cached_at']) as $ck) {
                    if (is_string($ck) && str_starts_with($ck, 'menu_alias_' . $alias)) {
                        unset($_SESSION['menu_cached_at'][$ck]);
                    }
                }
            }
            return;
        }
        foreach (array_keys($_SESSION ?? []) as $k) {
            if (is_string($k) && str_starts_with($k, 'menu_alias_')) {
                unset($_SESSION[$k]);
            }
        }
        unset($_SESSION['menu_cached_at']);
    }

    /**
     * @return list<array{key:string,label:string,href:string,icon:string,grupo:string,grupo_orden:int,orden:int}>
     */
    public static function forAlias(?string $alias, string $scope = 'crm'): array
    {
        self::bootstrap();

        $alias = strtoupper(trim((string) $alias));
        if ($alias === '') {
            $alias = 'AGENT';
        }
        $scope = strtolower($scope) ?: 'crm';

        // Sin caché de sesión: el SPA conserva el shell y una caché vieja mezclaba OCP en CRM.
        $items = self::fromDatabase($scope);
        $allowed = [];

        foreach ($items as $item) {
            $codigo = (string) ($item['codigo'] ?? '');
            if (!self::codigoBelongsToScope($codigo, $scope, (string) ($item['app_scope'] ?? ''))) {
                continue;
            }
            $aliases = array_map(
                static fn (string $a): string => strtoupper(trim($a)),
                explode(',', (string) $item['aliases'])
            );
            if (in_array($alias, $aliases, true) || in_array('ALL', $aliases, true)) {
                $iconRaw = (string) ($item['icono'] ?? '');
                $allowed[] = [
                    'key'         => $codigo,
                    'label'       => (string) $item['titulo'],
                    'href'        => (string) $item['ruta'],
                    'icon'        => Icons::normalize($iconRaw, $codigo),
                    'grupo'       => (string) $item['grupo'],
                    'grupo_orden' => (int) $item['grupo_orden'],
                    'orden'       => (int) $item['orden'],
                ];
            }
        }

        usort($allowed, static function (array $a, array $b): int {
            return [$a['grupo_orden'], $a['orden']] <=> [$b['grupo_orden'], $b['orden']];
        });

        return self::assertScope($scope, $allowed);
    }

    /**
     * Defensa en profundidad: CRM nunca muestra módulos OCP y viceversa.
     *
     * @param list<array<string,mixed>> $items
     * @return list<array<string,mixed>>
     */
    private static function assertScope(string $scope, array $items): array
    {
        $scope = strtolower($scope) ?: 'crm';
        return array_values(array_filter(
            $items,
            static function (array $item) use ($scope): bool {
                $key = (string) ($item['key'] ?? $item['codigo'] ?? '');
                $rowScope = strtolower(trim((string) ($item['app_scope'] ?? '')));
                return self::codigoBelongsToScope($key, $scope, $rowScope);
            }
        ));
    }

    private static function codigoBelongsToScope(string $codigo, string $scope, string $rowScope = ''): bool
    {
        $codigo = trim($codigo);
        $scope = strtolower($scope) ?: 'crm';
        $rowScope = strtolower(trim($rowScope));
        $isOcpCode = str_starts_with($codigo, 'ocp_');

        if ($scope === 'crm') {
            // Nunca ítems OCP en el CRM, aunque app_scope esté mal en DB/caché
            if ($isOcpCode || $rowScope === 'ocp') {
                return false;
            }
            return $rowScope === '' || $rowScope === 'crm' || $rowScope === 'all';
        }

        if ($scope === 'ocp') {
            if ($rowScope === 'crm') {
                return false;
            }
            return $rowScope === 'ocp' || $rowScope === 'all' || ($rowScope === '' && $isOcpCode);
        }

        return true;
    }

    /**
     * @return array<string, list<array{key:string,label:string,href:string,icon:string,grupo:string,grupo_orden:int,orden:int}>>
     */
    public static function groupedForAlias(?string $alias, string $scope = 'crm'): array
    {
        $groups = [];
        foreach (self::forAlias($alias, $scope) as $item) {
            $groups[$item['grupo']][] = $item;
        }
        return $groups;
    }

    public static function canAccess(?string $alias, string $codigo, string $scope = 'crm'): bool
    {
        foreach (self::forAlias($alias, $scope) as $item) {
            if ($item['key'] === $codigo) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function fromDatabase(string $scope): array
    {
        $pdo = Database::connection();
        if (!$pdo) {
            return [];
        }

        try {
            if ($scope === 'crm') {
                $stmt = $pdo->prepare(
                    "SELECT codigo, titulo, ruta, icono, grupo, grupo_orden, orden, aliases, app_scope
                     FROM maestro_menu
                     WHERE activo = TRUE
                       AND codigo NOT LIKE 'ocp\\_%' ESCAPE '\\'
                       AND LOWER(TRIM(COALESCE(app_scope, 'crm'))) IN ('crm', 'all')
                     ORDER BY grupo_orden ASC, orden ASC"
                );
                $stmt->execute();
            } else {
                $stmt = $pdo->prepare(
                    "SELECT codigo, titulo, ruta, icono, grupo, grupo_orden, orden, aliases, app_scope
                     FROM maestro_menu
                     WHERE activo = TRUE
                       AND (
                            LOWER(TRIM(COALESCE(app_scope, ''))) = :scope
                            OR LOWER(TRIM(COALESCE(app_scope, ''))) = 'all'
                       )
                     ORDER BY grupo_orden ASC, orden ASC"
                );
                $stmt->execute(['scope' => $scope]);
            }
            $rows = $stmt->fetchAll() ?: [];
            return is_array($rows) ? $rows : [];
        } catch (\Throwable) {
            // Fallback sin app_scope (DB antigua): CRM nunca lista ocp_*
            try {
                $stmt = $pdo->query(
                    'SELECT codigo, titulo, ruta, icono, grupo, grupo_orden, orden, aliases
                     FROM maestro_menu
                     WHERE activo = TRUE
                     ORDER BY grupo_orden ASC, orden ASC'
                );
                $rows = $stmt->fetchAll() ?: [];
                if ($scope === 'crm') {
                    return array_values(array_filter(
                        $rows,
                        static fn ($r) => !str_starts_with((string) ($r['codigo'] ?? ''), 'ocp_')
                    ));
                }
                return array_values(array_filter(
                    $rows,
                    static fn ($r) => str_starts_with((string) ($r['codigo'] ?? ''), 'ocp_')
                ));
            } catch (\Throwable) {
                return [];
            }
        }
    }

    private static function seed(\PDO $pdo): void
    {
        $rows = self::crmMenuRows();

        $stmt = $pdo->prepare(
            'INSERT INTO maestro_menu (codigo, titulo, ruta, icono, grupo, grupo_orden, orden, aliases, app_scope)
             VALUES (:codigo, :titulo, :ruta, :icono, :grupo, :grupo_orden, :orden, :aliases, :app_scope)
             ON CONFLICT (codigo) DO NOTHING'
        );

        foreach ($rows as $r) {
            $stmt->execute([
                'codigo'      => $r[0],
                'titulo'      => $r[1],
                'ruta'        => $r[2],
                'icono'       => $r[3],
                'grupo'       => $r[4],
                'grupo_orden' => $r[5],
                'orden'       => $r[6],
                'aliases'     => $r[7],
                'app_scope'   => $r[8],
            ]);
        }

        self::seedOcpRows($pdo);
    }

    /**
     * Catálogo CRM de producción (+ Agenda / citas).
     *
     * @return list<array{0:string,1:string,2:string,3:string,4:string,5:int,6:int,7:string,8:string}>
     */
    private static function crmMenuRows(): array
    {
        return [
            ['dashboard',     'Dashboard',          'dashboard',     'home',     'Operación',      1, 10, 'ADMIN,AGENT', 'crm'],
            ['omnichannel',   'Omnicanalidad',      'omnicanalidad', 'chat',     'Operación',      1, 20, 'ADMIN,AGENT', 'crm'],
            ['clients',       'Gestión clientes',   'clientes',      'users',    'Operación',      1, 30, 'ADMIN,AGENT', 'crm'],
            ['appointments',  'Agenda / citas',     'agenda',        'calendar', 'Operación',      1, 40, 'ADMIN,AGENT', 'crm'],
            ['tasks',         'Tareas programadas', 'tareas',        'clock',    'Automatización', 2, 10, 'ADMIN,AGENT', 'crm'],
            ['chatgpt',       'Chat GPT',           'chat-gpt',      'bot',      'Automatización', 2, 15, 'ADMIN,AGENT', 'crm'],
            ['generator',     'Generador',          'generador',     'spark',    'Automatización', 2, 20, 'ADMIN',       'crm'],
            ['reports',       'Informes',           'informes',      'chart',    'Administración', 3, 10, 'ADMIN',       'crm'],
            ['settings',      'Configuración',      'configuracion', 'gear',     'Administración', 3, 20, 'ADMIN',       'crm'],
        ];
    }

    /** Inserta/ajusta módulos CRM en DBs ya pobladas. */
    private static function seedCrmExtras(\PDO $pdo): void
    {
        $rows = self::crmMenuRows();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO maestro_menu (codigo, titulo, ruta, icono, grupo, grupo_orden, orden, aliases, app_scope)
                 VALUES (:codigo, :titulo, :ruta, :icono, :grupo, :grupo_orden, :orden, :aliases, :app_scope)
                 ON CONFLICT (codigo) DO UPDATE SET
                    titulo = EXCLUDED.titulo,
                    ruta = EXCLUDED.ruta,
                    icono = EXCLUDED.icono,
                    grupo = EXCLUDED.grupo,
                    grupo_orden = EXCLUDED.grupo_orden,
                    orden = EXCLUDED.orden,
                    aliases = EXCLUDED.aliases,
                    app_scope = \'crm\',
                    activo = TRUE'
            );
            foreach ($rows as $r) {
                $stmt->execute([
                    'codigo' => $r[0], 'titulo' => $r[1], 'ruta' => $r[2], 'icono' => $r[3],
                    'grupo' => $r[4], 'grupo_orden' => $r[5], 'orden' => $r[6],
                    'aliases' => $r[7], 'app_scope' => $r[8],
                ]);
            }
            // Solo Agenda / citas; ocultar el resto de módulos Comerciales de Arya 2.0
            $pdo->exec("UPDATE maestro_menu SET activo = FALSE
                        WHERE codigo IN ('pipeline','quotes','campaigns','productivity')");
        } catch (\Throwable) {
            // ignore
        }
    }

    private static function seedOcpRows(\PDO $pdo): void
    {
        $rows = [
            ['ocp_dashboard', 'Dashboard gerencial',  'dashboard',  'home',   'Gerencia',        1, 10, 'ADMIN', 'ocp'],
            ['ocp_users',     'Usuarios',              'usuarios',   'users',  'Administración',  2, 10, 'ADMIN', 'ocp'],
            ['ocp_menu',      'Gestión menú',         'menu',       'gear',   'Administración',  2, 20, 'ADMIN', 'ocp'],
            ['ocp_roles',     'Roles',                'roles',      'shield', 'Administración',  2, 30, 'ADMIN', 'ocp'],
            ['ocp_reports',   'Informes gerenciales', 'informes',   'chart',  'Gerencia',        1, 20, 'ADMIN', 'ocp'],
            ['ocp_ai',        'Agente IA',            'agente-ia',  'spark',  'Automatización',  3, 10, 'ADMIN', 'ocp'],
        ];

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO maestro_menu (codigo, titulo, ruta, icono, grupo, grupo_orden, orden, aliases, app_scope)
                 VALUES (:codigo, :titulo, :ruta, :icono, :grupo, :grupo_orden, :orden, :aliases, :app_scope)
                 ON CONFLICT (codigo) DO UPDATE SET
                    titulo = EXCLUDED.titulo,
                    ruta = EXCLUDED.ruta,
                    icono = EXCLUDED.icono,
                    grupo = EXCLUDED.grupo,
                    grupo_orden = EXCLUDED.grupo_orden,
                    orden = EXCLUDED.orden,
                    app_scope = \'ocp\',
                    activo = TRUE'
            );
            foreach ($rows as $r) {
                $stmt->execute([
                    'codigo' => $r[0], 'titulo' => $r[1], 'ruta' => $r[2], 'icono' => $r[3],
                    'grupo' => $r[4], 'grupo_orden' => $r[5], 'orden' => $r[6],
                    'aliases' => $r[7], 'app_scope' => $r[8],
                ]);
            }
            // Solo rellenar scope vacío; respetar si el admin movió el módulo a ocp/all
            $pdo->exec("UPDATE maestro_menu SET app_scope = 'crm'
                        WHERE codigo IN (
                            'dashboard','omnichannel','clients','appointments',
                            'tasks','chatgpt','generator','reports','settings'
                        )
                          AND (app_scope IS NULL OR TRIM(app_scope) = '')");

            // Producción: no mostrar módulos Comerciales de Arya 2.0 (solo Agenda / citas).
            $pdo->exec("UPDATE maestro_menu SET activo = FALSE
                        WHERE codigo IN ('pipeline','quotes','campaigns','productivity')");
        } catch (\Throwable) {
            // ignore
        }
    }
}
