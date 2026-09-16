# Implementation contract

Start from `.audit/arena/candidate-1/`. Apply SYNTHESIS.md. Do not copy candidate-2/3/4 files.

## Product

App id `dashboard_links`. Namespace `OCA\DashboardLinks`. Licence `AGPL-3.0-or-later`. Category `dashboard`. Nextcloud 33–35. PHP 8.2–8.5. Name must not contain Nextcloud.

Admins edit three lanes (Featured, Company, Reference) and Save. Users see seven Dashboard items, featured first, with an importance subtitle. Overflow is **All links**. iframe opens `/open/{id}` (embed page). redirect opens `/open/{id}` which 303s to https. Bookmarks survive a mode change.

Import from External sites is browser-only. PHP exposes `externalSitesAvailable` in initial state. Nothing is stored until Save. Dedup by normalised https href.

## Types

- `Importance` featured | normal | reference. `rank()` only. No label on the enum.
- `OpenMode` iframe | redirect.
- `LinkId` lowercase UUIDv4. Client-minted. `parse` / `tryParse`. No public `fresh()` in production code.
- `HttpsUrl` scheme https, no userinfo, max 2048. `host()`, `normalized()`.
- `Icon` content-addressed AppData file name. No remote URL case.
- `CompanyLink` id, title (1–120), href, importance, openMode, ?icon, enabled. No sort field. Valid by construction.
- `Catalog` immutable, canonical order (importance rank, then input order in the lane). Max 200. `revision()` is first 12 hex of sha256 over canonical JSON of links. Never stored.
- `VisibleLinks` only `Catalog::visible()` produces it. Closed under `after(?LinkId)` and `take(int)`.
- `LinkView` id, title, subtitle, href, iconUrl, overlayIconUrl.
- `LinkPresenter::views(VisibleLinks): list<LinkView>`.
- `CatalogStore::current(): Catalog` (lenient). `replace(Catalog, expectedRevision): Catalog` (strict). Equal content returns current and writes nothing. Mismatch throws `StaleCatalog`.
- `Icons::store / exists / open / deleteAll`. No `pruneExcept` on save.

## HTTP

```
GET  /ocs/v2.php/apps/dashboard_links/api/v1/catalog
PUT  /ocs/v2.php/apps/dashboard_links/api/v1/catalog
     { revision, featured: [row], normal: [row], reference: [row] }
     row = { id, title, href, openMode, icon, enabled }   # no importance
POST /icons
GET  /icons/{file}
GET  /
GET  /open/{id}
```

PUT 400 collects every field error. PUT 412 body is the current catalog in the same lane envelope.

Storage document (private). `{ schema: 1, links: [canonical CompanyLink rows including importance] }`. One lazy key `catalog`.

## Widget

`IAPIWidget` + `IAPIWidgetV2` + `IIconWidget` + `IButtonWidget`. `load()` empty. id `dashboard_links`. order 20. Items from `presenter->views(store->current()->visible()->after($since)->take($limit))`. TYPE_MORE when visible count > 7. TYPE_SETUP when empty and user is admin.

## Settings

info.xml `<settings>`. Own section. Initial state `catalog` (lane envelope + revision) and `externalSitesAvailable`. Vue 3 + `@nextcloud/vue` 9 + Vite 7. CSRF via `@nextcloud/axios`.

## Tests first for this unit

`tests/Unit/Links/CatalogTest.php` with literal expected values.

- parse three lanes, reject a row-level importance key
- canonical order featured then normal then reference
- duplicate id is InvalidCatalog
- revision equal iff links equal
- replace with same content is a no-op even with a stale expected revision
- replace with different content and stale revision throws StaleCatalog
- visible() drops disabled
- after(unknown) returns the full visible list
- HttpsUrl rejects http and userinfo
- Icon::parse rejects a URL

## Out of scope for unit 1

Admin Vue polish, GitHub publish, store certificate, screenshots, Transifex.
