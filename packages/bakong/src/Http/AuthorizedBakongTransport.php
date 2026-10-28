<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Bakong\Http;

use Closure;
use TonSamith\Khqr\Bakong\Contracts\BakongTransportInterface;
use TonSamith\Khqr\Core\Exception\InvalidPayloadException;

/**
 * Explicitly gated Bakong-compatible MD5 lookup over HTTPS.
 *
 * No call is possible unless networkEnabled=true, a token supplier is
 * configured and the atomic quota budget allows the request.
 * This does not grant commercial use or provider certification.
 */
final readonly class AuthorizedBakongTransport implements BakongTransportInterface
{
    /**
     * @param Closure():string $tokenSupplier
     */
    public function __construct(
        private BakongHttpOptions $options,
        private HttpClientInterface $http,
        private FileDailyQuota $quota,
        private Closure $tokenSupplier,
    ) {
    }

    public function checkTransactionByMd5(string $md5): string
    {
        if (preg_match('/^[a-f0-9]{32}$/iD', $md5) !== 1) {
            throw new InvalidPayloadException('Invalid QR MD5 lookup identifier.');
        }

        if (!$this->options->networkEnabled) {
            throw new BakongTransportException('Bakong network access is disabled by default.');
        }

        $token = ($this->tokenSupplier)();
        if (!is_string($token) || strlen($token) < 16 || strlen($token) > 4096 ||
            preg_match('/^[\x21-\x7E]+$/D', $token) !== 1) {
            throw new BakongTransportException('Authorized provider bearer token is not configured.');
        }

        // Do not retry inside transport; every attempt consumes quota.
        $this->quota->consume();

        $body = json_encode(['md5' => strtolower($md5)], JSON_THROW_ON_ERROR);
        $response = $this->http->postJson(
            $this->options->md5Endpoint(),
            $token,
            $body,
            $this->options->connectTimeoutSeconds,
            $this->options->totalTimeoutSeconds,
        );

        // HTTP 4xx/5xx/redirects are transport failures, NEVER confirmations.
        if ($response->statusCode !== 200) {
            throw new BakongTransportException('Bakong provider returned a non-success HTTP status.');
        }

        if (preg_match('~^application/(?:json|[a-z0-9.+-]+\+json)(?:\s*;|$)~i', $response->contentType) !== 1 ||
            strlen($response->body) > 32768) {
            throw new BakongTransportException('Bakong provider returned an invalid JSON response envelope.');
        }

        // Still untrusted: BakongResponseMapper and SettlementMatcher must validate.
        return $response->body;
    }
}

