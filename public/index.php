<?php

use App\Kernel;

// Sync process environment into $_SERVER so DotEnv respects APP_ENV set by Panther/CLI
foreach (['APP_ENV', 'APP_DEBUG'] as $_envKey) {
    if (!isset($_SERVER[$_envKey]) && false !== ($v = getenv($_envKey))) {
        $_SERVER[$_envKey] = $v;
    }
}

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
