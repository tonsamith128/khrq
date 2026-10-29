<?php

declare(strict_types=1);

/**
 * Synthetic provider -> full reconciliation -> SQLite ledger acceptance.
 * No bank, token, network request, or production database involved.
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
use Illuminate\Database\QueryException;
use TonSamith\Khqr\Bakong\BakongMd5Verifier;
use TonSamith\Khqr\Bakong\Contracts\BakongTransportInterface;
use TonSamith\Khqr\Core\ExpectedPaymentFromQr;
use TonSamith\Khqr\Core\Khqr;
use TonSamith\Khqr\Core\PaymentRequest;
use TonSamith\Khqr\Core\ReconciliationDecision;
use TonSamith\Khqr\Core\ReconciliationService;
use TonSamith\Khqr\Laravel\Storage\LaravelPaymentStore;

function assertLifecycle(bool $ok, string $description): void
{
    if (!$ok) {
        throw new RuntimeException('Lifecycle acceptance failed: ' . $description);
    }
}

final class SyntheticBakongTransport implements BakongTransportInterface
{
    /** @var array<string, string> */
    private array $responses = [];

    public function put(string $md5, array $response): void
    {
        $this->responses[$md5] = json_encode($response, JSON_THROW_ON_ERROR);
    }

    public function checkTransactionByMd5(string $md5): string
    {
        if (!isset($this->responses[$md5])) {
            throw new RuntimeException('Synthetic provider fixture missing.');
        }
        return $this->responses[$md5];
    }
}

function successReceipt(string $hash, string $account, int $amount, string $currency = 'KHR'): array
{
    return [
        'responseCode' => 0,
        'data' => [
            'hash' => $hash,
            'toAccountId' => $account,
            'currency' => $currency,
            'amount' => $amount,
        ],
    ];
}

$database = new Capsule();
$database->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$database->setAsGlobal();
$database->bootEloquent();

$container = $database->getContainer();
$container->instance('db.schema', $database->getConnection()->getSchemaBuilder());
Facade::setFacadeApplication($container);

$migration = require dirname(__DIR__) .
    '/packages/laravel/database/migrations/create_khqr_payment_requests_table.php';
$migration->up();

$connection = $database->getConnection();
$store = new LaravelPaymentStore($connection);
$bank = new SyntheticBakongTransport();
$service = new ReconciliationService(new BakongMd5Verifier($bank), $store);
$builder = new ExpectedPaymentFromQr();
$qrBuilder = new Khqr();

$qr1 = $qrBuilder->generate(new PaymentRequest(
    'receiver@bank', 'Fixture Store', 'Battambang', 'KHR', 125, 'ORDER-QR-001',
));
$trusted1 = $builder->fromPayload($qr1->payload, 'ORDER-QR-001', 'receiver@bank', 125, 'KHR');
$store->createPending($trusted1);

$expected1 = $store->loadExpected('ORDER-QR-001');
assertLifecycle($expected1->lookupMd5 === $qr1->md5, 'Persisted QR lookup key');

$bank->put($expected1->lookupMd5, ['responseCode' => 1, 'errorCode' => 1]);
assertLifecycle($service->reconcile($expected1) === ReconciliationDecision::PENDING, 'Pending must not settle');

$bank->put($expected1->lookupMd5, successReceipt(str_repeat('a', 64), 'wrong@bank', 125));
assertLifecycle($service->reconcile($expected1) === ReconciliationDecision::REVIEW, 'Wrong recipient must not settle');

$bank->put($expected1->lookupMd5, successReceipt(str_repeat('a', 64), 'receiver@bank', 126));
assertLifecycle($service->reconcile($expected1) === ReconciliationDecision::REVIEW, 'Wrong amount must not settle');

$bank->put($expected1->lookupMd5, successReceipt(str_repeat('a', 64), 'receiver@bank', 125, 'USD'));
assertLifecycle($service->reconcile($expected1) === ReconciliationDecision::REVIEW, 'Wrong currency must not settle');

$before = $connection->table('khqr_payment_requests')->where('order_reference', 'ORDER-QR-001')->first();
assertLifecycle($before->status === 'pending' && $before->transaction_hash === null, 'Pending and review preserve ledger');

$transfer = str_repeat('a', 64);
$bank->put($expected1->lookupMd5, successReceipt($transfer, 'receiver@bank', 125));
assertLifecycle($service->reconcile($expected1) === ReconciliationDecision::SETTLED, 'Only complete matching receipt settles');
assertLifecycle($service->reconcile($expected1) === ReconciliationDecision::ALREADY_SETTLED, 'Replayed receipt is idempotent');

$after = $connection->table('khqr_payment_requests')->where('order_reference', 'ORDER-QR-001')->first();
assertLifecycle($after->status === 'paid' && $after->transaction_hash === $transfer, 'Atomic settlement persisted');
assertLifecycle($connection->table('khqr_payment_requests')->where('status', 'paid')->count() === 1, 'Exactly one paid record');

$qr2 = $qrBuilder->generate(new PaymentRequest(
    'receiver@bank', 'Fixture Store', 'Battambang', 'KHR', 125, 'ORDER-QR-002',
));
$trusted2 = $builder->fromPayload($qr2->payload, 'ORDER-QR-002', 'receiver@bank', 125, 'KHR');
$store->createPending($trusted2);
$expected2 = $store->loadExpected('ORDER-QR-002');

// A malicious provider fixture tries to reassign the SAME bank hash to order 2.
$bank->put($expected2->lookupMd5, successReceipt($transfer, 'receiver@bank', 125));
$collisionRejected = false;
try {
    $service->reconcile($expected2);
} catch (QueryException $error) {
    $collisionRejected = true;
}
assertLifecycle($collisionRejected, 'Same transfer cannot settle a different order');
$secondRow = $connection->table('khqr_payment_requests')->where('order_reference', 'ORDER-QR-002')->first();
assertLifecycle($secondRow->status === 'pending' && $secondRow->transaction_hash === null, 'Collision rolled back');

echo "Laravel synthetic payment lifecycle acceptance: PASS\n";

