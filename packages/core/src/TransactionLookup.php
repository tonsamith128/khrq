<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Core;

use TonSamith\Khqr\Core\Exception\InvalidPayloadException;

/**
 * Provider observation. Host application MUST compare values with its order.
 */
final readonly class TransactionLookup
{
    public function __construct(
        public TransactionStatus $status,
        public ?string $transactionHash = null,
        public ?string $recipientAccountId = null,
        public ?int $amountMinor = null,
        public ?string $currency = null,
    ) {
        if ($status === TransactionStatus::CONFIRMED) {
            if ($transactionHash === null ||
                preg_match('/^[0-9a-fA-F]{64}$/D', $transactionHash) !== 1 ||
                $recipientAccountId === null || $recipientAccountId === '' ||
                $amountMinor === null || $amountMinor <= 0 ||
                $currency === null) {
                throw new InvalidPayloadException('Confirmed transaction requires complete verified evidence.');
            }
            Money::currencyCode($currency);
        }
    }
}

