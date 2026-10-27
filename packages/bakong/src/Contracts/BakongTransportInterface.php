<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Bakong\Contracts;

/**
 * Implement only with an approved bank/NBC transport and backend secrets.
 * The method accepts a validated MD5 and returns an untrusted JSON envelope.
 */
interface BakongTransportInterface
{
    public function checkTransactionByMd5(string $md5): string;
}

