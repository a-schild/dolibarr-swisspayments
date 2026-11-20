# Changelog

All notable changes to the Dolibarr **Swisspayments** module are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/); the module
uses date‑based versions (`YYYY.M`).

## [Unreleased]

## [2026.11.3] – 2026-11-20

Multi-company (multicompany module) follow-ups. Not yet verified on a running
multicompany install.

### Fixed
- **Payment file could not be downloaded in entities other than 1.** The file was written
  to a hard-coded `DOL_DATA_ROOT/swisspayments/dtafiles/`, while `document.php` resolves
  the module's documents per entity (`DOL_DATA_ROOT/<entity>/swisspayments/`). Files are
  now written to the entity-aware directory (`swisspayments_data_dir()`), which also
  separates the payment files of different companies. Entity 1 / single-company installs
  keep the previous path.
- **Duplicate invoice-number check ignored the entity.** With third parties shared between
  companies, an invoice in company A blocked the same supplier reference in company B.
- **Legacy rows invisible on installs that already ran several entities.** Rows created
  before the `entity` column existed all got entity 1. A one-time backfill now derives the
  entity from the parent record (supplier invoice, third party, supplier payment) for
  rows still carrying the default.
- Mobile scan: the desktop poll now also checks the entity of the pairing, and the public
  phone page shows the logo of the company the pairing was started in.

### Changed
- `SWISSPAYMENTS_DB_VERSION` is stored once for all entities (entity 0) instead of per
  entity, so the schema migration no longer re-runs once per company.
- `swisspayments_soc` (ESR participant mapping) intentionally stays per entity, even when
  third parties are shared between companies.

## [2026.11.2] – 2026-11-17

All fixes below were reproduced and verified on a running Dolibarr 24.0.2 instance.

### Fixed
- **Supplier selector showed a technical error on Dolibarr 20+** ("Bestehendem Lieferant
  zuweisen" in `createinvoice.php`). `Form::select_company()` was given a raw SQL filter
  (`s.fournisseur = 1`); Dolibarr 20+ only accepts the Universal Search Filter syntax.
  The filter now comes from `swisspayments_supplier_filter()`, which returns
  `(s.fournisseur:=:1)` on Dolibarr 18+ and the raw SQL form on older releases. As a
  consequence no supplier could be assigned (`Column 'fk_societe' cannot be null`).
- **"Invoice date / due date required" although both were filled in.** The dates were
  checked with `GETPOST(..., 'date')`, which is not a valid check type and returns an
  empty value on Dolibarr 20+. They are now read from the day/month/year fields of the
  date selector.
- **"Supplier code required" when creating a supplier from the QR data.** The new third
  party is now created with `code_fournisseur = 'auto'`, so the configured numbering
  module assigns the supplier code.
- **Known suppliers were not recognised on Dolibarr 18+.** `societe_rib.iban_prefix` is
  stored encrypted there, so the SQL lookup of the scanned IBAN never matched and every
  scan ended in "unknown supplier"; the "IBAN is not the default account" warning showed
  the encrypted value. IBANs are now matched on the decrypted value
  (`swisspayments_find_ribs_by_iban()`, `swisspayments_decrypt()`), also for the
  default-RIB fallback in the payment list (`dtapayments.php`).
- **Menu entries showed `SwpMenuReadInvoice` / `SwpMenuPayInvoice`.** The menu
  definitions did not reference the module language file. Existing installations are
  repaired automatically on the first module page load after the update.
- **A failed invoice creation dropped back to the QR entry step**, losing the scanned
  bill. The review step is now shown again together with the error.
- The invoice number typed for a QR-bill was ignored when the form was submitted a second
  time (the QR reference was used instead).
- **Fatal error on the first page load after a zip update**
  (`Call to undefined function dolibarr_set_const()`): the runtime schema guard
  `swisspayments_check_db_version()` did not load `admin.lib.php`.
- The account owner of a supplier bank account created from a QR-bill was stored empty on
  Dolibarr 20+ (property renamed from `proprio` to `owner_name`); both are now set.
- Button label showed a literal `&AMP;` ("Zuweisen &AMP; Rechnung erstellen").
- The posted supplier id is now cast to an integer before being used in SQL.

## [2026.11.1] – 2026-11-17

### Fixed
- **Reading a QR-bill did nothing on Dolibarr 20+** (`createinvoice.php`): after
  pressing "Read" the QR field was simply emptied and the review step never appeared,
  without any error message. The page read its `action` parameter with
  `GETPOST('action', 'showcodefield')` — the second argument is the *sanitizing check
  type*, not a default value. Up to Dolibarr 19 an unknown check type passed the value
  through unchanged; since Dolibarr 20 `sanitizeVal()` discards it, so `action` was
  always empty and the page fell back to the entry step. The parameter is now read
  with the `aZ09` check (also in `dtapayments.php` and `dtafile.php`, where it was
  harmless but logged an error on every request).
- PHP 8 warnings on `createinvoice.php` (`Undefined array key "codeline"`,
  `Undefined variable $facture`).

## [2026.07.6] – 2026-07-13

### Changed
- **Dolibarr 15 → 24 compatibility.** The module is now validated across the full
  Dolibarr 15.x–24.x range. The module descriptor advertises the supported range
  (`need_dolibarr_version = 15.0`, new `max_dolibarr_version = 24.0`) and a realistic
  PHP floor (`phpmin = 7.0`). All PHP sources lint clean on PHP 8.3.
- **Deprecated-API cleanup via runtime shims.** Calls to APIs that were changed or
  deprecated between Dolibarr 15 and 24 now go through small compatibility helpers in
  `lib/swisspayments.lib.php` that prefer the modern API when present and fall back to
  the legacy accessor otherwise, so the same code runs cleanly on every supported
  version:
  - `swisspayments_user_has_right()` — `User::hasRight()` (Dolibarr 16+) or legacy
    `$user->rights->…`.
  - `swisspayments_user_socid()` — `$user->socid` or the deprecated `$user->societe_id`.
  - `swisspayments_conf_string()` — `getDolGlobalString()` (Dolibarr 16+) or
    `$conf->global->…`.
  - `swisspayments_mod_enabled()` — `isModEnabled()` or `$conf->{module}->enabled`.
  Migrated pages: `createinvoice.php`, `dtapayments.php`, `dtafile.php`,
  `company_swisspayments.php`, `mobileqr.php`, `mobilescan.php`, `scanpoll.php`.

### Fixed
- **Permission check on the payment list.** `dtapayments.php` tested only the existence
  of the `paydta` permission object rather than the `paydta->dopay` right; it now checks
  the actual right (matching `dtafile.php`).
- **"Invoice recorded" confirmation showed a raw `<a …>` tag.** On step 1 of
  `createinvoice.php` the success message passed the invoice link (`getNomUrl()`) as a
  `%s` parameter to `$langs->trans()`, which HTML-escapes substitution parameters, so the
  anchor (and its tooltip) printed as literal text. Switched to
  `$langs->transnoentities()` so the link renders as a clickable link.

### Removed
- **Dead `class/swisspaymentssoc_page.php`.** A modulebuilder template leftover that was
  unreferenced, had a broken include path, and checked a non-existent `mymodule`
  permission. Removed.

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
  entity. Existing installs are migrated automatically (see "Automatic schema migration
  on update" below).
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

### Changed
- The "back to payment list" link on `dtafile.php` now points to the supplier payments
  list (`/fourn/paiement/list.php?leftmenu=suppliers_bills_payment`) instead of
  `/fourn/facture/paiement.php`, so it lands on the right page with the matching left menu.

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
