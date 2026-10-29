# ABA receiving / ACLEDA paying — offline KHQR preflight (v0.0.7-dev)

**No bank request, payment, token use, order settlement, or customer-site deployment is performed by this milestone.**

ABA Mobile supports KHR and USD KHQR receiving from participating banks, while ACLEDA Super App supports scanning KHQR. A bank-issued QR may nevertheless use different merchant tag, reference, or account semantics than the SDK's current individual-account builder. **Always use the bank's own QR to establish the receiving account.** A valid CRC does not prove the account exists or is trustworthy.

Official product references:
- https://www.ababank.com/en/aba-mobile-app/aba-id/
- https://www.acledabank.com.kh/kh/eng/ps_ebacledamobile

## Proposed controlled test

- Receiving bank: your own ABA account.
- Sending bank: your own ACLEDA account.
- Proposed amount: KHR 1,000, only when bank apps allow that amount, applicable fees are acceptable and the bank/PSI permits a controlled test.
- Generate an ABA-issued **amount-bearing** QR via ABA Mobile > ABA QR; select KHR and set an amount. This is a bank action only — the SDK does not issue banking identifiers.
- **Do not transfer funds yet.** First confirm the ABA QR recipient and amount in ABA and in the ACLEDA preview, and inspect QR compatibility offline.

## Inspect the bank-issued QR offline

Do not share banking QR payloads, receiving identifiers or screenshots in GitHub, chat or online QR decoders. The tool expects a local private file inside a 0700 directory, with file mode 0600.

### Raw decoded QR text

If your bank/scanner can copy the **raw KHQR payload** as text, save it securely:

~~~bash
umask 077
install -d -m 0700 "$HOME/.config/khqr"
bash -c 'IFS= read -r -s -p "Paste decoded bank QR text (hidden): " RAW_QR; printf "\n"; printf "%s" "$RAW_QR" > "$HOME/.config/khqr/aba-qr.txt"; unset RAW_QR'
chmod 0600 "$HOME/.config/khqr/aba-qr.txt"

cd /root/khqr-bakong-check
php tools/bank-qr-offline-preflight.php --text-file="$HOME/.config/khqr/aba-qr.txt"
~~~

### PNG or JPEG QR image

Privately copy the image from your device to an **unserved** file such as `/root/.config/khqr/aba-qr.png` (SCP is suitable). The optional image decoder is `/usr/bin/zbarimg` from Ubuntu's `zbar-tools` package. Install this server tool **only if you approve it**. It works locally; no online decoding service.

~~~bash
umask 077
install -d -m 0700 "$HOME/.config/khqr"
chmod 0600 "$HOME/.config/khqr/aba-qr.png"
cd /root/khqr-bakong-check
php tools/bank-qr-offline-preflight.php --image-file="$HOME/.config/khqr/aba-qr.png"
~~~

If zbarimg is unavailable, the tool reports `ERROR=LOCAL_ZBARIMG_NOT_INSTALLED` and makes **no bank network request**.

## Results (privacy-safe)

- `QR_PREFLIGHT=COMPLETED_OFFLINE`: structurally recognized KHQR with a valid CRC; **not** evidence of bank account validity.
- `ACCOUNT_TEMPLATE=INDIVIDUAL_TAG_29`: individual account encoding.
- `ACCOUNT_TEMPLATE=MERCHANT_TAG_30`: bank merchant encoding; needs an approved provider-specific account adapter for our order-binding workflow.
- `PAYMENT_METHOD=STATIC`: our dynamic order-reference payment checker is **not** eligible, even if the bank QR itself is valid.
- `CURRENT_QR_BINDING_COMPATIBLE=YES`: only confirms required tag 29, dynamic amount and reference structure is present. Before verification, still compare exact recipient, amount, currency and reference with a **trusted, persisted order**.
- `CURRENT_QR_BINDING_COMPATIBLE=NO`: do **not** modify or forge a bank QR to force acceptance; use bank/PSI-supported QR integration.
- `PROVIDER_VERIFICATION=NOT_EXECUTED` and `PAYMENT_SETTLEMENT=DISABLED` always.

Output never contains account IDs, names, reference, amount, QR payload, or MD5 lookup identifier.

**No payment should be initiated until provider permission, bank-app recipient verification, and a controlled test plan are all confirmed.** The prior successful HTTP 200 / pending result was only for an unpaid test identifier. This milestone does not permit customer payment settlement.

## Offline tests

~~~bash
php tests/bank-qr-preflight.php
php tests/read-only-acceptance.php
php tools/bakong-connection-check.php --dry-run
~~~

