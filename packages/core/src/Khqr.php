<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Core;

/**
 * Offline KHQR generation and validation. No network or payment settlement.
 */
final class Khqr
{
    public function generate(PaymentRequest $request): QrResult
    {
        $fields = [
            Tlv::encode('00', '01'),
            Tlv::encode('01', $request->amountMinor === null ? '11' : '12'),
            Tlv::encode('29', Tlv::encode('00', $request->accountId)),
            Tlv::encode('52', $request->merchantCategoryCode),
            Tlv::encode('53', Money::currencyCode($request->currency)),
        ];

        if ($request->amountMinor !== null) {
            $fields[] = Tlv::encode('54', Money::formatMinor($request->amountMinor, $request->currency));
        }

        $fields[] = Tlv::encode('58', 'KH');
        $fields[] = Tlv::encode('59', $request->merchantName);
        $fields[] = Tlv::encode('60', $request->merchantCity);

        if ($request->reference !== null) {
            $fields[] = Tlv::encode('62', Tlv::encode('01', $request->reference));
        }

        $unsignedPayload = implode('', $fields) . '6304';
        $payload = $unsignedPayload . Crc16::checksum($unsignedPayload);

        return new QrResult($payload, md5($payload));
    }

    /**
     * @return array<string, string> Validated raw root TLV fields.
     */
    public function decode(string $payload): array
    {
        return KhqrDecoder::decode($payload);
    }

    public function isValid(string $payload): bool
    {
        try {
            $this->decode($payload);
            return true;
        } catch (\InvalidArgumentException $exception) {
            return false;
        }
    }
}

