# Rationale: dashboard_links, candidate 1

## Problem

Ship a Marketplace app whose Dashboard tile lists admin-configured company links with visible importance, per-link iframe-or-redirect opening, and an optional one-shot import from External sites. The server fixes most of the shape: the tile is rendered by the server's Vue from an `IAPIWidgetV2` payload (so a `WidgetItem::link` is a navigation, never an embed), the web always asks for 7 items, widget registration lives only in `IBootstrap`, settings live in `info.xml`, the catalog must sit in `IAppConfig` as a lazy array with its type locked on first write, and `external` offers no PHP API, so reuse is browser-side OCS or nothing. Icons must be same-origin because the Dashboard's `img-src` is `'self'`. PHP 8.2 rules out typed constants. What is left to design is the catalog itself: how the admin writes it, how the widget reads it, how ids and order are owned, and how two admins editing at once do not lose work.

## Usage (caller's view)

Full README in `USAGE.md`. The admin edits one page with three bands (featured, normal, reference), arranges rows by dragging, and presses Save; the browser PUTs the whole catalog with the `revision` it read. A conflicting save returns 412 with the current catalog. Import from External sites is a browser-side draft operation; nothing is stored until Save. Users see 7 links, featured first, and an **All links** button when there are more.

The three call sites the sketch is derived from:

```php
// Widget read path
$page = $this->store->current()->visible()->after(LinkId::tryParse($since))->take($limit);
return new WidgetItems(array_map($this->toItem(...), $page->links()), $this->l10n->t('No company links configured yet'));

// Admin write path
$saved = $this->store->replace(Catalog::parse($links), $revision);   // InvalidCatalog → 400, StaleCatalog → 412

// Open-mode resolution
return match ($link->openMode) {
	OpenMode::Redirect => new RedirectResponse((string) $link->href),
	OpenMode::Iframe => $this->frame($link),
};
```

## Shape

**Data.** `CompanyLink` is a `final readonly` value object of value types: `LinkId` (client-minted UUIDv4), `HttpsUrl` (scheme exactly https, no userinfo), `Importance` and `OpenMode` enums, `?Icon` (content-addressed AppData file name), `bool $enabled`, and a title checked in the constructor. If you hold a `CompanyLink`, every field is legal, per encode-lessons-in-structure. There is no `sort` field: a link's position is its index in its `Catalog`, and `Catalog` is always in canonical order (stable sort by `Importance::rank()`, input order preserved within a band). The admin UI, the wire, the stored document and the tile therefore share one ordering with one definition, per single source of truth.

**Ids are minted by the client.** The server never assigns ids, so there is no counter, no second config key, no "assign then reconcile" protocol in the admin UI, and no draft-versus-persisted pair of types. A save is a pure function of its input. Ids are opaque strings to `WidgetItem::sinceId` and to the `/open/{id}` route, which is all they are used for.

**Whole-catalog replace.** `CatalogStore` exposes two methods: `current(): Catalog` and `replace(Catalog $next, string $expectedRevision): Catalog`. That is the entire persistence surface. Behind it: one lazy `setValueArray` holding `{schema, links}`, lenient reads that drop corrupt rows instead of taking the Dashboard down, optimistic concurrency via `Catalog::revision()` (a hash derived from content, never stored), the idempotency rule "equal content succeeds regardless of revision" so retried saves are safe, icon-existence checks, and orphan-icon pruning after the single write, per make-operations-idempotent. Two admins editing is real shared state; per separate-before-serializing-shared-state the question "what happens if both write" is answered explicitly. Per-actor drafts merged at read would be overkill for a list a few admins touch a few times a year, so the answer is a revision token and a 412 whose body is the other admin's catalog.

**Boundaries.** `CompanyLink::parse(mixed)` and `Catalog::parse(array)` are the only functions that accept untrusted arrays, per boundary-discipline. Everything past them is typed. `Catalog::parse` collects every row's first error into `InvalidCatalog` so the admin sees all mistakes in one round trip. The same canonical JSON (`CompanyLink::jsonSerialize()`) is used on the OCS wire and inside the stored document; the storage envelope is private to `CatalogStore`.

**Routes in one place.** `LinkUrls` owns every URL handed to a browser: open target by mode, icon URL (uploaded or app default), all-links page, settings page. The widget, the all-links page and the settings UI cannot drift. Open-mode knowledge appears in exactly two exhaustive `match`es on `OpenMode`: `LinkUrls::openUrl()` (what to put on a tile) and `PageController::open()` (what to serve when the embed route is hit). Both modes are served on `/open/{id}` so a bookmark survives a mode change.

**Interface depth.** Public surface a caller must learn: `Catalog` (a small immutable collection), `CompanyLink` and its value types, `CatalogStore` (two methods), `LinkUrls` (five), `Icons` (five). Hidden: storage schema and laziness, revision derivation, corruption handling, canonical ordering, since-paging semantics, route names, content addressing, mime sniffing, CSP for the frame and the icon response. The save-to-tile trace is `CatalogController` → `CatalogStore` → `LinksWidget`, three files, per minimize-reader-load. The `Links` namespace has no `OCP` dependency except in its two adapters and `LinkUrls`, so `Catalog` and `CompanyLink` are unit-tested without a server checkout.

**Deliberately not done.** No `IConditionalWidget` (an empty tile must be able to show admins a Configure button). No `IReloadableWidget`, no capability, no `occ` command, no per-link groups or languages, no Vue on the all-links page (server-rendered), no dashboard JavaScript. Import from External sites has zero PHP: the settings form exposes `externalSitesAvailable` in initial state and the browser does the rest, so a missing `external` costs nothing, per subtract-before-you-add.

## Synthesis decision

pending

## Tradeoffs accepted

- We accept client-minted UUIDs (long URLs, `curl` users must mint their own) in exchange for a stateless, idempotent save with one link type and no id-assignment protocol.
- We accept one canonical JSON shape for both the wire and storage in exchange for one codec; a storage-only change would need a schema bump in `CatalogStore`, which is why the envelope carries `schema`.
- We accept last-writer-must-retry (412) rather than merging concurrent admin edits, in exchange for no per-actor state; the 412 body carries the other version so nothing is lost.
- We accept that `replace()` prunes icons on every save, which can delete an icon another admin uploaded but has not yet saved; that admin's save then fails on the icon field with a clear message rather than storing a dangling reference.
- We accept lenient reads (drop corrupt rows with a warning) over strict ones, because the read runs on every Dashboard load and a thrown exception there hides the whole tile from every user.
- We accept `frame-src *` on the embed page, matching External sites, because a per-origin allowlist breaks SSO redirect chains inside the frame; the page contains only our template and one admin-chosen `src`.
- We accept SVG icons served with a `sandbox; default-src 'none'` CSP rather than banning SVG, because company logos are usually SVG.
- We accept that position-as-sort means the admin cannot interleave bands; that is the product rule (featured before normal before reference), not a limitation.
- We accept a hard cap of 200 links and 256 KB per icon to bound the lazy config blob and AppData growth.

## Alternatives considered

- **Per-link CRUD with server-minted integer ids and a `max_id` counter** (the `external` shape). Exposes to callers: id assignment and reconciliation after POST, a separate `sort` integer and a reorder call, N round trips per edit session, partial-failure states across two config keys. Hides less than it costs. Lost on interface depth and idempotency.
- **Live wrapper over External sites** (the `externalportal` shape): the tile reads `external`'s OCS or appconfig at render time. Exposes: a hard runtime dependency on an app maintained critical-bugs-only, no importance dimension, coupling to a private JSON schema with a write-on-read migration. Lost because the locked constraints forbid it and because it owns nothing.
- **Resolve the open target at parse time and store it** (a `Target` sum type inside `CompanyLink`, or a `LinkOpener` that both builds URLs and returns HTTP responses). Exposes route knowledge to the domain and makes the pure types depend on `IURLGenerator`. Lost because it removes the property that `Links` value types are testable without a server; `LinkUrls` keeps the same single source of route truth without that cost.
- **Explicit `sort` integer plus positional order in the list.** Two sources of truth for one fact; every save must reconcile them. Lost on single-source-of-truth.

## Open questions and risks

- Does `NcDashboardWidgetItem` open `link` in a new tab? If not, Redirect links navigate the Dashboard tab away; is that acceptable, or would you fund a custom dashboard bundle to get `_blank`?
- Should Reference links be excluded from the tile entirely (all-links page only) rather than merely sorted last and usually cut by the 7-item limit?
- Is the config lexicon (`registerConfigLexicon`) available with a `@since` ≤ 33? If yes, should the `catalog` key be declared there to make lazy-array locking declarative?
- Do we want `#[PasswordConfirmationRequired]` on `PUT catalog` and `POST icons`? It hardens destructive edits at the cost of a password prompt on every save.
- Should group admins edit links via `IDelegatedSettings`? Whole-catalog replace makes delegation all-or-nothing; is that acceptable?
- Are 200 links, 120-character titles and 256 KB icons the right caps?
- Risk: attribute routing (`#[ApiRoute]`, `#[FrontpageRoute]`) without `appinfo/routes.php` must be confirmed on NC 33; otherwise the same routes move to `routes.php` with no shape change.
- Risk: the Dashboard `img-src 'self'` claim was not verified by the explorers; the same-origin icon design is correct either way, but if `img-src` is wider the icon upload could later become optional.

## Next implementation step

Write `lib/Links/` value types, `CompanyLink` and `Catalog` together with `tests/Unit/Links/CatalogTest.php` (canonical ordering, parse/serialize round-trip, duplicate ids, `after()` with an unknown anchor, revision equality), since every other file is derived from those types.
