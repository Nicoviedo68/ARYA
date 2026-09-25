<?php

declare(strict_types=1);

namespace Arya\Services;

use Arya\Models\Integration;

/**
 * Cliente REST de n8n (listado y activación de workflows).
 */
final class N8nApi
{
    private const CACHE_SESSION_KEY = 'n8n_workflows_cache';
    private const CACHE_TTL_SECONDS = 120;

    /**
     * @return array{
     *   ok:bool,
     *   message:string,
     *   base_url?:string,
     *   workflows?:list<array{
     *     id:string,
     *     name:string,
     *     active:bool,
     *     status:string,
     *     archived:bool,
     *     triggers:list<string>,
     *     trigger_label:string,
     *     updated_at:?string,
     *     created_at:?string
     *   }>,
     *   counts?:array{total:int,active:int,inactive:int},
     *   from_cache?:bool
     * }
     */
    public static function listWorkflows(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            self::clearWorkflowsCache();
        } else {
            $cached = self::cachedWorkflows();
            if ($cached !== null) {
                $cached['from_cache'] = true;
                return $cached;
            }
        }

        $creds = self::credentials();
        if ($creds === null) {
            return [
                'ok'      => false,
                'message' => self::credentialsErrorMessage(),
            ];
        }

        if (!extension_loaded('curl')) {
            return ['ok' => false, 'message' => 'cURL no disponible en PHP.', 'base_url' => $creds['base_url']];
        }

        $rawList = [];
        $cursor = null;
        $pages = 0;

        do {
            $pages++;
            $query = ['limit' => 250];
            if (is_string($cursor) && $cursor !== '') {
                $query['cursor'] = $cursor;
            }

            $url = rtrim($creds['base_url'], '/') . '/api/v1/workflows?' . http_build_query($query);
            $response = self::request('GET', $url, $creds['api_key']);

            if (!$response['ok']) {
                return [
                    'ok'       => false,
                    'message'  => $response['message'],
                    'base_url' => $creds['base_url'],
                ];
            }

            $json = $response['json'];
            $pageItems = [];
            if (is_array($json)) {
                if (isset($json['data']) && is_array($json['data'])) {
                    $pageItems = $json['data'];
                } elseif (array_is_list($json)) {
                    $pageItems = $json;
                }
            }

            foreach ($pageItems as $wf) {
                if (is_array($wf)) {
                    $rawList[] = $wf;
                }
            }

            $next = is_array($json) ? ($json['nextCursor'] ?? null) : null;
            $cursor = is_string($next) && $next !== '' ? $next : null;
        } while ($cursor !== null && $pages < 40);

        $workflows = [];
        foreach ($rawList as $wf) {
            $mapped = self::mapWorkflow($wf);
            if ($mapped !== null) {
                $workflows[] = $mapped;
            }
        }

        usort($workflows, static function (array $a, array $b): int {
            if ($a['active'] !== $b['active']) {
                return $a['active'] ? -1 : 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });

        $active = count(array_filter($workflows, static fn (array $w): bool => $w['active']));
        $total = count($workflows);

        $result = [
            'ok'         => true,
            'message'    => $total > 0 ? ('Se listaron ' . $total . ' workflows.') : 'n8n no devolvió workflows.',
            'base_url'   => $creds['base_url'],
            'workflows'  => $workflows,
            'counts'     => [
                'total'    => $total,
                'active'   => $active,
                'inactive' => $total - $active,
            ],
            'from_cache' => false,
        ];

        self::storeWorkflowsCache($result);

        return $result;
    }

    public static function clearWorkflowsCache(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION[self::CACHE_SESSION_KEY]);
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function cachedWorkflows(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }

        $entry = $_SESSION[self::CACHE_SESSION_KEY] ?? null;
        if (!is_array($entry)) {
            return null;
        }

        $storedAt = (int) ($entry['stored_at'] ?? 0);
        $payload = $entry['payload'] ?? null;
        if ($storedAt <= 0 || !is_array($payload) || empty($payload['ok'])) {
            return null;
        }

        if ((time() - $storedAt) > self::CACHE_TTL_SECONDS) {
            unset($_SESSION[self::CACHE_SESSION_KEY]);
            return null;
        }

        return $payload;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private static function storeWorkflowsCache(array $payload): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE || empty($payload['ok'])) {
            return;
        }

        $_SESSION[self::CACHE_SESSION_KEY] = [
            'stored_at' => time(),
            'payload'   => $payload,
        ];
    }

    /**
     * @return array{ok:bool,message:string,workflow?:array<string,mixed>}
     */
    public static function setWorkflowActive(string $workflowId, bool $active): array
    {
        $workflowId = trim($workflowId);
        if ($workflowId === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $workflowId)) {
            return ['ok' => false, 'message' => 'ID de workflow inválido.'];
        }

        $creds = self::credentials();
        if ($creds === null) {
            return ['ok' => false, 'message' => self::credentialsErrorMessage()];
        }

        if (!extension_loaded('curl')) {
            return ['ok' => false, 'message' => 'cURL no disponible en PHP.'];
        }

        $action = $active ? 'activate' : 'deactivate';
        $url = rtrim($creds['base_url'], '/') . '/api/v1/workflows/' . rawurlencode($workflowId) . '/' . $action;
        // n8n espera POST JSON (aunque el body vaya vacío).
        $response = self::request('POST', $url, $creds['api_key'], '{}');

        if (!$response['ok']) {
            return ['ok' => false, 'message' => $response['message']];
        }

        // El listado en sesión deja de ser válido tras activar/desactivar.
        self::clearWorkflowsCache();

        $mapped = is_array($response['json']) ? self::mapWorkflow($response['json']) : null;
        if ($mapped !== null) {
            // Forzar el estado pedido si n8n no lo devuelve claro en la respuesta.
            $mapped['active'] = $active;
            $mapped['status'] = $active ? 'activo' : 'inactivo';
        }

        return [
            'ok'       => true,
            'message'  => $active
                ? 'Workflow activado en n8n.'
                : 'Workflow desactivado en n8n.',
            'workflow' => $mapped ?? [
                'id'     => $workflowId,
                'active' => $active,
                'status' => $active ? 'activo' : 'inactivo',
            ],
        ];
    }

    /**
     * Estado de configuración para la UI de Tareas (sin exponer la API key).
     *
     * @return array{
     *   id:int,
     *   endpoint_url:string,
     *   base_url:string,
     *   estado:string,
     *   tiene_api_key:bool,
     *   ready:bool
     * }
     */
    public static function configStatus(): array
    {
        $row = Integration::findByCodigo('n8n', false);
        $endpoint = trim((string) ($row['endpoint_url'] ?? ''));
        $apiKey = trim((string) ($row['api_key'] ?? ''));

        if ($endpoint === '') {
            $endpoint = trim((string) config('n8n.webhook_url', ''));
        }
        if ($endpoint === '') {
            $endpoint = trim((string) config('ocp.webhook_2fa', ''));
        }
        if ($apiKey === '') {
            $apiKey = trim((string) config('n8n.api_key', ''));
        }

        $base = self::normalizeBaseUrl($endpoint);

        return [
            'id'            => (int) ($row['id'] ?? 0),
            'endpoint_url'  => $endpoint,
            'base_url'      => $base,
            'estado'        => (string) ($row['estado'] ?? 'inactivo'),
            'tiene_api_key' => $apiKey !== '',
            'ready'         => $base !== '' && $apiKey !== '',
        ];
    }

    /**
     * @return array{base_url:string,api_key:string}|null
     */
    public static function credentials(): ?array
    {
        $row = Integration::findByCodigo('n8n', false);
        $endpoint = trim((string) ($row['endpoint_url'] ?? ''));
        $apiKey = trim((string) ($row['api_key'] ?? ''));

        if ($endpoint === '') {
            $endpoint = trim((string) config('n8n.webhook_url', ''));
        }
        // Fallback: host del webhook 2FA (solo URL base; la API key sigue siendo obligatoria).
        if ($endpoint === '') {
            $endpoint = trim((string) config('ocp.webhook_2fa', ''));
        }
        if ($apiKey === '') {
            $apiKey = trim((string) config('n8n.api_key', ''));
        }

        $base = self::normalizeBaseUrl($endpoint);
        if ($base === '' || $apiKey === '') {
            return null;
        }

        return ['base_url' => $base, 'api_key' => $apiKey];
    }

    public static function credentialsErrorMessage(): string
    {
        $status = self::configStatus();

        if ($status['base_url'] === '' && !$status['tiene_api_key']) {
            return 'Falta configurar n8n aquí: URL base del host y API key (Settings → API en n8n).';
        }
        if ($status['base_url'] === '') {
            return 'Falta la URL base de n8n (ej. https://tu-n8n.host, sin /webhook/…). Usa el botón Configurar abajo.';
        }
        if (!$status['tiene_api_key']) {
            return 'Falta la API key de n8n en Arya. En n8n: Settings → API → Create API key (permisos workflow:list y workflow:activate). Pégala con el botón Configurar.';
        }

        return 'Configura n8n en esta página (URL base + API key).';
    }

    public static function normalizeBaseUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = (string) $parts['host'];
        $port = isset($parts['port']) ? (':' . (int) $parts['port']) : '';

        return $scheme . '://' . $host . $port;
    }

    /**
     * @return array{ok:bool,message:string,json:mixed,status:int}
     */
    private static function request(string $method, string $url, string $apiKey, ?string $body = null): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'message' => 'No se pudo iniciar cURL.', 'json' => null, 'status' => 0];
        }

        $headers = [
            'Accept: application/json',
            'X-N8N-API-KEY: ' . $apiKey,
        ];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        $method = strtoupper($method);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => $headers,
        ];

        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = $body ?? '{}';
            if ($body === null && !in_array('Content-Type: application/json', $headers, true)) {
                $headers[] = 'Content-Type: application/json';
                $opts[CURLOPT_HTTPHEADER] = $headers;
            }
        } elseif ($method !== 'GET') {
            $opts[CURLOPT_CUSTOMREQUEST] = $method;
            if ($body !== null) {
                $opts[CURLOPT_POSTFIELDS] = $body;
            }
        }

        curl_setopt_array($ch, $opts);
        $raw = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        $json = json_decode($raw, true);
        $apiMessage = '';
        if (is_array($json)) {
            $apiMessage = trim((string) ($json['message'] ?? $json['error'] ?? ''));
            if ($apiMessage === '' && isset($json['message']) && is_array($json['message'])) {
                $apiMessage = trim((string) json_encode($json['message'], JSON_UNESCAPED_UNICODE));
            }
        }

        if ($raw === '' && $err !== '') {
            return ['ok' => false, 'message' => 'Error de red hacia n8n: ' . $err, 'json' => null, 'status' => $status];
        }
        if ($status === 401 || $status === 403) {
            return [
                'ok'      => false,
                'message' => 'API key de n8n rechazada (HTTP ' . $status . '). Revisa la clave y permisos workflow:activate / workflow:deactivate.',
                'json'    => is_array($json) ? $json : null,
                'status'  => $status,
            ];
        }
        if ($status === 404) {
            return [
                'ok'      => false,
                'message' => 'Recurso no encontrado en n8n (HTTP 404). Verifica el ID del workflow o la URL base.',
                'json'    => is_array($json) ? $json : null,
                'status'  => $status,
            ];
        }
        if ($status < 200 || $status >= 300) {
            $snippet = $apiMessage !== ''
                ? $apiMessage
                : trim(mb_substr(strip_tags($raw), 0, 200));
            return [
                'ok'      => false,
                'message' => 'n8n respondió HTTP ' . $status
                    . ($snippet !== '' ? (': ' . $snippet) : '. No se pudo completar la acción.'),
                'json'    => is_array($json) ? $json : null,
                'status'  => $status,
            ];
        }

        return ['ok' => true, 'message' => 'OK', 'json' => $json, 'status' => $status];
    }

    /**
     * @param array<string,mixed> $wf
     * @return array{
     *   id:string,
     *   name:string,
     *   active:bool,
     *   status:string,
     *   archived:bool,
     *   triggers:list<string>,
     *   trigger_label:string,
     *   updated_at:?string,
     *   created_at:?string
     * }|null
     */
    private static function mapWorkflow(array $wf): ?array
    {
        $id = (string) ($wf['id'] ?? '');
        $name = trim((string) ($wf['name'] ?? ''));
        if ($id === '' && $name === '') {
            return null;
        }

        // n8n reciente puede marcar activo con activeVersionId.
        if (array_key_exists('active', $wf)) {
            $active = filter_var($wf['active'], FILTER_VALIDATE_BOOLEAN);
        } else {
            $active = !empty($wf['activeVersionId']) || !empty($wf['active_version_id']);
        }
        $archived = !empty($wf['isArchived']) || !empty($wf['is_archived']);
        $triggers = self::detectTriggers($wf);
        $label = $triggers !== [] ? implode(' · ', $triggers) : 'Sin trigger detectado';

        return [
            'id'            => $id !== '' ? $id : $name,
            'name'          => $name !== '' ? $name : ('Workflow ' . $id),
            'active'        => $active,
            'status'        => $active ? 'activo' : 'inactivo',
            'archived'      => $archived,
            'triggers'      => $triggers,
            'trigger_label' => $label,
            'updated_at'    => self::formatTs($wf['updatedAt'] ?? $wf['updated_at'] ?? null),
            'created_at'    => self::formatTs($wf['createdAt'] ?? $wf['created_at'] ?? null),
        ];
    }

    /**
     * @param array<string,mixed> $wf
     * @return list<string>
     */
    private static function detectTriggers(array $wf): array
    {
        $nodes = $wf['nodes'] ?? null;
        if (!is_array($nodes) && isset($wf['activeVersion']) && is_array($wf['activeVersion'])) {
            $nodes = $wf['activeVersion']['nodes'] ?? null;
        }
        if (!is_array($nodes)) {
            return [];
        }

        $found = [];
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }
            $type = strtolower((string) ($node['type'] ?? ''));
            if ($type === '') {
                continue;
            }

            $label = match (true) {
                str_contains($type, 'webhook') => 'Webhook',
                str_contains($type, 'scheduletrigger') || str_contains($type, 'schedule') => 'Schedule Trigger',
                str_contains($type, 'cron') => 'Cron',
                str_contains($type, 'manualtrigger') || str_contains($type, 'manual') => 'Manual',
                str_contains($type, 'emailtrigger') || str_contains($type, 'emailreadimap') => 'Email Trigger',
                str_contains($type, 'formtrigger') => 'Form Trigger',
                str_contains($type, 'chattrigger') => 'Chat Trigger',
                str_ends_with($type, 'trigger') || str_contains($type, '.trigger') => 'Otro trigger',
                default => null,
            };

            if ($label !== null) {
                $found[$label] = true;
            }
        }

        return array_keys($found);
    }

    private static function formatTs(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            if (is_numeric($value)) {
                $dt = (new \DateTimeImmutable('@' . ((int) $value)))
                    ->setTimezone(new \DateTimeZone('America/Bogota'));
            } else {
                $dt = (new \DateTimeImmutable((string) $value))
                    ->setTimezone(new \DateTimeZone('America/Bogota'));
            }
            return $dt->format('d/m/Y H:i');
        } catch (\Throwable) {
            return is_scalar($value) ? (string) $value : null;
        }
    }
}
