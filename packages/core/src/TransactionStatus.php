<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Core;

enum TransactionStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case REVIEW = 'review';
}

