<?php

declare(strict_types=1);

/**
 * Front controller Arya CRM
 *
 * URLs soportadas (sin depender solo de mod_rewrite):
 *   /Arya/public/index.php
 *   /Arya/public/index.php/dashboard
 *   /Arya/public/index.php?r=/dashboard
 *   /Arya/public/dashboard  (si mod_rewrite está activo)
 */

$router = require dirname(__DIR__) . '/app/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri    = \Arya\Core\Router::resolveRequestUri();

$router->dispatch($method, $uri);
