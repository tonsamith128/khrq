<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Core;

use TonSamith\Khqr\Core\Exception\InvalidPayloadException;

/**
 * EMV-style tag-length-value framing. Lengths are measured in bytes.
 */
final class Tlv
{
    public static function encode(string $id, string $value): string
    {
        if (preg_match('/^[0-9]{2}$/D', $id) !== 1) {
            throw new InvalidPayloadException('TLV tag must contain exactly two ASCII digits.');
        }

        $length = strlen($value);
        if ($length > 99) {
            throw new InvalidPayloadException('TLV value must not exceed 99 bytes.');
        }

        return $id . sprintf('%02d', $length) . $value;
    }

    /**
     * @return array<string, string> Tags in original order.
     */
    public static function decode(string $encoded): array
    {
        $offset = 0;
        $total = strlen($encoded);
        $fields = [];

        while ($offset < $total) {
            if ($total - $offset < 4) {
                throw new InvalidPayloadException('Truncated TLV field header.');
            }

            $id = substr($encoded, $offset, 2);
            $lengthText = substr($encoded, $offset + 2, 2);

            if (preg_match('/^[0-9]{2}$/D', $id) !== 1 ||
                preg_match('/^[0-9]{2}$/D', $lengthText) !== 1) {
                throw new InvalidPayloadException('Invalid TLV field header.');
            }

            $length = (int) $lengthText;
            $offset += 4;

            if ($length > $total - $offset) {
                throw new InvalidPayloadException('TLV field exceeds remaining payload.');
            }

            if (array_key_exists($id, $fields)) {
                throw new InvalidPayloadException('Duplicate TLV tag: ' . $id);
            }

            $fields[$id] = substr($encoded, $offset, $length);
            $offset += $length;
        }

        return $fields;
    }
}

