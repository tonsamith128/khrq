<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Core;

use InvalidArgumentException;

/**
 * Offline, side-effect-free matching of a bank-issued QR and observation
 * against values already loaded from a merchant-owned trusted order.
 *
 * The caller MUST authenticate the provider response before constructing
 * TransactionLookup. This class cannot attest that the source is a bank.
 * This class never calls HTTP, uses tokens, stores data or settles orders.
 *
 * Missing Tag 62 CANNOT be replaced by merchant guesses about opaque tags
 * 40/99, amount matching, a valid QR CRC or a provider transfer hash.
 */
final readonly class ReadOnlyBankReceiptReviewer
{
    public function __construct(
        private BankQrOrderReview $qrReview = new BankQrOrderReview(),
        private SettlementMatcher $matcher = new SettlementMatcher(),
    ) {
    }

    public function review(
        string $qrPayload,
        ExpectedPayment $expected,
        TransactionLookup $observation,
    ): BankReceiptReviewStatus {
        $qrResult = $this->qrReview->inspect($qrPayload, $expected);

        if ($qrResult === BankQrOrderReviewStatus::UNSUPPORTED_TEMPLATE) {
            return BankReceiptReviewStatus::QR_UNSUPPORTED;
        }

        if ($qrResult === BankQrOrderReviewStatus::MISMATCH) {
            return BankReceiptReviewStatus::QR_MISMATCH;
        }

        if ($observation->status === TransactionStatus::PENDING) {
            return BankReceiptReviewStatus::PROVIDER_PENDING;
        }

        if (!$this->matcher->matches($expected, $observation)) {
            return BankReceiptReviewStatus::PROVIDER_REVIEW;
        }

        return $qrResult === BankQrOrderReviewStatus::PROVIDER_UNIQUE_BINDING_REQUIRED
            ? BankReceiptReviewStatus::REFERENCE_FREE_UNBOUND
            : BankReceiptReviewStatus::REFERENCE_MATCH_NOT_SETTLED;
    }

    /**
     * Review a small, operator-selected set of claims without writing a ledger.
     *
     * @param list<array{payload:string,expected:ExpectedPayment,observation:TransactionLookup}> $claims
     * @return list<BankReceiptReviewStatus>
     *
     * Duplicated QR/receipt/order among claims always blocks attribution.
     * For persistence/concurrent workers, DB uniqueness constraints remain
     * mandatory. This in-memory detection is NOT a concurrency mechanism.
     */
    public function reviewBatch(array $claims): array
    {
        $results = [];
        $qrGroups = [];
        $receiptGroups = [];
        $orderGroups = [];

        foreach ($claims as $index => $claim) {
            if (!is_int($index) || !is_array($claim) ||
                !isset($claim['payload'], $claim['expected'], $claim['observation']) ||
                !is_string($claim['payload']) ||
                !$claim['expected'] instanceof ExpectedPayment ||
                !$claim['observation'] instanceof TransactionLookup) {
                throw new InvalidArgumentException('Invalid read-only receipt review claim.');
            }

            $expected = $claim['expected'];
            $observation = $claim['observation'];
            $results[] = $this->review($claim['payload'], $expected, $observation);

            // Keys stay private. The caller only receives status enums.
            $qrGroups[strtolower($expected->lookupMd5)][] = $index;
            $orderGroups[$expected->orderReference][] = $index;
            if ($observation->status === TransactionStatus::CONFIRMED &&
                $observation->transactionHash !== null) {
                $receiptGroups[strtolower($observation->transactionHash)][] = $index;
            }
        }

        foreach ($qrGroups as $indices) {
            if (count($indices) > 1) {
                foreach ($indices as $index) {
                    $results[$index] = BankReceiptReviewStatus::DUPLICATE_QR_REVIEW;
                }
            }
        }

        foreach ($receiptGroups as $indices) {
            if (count($indices) > 1) {
                foreach ($indices as $index) {
                    $results[$index] = BankReceiptReviewStatus::DUPLICATE_TRANSFER_REVIEW;
                }
            }
        }

        foreach ($orderGroups as $indices) {
            if (count($indices) > 1) {
                foreach ($indices as $index) {
                    $results[$index] = BankReceiptReviewStatus::DUPLICATE_ORDER_REVIEW;
                }
            }
        }

        return $results;
    }
}
