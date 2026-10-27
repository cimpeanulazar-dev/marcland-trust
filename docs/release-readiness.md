# Task 05 release-readiness audit

Date: 27 October 2026. Repository: `cimpeanulazar-dev/marcland-trust`, branch `develop`.
Starting commit: `9446e993502caafeecbadeac832c4a8c3e72054d`.

**Recommendation: NO-GO for release/promotion and Marketplace submission.**
The isolated checks below pass after targeted fixes, but a complete PrestaShop 9
store could not be installed. Actual module lifecycle, admin Save/redirect/session
handling, storefront hook dispatch and post-upgrade CSS registration remain release
gates. No changes were made to `main` or to a production store.

## Environment and the real-store blocker

The official `prestashop/prestashop:9.0.0` image was downloaded with verified Docker
layer digests (image digest
`sha256:668d0b9be1a7af6f0e9946df35bebec48c1acb5a6f7661a70bee98ca0d3225f2`).
A disposable database and official installer were started in a dedicated Docker
network. The installer created its initial database but failed at default-data setup:
`Cannot download language pack "en"`.

The required request to
`https://i18n.prestashop-project.org/translations/9.0.0/en-US/en-US.zip`
was rejected by the configured proxy with HTTP 403 (CONNECT denied). Verification
was not disabled and the policy was not bypassed. A draft environment-settings
addition for **only `i18n.prestashop-project.org`** was saved, preserving package-manager
presets. This is a saved draft, not an applied or published network change. Review/save
that setting and publish the environment, then rerun real PrestaShop integration.
Additional destinations, if the installer proves they are needed, must be diagnosed
rather than preemptively opening unrestricted networking.

**No successful PrestaShop installation or real-store functional coverage is claimed.**
The separately run Apache/PHP harness used the official image's web server, not a
working PrestaShop application. Browser checks used rendered Smarty fixtures.

Versions observed: PHP 8.4.27 in the Apache image, MariaDB 11.4.13, Apache 2.4.68,
Smarty 4.5.6, Chromium 154.0.8037.57. DOM checks used jsdom 26; native browser checks
used Playwright Core with the existing system Chromium. Test tooling was installed
outside the checkout; no runtime framework/dependency or architecture was added.

## Executed coverage

| Category | Executed checks and outcome | Practical limit |
| --- | --- | --- |
| Real PrestaShop integration | Official installer attempted; **blocked** by language-pack proxy 403 | No actual module install, upgrade, login, save redirect or product-page execution passed |
| Standalone PHP | All PHP files linted; `tests/schema.php`, `security.php`, `admin.php`, `front.php` passed | PrestaShop services/controllers are doubles |
| MariaDB persistence | `tests/persistence.php` passed create/update/delete, generated IDs, Unicode/quotes, empty translation removal, stale/unknown IDs, explicit deletion, rollback, replacement and file cleanup | PDO adapter implements relevant PrestaShop Db signatures; not the actual platform Db runtime |
| MariaDB lifecycle | `tests/lifecycle.php` passed schema-less upgrade, installation, repeated upgrade, preserved rows/IDs, uninstall and reinstall retention, InnoDB verification | Real DDL/DML with parent Module/hook-registration doubles |
| MariaDB concurrency | `tests/concurrency.php` synchronized two independent PHP processes submitting three zones each; **one committed and one was stale**, with exactly three coherent rows including an inactive zone | Module locking protocol; unrelated external SQL writers must honor it |
| MariaDB storefront query | Actual focused SQL passed language, visibility, ordering, missing translation and shop-aware CMS availability checks | Synthetic CMS/core tables, no live shop |
| Smarty | Both templates compiled/rendered; storefront empty-output, escaped text, optional links and decorative-icon markup checks passed | Registered translation stub; no full theme renderer |
| JavaScript/DOM | `node --check`; `tests/editor.js` passed add/remove/reorder, three-zone cap, null new IDs, existing IDs, same-input language switching, retained file controls and duplicate-free structured serialization | DOM fixture, not a PrestaShop admin screen |
| Native browser + PHP | Chromium submitted a normal multipart form through Apache/PHP after reordering and language switching; server received the new null ID, existing ID 8, correct file index and both translations; SVG sanitized successfully | Harness validates request/upload; it does not perform a store Save or PRG |
| SVG adversarial uploads | Actual PHP HTTP uploads rejected script/event/foreignObject/external-reference/entity/namespace/malformed/oversized fixtures, executable extension and missing selected upload | Adversarial targets were local only; no third-party security testing |
| Apache public assets | Generated canonical SVG returned 200 with image/svg+xml, nosniff and sandbox CSP; a PHP file in uploads returned 403 without executing | Apache AllowOverride configuration used; Nginx deployment remains configuration-dependent |
| Browser layout/accessibility | Chromium at 320/768/1280 px: three-zone fixture had no horizontal overflow, CSS and icons loaded, links had visible 2px keyboard focus; reorder-focus regression passed in DOM | Isolated component, not multiple actual PrestaShop themes or assistive-technology certification |
| Static/manual review | Reviewed prefix/transaction ownership, CSRF and permissions, explicit deletion, escaping, path restrictions, retention policy, scoped CSS, packaging and real PS9 CMS-link signature | Not a Marketplace validator or independent security certification |

The actual PrestaShop 9.0.0 `Link::getCMSLink` signature was checked in source:
`($cms, $alias = null, $ssl = null, $idLang = null, $idShop = null, $relativeProtocol = false)`.
The module passes language/shop in the correct positions. Query tests verified that
inactive/deleted/unavailable CMS links become plain text. Frontend cases include empty,
one, two and three zones, inactive zones, absent text, invalid/missing/tampered icons,
Unicode, escaped HTML, valid/invalid anchors and CMS targets. Different actual storefront
languages and shops still need confirmation through the real platform.

Harness failures caused by restrictive temporary-file permissions and a fixture's
missing UTF-8 document charset were corrected in the **harness**, then rerun. They
were not masked by weakening application validation or changing release code.

## Confirmed defects and targeted resolutions

1. **Upgrade skipped missing tables.** The 0.2.0 upgrade only registered the media
   hook, so an original schema-less 0.1.0 installation remained unusable. The upgrade
   now calls the existing additive `installSchema()` and ensures both hooks. It does
   not alter existing table definitions, destroy rows, replace IDs or delete icons.
   A pre-fix probe confirmed no schema initialization; real MariaDB lifecycle tests
   now cover missing tables and repeated upgrades with retained merchant-like rows.
2. **Keyboard focus disappeared at reorder boundaries.** Moving a zone to first/last
   position recreated its card then attempted to focus a newly disabled move button.
   A new DOM regression failed before the fix. Focus now stays in the moved zone,
   using its Remove button when the corresponding move button is disabled.
3. **Repeated XPath construction caused avoidable SVG cost.** The strict sanitizer
   constructed a new DOMXPath object for every element. It now reuses one object per
   parsed document. Element/attribute/namespace allowlists, external-entity policy,
   limits, canonical output and per-render tamper validation are unchanged. Adversarial
   and canonical-output checks remain green; no cache or stale asset trust was added.

The database schema, save architecture, SVG feature policy and shared multistore
ownership were preserved. No new product functionality or fabricated claims were added.

## Performance evidence

Local microbenchmarks (not production capacity guarantees):

| Workload | Before median / p95 | After median / p95 |
| --- | --- | --- |
| Three typical truck SVGs; 30 renders | 0.422 / 0.590 ms | 0.250 / 0.396 ms |
| Three maximum-element SVGs (2,000 elements each, about 40 KB); 30 renders | 161.818 / 498.397 ms | 42.795 / 47.353 ms |

The focused MariaDB query plus PHP presentation for two predefined-icon zones,
200 iterations: median **0.525 ms**, p95 **1.057 ms**. This excludes the real platform's
CMS object/link generation, Smarty/theme/controller work and network latency.

Per-product custom SVG validation is still bounded to three reads of at most 100 KB
and full strict parsing. The pathological supported case remains more expensive;
retain that security check rather than add an unproven cache. Validate hosting load
with representative merchant SVGs and real pages before release.

## Security and integrity assessment

No accepted executable SVG payload, path traversal, cross-zone file assignment,
CSRF bypass or partial-write corruption was demonstrated in the executed tests.
This is bounded test evidence, **not a security certification**. Uploads require actual
PHP HTTP-upload provenance, strict XML parsing and generated names. Original filenames
are not persisted. Raw SVG/XML does not enter database columns. Replacement/cleanup
occurs around a database transaction as documented; old icons survive failed saves.

The filesystem and database cannot commit atomically together. A process crash or
ambiguous COMMIT/ROLLBACK can retain sanitized orphan files; cleanup is intentionally
conservative. Apache headers were tested, but servers ignoring `.htaccess` need the
explicit static-only restrictions in README. Only uploads should be writable by PHP.
External direct database writers are outside the module's advisory-lock guarantees.

## Remaining release gates and Marketplace packaging

- Complete real PS9 install and upgrade (both schema-less and populated 0.1.0),
  uninstall/reinstall, token/session/permissions and full Save/error/PRG tests.
- Confirm hook dispatch and actual CSS inclusion after platform-managed upgrade.
- Test at least the intended target themes, real language switches, CMS visibility
  and a real multistore configuration. Shared module tables are deliberate; CMS
  destinations are filtered by storefront shop/language. No shop-owned data migration
  is introduced.
- Test screen-reader behavior and keyboard workflows in the complete administration
  page; isolated checks are insufficient to certify accessibility.
- Construct a clean distributable with one root **`marclandtrust/`**, not the repository
  name `marcland-trust/`. Exclude `.git`, development tests, audit artifacts, credentials,
  logs and **all merchant-uploaded SVGs**; retain uploads protections and upgrade files.
  Preserve deployed merchant assets when updating an existing store.
- No release archive or official Marketplace validator was run. Licensing/copyright
  declarations, required logo/metadata and an explicit supported PS/PHP compatibility
  range are not finalized in this repository. These require owner/release review;
  no license or compliance claim was invented during the audit.
- The deliberately limited SVG subset rejects common design-tool styles/transforms.
  Document that limitation to merchants; do not broaden it without security review.

After the network prerequisite is applied, rerun the blocked integration checks and
resolve packaging/metadata requirements before reconsidering GO. Passing harnesses
does not justify promotion to `main` or a Marketplace-readiness claim.

## Changed files and Git boundary

Runtime fixes: `upgrade/upgrade-0.2.0.php`, `views/js/admin-config.js`,
`classes/TrustSvg.php`. Regression coverage: `tests/editor.js`, `tests/front.php`,
new `tests/lifecycle.php`, new `tests/concurrency.php`. Documentation: README and
this report. The final commit SHA is reported in the task response; it is not embedded
in its own contents. Fixes are committed/pushed only to `origin/develop` after validation.
No feature branches, PRs, forced pushes or main-branch changes are involved.
