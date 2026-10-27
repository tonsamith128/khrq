<?php

declare(strict_types=1);

/**
 * MySQL 8.0 integration acceptance, with REAL concurrent PHP processes
 * against an isolated ephemeral GitHub Actions MySQL service.
 * Never run against customer/production databases.
 */
require_once __DIR__ . '/mysql-bootstrap.php';

use Illuminate\Database\QueryException;
use TonSamith\Khqr\Core\ExpectedPayment;
use TonSamith\Khqr\Core\TransactionLookup;
use TonSamith\Khqr\Core\TransactionStatus;
use TonSamith\Khqr\Laravel\Storage\LaravelPaymentStore;

function assertMysql(bool $assertion, string $description): void
{
    if (!$assertion) {
        throw new RuntimeException('MySQL acceptance failed: ' . $description);
    }
}

function raceWorkers(array $requests): array
{
    $dir = sys_get_temp_dir() . '/khqr-test-' . bin2hex(random_bytes(8));
    if (!mkdir($dir, 0700)) {
        throw new RuntimeException('Could not create private race directory.');
    }

    $barrier = $dir . '/GO';
    $processes = [];
    try {
        foreach ($requests as $index => $request) {
            $ready = $dir . '/ready-' . $index;
            $args = [
                PHP_BINARY,
                __DIR__ . '/mysql-worker.php',
                $request['reference'],
                $request['md5'],
                $request['hash'],
                $barrier,
                $ready,
            ];
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $pipes = [];
            $process = proc_open($args, $descriptors, $pipes, dirname(__DIR__));
            if (!is_resource($process)) {
                throw new RuntimeException('Could not start MySQL race worker.');
            }

            fclose($pipes[0]);
            $processes[] = ['process' => $process, 'pipes' => $pipes, 'ready' => $ready];
        }

        $deadline = microtime(true) + 20;
        do {
            $readyCount = count(array_filter($processes, static fn (array $p): bool => is_file($p['ready'])));
            if ($readyCount === count($processes)) {
                break;
            }
            usleep(50000);
        } while (microtime(true) < $deadline);

        if ($readyCount !== count($processes)) {
            throw new RuntimeException('One or more workers did not reach the race barrier.');
        }

        if (@file_put_contents($barrier, 'GO') !== 2) {
            throw new RuntimeException('Could not release the race barrier.');
        }

        $outcomes = [];
        foreach ($processes as $worker) {
            $output = trim(stream_get_contents($worker['pipes'][1]));
            $error = trim(stream_get_contents($worker['pipes'][2]));
            fclose($worker['pipes'][1]);
            fclose($worker['pipes'][2]);
            $exit = proc_close($worker['process']);

            if ($exit !== 0) {
                throw new RuntimeException('MySQL race worker exited ' . $exit . ': ' . $error);
            }
            $outcomes[] = $output;
        }

        sort($outcomes);
        return $outcomes;
    } finally {
        @unlink($barrier);
        foreach ($processes as $worker) {
            @unlink($worker['ready']);
        }
        @rmdir($dir);
    }
}

$connection = mysqlTestConnection();
$database = (string) $connection->getDatabaseName();
assertMysql($database === 'khqr_test', 'Must use isolated khqr_test database');

// Destroy ONLY the isolated test table in ephemeral GitHub Actions database.
$connection->getSchemaBuilder()->dropIfExists('khqr_payment_requests');
$migration = require dirname(__DIR__) . '/packages/laravel/database/migrations/create_khqr_payment_requests_table.php';
$migration->up();

$store = new LaravelPaymentStore($connection);
$hash = str_repeat('b', 64);

// Same order, identical confirmed transaction, two real PHP workers.
$a = new ExpectedPayment('CONCURRENT-ORDER-A', str_repeat('a', 32), 'merchant@bank', 1250, 'USD');
$store->createPending($a);
$outcomes = raceWorkers([
    ['reference' => $a->orderReference, 'md5' => $a->lookupMd5, 'hash' => $hash],
    ['reference' => $a->orderReference, 'md5' => $a->lookupMd5, 'hash' => $hash],
]);
assertMysql($outcomes === ['ALREADY', 'NEW'], 'Concurrent same-order settlement must occur exactly once');

$paid = $connection->table('khqr_payment_requests')->where('order_reference', $a->orderReference)->first();
assertMysql($paid->status === 'paid' && $paid->transaction_hash === $hash, 'First order settled once');

// Same transfer hash cannot settle two DIFFERENT orders concurrently.
$b = new ExpectedPayment('CONCURRENT-ORDER-B', str_repeat('c', 32), 'merchant@bank', 1250, 'USD');
$c = new ExpectedPayment('CONCURRENT-ORDER-C', str_repeat('d', 32), 'merchant@bank', 1250, 'USD');
$store->createPending($b);
$store->createPending($c);
$sharedHash = str_repeat('e', 64);
$outcomes = raceWorkers([
    ['reference' => $b->orderReference, 'md5' => $b->lookupMd5, 'hash' => $sharedHash],
    ['reference' => $c->orderReference, 'md5' => $c->lookupMd5, 'hash' => $sharedHash],
]);
assertMysql($outcomes === ['COLLISION', 'NEW'], 'Transfer hash must be unique across orders');
$settledCount = $connection->table('khqr_payment_requests')->where('transaction_hash', $sharedHash)->count();
assertMysql($settledCount === 1, 'No duplicate transaction settlement across orders');

// Wrong QR MD5 and recipient mismatch cannot change an order.
$d = new ExpectedPayment('CONCURRENT-ORDER-D', str_repeat('f', 32), 'merchant@bank', 1250, 'USD');
$store->createPending($d);
$receipt = new TransactionLookup(TransactionStatus::CONFIRMED, str_repeat('1', 64), 'merchant@bank', 1250, 'USD');

try {
    $store->settleOnce($d->orderReference, str_repeat('0', 32), $receipt);
    throw new RuntimeException('Unexpected settlement with wrong lookup MD5.');
} catch (RuntimeException $exception) {
    assertMysql(str_contains($exception->getMessage(), 'mismatch'), 'Wrong MD5 error');
}

try {
    $store->settleOnce($d->orderReference, $d->lookupMd5, new TransactionLookup(
        TransactionStatus::CONFIRMED, str_repeat('2', 64), 'other@bank', 1250, 'USD',
    ));
    throw new RuntimeException('Unexpected settlement with wrong recipient.');
} catch (RuntimeException $exception) {
    assertMysql(str_contains($exception->getMessage(), 'mismatch'), 'Wrong recipient error');
}

$stillPending = $connection->table('khqr_payment_requests')->where('order_reference', $d->orderReference)->first();
assertMysql($stillPending->status === 'pending' && $stillPending->transaction_hash === null, 'Failed transfers rolled back');

echo "MySQL 8 acceptance: concurrent same-order replay, cross-order transaction collision and negative cases PASS\n";

