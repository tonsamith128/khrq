<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Core\Contracts;

use TonSamith\Khqr\Core\TransactionLookup;

/**
 * Host-owned persistence with database-level transaction/uniqueness controls.
 * Must validate the stored QR lookup ID and expected order details again.
 */
interface PaymentStoreInterface
{
    /**
     * Returns true only when the order newly transitions to paid.
     * Returns false only for an already-recorded, identical transaction.
     * Mismatches and transaction collisions MUST throw, never return false.
     */
    public function settleOnce(
        string $merchantOrderReference,
        string $lookupMd5,
        TransactionLookup $verifiedLookup,
    ): bool;
}

