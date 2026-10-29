<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use TonSamith\Khqr\Core\BankQrCompatibility;
use TonSamith\Khqr\Core\BankQrOrderReview;
use TonSamith\Khqr\Core\BankQrOrderReviewStatus;
use TonSamith\Khqr\Core\Crc16;
use TonSamith\Khqr\Core\ExpectedPayment;
use TonSamith\Khqr\Core\Khqr;
use TonSamith\Khqr\Core\PaymentRequest;
use TonSamith\Khqr\Core\Tlv;

$passes = 0;
$failures = 0;

function reviewTest(string $name, callable $test): void
{
    global $passes, $failures;
    try {
        $test();
        $passes++;
        echo '[PASS] ' . $name . PHP_EOL;
    } catch (Throwable $error) {
        $failures++;
        fwrite(STDERR, '[FAIL] ' . $name . ': ' . $error->getMessage() . PHP_EOL);
    }
}

function mustReview(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** Synthetic ABA-like structure: values here are fictional, NOT copied from a bank QR. */
function bankLikePayload(bool $reference = false, bool $merchant = false): string
{
    $elements = [
        Tlv::encode('00', '01'),
        Tlv::encode('01', '12'),
        Tlv::encode($merchant ? '30' : '29', Tlv::encode('00', 'fake@bank')),
        Tlv::encode('40', Tlv::encode('00', 'opaque-test-tag')),
        Tlv::encode('52', '5999'),
        Tlv::encode('53', '116'),
        Tlv::encode('54', '1000'),
        Tlv::encode('58', 'KH'),
        Tlv::encode('59', 'Synthetic Shop'),
        Tlv::encode('60', 'Battambang'),
        Tlv::encode('99', 'NOT_A_PROVIDER_UNIQUENESS_PROOF'),
    ];
    if ($reference) {
        $elements[] = Tlv::encode('62', Tlv::encode('01', 'ORDER-001'));
    }
    $unsigned = implode('', $elements) . '6304';
    return $unsigned . Crc16::checksum($unsigned);
}

function expectedBankOrder(string $payload, string $recipient = 'fake@bank', int $amount = 1000, string $currency = 'KHR', string $order = 'ORDER-001'): ExpectedPayment
{
    return new ExpectedPayment($order, md5($payload), $recipient, $amount, $currency);
}

reviewTest('Reference-free bank QR with opaque tags 40/99 cannot authorize order binding', static function (): void {
    $payload = bankLikePayload();
    $status = (new BankQrOrderReview())->inspect($payload, expectedBankOrder($payload));
    mustReview($status === BankQrOrderReviewStatus::PROVIDER_UNIQUE_BINDING_REQUIRED, 'No unique order mapping proof');
    $flags = (new BankQrCompatibility())->inspect($payload);
    mustReview($flags['reference_free_order_review'] === 'PROVIDER_UNIQUE_BINDING_REQUIRED', 'Offline flag');
    mustReview($flags['bank_extension_tags_present'] === 'YES', 'Opaque bank extension tags detected');
    mustReview($flags['payment_settlement'] === 'DISABLED', 'No settlement');
});

reviewTest('All order attributes and QR MD5 must match trusted record', static function (): void {
    $payload = bankLikePayload();
    $review = new BankQrOrderReview();
    foreach ([
        expectedBankOrder($payload, recipient: 'different@bank'),
        expectedBankOrder($payload, amount: 1001),
        expectedBankOrder($payload, currency: 'USD'),
        expectedBankOrder($payload, order: 'OTHER-ORDER', recipient: 'fake@bank'),
        new ExpectedPayment('ORDER-001', str_repeat('a', 32), 'fake@bank', 1000, 'KHR'),
    ] as $trusted) {
        // If reference is absent, distinct order IDs alone cannot be proven
        // wrong by the QR. All MUST remain unbound; only mismatched fields/MD5
        // can be explicitly rejected as mismatches.
        $result = $review->inspect($payload, $trusted);
        mustReview(in_array($result, [
            BankQrOrderReviewStatus::MISMATCH,
            BankQrOrderReviewStatus::PROVIDER_UNIQUE_BINDING_REQUIRED,
        ], true), 'No reference-free order may settle');
    }
});

reviewTest('Identical reference-free QR cannot distinguish two merchant orders', static function (): void {
    $payload = bankLikePayload();
    $review = new BankQrOrderReview();
    foreach (['ORDER-A', 'ORDER-B'] as $order) {
        $trusted = expectedBankOrder($payload, order: $order);
        mustReview(
            $review->inspect($payload, $trusted) === BankQrOrderReviewStatus::PROVIDER_UNIQUE_BINDING_REQUIRED,
            'Two orders same QR must remain unbound',
        );
    }
});

reviewTest('Reference-present matching QR is still NOT a payment receipt', static function (): void {
    $payload = bankLikePayload(reference: true);
    $review = new BankQrOrderReview();
    mustReview(
        $review->inspect($payload, expectedBankOrder($payload)) === BankQrOrderReviewStatus::REFERENCE_MATCH_NOT_SETTLED,
        'Matching QR does not prove money transfer',
    );
    mustReview(
        $review->inspect($payload, expectedBankOrder($payload, order: 'WRONG-ORDER')) === BankQrOrderReviewStatus::MISMATCH,
        'Wrong explicit reference rejected',
    );
});

reviewTest('Merchant tag 30 remains unsupported', static function (): void {
    $payload = bankLikePayload(merchant: true);
    mustReview(
        (new BankQrOrderReview())->inspect($payload, expectedBankOrder($payload)) === BankQrOrderReviewStatus::UNSUPPORTED_TEMPLATE,
        'Merchant QR is not silently accepted',
    );
});

reviewTest('Invalid CRC, static QR and QR without amount remain ineligible', static function (): void {
    $valid = bankLikePayload();
    $bad = substr($valid, 0, -1) . ($valid[-1] === 'A' ? 'B' : 'A');
    mustReview(
        (new BankQrOrderReview())->inspect($bad, expectedBankOrder($valid)) === BankQrOrderReviewStatus::MISMATCH,
        'Bad CRC fails',
    );
    $static = (new Khqr())->generate(new PaymentRequest('fake@bank', 'Shop', 'Battambang'))->payload;
    mustReview(
        (new BankQrOrderReview())->inspect($static, expectedBankOrder($static)) === BankQrOrderReviewStatus::UNSUPPORTED_TEMPLATE,
        'Static QR cannot be attributed',
    );
});

echo sprintf("Bank-issued QR order review: %d passed, %d failed\n", $passes, $failures);
exit($failures === 0 ? 0 : 1);

