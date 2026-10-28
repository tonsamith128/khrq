<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Bakong\Http;

/**
 * Network adapter seam. Tests use mocks; CurlHttpClient enforces TLS.
 */
interface HttpClientInterface
{
    public function postJson(
        string $url,
        string $bearerToken,
        string $jsonBody,
        int $connectTimeoutSeconds,
        int $totalTimeoutSeconds,
    ): HttpResponse;
}

