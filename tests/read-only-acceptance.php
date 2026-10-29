<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use TonSamith\Khqr\Bakong\BakongMd5Verifier;
use TonSamith\Khqr\Bakong\BakongResponseMapper;
use TonSamith\Khqr\Bakong\Contracts\BakongTransportInterface;
use TonSamith\Khqr\Core\Exception\InvalidPayloadException;
use TonSamith\Khqr\Core\ExpectedPaymentFromQr;
use TonSamith\Khqr\Core\InspectionOutcome;
use TonSamith\Khqr\Core\Khqr;
use TonSamith\Khqr\Core\PaymentRequest;
use TonSamith\Khqr\Core\ReadOnlyPaymentInspector;

$passed = 0;
$failed = 0;

function ensureAcceptance(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

function scenarioAcceptance(string $name, callable $test): void
{
    global $passed, $failed;
    try {
        $test();
        $passed++;
        echo '[PASS] ' . $name . PHP_EOL;
    } catch (Throwable $exception) {
        $failed++;
        fwrite(STDERR, '[FAIL] ' . $name . ': ' . $exception->getMessage() . PHP_EOL);
    }
}

function assertQrRejected(callable $action): void
{
    try {
        $action();
    } catch (InvalidPayloadException $exception) {
        return;
    }
    throw new RuntimeException('Invalid QR must be rejected.');
}

function fixtureQr(): array
{
    $expected = [
        'order' => 'TEST-QR-008',
        'recipient' => 'receiver@bank',
        'amount' => 125,
        'currency' => 'KHR',
    ];
    $qr = (new Khqr())->generate(new PaymentRequest(
        accountId: $expected['recipient'],
        merchantName: 'Test Merchant',
        merchantCity: 'Battambang',
        currency: $expected['currency'],
        amountMinor: $expected['amount'],
        reference: $expected['order'],
    ));
    $expected['payload'] = $qr->payload;
    $expected['md5'] = $qr->md5;
    return $expected;
}

function fakeProvider(array $observation): ReadOnlyPaymentInspector
{
    $transport = new class(json_encode($observation, JSON_THROW_ON_ERROR)) implements BakongTransportInterface {
        public function __construct(private readonly string $body) {}
        public function checkTransactionByMd5(string $md5): string
        {
            if (preg_match('/^[a-f0-9]{32}$/D', $md5) !== 1) {
                throw new RuntimeException('Unexpected lookup MD5.');
            }
            return $this->body;
        }
    };

    return new ReadOnlyPaymentInspector(new BakongMd5Verifier($transport, new BakongResponseMapper()));
}

function bankResponse(string $recipient = 'receiver@bank', string $currency = 'KHR', int $amount = 125, string $hash = ''): array
{
    return [
        'responseCode' => 0,
        'responseMessage' => 'Getting transaction successfully.',
        'data' => [
            'hash' => $hash ?: str_repeat('a', 64),
            'toAccountId' => $recipient,
            'fromAccountId' => 'payer@bank',
            'currency' => $currency,
            'amount' => $amount,
        ],
    ];
}

scenarioAcceptance('QR receipt expectation binds exact reference, bank account, KHR and amount', static function (): void {
    $p = fixtureQr();
    $expected = (new ExpectedPaymentFromQr())->fromPayload(
        $p['payload'], $p['order'], $p['recipient'], $p['amount'], $p['currency'],
    );
    ensureAcceptance($expected->lookupMd5 === $p['md5'], 'MD5 must be from original QR payload');
    ensureAcceptance($expected->amountMinor === 125, 'Exact KHR amount');
});

scenarioAcceptance('Reject wrong account, currency, amount and reference BEFORE bank call', static function (): void {
    $p = fixtureQr();
    $builder = new ExpectedPaymentFromQr();
    foreach ([
        [$p['order'], 'attacker@bank', 125, 'KHR'],
        [$p['order'], $p['recipient'], 126, 'KHR'],
        [$p['order'], $p['recipient'], 125, 'USD'],
        ['OTHER-ORDER', $p['recipient'], 125, 'KHR'],
    ] as $bad) {
        assertQrRejected(static fn () => $builder->fromPayload($p['payload'], ...$bad));
    }
});

scenarioAcceptance('Reject static and no-reference QR before checking provider', static function (): void {
    $builder = new ExpectedPaymentFromQr();
    $static = (new Khqr())->generate(new PaymentRequest('receiver@bank', 'Test Merchant', 'Battambang'));
    assertQrRejected(static fn () => $builder->fromPayload($static->payload, 'TEST-QR-008', 'receiver@bank', 125, 'KHR'));
    $withoutReference = (new Khqr())->generate(new PaymentRequest('receiver@bank', 'Test Merchant', 'Battambang', 'KHR', 125));
    assertQrRejected(static fn () => $builder->fromPayload($withoutReference->payload, 'TEST-QR-008', 'receiver@bank', 125, 'KHR'));
});

scenarioAcceptance('Unpaid QR remains pending and no payment state changes', static function (): void {
    $p = fixtureQr();
    $expected = (new ExpectedPaymentFromQr())->fromPayload($p['payload'], $p['order'], $p['recipient'], 125, 'KHR');
    $inspector = fakeProvider(['responseCode' => 1, 'errorCode' => 1, 'data' => null]);
    ensureAcceptance($inspector->inspect($expected) === InspectionOutcome::PENDING, 'Unpaid QR pending');
});

scenarioAcceptance('Provider confirmed matches, but result is explicitly NOT SETTLED', static function (): void {
    $p = fixtureQr();
    $expected = (new ExpectedPaymentFromQr())->fromPayload($p['payload'], $p['order'], $p['recipient'], 125, 'KHR');
    ensureAcceptance(
        fakeProvider(bankResponse())->inspect($expected) === InspectionOutcome::MATCHED_NOT_SETTLED,
        'Read-only match',
    );
    ensureAcceptance(
        fakeProvider(bankResponse())->inspect($expected) === InspectionOutcome::MATCHED_NOT_SETTLED,
        'Repeated matches still must not claim settlement',
    );
});

scenarioAcceptance('Mismatched recipient, amount or currency always needs review', static function (): void {
    $p = fixtureQr();
    $expected = (new ExpectedPaymentFromQr())->fromPayload($p['payload'], $p['order'], $p['recipient'], 125, 'KHR');
    foreach ([
        bankResponse(recipient: 'wrong@bank'),
        bankResponse(amount: 126),
        bankResponse(currency: 'USD'),
    ] as $receipt) {
        ensureAcceptance(fakeProvider($receipt)->inspect($expected) === InspectionOutcome::REVIEW, 'Provider mismatch');
    }
});

scenarioAcceptance('Malformed receipt, ambiguous status and expired token cannot imply success', static function (): void {
    $p = fixtureQr();
    $expected = (new ExpectedPaymentFromQr())->fromPayload($p['payload'], $p['order'], $p['recipient'], 125, 'KHR');
    foreach ([
        ['responseCode' => 0, 'data' => ['hash' => 'broken']],
        ['responseCode' => 0, 'data' => array_merge(bankResponse()['data'], ['status' => 'PENDING'])],
        ['responseCode' => 401, 'errorCode' => 401, 'data' => null],
        ['responseCode' => 2, 'errorCode' => 9, 'data' => null],
    ] as $receipt) {
        ensureAcceptance(fakeProvider($receipt)->inspect($expected) === InspectionOutcome::REVIEW, 'Malformed/ambiguous/provider failure');
    }
});

scenarioAcceptance('USD amount match uses exact minor units', static function (): void {
    $result = (new Khqr())->generate(new PaymentRequest('receiver@bank', 'Test Merchant', 'Battambang', 'USD', 101, 'TEST-USD-010'));
    $expected = (new ExpectedPaymentFromQr())->fromPayload($result->payload, 'TEST-USD-010', 'receiver@bank', 101, 'USD');
    $match = bankResponse(currency: 'USD', amount: 1);
    $match['data']['amount'] = '1.01';
    ensureAcceptance(fakeProvider($match)->inspect($expected) === InspectionOutcome::MATCHED_NOT_SETTLED, '1.01 USD exact');
    $match['data']['amount'] = '1.02';
    ensureAcceptance(fakeProvider($match)->inspect($expected) === InspectionOutcome::REVIEW, '1.02 USD rejected');
});

echo sprintf('Read-only payment acceptance: %d passed, %d failed', $passed, $failed) . PHP_EOL;
exit($failed === 0 ? 0 : 1);

