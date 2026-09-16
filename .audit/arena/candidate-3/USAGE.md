# Company Links Dashboard

A Marketplace app (`dashboard_links`, namespace `OCA\DashboardLinks`) that puts an admin-owned catalog of company links on the Nextcloud Dashboard. The tile is a projection of that catalog. Importance is visible. Opening a link is either an in-Nextcloud embed or a straight https navigation. There is no dashboard JavaScript of our own.

## What an admin does

1. Enable the app. The tile is **not** injected onto anyone’s layout. Users tick it under Customize, or an admin sets the instance default for users who have never customised:

   `occ config:app:set dashboard layout --value "recommendations,spreed,mail,calendar,dashboard_links"`

   Restricting the app with `occ app:enable dashboard_links --groups=…` hides the tile from everyone else. This app never writes `IUserConfig` dashboard layouts.

2. Open **Administration settings → Company Links**. The editor is three lanes — Featured, Company, Reference — and one Save. Dragging a row between lanes *is* changing importance. There is no importance dropdown on the row, so a link cannot be “featured” and sit in the reference lane.

3. Each row is a title, an `https` URL, an open mode (`iframe` or `redirect`), an optional same-origin icon, enabled, and a sort position inside its lane. Save replaces the whole catalog in one write. There is no per-link create/update/delete API.

4. Icons are uploaded to this app (or picked from `img/`). A paste of `https://intranet.example.com/favicon.ico` is rejected. The Dashboard page will not display a foreign `img-src`.

5. **Import from External sites** is optional and one-shot. The admin Vue calls External’s own admin OCS (`GET /ocs/v2.php/apps/external/api/v1/sites`). If that app is missing or the call fails, the button degrades to an explanation — our PHP never `use`s `OCA\External\*` and never reads `external/sites`. On success the browser POSTs the raw `sites` array to us. We parse, drop illegal rows (non-https, empty name), and merge into the **Company** lane by normalised href. A re-import does not duplicate and does not demote a link the admin already moved to Featured or Reference. Icons from External are dropped; they are not ours.

6. Uninstall deletes our appconfig. We do not touch External’s data.

## What a user sees

- The web tile asks for **7** items and we honour that limit. Each row’s subtitle is the lane label (Featured / Company / Reference), so importance is visible without opening the overflow page.
- The item link is never an iframe and never a `javascript:` URL. `redirect` items navigate to the https href. `iframe` items navigate to **our** embed page, which is the only place CSP is relaxed (`frame-src *`). The remote site can still refuse to be framed; the admin manual should prefer `redirect` for those.
- If more than seven enabled links exist, a **More links** button (`TYPE_MORE`) goes to our full list page (same resolved targets, grouped by lane). Admins also get **Configure links** (`TYPE_SETUP`) to the settings section. We do not emit `TYPE_NEW`.
- Header click on the tile is the same list page. Empty catalog: “No links configured yet.”

## What this app will not do

- Register the widget from `info.xml` or `RegisterWidgetEvent`. `IBootstrap::register()` is the only registration; `LinksWidget::load()` stays empty.
- Embed a site inside the Dashboard tile.
- Depend on External at the PHP level, write External’s keys, or keep a live mirror of External’s sites.
- Accept `http`, `mailto`, or any non-https href into the catalog.
- Serve a remote icon URL.
- Poll (`IReloadableWidget`) or round the icons (`IOptionWidget`).
- Filter links by user group, language, or device. Group visibility is “is the app enabled for this user.”

## Shipping constraints the code must honour

- PHP 8.2+, Nextcloud 33–35, licence `AGPL-3.0-or-later`, category `dashboard`.
- `info.xml` name must not contain “Nextcloud”. Store XSD requires `max-version`. `<namespace>DashboardLinks</namespace>`.
- `public const APP_ID = 'dashboard_links';` — no typed class constants (`public const string` is 8.3).
- CSRF stays on every mutating OCS method. `@nextcloud/axios` already sends `requesttoken`.

## Routes the UI actually hits

| Who | Method | Path | CSRF |
| --- | --- | --- | --- |
| Admin Vue | GET | `/ocs/v2.php/apps/dashboard_links/api/v1/catalog` | n/a |
| Admin Vue | PUT | `/ocs/v2.php/apps/dashboard_links/api/v1/catalog` | on |
| Admin Vue | POST | `/ocs/v2.php/apps/dashboard_links/api/v1/catalog/import` body `{ "sites": [ /* External OCS rows */ ] }` | on |
| Admin Vue | POST | `/ocs/v2.php/apps/dashboard_links/api/v1/icons` (multipart) | on |
| Dashboard / users | GET | `/apps/dashboard_links/` (full list) | n/a |
| Dashboard / users | GET | `/apps/dashboard_links/embed/{id}` (iframe shell) | n/a |
| Dashboard / users | GET | `/apps/dashboard_links/icon/{fileId}` (AppData) | n/a |

Admin payload (Save and GET) is lanes, not a flat list with an `importance` field:

```json
{
  "featured": [
    {
      "id": 1,
      "title": "Wiki",
      "href": "https://wiki.example.com",
      "openMode": "iframe",
      "icon": { "kind": "stored", "id": "ab12" },
      "enabled": true,
      "sort": 0
    }
  ],
  "normal": [],
  "reference": []
}
```

New rows omit `id`. The server issues a never-reused integer. Omitted lanes are empty. A flat `{ "links": [ { "importance": "featured", … } ] }` is a 400, and so is an `importance` key on a row — the lane is the only source.

## PHP call sites

These three are the spec. The sketch must be callable exactly like this.

### 1. Bootstrap — register the widget, nothing else

```php
public function register(IRegistrationContext $context): void {
	$context->registerDashboardWidget(LinksWidget::class);
}

public function boot(IBootContext $context): void {
}
```

`register()` does not inspect `IAppManager`, config, or External. `load()` on the widget is empty. Settings stay in `info.xml`.

### 2. Admin Save and one-shot import — whole catalog, parse at the door

```php
public function show(): DataResponse {
	return new DataResponse($this->links->get()->toAdminArray());
}

public function replace(): DataResponse {
	$saved = $this->links->saveFromAdmin($this->request->getParams());
	return new DataResponse($saved->toAdminArray());
}

public function import(): DataResponse {
	$sites = $this->request->getParam('sites');
	if (!is_array($sites)) {
		throw new OCSBadRequestException('sites must be an array');
	}
	$result = $this->links->importExternal($sites);
	return new DataResponse([
		'catalog' => $result->catalog->toAdminArray(),
		'skipped' => $result->batch->skipped(),
	]);
}
```

The controller does not sort, assign ids, resolve open mode, or know `IAppConfig` array keys. `saveFromAdmin` and `importExternal` are the write surface. Replacing with the same payload, or importing the same External rows twice, is a no-op besides rewriting the same blob.

### 3. Tile and embed — read the projection, resolve the target once

```php
public function getItemsV2(?string $userId, ?string $since = null, int $limit = 7): WidgetItems {
	$items = [];
	foreach ($this->links->get()->sliceVisible($since, $limit) as $row) {
		$items[] = $this->toItem($row);
	}
	return new WidgetItems($items, $this->l10n->t('No links configured yet'));
}

public function getWidgetButtons(string $userId): array {
	$buttons = [];
	if (count($this->links->get()->visible()) > self::TILE_SIZE) {
		$buttons[] = new WidgetButton(
			WidgetButton::TYPE_MORE,
			$this->routes->listPage(),
			$this->l10n->t('More links'),
		);
	}
	if ($this->groups->isAdmin($userId)) {
		$buttons[] = new WidgetButton(
			WidgetButton::TYPE_SETUP,
			$this->routes->settings(),
			$this->l10n->t('Configure links'),
		);
	}
	return $buttons;
}

private function toItem(DisplayedLink $row): WidgetItem {
	return new WidgetItem(
		$row->link->title()->value(),
		match ($row->importance) {
			Importance::Featured => $this->l10n->t('Featured'),
			Importance::Normal => $this->l10n->t('Company'),
			Importance::Reference => $this->l10n->t('Reference'),
		},
		LinkTarget::resolve($row->link, $this->routes)->href(),
		$this->routes->icon($row->link->icon()),
		(string) $row->link->id()->value(),
	);
}

public function embed(int $id): TemplateResponse|RedirectResponse {
	$link = $this->links->get()->find(LinkId::fromInt($id));
	if ($link === null || !$link->enabled()) {
		throw new NotFoundException();
	}
	return match ($link->openMode()) {
		OpenMode::Redirect => new RedirectResponse($link->href()->value()),
		OpenMode::Iframe => $this->frame($link),
	};
}
```

`getItems()` (API v1) uses the same `toItem()` over `sliceVisible`. `getId()` is `dashboard_links`. `getOrder()` is `20`. `LinkTarget::resolve` is the only place an `iframe` link becomes a Nextcloud URL; the tile and the list page both call it. `embed()` iframes only `OpenMode::Iframe`. A `redirect` id opened on the embed route 302s to https, so a crafted `/embed/{id}` cannot frame a redirect-mode site.

`since` is a link id in visible order: return the items after that id. If the id is unknown, return the first page. Honour `$limit`; the web frontend omits it and the server passes 7.
