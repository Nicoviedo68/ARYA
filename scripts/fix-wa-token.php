<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost:8080';
$_SERVER['SCRIPT_NAME'] = '/index.php';

require dirname(__DIR__) . '/app/bootstrap.php';

use Arya\Core\Database;
use Arya\Models\TokenMeta;

$pdo = Database::connection();
if (!$pdo) {
    echo "ERROR: sin DB\n";
    exit(1);
}

$row = $pdo->query(
    "SELECT id, page_id, identificador, token
     FROM token_meta
     WHERE canal = 'whatsapp' AND activo = TRUE
     ORDER BY id DESC LIMIT 1"
)->fetch();

if (!$row) {
    echo "NO hay whatsapp en token_meta\n";
    exit(1);
}

$ident = (string) ($row['identificador'] ?? '');
$token = (string) ($row['token'] ?? '');

// Caso típico: pegaron el token EAA... en identificador
$realToken = $token;
$phoneNumberId = $ident;

if (str_starts_with($ident, 'EAA') && strlen($ident) > 40) {
    $realToken = $ident;
    // phone_number_id desde la conversación sincronizada
    $phoneNumberId = (string) $pdo->query(
        "SELECT phone_number_id FROM omni_conversations
         WHERE channel = 'whatsapp' AND phone_number_id IS NOT NULL
         ORDER BY id DESC LIMIT 1"
    )->fetchColumn();
}

if ($phoneNumberId === '' || strlen($phoneNumberId) > 40) {
    echo "ERROR: no pude determinar phone_number_id válido\n";
    exit(1);
}

if ($realToken === '' || strlen($realToken) < 40) {
    echo "ERROR: token inválido (muy corto)\n";
    exit(1);
}

$stmt = $pdo->prepare(
    'UPDATE token_meta
     SET identificador = :ident,
         token = :token,
         updated_at = NOW()
     WHERE id = :id'
);
$stmt->execute([
    'ident' => $phoneNumberId,
    'token' => $realToken,
    'id'    => (int) $row['id'],
]);

echo "UPDATED token_meta id={$row['id']}\n";
echo "identificador(phone_number_id)={$phoneNumberId}\n";
echo 'token_len=' . strlen($realToken) . " prefix=" . substr($realToken, 0, 6) . "...\n";

$creds = TokenMeta::whatsappCredentials($phoneNumberId, (string) $row['page_id']);
if (!$creds) {
    echo "creds NULL\n";
    exit(1);
}

$url = 'https://graph.facebook.com/v25.0/' . rawurlencode($creds['phone_number_id'])
    . '?fields=id,display_phone_number';
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $creds['token'],
    ],
]);
$body = (string) curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Graph HTTP={$status}\n";
echo "body={$body}\n";
