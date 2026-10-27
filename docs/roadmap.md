# Roadmap — KHQR

## v0.0.1 — Foundation (implemented)

- Framework-neutral TLV and CRC engine.
- Static and dynamic tag 29 KHQR request generation.
- Strict local payload validation and MD5 lookup identifier.
- Money minor-unit formatting, immutable request and result types.
- PHP factory, Laravel binding scaffold, verification/storage contracts.
- Tests, linting, CI, documentation, MIT licensing.

## v0.0.2 — PHP and Laravel integration

- Expanded service provider and Laravel testing matrix.
- Host-controlled payment repository/migrations/events, not core dependency.
- QR rendering through an optional vetted library.
- Composer subtree split and real Packagist packages.

## v0.0.3 — Payment reliability

- Bank/ NBC-authorized provider adapter, after credentials/approval.
- Sandbox acceptance tests and negative/malformed responses.
- Reconciliation, expiration, transaction deduplication and safe retries.
- Merchant-specific tag 30 adapters where officially supported.

## v0.1.0 — Optional universal gateway

- Versioned OpenAPI contract, API key auth, rate limiting.
- Audit logs, multi-tenant isolation, and platform language SDKs.
- Independently deployable service; core library still offline-capable.

## Later

- Docs/SDK portal at https://khqr.tonsamith.com.
- Optional merchant UI, approved bank integrations, reporting, and paid
  commercial modules under separate terms.

No schedule implies certification or provider endorsement.

