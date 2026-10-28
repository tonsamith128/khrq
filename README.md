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

## v0.0.4-dev — opt-in Bakong HTTP adapter (PR candidate)

A secure HTTPS-only transport is under development on the
`feature/bakong-opt-in-http-transport-v004` branch. It supports an authorized,
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

