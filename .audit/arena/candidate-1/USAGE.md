# Company Links Dashboard (`dashboard_links`)

A Dashboard tile with the links your company wants everyone to find: intranet, HR portal, ticketing, wiki. Admins maintain one ordered catalog. Users see the top entries on their Dashboard and the full list on one page. The app ships no dashboard JavaScript; the server's own Dashboard renders the tile from the widget's OCS payload.

Requirements: Nextcloud 33–35, PHP 8.2+. Optional: the official **External sites** app, for a one-shot import.

## For admins

### 1. Enable and make the tile visible

```sh
occ app:enable dashboard_links
# Only staff see the tile:
occ app:enable dashboard_links --groups staff
# Put the tile on the default layout for users who never customised their Dashboard:
occ config:app:set dashboard layout --value "recommendations,spreed,mail,calendar,dashboard_links"
```

Enabling the app does not edit anyone's Dashboard. Users who already customised their layout add the tile through **Customize**. The app never rewrites user layouts.

### 2. Configure links

**Administration settings → Company links.** One page, three bands:

| Band | Meaning | Where it shows |
|---|---|---|
| Featured | The handful everybody needs | Top of the tile |
| Normal | Everything else worth a click | Tile, after featured, until the 7 slots are used |
| Reference | Rarely needed, kept for completeness | Usually only on the **All links** page |

Order within a band is the order you arrange the rows. Drag a row into another band to change its importance. Every row has:

- **Title**: 1–120 characters.
- **URL**: must be `https://`. `http://`, `mailto:`, and URLs with embedded credentials are rejected on save.
- **Open mode**: *Redirect* sends the user straight to the URL. *Iframe* opens an in-Nextcloud page that embeds the site. Iframe only works if the site allows framing (`X-Frame-Options`, `frame-ancestors`); when in doubt use Redirect.
- **Icon**: optional. Upload a PNG, SVG, JPEG or WebP up to 256 KB. It is stored inside Nextcloud and served from your own domain, because the Dashboard blocks images from other origins. Without an icon the row shows the app icon.
- **Enabled**: off hides the link everywhere without deleting it.

**Save** writes the whole catalog at once. If another admin saved in the meantime you get a "catalog changed" notice with their version loaded; re-apply your edits and save again. Nothing is lost silently.

### 3. Import from External sites (optional)

When the External sites app is enabled, the settings page shows **Import from External sites**. Your browser reads the site list from External sites' own admin OCS endpoint (`GET /ocs/v2.php/apps/external/api/v1/sites`) and appends rows for review. Nothing is saved until you press **Save**.

| External sites field | Company links field |
|---|---|
| `name` | Title |
| `url` (https only; other schemes are skipped with a notice) | URL |
| `redirect: true` / `false` | Open mode Redirect / Iframe |
| `icon` | Re-uploaded as our icon when the browser can fetch it, else none |
| — | Importance *Normal*, Enabled on |
| `groups`, `lang`, `device` | Dropped; this app has no per-link filters |

Rows whose URL already exists in your catalog are skipped. The app never reads or writes External sites' configuration server-side and behaves normally when External sites is missing.

### 4. Admin API

Catalog: OCS, admin session, CSRF token on writes (`@nextcloud/axios` adds it).

```
GET /ocs/v2.php/apps/dashboard_links/api/v1/catalog
→ 200 { "revision": "3f9a0c1b2d4e", "links": [ …link… ] }

PUT /ocs/v2.php/apps/dashboard_links/api/v1/catalog
   { "revision": "3f9a0c1b2d4e", "links": [ …link… ] }
→ 200 { "revision": "…new…", "links": [ …link… ] }
→ 400 { "errors": [ { "index": 2, "field": "href", "message": "must be an https URL" } ] }
→ 412 { "revision": "…current…", "links": [ …link… ] }   # someone else saved first; body is the current catalog
```

A link on the wire:

```json
{
  "id": "6d4f0c4e-6a8c-4a0b-9d3a-2f0a1c3b5e7d",
  "title": "Intranet",
  "href": "https://intranet.example.com/",
  "importance": "featured",
  "openMode": "iframe",
  "icon": "9f3c2a1b0e4d5c6f.png",
  "enabled": true
}
```

Ids are lowercase UUIDv4 minted by the client (`crypto.randomUUID()` in the settings page; `uuidgen | tr A-F a-f` from a shell). Keep the id when editing a link; mint a new one when adding. The order of `links` is the display order within each band. The server always returns the list in display order (featured, then normal, then reference). Sending the exact catalog that is already stored succeeds with 200 regardless of `revision`, so a retried save is safe.

Icons: plain app routes, multipart upload.

```
POST /apps/dashboard_links/icons               (form field "icon"; admin; CSRF)
→ 200 { "icon": "9f3c2a1b0e4d5c6f.png",
        "url": "https://cloud.example.com/apps/dashboard_links/icons/9f3c2a1b0e4d5c6f.png" }
GET  /apps/dashboard_links/icons/{file}        (any logged-in user)
```

The file name is derived from the content, so uploading the same image twice returns the same name. Icons that no catalog entry references are deleted on the next catalog save.

### 5. Uninstall

`occ app:remove dashboard_links` deletes the catalog and the uploaded icons. Nothing else is touched.

## For users

The **Company links** tile lists up to 7 links: featured first, then normal, then reference, in the order the admin arranged. Click a link to open it. When there are more than 7, an **All links** button opens a page with every link grouped by band. Admins see a **Configure** button on an empty tile.

## For developers

Three call sites cover the whole app. The domain (`OCA\DashboardLinks\Links`) is pure PHP with no Nextcloud dependency; only `CatalogStore`, `Icons` and `LinkUrls` touch `OCP\*`.

### Read path: the widget

```php
// lib/Dashboard/LinksWidget.php
#[\Override]
public function getItemsV2(string $userId, ?string $since = null, int $limit = 7): WidgetItems {
	$page = $this->store->current()
		->visible()
		->after(LinkId::tryParse($since))
		->take($limit);

	return new WidgetItems(
		array_map($this->toItem(...), $page->links()),
		$this->l10n->t('No company links configured yet'),
	);
}

private function toItem(CompanyLink $link): WidgetItem {
	return new WidgetItem(
		$link->title,
		$link->href->host(),
		$this->urls->openUrl($link),   // redirect → the https href; iframe → our embed page
		$this->urls->iconUrl($link),   // uploaded icon or the app icon, always same-origin
		(string) $link->id,
	);
}
```

### Write path: the admin saves the catalog

```php
// lib/Controller/CatalogController.php
#[ApiRoute(verb: 'PUT', url: '/api/v1/catalog')]
public function replace(string $revision, array $links): DataResponse {
	try {
		$saved = $this->store->replace(Catalog::parse($links), $revision);
		return new DataResponse($saved);
	} catch (InvalidCatalog $e) {
		return new DataResponse(['errors' => $e->errors], Http::STATUS_BAD_REQUEST);
	} catch (StaleCatalog $e) {
		return new DataResponse($e->current, Http::STATUS_PRECONDITION_FAILED);
	}
}
```

### Open-mode resolution: the embed page

```php
// lib/Controller/PageController.php
#[FrontpageRoute(verb: 'GET', url: '/open/{id}')]
#[NoAdminRequired]
#[NoCSRFRequired]
public function open(string $id): Response {
	try {
		$link = $this->store->current()->visible()->find(LinkId::parse($id));
	} catch (InvalidLink) {
		$link = null;
	}
	if ($link === null) {
		return new NotFoundResponse();
	}
	return match ($link->openMode) {
		OpenMode::Redirect => new RedirectResponse((string) $link->href),
		OpenMode::Iframe => $this->frame($link),   // TemplateResponse whose CSP allows framing
	};
}
```

### The domain in a unit test

```php
$catalog = Catalog::of(
	new CompanyLink(LinkId::fresh(), 'Wiki', HttpsUrl::parse('https://wiki.example.com'),
		Importance::Reference, OpenMode::Redirect, null, true),
	new CompanyLink(LinkId::fresh(), 'Intranet', HttpsUrl::parse('https://intranet.example.com'),
		Importance::Featured, OpenMode::Iframe, null, true),
);

self::assertSame('Intranet', $catalog->links()[0]->title);   // featured sorts first, whatever the input order
self::assertSame($catalog->revision(), Catalog::parse(json_decode(json_encode($catalog), true)['links'])->revision());
```
