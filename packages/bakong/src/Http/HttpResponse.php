<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Bakong\Http;

/** Trusted only as an HTTP observation, not evidence of payment by itself. */
final readonly class HttpResponse
{
    public function __construct(
        public int $statusCode,
        public string $contentType,
        public string $body,
    ) {
    }
}

