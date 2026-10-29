<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Core;

use TonSamith\Khqr\Core\Exception\InvalidPayloadException;

/**
 * Bind a merchant-owned expectation to a specific amount-bearing QR payload.
 *
 * Restricted to individual-account (tag 29) dynamic KHQR until bank-specific
 * merchant template (tag 30) account identification has been approved.
 * The expected values MUST come from the merchant's trusted order/store.
 */
final class ExpectedPaymentFromQr
{
    public function fromPayload(
        string $payload,
        string $orderReference,
        string $expectedRecipient,
        int $expectedAmountMinor,
        string $expectedCurrency,
    ): ExpectedPayment {
        $fields = (new Khqr())->decode($payload);

        if (($fields['01'] ?? null) !== '12' ||
            !isset($fields['29']) ||
            isset($fields['30']) ||
            !isset($fields['54'], $fields['62'])) {
            throw new InvalidPayloadException(
                'Acceptance requires a dynamic individual-account QR with an order reference.'
            );
        }

        $recipientFields = Tlv::decode($fields['29']);
        $referenceFields = Tlv::decode($fields['62']);

        $actualRecipient = $recipientFields['00'] ?? null;
        $qrReference = $referenceFields['01'] ?? null;
        $currency = Money::currencyFromCode($fields['53']);
        $amountMinor = Money::parseMinor($fields['54'], $currency);

        if (!is_string($actualRecipient) || !is_string($qrReference) ||
            !hash_equals($expectedRecipient, $actualRecipient) ||
            !hash_equals($orderReference, $qrReference) ||
            $amountMinor !== $expectedAmountMinor ||
            !hash_equals($expectedCurrency, $currency)) {
            throw new InvalidPayloadException('QR payload does not match trusted merchant order.');
        }

        // MD5 is a provider lookup key ONLY, not a security signature.
        return new ExpectedPayment(
            $orderReference,
            md5($payload),
            $expectedRecipient,
            $expectedAmountMinor,
            $expectedCurrency,
        );
    }
}

