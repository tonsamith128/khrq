<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Core;

enum ReconciliationDecision: string
{
    case PENDING = 'pending';
    case REVIEW = 'review';
    case SETTLED = 'settled';
    case ALREADY_SETTLED = 'already_settled';
}

