<?php

declare(strict_types=1);

/**
 * Synthetic-only ABA-like QR/receipt tests. NO real QR, token, bank network,
 * account ID, transfer data or production database involved.
 */
require_once __DIR__ . '/bootstrap.php';

use TonSamith\Khqr\Core\BankReceiptReviewStatus;
use TonSamith\Khqr\Core\ExpectedPayment;
use TonSamith\Khqr\Core\Khqr;
use TonSamith\Khqr\Core\PaymentRequest;
use TonSamith\Khqr\Core\ReadOnlyBankReceiptReviewer;
use TonSamith\Khqr\Core\TransactionLookup;
use TonSamith\Khqr\Core\TransactionStatus;

$passes = 0;
$failures = 0;

function receiptTest(string $name, callable $action): void
{
    global $passes, $failures;
    try {
        $action();
        $passes++;
        echo '[PASS] ' . $name . PHP_EOL;
    } catch (Throwable $error) {
        $failures++;
        fwrite(STDERR, '[FAIL] ' . $name . ': ' . $error->getMessage() . PHP_EOL);
    }
}

function mustReceipt(bool $value, string $reason): void
{
    if (!$value) {
        throw new RuntimeException($reason);
    }
}

/** @return array{payload:string,expected:ExpectedPayment,observation:TransactionLookup} */
function makeReceiptClaim(
    string $order,
    bool $qrReference = false,
    string $transactionHash = '',
    int $expectedAmount = 1000,
    int $observedAmount = 1000,
    string $observedRecipient = 'receiver@bank',
    string $observedCurrency = 'KHR',
): array {
    $result = (new Khqr())->generate(new PaymentRequest(
        accountId: 'receiver@bank',
        merchantName: 'Synthetic Test',
        merchantCity: 'Battambang',
        currency: 'KHR',
        amountMinor: 1000,
        reference: $qrReference ? $order : null,
    ));

    return [
        'payload' => $result->payload,
        'expected' => new ExpectedPayment(
            $order, $result->md5, 'receiver@bank', $expectedAmount, 'KHR',
        ),
        'observation' => new TransactionLookup(
            TransactionStatus::CONFIRMED,
            $transactionHash !== '' ? $transactionHash : str_repeat('a', 64),
            $observedRecipient,
            $observedAmount,
            $observedCurrency,
        ),
    ];
}

receiptTest('ABA-style no-reference QR with matched receipt remains UNBOUND', static function (): void {
    $claim = makeReceiptClaim('ORDER-001');
    $reviewer = new ReadOnlyBankReceiptReviewer();
    mustReceipt(
        $reviewer->review(...array_values($claim)) === BankReceiptReviewStatus::REFERENCE_FREE_UNBOUND,
        'A bank QR without tag 62 is never proof of an order',
    );
});

receiptTest('Provider pending must remain pending, never settled', static function (): void {
    $claim = makeReceiptClaim('ORDER-001');
    $claim['observation'] = new TransactionLookup(TransactionStatus::PENDING);
    mustReceipt(
        (new ReadOnlyBankReceiptReviewer())->review(...array_values($claim)) === BankReceiptReviewStatus::PROVIDER_PENDING,
        'Pending provider status',
    );
});

receiptTest('Provider review, malformed status and missing receipt remain REVIEW', static function (): void {
    $claim = makeReceiptClaim('ORDER-001');
    foreach ([TransactionStatus::REVIEW] as $status) {
        $claim['observation'] = new TransactionLookup($status);
        mustReceipt(
            (new ReadOnlyBankReceiptReviewer())->review(...array_values($claim)) === BankReceiptReviewStatus::PROVIDER_REVIEW,
            'Unverified provider response must be reviewed',
        );
    }
});

receiptTest('Wrong provider recipient, amount or currency never matches', static function (): void {
    $reviewer = new ReadOnlyBankReceiptReviewer();
    foreach ([
        makeReceiptClaim('ORDER-001', observedRecipient: 'attacker@bank'),
        makeReceiptClaim('ORDER-001', observedAmount: 1001),
        makeReceiptClaim('ORDER-001', observedCurrency: 'USD'),
    ] as $claim) {
        mustReceipt(
            $reviewer->review(...array_values($claim)) === BankReceiptReviewStatus::PROVIDER_REVIEW,
            'Provider mismatch must not match',
        );
    }
});

receiptTest('Wrong persisted amount or payload MD5 fails before provider match', static function (): void {
    $reviewer = new ReadOnlyBankReceiptReviewer();
    $bad = makeReceiptClaim('ORDER-001', expectedAmount: 1001);
    mustReceipt(
        $reviewer->review(...array_values($bad)) === BankReceiptReviewStatus::QR_MISMATCH,
        'Trusted order differs from QR',
    );
    $good = makeReceiptClaim('ORDER-001');
    $good['expected'] = new ExpectedPayment('ORDER-001', str_repeat('b', 32), 'receiver@bank', 1000, 'KHR');
    mustReceipt(
        $reviewer->review(...array_values($good)) === BankReceiptReviewStatus::QR_MISMATCH,
        'Tampered QR lookup MD5',
    );
});

receiptTest('Matching reference-bearing QR remains NOT SETTLED', static function (): void {
    $claim = makeReceiptClaim('ORDER-001', qrReference: true);
    mustReceipt(
        (new ReadOnlyBankReceiptReviewer())->review(...array_values($claim)) === BankReceiptReviewStatus::REFERENCE_MATCH_NOT_SETTLED,
        'Even complete QR and observation match is only read-only',
    );
});

receiptTest('Same reference-free QR across two orders blocked BEFORE payment attribution', static function (): void {
    $a = makeReceiptClaim('ORDER-A');
    $b = makeReceiptClaim('ORDER-B', transactionHash: str_repeat('b', 64));
    mustReceipt($a['payload'] === $b['payload'], 'Synthetic QR shared');
    $batch = (new ReadOnlyBankReceiptReviewer())->reviewBatch([$a, $b]);
    mustReceipt($batch === [
        BankReceiptReviewStatus::DUPLICATE_QR_REVIEW,
        BankReceiptReviewStatus::DUPLICATE_QR_REVIEW,
    ], 'One QR cannot prove two independent orders');
});

receiptTest('One bank hash cannot be attributed to two distinct referenced QRs', static function (): void {
    $a = makeReceiptClaim('ORDER-A', qrReference: true);
    $b = makeReceiptClaim('ORDER-B', qrReference: true);
    mustReceipt($a['payload'] !== $b['payload'], 'Different QRs');
    $batch = (new ReadOnlyBankReceiptReviewer())->reviewBatch([$a, $b]);
    mustReceipt($batch === [
        BankReceiptReviewStatus::DUPLICATE_TRANSFER_REVIEW,
        BankReceiptReviewStatus::DUPLICATE_TRANSFER_REVIEW,
    ], 'Transaction hash collision across orders must be reported');
});

receiptTest('Repeated merchant order is flagged and never considered new payment', static function (): void {
    $a = makeReceiptClaim('ORDER-A', qrReference: true);
    $b = makeReceiptClaim('ORDER-A', qrReference: true, transactionHash: str_repeat('c', 64));
    $batch = (new ReadOnlyBankReceiptReviewer())->reviewBatch([$a, $b]);
    mustReceipt($batch === [
        BankReceiptReviewStatus::DUPLICATE_ORDER_REVIEW,
        BankReceiptReviewStatus::DUPLICATE_ORDER_REVIEW,
    ], 'Duplicate merchant order blocked');
});

receiptTest('Independent receipts do not collide, but are not settled', static function (): void {
    $a = makeReceiptClaim('ORDER-A', qrReference: true);
    $b = makeReceiptClaim('ORDER-B', qrReference: true, transactionHash: str_repeat('b', 64));
    $batch = (new ReadOnlyBankReceiptReviewer())->reviewBatch([$a, $b]);
    mustReceipt($batch === [
        BankReceiptReviewStatus::REFERENCE_MATCH_NOT_SETTLED,
        BankReceiptReviewStatus::REFERENCE_MATCH_NOT_SETTLED,
    ], 'Read-only matches, no settlement');
});

receiptTest('Malformed batch input rejected without exposing account or hash', static function (): void {
    try {
        (new ReadOnlyBankReceiptReviewer())->reviewBatch([['payload' => 'invalid']]);
    } catch (InvalidArgumentException $error) {
        mustReceipt(!str_contains($error->getMessage(), 'receiver@bank'), 'Private error');
        return;
    }
    throw new RuntimeException('Invalid batch must throw');
});

receiptTest('Static QR cannot be used as reference-free payment evidence', static function (): void {
    $payload = (new Khqr())->generate(new PaymentRequest(
        'receiver@bank', 'Synthetic Shop', 'Battambang',
    ))->payload;
    $expected = new ExpectedPayment('ORDER-STATIC', md5($payload), 'receiver@bank', 1000, 'KHR');
    $lookup = new TransactionLookup(TransactionStatus::CONFIRMED, str_repeat('a', 64), 'receiver@bank', 1000, 'KHR');
    mustReceipt(
        (new ReadOnlyBankReceiptReviewer())->review($payload, $expected, $lookup) === BankReceiptReviewStatus::QR_UNSUPPORTED,
        'Static QR not supported for individual order attribution',
    );
});

echo sprintf("Bank receipt read-only acceptance: %d passed, %d failed\n", $passes, $failures);
exit($failures === 0 ? 0 : 1);
