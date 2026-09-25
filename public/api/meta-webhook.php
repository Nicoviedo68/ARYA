<?php

declare(strict_types=1);

/**
 * Webhook Meta Business → recepción de mensajes (WhatsApp / Messenger / Instagram).
 *
 * Callback URL (pegar en Meta Developers → Webhooks):
 *   https://app.aisscol.com/Arya/public/api/meta-webhook.php
 *
 * GET  → verificación (hub.mode, hub.verify_token, hub.challenge)
 * POST → eventos / mensajes entrantes
 *
 * Bootstrap ligero (sin sesión) para que Meta valide sin cookies/headers raros.
 */

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Hub-Signature-256');
    http_response_code(204);
    exit;
}

$basePath = dirname(__DIR__, 2);
define('BASE_PATH', $basePath);
define('APP_PATH', $basePath . '/app');

require_once APP_PATH . '/Helpers/functions.php';
require_once APP_PATH . '/Core/Env.php';
require_once APP_PATH . '/Core/Database.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'Arya\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = APP_PATH . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

\Arya\Core\Env::load(BASE_PATH . '/.env');

use Arya\Services\MetaWebhook;

/**
 * @return array{mode:string,token:string,challenge:string}
 */
function meta_hub_params(): array
{
    $mode = (string) ($_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '');
    $token = (string) ($_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '');
    $challenge = (string) ($_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '');

    if ($mode === '' || $token === '' || $challenge === '') {
        $qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
        // Meta envía hub.mode — PHP a veces lo deja con punto o lo convierte a _
        parse_str(str_replace(['hub.', 'hub%2E', 'hub%2e'], 'hub_', $qs), $hub);
        $mode = $mode !== '' ? $mode : (string) ($hub['hub_mode'] ?? '');
        $token = $token !== '' ? $token : (string) ($hub['hub_verify_token'] ?? '');
        $challenge = $challenge !== '' ? $challenge : (string) ($hub['hub_challenge'] ?? '');
    }

    return [
        'mode'      => $mode,
        'token'     => $token,
        'challenge' => $challenge,
    ];
}

if ($method === 'GET') {
    $hub = meta_hub_params();

    // Sin params: healthcheck (útil para comprobar que el archivo ya está en Hostinger)
    if ($hub['mode'] === '' && $hub['token'] === '' && $hub['challenge'] === '') {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'      => true,
            'service' => 'meta-webhook',
            'message' => 'Endpoint activo. Meta debe llamar con hub.mode / hub.verify_token / hub.challenge.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    MetaWebhook::handleVerification($hub['mode'], $hub['token'], $hub['challenge']);
}

if ($method !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => 'Usa GET (verify) o POST (eventos).'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = (string) file_get_contents('php://input');
$result = MetaWebhook::handleEvent($raw);

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok'      => $result['ok'],
    'stored'  => $result['stored'],
    'message' => $result['message'],
], JSON_UNESCAPED_UNICODE);
