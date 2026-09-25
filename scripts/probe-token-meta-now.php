<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost:8080';
$_SERVER['SCRIPT_NAME'] = '/index.php';

require dirname(__DIR__) . '/app/bootstrap.php';

use Arya\Core\Database;

$pdo = Database::connection();
$row = $pdo->query(
    "SELECT id, canal, page_id, identificador,
            length(token) AS token_len, left(token,6) AS tok_pref, right(token,4) AS tok_suf
     FROM token_meta WHERE canal='whatsapp' AND activo=TRUE ORDER BY id DESC LIMIT 1"
)->fetch();

echo "DB row:\n";
print_r($row);

$tokenRow = $pdo->query("SELECT token FROM token_meta WHERE id=" . (int) $row['id'])->fetch();
$token = (string) $tokenRow['token'];

$ids = [
    'identificador' => (string) $row['identificador'],
    'page_id'       => (string) $row['page_id'],
];

foreach ($ids as $label => $id) {
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
    echo "\n{$label}={$id} HTTP={$status}\n{$body}\n";
}
