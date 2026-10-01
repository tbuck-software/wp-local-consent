<?php
// Router for PHP's development server. Never used on production.
$root = $_SERVER['DOCUMENT_ROOT'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/' && is_file($root . $path)) {
    return false;
}
if (is_dir($root . $path) && is_file($root . rtrim($path, '/') . '/index.php')) {
    require $root . rtrim($path, '/') . '/index.php';
    return true;
}
require $root . '/index.php';
