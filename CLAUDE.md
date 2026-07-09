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

When editing include/`dol_include_once` paths, match the convention already used by neighbouring files in that page's call chain rather than "fixing" one in isolation — changing it can break the deployment the file was written for. Entry pages start with `require '../../main.inc.php';` which assumes the two-levels-deep `custom/` layout.

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
The "to pay" list in `dtapayments.php` gates each bill with a `$canPay` flag; when false it hides the amount input, which excludes the bill from the batch (the POST handler only pays rows with a positive `amount_<facid>`). This gate must mirror what `Swisspaymentspayh::createDTA()` will accept, or file generation throws mid-export. In particular, a QR-bill (`swisspayments_factf.esrpartynr == 'QRBILL'`) is emitted via `BankCreditTransferWithQRR`, which requires its `esrline` to match `^[0-9]{1,27}$` **and** pass the modulo-10 check digit (`isValidCheckDigit()` in `swisspayments.lib.php`, identical to `PostalAccount::validateCheckDigit`). If you add new transaction types in `createDTA()`, add the matching precondition to the `$canPay` computation.

### Bank file generation (`lib/`)
Two parallel families of generator classes produce the outbound payment file:
- **Legacy fixed-width** (SIX Interbank Clearing DTA / EZAG): `dtaChFile.php`+`dtaChTransaction.php`, `ezagChFile.php`+`ezagChTransaction.php`.
- **ISO 20022 pain.001 XML**: `z38ChFile.php`+`z38ChTransaction.php` wrap the vendored **`Z38\SwissPayment`** library under `lib/Z38/SwissPayment/` (namespaced, Composer-style but vendored in-tree — no `composer.json`). This is the modern path; the `dtaCh*`/`ezagCh*` classes are the legacy path.

### Frontend / scanning
- `mobilescan.php` + `js/html5-qrcode.min.js` scan a QR-bill with the device camera and POST the payload to `createinvoice.php`.
- `mobileqr.php` / `decodeqr.php` decode/inspect QR payloads. `lib/phpqrcode/` is a vendored QR library.
- Supplier-invoice creation in `swisspayments.php` / `createinvoice.php` includes JS that lets the user text-select the bill number out of the raw reference line when the supplier/offsets aren't known yet.

### Hooks
`class/actions_swisspayments.class.php` (`ActionsSwisspayments`) is the Dolibarr hook handler; the descriptor registers hook context `swisspayments`.

## Conventions

- **CSRF**: form pages use Dolibarr's token system — emit `newToken()` in forms and expect `token` on POST (see `mobilescan.php`, and recent commits adding tokens for Dolibarr 15.x+). Preserve this when touching any form.
- **Language**: user-facing strings are a mix of `$langs->trans('Key')` and hard-coded **German** literals (e.g. `"Lieferantenrechnung erfassen"`). There are currently **no `.lang` files shipped** in the repo despite `$langs->load("swisspayments@swisspayments")` calls. Match the surrounding style of the file you edit.
- **Logging**: use `dol_syslog(__METHOD__ . " ...", LOG_DEBUG|LOG_INFO|LOG_WARNING)` as the existing parser does.
- **Errors**: accumulate an `$error` counter and surface via `setEventMessage(..., 'errors')` or `dol_htmloutput_errors($mesg)`, matching existing pages.
- No CHANGELOG file exists; the README is minimal. History/versioning lives in git and the descriptor's `$this->version`.
