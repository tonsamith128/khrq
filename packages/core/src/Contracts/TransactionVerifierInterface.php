<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Core\Contracts;

use TonSamith\Khqr\Core\TransactionLookup;

/**
 * Provider implementations need authorized bank credentials and verified API.
 * A valid QR payload is never sufficient evidence for CONFIRMED status.
 */
interface TransactionVerifierInterface
{
    public function checkByMd5(string $lookupMd5): TransactionLookup;
}

