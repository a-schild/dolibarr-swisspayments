# Open items / known issues

Tracked work that isn't a quick code fix. Newest concerns first.

_The items tracked before (batch-file IDOR, multi-entity isolation, the deprecated
legacy `swisspayments.php` page, and the missing `.lang` files) were resolved in
release 2026.07.5 — see the [CHANGELOG](CHANGELOG.md). Nothing is currently open._

## Notes for future work

- Multi-company: files are written to the entity-aware `swisspayments_data_dir()`;
  `swisspayments_soc` is per entity **by design** (does not follow third-party sharing);
  the "to pay" list is strictly per entity (`f.entity = $conf->entity`, no multicompany
  invoice sharing). The 2026.11.3 changes were tested on Dolibarr 15.0.3 and 24.0.2 with a
  stub emulating third-party + bank-account sharing (`tests/multicompany/run.sh`); a run
  against the real multicompany module, and the index migration on PostgreSQL, are still
  outstanding.
- The one-time entity backfill (`swisspayments_backfill_entities()`, gated on a stored
  schema version < 2026.11.3) only touches rows with `entity = 1` whose parent record
  belongs to another entity. A `swisspayments_soc` row deliberately created in entity 1
  for a shared third party owned by another entity (between 2026.07.5 and 2026.11.3) is
  moved to the owning entity by it.

- The migration that adds the `entity` / `fk_user_author` / `factf.iban` columns is
  defined in `swisspayments_db_columns()` / `swisspayments_migrate_tables()`
  (`lib/swisspayments.lib.php`). It runs from `modswisspayments::init()` on activation
  **and** from `swisspayments_check_db_version()` on the first module page load after a
  plain file/zip update (guarded by the `SWISSPAYMENTS_DB_VERSION` constant), so a
  re-enable is no longer required. Both paths are idempotent.
- Existing pre-migration rows get `entity = 1` (the column default) and a NULL
  `fk_user_author`; legacy payment batches with a NULL owner are therefore accessible
  to administrators only.
- Many hard-coded German strings deep inside page logic (and the client-side JS) were
  converted to `$langs->trans()` keys; a few stored-content strings (e.g. the
  "Lieferantenrechnung" invoice-line description in `createinvoice.php`) were left as
  literals on purpose, since translating them would make persisted data depend on the
  creator's language.
