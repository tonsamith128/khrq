<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Core;

use TonSamith\Khqr\Core\Exception\InvalidPayloadException;

/**
 * Data for an individual-account (tag 29) KHQR payload.
 *
 * amountMinor=null means static; non-null means amount-bearing dynamic QR.
 * This model does not support merchant tag 30 or proprietary bank fields.
 */
final readonly class PaymentRequest
{
    public function __construct(
        public string $accountId,
        public string $merchantName,
        public string $merchantCity,
        public string $currency = 'KHR',
        public ?int $amountMinor = null,
        public ?string $reference = null,
        public string $merchantCategoryCode = '5999',
    ) {
        if (preg_match('/^[A-Za-z0-9_.-]+@[A-Za-z0-9_.-]+$/D', $accountId) !== 1 ||
            strlen($accountId) > 95) {
            throw new InvalidPayloadException('Invalid individual Bakong account identifier.');
        }

        self::requireAscii($merchantName, 25, 'Merchant name');
        self::requireAscii($merchantCity, 15, 'Merchant city');

        if ($reference !== null) {
            self::requireAscii($reference, 25, 'Reference');
        }

        if (preg_match('/^[0-9]{4}$/D', $merchantCategoryCode) !== 1) {
            throw new InvalidPayloadException('Merchant category code must be four digits.');
        }

        Money::currencyCode($currency);

        if ($amountMinor !== null) {
            Money::formatMinor($amountMinor, $currency);
        }

        // Account template ID=29 adds its own four-byte TLV header to child ID=00.
        if (strlen(Tlv::encode('00', $accountId)) > 99) {
            throw new InvalidPayloadException('Account template is too long.');
        }
    }

    private static function requireAscii(string $value, int $maxBytes, string $field): void
    {
        if ($value === '' || strlen($value) > $maxBytes ||
            preg_match('/^[\x20-\x7E]+$/D', $value) !== 1) {
            throw new InvalidPayloadException($field . ' must be printable ASCII, 1-' . $maxBytes . ' bytes.');
        }
    }
}

