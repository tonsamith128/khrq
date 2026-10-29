# KHQR v0.0.10-dev — ABA/ACLEDA read-only receipt review

**Development-only. No bank transfer or provider network request was
performed for this milestone. No live order settlement is enabled.**

## Current confirmed observations

- The operator's own ABA-issued 1,000 KHR QR was inspected **privately**
  using OpenCV 4.13 on Ubuntu; the account identifier and QR body are
  NOT in the repository.
- It is a valid **dynamic individual tag 29** KHQR, with amount and
  proprietary tags 40 and 99, **without tag 62 order reference**.
- An earlier authenticated Bakong production API lookup yielded
  **HTTP 200 / pending for an unpaid synthetic identifier**. This proves
  connectivity only; it is not a real payment receipt.
- An ABA QR without explicit order correlation cannot safely identify
  a unique merchant order merely from amount, recipient or MD5.
- Whether the operator's ABA account is personal or a merchant account
  **has not been established**. The project must not assume merchant API
  entitlements or permission for commercial processing.

## What v0.0.10 actually adds

\`ReadOnlyBankReceiptReviewer\` accepts:

1. An exact bank-issued QR payload, retained privately.
2. \`ExpectedPayment\` previously loaded from a merchant-owned trusted
   order database. Never trust user/browser-provided expected values.
3. \`TransactionLookup\`, derived from an independently authenticated
   provider response whose provenance and schema must be validated
   before entering this library. This component **does not authenticate
   the origin of a PHP object**.

The reviewer:

- Validates KHQR structure, CRC, dynamic/individual template, original
  QR MD5, and expected recipient, currency and exact minor-unit amount.
- Separately matches the observed transaction hash, recipient, amount,
  currency and confirmed provider status.
- Returns **REFERENCE_FREE_UNBOUND** if tag 62 is missing. This remains
  unbound even when a matching confirmed provider transfer is observed.
  Tags 40/99 remain opaque. Never infer order identity from them.
- Returns **REFERENCE_MATCH_NOT_SETTLED** for a fully matched reference
  QR and provider observation. Even this is NOT proof an order has been
  marked paid. No database mutation exists in this class.
- Reports PENDING, REVIEW, QR mismatch and unsupported QR safely.
- \`reviewBatch\` detects duplicate QR MD5, duplicate provider hash and
  duplicate merchant order among operator-selected claims. All duplicate
  claims remain in REVIEW. **This in-memory check is not a cross-worker
  or durable uniqueness guarantee.** Use database unique constraints
  and transactions at any eventual production settlement boundary.

No CLI has been added that reads a real provider response or makes live
bank requests. The existing production token continues to be held in the
server's private file; no secret is accessed by these tests.

## Source of provider facts vs unknown bank semantics

The National Bank of Cambodia Open API portal currently offers lookup
categories including MD5, full hash, instruction reference and external
reference. That **does not establish** the bank's QR tag-40/tag-99 contents
or guarantee a given lookup is a unique commercial payment request.

- Portal: https://api-bakong.nbc.gov.kh/
- Official QR payment documentation:
  https://bakong.nbc.gov.kh/download/KHQR/integration/QR%20Payment%20Integration.pdf
- Official MD5 API description:
  https://bakong.nbc.gov.kh/download/KHQR/integration/Bakong%20Open%20API%20Document.pdf

Provider/ABA/PSI sign-off is needed for:
- Account eligibility: personal receiving vs registered merchant.
- Correct field mapping for the current ABA QR and bank/provider response.
- A bank-attested **immutable payment request/transaction identifier**
  that binds a bank receipt to **exactly one merchant order**.
- Payment timestamp, late/duplicate/reversal/refund semantics.
- Whether a transaction may be inspected and reconciled in production.
- Provider rate limits, banking operations and data retention.

## Safe next acceptance sequence

1. Confirm the bank account type and actual allowed use with the bank.
2. Request the bank/PSI's **current written** instructions for QR request
   identifiers and provider receipt attribution; do not send a token.
3. Keep the real QR and original bank records private and outside GitHub.
4. Run synthetic offline tests below; share only the pass/fail result.
5. After provider authorization, design an explicit *merchant-issued*
   unique payment request (if supported), verify with the bank app,
   and request a read-only receipt test.
6. A real ABA-to-ACLEDA small transfer, if performed manually after
   approval, is a **separate acceptance step**. This milestone does not
   initiate or validate any actual funds.
7. Review idempotence in an isolated database and receiving-bank ledger.
   Do not enable the optional legacy \`ReconciliationService\` on a
   tag-62-free QR; it does not establish order attribution by itself.

## Run the new offline tests

~~~bash
cd /root/khqr-bakong-check
php tests/bank-receipt-review.php
php tests/bank-issued-order-review.php
php tests/read-only-acceptance.php
php tools/bakong-connection-check.php --dry-run
~~~

The tests use fabricated \`receiver@bank\` values and synthetic 64-hex
hashes. No bank account, QR payload, bearer token, provider raw response,
or personal identifiable information is logged or checked in.
