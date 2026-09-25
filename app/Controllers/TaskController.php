<?php

declare(strict_types=1);

namespace Arya\Controllers;

use Arya\Core\Controller;
use Arya\Models\Integration;
use Arya\Services\N8nApi;

final class TaskController extends Controller
{
    public function index(): void
    {
        $this->requireMenuAccess('tasks');

        $result = N8nApi::listWorkflows();
        $workflows = $result['workflows'] ?? [];
        $counts = $result['counts'] ?? ['total' => 0, 'active' => 0, 'inactive' => 0];
        $config = N8nApi::configStatus();
        $showConfig = !$config['ready'] || isset($_GET['config']);
        $user = auth();
        $isAdmin = strtoupper((string) ($user['role'] ?? '')) === 'ADMIN';

        $this->view('tasks/index', [
            'title'      => 'Tareas programadas',
            'active'     => 'tasks',
            'workflows'  => $workflows,
            'counts'     => $counts,
            'n8nOk'      => !empty($result['ok']),
            'n8nError'   => empty($result['ok']) ? (string) ($result['message'] ?? 'No se pudo consultar n8n.') : '',
            'n8nBase'    => (string) ($result['base_url'] ?? $config['base_url'] ?? ''),
            'n8nConfig'  => $config,
            'showConfig' => $showConfig,
            'isAdmin'    => $isAdmin,
        ]);
    }

    /** GET JSON para auto-actualizar la tabla sin F5. */
    public function apiList(): void
    {
        $this->requireMenuAccess('tasks');

        $force = isset($_GET['fresh']) && (string) $_GET['fresh'] === '1';
        $result = N8nApi::listWorkflows($force);
        $config = N8nApi::configStatus();

        $this->json([
            'ok'         => !empty($result['ok']),
            'message'    => (string) ($result['message'] ?? ''),
            'workflows'  => $result['workflows'] ?? [],
            'counts'     => $result['counts'] ?? ['total' => 0, 'active' => 0, 'inactive' => 0],
            'n8nBase'    => (string) ($result['base_url'] ?? $config['base_url'] ?? ''),
            'estado'     => (string) ($config['estado'] ?? 'inactivo'),
            'isAdmin'    => strtoupper((string) (auth()['role'] ?? '')) === 'ADMIN',
            'updated_at' => date('c'),
        ], !empty($result['ok']) ? 200 : 502);
    }

    public function toggle(string $id): void
    {
        $this->requireMenuAccess('tasks');
        $this->validateCsrf();

        $action = strtolower(trim((string) ($_POST['action'] ?? '')));
        $active = $action === 'activate' || $action === 'activar';

        if (!in_array($action, ['activate', 'activar', 'deactivate', 'desactivar'], true)) {
            if ($this->wantsJson()) {
                $this->json(['ok' => false, 'message' => 'Acción no válida. Usa activar o desactivar.'], 422);
            }
            flash('error', 'Acción no válida. Usa activar o desactivar.');
            redirect('tareas');
        }

        $result = N8nApi::setWorkflowActive($id, $active);

        if ($this->wantsJson()) {
            $list = N8nApi::listWorkflows(true);
            $this->json([
                'ok'        => !empty($result['ok']),
                'message'   => (string) ($result['message'] ?? 'No se pudo actualizar el workflow.'),
                'workflow'  => $result['workflow'] ?? null,
                'workflows' => $list['workflows'] ?? [],
                'counts'    => $list['counts'] ?? ['total' => 0, 'active' => 0, 'inactive' => 0],
            ], !empty($result['ok']) ? 200 : 422);
        }

        flash($result['ok'] ? 'success' : 'error', (string) ($result['message'] ?? 'No se pudo actualizar el workflow.'));
        redirect('tareas');
    }

    public function refresh(): void
    {
        $this->requireMenuAccess('tasks');
        $this->validateCsrf();

        N8nApi::clearWorkflowsCache();
        $result = N8nApi::listWorkflows(true);

        if ($this->wantsJson()) {
            $config = N8nApi::configStatus();
            $this->json([
                'ok'        => !empty($result['ok']),
                'message'   => !empty($result['ok'])
                    ? ('Se recargaron ' . (int) ($result['counts']['total'] ?? 0) . ' workflows desde n8n.')
                    : (string) ($result['message'] ?? 'No se pudo recargar n8n.'),
                'workflows' => $result['workflows'] ?? [],
                'counts'    => $result['counts'] ?? ['total' => 0, 'active' => 0, 'inactive' => 0],
                'n8nBase'   => (string) ($result['base_url'] ?? $config['base_url'] ?? ''),
                'estado'    => (string) ($config['estado'] ?? 'inactivo'),
            ], !empty($result['ok']) ? 200 : 502);
        }

        if (!empty($result['ok'])) {
            $total = (int) ($result['counts']['total'] ?? count($result['workflows'] ?? []));
            flash(
                'success',
                $total > 0
                    ? ('Reset OK: se recargaron ' . $total . ' workflows nuevos desde n8n.')
                    : 'Reset OK: n8n respondió sin workflows.'
            );
        } else {
            flash('error', (string) ($result['message'] ?? 'No se pudo recargar n8n.'));
        }

        redirect('tareas');
    }

    public function saveConfig(): void
    {
        $this->requireMenuAccess('tasks');
        $this->validateCsrf();

        $endpoint = trim((string) ($_POST['endpoint_url'] ?? ''));
        $apiKey = trim((string) ($_POST['api_key'] ?? ''));
        $estado = strtolower(trim((string) ($_POST['estado'] ?? 'activo'))) ?: 'activo';

        $result = Integration::saveN8nConnection($endpoint, $apiKey, $estado);
        if (!$result['ok']) {
            flash('error', (string) ($result['message'] ?? 'No se pudo guardar la configuración.'));
            redirect('tareas?config=1');
        }

        N8nApi::clearWorkflowsCache();
        $probe = N8nApi::listWorkflows(true);

        if (!empty($probe['ok'])) {
            $total = (int) ($probe['counts']['total'] ?? count($probe['workflows'] ?? []));
            flash(
                'success',
                'Configuración n8n guardada. ' . (
                    $total > 0
                        ? ('Se cargaron ' . $total . ' workflows.')
                        : 'n8n respondió sin workflows.'
                )
            );
            redirect('tareas');
        }

        flash(
            'error',
            'Configuración guardada, pero n8n no respondió: ' . (string) ($probe['message'] ?? 'error desconocido')
        );
        redirect('tareas?config=1');
    }

    private function wantsJson(): bool
    {
        if ($this->wantsPartial()) {
            return true;
        }
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        if (str_contains($accept, 'application/json')) {
            return true;
        }
        $xrw = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        return $xrw === 'xmlhttprequest' || $xrw === 'fetch';
    }
}
