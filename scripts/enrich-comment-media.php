<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost:8080';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTPS'] = 'off';

require dirname(__DIR__) . '/app/bootstrap.php';

use Arya\Models\Omnichannel;

$id = (int) ($argv[1] ?? 7);
Omnichannel::bootstrap();
Omnichannel::enrichCommentMediaIfNeeded($id);
$c = Omnichannel::findConversation($id);
echo 'id=' . $id . PHP_EOL;
echo 'kind=' . ($c['thread_kind'] ?? '') . PHP_EOL;
echo 'has_media=' . ((!empty($c['post_media_url'])) ? 'YES' : 'NO') . PHP_EOL;
echo 'has_link=' . ((!empty($c['post_permalink'])) ? 'YES' : 'NO') . PHP_EOL;
echo 'post_id=' . ($c['post_id'] ?? '') . PHP_EOL;
