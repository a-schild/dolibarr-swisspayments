# Open items / known issues

Tracked work that isn't a quick code fix. Newest concerns first.

_All previously tracked items (batch-file IDOR, multi-entity isolation, the deprecated
legacy `swisspayments.php` page, and the missing `.lang` files) were resolved in
release 2026.07.5 — see the [CHANGELOG](CHANGELOG.md). Nothing is currently open._

## Notes for future work

- The migration that adds the `entity` / `fk_user_author` columns to existing installs
  runs from `modswisspayments::init()` (guarded, idempotent `ALTER TABLE`s). It only
  runs when the module is (re)activated or on a Dolibarr upgrade — a plain file update
  without re-activation will not add the columns.
- Existing pre-migration rows get `entity = 1` (the column default) and a NULL
  `fk_user_author`; legacy payment batches with a NULL owner are therefore accessible
  to administrators only.
- Many hard-coded German strings deep inside page logic (and the client-side JS) were
  converted to `$langs->trans()` keys; a few stored-content strings (e.g. the
  "Lieferantenrechnung" invoice-line description in `createinvoice.php`) were left as
  literals on purpose, since translating them would make persisted data depend on the
  creator's language.
