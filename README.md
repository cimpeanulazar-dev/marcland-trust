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
language switching never changes identity or creates a zone. Task 02 implements the unsaved editor; database saving remains deferred.

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


## Configuration editor (Task 02)

The administration page loads all installed languages, existing shared zones and
translations, and CMS identifiers/titles for the current shop through read-only
PrestaShop APIs. Both configuration assets use the existing admin controller.
The language selector changes the value of the same visible input per zone without
changing zone identity. Add creates a local key and a null persistent ID. Inactive
zones count toward the three-zone limit; Move up/down updates display positions.
Pre-existing excess rows are preserved for review/removal, with submission disabled
until no more than three remain.

Six original SVG icons are bundled under `views/img/icons`. The editor stores their
identifiers. Custom selections retain a local File object and show the filename as
text; they never render SVG, create a preview URL, upload, or submit the file.
The browser file input may reset when cards are reordered, but the selected File
remains in editor memory and its filename remains visible. Language changes retain
the existing file input too.

Save submits a normal POST. Hidden fields are rebuilt once per submission under
`trust_signals[index]`, including an empty `id_zone` for new zones, numeric position,
active flag, icon/link configuration and `text[id_lang]` for every installed
language. `trust_signals_present=1` also represents an intentionally empty editor.
Client keys and file objects are never serialized. Anchor inputs accept only an
ASCII letter followed by letters, numbers, underscores or hyphens; CMS links use
page IDs, not URLs. All client fields are untrusted and need server validation in
Task 03, including existing IDs and custom paths.

**Nothing is saved yet.** The placeholder POST handler displays a warning instead
of success and reloads persisted data; unsaved edits and file selections are lost
on submit/reload/navigation. Upload processing, authoritative validation, CSRF
handling for writes, and transactional persistence remain Task 03 work. No frontend
hook/template or database schema changes were made in Task 02. Language switches,
adding, removing, and reordering make no backend calls.

Task 02 checks: PHP 8.4 lint, standalone schema/lifecycle tests, JavaScript syntax,
Smarty 4.5.6 compilation/rendering with a translation stub, matching JS/template
selectors, and bundled SVG XML validation. Real PrestaShop administration behavior
and complete integration/functional testing remain deferred as requested.
