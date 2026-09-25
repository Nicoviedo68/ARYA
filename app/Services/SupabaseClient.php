<?php

declare(strict_types=1);

namespace Arya\Services;

/**
 * Cliente mínimo para la API REST de Supabase (PostgREST vía Kong).
 */
final class SupabaseClient
{
    /** @var string */
    private $baseUrl;

    /** @var string */
    private $apiKey;

    public function __construct(string $baseUrl, string $apiKey)
    {
        $this->baseUrl = $baseUrl;
        $this->apiKey = $apiKey;
    }

    public static function fromConfig(): ?self
    {
        $url = rtrim((string) config('supabase.url', ''), '/');
        $key = (string) (config('supabase.service_key') ?: config('supabase.anon_key'));

        if ($url === '' || $key === '') {
            return null;
        }

        return new self($url, $key);
    }

    /**
     * @return array{ok:bool, status:int, body:string, message:string}
     */
    public function health(): array
    {
        $endpoint = $this->baseUrl . '/rest/v1/';
        $ch = curl_init($endpoint);
        if ($ch === false) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'message' => 'No se pudo iniciar cURL'];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_HTTPHEADER     => [
                'apikey: ' . $this->apiKey,
                'Authorization: Bearer ' . $this->apiKey,
            ],
        ]);

        $body = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error !== '') {
            return ['ok' => false, 'status' => $status, 'body' => $body, 'message' => $error];
        }

        $ok = $status >= 200 && $status < 400;
        return [
            'ok'      => $ok,
            'status'  => $status,
            'body'    => mb_substr($body, 0, 300),
            'message' => $ok ? 'API Supabase responde' : 'API Supabase respondió con error HTTP ' . $status,
        ];
    }
}
