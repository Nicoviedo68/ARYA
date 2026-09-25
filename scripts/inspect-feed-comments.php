<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost:8080';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTPS'] = 'off';

require dirname(__DIR__) . '/app/bootstrap.php';

use Arya\Core\Database;
use Arya\Models\Omnichannel;

Omnichannel::bootstrap();
$pdo = Database::connection();
if (!$pdo) {
    echo "NO DB\n";
    exit(1);
}

echo "=== meta_webhook_inbox feed/comments ===\n";
$rows = $pdo->query(
    "SELECT id, canal, event_type, page_id, processed_at, created_at, payload
     FROM meta_webhook_inbox
     WHERE event_type IN ('feed','comments','live_comments')
        OR payload::text ILIKE '%comment%'
     ORDER BY id DESC
     LIMIT 8"
)->fetchAll();

foreach ($rows as $r) {
    echo "#{$r['id']} canal={$r['canal']} type={$r['event_type']} processed=" . ($r['processed_at'] ?: 'NULL') . "\n";
    $p = is_string($r['payload']) ? json_decode($r['payload'], true) : $r['payload'];
    echo "  keys=" . implode(',', array_keys(is_array($p) ? $p : [])) . "\n";
    if (is_array($p) && isset($p['changes'][0])) {
        $c = $p['changes'][0];
        echo "  field=" . ($c['field'] ?? '?') . "\n";
        $v = $c['value'] ?? [];
        if (is_array($v)) {
            echo "  value.item=" . ($v['item'] ?? '-') . " verb=" . ($v['verb'] ?? '-') . "\n";
            echo "  comment_id=" . ($v['comment_id'] ?? $v['id'] ?? '-') . "\n";
            echo "  message=" . substr((string) ($v['message'] ?? $v['text'] ?? ''), 0, 120) . "\n";
            echo "  from=" . json_encode($v['from'] ?? null, JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
    echo "\n";
}

echo "=== omni comment threads ===\n";
$convs = $pdo->query(
    "SELECT id, channel, thread_kind, comment_id, contact_name, preview, external_contact, last_message_at
     FROM omni_conversations
     WHERE thread_kind = 'comment' OR external_contact LIKE 'cmt:%' OR COALESCE(comment_id,'') <> ''
     ORDER BY id DESC
     LIMIT 20"
)->fetchAll();
if (!$convs) {
    echo "(ninguno)\n";
} else {
    foreach ($convs as $c) {
        echo "#{$c['id']} {$c['channel']} kind={$c['thread_kind']} name={$c['contact_name']} preview={$c['preview']}\n";
    }
}

echo "\n=== reprocess pending ===\n";
// force reprocess unprocessed + recently processed feed
$pdo->exec("UPDATE meta_webhook_inbox SET processed_at = NULL WHERE event_type = 'feed' OR event_type = 'comments'");
$res = Omnichannel::syncFromMetaInbox();
echo json_encode($res, JSON_UNESCAPED_UNICODE) . "\n";

$convs2 = $pdo->query(
    "SELECT id, channel, thread_kind, comment_id, contact_name, preview
     FROM omni_conversations
     WHERE thread_kind = 'comment' OR external_contact LIKE 'cmt:%'
     ORDER BY id DESC LIMIT 20"
)->fetchAll();
echo "comments after sync=" . count($convs2) . "\n";
foreach ($convs2 as $c) {
    echo "#{$c['id']} {$c['channel']} {$c['contact_name']} :: {$c['preview']}\n";
}
