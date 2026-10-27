<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Core;

/**
 * A QR payload and API lookup identifier. Neither is a payment receipt.
 */
final readonly class QrResult
{
    public function __construct(
        public string $payload,
        public string $md5,
    ) {
    }
}

