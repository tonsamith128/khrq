<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use TonSamith\Khqr\Core\Khqr as CoreKhqr;

/**
 * @method static \TonSamith\Khqr\Core\QrResult generate(\TonSamith\Khqr\Core\PaymentRequest $request)
 * @method static array decode(string $payload)
 * @method static bool isValid(string $payload)
 */
final class Khqr extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CoreKhqr::class;
    }
}

