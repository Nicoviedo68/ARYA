<?php
/**
 * Acceso directo al dashboard (por si el router falla).
 * URL: /Arya/public/dashboard.php
 */
$_GET['r'] = '/dashboard';
require __DIR__ . '/index.php';
