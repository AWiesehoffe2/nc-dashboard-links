# How findings for dashboard_links

Workspace `/Users/andrewiesehoff/nc-dashboard-links` is empty. This is a greenfield Nextcloud app. Explorers researched official Nextcloud docs and GitHub, not this repo.

Full explorer transcripts (read if a claim needs the source):

- marketplace: `.../subagents/2f6d465b-4c5a-4c0c-9245-33f57c4df07c.jsonl`
- external sites: `.../subagents/d02e14d9-3d3f-4ad0-8b6e-4b80d0908887.jsonl`
- dashboard API: `.../subagents/d959683e-d8b2-4eba-a8f2-a7950caf7239.jsonl`
- coding standards: `.../subagents/f466149b-2fa0-4cff-8544-dd8dd89b68f8.jsonl`

Base path: `/Users/andrewiesehoff/.cursor/projects/Users-andrewiesehoff-nc-dashboard-links/agent-transcripts/7366b86a-578f-4c61-a1b7-fdaa077c2188/subagents/`

## Question

How should a Nextcloud app expose a Dashboard tile for company external links, store admin-configured links, optionally reuse the official External sites (`external`) app, and ship to the Nextcloud Marketplace with official update and coding conventions?

## Reconciled facts

### Product shape

- Official `external` has no dashboard widget, no OCC, no events, no DB table.
- Sites live as JSON in `IAppConfig` keys `sites` and `max_site`.
- Safe reuse of `external`: capability `external.v1` and `GET /ocs/v2.php/apps/external/api/v1`. Never `OCA\External\*`. Never write `external`/`sites`.
- `info.xml` cannot declare another app as a dependency. Use `IAppManager::isEnabledForUser('external')`.
- Community `externalportal` already wraps External sites for Dashboard. Do not clone it. A new app owns its catalog and may import via OCS.
- `redirect` in External sites is a nav href / `_blank` flag. `SiteController` always iframes if you open `/apps/external/{id}/`.
- Dashboard tiles cannot embed iframes. Dashboard CSP has no `frame-src *`. External only relaxes CSP on its frame page.
- `WidgetItem` is title, subtitle, link, iconUrl, sinceId, overlayIconUrl. That is the official browser tile.

### Dashboard registration

- Register only in `IBootstrap::register()` via `$context->registerDashboardWidget(...)`.
- `info.xml` `<dashboard>` is not applied by current `AppManager::loadApp()`.
- `RegisterWidgetEvent` is gone since NC 29.
- Implement `IAPIWidgetV2` + `IAPIWidget` + `IIconWidget` + `IButtonWidget`. Leave `load()` empty.
- `IReloadableWidget` requires V2. A links list does not need a short reload.
- `IConditionalWidget` can hide the tile if there is nothing to show.
- `getId()` unique, prefix with app id. Pattern `[a-z][a-z0-9\-_]*`.
- `getOrder()` 10–100 for third-party. Use 20.
- Web UI fetches v2 widget-items without `limit` → 7 items. Extra links need `TYPE_MORE`.
- `TYPE_NEW` is not rendered on the web. `TYPE_SETUP` and `TYPE_MORE` are.
- Enabling the app does not add the tile to the user layout. It appears under Customize unless the admin sets `occ config:app:set dashboard layout`.
- Do not silently rewrite every user's layout. Store rule: respect user choices.
- Settings stay in `info.xml` `<settings>`. Widgets and capabilities stay in `IBootstrap`.
- Set `<namespace>DashboardLinks</namespace>` or `dashboard_links` becomes `OCA\Dashboard_links`.

### Versions (2026-09-16)

- Supported: NC 33 (33.0.9), 34 (34.0.4). 32 is EOL. 35.0.0 final scheduled today.
- PHP: 33/34 = 8.2–8.5. 35 = 8.3–8.5. App targeting 33–35: php min 8.2.
- Store rule: `max-version` may be latest + 1. Live store XSD **requires** `max-version`.
- Official `external` 10.0.0 is NC 35 only. Optional OCS reuse must tolerate missing `external`.

### Marketplace and files

- Validate `info.xml` against the **store** XSD (`nextcloud/appstore` info.xsd), not the narrower server copy. Official `lint-info-xml.yml` wget's the store XSD.
- Required: id, name, summary, description, semver, licence, author, category, bugs, nextcloud min+max.
- Licence: `AGPL-3.0-or-later`. Name must not contain Nextcloud.
- Category `dashboard` exists in the live XSD. Docs category list is stale.
- App id: `[a-z]+[a-z0-9_]*[a-z0-9]+`, max 32 chars. Chosen: `dashboard_links`.
- Two signatures: SHA-512 over `tar.gz` for the store, `occ integrity:sign-app` → `appinfo/signature.json` for the server.
- GitHub auto source zip fails (folder is `repo-version/`). Ship `dashboard_links.tar.gz`.
- `CHANGELOG.md` (Keep a Changelog) for store. `CHANGELOG.en.md` for user update notices since NC 29.
- Certificate CSR to nextcloud/app-certificate-requests. Pause before that. Human owns the key.
- Official `appstore-build-publish.yml` is gated to `nextcloud-releases`. Third-party copies the steps.
- REUSE.toml + SPDX headers. Official `external` already has a REUSE badge.
- `.editorconfig`: utf-8, LF, tabs width 4. YAML and package.json 2-space.

### Coding stack to copy

- Skeleton: `nextcloud/external` (Marketplace, admin Vue, Vite, vendor-bin, REUSE, krankerl).
- Widget: `apps/user_status` `UserStatusWidget` (IAPIWidget + V2 + IIconWidget, empty `load()`).
- Tests: `recommendations` `tests/Unit` + phpunit 10. `external` has almost no unit tests.
- Do not copy recommendations `<default_enable/>`.
- PHP: `nextcloud/coding-standard` 1.5, php-cs-fixer, psalm 6, phpunit 10, `nextcloud/ocp` require-dev, bamarni composer-bin-plugin.
- Frontend admin only: Vue 3.5, `@nextcloud/vue` 9, Vite 7, `@nextcloud/vite-config` 2, `@nextcloud/eslint-config` 9, node 24 / npm 11.
- No dashboard JS if V2. No Jest/Vitest convention in official small apps.
- CSRF on mutating OCS. `@nextcloud/axios` sends requesttoken. `#[NoCSRFRequired]` only on GETs that need it.
- `IAppConfig` types are sticky. Prefer `setValueArray` + `lazy: true` for the catalog. `external` used a JSON string. New code can use array.
- Zero production Composer deps if possible.
- Tabs, 80-col target, single quotes, `declare(strict_types=1)`, constructor promotion, `#[Override]`.
- Settings: own section only if the form is large. Else `additional`. A links editor is large enough for its own section.

### Known contradictions (resolve in the explanation)

- Store XSD vs server XSD vs developer-manual licence and category lists. Prefer live store XSD + official lint workflow.
- Dashboard docs still show Vue 2 and `IInitialStateService`. Server dashboard is Vue 3. Use `OCP\AppFramework\Services\IInitialState` if needed.
- `WidgetItems` getter docblocks in server source are swapped. Trust ctor + `jsonSerialize`.
- Official `external` still ships `<licence>agpl</licence>`. New NC 31+ apps should use SPDX `AGPL-3.0-or-later`.
- `getOrder` 0–9 reserved in docs. Shipped widgets still use 0–5.

## Planned domain (parent framing, not explorer-invented)

```
CompanyLink
  id
  title
  href          https after parse
  importance    featured | normal | reference
  openMode      iframe | redirect
  iconUrl?
  enabled
  sort
```

iframe → in-Nextcloud embed route (own page or `external.site.showPage` if imported).
redirect → external URL.
Featured sorts first. Web tile shows 7. More behind TYPE_MORE.
