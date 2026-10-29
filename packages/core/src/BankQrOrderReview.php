<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Core;

/**
 * Strict, offline comparison of a genuine bank-issued QR with an order
 * expectation loaded from the merchant's OWN trusted private database.
 *
 * Bank-specific extensions (e.g. tags 40 and 99) are NOT interpreted as
 * unique IDs. Missing tag-62 reference always blocks order attribution.
 * No network, token, database mutation, or settlement path exists here.
 */
final class BankQrOrderReview
{
    public function inspect(string $payload, ExpectedPayment $trustedOrder): BankQrOrderReviewStatus
    {
        try {
            $fields = (new Khqr())->decode($payload);
            if (!isset($fields['29'], $fields['54']) ||
                isset($fields['30']) ||
                ($fields['01'] ?? null) !== '12') {
                return BankQrOrderReviewStatus::UNSUPPORTED_TEMPLATE;
            }

            $account = Tlv::decode($fields['29'])['00'] ?? null;
            $currency = Money::currencyFromCode($fields['53'] ?? '');
            $amount = Money::parseMinor($fields['54'], $currency);

            if (!is_string($account) ||
                !hash_equals(strtolower($trustedOrder->lookupMd5), md5($payload)) ||
                !hash_equals($trustedOrder->recipientAccountId, $account) ||
                $currency !== $trustedOrder->currency ||
                $amount !== $trustedOrder->amountMinor) {
                return BankQrOrderReviewStatus::MISMATCH;
            }

            // Even if the QR has other opaque bank tags, no tag-62 order
            // reference means we cannot prove one-QR-to-one-order uniqueness.
            if (!isset($fields['62'])) {
                return BankQrOrderReviewStatus::PROVIDER_UNIQUE_BINDING_REQUIRED;
            }

            $reference = Tlv::decode($fields['62'])['01'] ?? null;
            if (!is_string($reference) || $reference === '') {
                return BankQrOrderReviewStatus::PROVIDER_UNIQUE_BINDING_REQUIRED;
            }

            return hash_equals($trustedOrder->orderReference, $reference)
                ? BankQrOrderReviewStatus::REFERENCE_MATCH_NOT_SETTLED
                : BankQrOrderReviewStatus::MISMATCH;
        } catch (\InvalidArgumentException) {
            return BankQrOrderReviewStatus::MISMATCH;
        }
    }
}

