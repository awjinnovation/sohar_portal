<?php

/*
 * Router for `php artisan serve` (ServeCommand uses ./server.php when it
 * exists). Same as the framework's router, plus CORS headers on the public
 * images under /storage/* (uploads), /tenants/* (tenant branding) and
 * /images/* so Flutter web builds on another origin can load them — the
 * built-in server would otherwise send static files without any header.
 * The Docker image's NGINX does the same (docker/nginx/public-assets.conf).
 */

$publicPath = getcwd();

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');

$corsPrefix = null;
foreach (['/storage/', '/tenants/', '/images/'] as $prefix) {
    if (str_starts_with($uri, $prefix)) {
        $corsPrefix = rtrim($prefix, '/');
        break;
    }
}

if ($corsPrefix !== null && in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD', 'OPTIONS'], true)) {
    $file = realpath($publicPath.$uri);
    $root = realpath($publicPath.$corsPrefix);
    if ($file !== false && $root !== false && str_starts_with($file, $root.DIRECTORY_SEPARATOR) && is_file($file)) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
        header('Vary: Origin');
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);

            return true;
        }
        $types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif',
            'avif' => 'image/avif', 'svg' => 'image/svg+xml', 'pdf' => 'application/pdf'];
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        header('Content-Type: '.($types[$ext] ?? (mime_content_type($file) ?: 'application/octet-stream')));
        header('Content-Length: '.filesize($file));
        header('Cache-Control: public, max-age=86400');
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($file)).' GMT');
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            readfile($file);
        }

        return true;
    }
}

if ($uri !== '/' && file_exists($publicPath.$uri)) {
    return false;
}

file_put_contents('php://stdout', '['.date('D M j H:i:s Y').'] '.$_SERVER['REMOTE_ADDR'].':'.$_SERVER['REMOTE_PORT'].' ['.$_SERVER['REQUEST_METHOD']."] URI: $uri\n");

require_once $publicPath.'/index.php';
