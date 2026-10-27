<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Core;

use TonSamith\Khqr\Core\Exception\InvalidPayloadException;

/**
 * Currency arithmetic using integer minor units, without floating point.
 */
final class Money
{
    public static function currencyCode(string $currency): string
    {
        return match ($currency) {
            'KHR' => '116',
            'USD' => '840',
            default => throw new InvalidPayloadException('Only KHR and USD are supported in v0.0.1.'),
        };
    }

    public static function currencyFromCode(string $code): string
    {
        return match ($code) {
            '116' => 'KHR',
            '840' => 'USD',
            default => throw new InvalidPayloadException('Unsupported KHQR currency code.'),
        };
    }

    public static function formatMinor(int $amountMinor, string $currency): string
    {
        if ($amountMinor <= 0) {
            throw new InvalidPayloadException('Dynamic QR amount must be positive.');
        }

        $value = match ($currency) {
            'KHR' => (string) $amountMinor,
            'USD' => intdiv($amountMinor, 100) . '.' . sprintf('%02d', $amountMinor % 100),
            default => throw new InvalidPayloadException('Unsupported currency.'),
        };

        if (strlen($value) > 13) {
            throw new InvalidPayloadException('KHQR amount exceeds 13 bytes.');
        }

        return $value;
    }

    public static function parseMinor(string $value, string $currency): int
    {
        if (strlen($value) > 13) {
            throw new InvalidPayloadException('Invalid transaction amount length.');
        }

        if ($currency === 'KHR') {
            if (preg_match('/^(?:0|[1-9][0-9]*)$/D', $value) !== 1) {
                throw new InvalidPayloadException('KHR amounts cannot contain decimals.');
            }

            return self::checkedPositiveInt($value);
        }

        if ($currency !== 'USD' ||
            preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/D', $value) !== 1) {
            throw new InvalidPayloadException('Invalid USD amount.');
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $minor = ltrim($whole . str_pad($fraction, 2, '0'), '0');

        return self::checkedPositiveInt($minor === '' ? '0' : $minor);
    }

    private static function checkedPositiveInt(string $digits): int
    {
        $max = (string) PHP_INT_MAX;
        if (strlen($digits) > strlen($max) ||
            (strlen($digits) === strlen($max) && strcmp($digits, $max) > 0)) {
            throw new InvalidPayloadException('Amount exceeds PHP integer capacity.');
        }

        $amount = (int) $digits;
        if ($amount <= 0) {
            throw new InvalidPayloadException('Amount must be greater than zero.');
        }

        return $amount;
    }
}

