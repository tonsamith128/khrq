<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Laravel\Storage;

use Illuminate\Database\Connection;
use LogicException;
use RuntimeException;
use TonSamith\Khqr\Core\Contracts\PaymentStoreInterface;
use TonSamith\Khqr\Core\ExpectedPayment;
use TonSamith\Khqr\Core\TransactionLookup;
use TonSamith\Khqr\Core\TransactionStatus;

/**
 * Example merchant-owned settlement ledger for ONE Laravel application.
 *
 * This is not automatically migrated or enabled. The host must apply the
 * published migration and load ExpectedPayment from persisted records.
 */
final readonly class LaravelPaymentStore implements PaymentStoreInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function createPending(ExpectedPayment $expected): void
    {
        $now = date('Y-m-d H:i:s');

        // Unique indexes reject duplicate order refs and QR lookup IDs.
        $this->connection->table('khqr_payment_requests')->insert([
            'order_reference' => $expected->orderReference,
            'lookup_md5' => strtolower($expected->lookupMd5),
            'recipient_account_id' => $expected->recipientAccountId,
            'amount_minor' => $expected->amountMinor,
            'currency' => $expected->currency,
            'status' => 'pending',
            'transaction_hash' => null,
            'paid_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function settleOnce(
        string $merchantOrderReference,
        string $lookupMd5,
        TransactionLookup $verifiedLookup,
    ): bool {
        if ($verifiedLookup->status !== TransactionStatus::CONFIRMED) {
            throw new LogicException('Only confirmed provider observations can settle payments.');
        }

        return $this->connection->transaction(function () use (
            $merchantOrderReference,
            $lookupMd5,
            $verifiedLookup
        ): bool {
            $row = $this->connection
                ->table('khqr_payment_requests')
                ->where('order_reference', $merchantOrderReference)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                throw new RuntimeException('KHQR order not found; refusing settlement.');
            }

            if (!hash_equals((string) $row->lookup_md5, strtolower($lookupMd5)) ||
                !hash_equals((string) $row->recipient_account_id, (string) $verifiedLookup->recipientAccountId) ||
                (string) $row->amount_minor !== (string) $verifiedLookup->amountMinor ||
                (string) $row->currency !== (string) $verifiedLookup->currency) {
                throw new RuntimeException('KHQR order and provider observation mismatch.');
            }

            $hash = strtolower((string) $verifiedLookup->transactionHash);

            if ((string) $row->status === 'paid') {
                if (hash_equals((string) $row->transaction_hash, $hash)) {
                    return false;
                }

                throw new RuntimeException('KHQR order already paid with a different transaction.');
            }

            if (!in_array((string) $row->status, ['pending', 'review'], true)) {
                throw new RuntimeException('KHQR order is not eligible for settlement.');
            }

            // Unique transaction_hash prevents a successful transfer from
            // fulfilling two DIFFERENT orders, including concurrent workers.
            $changed = $this->connection
                ->table('khqr_payment_requests')
                ->where('id', $row->id)
                ->whereIn('status', ['pending', 'review'])
                ->update([
                    'status' => 'paid',
                    'transaction_hash' => $hash,
                    'paid_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            if ($changed !== 1) {
                throw new RuntimeException('KHQR settlement transition was not atomic.');
            }

            return true;
        }, 3);
    }
}

