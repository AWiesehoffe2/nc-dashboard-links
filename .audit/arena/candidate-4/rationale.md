<!--
  SPDX-FileCopyrightText: 2026 the dashboard_links authors
  SPDX-License-Identifier: AGPL-3.0-or-later
-->

# dashboard_links — design rationale

## Problem

We need a Dashboard tile showing an admin-maintained list of company links, with
importance visible to users, per-link iframe-or-redirect behaviour, and an
optional one-shot import from the official External sites app. The shape is
non-obvious because almost every interesting decision is forced by a system we
do not own. The Dashboard renders API v2 tiles with its own Vue, so we ship no
tile JavaScript and `load()` must stay empty; the frontend never sends `limit`,
so the tile is exactly seven items and overflow has to go somewhere we own. The
Dashboard page's CSP (`frame-src 'self'`, `img-src 'self' data: blob:`) means a
tile can never embed a site and an off-origin favicon renders blank, so
"iframe mode" and "icon URL" cannot mean what they sound like. The catalog lives
in `IAppConfig`, whose value type is locked by its first write and whose lazy
keys return defaults when read non-lazily, so the storage shape is a one-way
door. `external` exposes no PHP API, no events and no table, and its config is a
private, unfiltered schema with a write-on-read migration — so reuse can only be
an import driven from the admin's browser. And the domain itself contains
combinations that must be impossible rather than merely validated: a non-https
href, an off-origin icon, a disabled link on the tile, a redirect-only link
being framed.

## Usage (caller's view)

Written first, in [`USAGE.md`](USAGE.md); the sketch below is derived from it.
The load-bearing parts of that contract:

- **Admins edit one list.** Settings → Administration → Company links is a
  single drag-and-drop catalog. There is no sort number to type: row position
  *is* the sort. Saving `PUT`s the whole catalog plus the `revision` it was
  editing, and a concurrent save comes back as `412` with the current catalog
  attached rather than silently winning.
- **Importance is the only ranking control**, and users see it three ways with
  zero custom JavaScript: position on the tile, a badge overlay on the logo
  (`WidgetItem::$overlayIconUrl`), and a localised subtitle.
- **Logos are same-origin by construction**: a shipped asset from `img/links/`
  or a raster upload served by our own `IconController`. There is no field in
  which to type a remote favicon URL.
- **Open mode is a destination, not a rendering.** `redirect` links go to the
  site; `iframe` links go to `/apps/dashboard_links/open/{id}`, our page, which
  widens `frame-src` to that one site's origin.
- **Import degrades to nothing.** The admin's browser reads `external`'s admin
  OCS and posts the records to us. No `external`, no button. Re-running it
  upserts.

The three call sites in `USAGE.md` are the whole app in miniature:
`Application::register()` (one line, nothing resolved), `LinksWidget::items()`
(`$store->load()->visible()->page($limit, $since)` → `$presenter->views()` →
`WidgetItem`), and `CatalogApiController::save()`
(`SubmittedCatalog::fromOcs()` → `$store->save()`, with two catch arms).

## Shape

**Data structures first.** One aggregate, `LinkCatalog`, holding
`list<Link>` canonically ordered by (importance rank, sort, id) and unique by
id, both established once in `LinkCatalog::of()`. `Link` is built from value
types that refuse illegal input in their constructors: `LinkTitle`, `HttpsUrl`,
`Importance`, `OpenMode`, and a sealed `LinkIcon` (`BundledIcon | AppDataIcon |
null`). `sort` exists on `Link` because the domain requires it, but no client
can set it — it is assigned from submitted row position, so display order and
stored order cannot disagree (*derive instead of sync*).

**The four dominant accesses traced through that structure:**

1. *Admin saves the whole catalog.* `SubmittedCatalog::fromOcs(array, string)`
   → `CatalogStore::save()`. `SubmittedLink` has no `sort` and no `icon` field,
   so the two things a client could get wrong are not expressible.
2. *Widget reads enabled links in order.* `load()->visible()->page(7, $since)`.
   No sort, no filter, no branch at the call site; the ordering happened at
   construction and `visible()` is the only door out of a catalog to a
   user-facing surface.
3. *Resolve href from open mode.* `LinkPresenter::views(VisibleLinks)`, one
   method, the single owner of every URL in the app.
4. *Map to `WidgetItem`.* One private method in `LinksWidget`, `LinkView` →
   `WidgetItem`, used by both `getItems()` and `getItemsV2()`.

No step in any of those answers "we'll add an index or a cache later." The
catalog is tens of rows in a lazy `IAppConfig` array; `page()` is a slice of an
already-ordered list.

**Invariants encoded in types, not checks.** Non-https href: no `Url` type
exists that could hold one. Off-origin icon: `LinkIcon` has no URL case, so the
CSP fact is structural rather than reviewed (*encode-lessons-in-structure*).
Disabled link on the tile: `LinkPresenter::views()` accepts `VisibleLinks`, a
type only `LinkCatalog::visible()` can produce. Framing a redirect-only link:
`LinkCatalog::embeddable()` throws `LinkNotEmbeddable`, and that one throw *is*
the embed page's 404 branch. Duplicate ids: `LinkCatalog::of()` rejects. Tile
embedding a site: nothing in the pipeline can produce an iframe — `LinkView`
carries a URL string.

**Validation lives at three boundaries and nowhere else** (*boundary-discipline*).
`SubmittedCatalog::fromOcs()` for admin writes, strict, collecting every field
error before throwing so the UI marks all bad rows at once.
`ImportBatch::fromExternalSites()` for another app's records, tolerant, turning
bad sites into `SkippedSite` entries the admin sees by name.
`CatalogStore::load()` for our own stored rows, tolerant: a row that no longer
parses is dropped and logged, because one bad row must never blank every user's
tile. That asymmetry is deliberate and is the one thing a reviewer would
otherwise read as sloppiness.

**Shared state: two admins.** A whole-catalog `PUT` makes the last writer clobber
the other's rows, which is exactly the "what happens?" the
*separate-before-serializing-shared-state* question asks. Per-actor state and a
merge is the wrong answer here — there is one instance-wide catalog by
definition, and nothing in this app is per-user at all. So the merge moves to the
*read* boundary in a different form: the catalog carries a `CatalogRevision`,
saves present the revision they started from, and a mismatch is `412` with the
current catalog attached so the human resolves it with full information. The
revision is a **content hash of the link list, not a counter** — derived, so
there is no version field to bump, nothing to forget, and no way for the token
to drift from the rows it describes (*single source of truth*).

**Idempotence falls out of that** (*make-operations-idempotent*). Replaying a
no-op save is a `200`, because the submitted content already hashes to the
current revision. Replaying a save that created rows is a `412` — and that is
the point: the guard is precisely what stops a retried create from inserting the
row twice. Import needs no client revision at all, because it upserts on
`ExternalSiteId`; running it twice reports `added: 0`. `setIcon()` writes the
blob before the row, so a crash between them leaves an invisible orphan blob
rather than a row pointing at nothing. Tolerant reads plus content-hash
revisions also make corrupt rows self-healing: the admin's next save writes the
already-cleaned list, with no repair step.

**Interface depth.** `CatalogStore` is four methods —
`load`, `save`, `import`, `setIcon` — hiding the `IAppConfig` keys and their
lazy/array types, the row schema, id allocation from a never-reused `max_id`,
sort assignment from position, icon carry-over across a save, orphan blob
cleanup, the revision check and its one retry, and the strict/tolerant
asymmetry. No caller has ever seen an `IAppConfig` array or a storage key.
`LinkPresenter` is **one** public method hiding open-mode href resolution, the
same-origin icon rules with their fallback and cache-busting, and the importance
label and badge. What stays exposed to callers is only what the framework
dictates: `LinksWidget`'s surface is the four OCP widget interfaces, and the
controllers' surface is HTTP.

**Call chains are two hops.** Admin save is
`CatalogApiController` → `CatalogStore` → `LinkCatalog`. Tile render is
`LinksWidget` → `CatalogStore` → `LinkPresenter`. Routes are attributes rather
than `appinfo/routes.php`, so a route and its handler are one file. There is no
`LinkService`, no repository, no mapper layer, and no separate
parse/validate/save modules — `CatalogStore` owns the catalog's whole lifecycle
because every one of those operations protects the same three decisions (ids,
order, revision), and splitting them by execution order would be textbook
temporal decomposition.

**What the system deliberately does not do.** It never writes another user's
dashboard layout. It never imports an `OCA\External\*` symbol, reads or writes
`external`'s config, or links to `external.site.showPage` — imported links get
our embed page like any other, so a disabled `external` cannot break a link we
are showing, and `LinkPresenter` needs no `IAppManager`. It never widens
`frame-src` to `*`. It stores no per-user state, ships no dashboard JavaScript,
and implements neither `IReloadableWidget` (a static catalog gains nothing from
polling) nor `IConditionalWidget` (the empty-state message is the honest answer
to an empty catalog, and hiding a tile a user deliberately added is worse).

**Red-flag screen.** Shallow modules: the two modules that matter have 4 and 1
public methods against substantial hidden policy. `IconStore` is the thinnest
(3 methods over an AppData folder) and is justified only because it has two
independent consumers, `CatalogStore::setIcon()` and `IconController::show()`;
it is not a pass-through, since `setIcon()` adds the row write and the revision.
Information leakage: the storage row schema exists only in `CatalogStore`,
`external`'s record schema only in `ImportBatch::fromExternalSites()`, and
`OCP\Dashboard\Model\*` only in `LinksWidget`. Pass-throughs: `getItems()` and
`getItemsV2()` both delegate to one private mapper, which is two framework
interfaces over one behaviour rather than a layer.

## Synthesis decision

pending

## Tradeoffs accepted

- We accept a whole-catalog `PUT` and the `412` handling it forces on the
  settings UI, in exchange for row position being the sort, reordering being one
  request, and replay safety coming from one guard instead of four endpoints
  each doing their own read-modify-write.
- We accept that a content-hash revision conflicts even when two admins made
  byte-identical edits to different rows, in exchange for having no version
  counter to store, bump, or desynchronise from the rows.
- We accept that `CatalogStore::load()` silently drops unparseable rows (logged)
  rather than failing loudly, in exchange for one corrupt row never blanking
  every user's dashboard. Combined with content-hash revisions this makes the
  drop permanent on the admin's next save, which looks like data loss and is
  actually the repair step we then do not need.
- We accept that logos need a second request after the catalog save, because a
  new link has no id until it is saved, in exchange for `SubmittedLink` having
  no icon field the UI could send wrong and a save never being able to clobber
  an icon.
- We accept raster-only logo uploads (no SVG), in exchange for not serving
  admin-supplied script-bearing documents same-origin.
- We accept losing `external`'s JWT and placeholder substitution on imported
  links, in exchange for no runtime coupling to an app that is on
  critical-bugs-only maintenance and ships one major per Nextcloud release.
- We accept `LinkView` as an app-owned presentation type rather than building
  `WidgetItem` directly, in exchange for a JavaScript-free overflow page that
  renders from the identical data as the tile and cannot drift from it.
- We accept one extra `IAppConfig` key for `max_id` rather than deriving
  `max(id) + 1`, so ids are never reused and a bookmarked
  `/open/{id}` or a stale `since` cursor can never resolve to a different link
  than it did before.
- We accept that `7` appears both as the `getItems*` default and as the
  `TYPE_MORE` threshold, because `getWidgetButtons()` is served by a different
  OCS call that has no limit to consult. It is one named constant, not two
  literals.

## Alternatives considered

- **Per-link CRUD behind a `LinkService`** (`POST`/`PUT {id}`/`DELETE {id}`, the
  shape `external` uses and the grounding doc's default). Exposes more and hides
  less: `sort` becomes a number the client chooses and must keep consistent with
  what it displays, reordering becomes N requests, and the clobber risk is
  unchanged but now spread across four handlers that each read-modify-write.
  Callers would have to learn our ordering rules to use the interface correctly,
  which is the definition of a shallow one. Lost.
- **A database table with a QueryBuilder repository.** Ordering would hide
  behind `ORDER BY`, which is genuinely deeper, but it costs migrations, an
  entity/mapper pair, and per-request queries for a list of tens of rows already
  sitting in the lazy config cache. It also breaks the locked `IAppConfig`
  constraint. Lost on cost, not on shape.
- **A mutable catalog service (`addLink`/`updateLink`/`reorder`) with an
  in-memory cache invalidated by events.** Two sources of truth for order and
  the "add a cache later" smell the *data-structures-first* discipline warns
  about, for no reduction in caller-visible surface. Lost.
- **Own dashboard bundle via `OCA.Dashboard.register`.** The only way to control
  `_blank` on redirect links and the only way to get richer importance styling
  than a badge and a subtitle. It hides nothing extra from PHP callers while
  adding a second render path, a Vue app, and a fight with the generic V2
  renderer. Lost; the `_blank` uncertainty is demoted to an open question below.
- **Imported iframe links pointing at `external.site.showPage`.** Hides
  `external`'s JWT and placeholder machinery behind their route, which is real
  capability we are giving up. But it makes our records depend on another app's
  site ids and enablement, and pushes `IAppManager` into the presenter so every
  render asks whether a neighbour is installed. Lost.
- **Deriving the icon reference from AppData existence instead of storing it.**
  Removes a field to keep in sync, which is attractive. Costs a storage stat per
  rendered item and cannot express bundled icons at all. Lost.

## Open questions and risks

- Does `NcDashboardWidgetItem` open `WidgetItem::$link` in a new tab? If
  redirect-mode links must open in a new tab and it does not, is in-tab
  navigation acceptable, or is that worth abandoning the zero-JavaScript tile
  for a custom bundle?
- The task locks ordering to importance-major, so dragging a row from
  `reference` into the `featured` band changes its position by changing its
  importance. Is that the intended admin mental model, or should drag order be
  absolute with importance reduced to a badge?
- Is `#[PasswordConfirmationRequired]` on the catalog save too heavy for routine
  edits, given it re-prompts every 30 minutes? Should it apply only to icon
  uploads and deletions?
- Does `registerConfigLexicon()` exist as of NC 33? If it does, should the
  `links`/`max_id` key types and lazy flags move there, leaving `CatalogStore`
  as the only *writer* but no longer the only place a key is named?
- `max-version`: ship `35`, or `36` under the store's "latest + 1" rule if 35.0
  is actually published?
- Should the overflow page also get a `<navigations>` entry, or stay reachable
  only from the tile's header and `TYPE_MORE` button?
- Should SVG logos be accepted with strict sanitisation, since company logos are
  usually vector and raster-only will be the first complaint?
- Risk: the `412` path is the only part of this design that pushes work onto the
  admin UI. If that reconciliation UX is not built properly, the API is correct
  and the product feels broken.

## Next implementation step

Build `lib/Catalog` — the value types, `LinkCatalog::of()`, `VisibleLinks::page()`
and `CatalogRevision::ofLinks()` — with unit tests, since it is pure, needs no
Nextcloud runtime, and every other file in the sketch is typed against it.
