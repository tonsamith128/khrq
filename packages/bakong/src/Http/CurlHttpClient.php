<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Bakong\Http;

/**
 * Server-side HTTPS POST transport with no redirects or proxies.
 *
 * The endpoint origin must be validated upstream by BakongHttpOptions.
 * No debugging, logging, cookies, automatic retry or token persistence.
 */
final class CurlHttpClient implements HttpClientInterface
{
    private const MAX_RESPONSE_BYTES = 32768;

    public function postJson(
        string $url,
        string $bearerToken,
        string $jsonBody,
        int $connectTimeoutSeconds,
        int $totalTimeoutSeconds,
    ): HttpResponse {
        if (!extension_loaded('curl')) {
            throw new BakongTransportException('PHP cURL extension is required for provider transport.');
        }

        $handle = curl_init();
        if ($handle === false) {
            throw new BakongTransportException('Unable to initialize the provider transport.');
        }

        $buffer = '';
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonBody,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $bearerToken,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => $connectTimeoutSeconds,
            CURLOPT_TIMEOUT => $totalTimeoutSeconds,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_PROXY => '',
            CURLOPT_NOPROXY => '*',
            CURLOPT_HEADER => false,
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$buffer): int {
                $bytes = strlen($chunk);
                if ($bytes > self::MAX_RESPONSE_BYTES - strlen($buffer)) {
                    return 0; // cURL fails closed on oversized body.
                }
                $buffer .= $chunk;
                return $bytes;
            },
        ];

        // PHP/cURL versions vary; both forms restrict transport to HTTPS.
        if (defined('CURLOPT_PROTOCOLS_STR')) {
            $options[CURLOPT_PROTOCOLS_STR] = 'https';
        } else {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
        }

        try {
            if (curl_setopt_array($handle, $options) !== true) {
                throw new BakongTransportException('Provider transport could not be configured securely.');
            }

            if (curl_exec($handle) === false) {
                // Do not include cURL's raw error text (which may contain URLs).
                throw new BakongTransportException('Provider network or TLS request failed.');
            }

            $code = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
            $type = (string) (curl_getinfo($handle, CURLINFO_CONTENT_TYPE) ?: '');

            return new HttpResponse($code, $type, $buffer);
        } finally {
            curl_close($handle);
        }
    }
}

