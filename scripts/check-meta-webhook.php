<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost:8080';
$_SERVER['SCRIPT_NAME'] = '/index.php';

require dirname(__DIR__) . '/app/bootstrap.php';

use Arya\Services\MetaWebhook;

MetaWebhook::bootstrap();

$token = MetaWebhook::verifyToken();

echo 'URL: ' . MetaWebhook::callbackUrl() . PHP_EOL;
echo 'VERIFY_OK: ' . ($token !== '' ? 'yes' : 'no') . PHP_EOL;
echo 'VERIFY_LEN: ' . strlen($token) . PHP_EOL;
echo 'INBOX: ' . MetaWebhook::inboxCount() . PHP_EOL;
