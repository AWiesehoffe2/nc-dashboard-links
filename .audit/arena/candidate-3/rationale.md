## Problem

`dashboard_links` must put an admin-owned company-link catalog on the Dashboard without shipping tile JavaScript, without embedding in the tile (Dashboard CSP is `frame-src 'self'`), and without a PHP dependency on External (no public API, private appconfig, write-on-read). The catalog is one shared instance list; the tile is a seven-item projection with visible importance; overflow is a page we own. The non-obvious part is how to store importance, open mode, and ids so illegal combinations cannot leak into the widget mapper or the iframe page.

## Usage (caller's view)

Admins edit three lanes (Featured / Company / Reference) and hit Save. That is one PUT of the whole catalog. Users never configure rows; they tick the widget, click a resolved target, or follow More links. Import is the admin browser forwarding External’s admin OCS payload to our POST; missing External is a failed browser call, not a PHP `use`. The three PHP call sites in `USAGE.md` are the contract: `IBootstrap` registers only the widget; `APIController` calls `LinkCatalog::saveFromAdmin` / `importExternal` and never sees appconfig arrays; `LinksWidget` / `PageController` read `Catalog::sliceVisible` / `find` and send every navigation through `LinkTarget::resolve`.

## Shape

The aggregate is a **laned `Catalog`**. `CompanyLink` has title, `HttpsUrl`, `OpenMode`, `LinkIcon`, enabled, sort — and no importance field. Importance is which `LinkLane` holds the row. `DisplayedLink` is a read model produced only by `visible()` / `sliceVisible()`, so the subtitle cannot disagree with display order. Parse rejects a flat `links` + `importance` payload.

Writes are **atomic replace** of one lazy `IAppConfig` blob (`catalog` = version + maxId + three lanes). `maxId` lives inside that blob so id issue and row persist cannot tear. `IdIssuer` only grows. `LinkCatalog` is the deep module: load, parse, persist, prune unused stored icons, import-merge. `ExternalSiteImport` is the only type that knows External’s row shape; merge is by `HttpsUrl::normalized()`, keeps the existing lane, appends newcomers to Company.

`LinkTarget` + `AppRoutes` own navigation. Iframe mode always yields our embed route; redirect mode always yields the https href. `embed()` 302s redirect-mode ids instead of framing them. Icons are `none | bundled | stored` — never a URL.

Interface depth: callers have `saveFromAdmin`, `importExternal`, and `get()`. Hidden behind that: id policy, lane sort, href merge, icon GC, open-mode resolution, storage schema. Exposed: domain values the admin Vue and the tile must show. No transport arrays, no `OCA\External\*`, no `WidgetItem` on the catalog. Save-to-tile is three files (`APIController` → `LinkCatalog` → `LinksWidget` reading `Catalog`). Validation lives at `::parse` boundaries; interior code trusts the types (`boundary-discipline`, `encode-lessons-in-structure`). Two admins saving is last-write-wins on one blob (`make-operations-idempotent` on the payload, not a lock).

Deliberately omitted: per-link CRUD, group/lang/device filters, live External mirror, `IReloadableWidget`, `IOptionWidget`, capability, layout rewriting, widget JS.

## Synthesis decision

pending

## Tradeoffs accepted

- We accept last-write-wins when two admins save or import at once, in exchange for one atomic blob and no locking.
- We accept that the admin Vue must be a three-lane editor, in exchange for importance that cannot drift from sort order.
- We accept dropping External icons on import, in exchange for never pointing `img-src` at another app’s private files.
- We accept unused id gaps after delete or after an import draft merges onto an existing href, in exchange for never-reused `since` / embed ids.
- We accept no per-link REST, in exchange for a write surface the widget cannot misuse and a Save that matches the dominant access pattern.

## Alternatives considered

- **Flat `CompanyLink.importance` plus POST/PUT/DELETE.** Smaller Vue, but callers coordinate four endpoints and display order is a convention two modules can get wrong. Shallower: the service exposes stages (create, update, sort, rank) instead of one replace. Lost on interface depth.
- **Two appconfig keys (`links` + `max_id`).** Matches External’s `max_site`, but a crash between the writes can mint an id without a row or reuse after a partial replace. Lost; the blob is the single source.
- **Live read of External (SitesManager, `external/sites`, or server-side OCS loopback).** Hides import from the Vue, but leaks a private schema, needs credentials for loopback, and breaks when External is disabled. Lost; our catalog is the only source of truth.
- **Custom dashboard bundle so the tile can iframe.** Would expose open mode as a DOM policy the generic V2 renderer does not have, and `load()` must stay empty. Lost; embed is a page we own.

## Open questions and risks

- Does `registerConfigLexicon()` exist and stay stable on the Nextcloud 33 floor, or do we document the lazy array key in code only?
- If `NcDashboardWidgetItem` always opens same-tab, do redirect links need a follow-up, or is same-tab acceptable for v1?
- Should PUT catalog require `#[PasswordConfirmationRequired]`, given one request can empty the instance list?
- Is import-persist-immediately right, or does the admin need a preview of skipped rows before the merge is written?

## Next implementation step

Implement `Catalog` / `LinkLane` / `CompanyLink` parse and `LinkCatalog` get/save on the single lazy `catalog` blob, then fill `LinksWidget::toItem` against `LinkTarget`.
