<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost:8080';
$_SERVER['SCRIPT_NAME'] = '/index.php';

require dirname(__DIR__) . '/app/bootstrap.php';

use Arya\Core\Database;
use Arya\Models\Omnichannel;
use Arya\Models\TokenMeta;

$pdo = Database::connection();
if (!$pdo) {
    echo "ERROR: sin DB\n";
    exit(1);
}

echo "=== token_meta (whatsapp) ===\n";
$rows = $pdo->query(
    "SELECT id, canal, page_id, identificador, estado,
            length(token) AS token_len,
            left(token, 8) AS token_prefix,
            right(token, 6) AS token_suffix,
            updated_at
     FROM token_meta
     WHERE canal = 'whatsapp' AND activo = TRUE
     ORDER BY id DESC"
)->fetchAll();

if (!$rows) {
    echo "NO HAY tokens WhatsApp activos en token_meta\n";
} else {
    foreach ($rows as $r) {
        echo "id={$r['id']}\n";
        echo "  page_id={$r['page_id']}\n";
        echo "  identificador={$r['identificador']}\n";
        echo "  identificador_len=" . strlen((string) $r['identificador']) . "\n";
        echo "  token_len={$r['token_len']} prefix={$r['token_prefix']}...{$r['token_suffix']}\n";
        echo "  estado={$r['estado']} updated={$r['updated_at']}\n\n";
    }
}

echo "=== omni_conversations ===\n";
$convs = $pdo->query(
    'SELECT id, channel, external_contact, contact_name, page_id, phone_number_id, preview
     FROM omni_conversations ORDER BY id DESC LIMIT 5'
)->fetchAll();
foreach ($convs as $c) {
    echo "conv#{$c['id']} contact={$c['external_contact']} page_id={$c['page_id']} phone_number_id={$c['phone_number_id']}\n";
}

$conv = Omnichannel::findConversation((int) ($convs[0]['id'] ?? 0));
$phoneNumberId = (string) ($conv['phone_number_id'] ?? '');
$pageId = (string) ($conv['page_id'] ?? '');
$creds = TokenMeta::whatsappCredentials(
    $phoneNumberId !== '' ? $phoneNumberId : null,
    $pageId !== '' ? $pageId : null
);

echo "\n=== credentials resueltas ===\n";
if (!$creds) {
    echo "NULL — no se pudo armar credenciales\n";
    exit(1);
}
echo 'phone_number_id=' . $creds['phone_number_id'] . "\n";
echo 'page_id=' . $creds['page_id'] . "\n";
echo 'token_len=' . strlen($creds['token']) . "\n";

// Probe Graph API: GET /{phone-number-id}?fields=id,display_phone_number
$url = 'https://graph.facebook.com/v21.0/' . rawurlencode($creds['phone_number_id'])
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
$err = curl_error($ch);
curl_close($ch);

echo "\n=== Graph probe GET phone_number_id ===\n";
echo "HTTP={$status}\n";
if ($err) {
    echo "curl_err={$err}\n";
}
echo "body={$body}\n";

// Also try debug_token if token looks like a user/system token
$url2 = 'https://graph.facebook.com/v25.0/me?fields=id,name';
$ch2 = curl_init($url2);
curl_setopt_array($ch2, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $creds['token'],
    ],
]);
$body2 = (string) curl_exec($ch2);
$status2 = (int) curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);
echo "\n=== Graph probe GET /me ===\n";
echo "HTTP={$status2}\n";
echo "body={$body2}\n";
