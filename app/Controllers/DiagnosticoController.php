<?php

declare(strict_types=1);

namespace Arya\Controllers;

use Arya\Core\Controller;
use Arya\Core\Database;
use Arya\Models\User;
use Arya\Services\SupabaseClient;

/**
 * Página pública de diagnóstico (útil en hosting sin rewrite).
 * URL: /index.php/diagnostico  o  /index.php?r=/diagnostico
 */
final class DiagnosticoController extends Controller
{
    public function index(): void
    {
        $dbPing = Database::ping();
        $api    = null;
        $client = SupabaseClient::fromConfig();
        if ($client) {
            $api = $client->health();
        }

        $installResult = null;
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->validateCsrf();
            $action = (string) ($_POST['action'] ?? 'install');
            $installResult = match ($action) {
                'seed' => $this->runSeedOnly(),
                default => $this->runInstall(),
            };
            $dbPing = Database::ping();
        }

        $this->view('diagnostico/index', [
            'title'         => 'Diagnóstico',
            'db'            => $dbPing,
            'api'           => $api,
            'tableExists'   => User::tableExists(),
            'installResult' => $installResult,
            'php'           => [
                'version'    => PHP_VERSION,
                'pdo_pgsql'  => extension_loaded('pdo_pgsql'),
                'curl'       => extension_loaded('curl'),
                'mbstring'   => extension_loaded('mbstring'),
                'path_info'  => $_SERVER['PATH_INFO'] ?? '(vacío)',
                'request'    => $_SERVER['REQUEST_URI'] ?? '',
                'script'     => $_SERVER['SCRIPT_NAME'] ?? '',
                'resolved'   => \Arya\Core\Router::resolveRequestUri(),
            ],
        ], 'layouts/auth');
    }

    /**
     * @return array{ok:bool, message:string}
     */
    private function runInstall(): array
    {
        $pdo = Database::connection();
        if (!$pdo) {
            return ['ok' => false, 'message' => 'Sin conexión DB: ' . (Database::lastError() ?? 'desconocido')];
        }

        $schemaFile = BASE_PATH . '/database/schema.sql';
        if (!is_file($schemaFile)) {
            return ['ok' => false, 'message' => 'No se encontró database/schema.sql'];
        }

        try {
            $sql = (string) file_get_contents($schemaFile);
            $sql = preg_replace('/^--.*$/m', '', $sql) ?? $sql;
            $pdo->exec($sql);

            $hash = password_hash('Bluerain1992', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                'INSERT INTO arya_users (name, email, password_hash, role)
                 VALUES (:name, :email, :hash, :role)
                 ON CONFLICT (email) DO UPDATE SET
                   password_hash = EXCLUDED.password_hash,
                   name = EXCLUDED.name,
                   updated_at = NOW()'
            );
            $stmt->execute([
                'name'  => 'Nico',
                'email' => 'Nico',
                'hash'  => $hash,
                'role'  => 'admin',
            ]);

            $seed = $this->runSeedSql($pdo);
            $msg = 'Esquema aplicado y usuario admin Nico listo.';
            if ($seed['ok']) {
                $msg .= ' ' . $seed['message'];
            }

            return ['ok' => true, 'message' => $msg];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok:bool, message:string}
     */
    private function runSeedOnly(): array
    {
        $pdo = Database::connection();
        if (!$pdo) {
            return ['ok' => false, 'message' => 'Sin conexión DB: ' . (Database::lastError() ?? 'desconocido')];
        }

        return $this->runSeedSql($pdo);
    }

    /**
     * @return array{ok:bool, message:string}
     */
    private function runSeedSql(\PDO $pdo): array
    {
        $seedFile = BASE_PATH . '/database/seed.sql';
        if (!is_file($seedFile)) {
            return ['ok' => false, 'message' => 'No se encontró database/seed.sql'];
        }

        try {
            $sql = (string) file_get_contents($seedFile);
            $pdo->exec($sql);
            return ['ok' => true, 'message' => 'Datos demo (clientes, tareas, inbox) cargados.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Seed: ' . $e->getMessage()];
        }
    }
}
