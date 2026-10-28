# MARCLAND Trust Signals 0.3.0

PrestaShop 9 module, checked out directly at `modules/marclandtrust`. No Composer,
build step, storefront JavaScript or external service is required. PHP DOM/libxml
and mbstring are required. Development and updates use Git and the normal module
upgrade mechanism.

## Signals and placements

A signal has a stable database ID, global activation, icon and multilingual plain
text (maximum 500 Unicode code points per translation). Empty translations omit the
signal in that language; there is no fallback or seeded commercial claim.

The editor supports normal-use counts beyond three. Add creates only a temporary
client key with a null persistent ID. Save inserts the signal, obtains AUTO_INCREMENT
and writes its translations/placements in the same transaction. Language changes,
reordering and editing retain identity. Native file inputs survive editor reordering
and language changes; select files again after a failed POST.

Expand each placement to choose visibility, display order and click action:

| Placement | Hook | Default layout | Click actions |
| --- | --- | --- | --- |
| Homepage | `displayHome` | Horizontal, wrapping | None, CMS, same-page anchor |
| Product | `displayProductAdditionalInfo` | Vertical list | None, CMS, same-page anchor |
| Checkout | `displayCheckoutSummaryTop` | Compact vertical list | None, CMS in new tab |

Global activation and placement selection must both be enabled. Lower order numbers
appear first; zero is valid and equal priorities use persistent ID as the tie-breaker.
Editor Move up/down organizes the editor only, independently of placement priorities.
New signals default to no selected placements and no links.

CMS URLs use native PrestaShop generation with the current shop and language. Missing,
inactive, deleted or unassociated CMS destinations leave plain text. The editor lists
CMS pages for the current shop, while existence validation is global because **all
shops share signal content and placement settings**. Enable the module independently
in each intended shop. Shared storage does not force other shops' module activation.

Anchors contain no `#`: an ASCII letter followed by up to 127 letters, digits,
underscores or hyphens. Merchants must choose an ID that actually exists in the
corresponding page/theme. They never navigate to another page. Product anchors are
not copied to home or checkout. Checkout rejects anchors; CMS links have
`target="_blank"`, `rel="noopener noreferrer"` and a visible new-tab notice.
Arbitrary external URLs are unsupported.

Themes determine hook location. Classic's `displayHome` is in its homepage content
area; use standard theme hook positioning for a lower placement where supported.
Classic's checkout hook is inside the native cart summary, outside payment forms.
Third-party themes and one-page checkouts must expose the hooks themselves. Missing
hooks produce no component; there are no core-template edits or DOM injection
workarounds. Checkout controller context must be the native `order` context. Test
custom themes/checkout modules separately. CSS is scoped, responsive and registered
through `actionFrontControllerSetMedia` for index/product/order controllers.

## Data model and migration

Three prefix-aware InnoDB/utf8mb4 tables hold:

- `marclandtrust_zone`: AUTO_INCREMENT identity, global activation, icon and editor order.
- `marclandtrust_zone_lang`: `(id_zone, id_lang)` translations.
- `marclandtrust_zone_placement`: `(id_zone, placement)` primary key, enabled,
  unsigned display priority, link type/target; indexed by placement/enabled/position.

Placement identifiers are registered centrally in `TrustConfiguration::PLACEMENTS`.
The table can hold future placements without duplicating content or redesigning the
schema. Unknown incoming placement keys are rejected; existing unknown database
placement rows are retained during known-placement saves and included in revisions.
A new placement still needs registry, editor, hook and presentation support.
Foreign keys are application-managed. Explicit parent deletion removes its child rows.
Successful full saves replace retained signals' translations, removing stale language
rows; there is no independent core-language-deletion hook.

The 0.3.0 upgrade widens the legacy editor position to INT UNSIGNED and adds placement
storage. Each existing signal gets a product placement preserving its old priority,
link and global active state. **Home and checkout remain opt-in.** IDs, translations
and saved icon references/bytes are preserved. Legacy link columns remain available
for inspection/recovery but are no longer edited or used for rendering. Repeat
migration uses INSERT IGNORE and never resets existing placement settings.

DDL implicitly commits: partial schema changes can remain after an interrupted
upgrade. Steps are additive/repeatable; retry the normal upgrade after correcting the
error. Do not edit with old browser tabs or old module code after migration. There is
no automatic downgrade, destructive cleanup or migration of custom third-party schemas.
Uninstall retains merchant tables and uploaded icons; it is not an upgrade procedure.

## Update an already-installed local store

Back up the store database and `modules/marclandtrust/uploads/` together. Finish
pending edits and close configuration tabs. In the existing module checkout:

```sh
cd /path/to/prestashop/modules/marclandtrust
git switch develop
git pull --ff-only origin develop
cd ../..
php -d memory_limit=2G bin/console prestashop:module upgrade marclandtrust --env=prod --no-interaction
```

Alternatively, after the Git pull, use the module manager's normal **Upgrade** action.
Do not uninstall/reinstall. File copying or Git pull alone does not migrate tables or
register hooks. Verify version 0.3.0, the placement table and all four hooks listed
above (including media). The successful upgrade refreshes compiled Smarty templates
and PrestaShop's theme asset cache; BO assets are versioned. Clear the affected
custom themes' caches through normal PrestaShop tools if they maintain additional
caches. Reload the configuration page and check the intended shops/themes. See the
[executed v0.3 validation and manual checklist](docs/v0.3.0-validation.md).

## Security, transactions and resource budgets

Authenticated employee/module configure permission and the existing PS9 session CSRF
validation remain required. Full validation precedes writes: installed language IDs,
zone ownership, exact placement structures, canonical ordering, supported icons, CMS
IDs and strict anchors. HTML/control characters are rejected in text; Smarty escapes
text and attributes. No raw SVG/XML is stored in the database or injected inline.

One database/prefix-specific advisory GET_LOCK serializes writes, including empty
configurations. A SHA-256 revision of **all three tables** is checked under the lock
before writing. Stale tabs fail instead of overwriting. Every existing ID must be
retained or explicitly deleted. Signal, translation and placement changes share one
transaction/master connection. Failures roll back; no SQL errors reach the merchant.
Success redirects to the configuration page (Post/Redirect/Get). Writers outside the
module must honor the same locking protocol. Migration takes the same writer lock.

There is no signal count/display cap. Request metadata has a 2 MiB encoded budget;
selected raw uploads together have a 2 MiB budget, with 102400 bytes per SVG. PHP
`post_max_size`, `upload_max_filesize`, `max_file_uploads` and `max_input_vars` may impose
lower limits; configure them for the required signal/language count. The final payload
sentinel, complete language/placement fields and upload-selection flags detect
truncation instead of silently saving partial configuration. Rejected oversized
payloads reload saved content when submitted data cannot safely be redisplayed.

The renderer performs one placement/translation/CMS association query per hook,
without persistent result caching or a display LIMIT. Native CMS URL lookups and icon
validation are deduplicated within that render. Custom icons are still strictly
revalidated on every request; no cross-request trust cache can mask changed assets.
Very large configurations still consume proportional memory, database and DOM work.

SVG uploads retain the existing strict allowlist: actual HTTP upload provenance,
.svg filename, bounded XML, DOM/libxml with external entity resolution/substitution
disabled and LIBXML_NONET; DTD/entities rejected. A fresh DOM accepts basic shapes,
paths, groups and text-only title/desc with limited numeric geometry/colors. Scripts,
events, styles, transforms, animations, foreignObject, image/use/href, URLs, nested SVG,
processing instructions and unsupported namespaces are rejected. Maximum depth32 and
2000 elements. Design-tool exports may need simplification; this is not a general SVG
compatibility sanitizer. Do not broaden it without security review.

Files use generated `uploads/<32-random-hex>.svg` names. Canonical paths, ownership and
symlink checks prevent arbitrary references. New sanitized files are published before
commit, removed on confirmed rollback, and old unreferenced files retired only after
commit under the writer lock. Ambiguous connection outcomes retain files to avoid
breaking possibly committed references; crash/cleanup failures can leave sanitized
orphans. Database/filesystem writes are not a distributed transaction.

Give only `uploads/` write access to the web user; avoid world-writable permissions.
Apache 2.4 must honor its shipped `.htaccess` (no listing/execution, nosniff and sandbox
CSP). On Nginx/other servers configure static image/svg+xml delivery, no listing or
script execution, `X-Content-Type-Options: nosniff` and CSP
`default-src 'none'; style-src 'none'; sandbox`. Back up icons with the database.

## Development verification

```sh
find . -name '*.php' -exec php -l {} \;
node --check views/js/admin-config.js
for t in schema security admin csrf front upgrade; do php tests/$t.php; done
```

Standalone tests use explicit PrestaShop doubles. `tests/editor.js` needs jsdom outside
the repo via NODE_PATH and a rendered Smarty fixture with languages17/29 and zone8.
No npm dependency is required by the module.

MariaDB tests require an **empty disposable database named `trust_test`**, root/empty
password and pdo_mysql. Never point them at a store. Apply SQL from
`php tests/schema.php --sql`, then with
`MARCLAND_TEST_DSN='mysql:host=YOUR_DISPOSABLE_DB;dbname=trust_test;charset=utf8mb4'`
run `tests/migration.php`, `tests/persistence.php`, `tests/lifecycle.php`, then
`tests/concurrency.php`. Concurrency leaves six synthetic winner signals; empty the
three test_shop_ tables before repeating persistence. Lifecycle uses its own random
prefix and exercises uninstall/reinstall only against doubles/disposable test data.

Historical reports: [Task05](docs/release-readiness.md),
[real v0.2 integration](docs/real-prestashop-integration.md). Their original three-zone
policy describes v0.2, not v0.3. Current evidence is recorded in the
[v0.3 report](docs/v0.3.0-validation.md). Marketplace submission still requires separate
licensing, branding, compatibility metadata and official validation; this Git workflow
is not a Marketplace certification claim.
