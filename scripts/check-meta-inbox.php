<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost:8080';
$_SERVER['SCRIPT_NAME'] = '/index.php';

require dirname(__DIR__) . '/app/bootstrap.php';

use Arya\Core\Database;
use Arya\Services\MetaWebhook;

MetaWebhook::bootstrap();
$pdo = Database::connection();
if (!$pdo) {
    echo "ERROR: sin DB\n";
    exit(1);
}

$total = (int) $pdo->query('SELECT COUNT(*) FROM meta_webhook_inbox')->fetchColumn();
echo "TOTAL_EVENTOS={$total}\n\n";

$stmt = $pdo->query(
    'SELECT id, object_type, page_id, canal, event_type, created_at,
            left(payload::text, 280) AS payload_preview
     FROM meta_webhook_inbox
     ORDER BY created_at DESC
     LIMIT 10'
);
$rows = $stmt->fetchAll();

if (!$rows) {
    echo "INBOX vacío: aún no llegó ningún webhook POST de Meta.\n";
    exit(0);
}

foreach ($rows as $r) {
    echo "----- #{$r['id']} -----\n";
    echo 'created_at: ' . $r['created_at'] . "\n";
    echo 'canal: ' . ($r['canal'] ?? '-') . "\n";
    echo 'object: ' . ($r['object_type'] ?? '-') . "\n";
    echo 'page_id: ' . ($r['page_id'] ?? '-') . "\n";
    echo 'event: ' . ($r['event_type'] ?? '-') . "\n";
    echo 'payload: ' . ($r['payload_preview'] ?? '') . "\n\n";
}
