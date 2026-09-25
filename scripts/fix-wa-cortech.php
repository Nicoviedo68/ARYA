<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost:8080';
$_SERVER['SCRIPT_NAME'] = '/index.php';

require dirname(__DIR__) . '/app/bootstrap.php';

use Arya\Core\Database;

$pdo = Database::connection();
if (!$pdo) {
    echo "NO DB\n";
    exit(1);
}

// Datos oficiales Meta (imagen API Setup Cortech / +57 312 5927598)
$wabaId = '578881904471475';
$phoneNumberId = '585313634670751';

$row = $pdo->query(
    "SELECT id, token, length(token) AS token_len, left(token,6) AS pref
     FROM token_meta WHERE canal='whatsapp' AND activo=TRUE ORDER BY id DESC LIMIT 1"
)->fetch();

if (!$row) {
    echo "NO token_meta whatsapp\n";
    exit(1);
}

$token = (string) $pdo->query('SELECT token FROM token_meta WHERE id=' . (int) $row['id'])->fetchColumn();

echo "token_len={$row['token_len']} pref={$row['pref']}\n";

// Actualizar IDs correctos (mantener el mismo token)
$pdo->prepare(
    'UPDATE token_meta
     SET page_id = :waba,
         identificador = :phone,
         updated_at = NOW()
     WHERE id = :id'
)->execute([
    'waba'  => $wabaId,
    'phone' => $phoneNumberId,
    'id'    => (int) $row['id'],
]);

echo "UPDATED token_meta id={$row['id']}\n";
echo "page_id(WABA)={$wabaId}\n";
echo "identificador(phone_number_id)={$phoneNumberId}\n\n";

$tests = [
    'phone_number_id' => $phoneNumberId,
    'waba'            => $wabaId,
    'old_cardionet'   => '707834689087103',
];

foreach ($tests as $label => $id) {
    $url = 'https://graph.facebook.com/v21.0/' . rawurlencode($id) . '?fields=id,display_phone_number,verified_name';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
    ]);
    $body = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "{$label}={$id} HTTP={$status}\n{$body}\n\n";
}

// Intento de envío real DESDE el número correcto
$url = 'https://graph.facebook.com/v21.0/' . rawurlencode($phoneNumberId) . '/messages';
$payload = json_encode([
    'messaging_product' => 'whatsapp',
    'to' => '573133202689',
    'type' => 'text',
    'text' => ['body' => 'Prueba Arya desde +57 312 5927598'],
], JSON_UNESCAPED_UNICODE);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 25,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => $payload,
]);
$body = (string) curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "SEND from {$phoneNumberId} HTTP={$status}\n{$body}\n";
