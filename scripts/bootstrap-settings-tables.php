<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost:8080';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTPS'] = 'off';

require dirname(__DIR__) . '/app/bootstrap.php';

use Arya\Models\Integration;
use Arya\Models\TokenMeta;
use Arya\Models\TokenRedes;

TokenMeta::bootstrap();
TokenRedes::bootstrap();
Integration::bootstrap();

echo 'token_meta=' . (TokenMeta::tableExists() ? 'OK' : 'FAIL') . PHP_EOL;
echo 'token_redes=' . (TokenRedes::tableExists() ? 'OK' : 'FAIL') . PHP_EOL;
echo 'integraciones=' . (Integration::tableExists() ? 'OK' : 'FAIL') . PHP_EOL;
echo 'meta=' . count(TokenMeta::allActive()) . PHP_EOL;
echo 'redes=' . count(TokenRedes::allActive()) . PHP_EOL;
