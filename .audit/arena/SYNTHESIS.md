# Arena synthesis

## Base

candidate-1. Cross-judge [Arena cross-judge](0ea644d6-ebe0-4268-b70e-0e656a3d8752) picked the same base.

A maintainer extends `OCA\DashboardLinks\Links` without touching persistence or concurrency. Order is the index in a canonical `Catalog`. Ids are client UUIDs. Revision is a content hash. One `setValueArray`. Adding a field edits `CompanyLink`. Adding a route edits `LinkUrls` inside the presenter.

candidate-4 is closer on presentation. Grafting 1 into 4 rewrites the store. Grafting 4 into 1 is additive. **Laziness Protocol** takes the smaller write surface.

## Rejected as base

- candidate-2. Wrong `OCP\Dashboard` namespaces. No settings, icon, or uninstall. `WidgetItem` built inside persistence.
- candidate-3. No revision. Transport arrays on `LinkCatalog`. Domain constructible only from a wire array.
- candidate-4. Two config keys (`links` + `max_id`). Server ids. Icon bound to link id. A retried create is 412.

## Grafts applied

1. From candidate-4. `VisibleLinks` is what `Catalog::visible()` returns. `LinkPresenter::views()` yields `LinkView` for the tile and the all-links page. Importance label and overlay live in the presenter, not on the enum. `LinkUrls` is the presenter's only collaborator.
2. From candidate-4. Storage codec is private to `CatalogStore`. Admin wire is private to the controller. `CompanyLink::parse` remains the only untrusted-array door.
3. From candidate-3. Admin PUT is `{revision, featured, normal, reference}`. A row-level `importance` key is invalid. `CompanyLink.importance` is set from the lane. Import dedupes with `HttpsUrl::normalized()`.

Not grafted. Origin-only `frame-src`. It breaks SSO inside the iframe. Keep `frame-src *` on the embed page.

## Fixes on the base

- Do not prune icons on every save. Prune on uninstall. A later repair can collect orphans older than a day.
- `LinkId::fresh()` is test-only. The server does not mint ids.
- USAGE and `info.xml` state category `dashboard` and `max-version="35"`.
- Document the check-then-write window. `IAppConfig` has no CAS.

## Verification of this note

Judge scores (sum). 1: 37. 2: 29. 3: 29. 4: 36. Parent read all 12 files. Agreement on the base.
