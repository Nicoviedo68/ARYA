<?php

declare(strict_types=1);

/**
 * Endpoint HTTP para n8n → guarda cod_ingreso en maestrousuario.
 *
 * URL producción:
 *   https://app.aisscol.com/Arya/public/api/n8n-2fa.php
 *
 * Método: POST
 * Content-Type: application/json
 *
 * Body:
 * {
 *   "id": 1,
 *   "cod_ingreso": "834815"
 * }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Solo POST.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use Arya\N8N\TwoFactorService;

$raw = file_get_contents('php://input');
$data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
if (!is_array($data)) {
    $data = $_POST;
}

$id = $data['id'] ?? null;
$codIngreso = trim((string) ($data['cod_ingreso'] ?? $data['codigo'] ?? ''));

// API key opcional
$expectedKey = trim((string) config('n8n.api_key', ''));
if ($expectedKey !== '') {
    $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    $provided = '';
    if (preg_match('/Bearer\s+(.+)/i', $header, $m)) {
        $provided = trim($m[1]);
    } else {
        $provided = (string) ($data['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '');
    }
    if ($provided === '' || !hash_equals($expectedKey, $provided)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'message' => 'No autorizado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if ($id === null || $id === '' || $codIngreso === '') {
    http_response_code(422);
    echo json_encode([
        'ok'      => false,
        'message' => 'Se requieren id y cod_ingreso.',
        'example' => [
            'id'          => 1,
            'cod_ingreso' => '834815',
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$user = TwoFactorService::findById($id);
if (!$user) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Usuario no encontrado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!TwoFactorService::setCodigoIngreso($id, $codIngreso)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'No se pudo guardar cod_ingreso.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'ok'      => true,
    'message' => 'cod_ingreso guardado.',
    'data'    => [
        'id'          => $user['id'],
        'usuario'     => $user['usuario'],
        'cod_ingreso' => $codIngreso,
    ],
], JSON_UNESCAPED_UNICODE);
