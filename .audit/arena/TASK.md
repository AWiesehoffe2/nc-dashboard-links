# Architect runner task

Read these first, in order:

1. `/Users/andrewiesehoff/.cursor/plugins/cache/cursor-public/pstack/be432a96ed36e48d05f44bf375864355f62263f9/skills/architect/SKILL.md`
2. `/Users/andrewiesehoff/.cursor/plugins/cache/cursor-public/pstack/be432a96ed36e48d05f44bf375864355f62263f9/skills/architect/references/rationale-template.md`
3. `/Users/andrewiesehoff/.cursor/plugins/cache/cursor-public/pstack/be432a96ed36e48d05f44bf375864355f62263f9/skills/architect/references/design-red-flags.md`
4. `/Users/andrewiesehoff/nc-dashboard-links/.audit/how-explanation.md`

You are one of several runners. Produce the best design your model can make. Do not hedge toward a middle. Whole-shape alternatives are the point.

## Artifact

A Nextcloud app `dashboard_links` (namespace `OCA\DashboardLinks`) that shows admin-configured company links on the Dashboard, with visible importance, iframe or redirect per link, optional one-shot import from official External sites via OCS only.

Write three files into your assigned output directory and nowhere else:

- `USAGE.md` first. README-style admin and widget usage plus two or three real PHP call sites. Usage is the spec.
- `sketch.php`. Types, enums, function signatures, class shapes. Bodies throw `not implemented` or are empty. Comments only for invariants the types cannot show.
- `rationale.md` shaped exactly like the rationale template, including Usage, Shape, Tradeoffs, Alternatives considered. Leave Synthesis decision as `pending`.

Do not implement the app. Do not write into the repo root. Do not touch other candidate directories.

## Locked constraints (from how)

- Register the widget only in `IBootstrap`. `load()` stays empty.
- Implement `IAPIWidget` + `IAPIWidgetV2` + `IIconWidget` + `IButtonWidget`.
- `getId()` = `dashboard_links`. `getOrder()` = 20.
- Web tile shows 7 items. Overflow is `TYPE_MORE` to a page we own.
- Do not rewrite every user's dashboard layout.
- Own catalog in `IAppConfig` (`setValueArray` + `lazy: true`). No `OCA\External\*`. No write to `external`/`sites`.
- Import from External sites is optional, admin-browser OCS, degradable if the app is missing.
- The tile never embeds an iframe. `openMode` iframe points at an in-Nextcloud embed page we own. `redirect` points at the https URL.
- Icons are same-origin (`img/` or AppData via our controller).
- PHP 8.2 minimum. No `public const string`. Use `public const APP_ID = 'dashboard_links'`.
- Nextcloud 33–35. Licence `AGPL-3.0-or-later`. Category `dashboard`.
- Store XSD requires `max-version`. Name must not contain Nextcloud.
- CSRF stays on mutating OCS.

## Domain the types must encode

A company link has title, https href, importance (`featured` | `normal` | `reference`), open mode (`iframe` | `redirect`), optional icon, enabled, sort. Illegal combinations must not compile or must fail at the parse boundary.

## Discipline

- Caller's usage first. Derive types from usage, not the reverse.
- Data structures first. Dominant access: admin save whole catalog, widget read enabled links sorted by importance then sort, resolve href from open mode, map to `WidgetItem`.
- Interface depth. Small public surface. Hide parse, sort, open-mode resolution, WidgetItem mapping.
- No transport or `IAppConfig` arrays on the public API. Parse at the boundary.
- One source of truth per invariant.
- Screen yourself against the design-red-flags file before you finish.
- Short call chains. If tracing a save-to-tile path needs more than three files, flatten.
