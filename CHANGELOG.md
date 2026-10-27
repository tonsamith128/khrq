# Changelog

All notable changes to KHQR will be recorded here. This is a foundation release, not an approved payment processing product.

## [Unreleased]

- Plan authenticated Bakong provider adapters and production sandbox acceptance.
- Plan provider-specific merchant tag 30 integration, QR artwork, persistence, and gateway.

## [Unreleased v0.0.3-dev]

- Add opt-in ephemeral MySQL 8 database acceptance with concurrent PHP workers.
- Verify idempotent same-order replay and database-enforced cross-order transfer-hash collisions.
- Prevent accidental test execution against production databases.
- Keep live Bakong transport and production settlement disabled.

## [0.0.2-dev] — 2026-10-28

- Add offline Bakong MD5 lookup response mapping; reject malformed/ambiguous results.
- Add expected-payment matching, reconciliation decisions and replay-aware store contract.
- Add opt-in Laravel transactional ledger and publishing-only migration.
- Add mock provider regression tests and provider eligibility documentation.
- Not yet production settlement ready; live provider transport and sandbox acceptance pending.

## [0.0.1] — 2026-10-27

- Define framework-neutral Composer monorepo and PHP/Laravel extension points.
- Implement strict TLV framing, CCITT-FALSE CRC, individual-account KHQR builder, payload verifier, and lookup ID.
- Introduce typed money conversion, transaction verifier/store interfaces, and exceptions.
- Document risk controls, installation, licensing, and roadmap.
- Add local tests, PHP lint checks, and GitHub CI.

