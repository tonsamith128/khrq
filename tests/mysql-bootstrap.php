<?php

declare(strict_types=1);

/**
 * Test-only DB bootstrap. No production connection is permitted.
 * Requires explicit isolated CI configuration.
 */
if (getenv('KHQR_ACCEPT_ISOLATED_TEST_DB') !== 'YES') {
    throw new RuntimeException('Isolated MySQL test opt-in flag missing.');
}

$databaseName = getenv('KHQR_TEST_MYSQL_DATABASE');
if ($databaseName !== 'khqr_test') {
    throw new RuntimeException('Tests require the exact isolated database khqr_test.');
}

$vendor = getenv('KHQR_LARAVEL_VENDOR');
if (!is_string($vendor) || !is_file($vendor . '/autoload.php')) {
    throw new RuntimeException('Test Illuminate vendor installation not found.');
}

require_once $vendor . '/autoload.php';
require_once __DIR__ . '/bootstrap.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'TonSamith\\Khqr\\Laravel\\';
    if (str_starts_with($class, $prefix)) {
        $path = dirname(__DIR__) . '/packages/laravel/src/'
            . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

        if (is_file($path)) {
            require_once $path;
        }
    }
});

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\Facade;

function mysqlTestConnection(): \Illuminate\Database\Connection
{
    $capsule = new Capsule();
    $capsule->addConnection([
        'driver' => 'mysql',
        'host' => getenv('KHQR_TEST_MYSQL_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('KHQR_TEST_MYSQL_PORT') ?: '3306'),
        'database' => 'khqr_test',
        'username' => getenv('KHQR_TEST_MYSQL_USER') ?: 'khqr_test',
        'password' => getenv('KHQR_TEST_MYSQL_PASSWORD') ?: '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    $container = $capsule->getContainer();
    $container->instance('db.schema', $capsule->getConnection()->getSchemaBuilder());
    Facade::setFacadeApplication($container);

    return $capsule->getConnection();
}

