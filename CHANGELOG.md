# Changelog

All notable changes to KHQR will be recorded here. This is a foundation release, not an approved payment processing product.

## [Unreleased v0.0.10-dev — Read-only ABA/ACLEDA receipt verification]

- Add provider-neutral, offline read-only bank QR → order → receipt review.
- Reference-free ABA QR always reports UNBOUND, even for matching provider receipt.
- Distinguish pending/review/QR mismatch/unsupported from matched-but-unsettled.
- Detect same QR, bank transfer hash and merchant order claimed multiple times.
- Add synthetic negative, replay/collision and no-order-correlation tests.
- Document required bank/PSI account-eligibility and transaction-ID confirmation.
- No real transfer, bank request, token handling or auto-settlement activation.

## [Unreleased v0.0.9-dev — OpenCV 4.5 screenshot decoding hardening]

- Add multi-pass, offline OpenCV decoding for bank-issued QR screenshots with a central logo.
- Preserve private-file permissions and discard decoder errors; no QR payload, account identifier or token in CI.
- Bound OpenCV subprocess with system timeout and retain fail-closed semantics.
- Test modern OpenCV 4.13 inside a private Ubuntu 22.04 virtual environment;
  Ubuntu's OpenCV 4.5 failed the dense synthetic check and is not relied upon.
- No bank call, payment transfer or settlement enabled.

## [Unreleased v0.0.8-dev]

- Optional offline OpenCV fallback for center-logo/high-density bank-issued QR images.
- Preserve private file checks and redact decoded contents; no bank HTTP requests.
- New fail-closed, read-only bank-issued QR to trusted-order comparison.
- Tag-62-free bank QR remains unbound until bank-approved uniqueness evidence exists.
- No assumptions about ABA proprietary tags 40/99; no production settlement enabled.
- Synthetic OpenCV decoder and offline order-review regression tests.

## [Unreleased v0.0.7-dev]

- Add offline privacy-preserving bank-issued QR compatibility inspection.
- Distinguish individual/merchant templates, static/dynamic mode, currency,
  amount/reference presence and current SDK order-binding eligibility.
- Add private text and optional local image decoder, no online decoding.
- Test invalid QR, unsupported bank template, private-file permissions and
  redacted CLI output; no bank network call, token use or transfer.

## [Unreleased v0.0.6-dev]

- Add verified dynamic individual KHQR → trusted order expectation binding.
- Add read-only provider response inspector that can MATCH but NEVER settle.
- Add synthetic bank-response matrix covering match, pending, recipient/amount/currency mismatch, and ambiguous status.
- Add full mocked-provider → SQLite reconciliation ledger tests, including replay/cross-order collision rollback.
- Document actual small-transfer acceptance gates; no money moved or live bank testing in CI.

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


