<?php

declare(strict_types=1);

/**
 * Private MySQL race worker. Invoked only by tests/mysql-ledger.php.
 * No provider API calls. All data is synthetic.
 */
require_once __DIR__ . '/mysql-bootstrap.php';

use Illuminate\Database\QueryException;
use TonSamith\Khqr\Core\TransactionLookup;
use TonSamith\Khqr\Core\TransactionStatus;
use TonSamith\Khqr\Laravel\Storage\LaravelPaymentStore;

if ($argc !== 6) {
    throw new RuntimeException('Expected order, QR MD5, transaction hash, barrier and ready path.');
}

[$script, $order, $md5, $hash, $barrier, $ready] = $argv;
if (!str_starts_with($barrier, sys_get_temp_dir() . '/khqr-test-')) {
    throw new RuntimeException('Unexpected race-test barrier path.');
}

$store = new LaravelPaymentStore(mysqlTestConnection());
if (@file_put_contents($ready, 'ready') !== 5) {
    throw new RuntimeException('Could not create ready marker.');
}

$deadline = microtime(true) + 12;
while (!is_file($barrier) && microtime(true) < $deadline) {
    usleep(20000);
}

if (!is_file($barrier)) {
    throw new RuntimeException('Test race barrier was not released.');
}

try {
    $result = $store->settleOnce($order, $md5, new TransactionLookup(
        TransactionStatus::CONFIRMED, $hash, 'merchant@bank', 1250, 'USD',
    ));
    echo $result ? "NEW\n" : "ALREADY\n";
} catch (QueryException $exception) {
    // Only a real database uniqueness collision is expected in race test #2.
    $errorInfo = $exception->errorInfo;
    if ((string) ($errorInfo[0] ?? '') === '23000' &&
        (int) ($errorInfo[1] ?? 0) === 1062) {
        echo "COLLISION\n";
    } else {
        throw $exception;
    }
}

