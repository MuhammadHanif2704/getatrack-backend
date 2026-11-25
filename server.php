<?php

$publicPath = __DIR__ . '/public';
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');

if (str_starts_with($uri, '/storage/') && file_exists($publicPath . $uri) && !is_dir($publicPath . $uri)) {
    $filePath = $publicPath . $uri;
    $mime = mime_content_type($filePath) ?: 'application/octet-stream';
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
}

if ($uri !== '/' && file_exists($publicPath . $uri)) {
    return false;
}

require_once $publicPath . '/index.php';