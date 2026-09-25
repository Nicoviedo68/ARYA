<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost:8080';
$_SERVER['SCRIPT_NAME'] = '/index.php';

require dirname(__DIR__) . '/app/bootstrap.php';

use Arya\Models\TokenMeta;
use Arya\Services\MetaCloud;

$creds = TokenMeta::whatsappCredentials();
echo "creds phone_number_id=" . ($creds['phone_number_id'] ?? 'null') . "\n";
echo "creds page_id=" . ($creds['page_id'] ?? 'null') . "\n";
echo "creds ident=" . ($creds['identificador'] ?? 'null') . "\n";
echo 'token_len=' . strlen((string) ($creds['token'] ?? '')) . "\n\n";

// Envío real de prueba al cliente del chat
$to = '573133202689';
$result = MetaCloud::sendText($to, 'Prueba Arya: respuesta desde token_meta ✅');
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
