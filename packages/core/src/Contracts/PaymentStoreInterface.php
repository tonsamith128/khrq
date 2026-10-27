<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Core\Contracts;

use TonSamith\Khqr\Core\TransactionLookup;

/**
 * Host-owned persistence. Implementations MUST compare with the recorded order,
 * reject duplicates, and transition state in an atomic database transaction.
 */
interface PaymentStoreInterface
{
    /**
     * @return bool True only if a new, verified settlement was recorded once.
     */
    public function settleOnce(string $merchantOrderReference, TransactionLookup $verifiedLookup): bool;
}

