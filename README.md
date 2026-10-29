# KHQR — Universal Payment Integration SDK

**Independent PHP / Laravel payment integration toolkit · v0.0.1 (foundation)**

KHQR is a framework-independent PHP library for encoding, decoding, and validating Cambodia's Bakong KHQR payment payloads. It is designed for reusable integration with standalone PHP, Laravel, and later a separately deployed REST gateway.

| Project | Information |
| --- | --- |
| Product | KHQR |
| Version | v0.0.1 |
| Type | PHP SDK and Laravel integration package |
| Author / copyright | Ton Samith © 2026 |
| Website | https://khqr.tonsamith.com |
| Author website | https://tonsamith.com |
| Repository | https://github.com/tonsamith128/khrq |
| Open-source license | MIT |

> **Foundation release — not production-payment certified.** QR generation does not establish that funds were received. No live Bakong/bank API integration, settlement service, hosted dashboard, or live payment callback is included in v0.0.1. A merchant must perform an authorized server-side settlement check before fulfilling an order.

## v0.0.9-dev — Ubuntu 22.04 QR screenshot decoding hardening

Ubuntu 22.04's system OpenCV 4.5.4 can fail on dense QR screenshots. Use an
**opt-in isolated Python virtual environment** with pinned
`opencv-python-headless==4.13.0.92` instead; system Python is unchanged.
The private decoder attempts the original, detected QR-region crops,
grayscale and normalization within a 40-second bound. CI tests the isolated
modern version on Ubuntu 22.04 using synthetic logo screenshots.
No bank data is stored in the repo and no settlement is enabled.

## v0.0.8-dev — OpenCV fallback and reference-free bank QR safety

The private bank QR preflight tries local zbar first and **automatically
falls back to OpenCV** when zbar cannot decode a dense QR (such as a QR
with a center logo). OpenCV is an optional server dependency: install
Ubuntu's `python3-opencv` for the system `/usr/bin/python3` interpreter.

`BankQrOrderReview` can compare a real bank-issued dynamic tag-29 QR
against a trusted merchant order **offline**, but if tag 62 is absent it
will **require independent provider-approved uniqueness/attribution proof**.
Bank-specific tags 40 and 99 are treated as opaque, never as proof
of a unique paid order. No account identifiers, payloads or MD5s are
logged; automatic settlement remains disabled.

See [ABA QR local OpenCV fallback and order-proof limits](docs/aba-opencv-order-review.md).

## v0.0.7-dev — Bank-issued QR offline compatibility preflight

Inspect an actual bank-issued QR **locally** before an ABA-to-ACLEDA test.
`BankQrCompatibility` and `tools/bank-qr-offline-preflight.php` read
private decoded QR text or an optional PNG/JPEG image through local zbarimg.
They report only redacted structural flags and **never access your token,
connect to Bakong, transfer funds, or settle orders**. Static and merchant
tag 30 QRs may be bank-valid but are not currently supported by the SDK's
dynamic individual-order binding.

See [ABA + ACLEDA QR preflight](docs/aba-acleda-qr-preflight.md).

## v0.0.6-dev — Read-only real-payment acceptance foundation

The v0.0.6 development milestone binds a **real dynamic individual KHQR
payload** to a merchant-owned expected recipient, amount, currency and order
reference, then inspects a provider receipt **without updating payment state**.
Synthetic end-to-end tests exercise pending, mismatch, match-without-settlement,
repeat, and database replay protection. The SDK does not initiate transfers
or activate payment processing.

See [Payment acceptance plan](docs/real-payment-acceptance.md).

## v0.0.5-dev — Secure Bakong API connection check (merged)

After receiving an authorized NBC/Bakong API token, operators may use
a **local private-token file**, an explicit opt-in CLI and per-environment
rate limit to run a controlled read-only MD5 lookup. The tool does **not**
print tokens, make payments or mark orders settled. Network stays off until
both authorization flags are provided.

See [Token connection guide](docs/bakong-token-connection.md).

## v0.0.4-dev — opt-in Bakong HTTP adapter (merged)

A secure HTTPS-only transport is merged into `main`. It supports an authorized,
server-side MD5 lookup using an injected token, a fixed host allowlist,
strict timeouts and an atomic daily request budget. Networking remains
**disabled by default**. No bank credentials, sandbox transfers, or live
payment acceptance tests are included.

Read [Bakong HTTP integration](docs/bakong-http-integration.md) and
[Provider approval issue #3](https://github.com/tonsamith128/khrq/issues/3).

## v0.0.2 verification safety (merged)

The v0.0.2-dev foundation is merged into main, adding an offline Bakong
response mapper, an explicitly injected provider transport, strict receipt
matching, safe reconciliation decisions, and an optional Laravel
transactional ledger. **It does not include approved credentials, a
default live HTTP transport, or a production-ready payment processor.**

Read [Verification Safety v0.0.2](docs/verification-v002.md) before use. MySQL 8 race-condition tests passed in the v0.0.3 test milestone; see [MySQL Acceptance](docs/mysql-acceptance.md).

## Scope in v0.0.1

- PHP 8.2+ and Composer-first monorepo.
- EMV-style tag-length-value codec, nested template support, and CRC-16/CCITT-FALSE.
- KHQR individual account (tag 29) static or amount-bearing dynamic payload creation.
- KHR and USD formatting using integer minor units without floating-point calculations.
- Payload structural validation, CRC checking, and MD5 lookup identifier (not a security primitive).
- Provider-neutral transaction verification and payment-store contracts.
- Standalone PHP adapter and Laravel service-provider scaffold.
- Regression tests, GitHub Actions, documentation, security and contribution policies.

**Not included:** live bank verification, merchant-specific tag 30 generation, provider-issued fields, QR bitmap rendering, web dashboard, payment database schema, API gateway, registration with NBC, bank certification, or payout functions. All are future opt-in integrations.

## Directory layout

~~~text
packages/
  core/      # Pure PHP KHQR codecs, value objects, contracts
  php/       # Small convenience wrapper for native PHP
  laravel/   # Laravel service provider, config, facade
gateway/
  openapi/   # Design placeholder; no live gateway
examples/
  plain-php/
docs/
  architecture.md
  khqr-specification.md
  security.md
  installation.md
  roadmap.md
  provider-integration.md
tests/
  run.php
.github/workflows/
~~~

## Try it locally

~~~bash
git clone https://github.com/tonsamith128/khrq.git
cd khrq
php tests/run.php
# Optional: composer install
php examples/plain-php/generate.php
~~~

The example uses the repository's development autoloader and dummy account details. Replace with a legitimate account for sandbox testing and obtain approval before production use.

~~~php
use TonSamith\Khqr\Core\Khqr;
use TonSamith\Khqr\Core\PaymentRequest;

$request = new PaymentRequest(
    accountId: 'example@bank',
    merchantName: 'Example Store',
    merchantCity: 'Phnom Penh',
    currency: 'KHR',
    amountMinor: 10000,
    reference: 'ORDER-1001',
);

$result = (new Khqr())->generate($request);
echo $result->payload;       // KHQR payload string, not proof of payment
echo $result->md5;           // API lookup ID, NOT an authentication token
~~~

Set `amountMinor: null` for a static QR with no transaction amount. KHR's minor units are riel (e.g. 10000 = ៛10,000); USD has cents (10000 = $100.00).

## Integration packages

- `packages/core`: proposed future Packagist package `tonsamith/khqr-core`.
- `packages/php`: proposed `tonsamith/khqr-php`.
- `packages/laravel`: proposed `tonsamith/khqr-laravel`.

The names are reserved in this repository only; they are **not yet published** to Packagist. A monorepo split/tag workflow will be needed before publishing each nested Composer package.

## Payment safety

A scannable QR code can be paid zero, one, or more times depending on the bank and QR type. Never treat QR generation, frontend notifications, screenshots, or unverified callbacks as payment success. Verify the actual settlement via an authenticated provider, match amount/currency/recipient/reference where supported, and atomically enforce unique transaction/order settlement.

See [Security](docs/security.md), [Provider Integration](docs/provider-integration.md), and [Roadmap](docs/roadmap.md).

## Sources and independence

- [National Bank of Cambodia — Bakong](https://bakong.nbc.gov.kh/)
- [NBC KHQR guideline](https://bakong.nbc.gov.kh/download/KHQR%20Guideline.pdf)
- [NBC Bakong Open API](https://api-bakong.nbc.gov.kh/)

This is an **independent community software project** authored by Ton Samith. It is not the National Bank of Cambodia, not the Bakong payment network, and does not grant merchant onboarding, regulatory permissions, banking partnership, or KHQR brand rights.

## License

MIT for the code in this repository. See [LICENSE](LICENSE). Separate future hosted or commercial products may be licensed independently without re-licensing existing MIT-covered code.


