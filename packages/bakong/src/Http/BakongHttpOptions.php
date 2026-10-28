<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Bakong\Http;

use InvalidArgumentException;

/**
 * Approved provider origin is configured by the trusted application owner.
 * It must never be selected from a web request or QR payload.
 */
final readonly class BakongHttpOptions
{
    /** @param list<string> $allowedHosts */
    public function __construct(
        public string $baseUrl = 'https://api-bakong.nbc.gov.kh',
        public bool $networkEnabled = false,
        public array $allowedHosts = ['api-bakong.nbc.gov.kh'],
        public int $connectTimeoutSeconds = 3,
        public int $totalTimeoutSeconds = 10,
    ) {
        $parts = parse_url($baseUrl);
        if (!is_array($parts) ||
            ($parts['scheme'] ?? '') !== 'https' ||
            !isset($parts['host']) ||
            isset($parts['user'], $parts['pass']) ||
            isset($parts['user']) || isset($parts['pass']) ||
            isset($parts['port']) || isset($parts['query']) || isset($parts['fragment']) ||
            !in_array($parts['path'] ?? '', ['', '/'], true) ||
            str_contains($baseUrl, '\\') ||
            str_contains($baseUrl, "\r") || str_contains($baseUrl, "\n")) {
            throw new InvalidArgumentException('Provider origin must be an HTTPS hostname without port, credentials, query or path.');
        }

        $host = strtolower($parts['host']);
        if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false ||
            filter_var($host, FILTER_VALIDATE_IP) !== false ||
            $host === 'localhost' || str_ends_with($host, '.local') ||
            !in_array($host, $allowedHosts, true)) {
            throw new InvalidArgumentException('Provider hostname is not explicitly allowed.');
        }

        if ($connectTimeoutSeconds < 1 || $totalTimeoutSeconds < $connectTimeoutSeconds ||
            $totalTimeoutSeconds > 30) {
            throw new InvalidArgumentException('Invalid bounded provider timeouts.');
        }
    }

    public function md5Endpoint(): string
    {
        return rtrim($this->baseUrl, '/') . '/v1/check_transaction_by_md5';
    }
}

