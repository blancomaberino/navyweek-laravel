<?php

/**
 * Local dev-server router — a copy of the framework's
 * Illuminate/Foundation/resources/server.php with ONE change: it hands off to the
 * built-in server only for real FILES (`is_file`), where the framework's copy uses
 * `file_exists`.
 *
 * `file_exists()` is also true for a DIRECTORY, so under plain `artisan serve` any
 * URL whose path matches a folder under public/ never reaches Laravel — PHP's
 * built-in server tries to serve the directory itself and returns its own 404.
 *
 * On this site that silently broke `/authors/`: public/authors/ exists to hold the
 * two byline portraits (/authors/t-alford.jpg), so the family root 404'd locally
 * while the application — and production, which serves through public/index.php —
 * correctly 301s it to "/". It is the only public/ directory that collides with a
 * page family, but the failure looked exactly like a routing bug.
 *
 * Lives at the app root ON PURPOSE: `artisan serve` prefers `base_path('server.php')`
 * over the framework's copy (ServeCommand.php:211), so simply existing here fixes
 * `php artisan serve`, `composer run dev`, and Dusk alike — no special invocation
 * to remember and no way to accidentally use the broken router.
 */
$publicPath = __DIR__.'/public';

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

if ($uri !== '/' && is_file($publicPath.$uri)) {
    return false;
}

$formattedDateTime = date('D M j H:i:s Y');

$requestMethod = $_SERVER['REQUEST_METHOD'];
$remoteAddress = $_SERVER['REMOTE_ADDR'].':'.$_SERVER['REMOTE_PORT'];

file_put_contents('php://stdout', "[$formattedDateTime] $remoteAddress [$requestMethod] URI: $uri\n");

require_once $publicPath.'/index.php';
