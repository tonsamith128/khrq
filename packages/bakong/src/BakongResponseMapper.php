<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Bakong;

use JsonException;
use TonSamith\Khqr\Core\Money;
use TonSamith\Khqr\Core\TransactionLookup;
use TonSamith\Khqr\Core\TransactionStatus;

/**
 * Offline parsing only: cannot authenticate where response data came from.
 * Fail closed on ambiguous, incomplete or unfamiliar response envelopes.
 */
final class BakongResponseMapper
{
    public function fromJson(string $body): TransactionLookup
    {
        if (strlen($body) > 32768) {
            return new TransactionLookup(TransactionStatus::REVIEW);
        }

        try {
            $decoded = json_decode($body, true, 32, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException) {
            return new TransactionLookup(TransactionStatus::REVIEW);
        }

        return is_array($decoded)
            ? $this->fromArray($decoded)
            : new TransactionLookup(TransactionStatus::REVIEW);
    }

    /** @param array<string,mixed> $envelope */
    public function fromArray(array $envelope): TransactionLookup
    {
        // Missing transaction is not proof of failure; it may settle later.
        if (($envelope['responseCode'] ?? null) === 1 &&
            ($envelope['errorCode'] ?? null) === 1) {
            return new TransactionLookup(TransactionStatus::PENDING);
        }

        if (($envelope['responseCode'] ?? null) !== 0) {
            return new TransactionLookup(TransactionStatus::REVIEW);
        }

        $data = $envelope['data'] ?? null;
        if (!is_array($data)) {
            return new TransactionLookup(TransactionStatus::REVIEW);
        }

        // Some acquiring-bank adapters include an explicit status even
        // when their API envelope reports success. Fail closed if not SUCCESS.
        foreach (['status', 'trackingStatus'] as $field) {
            if (array_key_exists($field, $data) &&
                (!is_string($data[$field]) || strtoupper($data[$field]) !== 'SUCCESS')) {
                return new TransactionLookup(TransactionStatus::REVIEW);
            }
        }

        $hash = $data['hash'] ?? null;
        $recipient = $data['toAccountId'] ?? null;
        $currency = $data['currency'] ?? null;
        $amount = $data['amount'] ?? null;

        if (!is_string($hash) || preg_match('/^[0-9a-fA-F]{64}$/D', $hash) !== 1 ||
            !is_string($recipient) || $recipient === '' || strlen($recipient) > 95 ||
            !is_string($currency) || !in_array($currency, ['KHR', 'USD'], true)) {
            return new TransactionLookup(TransactionStatus::REVIEW);
        }

        $amountText = $this->amountText($amount);
        if ($amountText === null) {
            return new TransactionLookup(TransactionStatus::REVIEW);
        }

        if ($currency === 'KHR' && preg_match('/^([0-9]+)\.0{1,2}$/D', $amountText, $m) === 1) {
            $amountText = $m[1];
        }

        try {
            $amountMinor = Money::parseMinor($amountText, $currency);
        } catch (\InvalidArgumentException) {
            return new TransactionLookup(TransactionStatus::REVIEW);
        }

        return new TransactionLookup(
            TransactionStatus::CONFIRMED,
            strtolower($hash),
            $recipient,
            $amountMinor,
            $currency,
        );
    }

    private function amountText(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        // Preserve decimal representation rather than converting to cents
        // using floating-point arithmetic. Reject exponent notation.
        if (is_float($value) && is_finite($value)) {
            $json = json_encode($value, JSON_PRESERVE_ZERO_FRACTION);
            return is_string($json) && !str_contains(strtolower($json), 'e') ? $json : null;
        }

        return null;
    }
}

