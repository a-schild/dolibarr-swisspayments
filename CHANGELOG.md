# Changelog

All notable changes to the Dolibarr **Swisspayments** module are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/); the module
uses date‑based versions (`YYYY.M`).

## [2026.7] – 2026-07-09

Major update: migration to the current Swiss Payment Standards and a reworked
QR‑bill import flow.

### Added
- **ISO 20022 `pain.001.001.09.ch.03` (Swiss Payment Standards 2022)** payment‑file
  generation, replacing the retired `pain.001.001.03.ch.02`.
- Support for **all Swiss QR‑bill variants**: QR‑IBAN + QR reference (QRR), normal IBAN
  + Creditor Reference (SCOR / `RF…`), and normal IBAN without a reference (NON).
- **2‑step QR‑bill import wizard** with the ability to **create a new supplier** (name +
  structured address + IBAN bank account) directly from the scanned QR data.
- **Editable QR reference** with live check‑digit validation, so a mis‑scanned reference
  can be corrected before the invoice is created.
- A module **version constant** (`modswisspayments::VERSION`), reported in the payment
  file's software‑information header.
- **GitHub Actions release workflow** that builds a minimal deployable module zip on tag
  push and attaches it to the GitHub release.
- Reference XSD schemas (`tests/`) and the SIX Implementation Guidelines (`doc/`) for
  local validation, plus a `CHANGELOG.md` and developer notes (`CLAUDE.md`).

### Changed
- The module now **always emits `pain.001.001.09.ch.03`** (a date‑based cutover was
  briefly used, then removed once the bank's validator accepted the new format).
- Creditor addresses are emitted as **structured addresses** (`StrtNm`/`BldgNb`/`PstCd`/
  `TwnNm`/`Ctry`), splitting the house number into `BldgNb`.
- The "to pay" list validates each bill against what the file generator will accept
  (reference type, QR‑IBAN, and a complete post code / town), flagging and excluding
  bills that would otherwise break the export.
- The bill‑entry page is QR‑only: the legacy ESR coding‑line input was removed.
- Rewrote the README for the current module.

### Fixed
- Open‑amount bills: the entered **amount is now applied and validated** (positive,
  numeric) instead of silently creating a zero/null‑amount invoice.
- Accept QR payloads with **31 lines** (the billing‑information and alternative‑procedure
  trailer fields are optional).
- Prevent an invalid‑integer insert into `llx_swisspayments_soc` (`strpos()` returning
  `false` for QR references) that blocked invoice creation.
- Display the creditor address from its structured parts (no more raw `\n`).
- Additional pre‑payment validations and edge‑case handling.

## Earlier history (2021 – 2024)

The module was not formally versioned before 2026.7 (it declared `8.*`). Notable
milestones:

- **2024-01** – CSRF token added for the image‑upload case.
- **2022-04** – CSRF token for Dolibarr 15.x+; timestamp‑based message id; accept QR
  codes without a final CRLF.
- **2022-01** – Improved error catching and display.
- **2021-12** – First working version; updated the vendored Z38 SwissPayment library.
- **2021-08** – QR‑bill import implemented (create supplier bills from a QR code);
  layout and CR/LF fixes.
- **2021-07** – Initial import: enter Swiss ESR/PVR bills and generate DTA payment files.
