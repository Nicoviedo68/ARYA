<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost:8080';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTPS'] = 'off';

require dirname(__DIR__) . '/app/bootstrap.php';

use Arya\Models\Dashboard;

echo "clients=" . json_encode(Dashboard::clientModule(), JSON_UNESCAPED_UNICODE) . "\n\n";
echo "tasks=" . json_encode(Dashboard::taskModule(), JSON_UNESCAPED_UNICODE) . "\n\n";
echo "generator=" . json_encode(Dashboard::generatorModule(), JSON_UNESCAPED_UNICODE) . "\n\n";
echo "reports=" . json_encode(Dashboard::reportKpis(), JSON_UNESCAPED_UNICODE) . "\n";
