# Real payment acceptance plan — v0.0.6-dev

**Status: code acceptance only. No production payment was initiated, received,
settled, or enabled by this development milestone.**

The KHQR project's earlier operator-performed API probe returned HTTP 200
and `pending` for an **unpaid synthetic test identifier**. That was an API
connectivity test, not a paid-transfer receipt.

## What this milestone implements

- `ExpectedPaymentFromQr` decodes a dynamic KHQR, validates CRC and binds
  its exact payload MD5 to **independent merchant-owned order data**:
  recipient account, amount in minor units, currency and order reference.
- Only **tag 29 individual-account dynamic QR with an explicit reference**
  is accepted. Merchant tag 30 semantics are provider-specific and must be
  separately confirmed; no generic merchant QR is certified.
- `ReadOnlyPaymentInspector` takes a bank/provider observation and returns
  `PENDING`, `REVIEW`, or `MATCHED_NOT_SETTLED`. It has **no database store**,
  so even a confirmed-looking receipt cannot mark an order paid.
- A separate mocked-provider → Laravel SQLite ledger test exercises actual
  `ReconciliationService`, settlement after complete match, idempotent replay,
  and rejection of a duplicated transaction hash for a second order.

The included tests use **only synthetic fixtures**, never bank credentials or
real customer records.

## Gate for a genuinely paid trial transaction

1. Obtain explicit permission from the intended bank/PSI or Bakong provider
   for a controlled small-value verification test; confirm the current API
   contract, allowed recipient accounts and token environment.
2. Verify that you **control the receiving account** and have a separate
   authorized payer account; choose a low-value, voluntary test amount.
   Do not enter the account number, personal information or token into chat.
3. Generate a one-time **dynamic, amount-bearing individual-account KHQR**
   with an order reference, and inspect its recipient and amount in the
   participating bank application. A valid CRC alone does not prove the
   account is genuine or that the QR is accepted by every bank.
4. Persist the trusted order reference, payload MD5, exact recipient,
   currency and amount in a private test database; verify the QR matches
   the trusted order using `ExpectedPaymentFromQr`.
5. If authorized, make **one manual test transfer** from the separate payer
   account through the official bank application. The SDK does not initiate
   payment and must not auto-charge anyone.
6. Perform a provider-authorized MD5 status lookup within the permitted
   daily quota. The existing private-token CLI prints only a coarse provider
   status and **does not** compare order details or settle it.
7. When a receipt is reported, use `ReadOnlyPaymentInspector` to compare
   it with the persisted expectation. Treat PENDING/REVIEW as **unpaid**.
   MATCHED_NOT_SETTLED means the observation matched, **not** that a
   merchant order was fulfilled.
8. Separately compare the result with the receiving bank's own transaction
   record, including timing, recipient, amount, currency and transaction ID.
   Test repeated lookups and attempted cross-order transfer reuse.
9. Record only redacted diagnostic results. Never upload live bank response
   bodies, transfer identifiers, wallet names or bearer tokens to CI/GitHub.
10. Obtain provider sign-off and operational review before enabling the
    optional `ReconciliationService` or live merchant orders.

### Current boundary

The repository does **not** have a licensed acquiring-bank partnership,
bank sandbox end-to-end acceptance evidence, a bank-backed refund or reversal
policy, or a confirmed reference/identity mapping for merchant tag 30.
A token by itself is not proof of commercial approval.

## Run offline acceptance

~~~bash
cd /root/khqr-bakong-check
php tests/read-only-acceptance.php
php tests/bakong-http.php
php tools/bakong-connection-check.php --dry-run
~~~

The full synthetic Laravel acceptance test also runs in GitHub Actions,
using an ephemeral SQLite database and development-only Illuminate packages.
Do not run it against the production database or real customer orders.

**No new Bakong API lookup is necessary to evaluate this milestone.**

