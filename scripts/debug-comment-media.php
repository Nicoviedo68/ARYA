<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost:8080';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTPS'] = 'off';

require dirname(__DIR__) . '/app/bootstrap.php';

use Arya\Core\Database;
use Arya\Models\TokenMeta;
use Arya\Services\MetaCloud;

$pdo = Database::connection();
$row = $pdo->query(
    "SELECT raw FROM omni_messages WHERE conversation_id = 7 AND direction = 'inbound' ORDER BY id ASC LIMIT 1"
)->fetch();
$raw = json_decode((string) ($row['raw'] ?? '{}'), true) ?: [];
echo 'raw_keys=' . implode(',', array_keys($raw)) . PHP_EOL;
echo 'has_post_id=' . (isset($raw['post_id']) ? 'Y' : 'N') . PHP_EOL;
echo 'has_parent_id=' . (isset($raw['parent_id']) ? 'Y' : 'N') . PHP_EOL;
echo 'has_photo_id=' . (isset($raw['photo_id']) ? 'Y' : 'N') . PHP_EOL;
echo 'has_comment_id=' . (isset($raw['comment_id']) ? 'Y' : 'N') . PHP_EOL;
echo 'has_link=' . (isset($raw['link']) ? 'Y' : 'N') . PHP_EOL;

$creds = TokenMeta::channelCredentials('messenger');
echo 'token_page=' . ($creds['page_id'] ?? '') . PHP_EOL;

$candidates = array_values(array_filter([
    (string) ($raw['post_id'] ?? ''),
    (string) ($raw['parent_id'] ?? ''),
    (string) ($raw['photo_id'] ?? ''),
    (string) ($raw['comment_id'] ?? ''),
]));
if (!empty($raw['comment_id']) && str_contains((string) $raw['comment_id'], '_')) {
    $parts = explode('_', (string) $raw['comment_id']);
    $candidates[] = $parts[0];
    if (count($parts) >= 2) {
        $candidates[] = $parts[0] . '_' . $parts[1];
    }
}
$candidates = array_values(array_unique($candidates));

foreach ($candidates as $id) {
    $ctx = MetaCloud::fetchFacebookPostContext($id);
    echo 'try=' . $id . ' ok=' . ($ctx['ok'] ? 'Y' : 'N')
        . ' media=' . (!empty($ctx['media_url']) ? 'Y' : 'N')
        . ' msg=' . substr((string) ($ctx['message'] ?? ''), 0, 80)
        . PHP_EOL;
}

$ctx2 = MetaCloud::resolveCommentPostContext('messenger', $raw);
echo 'resolve_ok=' . ($ctx2['ok'] ? 'Y' : 'N') . ' media=' . (!empty($ctx2['media_url']) ? 'Y' : 'N')
    . ' msg=' . substr((string) ($ctx2['message'] ?? ''), 0, 100) . PHP_EOL;
