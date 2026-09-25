<?php

declare(strict_types=1);

/**
 * Uso (NO dejes tokens en este archivo):
 *   php scripts/update-wa-from-n8n.php
 *
 * Actualiza token_meta desde variables de entorno:
 *   WA_WABA_ID
 *   WA_PHONE_NUMBER_ID
 *   WA_TOKEN
 */

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost:8080';
$_SERVER['SCRIPT_NAME'] = '/index.php';

require dirname(__DIR__) . '/app/bootstrap.php';

use Arya\Core\Database;
use Arya\Models\TokenMeta;
use Arya\Services\MetaCloud;

$wabaId = trim((string) (getenv('WA_WABA_ID') ?: ''));
$phoneNumberId = trim((string) (getenv('WA_PHONE_NUMBER_ID') ?: ''));
$token = trim((string) (getenv('WA_TOKEN') ?: ''));

if ($wabaId === '' || $phoneNumberId === '' || $token === '') {
    echo "Define WA_WABA_ID, WA_PHONE_NUMBER_ID y WA_TOKEN en el entorno.\n";
    exit(1);
}

$pdo = Database::connection();
if (!$pdo) {
    echo "NO DB\n";
    exit(1);
}

$id = (int) $pdo->query(
    "SELECT id FROM token_meta WHERE canal='whatsapp' AND activo=TRUE ORDER BY id DESC LIMIT 1"
)->fetchColumn();

if ($id > 0) {
    $pdo->prepare(
        'UPDATE token_meta
         SET page_id = :page_id, identificador = :identificador, token = :token,
             estado = \'conectado\', updated_at = NOW()
         WHERE id = :id'
    )->execute([
        'page_id' => $wabaId,
        'identificador' => $phoneNumberId,
        'token' => $token,
        'id' => $id,
    ]);
    echo "UPDATED id={$id}\n";
} else {
    $stmt = $pdo->prepare(
        'INSERT INTO token_meta (canal, page_id, identificador, token, estado, activo, updated_at)
         VALUES (\'whatsapp\', :page_id, :identificador, :token, \'conectado\', TRUE, NOW()) RETURNING id'
    );
    $stmt->execute([
        'page_id' => $wabaId,
        'identificador' => $phoneNumberId,
        'token' => $token,
    ]);
    echo 'INSERTED id=' . $stmt->fetchColumn() . "\n";
}

$creds = TokenMeta::whatsappCredentials();
echo 'phone_number_id=' . ($creds['phone_number_id'] ?? '') . "\n";

$result = MetaCloud::sendText('573133202689', 'Prueba Arya OK');
echo json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
