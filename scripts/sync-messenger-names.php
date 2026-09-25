<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost:8080';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTPS'] = 'off';

require dirname(__DIR__) . '/app/bootstrap.php';

use Arya\Core\Database;
use Arya\Models\Omnichannel;

$n = Omnichannel::syncMessengerDisplayNames();
echo "propagated_from_comments={$n}\n";

$pdo = Database::connection();
if (!$pdo) {
    echo "no_db\n";
    exit(1);
}

$rows = $pdo->query(
    "SELECT id, contact_name, channel, thread_kind, external_contact, phone
     FROM omni_conversations
     WHERE channel IN ('messenger', 'instagram')
     ORDER BY id"
)->fetchAll();

foreach ($rows as $r) {
    echo $r['id'] . '|' . $r['thread_kind'] . '|' . $r['contact_name']
        . '|ext=' . $r['external_contact'] . '|phone=' . $r['phone'] . "\n";
}
