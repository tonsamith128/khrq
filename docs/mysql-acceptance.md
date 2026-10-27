# MySQL 8 acceptance — test-only milestone

The isolated MySQL 8 CI job exercises the **Laravel payment storage adapter**, not the Bakong network.

## Coverage

- Fresh Laravel schema migration on an ephemeral MySQL 8 test database
- Two PHP worker processes racing to settle **the same** merchant order
- The same provider transfer hash attempting to settle **two different** orders simultaneously
- Wrong MD5 lookup key and incorrect recipient fail closed
- Transaction failure leaves order pending

Expected results: one new settlement per order/transaction, a repeat on the same order is idempotent, and reuse of the same transaction hash on a different order is rejected by database uniqueness. Database constraints are the final protection, not client-side locks.

## Safety

Only the isolated database named exactly `khqr_test` is permitted, and execution requires `KHQR_ACCEPT_ISOLATED_TEST_DB=YES`. Do **not** run the test runner against real merchant environments, payment data or production MySQL. This suite drops and recreates its own test ledger table.

CI credentials are throwaway and have no access to real financial accounts.

## Remaining before real merchant usage

- Authorized provider integration, with contractual/sandbox approval
- Verify actual NBC/acquirer response schema and recipient identity
- Bank sandbox transfer lifecycle including late settlement, overpayment and reversal handling
- A bounded, quota-aware transport with verified TLS and safe credentials
- Operational reconciliation schedule, monitoring, event audit, retention and rollback runbooks
- Additional MySQL compatibility and Laravel version testing

This milestone is NOT certification, a bank payment test, or permission to process customer money.

