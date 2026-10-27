<?php

declare(strict_types=1);

/**
 * Optional CI-only Illuminate SQLite integration smoke (not a bank test).
 * Installed vendor dir is supplied by GH Actions, never bundled in SDK.
 */
$vendor = getenv('KHQR_LARAVEL_VENDOR');
if (!is_string($vendor) || !is_file($vendor . '/autoload.php')) {
    throw new RuntimeException('KHQR_LARAVEL_VENDOR must point to Illuminate vendor directory.');
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
use TonSamith\Khqr\Core\ExpectedPayment;
use TonSamith\Khqr\Core\TransactionLookup;
use TonSamith\Khqr\Core\TransactionStatus;
use TonSamith\Khqr\Laravel\Storage\LaravelPaymentStore;

function must(bool $value, string $message): void
{
    if (!$value) {
        throw new RuntimeException($message);
    }
}

$database = new Capsule();
$database->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$database->setAsGlobal();
$database->bootEloquent();

$container = $database->getContainer();
$container->instance('db.schema', $database->getConnection()->getSchemaBuilder());
Facade::setFacadeApplication($container);

$migration = require dirname(__DIR__)
    . '/packages/laravel/database/migrations/create_khqr_payment_requests_table.php';
$migration->up();

$store = new LaravelPaymentStore($database->getConnection());
$expected = new ExpectedPayment('ORDER-1', str_repeat('a', 32), 'merchant@bank', 1250, 'USD');
$store->createPending($expected);
$persisted = $store->loadExpected('ORDER-1');
must($persisted->amountMinor === 1250, 'Expected amount persisted');
must($persisted->recipientAccountId === 'merchant@bank', 'Recipient persisted');
$receipt = new TransactionLookup(
    TransactionStatus::CONFIRMED, str_repeat('b', 64), 'merchant@bank', 1250, 'USD'
);
must($store->settleOnce('ORDER-1', $expected->lookupMd5, $receipt), 'First settlement changes state');
must(!$store->settleOnce('ORDER-1', $expected->lookupMd5, $receipt), 'Replay stays idempotent');

$row = $database->getConnection()->table('khqr_payment_requests')->where('order_reference', 'ORDER-1')->first();
must($row->status === 'paid', 'Ledger records paid once');

$second = new ExpectedPayment('ORDER-2', str_repeat('c', 32), 'merchant@bank', 1250, 'USD');
$store->createPending($second);

try {
    $store->settleOnce('ORDER-2', $second->lookupMd5, $receipt);
    throw new RuntimeException('Reused transfer hash must be rejected');
} catch (\Illuminate\Database\QueryException $expectedException) {
    // DB-level unique transaction hash protection.
}

try {
    $store->settleOnce('ORDER-2', $second->lookupMd5, new TransactionLookup(
        TransactionStatus::CONFIRMED, str_repeat('d', 64), 'wrong@bank', 1250, 'USD'
    ));
    throw new RuntimeException('Wrong recipient must be rejected');
} catch (RuntimeException $expectedException) {
    must(str_contains($expectedException->getMessage(), 'mismatch'), 'Correct mismatch exception');
}

$row2 = $database->getConnection()->table('khqr_payment_requests')->where('order_reference', 'ORDER-2')->first();
must($row2->status === 'pending', 'Rejected payment has no ledger effect');

echo "Laravel SQLite ledger acceptance: PASS\n";
