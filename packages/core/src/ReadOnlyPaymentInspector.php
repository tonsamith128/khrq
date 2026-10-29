<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Core;

use TonSamith\Khqr\Core\Contracts\TransactionVerifierInterface;

/**
 * Inspect an authorized provider observation without ever invoking a store.
 * This is intended for bank-sanctioned small-value acceptance only.
 */
final readonly class ReadOnlyPaymentInspector
{
    public function __construct(
        private TransactionVerifierInterface $verifier,
        private SettlementMatcher $matcher = new SettlementMatcher(),
    ) {
    }

    public function inspect(ExpectedPayment $expected): InspectionOutcome
    {
        $observation = $this->verifier->checkByMd5($expected->lookupMd5);
        if ($observation->status === TransactionStatus::PENDING) {
            return InspectionOutcome::PENDING;
        }

        return $this->matcher->matches($expected, $observation)
            ? InspectionOutcome::MATCHED_NOT_SETTLED
            : InspectionOutcome::REVIEW;
    }
}

