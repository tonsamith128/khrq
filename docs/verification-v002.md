# KHQR verification safety — v0.0.2 development milestone

**Status: review candidate. Not yet cleared for live transactions.**

The Bakong API protocol defines a POST to §/v1/check_transaction_by_md5§,
with JSON body containing §md5§ and a server-held §Authorization: Bearer§ token.
The current registration portal states a limit of 100 requests per day.
Merchant/commercial usage eligibility must be confirmed with an authorized bank
or payment service provider before production use.

Official reference (historical API specification):
https://api-bakong.nbc.gov.kh/Bakong%20Open%20API%20Docs%20v1.0.1.pdf
Current registration portal: https://api-bakong.nbc.gov.kh/register

## Implemented

- §packages/bakong§: strict offline response mapper and an interface for an
  injected, authenticated provider transport. **No default HTTP client or
  API credential is shipped.**
- §ExpectedPayment§: canonical expected order reference, QR MD5, recipient,
  integer minor-unit amount and currency.
- §SettlementMatcher§: strict receipt vs. expected-order comparison.
- §ReconciliationService§: pending / review / settled / already-settled decisions.
- Laravel optional migration and an idempotent, transactional payment store.
- Mock-provider tests with negative cases.

## Transaction statuses

| Provider observation | Decision | Effect |
| --- | --- | --- |
| Lookup not found (errorCode=1) | Pending | No settlement |
| API failed / malformed / ambiguous | Review | No settlement |
| Complete provider success but details mismatch | Review | No settlement |
| Verified success with matching expectation and unique hash | Settled | Atomic mark paid |
| Repeat of same verified transaction on same order | Already settled | No duplicate fulfillment |

HTTP failures MUST NOT be transformed into successful statuses. The production
implementation must enforce request budget, secure tokens, retry policy, and
periodic reconciliation through an authorized payment provider.

## Required host flow

1. Validate incoming order and construct dynamic KHQR.
2. Persist §ExpectedPayment§ and its QR MD5 using §createPending§ or an equivalent secure store before displaying QR.
3. A worker loads expectation **from its own database**; never take expected
   amount, recipient or MD5 from the browser.
4. Construct §BakongMd5Verifier§ with a transport that was separately
   approved to use an authenticated provider.
5. Run §ReconciliationService::reconcile($expected)§.
6. Publish order-paid / fulfill only on a newly persisted SETTLED decision.
7. Audit review/pending decisions and retry with a quota-aware scheduler.

## Laravel host setup (opt-in)

Once both core and Laravel packages are installed as Composer path packages
in a Laravel 10/11/12 app, manually publish configuration and migration:

~~~bash
php artisan vendor:publish --tag=khqr-config
php artisan vendor:publish --tag=khqr-migrations
# review the migration and configure database backups BEFORE running
php artisan migrate
~~~

Do not run migrations against customer production data before isolated MySQL
integration tests. The included store is a **single-ledger example**; tenants
must namespace order references and account data or provide their own store.

## Integration warning

The GitHub CI runs offline core and provider contract tests. It does not
connect to NBC/banks, simulate a real bank transfer, or test the optional
Laravel migration on a full Laravel application. Production activation
requires separately controlled sandbox testing and an approval gate.

