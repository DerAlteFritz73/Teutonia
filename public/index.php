<?php

use App\Kernel;

error_log('Symfony front controller called - REQUEST_URI: ' . $_SERVER['REQUEST_URI']);

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    error_log('Kernel instantiation - ENV: ' . $context['APP_ENV']);
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
