# KHQR protocol notes

**Implementation notes, not a substitute for current NBC/acquiring-bank specifications.**

## TLV framing

An EMV-style field is `ID(2 ASCII digits) + LENGTH(2 ASCII digits) + VALUE(length bytes)`.
Every field length must be 0..99 **bytes**, not Unicode code points.
A document may contain nested TLV templates; template framing must validate
the full inner content, without unparsed trailing bytes.

Root fields used in v0.0.1:

| ID | Usage |
| --- | --- |
| 00 | Payload format indicator `01` |
| 01 | Point of initiation: `11` static, `12` dynamic |
| 29 | Individual account information; child 00 contains Bakong account identifier |
| 52 | Merchant category code (defaults to `5999`) |
| 53 | Currency: `116` for KHR; `840` for USD |
| 54 | Dynamic amount in major currency units |
| 58 | Country `KH` |
| 59 | Merchant name |
| 60 | Merchant city |
| 62 | Optional reference template; child 01 for bill/reference |
| 63 | Final CRC, four uppercase hexadecimal characters |

Merchant tag 30 and bank-defined fields are **not generated** by v0.0.1.
Never invent bank-assigned MID, merchant identifier, or proprietary fields.

## CRC-16

Algorithm: CRC-16/CCITT-FALSE, polynomial 0x1021, initial 0xFFFF.
CRC includes the root payload through the literal header `6304`, excluding
the CRC's four hexadecimal characters. Store as uppercase hexadecimal.

## Money

Amounts are represented as **integer minor units**. KHR has zero decimals;
USD has two. The generator produces an integer amount for KHR and a
two-decimal major-unit amount for USD. It rejects negative and zero dynamic
amounts. It avoids floating-point rounding.

## Validation

Decoder verifies TLV syntax, unique root fields, mandatory tag values,
tag 29's valid nested structure, field order (63 last), CRC, and amount rules.
A structurally valid QR **is not** settlement proof.

## Protocol sources

- NBC KHQR Guideline: https://bakong.nbc.gov.kh/download/KHQR%20Guideline.pdf
- Cambodia KHQR code specification: https://compendium.fsa.gov.kh/pdf/compendium/ANN/298_ANN_351_26_06_2020.pdf
- NBC Bakong Open API: https://api-bakong.nbc.gov.kh/

Review against current revisions and the acquiring bank's rules before
enabling production use.

