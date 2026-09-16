<!--
  SPDX-FileCopyrightText: 2026 André Wiesehoff
  SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Company Links Dashboard

A Dashboard tile of admin-configured company links. App id `dashboard_links`, PHP namespace `OCA\DashboardLinks`, licence [AGPL-3.0-or-later](LICENSES/AGPL-3.0-or-later.txt). Requires Nextcloud 33–35 and PHP 8.2–8.5.

The tile is an `IAPIWidgetV2` widget. This app ships no dashboard JavaScript; Nextcloud’s own Dashboard renders the items from the widget payload.

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

## Configure three lanes

Open **Administration settings → Company links**. The catalog is three lanes. The lane is the importance; rows do not carry an `importance` key.

| Lane | Wire key | Meaning | Where it shows |
| --- | --- | --- | --- |
| Featured | `featured` | The handful everybody needs | Top of the tile |
| Company | `normal` | Everyday company links | After featured, until the seven tile slots are used |
| Reference | `reference` | Rarely needed, kept for completeness | Usually only on **All links** |

Order within a lane is the order of the rows. Save replaces the whole catalog (at most 200 links). If another administrator saved in the meantime, the API returns the current catalog; re-apply your edits and save again.

Each row has:

- **Title**: 1–120 characters.
- **URL**: `https` only. `http`, `mailto`, and URLs with embedded credentials are rejected.
- **Open mode**: iframe or redirect (see below).
- **Icon**: optional, stored in this app and served from your Nextcloud origin.
- **Enabled**: off hides the link without deleting it.

## iframe vs redirect

Both modes open through `/open/{id}` so a bookmark keeps working after a mode change.

- **iframe** — `/open/{id}` shows an in-Nextcloud embed page that frames the site.
- **redirect** — `/open/{id}` responds with 303 to the https URL.

The Dashboard tile itself never embeds a site. Remote sites may still refuse framing (`X-Frame-Options`, `frame-ancestors`); use redirect when in doubt.

## Uninstall

```sh
occ app:remove dashboard_links
```

Uninstall removes this app’s catalog and uploaded icons. It does not touch other apps or user Dashboard layouts.

## Admin API

The catalog lives in one lazy `IAppConfig` key, `catalog`. Administrators read and replace it over OCS (admin session; CSRF token on writes).

```
GET /ocs/v2.php/apps/dashboard_links/api/v1/catalog
PUT /ocs/v2.php/apps/dashboard_links/api/v1/catalog
```

The body is a three-lane envelope, not a flat `links` list:

```json
{
  "revision": "3f9a0c1b2d4e",
  "featured": [
    {
      "id": "6d4f0c4e-6a8c-4a0b-9d3a-2f0a1c3b5e7d",
      "title": "Intranet",
      "href": "https://intranet.example.com/",
      "openMode": "iframe",
      "icon": "9f3c2a1b0e4d5c6f.png",
      "enabled": true
    }
  ],
  "normal": [],
  "reference": []
}
```

A row on the wire is `{id, title, href, openMode, icon, enabled}`. Do not send `importance`; the lane supplies it. Ids are lowercase UUIDv4 minted by the client. Keep the id when editing a row; mint a new one when adding.

- `200` — saved catalog (same envelope). Saving the catalog that is already stored succeeds and writes nothing, even if `revision` is stale.
- `400` — `{ "errors": [ { "index": 2, "field": "href", "message": "…" } ] }`; every field error is collected.
- `412` — someone else saved first; the body is the current catalog in the same lane envelope.

`revision` is the first 12 hex characters of SHA-256 over the canonical link list. It is not stored as its own config key.

Optional import from the official External sites app is browser-only. When `externalSitesAvailable` is true, the settings page can GET External sites and append Company rows. Nothing is stored until Save. This app never reads or writes External sites’ configuration on the server.

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
