# MARCLAND Trust Signals

PrestaShop 9 module. Install the checkout as `modules/marclandtrust` in an existing
PrestaShop application. No Composer dependencies, build system or external services
are required. PHP DOM/libxml and mbstring are required (standard PrestaShop requirements).

## Schema and ownership

`installSchema()` creates two prefix-aware InnoDB tables using utf8mb4 and
utf8mb4_unicode_ci. The zone table has an unsigned AUTO_INCREMENT `id_zone`,
position, visibility, nonlocalized icon references and link configuration.
The `(active, position)` index supports ordered visible-zone reads. The language
table has a composite `(id_zone, id_lang)` primary key and an `id_lang` index.
No language IDs or default trust claims are seeded.

**All shops in this PrestaShop installation share zones and translations.**
There is no implicit current-shop filter or per-shop ownership. The editor lists
CMS titles for its current shop; the save handler validates that the CMS record
exists globally, consistent with shared configuration. Merchants must select CMS
pages available in the shops where the link will be used.

Foreign keys are application-managed. Saves validate installed language IDs and
zone ownership, and delete translations before deleting zones in the same transaction.
A successful full save replaces each retained zone's translations, removing stale
language rows. There is no independent core-language-deletion hook yet.

The Task 01 schema and install/uninstall behavior remain unchanged. Installation
uses CREATE TABLE IF NOT EXISTS, reports errors and attempts module-registration
cleanup on failure. DDL implicitly commits, so partial tables and existing data
are retained on failure. Existing table definitions are not repaired automatically.
The existing product hook is registered on successful installation. Already-installed
copies without these tables receive additive schema setup through the 0.2.0 upgrade; no destructive
upgrade path is introduced.

Uninstall unregisters the module but retains tables, merchant data **and uploaded
icons**. Permanent removal requires an explicit backed-up administrator operation,
deleting translations before zones and removing their generated icons.

## Configuration editor and persistence

Languages come from PrestaShop, including installed inactive languages. Each zone has
one visible message input; switching languages preserves all translations and zone
identity. Add creates a null database ID and a separate temporary client key. Removing
an unsaved zone makes no backend request. Move up/down determines positions 1–3.
Inactive zones count toward the three-zone limit.

Six original icons are bundled under `views/img/icons`. Custom selection retains the
**original file input element**, including through reordering/language changes. Only
saved sanitized icons are displayed through image references; local uploads are never
injected inline or previewed with object URLs. Selecting a predefined icon removes the
custom reference on successful save. Selecting another SVG replaces the existing one
only after the whole configuration succeeds.

Save performs a traditional multipart POST. Hidden fields are rebuilt under
`trust_signals[index]`: persistent ID (empty for new zones), ordering, active flag,
icon/link settings and `text[id_lang]` for every installed language. Native file inputs
are named `trust_icons[index]` using the same current index. Client keys are never
submitted. `trust_deleted[]` explicitly lists removed existing zones. An empty editor
has `trust_signals_present=1`; an `upload_selected` flag detects missing/truncated selected files; the final
`trust_payload_complete=1` sentinel detects
truncation from PHP input limits. No AJAX/fetch is used.

The handler requires an authenticated employee, module configure permission and a
constant-time check of the PrestaShop AdminModules token. Server validation rejects
unexpected fields, invalid/duplicate IDs, unknown languages, incorrect ordering or
visibility, unsupported icons, manipulated file references, nonexistent CMS IDs and
invalid anchors. Anchors are identifiers without `#`, matching an ASCII letter followed
by up to 127 letters, digits, underscores or hyphens; arbitrary URLs are unsupported.

Messages are UTF-8 **plain text, at most 500 Unicode code points per language**.
HTML and control characters are rejected; quotes, ampersands and emoji are preserved.
Every installed language must appear in the request. An empty string intentionally
removes that translation; missing languages fail validation. No translation is copied
from another language, and there is no implicit fallback or generated claim.

## Transactions and failure handling

One master connection acquires a database/prefix-specific GET_LOCK before starting a
transaction. This serializes module saves even when both tables are empty. A SHA-256
revision of the loaded rows is checked inside that lock; stale tabs fail rather than
silently overwrite another save. All existing IDs must be accounted for exactly once
as retained or explicitly deleted. The complete final set must contain at most three
zones. Writers outside this module must honor the same locking protocol.

New rows omit `id_zone`; the same connection's Insert_ID supplies translation IDs.
Updates retain real IDs. All zone/translation INSERT/UPDATE/DELETE operations must
succeed before COMMIT; failures attempt ROLLBACK. No SQL/server error text is exposed.
Successful saves redirect back to the tokenized configuration URL (Post/Redirect/Get).

Validation errors redisplay bounded submitted values and translations. Stale edits
remain visible for copying but require a reload to get the latest revision. **File
selections must be repeated after a failed POST**; browsers cannot restore uploaded
FileLists from PHP. Requests discarded by PHP's total upload limit cannot be recovered.

Sanitized files are written under exclusive generated filenames before committing
references. On confirmed rollback newly created files are removed. Old files are
retired only after commit and only if no zone still references them, while the writer
lock is held. Failed cleanup may leave harmless sanitized orphans. Ambiguous COMMIT or
ROLLBACK outcomes retain new files to avoid deleting potentially committed references;
reload to check the actual saved state before retrying. Database/filesystem operations
are not a distributed transaction, so a process crash can also leave sanitized orphans.

## SVG policy and deployment

Uploads are limited to 102400 bytes and a `.svg` filename. PHP verifies that the source
is an actual HTTP-uploaded file. Client MIME and filename are not trusted as content
validation. XML is parsed with DOM/libxml, external resolution/substitution disabled,
and LIBXML_NONET. DTDs and entity declarations are rejected. A **fresh DOM** is built
from explicit allowlists, never from copied untrusted nodes or regex filtering alone.

The supported subset is svg/g, paths, rectangles, circles, ellipses, lines,
polylines/polygons and text-only title/desc, with limited numeric geometry and basic
fill/stroke attributes. Colors accept common basic names, hex, none and currentColor.
Scripts, event attributes, styles, transforms, animation, images, use/href, URLs,
foreignObject, nested SVG, processing instructions, unsupported namespaces and other
features are rejected. Limits of 32 nested levels and 2000 elements also apply.
This intentionally narrow policy requires some design-tool exports to be simplified;
it is not a general-purpose SVG compatibility sanitizer. It adds no vendored dependency
or Marketplace packaging step. The policy and tests should receive security review
before broadening the supported SVG feature set.

Generated assets use `uploads/<32-random-hex>.svg`; raw SVG/XML never enters the database.
The browser cannot assign a new path or reuse another zone's path. `uploads/` is shipped
with directory-listing/execution restrictions and a deny index. Give **only this
directory** write access for the PHP/web-server user; avoid world-writable permissions.
Apache 2.4 must allow the shipped `.htaccess`. With Nginx or other servers, configure
this directory for static SVG serving, no directory listing or script execution, an
`image/svg+xml` content type, `X-Content-Type-Options: nosniff`, and a restrictive CSP
(`default-src 'none'; style-src 'none'; sandbox`). Back up assets alongside database rows.

## Focused development checks

With PHP 8.4:

```sh
find . -name '*.php' -not -path './vendor/*' -exec php -l {} \;
node --check views/js/admin-config.js
php tests/schema.php
php tests/security.php
php tests/admin.php
```

Schema tests use PrestaShop doubles. Security tests cover request validation and SVG
allowlist rejection; admin doubles check CSRF, configure permission and redisplay.
They are not a real PrestaShop installation test.

For `tests/persistence.php`, use an **empty disposable MariaDB database named
trust_test**, root with an empty password, and PHP pdo_mysql. Apply the schema emitted
by `php tests/schema.php --sql`, then run:

```sh
MARCLAND_TEST_DSN='mysql:host=YOUR_DISPOSABLE_DB;dbname=trust_test;charset=utf8mb4' php tests/persistence.php
```

That test writes actual rows and checks create/update/delete, Unicode, second-connection
lock contention, stale/unknown IDs, rollback and icon lifecycle. Never point it at a
store database. The SQL adapter emulates the relevant PrestaShop Db method signatures;
it is not a complete PrestaShop runtime.

`tests/editor.js` optionally uses jsdom installed outside the repository via NODE_PATH
and a rendered Smarty fixture filename as its argument. It checks same-input language
switching, retained file controls through reordering, null new IDs, explicit deletions,
matching multipart indices, the three-zone limit and repeatable hidden serialization.
The fixture must contain languages 17/29 and one existing custom zone ID 8; these IDs
are test fixtures only. No npm dependency is needed to run the module.

Task 03 was checked using PHP 8.4/MariaDB 11.4, Smarty 4.5.6 compilation, the focused DOM
test, and actual PHP multipart requests (valid SVG accepted; disguised PHP rejected).
Full PrestaShop installation, admin routing/token lifecycle, browser integration and
end-to-end testing remain deferred. Product-page rendering is documented under Task 04 below.

## Product-page rendering (Task 04)

`displayProductAdditionalInfo` now reads persisted, enabled zones with a focused
zone/translation join using the actual storefront language ID. Empty or missing
translations are omitted without a fallback. Stored position and ID determine order;
at most three nonempty zones render. Configuration remains shared across shops.
There is no old configuration placeholder or fabricated advertising claim.

The same query checks active CMS destinations against the current shop and language.
Valid CMS IDs are passed to PrestaShop `getCMSLink` with both context IDs (the API may
perform its own CMS lookup). Invalid, inactive, deleted or unavailable destinations
leave plain text; anchors use the existing strict identifier policy. All text and
attribute values are escaped in Smarty. Icons are decorative images with empty alt
text; links have visible keyboard focus and underlines. Missing/invalid icons leave
the message visible without a broken image element.

Bundled icon names are allowlisted. Custom references must match generated paths,
exist without symlink indirection, and match the sanitizer's canonical safe output.
At most three bounded 100 KB reads/revalidations occur per hook invocation; no new
cache layer is introduced. This trades small local reads for rejecting tampered assets.
A missing schema or database error returns no component rather than breaking the page.

`views/css/front.css` is registered on product controllers through
`actionFrontControllerSetMedia`. Flex wrapping adapts to narrow containers; all selectors
are scoped and typography/colors inherit the theme. Version 0.2.0 adds a non-destructive
upgrade script to ensure additive schema setup and both hooks for already-installed copies. Run the normal
PrestaShop module upgrade after deploying these files; copying files alone does not
register a new hook. Fresh installs register both hooks. Schema and data-retention
policy are unchanged. Themes control whether/where `displayProductAdditionalInfo`
renders, so positioning and spacing still need verification in the target theme.

Run `php tests/front.php` for focused presentation-data checks. Smarty 4 compilation
and escaped output are checked separately with representative presentation data.
These checks do not establish real PrestaShop storefront validation. Full integration
and end-to-end testing remain deferred; Task 04 does not start that final phase.


## Final validation (Task 05)

See [the release-readiness report](docs/release-readiness.md) for executed tests,
confirmed fixes, performance measurements and unresolved release blockers.
Additional disposable MariaDB checks are `tests/lifecycle.php` (its own random
`audit_lifecycle_` prefix) and `tests/concurrency.php` (empty `test_shop_` tables).
Both use MARCLAND_TEST_DSN with the same disposable trust_test database policy as
`tests/persistence.php`; run persistence before concurrency, which intentionally
leaves its three synthetic race-winner zones for inspection.
