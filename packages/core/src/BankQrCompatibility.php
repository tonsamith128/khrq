<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Core;

use InvalidArgumentException;

/**
 * Strict offline compatibility assessment, never a payment authorization.
 * Returns non-sensitive metadata only: no account IDs, names, amounts,
 * payload, MD5, or transaction references are included in the result.
 */
final class BankQrCompatibility
{
    /** @return array<string, string> */
    public function inspect(string $payload): array
    {
        if (strlen($payload) > 2048 || str_contains($payload, "\n") ||
            str_contains($payload, "\r") || $payload === '') {
            throw new InvalidArgumentException('Unrecognized or oversized QR content.');
        }

        $tags = (new Khqr())->decode($payload);
        $template = isset($tags['29']) ? 'INDIVIDUAL_TAG_29' : 'MERCHANT_TAG_30';
        $method = $tags['01'] === '12' ? 'DYNAMIC' : 'STATIC';
        $currency = Money::currencyFromCode($tags['53']);
        $hasReference = false;

        if (isset($tags['62'])) {
            $additional = Tlv::decode($tags['62']);
            $hasReference = isset($additional['01']) && $additional['01'] !== '';
        }

        $eligible = $template === 'INDIVIDUAL_TAG_29' &&
            $method === 'DYNAMIC' && isset($tags['54']) && $hasReference;

        return [
            'structure' => 'VALID_KHQR',
            'account_template' => $template,
            'payment_method' => $method,
            'currency' => $currency,
            'amount_embedded' => isset($tags['54']) ? 'YES' : 'NO',
            'reference_embedded' => $hasReference ? 'YES' : 'NO',
            'current_qr_binding_compatible' => $eligible ? 'YES' : 'NO',
            'provider_verification' => 'NOT_EXECUTED',
            'payment_settlement' => 'DISABLED',
        ];
    }
}

