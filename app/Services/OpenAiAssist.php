<?php

declare(strict_types=1);

namespace Arya\Services;

use Arya\Models\Integration;

/**
 * Mejora de mensajes con OpenAI (token en maestro_integraciones).
 */
final class OpenAiAssist
{
    /**
     * @return array{ok:bool,message:string,text?:string}
     */
    public static function improveMessage(string $draft, string $context = ''): array
    {
        $draft = trim($draft);
        if ($draft === '') {
            return ['ok' => false, 'message' => 'Escribe un mensaje para mejorar.'];
        }

        $integ = Integration::findByCodigo('openai');
        if (!$integ || empty($integ['api_key'])) {
            return ['ok' => false, 'message' => 'OpenAI no configurado. Agrégalo en Configuración → Integraciones.'];
        }

        $endpoint = trim((string) ($integ['endpoint_url'] ?? ''));
        if ($endpoint === '') {
            $endpoint = 'https://api.openai.com/v1/chat/completions';
        } elseif (!str_contains($endpoint, '/chat/completions')) {
            $endpoint = rtrim($endpoint, '/') . '/chat/completions';
        }

        $system = 'Eres un asistente de un CRM omnicanal (Arya). '
            . 'Reescribe el mensaje del agente para WhatsApp: claro, amable, profesional y conciso en español. '
            . 'No inventes datos. Devuelve SOLO el texto mejorado, sin comillas ni explicación.';

        $userContent = "Mensaje a mejorar:\n" . $draft;
        if (trim($context) !== '') {
            $userContent .= "\n\nContexto del chat:\n" . mb_substr(trim($context), 0, 1200);
        }

        $payload = [
            'model'       => 'gpt-4o-mini',
            'temperature' => 0.4,
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $userContent],
            ],
        ];

        $ch = curl_init($endpoint);
        if ($ch === false) {
            return ['ok' => false, 'message' => 'No se pudo iniciar cURL'];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 35,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . (string) $integ['api_key'],
                'Content-Type: application/json',
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
            if ($text === '') {
                return ['ok' => false, 'message' => 'OpenAI no devolvió texto.'];
            }
            return ['ok' => true, 'message' => 'Mensaje mejorado', 'text' => $text];
        }

        $msg = is_array($data) ? (string) ($data['error']['message'] ?? $body) : $body;
        return ['ok' => false, 'message' => 'OpenAI: ' . $msg];
    }
}
