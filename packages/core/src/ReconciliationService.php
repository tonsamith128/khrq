<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Core;

use TonSamith\Khqr\Core\Contracts\PaymentStoreInterface;
use TonSamith\Khqr\Core\Contracts\TransactionVerifierInterface;

/**
 * Fail-closed settlement orchestration. It is safe ONLY when:
 * - ExpectedPayment was loaded from the merchant's persisted order.
 * - The verifier uses an authorized authenticated provider.
 * - The store atomically checks QR MD5, recipient, amount, currency and hash.
 */
final readonly class ReconciliationService
{
    public function __construct(
        private TransactionVerifierInterface $verifier,
        private PaymentStoreInterface $store,
        private SettlementMatcher $matcher = new SettlementMatcher(),
    ) {
    }

    public function reconcile(ExpectedPayment $expected): ReconciliationDecision
    {
        $lookup = $this->verifier->checkByMd5($expected->lookupMd5);

        if ($lookup->status === TransactionStatus::PENDING) {
            return ReconciliationDecision::PENDING;
        }

        if (!$this->matcher->matches($expected, $lookup)) {
            return ReconciliationDecision::REVIEW;
        }

        $recorded = $this->store->settleOnce(
            $expected->orderReference,
            $expected->lookupMd5,
            $lookup,
        );

        return $recorded
            ? ReconciliationDecision::SETTLED
            : ReconciliationDecision::ALREADY_SETTLED;
    }
}

