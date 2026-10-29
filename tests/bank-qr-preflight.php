<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use TonSamith\Khqr\Core\BankQrCompatibility;
use TonSamith\Khqr\Core\Crc16;
use TonSamith\Khqr\Core\Khqr;
use TonSamith\Khqr\Core\PaymentRequest;
use TonSamith\Khqr\Core\Tlv;

$count = 0;
$failed = 0;

function qrAssert(bool $condition, string $reason): void
{
    if (!$condition) {
        throw new RuntimeException($reason);
    }
}

function qrTest(string $name, callable $callback): void
{
    global $count, $failed;
    try {
        $callback();
        $count++;
        echo '[PASS] ' . $name . PHP_EOL;
    } catch (Throwable $exception) {
        $failed++;
        fwrite(STDERR, '[FAIL] ' . $name . ': ' . $exception->getMessage() . PHP_EOL);
    }
}

function fixtureMerchantQr(): string
{
    $tags = [
        Tlv::encode('00', '01'),
        Tlv::encode('01', '12'),
        Tlv::encode('30', Tlv::encode('00', 'merchant@bank')),
        Tlv::encode('52', '5999'),
        Tlv::encode('53', '116'),
        Tlv::encode('54', '1000'),
        Tlv::encode('58', 'KH'),
        Tlv::encode('59', 'Fixture Merchant'),
        Tlv::encode('60', 'Battambang'),
        Tlv::encode('62', Tlv::encode('01', 'ORDER-QR-001')),
    ];
    $unsigned = implode('', $tags) . '6304';
    return $unsigned . Crc16::checksum($unsigned);
}

qrTest('Dynamic individual bank QR with reference is eligible for trusted-order binding', static function (): void {
    $payload = (new Khqr())->generate(new PaymentRequest(
        'private@bank', 'Test Merchant', 'Battambang', 'KHR', 1000, 'ORDER-QR-001',
    ))->payload;
    $result = (new BankQrCompatibility())->inspect($payload);
    qrAssert($result['structure'] === 'VALID_KHQR', 'Valid structure');
    qrAssert($result['account_template'] === 'INDIVIDUAL_TAG_29', 'Individual account');
    qrAssert($result['current_qr_binding_compatible'] === 'YES', 'Eligible for further trusted-order validation');
    qrAssert($result['payment_settlement'] === 'DISABLED', 'Never settles');
    qrAssert(!str_contains(json_encode($result), 'private@bank'), 'No account ID leakage');
});

qrTest('Static individual QR cannot enter dynamic reference acceptance', static function (): void {
    $payload = (new Khqr())->generate(new PaymentRequest('private@bank', 'Test Merchant', 'Battambang'))->payload;
    $result = (new BankQrCompatibility())->inspect($payload);
    qrAssert($result['payment_method'] === 'STATIC', 'Static');
    qrAssert($result['current_qr_binding_compatible'] === 'NO', 'Not accepted');
});

qrTest('Dynamic bank QR without order reference cannot enter dynamic reference acceptance', static function (): void {
    $payload = (new Khqr())->generate(new PaymentRequest(
        'private@bank', 'Test Merchant', 'Battambang', 'KHR', 1000
    ))->payload;
    $result = (new BankQrCompatibility())->inspect($payload);
    qrAssert($result['reference_embedded'] === 'NO', 'No reference');
    qrAssert($result['current_qr_binding_compatible'] === 'NO', 'Not accepted');
});

qrTest('Merchant tag 30 is decoded but requires bank-specific integration', static function (): void {
    $result = (new BankQrCompatibility())->inspect(fixtureMerchantQr());
    qrAssert($result['account_template'] === 'MERCHANT_TAG_30', 'Merchant QR');
    qrAssert($result['current_qr_binding_compatible'] === 'NO', 'Must be adapter-approved');
    qrAssert($result['provider_verification'] === 'NOT_EXECUTED', 'No bank call');
});

qrTest('Malformed CRC, overlong QR, non-KHQR string fail closed', static function (): void {
    $valid = fixtureMerchantQr();
    foreach ([
        substr($valid, 0, -1) . ($valid[-1] === '1' ? '2' : '1'),
        'not a QR',
        str_repeat('A', 2049),
        $valid . "\nINJECTED",
    ] as $payload) {
        $threw = false;
        try {
            (new BankQrCompatibility())->inspect($payload);
        } catch (InvalidArgumentException $exception) {
            $threw = true;
        }
        qrAssert($threw, 'Invalid or non-KHQR input must fail');
    }
});

qrTest('Offline CLI prints no payment identifiers and refuses publicly-readable files', static function (): void {
    $dir = sys_get_temp_dir() . '/khqr-bank-qr-' . bin2hex(random_bytes(8));
    qrAssert(mkdir($dir, 0700), 'Private directory');
    $path = $dir . '/aba.txt';
    $payload = (new Khqr())->generate(new PaymentRequest(
        'private@bank', 'Test Merchant', 'Battambang', 'KHR', 1000, 'ORDER-QR-001'
    ))->payload;

    try {
        file_put_contents($path, $payload);
        chmod($path, 0600);
        $command = [PHP_BINARY, dirname(__DIR__) . '/tools/bank-qr-offline-preflight.php', '--text-file=' . $path];
        $run = static function () use ($command): array {
            $pipes = [];
            $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
            qrAssert(is_resource($process), 'Run local CLI');
            fclose($pipes[0]);
            $out = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            return [proc_close($process), $out, $err];
        };

        [$code, $out, $err] = $run();
        qrAssert($code === 0 && str_contains($out, 'QR_PREFLIGHT=COMPLETED_OFFLINE'), 'Valid private file');
        qrAssert(!str_contains($out . $err, $payload), 'No payload leak');
        qrAssert(!str_contains($out . $err, 'private@bank'), 'No account leak');
        qrAssert(!str_contains($out . $err, 'ORDER-QR-001'), 'No order leak');

        chmod($path, 0644);
        [$code, $out, $err] = $run();
        qrAssert($code !== 0 && str_contains($err, 'PRIVATE_FILE_PERMISSIONS_OR_SIZE'), 'Public file rejected');
    } finally {
        @chmod($path, 0600);
        @unlink($path);
        @rmdir($dir);
    }
});

echo sprintf("Bank QR offline preflight: %d passed, %d failed\n", $count, $failed);
exit($failed === 0 ? 0 : 1);

