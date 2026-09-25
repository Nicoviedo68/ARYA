<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost:8080';
$_SERVER['SCRIPT_NAME'] = '/index.php';

require dirname(__DIR__) . '/app/bootstrap.php';

use Arya\Core\Database;

$pdo = Database::connection();
$row = $pdo->query(
    "SELECT token FROM token_meta WHERE canal='whatsapp' AND activo=TRUE ORDER BY id DESC LIMIT 1"
)->fetch();
$token = (string) $row['token'];
$to = '573133202689';

$phoneIds = ['707834689087103', '585313634670751'];

foreach ($phoneIds as $phoneId) {
    $url = 'https://graph.facebook.com/v21.0/' . rawurlencode($phoneId) . '/messages';
    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => $to,
        'type' => 'text',
        'text' => ['body' => 'Prueba Arya (no enviar si falla auth)'],
    ];

    // Solo validamos con un GET de permisos; para send usamos POST pero NO queremos spamear.
    // Mejor: endpoint de message_templates o solo reportar cuál phone id es usable.
    $check = 'https://graph.facebook.com/v21.0/' . rawurlencode($phoneId) . '?fields=id,display_phone_number,quality_rating';
    $ch = curl_init($check);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
    ]);
    $body = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "phone_id={$phoneId} HTTP={$status} {$body}\n\n";
}

echo "CONCLUSIÓN:\n";
echo "- Token válido (GET /me OK).\n";
echo "- El token SÍ ve phone_number_id 2209035232944269 (+57 333 6025171).\n";
echo "- El webhook llegó a phone_number_id 585313634670751 (573125927598) — ese ID el token NO lo puede usar.\n";
echo "- Por eso falla Authentication/permissions al responder.\n";
echo "SOLUCIÓN: en token_meta guardar el token permanente del número que recibe los msgs (585313634670751),\n";
echo "o bien usar en Meta el mismo número/app. También page_id estaba mal: ahí pegaron el phone_number_id usable.\n";
