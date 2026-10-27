<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Bakong;

use TonSamith\Khqr\Bakong\Contracts\BakongTransportInterface;
use TonSamith\Khqr\Core\Contracts\TransactionVerifierInterface;
use TonSamith\Khqr\Core\Exception\InvalidPayloadException;
use TonSamith\Khqr\Core\TransactionLookup;

/**
 * This verifier cannot be constructed without an explicitly supplied
 * authorized transport. The SDK ships no default network transport.
 */
final readonly class BakongMd5Verifier implements TransactionVerifierInterface
{
    public function __construct(
        private BakongTransportInterface $transport,
        private BakongResponseMapper $mapper = new BakongResponseMapper(),
    ) {
    }

    public function checkByMd5(string $lookupMd5): TransactionLookup
    {
        if (preg_match('/^[a-f0-9]{32}$/iD', $lookupMd5) !== 1) {
            throw new InvalidPayloadException('Invalid MD5 lookup ID.');
        }

        // Propagate transport errors: never fabricate successful settlement.
        return $this->mapper->fromJson(
            $this->transport->checkTransactionByMd5(strtolower($lookupMd5)),
        );
    }
}

