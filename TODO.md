# Open items / known issues

Tracked work that isn't a quick code fix — mostly because it needs a database
schema change and migration. Newest concerns first.

## Security / data isolation

### Batch payment files are not access-scoped (IDOR)
`dtafile.php` fetches a payment batch by `rowid` alone. The
`llx_swisspayments_payh` table has **no owner column**, so any user who holds
the `paydta->dopay` right can download or (re)generate the bank file of a batch
created by another user, just by changing the id in the URL.

**Fix (needs schema change):** add an owner column (e.g. `fk_user_author`) to
`swisspayments_payh`, populate it in `Swisspaymentspayh::create()`, and filter
by it in `dtafile.php` / the batch list. Consider whether admins should be
allowed to see all batches.

### No multi-entity (multi-company) isolation
None of the module's tables (`swisspayments_soc`, `swisspayments_factf`,
`swisspayments_payh`, `swisspayments_payl`) carry an `entity` column. On a
Dolibarr install that runs several companies/entities, this module's data is
**shared across all of them** — there is no per-entity separation.

**Fix (needs schema change):** add an `entity` column to each table, default it
to the active `$conf->entity` on insert, and add `entity IN (...)` filters to
every read. Until then, the module should be treated as single-entity only
(see the note in `README.md`).

## Housekeeping

### Confirm and remove deprecated legacy pages
`swisspayments.php` still uses raw `$_GET`/`$_POST` and predates the QR-only
flow in `createinvoice.php`. If it is confirmed dead, delete it (as was done
for the old `decodeqr.php` debug page).

### No translation (`.lang`) files
Several pages call `$langs->load('swisspayments@swisspayments')` but no `.lang`
files ship, and many user-facing strings are hard-coded German. Add proper
language files if the module is to be localized.
