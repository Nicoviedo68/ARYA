<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost:8080';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTPS'] = 'off';

require dirname(__DIR__) . '/app/bootstrap.php';

use Arya\Core\Database;

$pdo = Database::connection();
if (!$pdo) {
    echo "no_db\n";
    exit(1);
}

echo "=== inbox comments / ig ===\n";
$rows = $pdo->query(
    "SELECT id, object_type, canal, event_type, page_id, processed_at, created_at, payload
     FROM meta_webhook_inbox
     WHERE event_type IN ('comments', 'live_comments', 'feed', 'mentions')
        OR canal = 'instagram'
     ORDER BY id DESC
     LIMIT 15"
)->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $r) {
    $payload = $r['payload'];
    if (is_string($payload)) {
        $payload = json_decode($payload, true) ?: [];
    }
    $change = is_array($payload) ? ($payload['changes'][0] ?? null) : null;
    $value = is_array($change) ? ($change['value'] ?? null) : null;
    if (is_string($value)) {
        $value = json_decode($value, true) ?: $value;
    }
    unset($r['payload']);
    echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    if (is_array($value)) {
        echo '  field=' . ($change['field'] ?? '') . "\n";
        echo '  keys=' . implode(',', array_keys($value)) . "\n";
        echo '  id=' . ($value['id'] ?? '') . ' comment_id=' . ($value['comment_id'] ?? '') . "\n";
        echo '  text=' . substr((string) ($value['text'] ?? $value['message'] ?? ''), 0, 80) . "\n";
        echo '  from=' . json_encode($value['from'] ?? null, JSON_UNESCAPED_UNICODE) . "\n";
        echo '  media=' . json_encode($value['media'] ?? null, JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "  value_type=" . gettype($value) . "\n";
        echo '  payload_keys=' . (is_array($payload) ? implode(',', array_keys($payload)) : 'n/a') . "\n";
    }
    echo "---\n";
}

echo "\n=== omni instagram ===\n";
$c = $pdo->query(
    "SELECT id, channel, thread_kind, contact_name, preview, comment_id, phone, external_contact, created_at
     FROM omni_conversations
     WHERE channel = 'instagram'
     ORDER BY id DESC
     LIMIT 20"
)->fetchAll(PDO::FETCH_ASSOC);
foreach ($c as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
}

echo "\n=== pending inbox ===\n";
$p = $pdo->query(
    "SELECT COUNT(*) FROM meta_webhook_inbox WHERE processed_at IS NULL"
)->fetchColumn();
echo "pending={$p}\n";
