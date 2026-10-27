# Roadmap — KHQR

## v0.0.1 — Foundation (merged to main)

- Framework-neutral TLV and CRC engine.
- Static and dynamic tag 29 KHQR request generation.
- Strict payload parsing and QR MD5 lookup identifier.
- Core settlement and storage contracts; PHP and Laravel adapter scaffolds.
- Offline regression tests and GitHub CI.

## v0.0.2-dev — Payment verification safety (feature branch / review)

- Provider-neutral expected-payment model and receipt matcher.
- Offline Bakong response adapter with fail-closed pending/review handling.
- Explicit transport boundary (no built-in live HTTP or bank credentials).
- Reconciliation decisions, atomic Laravel payment-store example and
  manually published optional migration.
- Mocked provider and replay/mismatch test cases.

## Next (v0.0.2 acceptance)

- Run isolated Laravel + MySQL integration tests (including concurrent settlement).
- Obtain authorized NBC/acquirer sandbox approval and credentials.
- Implement HTTPS transport with endpoint allowlist, TLS checks, quotas and
  bounded retries, outside the framework-neutral core.
- Verify static/dynamic tags with current bank-specific official test vectors.
- Publish Composer subtree packages after version-split acceptance.

## Later v0.0.3+

- Broker-specific reconciliation, expiry and late-payment support.
- Merchant-specific tag 30 adapters, if permitted by provider.
- Signed webhook adapters where supported, refund/reversal reconciliation,
  richer merchant events and PHP 8.5 CI coverage.

## Future v0.1.0

- Independently deployed optional REST gateway, OpenAPI spec and SDKs for
  JavaScript, Python and Java.
- API authentication, isolation, rate limits and a separately licensed UI.

A developer milestone is **not** live payment certification. Never mark an
order paid without independent, authenticated settlement evidence.

