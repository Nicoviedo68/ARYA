<?php

declare(strict_types=1);

namespace Arya\Services;

use Arya\Models\Integration;

/**
 * Chat multi-turno con OpenAI (GPT-5.6).
 */
final class OpenAiChat
{
    public const DEFAULT_MODEL = 'gpt-5.6';

    /** @return list<array{id:string,label:string,hint:string}> */
    public static function models(): array
    {
        return [
            ['id' => 'gpt-5.6', 'label' => 'GPT-5.6', 'hint' => 'Sol (flagship)'],
            ['id' => 'gpt-5.6-sol', 'label' => 'GPT-5.6 Sol', 'hint' => 'Máximo razonamiento'],
            ['id' => 'gpt-5.6-terra', 'label' => 'GPT-5.6 Terra', 'hint' => 'Equilibrio'],
            ['id' => 'gpt-5.6-luna', 'label' => 'GPT-5.6 Luna', 'hint' => 'Rápido y económico'],
        ];
    }

    public static function normalizeModel(string $model): string
    {
        $model = strtolower(trim($model));
        $allowed = array_column(self::models(), 'id');
        return in_array($model, $allowed, true) ? $model : self::DEFAULT_MODEL;
    }

    /**
     * @return array{
     *   ok:bool,
     *   message:string,
     *   configured:bool,
     *   endpoint:string,
     *   estado:string,
     *   tiene_api_key:bool
     * }
     */
    public static function status(): array
    {
        Integration::bootstrap();
        $row = Integration::findByCodigo('openai', false);
        $endpoint = trim((string) ($row['endpoint_url'] ?? ''));
        if ($endpoint === '') {
            $endpoint = trim((string) config('openai.endpoint', 'https://api.openai.com/v1/chat/completions'));
        }
        if ($endpoint === '') {
            $endpoint = 'https://api.openai.com/v1/chat/completions';
        }
        $apiKey = trim((string) ($row['api_key'] ?? ''));
        if ($apiKey === '') {
            $apiKey = trim((string) config('openai.api_key', ''));
        }
        $estado = strtolower((string) ($row['estado'] ?? 'inactivo'));
        $hasKey = $apiKey !== '';

        if (!$hasKey) {
            return [
                'ok'            => false,
                'configured'    => false,
                'tiene_api_key' => false,
                'endpoint'      => $endpoint,
                'estado'        => $estado,
                'message'       => 'OpenAI no configurado. Guarda la API key aquí o en Configuración → Integraciones.',
            ];
        }

        return [
            'ok'            => true,
            'configured'    => true,
            'tiene_api_key' => true,
            'endpoint'      => $endpoint,
            'estado'        => $estado !== '' ? $estado : 'activo',
            'message'       => 'OpenAI listo',
        ];
    }

    /**
     * @param list<array{role:string,content:string}> $messages
     * @return array{ok:bool,message:string,text?:string,model?:string}
     */
    public static function chat(array $messages, string $model = self::DEFAULT_MODEL): array
    {
        $creds = self::credentials();
        if ($creds === null) {
            return [
                'ok'      => false,
                'message' => 'OpenAI no configurado. Agrégalo en Configuración → Integraciones (código openai).',
            ];
        }

        $model = self::normalizeModel($model);
        $clean = [];
        foreach ($messages as $m) {
            if (!is_array($m)) {
                continue;
            }
            $role = (string) ($m['role'] ?? '');
            $content = trim((string) ($m['content'] ?? ''));
            if ($content === '' || !in_array($role, ['system', 'user', 'assistant'], true)) {
                continue;
            }
            $clean[] = ['role' => $role, 'content' => $content];
        }

        if ($clean === []) {
            return ['ok' => false, 'message' => 'No hay mensajes para enviar.'];
        }

        $hasSystem = false;
        foreach ($clean as $m) {
            if ($m['role'] === 'system') {
                $hasSystem = true;
                break;
            }
        }
        if (!$hasSystem) {
            array_unshift($clean, [
                'role'    => 'system',
                'content' => 'Eres Arya GPT, el asistente de IA del CRM Arya (Asellerator). '
                    . 'Responde en español, claro y profesional. Ayuda con ventas, omnicanalidad, clientes, '
                    . 'automatizaciones n8n y redacción. No inventes datos de clientes ni credenciales.',
            ]);
        }

        $payload = [
            'model'                 => $model,
            'messages'              => $clean,
            'max_completion_tokens' => 4096,
        ];

        $response = self::request($creds['endpoint'], $creds['api_key'], $payload);
        if (!$response['ok']) {
            // Compatibilidad: algunos entornos aún usan max_tokens
            if (str_contains(strtolower($response['message']), 'max_completion_tokens')
                || str_contains(strtolower($response['message']), 'unsupported')) {
                unset($payload['max_completion_tokens']);
                $payload['max_tokens'] = 4096;
                $response = self::request($creds['endpoint'], $creds['api_key'], $payload);
            }
        }

        if (!$response['ok']) {
            return $response;
        }

        $text = trim((string) ($response['text'] ?? ''));
        if ($text === '') {
            return ['ok' => false, 'message' => 'OpenAI no devolvió texto.'];
        }

        return [
            'ok'      => true,
            'message' => 'OK',
            'text'    => $text,
            'model'   => $model,
        ];
    }

    /**
     * @return array{endpoint:string,api_key:string}|null
     */
    private static function credentials(): ?array
    {
        Integration::bootstrap();
        $integ = Integration::findByCodigo('openai', false);
        $apiKey = trim((string) ($integ['api_key'] ?? ''));
        if ($apiKey === '') {
            $apiKey = trim((string) config('openai.api_key', ''));
        }
        if ($apiKey === '') {
            return null;
        }

        $endpoint = trim((string) ($integ['endpoint_url'] ?? ''));
        if ($endpoint === '') {
            $endpoint = trim((string) config('openai.endpoint', 'https://api.openai.com/v1/chat/completions'));
        }
        if ($endpoint === '') {
            $endpoint = 'https://api.openai.com/v1/chat/completions';
        } elseif (!str_contains($endpoint, '/chat/completions')) {
            $endpoint = rtrim($endpoint, '/') . '/chat/completions';
        }

        return ['endpoint' => $endpoint, 'api_key' => $apiKey];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{ok:bool,message:string,text?:string}
     */
    private static function request(string $endpoint, string $apiKey, array $payload): array
    {
        if (!extension_loaded('curl')) {
            return ['ok' => false, 'message' => 'cURL no disponible en PHP.'];
        }

        $ch = curl_init($endpoint);
        if ($ch === false) {
            return ['ok' => false, 'message' => 'No se pudo iniciar cURL.'];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 90,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        $body = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err !== '') {
            return ['ok' => false, 'message' => 'cURL: ' . $err];
        }

        $data = json_decode($body, true);
        if ($status >= 200 && $status < 300 && is_array($data)) {
            $text = trim((string) ($data['choices'][0]['message']['content'] ?? ''));
            return ['ok' => true, 'message' => 'OK', 'text' => $text];
        }

        $msg = is_array($data)
            ? (string) ($data['error']['message'] ?? $data['message'] ?? $body)
            : $body;
        $msg = trim($msg);
        if ($msg === '') {
            $msg = 'HTTP ' . $status;
        }

        return ['ok' => false, 'message' => 'OpenAI: ' . $msg];
    }
}
