# Security Policy

## Supported versions

| Version | Supported |
| --- | --- |
| v0.0.1 foundation | Best-effort security fixes; NOT production settlement certified |

## Reporting

Do not publish API tokens, customer identifiers, or vulnerability details in public issues. Contact https://tonsamith.com/contact with a private report and reproduction steps. Never paste live bank credentials in GitHub issues.

## Security boundaries

- The SDK **does not** create or settle funds.
- The MD5 digest of QR payloads is a provider lookup key, not a signature.
- QR creation and successful decoding are not proof of payment.
- No production Bakong connection is made by the foundation version.
- Merchant credentials must reside in backend-controlled, non-public configuration.
- Transaction verification requires authenticated, authorized provider endpoints.
- Match destination, amount, currency and transaction reference when provider data allows it.
- Reject mismatches, replay attempts and duplicate settlement events transactionally.
- Avoid logging credentials, personal payment data, full provider responses, or raw QR values unnecessarily.
- HTTPS and certificate verification are mandatory for future network adapters.
- Audit provider-specific QR fields against the latest acquiring bank's contract.

Use this package only after independently verifying regulatory, bank, and merchant eligibility.

