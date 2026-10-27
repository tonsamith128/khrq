<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Core;

/**
 * CRC-16/CCITT-FALSE: poly 0x1021, init 0xFFFF, no reflection or final XOR.
 */
final class Crc16
{
    public static function checksum(string $bytes): string
    {
        $crc = 0xFFFF;

        for ($i = 0, $length = strlen($bytes); $i < $length; $i++) {
            $crc ^= ord($bytes[$i]) << 8;

            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) !== 0
                    ? (($crc << 1) ^ 0x1021) & 0xFFFF
                    : ($crc << 1) & 0xFFFF;
            }
        }

        return sprintf('%04X', $crc);
    }
}

