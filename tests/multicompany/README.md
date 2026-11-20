# Multi-company integration tests

End-to-end tests for the module's multi-company (multi-entity) behaviour, run against real
Dolibarr installs in Docker. Not part of the release zip (`tests/` is `export-ignore`).

```bash
tests/multicompany/run.sh            # Dolibarr 15.0.3 and 24.0.2
```

```bash
tests/multicompany/run.sh 24         # one version
```

```bash
SWP_KEEP=1 tests/multicompany/run.sh 24   # keep the containers (http://localhost:8024, admin / admin123)
```

```bash
tests/multicompany/run.sh down       # remove the test containers
```

Requirements on the host: Docker, Node.js with Playwright + Chromium, `curl`. Ports
`80<version>` (8015, 8024) must be free. Other versions: `run.sh 20` uses the image tag
`dolibarr/dolibarr:20`; `SWP_IMAGE=...` overrides the image.

## What is simulated

The commercial **multicompany module is not used**. Instead:

- `stub/actions_multicompany.class.php` is copied to `htdocs/custom/multicompany/` and
  provides the `$mc` object core asks for entity resolution. It shares **third parties and
  bank accounts between entities 1 and 2**; everything else is per entity.
- `run.sh` sets `MAIN_MODULE_MULTICOMPANY`, and copies the module switches, payment-type
  dictionary and admin rights of entity 1 to entity 2.

So the tests prove the module's own entity handling and its use of core's `getEntity()`,
data directories and `document.php` — not the real multicompany module's behaviour.

## Files

| File | Purpose |
|---|---|
| `run.sh` | Starts Dolibarr + MariaDB per version (this checkout mounted read-only as `custom/swisspayments`), prepares entity 2, runs the tests, removes the containers. |
| `fx.php` | CLI tests inside the container; the entity is forced with `DOLENTITY`. Activation, fixtures, payment file generation, isolation, upgrade from the pre-2026.11.3 schema. |
| `ui.js` | Browser tests (Playwright): logs in to entity 1 and 2 and uses the real pages. |
| `stub/` | The multicompany stand-in described above. |

## Covered

- Module activation per entity; one schema-version constant (entity 0).
- Payment file of entity 2 is written to `documents/2/swisspayments/dtafiles/`, resolved
  and downloadable through `document.php`, valid against `../pain.001.001.09.ch.03.xsd`
  (skipped where the container's libxml is too old to compile the XSD, e.g. Dolibarr 15).
- Debit account check: closed, non-shared, unknown and missing accounts are rejected and
  nothing is created; a shared open account is accepted.
- QR wizard in entity 2 with a supplier owned by entity 1: found by IBAN, own per-entity
  `swisspayments_soc` row, duplicate supplier reference checked per entity.
- Batch number (`payident`) unique per entity, readable message on a duplicate.
- Isolation of `payh` / `factf` / `soc` between entities, also for `dtafile.php?payh=`.
- Upgrade: entity backfill of legacy rows, old global `payident` key replaced by
  `(payident, entity)`, per-entity version constants collapsed, backfill runs only once.
- Mobile scan pairing is bound to the entity it was started in.

Not covered: PostgreSQL, the company logo on the public phone page, the real multicompany
module.
