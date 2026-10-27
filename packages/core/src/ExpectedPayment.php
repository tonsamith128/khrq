<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Core;

use TonSamith\Khqr\Core\Exception\InvalidPayloadException;

/**
 * Values MUST come from the merchant's persisted order, never an untrusted
 * browser request. The MD5 is the QR lookup key, not payment authentication.
 */
final readonly class ExpectedPayment
{
    public function __construct(
        public string $orderReference,
        public string $lookupMd5,
        public string $recipientAccountId,
        public int $amountMinor,
        public string $currency,
    ) {
        if ($orderReference === '' || strlen($orderReference) > 128 ||
            preg_match('/^[\x21-\x7E]+$/D', $orderReference) !== 1) {
            throw new InvalidPayloadException('Invalid merchant order reference.');
        }

        if (preg_match('/^[a-fA-F0-9]{32}$/D', $lookupMd5) !== 1) {
            throw new InvalidPayloadException('MD5 lookup ID must be 32 hexadecimal characters.');
        }

        if ($recipientAccountId === '' || strlen($recipientAccountId) > 95 ||
            preg_match('/^[\x21-\x7E]+$/D', $recipientAccountId) !== 1) {
            throw new InvalidPayloadException('Invalid expected recipient.');
        }

        Money::currencyCode($currency);
        Money::formatMinor($amountMinor, $currency);
    }
}

