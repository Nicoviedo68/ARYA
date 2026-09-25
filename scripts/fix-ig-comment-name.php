<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost:8080';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTPS'] = 'off';

require dirname(__DIR__) . '/app/bootstrap.php';

use Arya\Core\Database;
use Arya\Models\Omnichannel;

Omnichannel::syncMessengerDisplayNames();

$pdo = Database::connection();
$pdo->exec(
    "UPDATE omni_conversations
     SET contact_name = '@nico_oviedo68', updated_at = NOW()
     WHERE channel = 'instagram'
       AND (
            phone = '893650119991448'
         OR external_contact = '893650119991448'
         OR comment_id = '18097707658977755'
         OR external_contact LIKE 'cmt:18097707658977755'
       )
       AND (contact_name IS NULL OR contact_name LIKE 'Usuario %' OR contact_name = 'nico_oviedo68')"
);

$rows = $pdo->query(
    "SELECT id, channel, thread_kind, contact_name, preview, comment_id
     FROM omni_conversations
     WHERE channel = 'instagram'
     ORDER BY id"
)->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
}
