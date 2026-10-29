<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Core;

/**
 * Offline QR-to-order review only. NONE of these statuses is a receipt,
 * payment authorization or permission to mutate an order.
 */
enum BankQrOrderReviewStatus: string
{
    case MISMATCH = 'mismatch';
    case UNSUPPORTED_TEMPLATE = 'unsupported_template';
    case PROVIDER_UNIQUE_BINDING_REQUIRED = 'provider_unique_binding_required';
    case REFERENCE_MATCH_NOT_SETTLED = 'reference_match_not_settled';
}

