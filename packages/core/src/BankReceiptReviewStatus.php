<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Core;

/**
 * An offline review result, NEVER permission to fulfill/settle an order.
 * No result here is a payment receipt or provider authorization.
 */
enum BankReceiptReviewStatus: string
{
    case QR_UNSUPPORTED = 'qr_unsupported';
    case QR_MISMATCH = 'qr_mismatch';
    case PROVIDER_PENDING = 'provider_pending';
    case PROVIDER_REVIEW = 'provider_review';
    case REFERENCE_FREE_UNBOUND = 'reference_free_unbound';
    case REFERENCE_MATCH_NOT_SETTLED = 'reference_match_not_settled';
    case DUPLICATE_QR_REVIEW = 'duplicate_qr_review';
    case DUPLICATE_TRANSFER_REVIEW = 'duplicate_transfer_review';
    case DUPLICATE_ORDER_REVIEW = 'duplicate_order_review';
}
