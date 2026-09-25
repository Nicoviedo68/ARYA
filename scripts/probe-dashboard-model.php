<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost:8080';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTPS'] = 'off';

require dirname(__DIR__) . '/app/bootstrap.php';

use Arya\Models\Dashboard;

echo "=== stats ===\n";
echo json_encode(Dashboard::stats(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

echo "\n=== chart ===\n";
echo json_encode(Dashboard::chartConversations(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

echo "\n=== inbox ===\n";
foreach (Dashboard::recentInbox(5) as $c) {
    echo $c['id'] . '|' . $c['channel'] . '|' . $c['client'] . '|' . $c['preview'] . '|' . $c['time'] . "\n";
}

echo "\n=== tasks ===\n";
echo 'count=' . count(Dashboard::tasks()) . "\n";
