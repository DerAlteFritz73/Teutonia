<?php

// For PHP built-in server: serve static files directly, route everything else through Symfony
$path = $_SERVER['REQUEST_URI'];
$file = __DIR__ . parse_url($path, PHP_URL_PATH);

if (is_file($file)) {
    return false; // Let PHP serve the static file
}

require __DIR__ . '/index.php';
