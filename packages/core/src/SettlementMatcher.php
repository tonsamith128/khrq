<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Core;

/**
 * The provider is authoritative about the transfer, but the merchant's saved
 * order is authoritative about the expected recipient/currency/amount.
 */
final class SettlementMatcher
{
    public function matches(ExpectedPayment $expected, TransactionLookup $lookup): bool
    {
        return $lookup->status === TransactionStatus::CONFIRMED
            && $lookup->transactionHash !== null
            && $lookup->amountMinor === $expected->amountMinor
            && $lookup->currency === $expected->currency
            && $lookup->recipientAccountId !== null
            && hash_equals($expected->recipientAccountId, $lookup->recipientAccountId);
    }
}

