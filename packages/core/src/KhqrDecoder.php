<?php
declare(strict_types=1);

namespace TonSamith\Khqr\Core;

use TonSamith\Khqr\Core\Exception\InvalidPayloadException;

final class KhqrDecoder
{
    /** @return array<string, string> */
    public static function decode(string $payload): array
    {
        if (strlen($payload) < 12) {
            throw new InvalidPayloadException('KHQR payload is too short.');
        }

        $tags = Tlv::decode($payload);

        // PHP casts digit-only array keys such as "63" to integers.
        if ((string) array_key_last($tags) !== '63' ||
            preg_match('/^[0-9A-F]{4}$/D', $tags['63']) !== 1) {
            throw new InvalidPayloadException('KHQR CRC must be final.');
        }

        $withoutCrcValue = substr($payload, 0, -4);
        if (!hash_equals(Crc16::checksum($withoutCrcValue), $tags['63'])) {
            throw new InvalidPayloadException('KHQR CRC mismatch.');
        }

        if (($tags['00'] ?? null) !== '01' ||
            !in_array($tags['01'] ?? null, ['11', '12'], true) ||
            ($tags['58'] ?? null) !== 'KH') {
            throw new InvalidPayloadException('Invalid mandatory KHQR fields.');
        }

        if (preg_match('/^[0-9]{4}$/D', $tags['52'] ?? '') !== 1) {
            throw new InvalidPayloadException('Invalid merchant category code.');
        }

        $currency = Money::currencyFromCode($tags['53'] ?? '');

        foreach (['59' => 25, '60' => 15] as $tag => $max) {
            $value = $tags[$tag] ?? '';
            if ($value === '' || strlen($value) > $max ||
                preg_match('/^[\x20-\x7E]+$/D', $value) !== 1) {
                throw new InvalidPayloadException('Invalid merchant field ' . $tag);
            }
        }

        if (!isset($tags['29']) && !isset($tags['30'])) {
            throw new InvalidPayloadException('Missing KHQR account template.');
        }

        if (isset($tags['29']) && isset($tags['30'])) {
            throw new InvalidPayloadException('Ambiguous account template.');
        }

        $accountFields = Tlv::decode($tags['29'] ?? $tags['30']);
        if (!isset($accountFields['00']) || $accountFields['00'] === '') {
            throw new InvalidPayloadException('Missing Bakong account identifier.');
        }

        if ($tags['01'] === '11' && isset($tags['54'])) {
            throw new InvalidPayloadException('Static KHQR must not include amount.');
        }

        if ($tags['01'] === '12') {
            if (!isset($tags['54'])) {
                throw new InvalidPayloadException('Dynamic KHQR requires amount.');
            }

            Money::parseMinor($tags['54'], $currency);
        }

        if (isset($tags['62'])) {
            $additional = Tlv::decode($tags['62']);
            if (isset($additional['01']) &&
                ($additional['01'] === '' || strlen($additional['01']) > 25)) {
                throw new InvalidPayloadException('Invalid reference length.');
            }
        }

        return $tags;
    }
}

