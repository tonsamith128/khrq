# Payment verification and security design

## Trust boundaries

1. The **browser** may request or display a QR but never confirms settlement.
2. The **merchant server** owns orders, merchant account configuration, credentials, and authorized provider integration.
3. The **authorized provider** returns transaction evidence, which must be validated against the order.
4. The **database** must atomically protect idempotent fulfillment and transaction hash uniqueness.

## Safe payment sequence

1. Generate unpredictable, unique merchant reference, store a pending order.
2. Build an amount-bearing QR for an approved merchant/account setup.
3. Render via a vetted optional QR library, over HTTPS.
4. Use an authenticated Bakong/acquirer service to check actual settlement.
5. Verify response status, amount, currency, recipient, and unique transaction hash; match merchant reference where supported.
6. In a database transaction, record settlement once and transition the order to paid exactly once.
7. If unavailable/ambiguous, remain pending or manual-review and schedule bounded reconciliation.
8. Allow late settlement review after a QR presentation timeout; do not silently discard paid funds.

Never mark a QR as paid merely because the user scanned it. A QR may be
paid more than once; duplicate and overpayment cases need a defined policy.

## API and token protections

- Separate sandbox/live credentials; encrypt at rest, never in JS or Git.
- HTTPS only; pin service host configuration and reject SSRF destinations.
- Request timeouts, retry budgets, circuit breakers, rate limits.
- Log correlation IDs, not secrets or full transaction contents.
- Verify signed webhooks if the provider offers them.
- Use redacted auditable status transitions and access control.

## Operational controls

- Production rollout requires an actual authorized account and bank approval,
  current specs, sandbox fixtures, negative testing and an operational runbook.
- Build an event replay test for duplicated callbacks and concurrent workers.
- Back up merchant payment records and encrypt sensitive fields.
- Document retention/deletion obligations and merchant consent.

See also ../SECURITY.md.

