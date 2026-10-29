# Changelog

All notable changes to KHQR will be recorded here. This is a foundation release, not an approved payment processing product.

## [Unreleased hotfix — PHP 8.5]

- Remove deprecated `curl_close()` invocation in Bakong HTTPS transport.
- Let PHP's `CurlHandle` object clean up automatically, with explicit final-scope release.
- Expand CI coverage to PHP 8.5 and guard against the deprecated call.
- Confirmed read-only production MD5 connectivity by operator (HTTP 200, pending).
  This does NOT demonstrate a paid transaction or activate settlement.

## [Unreleased v0.0.5-dev]

- Add a security-checked private token reader (0600 regular file in 0700 directory).
- Add default-off local CLI for SIT/production endpoint connection checks.
- Require explicit provider approval confirmation before consuming an API request.
- Never display tokens, full provider responses, account details, or modify ledger state.
- Add token-permission and no-network regression tests across PHP versions.

## [0.0.4-dev]

- Add explicit network-enable gate, HTTPS-only fixed endpoint allowlist and secure cURL transport.
- Add backend token supplier, no redirect, no proxy, bounded timeouts and response size limit.
- Add locked UTC-day file quota state (100 calls/day maximum).
- Add mocked transport security tests across PHP 8.2/8.3/8.4.
- Keep live bank testing, commercial approval and production rollout out of scope.

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

