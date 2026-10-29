# Bakong token connection — v0.0.5-dev

**The NBC verification email confirms account registration, not live payment readiness.**

This guide prepares a one-time, read-only HTTPS request using an API token
delivered privately by Bakong. It reuses the secure transport merged in
`v0.0.4-dev`. It NEVER moves funds, invokes a payment API, or modifies any
merchant order. The CLI is deliberately disabled by default.

## Before connecting

1. Confirm the email was sent by NBC through the official service. Never
   follow unfamiliar links or send the token to a third party.
2. **Do not paste the token into ChatGPT, GitHub, Slack or CI logs.**
3. Ask your acquiring bank/PSI or NBC which token environment you were issued:
   `sit-api-bakong.nbc.gov.kh` (SIT) or `api-bakong.nbc.gov.kh` (production).
   **Production email tokens cannot be assumed to work in SIT.**
4. Verify the current API contract and permission for your intended merchant
   integration; an access token alone does not mean the integration is approved.
5. Keep customer/production payment settlement disabled. Use an **unpaid test
   dynamic KHQR** MD5 lookup, not a customer's settled order.

## Private token setup (Ubuntu/aaPanel SSH, operator-only)

Do not enter the token into shell command arguments or files served by nginx.
Run these commands on an approved machine with an interactive terminal:

~~~bash
umask 077
install -d -m 0700 "$HOME/.config/khqr"
read -r -s -p "Paste Bakong token (hidden): " BAKONG_TOKEN
printf '\n'
printf '%s' "$BAKONG_TOKEN" > "$HOME/.config/khqr/bakong.token"
unset BAKONG_TOKEN
chmod 0600 "$HOME/.config/khqr/bakong.token"
stat -c '%a %n' "$HOME/.config/khqr/bakong.token"
~~~

Never echo the token after writing. If using a different OS, use equivalent
permissions and private secret storage. The owning account must also own
the private quota directory. The PHP CLI operator should have no access to
unnecessary production databases or site credentials.

## Local preflight (NO network calls)

Run after cloning/pulling the source repository:

~~~bash
php tools/bakong-connection-check.php --help
php tools/bakong-connection-check.php --dry-run
php tests/bakong-http.php
~~~

The dry-run should show `MODE=DRY_RUN`, `NETWORK=DISABLED`,
`SETTLEMENT=DISABLED`. It neither reads the private token nor makes a request.

## Explicit SIT check

**Only after your provider confirms this token may be used on SIT**, generate
a dynamic KHQR for a controlled test account and obtain its 32-character
MD5 identifier. Replace the placeholder below with the test MD5 **only**:

~~~bash
php tools/bakong-connection-check.php \
  --execute \
  --confirm-authorized-use \
  --environment=sit \
  --token-file="$HOME/.config/khqr/bakong.token" \
  --quota-state="$HOME/.config/khqr/quota-sit.json" \
  --md5=REPLACE_WITH_32_HEX_CHARACTERS
~~~

This consumes up to one request of the token's published daily limit,
including unsuccessful attempts. **It does not settle a payment.**

## Production-endpoint connectivity check

For a token issued for `api-bakong.nbc.gov.kh`, get explicit confirmation
from NBC or your bank/PSI that a read-only MD5 lookup is permitted.
This is **not** approval to process customer payments.

Only then use:

~~~bash
php tools/bakong-connection-check.php \
  --execute \
  --confirm-authorized-use \
  --acknowledge-production \
  --environment=production \
  --token-file="$HOME/.config/khqr/bakong.token" \
  --quota-state="$HOME/.config/khqr/quota-production.json" \
  --md5=REPLACE_WITH_32_HEX_CHARACTERS
~~~

Do not run repeatedly to force a result: account limits, 429 responses and
timeouts must be handled by the provider-approved integration policy.

## Interpreting output

- `MODE=DRY_RUN` — no token access, no request.
- `NETWORK_CHECK=HTTP_200_RECEIVED` — provider returned HTTP 200, but
  **this alone does not prove a transfer or commercial entitlement**.
- `PROVIDER_OBSERVATION=pending` — transaction was not found; expected for
  an unpaid QR, but it could also be delayed.
- `PROVIDER_OBSERVATION=review` — unrecognized/malformed/ambiguous response;
  never mark paid.
- `PROVIDER_OBSERVATION=confirmed` — parsed complete transaction details,
  but the CLI does not persist or settle. A separate merchant-owned
  `SettlementMatcher` and transactional store still must verify the specific
  order's recipient, amount and currency before settlement.
- `NETWORK_CHECK=FAILED` — configuration, quota, TLS, authorization, or
  provider failure. There is no claim of successful payment.

The CLI never prints bearer tokens, hashes, recipient account IDs, amounts
or raw HTTP response bodies.

## Security and production readiness

- Production activation requires authorized bank/PSI sandbox transaction
  acceptance, key rotation, scheduled reconciliation and operational support.
- Do not place tokens in public `.env`, source files, GitHub, CI or copied
  customer release packages.
- Prefer dedicated provider merchant credentials and a purpose-limited
  non-root server account for any future background verification worker.
- Use a separate private quota-state file for every different token account
  and deployment environment, shared across workers that use that token.
- Official registration currently advertises a maximum of 100 API calls/day.
  Budget resets are measured by UTC in this SDK.
- The API v1.0.1 PDF is a 2021 document; the current portal labels itself
  v2.0.4. Verify the current API schema and merchant terms directly.

**No live connection has been made by ChatGPT.** The repo contains only
configuration tooling and offline tests. No secret was requested or stored.

