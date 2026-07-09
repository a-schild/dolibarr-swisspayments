# Dolibarr Swisspayments

A [Dolibarr](https://www.dolibarr.org/) module that automates the Swiss supplier‑payment
workflow: read incoming **Swiss QR‑bills** into supplier invoices, and generate the
**bank payment file** to settle them.

It replaces manual entry of payment data end‑to‑end — from scanning the QR code on a
supplier's invoice to producing an ISO 20022 payment file you upload to your bank.

## Features

- **Import QR‑bills as supplier invoices** — paste the QR payload or scan it with your
  phone. The creditor, amount and reference are parsed automatically.
- **Create suppliers from the QR data** — when the creditor isn't on file yet, a
  pre‑filled form creates the third party (name + structured address) and its bank
  account from the scanned data.
- **Editable, validated QR reference** — correct a mis‑scanned reference in place, with
  live check‑digit validation.
- **Generate bank payment files** — batch open supplier invoices and export:
  - **ISO 20022 `pain.001.001.09.ch.03`** (Swiss Payment Standards 2022) — the default.
  - **Legacy DTA / EZAG** fixed‑width formats.
- **All Swiss QR‑bill variants** — QR‑IBAN + QR reference (QRR), normal IBAN + Creditor
  Reference (SCOR / `RF…`), and normal IBAN without a reference (NON), plus plain IBAN
  transfers. Creditor addresses are emitted in the structured form required from
  November 2026.
- **Pre‑payment validation** — bills with a missing/invalid reference, a wrong IBAN, or
  an incomplete address are flagged and excluded from the file instead of breaking the
  export.

## Requirements

- A recent **Dolibarr** installation (CSRF handling assumes Dolibarr 15 or later).
- **PHP 8.0** or newer.
- The module is deployed under Dolibarr's `htdocs/custom/` directory.

## Download

Ready‑to‑install module zips are published automatically on the
[**Releases**](https://github.com/a-schild/dolibarr-swisspayments/releases) page —
each tagged version has a `swisspayments-<version>.zip` asset built by GitHub Actions.
Grab the latest release, or clone this repository to run the current development state.

See the [changelog](CHANGELOG.md) for what changed between versions.

## Installation

1. Download the latest release zip (`swisspayments-<version>.zip`) from the
   [Releases](https://github.com/a-schild/dolibarr-swisspayments/releases) page, or clone
   this repository.
2. Extract it into your Dolibarr `htdocs/custom/` directory so the files live under
   `htdocs/custom/swisspayments/`.
   - In the Dolibarr admin you can also use **Setup → Modules/Applications →
     Deploy/install external app** and upload the zip.
3. Log in as a Dolibarr administrator, open **Setup → Modules/Applications**, find
   **Swisspayments** in the *Financial* section and enable it.
4. Grant the module permissions to the relevant users:
   - **Rechnungen einlesen** — read/enter supplier bills.
   - **Rechnungen bezahlen** — generate payment files.

## Usage

### Import a supplier bill

**Billing → Suppliers → "Rechnung einlesen"**

1. Paste the QR‑bill payload into the text area, or open the mobile scan page (a QR code
   on the form links your phone to the scanner).
2. Review the parsed creditor, address, amount and reference.
3. Assign an existing supplier, or create a new one from the pre‑filled form.
4. Adjust the invoice number, dates and the QR reference if needed, then create the
   supplier invoice.

### Pay bills

**Billing → Suppliers → "Rechnung bezahlen"**

1. Select the open supplier invoices to pay. Bills with incomplete or invalid payment
   data are flagged with the reason and cannot be selected.
2. Pick the debit account and execution date and generate the payment file.
3. Download the file and upload it to your bank's e‑banking / EBICS channel.

### Per‑supplier data

The **Swisspayments** tab on a third‑party card shows the stored QR/ESR account data for
that supplier.

## Payment file formats

| Format | Standard | Notes |
| --- | --- | --- |
| `pain.001.001.09.ch.03` | Swiss Payment Standards 2022 | Default; ISO 20022 XML |
| DTA / EZAG | SIX Interbank Clearing | Legacy fixed‑width |

Reference XSD schemas for local validation are in [`tests/`](tests/). Always validate a
generated file with your bank's test/validation portal before going live.

## Building a deployable zip

Tagging a release builds a minimal module zip automatically via GitHub Actions
([`.github/workflows/release.yml`](.github/workflows/release.yml)) and attaches it to the
GitHub release. The archive contains a single top‑level `swisspayments/` folder and
excludes dev‑only files (docs, tests, CI config) via `.gitattributes`.

To build one locally:

```sh
git archive --format=zip --prefix=swisspayments/ -o swisspayments.zip HEAD
```

## Version

The module version is defined once in `modswisspayments::VERSION`
(`core/modules/modSwisspayments.class.php`) and is reported in the payment file's
software‑information header.

## License & support

Copyright © Aarboard AG, André Schild.

The bundled `Z38\SwissPayment` library (`lib/Z38/SwissPayment/`) is distributed under the
MIT license.

Support:

    Aarboard AG
    Egliweg 10
    2560 Nidau
    support@aarboard.ch
    www.aarboard.ch
    +41 32 332 97 14
