<?php

declare(strict_types=1);

if (!function_exists('apcu_enabled') || !apcu_enabled()) {
    require __DIR__ . '/../../../composer/autoload_classmap.php';
    return;
}

require __DIR__ . '/src/Resolver.php';
YRV\Autoloader\Resolver::init(__DIR__ . '/../../../', 12);

