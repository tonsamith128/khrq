# Provider integration contract (future milestone)

v0.0.1 intentionally **does not** make live Bakong API requests.

`TransactionVerifierInterface::checkByMd5` is a seam for an adapter that may
communicate with an authorized NBC Bakong/acquirer endpoint. An implementation
must map provider payloads conservatively and must never return `CONFIRMED`
unless it has authenticated transaction evidence.

## Proposed adapter requirements

- Explicit, allowlisted HTTPS base URL (not user-controlled).
- Server-held bearer token or other approved credentials.
- Strict SSL verification, bounded connection and response timeouts.
- JSON schema validation, error code mappings, circuit breaker and logging redaction.
- Distinguish `PENDING` and `REVIEW` from actual settlement.
- Compare account, amount, currency and receipt hash with the host's order.
- Guarantee idempotent completion at the host persistence boundary.

The API's MD5 parameter is a lookup identifier derived from the QR string;
**MD5 is not a cryptographic signature or proof of payment**.

Read current provider guidelines and validate test vectors against its sandbox
before shipping an adapter. Do not persist unredacted customer data.

Reference: https://api-bakong.nbc.gov.kh/

