# Changelog

All notable changes to the Dolibarr **Swisspayments** module are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/); the module
uses date‑based versions (`YYYY.M`).

## [Unreleased]

## [2026.07.5] – 2026-07-10

### Security
- **Payment batches are now access-scoped (IDOR fix).** `llx_swisspayments_payh` gained a
  `fk_user_author` owner column (set on creation). `dtafile.php` now only lets the batch's
  creator — or an administrator — (re)generate/download its bank file; other users get
  `accessforbidden()` instead of being able to grab another user's file by changing the id.

### Added
- **Multi-entity (multi-company) isolation.** All module tables
  (`swisspayments_soc`, `swisspayments_factf`, `swisspayments_payh`,
  `swisspayments_payl`) gained an `entity` column, populated with the active
  `$conf->entity` on insert and filtered (`entity IN (...)`) on every read. On a
  Dolibarr install running several companies the module's data is now isolated per
  entity. Existing installs are migrated automatically on module re-activation (an
  idempotent `ALTER TABLE` guarded by an information_schema check).
- **Translation files** for German, French, Italian and English
  (`langs/{de_DE,fr_FR,it_IT,en_US}/swisspayments.lang`). The module chrome (menu
  entries, permission labels), the invoice-import wizard (`createinvoice.php`), the
  payment list (`dtapayments.php`), the payment-file page (`dtafile.php`) and the
  third-party ESR tab (`company_swisspayments.php`) now render through
  `$langs->trans()` keys instead of hard-coded German literals.
- **Per-bill creditor IBAN.** `swisspayments_factf` gained an `iban` column, filled from
  the QR bill at import. `createDTA()` and the `dtapayments.php` payability gate now pay
  each bill to the IBAN stored with it, instead of always using the supplier's *default*
  bank account (`default_rib`). This fixes wrong-account payments when a supplier has
  several bank accounts; legacy bills without a stored IBAN still fall back to the default
  RIB. The supplier's `societe_rib` IBAN is still used, separately, to identify the
  supplier from a scanned QR.
- **Download button** for the generated bank payment file on `dtafile.php` — a prominent
  action button with a download icon (and the file name), replacing the plain text link.
- **Automatic schema migration on update.** A `SWISSPAYMENTS_DB_VERSION` constant tracks
  the installed schema version; a guard on each module page
  (`swisspayments_check_db_version()`) runs the idempotent migration and bumps the
  constant when it is missing or older than the module `VERSION`. This means a plain
  file/zip update applies its schema changes on the first module page load, without
  having to disable/re-enable the module. The migration itself (`swisspayments_migrate_tables()`)
  is shared with the descriptor's `init()`, so activation and the runtime guard apply
  exactly the same changes.

### Removed
- Deprecated legacy `swisspayments.php` entry page (raw `$_GET`/`$_POST`, predated the
  QR-only flow in `createinvoice.php`). It was unreachable from any menu.

### Fixed
- `Swisspaymentspayl::fetch()` built an invalid `SELECT … ,  FROM` (trailing comma) and
  passed its filter unescaped; the column list and id/`fk_payh` casts are corrected.

## [2026.07.4] – 2026-07-10

### Fixed
- Dolistore package validation: entry pages (`createinvoice.php`, `dtafile.php`,
  `dtapayments.php`, `swisspayments.php`, `mobileqr.php`, `scanpoll.php`,
  `mobilescan.php`) now load Dolibarr with the modulebuilder multi-attempt
  `main.inc.php` sequence (tries the web root and the `custom/` subdir) instead of
  a single hard-coded `require '../../main.inc.php';`, which the store validator
  rejected.
- Module classes now include their `lib/` dependencies via `dol_include_once()`
  instead of `require_once(DOL_DOCUMENT_ROOT . '/custom/swisspayments/lib/…')`
  (`swisspaymentspayh.class.php` for `dtaChFile.php`/`ezagChFile.php`, `dtafile.php`
  for `dtaChFile.php`) — the store validator flagged the `DOL_DOCUMENT_ROOT` form.

## [2026.07.3] – 2026-07-10

### Fixed
- Release zip is now named `module_swisspayments-<version>.zip`. Dolibarr's
  "Deploy/install external app" uploader only accepts file names matching
  `module_*-x.y*.zip`; the previous `swisspayments-<version>.zip` was rejected
  ("… entspricht nicht der erwarteten Syntax: module_*-x.y*.zip"). The archive
  still contains the single top-level `swisspayments/` folder it needs.

### Changed
- New module icon: replaced the old ESR-slip picto with a Swiss QR-bill mark (QR
  finder patterns + the red Swiss cross). Shipped as a scalable `img/*.svg` plus
  regenerated 32×32 PNGs (`img/object_swisspayments.png`, `img/swisspayments.png`),
  up from the previous 16×16.

## [2026.07.2] – 2026-07-10

### Security
- Hardened all database access against SQL injection: the object classes
  (`swisspaymentssoc`, `swisspaymentsfactf`, `swisspaymentspayl`, `swisspaymentspayh`)
  now escape every string value and cast every id/number in their `create`, `update`
  and `fetch` statements.
- `dtapayments.php`: the `filtre` parameter now only accepts a whitelisted column name
  and escapes its value; the search filters and payment `comment`/`num_paiement` fields
  are escaped (SQL injection / stored XSS).
- `company_swisspayments.php`: the account id and delete id are read as integers, and
  deleting a payment account is now a POST action protected by a CSRF token (was a
  plain GET link, vulnerable to CSRF).
- `createinvoice.php`: the ESR-branch amount is sanitized with `price2num`.
- `mobilescan.php`: the posted scan payload is size-capped (2 KB).
- Removed the unused `decodeqr.php` debug page (reflected the raw request back — XSS).
- Removed the vendored PHP-QR-Code demo/build scripts (`lib/phpqrcode/index.php` and
  `lib/phpqrcode/tools/`): unauthenticated, web-reachable pages that wrote files to disk
  from `?data=` input. The module uses the library classes directly, so they were unused.

### Changed
- **Mobile scanning reworked** to a login-free, desktop-paired flow. The desktop shows a
  QR code with a one-time token; the phone opens the scan page for that token (no Dolibarr
  login), scans the QR-bill, and the desktop picks up the result by polling and continues
  automatically — like a USB scanner. Pairing state is kept in `DOL_DATA_ROOT` files
  (10-minute TTL), so no database change is needed.
- Updated `html5-qrcode` to 2.3.8 (from the 2021 build), which uses the browser's native
  `BarcodeDetector` when available. The mobile scan page now starts the rear camera
  directly and only prompts for permission when it isn't already granted, falling back to
  file upload if the camera is unavailable.

## [2026.07.1] – 2026-07-09

### Added
- Propose a `yyyymmdd` invoice number for QR bills when none is entered, made unique per
  supplier (`yyyymmdd`, `yyyymmdd-2`, …), instead of leaving "Rechnung Nr." empty.
- Warn on the review screen when a QR bill's IBAN is not the supplier's **default** bank
  account, with a checkbox to make the scanned IBAN the default — so a multi-account
  supplier is paid on the account the bill actually uses.

## [2026.07] – 2026-07-09

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

The module was not formally versioned before 2026.07 (it declared `8.*`). Notable
milestones:

- **2024-01** – CSRF token added for the image‑upload case.
- **2022-04** – CSRF token for Dolibarr 15.x+; timestamp‑based message id; accept QR
  codes without a final CRLF.
- **2022-01** – Improved error catching and display.
- **2021-12** – First working version; updated the vendored Z38 SwissPayment library.
- **2021-08** – QR‑bill import implemented (create supplier bills from a QR code);
  layout and CR/LF fixes.
- **2021-07** – Initial import: enter Swiss ESR/PVR bills and generate DTA payment files.
