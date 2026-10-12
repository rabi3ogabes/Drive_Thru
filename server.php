<?php

/**
 * Router for PHP's built-in web server in local development:
 *   php -d max_execution_time=300 -S 127.0.0.1:8000 -t public server.php
 * Serves real files from public/ directly and sends everything else to Laravel.
 * Not used in production (nginx/Apache point at public/index.php).
 */
$publicPath = __DIR__.'/public';

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '');

if ($uri !== '/' && file_exists($publicPath.$uri)) {
    return false;
}

require_once $publicPath.'/index.php';
