<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use TonSamith\Khqr\Core\Crc16;
use TonSamith\Khqr\Core\Khqr;
use TonSamith\Khqr\Core\Money;
use TonSamith\Khqr\Core\PaymentRequest;
use TonSamith\Khqr\Core\Tlv;
use TonSamith\Khqr\Core\TransactionLookup;
use TonSamith\Khqr\Core\TransactionStatus;
use TonSamith\Khqr\Php\KhqrFactory;

$passes = 0;
$failures = 0;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function same(mixed $expected, mixed $actual, string $message): void
{
    check($expected === $actual, $message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
}

function throwsInvalid(callable $action): void
{
    try {
        $action();
    } catch (InvalidArgumentException $exception) {
        return;
    }
    throw new RuntimeException('Expected InvalidArgumentException.');
}

function test(string $name, callable $test): void
{
    global $passes, $failures;

    try {
        $test();
        $passes++;
        echo '[PASS] ' . $name . PHP_EOL;
    } catch (Throwable $exception) {
        $failures++;
        fwrite(STDERR, '[FAIL] ' . $name . ': ' . $exception->getMessage() . PHP_EOL);
    }
}

test('Independent CRC known vector', static function (): void {
    same('29B1', Crc16::checksum('123456789'), 'CRC-16/CCITT-FALSE');
});

test('TLV round trip and nested templates', static function (): void {
    $encoded = Tlv::encode('29', Tlv::encode('00', 'name@bank'));
    same(['29' => Tlv::encode('00', 'name@bank')], Tlv::decode($encoded), 'Nested account');
    same('name@bank', Tlv::decode(Tlv::decode($encoded)['29'])['00'], 'Account ID');
});

test('Reject malformed and duplicate TLVs', static function (): void {
    throwsInvalid(static fn () => Tlv::decode('0105abc'));
    throwsInvalid(static fn () => Tlv::decode('01xxabc'));
    throwsInvalid(static fn () => Tlv::decode('0101a0101b'));
    throwsInvalid(static fn () => Tlv::encode('abc', 'value'));
    throwsInvalid(static fn () => Tlv::encode('00', str_repeat('X', 100)));
});

test('KHR and USD integer minor units', static function (): void {
    same('116', Money::currencyCode('KHR'), 'KHR');
    same('840', Money::currencyCode('USD'), 'USD');
    same('10000', Money::formatMinor(10000, 'KHR'), 'KHR amount');
    same('100.00', Money::formatMinor(10000, 'USD'), 'USD amount');
    same('0.01', Money::formatMinor(1, 'USD'), 'USD cent');
    same(1050, Money::parseMinor('10.50', 'USD'), 'Parse USD');
    same(10000, Money::parseMinor('10000', 'KHR'), 'Parse KHR');
    throwsInvalid(static fn () => Money::parseMinor('10.50', 'KHR'));
    throwsInvalid(static fn () => Money::parseMinor('0', 'KHR'));
    throwsInvalid(static fn () => Money::parseMinor('1.234', 'USD'));
    throwsInvalid(static fn () => Money::formatMinor(-1, 'USD'));
});

test('Static QR with lookup ID', static function (): void {
    $qr = new Khqr();
    $result = $qr->generate(new PaymentRequest('name@bank', 'Demo Shop', 'Phnom Penh'));
    check(str_starts_with($result->payload, '00020101021129'), 'Static prefix');
    check(!isset($qr->decode($result->payload)['54']), 'No static amount');
    same(md5($result->payload), $result->md5, 'MD5 lookup key');
    check($qr->isValid($result->payload), 'Round-trip');
    same('11', $qr->decode($result->payload)['01'], 'Static method');
});

test('Dynamic KHR with reference', static function (): void {
    $qr = new Khqr();
    $result = $qr->generate(new PaymentRequest(
        accountId: 'name@bank',
        merchantName: 'Demo Shop',
        merchantCity: 'Battambang',
        amountMinor: 10000,
        reference: 'ORDER-1001',
    ));
    $decoded = $qr->decode($result->payload);
    same('12', $decoded['01'], 'Dynamic initiation');
    same('10000', $decoded['54'], 'KHR amount');
    same('116', $decoded['53'], 'KHR currency');
    same('ORDER-1001', Tlv::decode($decoded['62'])['01'], 'Reference');
});

test('Dynamic USD exact decimals', static function (): void {
    $qr = new Khqr();
    $result = $qr->generate(new PaymentRequest('name@bank', 'Demo Shop', 'Phnom Penh', 'USD', 1051));
    same('10.51', $qr->decode($result->payload)['54'], 'USD amount');
});

test('Reject edited payload and invalid CRC', static function (): void {
    $qr = new Khqr();
    $payload = $qr->generate(new PaymentRequest('name@bank', 'Demo Shop', 'Phnom Penh'))->payload;
    check(!$qr->isValid(substr($payload, 0, -1) . ($payload[-1] === 'A' ? 'B' : 'A')), 'Tampered CRC');
    throwsInvalid(static fn () => $qr->decode($payload . '0101x'));
});

test('Reject invalid request values', static function (): void {
    throwsInvalid(static fn () => new PaymentRequest('invalid', 'Shop', 'Phnom Penh'));
    throwsInvalid(static fn () => new PaymentRequest('name@bank', str_repeat('A', 26), 'Phnom Penh'));
    throwsInvalid(static fn () => new PaymentRequest('name@bank', 'Shop', 'Phnom Penh', 'KHR', 0));
    throwsInvalid(static fn () => new PaymentRequest('name@bank', 'Shop', 'Phnom Penh', 'EUR', 100));
});

test('Confirmed observation requires evidence', static function (): void {
    throwsInvalid(static fn () => new TransactionLookup(TransactionStatus::CONFIRMED));
    $observation = new TransactionLookup(TransactionStatus::PENDING);
    same(TransactionStatus::PENDING, $observation->status, 'Pending status');
});

test('Native PHP adapter creates core instance', static function (): void {
    check(KhqrFactory::make() instanceof Khqr, 'Factory');
});


test('Bakong response mapper: valid USD success', static function (): void {
    $mapper = new \TonSamith\Khqr\Bakong\BakongResponseMapper();
    $response = json_encode([
        'responseCode' => 0,
        'responseMessage' => 'Getting transaction successfully.',
        'data' => [
            'hash' => str_repeat('a', 64),
            'fromAccountId' => 'sender@bank',
            'toAccountId' => 'merchant@bank',
            'currency' => 'USD',
            'amount' => 10.51,
        ],
    ], JSON_THROW_ON_ERROR);
    $lookup = $mapper->fromJson($response);
    same(TransactionStatus::CONFIRMED, $lookup->status, 'Provider confirmed');
    same(1051, $lookup->amountMinor, 'Exact USD cents');
    same('merchant@bank', $lookup->recipientAccountId, 'Recipient');
});

test('Bakong response mapper: valid KHR numeric amount', static function (): void {
    $mapper = new \TonSamith\Khqr\Bakong\BakongResponseMapper();
    $response = json_encode([
        'responseCode' => 0,
        'data' => ['hash' => str_repeat('b', 64), 'toAccountId' => 'merchant@bank',
            'currency' => 'KHR', 'amount' => 168.0],
    ], JSON_THROW_ON_ERROR);
    $lookup = $mapper->fromJson($response);
    same(TransactionStatus::CONFIRMED, $lookup->status, 'KHR confirmation');
    same(168, $lookup->amountMinor, 'KHR integer');
});

test('Bakong response mapper: fail closed on uncertain status', static function (): void {
    $mapper = new \TonSamith\Khqr\Bakong\BakongResponseMapper();
    same(TransactionStatus::PENDING, $mapper->fromArray([
        'responseCode' => 1, 'errorCode' => 1, 'data' => null
    ])->status, 'Unknown transaction remains pending');
    same(TransactionStatus::REVIEW, $mapper->fromArray([
        'responseCode' => 1, 'errorCode' => 3
    ])->status, 'Failed transaction not confirmed');
    same(TransactionStatus::REVIEW, $mapper->fromArray([
        'responseCode' => 0, 'data' => ['amount' => 10.5]
    ])->status, 'Incomplete confirmation rejected');
    same(TransactionStatus::REVIEW, $mapper->fromJson('{unparseable')->status, 'Bad JSON rejected');
    same(TransactionStatus::REVIEW, $mapper->fromJson(str_repeat('X', 40000))->status, 'Oversize rejected');
});

test('Bakong MD5 verifier: mock transport only', static function (): void {
    $transport = new class implements \TonSamith\Khqr\Bakong\Contracts\BakongTransportInterface {
        public string $lastMd5 = '';
        public function checkTransactionByMd5(string $md5): string {
            $this->lastMd5 = $md5;
            return '{"responseCode":1,"errorCode":1,"data":null}';
        }
    };
    $verifier = new \TonSamith\Khqr\Bakong\BakongMd5Verifier($transport);
    same(TransactionStatus::PENDING, $verifier->checkByMd5(str_repeat('A', 32))->status, 'Mock pending');
    same(str_repeat('a', 32), $transport->lastMd5, 'Normalize MD5');
    throwsInvalid(static fn () => $verifier->checkByMd5('not-an-md5'));
});

test('Settlement matcher rejects recipient, amount, currency mismatch', static function (): void {
    $expected = new \TonSamith\Khqr\Core\ExpectedPayment(
        'ORDER-22', str_repeat('a', 32), 'merchant@bank', 1050, 'USD'
    );
    $matcher = new \TonSamith\Khqr\Core\SettlementMatcher();
    $receipt = static fn ($account, $minor, $currency) => new TransactionLookup(
        TransactionStatus::CONFIRMED, str_repeat('b', 64), $account, $minor, $currency
    );
    check($matcher->matches($expected, $receipt('merchant@bank', 1050, 'USD')), 'Exact match required');
    check(!$matcher->matches($expected, $receipt('attacker@bank', 1050, 'USD')), 'Wrong recipient');
    check(!$matcher->matches($expected, $receipt('merchant@bank', 1051, 'USD')), 'Wrong amount');
    check(!$matcher->matches($expected, $receipt('merchant@bank', 1050, 'KHR')), 'Wrong currency');
});

test('Reconciliation: settle once, then repeat safely', static function (): void {
    $expected = new \TonSamith\Khqr\Core\ExpectedPayment(
        'ORDER-22', str_repeat('a', 32), 'merchant@bank', 1050, 'USD'
    );
    $lookup = new TransactionLookup(
        TransactionStatus::CONFIRMED, str_repeat('b', 64), 'merchant@bank', 1050, 'USD'
    );
    $verifier = new class($lookup) implements \TonSamith\Khqr\Core\Contracts\TransactionVerifierInterface {
        public function __construct(private TransactionLookup $result) {}
        public function checkByMd5(string $md5): TransactionLookup { return $this->result; }
    };
    $store = new class implements \TonSamith\Khqr\Core\Contracts\PaymentStoreInterface {
        public int $calls = 0;
        public function settleOnce(string $reference, string $md5, TransactionLookup $result): bool {
            check($reference === 'ORDER-22', 'Reference');
            check($md5 === str_repeat('a', 32), 'MD5');
            $this->calls++;
            return $this->calls === 1;
        }
    };
    $service = new \TonSamith\Khqr\Core\ReconciliationService($verifier, $store);
    same(\TonSamith\Khqr\Core\ReconciliationDecision::SETTLED, $service->reconcile($expected), 'First settlement');
    same(\TonSamith\Khqr\Core\ReconciliationDecision::ALREADY_SETTLED, $service->reconcile($expected), 'Repeat settlement');
    same(2, $store->calls, 'Store decides idempotency');
});

test('Reconciliation: mismatch never reaches storage', static function (): void {
    $expected = new \TonSamith\Khqr\Core\ExpectedPayment(
        'ORDER-22', str_repeat('a', 32), 'merchant@bank', 1050, 'USD'
    );
    $verifier = new class implements \TonSamith\Khqr\Core\Contracts\TransactionVerifierInterface {
        public function checkByMd5(string $md5): TransactionLookup {
            return new TransactionLookup(
                TransactionStatus::CONFIRMED, str_repeat('b', 64), 'wrong@bank', 1050, 'USD'
            );
        }
    };
    $store = new class implements \TonSamith\Khqr\Core\Contracts\PaymentStoreInterface {
        public function settleOnce(string $ref, string $md5, TransactionLookup $lookup): bool {
            throw new RuntimeException('Must not store mismatch');
        }
    };
    $service = new \TonSamith\Khqr\Core\ReconciliationService($verifier, $store);
    same(\TonSamith\Khqr\Core\ReconciliationDecision::REVIEW, $service->reconcile($expected), 'Wrong recipient rejected');
});

echo PHP_EOL . sprintf('Results: %d passed, %d failed', $passes, $failures) . PHP_EOL;
exit($failures === 0 ? 0 : 1);

