<?php
declare(strict_types=1);

$vendor = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($vendor)) {
    require_once $vendor;
    return;
}

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'TonSamith\\Khqr\\Core\\' => dirname(__DIR__) . '/packages/core/src/',
        'TonSamith\\Khqr\\Bakong\\' => dirname(__DIR__) . '/packages/bakong/src/',
        'TonSamith\\Khqr\\Php\\' => dirname(__DIR__) . '/packages/php/src/',
    ];

    foreach ($prefixes as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $relative = substr($class, strlen($prefix));
            $path = $dir . str_replace('\\', '/', $relative) . '.php';
            if (is_file($path)) {
                require_once $path;
            }
            return;
        }
    }
});

