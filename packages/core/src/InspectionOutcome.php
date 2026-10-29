<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Core;

/**
 * READ-ONLY observation. A match never means an order was marked paid.
 */
enum InspectionOutcome: string
{
    case PENDING = 'pending';
    case REVIEW = 'review';
    case MATCHED_NOT_SETTLED = 'matched_not_settled';
}

