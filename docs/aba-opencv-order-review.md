# ABA bank QR: local OpenCV fallback + order attribution (v0.0.8-dev)

This is a **privacy-preserving offline diagnostic and read-only order review**.
It does not prove a payment or bank account's authenticity and does not enable
automatic merchant settlement.

## Problem and confirmed offline observation

A bank-issued screenshot containing a high-density, center-logo KHQR may be
legible with OpenCV even when the Ubuntu zbar scanner fails. The operator's
previous production Bakong HTTP 200/pending was from an **unpaid probe**, not a
successful transfer.

The ABA screenshot under review had the following **structure only**:
individual tag 29, dynamic, KHR, a fixed amount, no order-reference tag 62,
and opaque fields tagged 40/99. Bank account and QR payload data are not
copied into this repository and are not part of CI tests.

**Do not infer that tag 40 or 99 is a one-time payment identifier.** Only the
bank or authorized provider can explain the current semantics.

## Installing the optional decoder on Ubuntu 22.04

The server already has zbar-tools installed. The offline fallback needs the
system Python interpreter's OpenCV package:

~~~bash
sudo apt-get update
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends python3-opencv
/usr/bin/python3 -c 'import cv2; print("OPENCV=AVAILABLE")'
~~~

Note that a separately installed Python (for example, /usr/local/bin/python3)
may not see Ubuntu's cv2 package. This tool intentionally uses
`/usr/bin/python3`.

The standard Ubuntu 22.04 package is available from the official repository
(`python3-opencv`). No public online QR decoding site is used.

## Run using a local private screenshot

Never put a bank QR payload, screenshot, receiving account identifier or
token on GitHub, in chat, or under a web-accessible directory.

~~~bash
cd /root/khqr-bakong-check
git pull --ff-only
chmod 0600 /root/.config/khqr/aba-qr.png

/www/server/php/85/bin/php tools/bank-qr-offline-preflight.php \
  --image-file=/root/.config/khqr/aba-qr.png
~~~

The file must be regular and in a private directory (0700). The CLI tries
zbar, then local OpenCV when zbar cannot decode. QR data is passed only in
private subprocess pipes and is never printed. Only safe flags are shown:

- `QR_PREFLIGHT=COMPLETED_OFFLINE`
- `LOCAL_DECODER=OPENCV` (if fallback was used)
- `ACCOUNT_TEMPLATE=INDIVIDUAL_TAG_29`
- `PAYMENT_METHOD=DYNAMIC`
- `REFERENCE_EMBEDDED=NO`
- `CURRENT_QR_BINDING_COMPATIBLE=NO`
- `BANK_EXTENSION_TAGS_PRESENT=YES`
- `REFERENCE_FREE_ORDER_REVIEW=PROVIDER_UNIQUE_BINDING_REQUIRED`
- `PROVIDER_VERIFICATION=NOT_EXECUTED`
- `PAYMENT_SETTLEMENT=DISABLED`

These flags describe the **format**, not the bank account's authenticity.

## What the new BankQrOrderReview does

The SDK now has a read-only `BankQrOrderReview::inspect` helper accepting the
raw QR payload and a trusted, independently persisted `ExpectedPayment`:

1. Validates the KHQR structure and CRC.
2. Requires dynamic individual-account tag 29 with amount.
3. Compares exact original payload MD5 (lookup ID), recipient, currency,
   and integer minor-unit amount against the trusted order.
4. If tag 62 is absent, returns `PROVIDER_UNIQUE_BINDING_REQUIRED` even if
   all fields match — regardless of proprietary bank fields.
5. If tag 62 matches, returns `REFERENCE_MATCH_NOT_SETTLED`, which is
   **not proof of payment**. Receipt verification and idempotent settlement
   remain independent processes.
6. Never makes network requests, reads a token, writes a database or moves
   money.

This prevents an ABA QR for the same amount and account from being incorrectly
used to mark more than one order paid. The SDK does **not** assume an opaque
tag 99 value is a unique transfer or order ID.

## Remaining provider dependency

Before a real ABA → ACLEDA transfer test or live payment acceptance, confirm
with ABA/NBC or an authorized PSI whether these QR fields guarantee uniqueness,
how an actual bank transaction's immutable receipt ties to a specific request,
and what merchant-account usage/limits apply. **Until there is a documented
provider attestation and database uniqueness design, the reference-free QR
must not automatically settle any merchant order.**

The real ABA screenshot and its account/QR data are **not** committed to GitHub.
No user bank API token is copied to this feature.

