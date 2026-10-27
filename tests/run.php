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

echo PHP_EOL . sprintf('Results: %d passed, %d failed', $passes, $failures) . PHP_EOL;
exit($failures === 0 ? 0 : 1);

