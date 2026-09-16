<!--
  SPDX-FileCopyrightText: 2026 André Wiesehoff
  SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Company Links Dashboard

A Dashboard tile of admin-configured company links. App id `dashboard_links`, PHP namespace `OCA\DashboardLinks`, licence [AGPL-3.0-or-later](LICENSES/AGPL-3.0-or-later.txt). Requires Nextcloud 33–35 and PHP 8.2–8.5.

The tile is an `IAPIWidgetV2` widget. This app ships no dashboard JavaScript; Nextcloud’s own Dashboard renders the items from the widget payload.

## Built entirely with AI

This repository is a **reference**: the app was designed and implemented completely with AI in Cursor. Product direction is André Wiesehoff’s; architecture, code, tests, admin Vue, CI, and documentation were written by the agent against official Nextcloud standards (Dashboard `IAPIWidgetV2`, store `info.xml` XSD, coding standard, REUSE).

Use it to see what a first Marketplace-oriented Nextcloud app can look like when the whole tree is AI-built, not as a claim that no human review is needed.

## Enable the app

From the server:

```sh
occ app:enable dashboard_links
```

To enable it only for a group:

```sh
occ app:enable dashboard_links --groups staff
```

You can also enable it under **Apps** in the web interface.

## Put the tile on the default Dashboard

Enabling the app does not edit anyone’s Dashboard. Users who already customised their layout add **Company links** under **Customize**.

For users who have never customised their Dashboard, an administrator can include the widget on the instance default layout (this is the Dashboard app’s layout setting, not a rewrite of stored user layouts):

```sh
occ config:app:set dashboard layout --value "recommendations,spreed,mail,calendar,dashboard_links"
```

This app never rewrites user layouts.

## Configure the catalog

Open **Administration settings → Company links**. Links live in one list. Leave them uncategorized unless you need a named group. Save replaces the whole catalog (at most 200 links and 40 categories). If another administrator saved in the meantime, the API returns the current catalog. Re-apply your edits and save again. A successful save shows a confirmation on the page.

Each row has:

- **Title**. 1–120 characters.
- **URL**. `https` only. `http`, `mailto`, and URLs with embedded credentials are rejected.
- **Open mode**. iframe or redirect (see below).
- **Icon**. A Nextcloud core icon or an upload stored in this app and served from your Nextcloud origin.
- **Category**. Optional. Missing or empty means the default list.
- **Enabled**. Off hides the link without deleting it.

## iframe vs redirect

Both modes open through `/open/{id}` so a bookmark keeps working after a mode change.

- **iframe** — `/open/{id}` shows an in-Nextcloud embed page that frames the site.
- **redirect** — `/open/{id}` responds with 303 to the https URL.

The Dashboard tile itself never embeds a site. Remote sites may still refuse framing (`X-Frame-Options`, `frame-ancestors`); use redirect when in doubt.

## Privacy

This app does not store user accounts and does not send data to the author. The catalog holds titles, https URLs, open mode, and optional icons that the administrator entered.

Opening a link (iframe or redirect) sends the user's browser to that https address. The destination can see the user's IP address, browser details, and often the Nextcloud address as referrer. Open responses send `Referrer-Policy: no-referrer` so the Nextcloud URL is less likely to leak. The destination still sees the request itself. Administrators must list those destinations in the instance privacy notice.

Uninstall deletes the catalog and uploaded icons.

## Uninstall

```sh
occ app:remove dashboard_links
```

Uninstall removes this app’s catalog and uploaded icons. It does not touch other apps or user Dashboard layouts.

## Admin API

The catalog lives in one lazy `IAppConfig` key, `catalog`. Administrators read and replace it over OCS (admin session). GET and PUT both require a CSRF token in the browser. Non-browser OCS clients can pass `OCS-APIREQUEST: true` or a Bearer token instead. PUT and icon POST also require a recent password confirmation.

```
GET /ocs/v2.php/apps/dashboard_links/api/v1/catalog
PUT /ocs/v2.php/apps/dashboard_links/api/v1/catalog
```

The body is `{revision, categories, links}`:

```json
{
  "revision": "3f9a0c1b2d4e",
  "categories": [],
  "links": [
    {
      "id": "6d4f0c4e-6a8c-4a0b-9d3a-2f0a1c3b5e7d",
      "title": "Intranet",
      "href": "https://intranet.example.com/",
      "openMode": "iframe",
      "icon": "core:places/link.svg",
      "categoryId": null,
      "enabled": true
    }
  ]
}
```

A category is `{id, title}`. A link is `{id, title, href, openMode, icon, categoryId, enabled}`. `categoryId` is null for the default list. `icon` is null, a stored file name, or a `core:` Nextcloud icon. Do not send `importance` or the old `featured` / `normal` / `reference` keys. Ids are lowercase UUIDv4 minted by the client. Keep the id when editing a row. Mint a new one when adding.

- `200` — saved catalog (same envelope). Saving the catalog that is already stored succeeds and writes nothing, even if `revision` is stale.
- `400` — `{ "errors": [ { "index": 2, "field": "href", "message": "…" } ] }`. Every field error is collected.
- `412` — someone else saved first. The body is the current catalog.

`revision` is the first 12 hex characters of SHA-256 over the canonical categories and links. It is not stored as its own config key. A schema 1 catalog (flat `links` with `importance`) is read as the default list. The next save writes schema 2.

Optional import from the official External sites app is browser-only. When `externalSitesAvailable` is true, the settings page can GET External sites and append rows to the default list. Nothing is stored until Save. This app never reads or writes External sites’ configuration on the server.

## For developers

```sh
composer install
composer test
composer cs:check
npm ci
npm run lint
npm run build
```

`composer cs:fix` applies the Nextcloud coding standard. There is no dashboard JavaScript; `npm run build` emits the admin settings bundle.

## App store

See [docs/publish.md](docs/publish.md) for the tarball, certificate request, and release signatures. `make appstore` writes `build/appstore/dashboard_links.tar.gz`. The store certificate is not in this repository.
