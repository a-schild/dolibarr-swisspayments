# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A **Dolibarr ERP/CRM external module** (`swisspayments`) that automates Swiss supplier-payment workflows:

1. **Read a bill** — parse a Swiss payment reference (legacy (V)ESR/PVR coding line, a QR-bill payload, or an IBAN) and create a Dolibarr supplier invoice (`FactureFournisseur`) from it.
2. **Pay bills** — select open supplier invoices, batch them, and export a bank payment file (legacy DTA / EZAG format and modern ISO 20022 pain.001 XML) for upload to a Swiss bank.

There is **no build, test, lint, or dependency-install step**. This is plain PHP deployed into a running Dolibarr install. To "run" it you copy/symlink the repo into Dolibarr's module directory and enable the module in the Dolibarr admin UI (Setup → Modules → Financial section). PHP 8.3 is available at `c:\laragon\bin\php\php-8.3.28-Win32-vs16-x64`.

## Deployment layout (important gotcha)

The module lives under Dolibarr's `htdocs/` as either `htdocs/swisspayments/` **or** `htdocs/custom/swisspayments/`. The codebase is **inconsistent** about which it assumes:

- `swisspayments.php`, `createinvoice.php`, `class/swisspayments.class.php` include via `/swisspayments/...`.
- `dtapayments.php`, `dtafile.php`, `mobileqr.php`, `class/swisspaymentspayh.class.php` hard-code `/custom/swisspayments/...`.

When editing include/`dol_include_once` paths, match the convention already used by neighbouring files in that page's call chain rather than "fixing" one in isolation — changing it can break the deployment the file was written for. Entry pages load Dolibarr with the modulebuilder multi-attempt sequence (`$res = @include ...` trying `CONTEXT_DOCUMENT_ROOT`, a path derived from `SCRIPT_FILENAME`, then `../`, `../../`, `../../../main.inc.php`, `die()` on failure) — this is required by the Dolistore package validator and works whether the module sits in `htdocs/` or `htdocs/custom/`. Do not revert an entry page to a single `require '../../main.inc.php';`. Likewise, module `lib/`/`class/` files must be pulled in with `dol_include_once('/…')` (or `dol_include_once('/custom/swisspayments/…')` to match neighbours), never `require_once(DOL_DOCUMENT_ROOT . '/custom/swisspayments/…')` — the validator rejects the latter.

## Architecture

### Module descriptor
`core/modules/modSwisspayments.class.php` is the Dolibarr module descriptor (module id `112001`, `rights_class = 'swisspayments'`). It declares:
- Two permissions: `invoices->create` ("Rechnungen einlesen") and `paydta->dopay` ("Rechnungen bezahlen"). Every entry page checks these, e.g. `if (! $user->rights->swisspayments->invoices->create) accessforbidden();`.
- Left-menu entries under Billing → Suppliers pointing to `createinvoice.php` and `dtapayments.php`.
- A `thirdparty` tab (`company_swisspayments.php`) for managing per-supplier ESR participant data.
- `init()` runs `sql/*.sql` (via `_load_tables`) and creates `DOL_DATA_ROOT/swisspayments/dtafiles/`.

### Data model (`sql/`, tables prefixed `llx_`)
- `swisspayments_soc` — maps a Swiss ESR participant (pcaccount + esrid) to a Dolibarr `societe`, plus `startorderno`/`endorderno` char offsets used to slice the bill number out of the reference line. Class: `swisspaymentssoc.class.php`.
- `swisspayments_factf` — record of an already-imported ESR bill (dedup). Class: `swisspaymentsfactf.class.php`.
- `swisspayments_payh` / `swisspayments_payl` — payment batch header + lines staged by `dtapayments.php`, later consumed by `dtafile.php` to emit the bank file. Classes: `swisspaymentspayh.class.php` / `swisspaymentspayl.class.php`.

Each class sets `var $table_element = 'swisspayments_xxx';` and follows Dolibarr's CommonObject CRUD convention (`create`/`fetch`/`update`/`delete($user)`).

### Reference-code parsing (the core domain logic)
`class/swisspayments.class.php` (`SwisspaymentsClass`) is the parser. `setCodeline()` then `validateCode($user)` classify the input and populate fields (`amount`, `pcAccount`, `iban`, `refLine`, `billnr`, `esrID`, `payToName`, `payToAddress`):
- **(V)ESR**: lines starting `01` (with amount) or `042` (no amount), amount/reference/account sliced by position around `>` and `+` delimiters.
- **QR-bill**: `SPC` payload split on `\n` (CR stripped); expects exactly 32 lines with header `SPC/0200/1` and footer `EPD`, currency `CHF`, IBAN starting `CH`/`LI`.
- **IBAN**: validated separately.

`lib/swisspayments.lib.php` holds the validation helpers: `is_valid_iban`, `is_valid_esr`, `modulo10` (recursive Swiss check-digit), `isValidCheckDigit`, `startsWith`. All Swiss reference validation goes through the modulo-10 recursive algorithm — do not replace it with a different checksum.

### Payability validation (cross-file invariant)
The "to pay" list in `dtapayments.php` gates each bill with a `$canPay` flag; when false it hides the amount input, which excludes the bill from the batch (the POST handler only pays rows with a positive `amount_<facid>`). This gate must mirror what `Swisspaymentspayh::createDTA()` will accept, or file generation throws mid-export. A QR-bill (`swisspayments_factf.esrpartynr == 'QRBILL'`) is emitted by one of three transactions chosen by the creditor account (default RIB IBAN) + reference (`esrline`): **QR-IBAN** (CH/LI, `3` at pos 5) needs a valid QRR (`^[0-9]{1,27}$` + modulo-10 check digit via `isValidCheckDigit()`) → `BankCreditTransferWithQRR`; **normal CH/LI IBAN + `RF…`** → `BankCreditTransferWithCreditorReference` (SCOR); **normal CH/LI IBAN + no reference** → plain `BankCreditTransfer`. The creditor agent IID is derived from the IBAN, so a CH/LI IBAN is mandatory. The gate encodes this as `$qrReason` (`''`=payable, else `qrr`/`noiban`/`ref`) and also requires a non-empty **post code and town** (`s.zip`/`s.town`), because `StructuredPostalAddress`'s `Text::assert()` throws on an empty post code/town and aborts the whole file. If you add new transaction types or mandatory fields in `createDTA()`, add the matching precondition to the gate.

### Bank file generation (`lib/`)
Two parallel families of generator classes produce the outbound payment file:
- **Legacy fixed-width** (SIX Interbank Clearing DTA / EZAG): `dtaChFile.php`+`dtaChTransaction.php`, `ezagChFile.php`+`ezagChTransaction.php`.
- **ISO 20022 pain.001 XML**: `z38ChFile.php`+`z38ChTransaction.php` wrap the vendored **`Z38\SwissPayment`** library under `lib/Z38/SwissPayment/` (namespaced, Composer-style but vendored in-tree — no `composer.json`; classes are hand-loaded via a `dol_include_once` list at the top of `swisspaymentspayh.class.php`). This is the modern path; the `dtaCh*`/`ezagCh*` classes are the legacy path.
  - The vendored lib is the **`sdespont/swiss-payment` fork** (of `ch2877/swiss-payment`, MIT), which adds an SPS version selector. `CustomerCreditTransfer`'s 3rd arg picks the format: `SPS_2021` → `pain.001.001.03.ch.02`, `SPS_2022` → `pain.001.001.09.ch.03`.
  - `createDTA()` **always emits `SPS_2022` (pain.001.001.09.ch.03)** — PostFinance accepts it (its validator has a format switch). The `SPS_2021` path still exists in the library if the older format is ever needed again. Both formats are validated in `tests/` against their XSDs.
  - Creditor addresses are **structured** in both formats (`StructuredPostalAddress::sanitize(street, buildingNo|null, postCode, town, country)`) — `.03.ch.02` accepts structured too, and PostFinance flags unstructured `AdrLine` with a hint (structured is mandatory from Nov 2026). ESR/IS (red-slip) transaction types were removed in `.09`; `createDTA()` only emits QR-bill transactions (QRR → `BankCreditTransferWithQRR`, SCOR → `BankCreditTransferWithCreditorReference`, no-reference → plain `BankCreditTransfer`) and IBAN transfers, and errors on ESR/IS.
  - Validate generated files against `tests/pain.001.001.09.ch.03.xsd` (DOMDocument::schemaValidate) before the bank's test portal.

### Frontend / scanning
- **Mobile scanning (login-free, paired to the desktop).** `createinvoice.php` (entry step) mints a one-time token via `swisspayments_scan_create_token()` and shows a QR (`mobileqr.php?t=<token>`) that opens `mobilescan.php?t=<token>` on the phone. `mobilescan.php` is a **public** page (`NOLOGIN`/`NOCSRFCHECK`, authorised only by the token) using `js/html5-qrcode.min.js`; after scanning it POSTs the payload back to itself. The desktop polls `scanpoll.php?t=<token>` (logged-in) and, when the payload arrives, fills `#qrcode` and submits `analyzecode` — behaving like a USB scanner. Pairing state is stored as JSON files under `DOL_DATA_ROOT/swisspayments/scan/` (10-min TTL, token = 32 hex chars validated to prevent path traversal); helpers are in `swisspayments.lib.php`.
- `mobileqr.php` / `decodeqr.php` decode/inspect QR payloads. `lib/phpqrcode/` is a vendored QR library.
- `createinvoice.php` is a 2-step wizard: **(1)** paste/scan a QR code (`action=analyzecode`), **(2)** review the parsed creditor/amount/reference and either assign an existing supplier or **create a new one** pre-filled from the QR's structured address (`action=createsupplier` → creates `Societe` + default `CompanyBankAccount` from the IBAN), then create the supplier invoice. The QR payload is carried between POSTs in a raw single-quoted hidden `codeline` field — `SwisspaymentsClass::setCodeline()` re-parses it each request, so preserve that mechanism. Legacy ESR coding-line entry and `swisspayments.php` are deprecated (QR-only). `SwisspaymentsClass` exposes the QR creditor address as structured fields (`payToStreet`/`payToBuildingNo`/`payToPostcode`/`payToTown`/`payToCountry`).

### Hooks
`class/actions_swisspayments.class.php` (`ActionsSwisspayments`) is the Dolibarr hook handler; the descriptor registers hook context `swisspayments`.

## Conventions

- **CSRF**: form pages use Dolibarr's token system — emit `newToken()` in forms and expect `token` on POST (see `mobilescan.php`, and recent commits adding tokens for Dolibarr 15.x+). Preserve this when touching any form.
- **Language**: user-facing strings are a mix of `$langs->trans('Key')` and hard-coded **German** literals (e.g. `"Lieferantenrechnung erfassen"`). There are currently **no `.lang` files shipped** in the repo despite `$langs->load("swisspayments@swisspayments")` calls. Match the surrounding style of the file you edit.
- **Logging**: use `dol_syslog(__METHOD__ . " ...", LOG_DEBUG|LOG_INFO|LOG_WARNING)` as the existing parser does.
- **Errors**: accumulate an `$error` counter and surface via `setEventMessage(..., 'errors')` or `dol_htmloutput_errors($mesg)`, matching existing pages.
- No CHANGELOG file exists; the README is minimal. History/versioning lives in git and the descriptor's `$this->version`.
