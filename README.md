# MARCLAND Trust Signals

PrestaShop 9 module. Install the checkout as `modules/marclandtrust` in an existing
PrestaShop application. No Composer or JavaScript build dependencies are required.

## Database foundation (Task 01)

`installSchema()` creates two prefix-aware InnoDB tables with utf8mb4 and
utf8mb4_unicode_ci. The zone table has an unsigned AUTO_INCREMENT `id_zone`,
ordering, visibility, nonlocalized icon references and link configuration.
The `(active, position)` index supports ordered visible-zone reads. The language
table has a composite `(id_zone, id_lang)` primary key and an `id_lang` index.
Text exists only in the language table; no language IDs or default content are seeded.

Ownership is installation-wide: **all shops share the same zones and translations**.
There is no implicit current-shop filter. Independent shop configuration would require
an explicit future schema migration and ownership design.

Foreign keys are deliberately application-managed, matching PrestaShop module
lifecycle conventions and avoiding constraints on core language tables. Future saves
must validate installed language IDs and zone ownership, and delete child translations
with their zone inside the same transaction. Future language deletion handling must
remove stale translations. The schema alone does not prevent orphan rows.

Both tables support future atomic DML transactions on the same master connection.
MySQL/MariaDB DDL implicitly commits: schema installation itself is not transactional.
On failure, module registration cleanup is attempted and errors are reported; any
partially created tables and all pre-existing data are retained. Retrying uses
CREATE TABLE IF NOT EXISTS. Existing table definitions are not migrated or repaired
by this method. The existing product hook is still registered on successful install.

Uninstall unregisters the module through PrestaShop but **retains both tables and
merchant data**, including after reinstall. There is no automatic destructive cleanup
or destructive upgrade path. Permanent removal requires a separate explicit,
backed-up administrator operation, deleting the prefixed language table before the
zone table. No upgrade runner is added in Task 01; already-installed copies need a
future additive upgrade task to invoke schema setup.

## Future persistence contract (not implemented)

The application must validate a maximum of `MarclandTrust::MAX_ZONES` (three),
including inactive zones. AUTO_INCREMENT values are identities, not a count or slot.
Concurrent saves must serialize the count check and writes in a transaction using a
shared lock strategy; simply counting before an INSERT is insufficient.

Add (+) creates only an unsaved UI element, with `id_zone: null` or no persistent ID.
A temporary client key must remain separate. Only Save triggers PHP validation and
INSERT, omitting `id_zone`; PHP retrieves `Db::getInstance()->Insert_ID()` on that
same connection and uses it for localized rows. Existing zones retain database IDs;
language switching never changes identity or creates a zone. No such UI or saving
behavior is implemented here.

Future validation must allowlist icon/link types, check predefined names, restrict
custom paths to controlled sanitized uploads, and validate link destinations.
The database stores references, never raw SVG/XML. Translation saves must account
for all installed languages without hardcoded IDs. Bundled icons, uploads, zone
persistence and frontend changes are outside Task 01.

## Development checks

With PHP 8.4 (within PrestaShop 9's supported range):

```sh
php -l marclandtrust.php
php -l tests/schema.php
php tests/schema.php
```

`tests/schema.php` uses standalone PrestaShop doubles to exercise generated SQL,
installation success, repeatability, boolean and exception failures, registration
cleanup, hook failure and data-preserving uninstall. It does not constitute a real
PrestaShop installation test. `--sql` emits the tested schema for database validation
with the deliberately nondefault `test_shop_` prefix.
