# Resumed real PrestaShop 9 integration

Date: 27 October 2026. Starting commit:
`b79f946bc0251fe93e5b0df230b46dde4ba29d9a`, branch `develop`.

## Decision

**GO for promotion review of the tested functional target: PrestaShop 9.0.0,
PHP 8.4 and the bundled Classic theme, including the two-shop EN/FR configuration.**
The previously blocked integration checks were executed successfully after the
CSRF fix below. No unresolved module defect was demonstrated in these tests.

**NO-GO for Marketplace publication.** Licensing/copyright declarations, logo and
compatibility metadata, clean release packaging and official Marketplace validation
remain outstanding. Other themes, PS9 minor versions, PHP versions, screen readers
and non-super-admin profiles were not certified by this run. These boundaries are
not implied passes. No merge, branch, PR or main-branch change was performed.

This report supersedes the *real-store integration blocker* in the original
[Task 05 report](release-readiness.md); it does not turn earlier simulated tests into
real integration tests.

## Environment and evidence boundary

- Runtime network access was rechecked: unrestricted, enforced, revision 3.
- The official English pack downloaded successfully and passed ZIP integrity checks.
  A container HTTPS request also returned 200 with 507118 bytes.
- Official image `prestashop/prestashop:9.0.0`, previously recorded digest
  `sha256:668d0b9be1a7af6f0e9946df35bebec48c1acb5a6f7661a70bee98ca0d3225f2`.
- Isolated Docker network, disposable MariaDB database, localhost-only Apache port.
  No production store was reset, installed, migrated or tested.
- The actual installer printed `-- Installation successful! --`. The bundled Classic
  theme and demo products were installed. French was subsequently downloaded and
  installed using the real Language API with an AdminKernel; it returned true.
- Real installation used PHP 8.4.27, MariaDB 11.4.13 and Apache 2.4.68. Browser
  automation used system Chromium 154 through Playwright Core installed outside the
  repository. No runtime dependency was added to the module.

The first retry exposed a separate environment trust issue: PrestaShop overrides
PHP's default CA selection with its own cached bundle. The trusted session CA bundle
was copied into that cache and TLS verification remained enabled. A production-cache
warmup exceeded the CLI's 512 MB memory limit; it succeeded with a 2 GB CLI limit.
Neither correction changed module code or disabled a security check.

## Confirmed defect and minimal fix

**All legitimate PS9 configuration saves were rejected as invalid CSRF.**

The old handler compared the posted `trust_token` with a newly generated
`Tools::getAdminTokenLite('AdminModules')` string. PS9's Symfony CSRF values are masked
and randomized between requests. A valid token from the form can therefore differ
from the new request's encoding. A real authenticated browser Save reproduced HTTP
200 plus `Invalid admin session or security token`, with no database changes.

The handler now:

1. Retains the authenticated-employee and module-configure permission checks.
2. Binds the posted hidden token to the token in the generated form action URL.
   Those two values originate from the same render request.
3. Uses PrestaShop's existing public `UserTokenManager::isTokenValid()` to validate
   the incoming token against the authenticated session, rather than comparing it
   with a newly generated encoding.
4. Retains the constant-time legacy comparison where no Symfony container exists,
   and fails closed for missing/mismatched tokens or validator errors.

No service alias, custom cryptography, new service configuration or dependency is
included in the fix. An attempted direct access to Symfony's private token-manager
service was discarded in favor of the platform's public API. `tests/csrf.php` covers
prior-request token acceptance, mismatches, failed session validation and unavailable
validators. Real HTTP tests verified both successful Save and tampered-token rejection.

Runtime change: **only `marclandtrust.php`**. The database schema, SVG sanitizer,
editor, shared ownership model and retention policy are unchanged.

## Executed real-platform integration

| Area | Executed result |
| --- | --- |
| Fresh installation | Standard `prestashop:module install marclandtrust` succeeded. Both owned tables and both frontend hooks were verified in the real platform database. |
| Original schema-less 0.1.0 upgrade | Installed the actual original 0.1.0 PHP from commit `0fac1f44eab61b2f436d7dd1514f13ed63f3f882`; confirmed version 0.1.0 and zero owned tables. Deployed 0.2.0 and ran the platform core upgrade engine: success=true, one upgrade executed, no upgrades left, database version 0.2.0 and both tables/hooks present. |
| Standard upgrade command | After a transient native Distribution API failure, that API returned HTTP 200. A later populated 0.1.0→0.2.0 upgrade through the standard `prestashop:module upgrade marclandtrust` command succeeded and preserved all fixture rows, IDs and SVG bytes. The earlier failed command is not represented as a pass. |
| Uninstall/reinstall retention | Actual platform uninstall and latest reinstall preserved the complete zone/translation snapshot and SHA-256 of the saved SVG. Installing the original 0.1.0 over retained data and upgrading also preserved them. Both hooks were verified in both shops. |
| Real admin session | Logged in through the actual back-office login form, opened Module Manager and the module's configuration page. No browser fixture replaced the real module form. |
| Add and maximum | Added three zones, including an inactive one; Add was disabled at three. Database count remained zero until Save. New rows acquired real AUTO_INCREMENT IDs; repeated edits/reorders retained those IDs. |
| Real Save/redirect/reload | Classic multipart POST returned HTTP 302 and followed to a GET with confirmation. Three zone rows and six EN/FR translations were checked; reload restored text, visibility, links, icons and IDs exactly. |
| Language switching | English and French values survived switching in the same visible input and persisted across requests. Quotes, ampersands, accented characters and emoji round-tripped. |
| Error preservation | Overlength text produced a validation error and preserved the entered value in the form. The complete database snapshot remained unchanged. |
| Tampering | Real POSTs with mismatched CSRF, unknown zone ID, unexpected language ID or duplicate position were rejected. Database snapshots remained unchanged. |
| Stale editing | Opened two real editor tabs from the same revision. The first saved; the second was rejected as stale while preserving its submitted text. The second editor's text did not reach the database. |
| Reordering | Reordered and saved, verified ID-to-zone identity, then restored order. The database snapshot returned to the original state. |
| Custom SVG | Native browser FileList transferred through multipart POST, was sanitized and stored under a generated filename. Saved asset URL returned 200 and rendered as an image. A malicious replacement was rejected without deleting the existing reference/file. |
| Actual write failure | A temporary trigger in this disposable store forced a translation INSERT failure after a new sanitized file and zone changes had been staged. The real PrestaShop Db transaction restored all rows, removed the new file and retained existing icons. The admin message exposed no SQLSTATE or trigger details. The trigger was removed and its absence verified. |
| Real-core concurrency | Synchronized two independent PHP processes bootstrapping real PrestaShop and using real `Db::getInstance(true)` connections. Both attempted three zones from the same empty revision. Results: one COMMITTED, one STALE, exactly three zone rows and six translations. This is real persistence-service concurrency, not two simultaneous HTTP requests. |
| Explicit deletion | Remove made no database change until Save. Save removed the selected zone rows, their translations and the unreferenced SVG. A subsequent Add again made no early INSERT and received a fresh, larger database ID on Save. |
| Empty configuration | After Save removed all zones, actual product requests in both shops/languages rendered no module wrapper or fabricated claims. |
| Product hook and CSS | Actual Classic products in EN and FR for shops 1 and 2 rendered the hook. PrestaShop's combined stylesheet contained the module selectors, and computed layout was flex. A test initially looking only for a standalone CSS request was corrected to account for normal CCC bundling. |
| One/two/three/disabled | Actual product responses showed one, two or three enabled configured zones as selected. All-disabled configuration produced no wrapper. Disabled rows still counted toward the admin limit. |
| Translation absence | Emptying the first zone's French text removed it only from French product responses; English retained it. No English fallback appeared in French. Restoring French restored the zone. |
| Icons | Bundled and custom icons loaded with consistent sizing. Temporarily moving a custom file omitted only its image and retained all message text, without a broken image element. The file was restored. |
| CMS and anchors | Real generated CMS destinations returned actual CMS pages (not a soft error page) in both shops and languages. Anchor destinations matched `#product-details`. Removing a CMS association only from shop 2 left its message unlinked there, while shop 1 retained its CMS link; the association was restored. |
| Multistore ownership | Created shop 2 through core Shop/ShopUrl APIs and copied fixture associations. Used the actual backend shop-switch link; shop-2 CMS scoping was verified using a controlled association difference. A save from the shop-2 admin context appeared in both storefronts, confirming shared configuration. |
| Responsive layout | Actual Classic product components in all four shop/language contexts were checked at 320, 768 and 1280 px without component overflow. |
| Keyboard/escaping | On a real Classic product, the module link had its visible 2px focus outline and Enter activated the anchor. Text retained literal quotes/ampersand/emoji, while decorative icons had empty alt and aria-hidden=true. |
| Public upload policy | In the actual installed PrestaShop uploads path, a canonical SVG returned 200 with image/svg+xml, nosniff and sandbox CSP. A temporary PHP file returned 403 without executing. Both policy fixtures were removed. |

### Important multistore activation detail

Uninstall removes PrestaShop's per-shop module activation. Reinstalling while one shop
is selected does not automatically enable the module in every shop. After lifecycle
tests the module was explicitly enabled in **all** audit-shop contexts through the core
API, and two `module_shop` associations were verified before further storefront tests.
This is platform activation behavior, not shop-specific storage of trust zones.
Merchants must enable the module in each intended shop. The module does not force
activation in other shops as a side effect of saving shared configuration.

## Additional regression coverage executed

- PHP lint on every repository PHP file; JavaScript syntax check.
- `tests/schema.php`, `tests/security.php`, `tests/admin.php`, new `tests/csrf.php`,
  `tests/front.php`: passed with standalone doubles where documented.
- `tests/editor.js`: passed against its rendered Smarty fixture, including retained
  FileLists, identity and keyboard-reorder regression.
- `tests/persistence.php`, `tests/lifecycle.php`, `tests/concurrency.php`: rerun and
  passed in a separate disposable `trust_test` database on MariaDB. These remain
  adapter/double tests, not real-platform results merely because a real store exists.
- The actual store also compiled both module Smarty templates during real admin and
  product requests. No synthetic translation plugin was substituted in those requests.

The previous strict SVG adversarial suite and performance findings remain applicable;
this change does not alter sanitization, canonical validation or its measured XPath
optimization. Actual malformed SVG replacement and actual public-serving checks were
repeated, but not every old adversarial fixture was repeated through the real admin.

## Remaining limitations and publication gates

- Only the bundled Classic theme and PrestaShop **9.0.0 / PHP 8.4** were exercised.
  Different themes may place or omit the product hook. Other versions and themes
  require their own acceptance tests; no universal compatibility claim is made.
- Real admin tests used a super-admin. Permission-denied behavior is covered by
  standalone guards, not by a complete second back-office profile. No screen-reader
  certification, load test or browser-engine matrix was performed.
- Keep uploaded assets writable only where required, with static-only serving rules.
  Nginx and hosts ignoring `.htaccess` need the documented equivalent configuration.
- Database/filesystem commits are not distributed transactions. Crash/ambiguous-commit
  orphan limitations documented in README are unchanged.
- Licensing/copyright, logo, declared compatibility metadata and an official
  Marketplace validation remain owner/release work. Build a clean `marclandtrust/`
  archive excluding `.git`, tests, audit material, credentials and merchant SVGs;
  preserve uploaded merchant files during deployment. No release ZIP was certified.

All trust-message fixtures were synthetic and the tests were isolated. The temporary failure trigger and public
security fixtures were removed. Module fixtures were removed through the actual Save
flow; final owned-table counts were zero. Core demo products and the two-shop setup
were not production data. No credentials or authentication-state files were added
to the repository.
