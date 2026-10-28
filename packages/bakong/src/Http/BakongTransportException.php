<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Bakong\Http;

use RuntimeException;

/**
 * Error messages contain NO tokens, endpoint secrets or provider response bodies.
 */
final class BakongTransportException extends RuntimeException
{
}

