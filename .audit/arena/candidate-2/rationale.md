# Design rationale

## Problem

The app must make one administrator-owned catalog serve three unlike callers:
an OCS list editor, Nextcloud's generic API-v2 Dashboard renderer, and an
authenticated iframe page. The non-obvious constraints are that Dashboard
registration is bootstrap-only, the web tile is fixed at seven items, iframe
mode is navigation to an owned page rather than embedding in the tile, icons
must be same-origin, and the optional External sites integration has no stable
PHP surface. Storage is a lazy `IAppConfig` array, while malformed storage,
malformed OCS input, and concurrent whole-catalog saves must not leak into the
domain.

## Usage (caller's view)

`USAGE.md` is the normative caller-facing specification. `Application`
registers only `LinksWidget`; `LinksWidget` asks `DashboardFeed::slice()` for
already sorted and mapped items; `AdminLinksController` parses its request into
a `CatalogReplacement` before calling `CatalogAdministration::replace()`; and
`PageController` asks `LinkNavigation::iframeSource()` for a source that is
known to be enabled and iframe-capable. These are the only application-facing
operations. The browser's optional import reads External sites over admin OCS
and then uses the same replacement operation as manual editing.

## Shape

The primary data structure is one versioned catalog envelope stored under one
lazy app-config key: schema version, revision, and the complete link list.
Opaque random `LinkId`s remove a shared max-id allocator. Keeping revision and
records in one value makes replacement atomic and makes a stale-editor check
possible without synchronizing a second key, per
`separate-before-serializing-shared-state` and
`make-operations-idempotent`.

`CompanyLink` contains value objects rather than primitives where validity
matters. `HttpsUrl`, `SortPosition`, `LinkId`, and `LocalIcon` validate at their
creation boundaries. Open mode is a structural choice between
`IframeTarget` and `RedirectTarget`; there is no independent mode string that
can disagree with a URL. Importance and visibility are enums. New versus
existing records are represented by a nullable id only in `SubmittedLink`,
never in stored `CompanyLink`. Consequently illegal combinations fail in
`OcsCatalogRequestParser` or stored-envelope parsing and trusted code does not
revalidate them, per `boundary-discipline` and
`encode-lessons-in-structure`.

One implementation, `AppConfigLinkCatalog`, owns persistence encoding,
revision checks, id allocation, filtering, importance ranking, stable sorting,
pagination, open-mode resolution, same-origin icon resolution, and
`WidgetItem` mapping. Callers see three role interfaces:
`CatalogAdministration`, `DashboardFeed`, and `LinkNavigation`. This is a deep
module: six small operations hide all catalog policy and framework adaptation,
while each caller receives only its role. The interfaces expose domain values
or final presentation values, never app-config arrays or OCS payloads. The
implementation groups code by catalog knowledge rather than load/validate/map
execution stages, avoiding temporal decomposition.

The save-to-tile path crosses the controller/parser, catalog, and widget—three
files at most. Widget rendering is a single catalog call, not a pipeline of
repositories, sorters, resolvers, and mappers. The embed endpoint similarly
uses one capability that rejects missing, disabled, and redirect-only links
before CSP changes. There is one source of truth for ordering and one source of
truth for destination resolution. The app deliberately has no dashboard
JavaScript, no layout writer, no `OCA\External` reference, and no
`external/sites` app-config access.

## Synthesis decision

pending

## Tradeoffs accepted

- We accept rewriting one lazy config value for every admin save in exchange
  for atomic catalog state and no migration/table machinery.
- We accept optimistic conflicts between concurrent admin sessions in exchange
  for never silently losing another administrator's complete-catalog edit.
- We accept opaque random ids in exchange for removing a coordinated counter
  and making retries safe when the client retains allocated ids.
- We accept one policy-dense catalog implementation in exchange for short call
  chains and small role-specific public interfaces.
- We accept dropping unsupported imported icons in exchange for preserving the
  same-origin icon invariant.

## Alternatives considered

- A CRUD service plus separate validator, repository, sorter, URL resolver,
  and widget mapper was rejected. Each class is individually simple, but
  callers must coordinate stages and the storage representation and ordering
  policy leak across a deep call chain; it has lower interface depth.
- A normalized database table with one row per link was rejected. It hides
  query ordering well but exposes transaction, migration, and id-allocation
  concerns for a small catalog whose dominant write is complete replacement;
  the single app-config envelope is the deeper interface for this workload.
- A live adapter over the External sites app was rejected. It appears to hide
  duplicate storage but exposes app availability, version-specific private
  schemas, group filtering, and failure semantics to every read caller.
  Browser-side one-shot OCS import contains that optional complexity at one
  boundary.

## Open questions and risks

- Should a conflicting save return HTTP/OCS conflict code 409, and does the
  chosen Nextcloud OCS exception mapping preserve that code on all versions
  33–35?
- What upper bound should the parser impose on catalog size, title length, and
  URL length to keep the single lazy config value operationally safe?
- Should imported SVG/icon content be copied through a dedicated validated
  upload endpoint, or is omitting imported icons sufficient for version one?
- Should iframe mode allow all HTTPS origins, or should administrators also
  maintain an explicit framing-origin allowlist?

## Next implementation step

Build value-object parsers and round-trip tests for the versioned app-config
envelope before wiring any controller or widget.
