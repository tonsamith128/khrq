<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Php;

use TonSamith\Khqr\Core\Khqr;

/**
 * Convenience adapter; works without a Laravel application.
 */
final class KhqrFactory
{
    public static function make(): Khqr
    {
        return new Khqr();
    }
}

