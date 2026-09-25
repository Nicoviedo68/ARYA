<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost:8080';
$_SERVER['SCRIPT_NAME'] = '/index.php';

require dirname(__DIR__) . '/app/bootstrap.php';

use Arya\Core\Database;

$pdo = Database::connection();
$row = $pdo->query(
    "SELECT token, identificador, page_id FROM token_meta WHERE canal='whatsapp' AND activo=TRUE ORDER BY id DESC LIMIT 1"
)->fetch();
$token = (string) $row['token'];

echo "=== raw phone_number_id from inbox payload ===\n";
$payload = $pdo->query(
    "SELECT payload::text FROM meta_webhook_inbox ORDER BY id DESC LIMIT 1"
)->fetchColumn();
if (preg_match('/"phone_number_id"\s*:\s*"(\d+)"/', (string) $payload, $m)) {
    echo "inbox phone_number_id={$m[1]}\n";
    $fromInbox = $m[1];
} else {
    echo "no phone_number_id in payload\n";
    $fromInbox = null;
}

if (preg_match('/"display_phone_number"\s*:\s*"([^"]+)"/', (string) $payload, $m2)) {
    echo "display_phone_number={$m2[1]}\n";
}

$candidates = array_values(array_unique(array_filter([
    $fromInbox,
    (string) $row['identificador'],
    (string) $row['page_id'],
    '585313634570751',
    '585313634670751',
    '578881904471475',
    '707834689087103',
])));

echo "\n=== GET /me ===\n";
$ch = curl_init('https://graph.facebook.com/v21.0/me?fields=id,name');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
]);
$b = (string) curl_exec($ch);
$s = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP={$s} body={$b}\n";

echo "\n=== debug candidates ===\n";
foreach ($candidates as $id) {
    $url = 'https://graph.facebook.com/v21.0/' . rawurlencode($id) . '?fields=id,display_phone_number';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
    ]);
    $body = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $short = mb_substr($body, 0, 160);
    echo "ID={$id} HTTP={$status} {$short}\n";
}

// List phone numbers on WABA if possible
echo "\n=== try WABA phone_numbers ===\n";
foreach (['2209035232944269', '707834689087103'] as $waba) {
    $url = 'https://graph.facebook.com/v25.0/' . rawurlencode($waba) . '/phone_numbers?fields=id,display_phone_number,verified_name';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
    ]);
    $body = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "WABA={$waba} HTTP={$status} " . mb_substr($body, 0, 300) . "\n";
}
