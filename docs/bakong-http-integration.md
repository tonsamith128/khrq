# Bakong HTTP integration — candidate v0.0.4-dev

**Status: opt-in network transport available for authorized evaluation.**
**Not certified; no live verification performed in CI and no banking access is implied.**

The project implements a deliberately limited HTTP adapter for the existing
`BakongTransportInterface`: MD5 transaction lookup only. It is not a payment
processor, hosted service, token registration portal, account management
solution or a general-purpose HTTP proxy.

## Verified protocol basis

The official [NBC Bakong Open API v1.0.2 specification](https://bakong.nbc.gov.kh/download/KHQR/integration/Bakong%20Open%20API%20Document.pdf)
(2021, pages 13–15) documents:

- HTTP POST `/v1/check_transaction_by_md5`
- `Authorization: Bearer <access token>` on the merchant's backend
- `Content-Type: application/json`; request JSON `{"md5":"<32 hex>"}`
- `responseCode=0` transaction found; code 1/error code 1 not found
- Success data: hash, receiver account, currency, amount, and other details

The current public [Bakong registration page](https://api-bakong.nbc.gov.kh/register)
advertises **100 calls/day**. Historical documentation does not prove current
commercial eligibility or current bank/PSI schema. Confirm with an authorized
provider **before** using merchant funds or treating a receipt as final.

## Safety gates

- `networkEnabled=false` by default, even when a token is present
- exact allowlisted HTTPS provider origin; no IP, localhost, custom port,
  embedded user credentials, query string or arbitrary path
- no redirects, explicit cURL proxy bypass, certificate and host verification
- connection and total timeouts; response capped at 32 KiB
- backend-provided token only; never sent to JavaScript, logs, URLs or QR
- explicit non-webroot 0700 directory for UTC daily budget with flock
- at most 100 requests per UTC day per shared quota state; no automatic retry
- HTTP errors, invalid content type or transport errors throw and **do not**
  mark orders paid
- responses remain untrusted until `BakongResponseMapper`,
  `SettlementMatcher` and the atomic `PaymentStoreInterface` have validated
  and persisted settlement evidence

Requests that fail or time out conservatively consume the quota. Reset is at
UTC midnight (which is 07:00 in Cambodia), not local midnight. Use the same
private quota state for all PHP processes that share a provider account.

## Installing in a host application

This code lives in `packages/bakong` and is **not yet released on Packagist**.
Requirements: PHP 8.2+, cURL extension with HTTPS support. The core SDK has
no cURL dependency. Example shows dependency composition, **not instructions
to activate a commercial gateway**.

~~~php
use TonSamith\Khqr\Bakong\BakongMd5Verifier;
use TonSamith\Khqr\Bakong\Http\AuthorizedBakongTransport;
use TonSamith\Khqr\Bakong\Http\BakongHttpOptions;
use TonSamith\Khqr\Bakong\Http\CurlHttpClient;
use TonSamith\Khqr\Bakong\Http\FileDailyQuota;

$quota = new FileDailyQuota('/private/outside-webroot/khqr/quota.json', 100);
// Admin must first create /private/outside-webroot/khqr with mode 0700.

$transport = new AuthorizedBakongTransport(
    new BakongHttpOptions(
        baseUrl: 'https://api-bakong.nbc.gov.kh',
        networkEnabled: false, // Enable ONLY with explicit provider approval.
        allowedHosts: ['api-bakong.nbc.gov.kh'],
    ),
    new CurlHttpClient(),
    $quota,
    static fn (): string => (string) (getenv('BAKONG_API_TOKEN') ?: ''),
);

$verifier = new BakongMd5Verifier($transport);
// Even with a token, any request will FAIL until networkEnabled is explicitly true.
~~~

When authorized, an application owner may deliberately use
`networkEnabled: true` *with a permitted environment and token*; the module
never enables itself through a browser request. For a bank/PSI endpoint,
provide the bank-approved HTTPS origin and **matching explicit allowedHosts**
configured by the trusted server operator. Do not use an arbitrary URL from
API requests. Never store real tokens in GitHub, CI logs or sample files.

## Quota state creation (on approved non-production backend)

~~~bash
install -d -m 0700 /private/outside-webroot/khqr
# PHP user must own the directory and be able to create quota.json
# Never place the quota file in public/ or under a served site.
~~~

Do not use the public daily quota as a high-volume production merchant polling
solution. Obtain licensed bank/PSI support and an authorized provider rate
contract for commercial transaction verification.

## Offline tests

~~~bash
php tests/bakong-http.php
php tests/run.php
~~~

All HTTP tests use an injected mock client; CI never contacts NBC. A separate
provider-authorized sandbox acceptance workflow must be constructed with
private credentials (ideally using a short-lived secret vault), a dedicated
merchant test account, and no production records.

## Explicit outstanding work

- Obtain confirmation of the **current** NBC or bank/PSI contract and API version
- Obtain sandbox credentials through approved procedures
- Test actual TLS connection and transaction responses in bank sandbox
- Record bank-specific idempotency, payer/recipient identity and statuses
- Implement token rotation and quota budgets suited to provider agreement
- Add reconciliation worker/alerting after authorized sandbox acceptance
- Separate commercial bank connector from public developer API policy

GitHub Issue #3 tracks these provider prerequisites.

