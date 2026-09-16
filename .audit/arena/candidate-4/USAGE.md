<!--
  SPDX-FileCopyrightText: 2026 the dashboard_links authors
  SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Company Links Dashboard (`dashboard_links`)

A Dashboard tile that shows the links your organisation wants people to use. An
administrator maintains one catalog for the whole instance; every user sees the
same links, ranked by importance. The app ships no dashboard JavaScript — the
server's own Dashboard renders the tile from our OCS payload.

- App id / widget id: `dashboard_links`
- Namespace: `OCA\DashboardLinks`
- Nextcloud 33–35, PHP 8.2+
- Licence `AGPL-3.0-or-later`, store category `dashboard`

## Install and make the tile visible

```bash
occ app:enable dashboard_links

# Enabling an app does NOT add its tile to anyone's dashboard. Either each user
# ticks it in Customise, or you set the instance default, which applies to users
# who never customised their layout:
occ config:app:set dashboard layout \
  --value "recommendations,spreed,mail,calendar,dashboard_links"

# Optional: restrict who sees the tile at all. Manager::loadLazyPanels() checks
# isEnabledForUser(), so group-scoping the app group-scopes the widget.
occ app:enable dashboard_links --groups=staff
```

The app deliberately never writes other users' dashboard layouts.

## Maintain the catalog

**Settings → Administration → Company links.** One drag-and-drop list. Each row
has a title, an `https://` address, an importance, an open mode, an enabled
toggle, and an optional logo.

**Importance** is the only ranking control, and it is visible to users three
ways at once, with no custom tile JavaScript:

| Importance  | Position on the tile | Shown as |
| ----------- | -------------------- | -------- |
| `featured`  | first                | badge overlay on the logo + "Featured" subtitle |
| `normal`    | after featured       | "Company link" subtitle |
| `reference` | last                 | "Reference" subtitle |

**Row order is the sort.** There is no sort number to type. Within one
importance band the links appear in the order you dragged them; the stored
`sort` value is derived from the row position when you save.

**Open mode** decides where the title click goes:

- `redirect` — straight to the `https://` address.
- `iframe` — to `/apps/dashboard_links/open/{id}`, a page this app owns, which
  embeds the site and relaxes `frame-src` to *that one site's origin*. The tile
  itself never embeds anything; a Dashboard tile cannot, and this app does not
  try.

An `iframe` link still fails if the remote site sends `X-Frame-Options` or a
`frame-ancestors` policy that excludes you. Prefer `redirect` unless you know
the site allows framing.

**Logos** are same-origin by construction. You can pick one of the icons bundled
under `img/links/`, or upload a PNG/JPEG/WebP (≤64 KiB) which is stored in
AppData and served by this app's own `IconController`. Arbitrary remote favicon
URLs are not accepted: the Dashboard page's `img-src` is `'self' data: blob:`,
so they would silently render blank.

Save the catalog first, then upload logos — an upload needs the link's id.

**Saving is guarded.** Every read of the catalog carries a `revision`, which is
a content hash of the catalog itself. A save presents the revision it was
editing. If another administrator saved in the meantime you get `412` with the
current catalog attached, and the settings UI offers to reload rather than
silently discarding their work.

## Import from External sites (optional)

If the official **External sites** app is installed, the settings page shows
*Import from External sites*. The **administrator's browser** reads
`GET /ocs/v2.php/apps/external/api/v1/sites` and posts the records to us. If
`external` is absent the button simply does not appear.

This app has no PHP dependency on `external`: no `OCA\External\*` class is
imported, `external`'s own config is never read or written, and imported links
never point at `external`'s page — they get our embed page like any other link.
The external site id is kept only as provenance, which makes re-running the
import an upsert instead of a duplicate.

Imported links arrive as `reference`, enabled, without a logo. Sites whose URL
is not `https://` are skipped and listed back to you by name.

## Uninstall

`occ app:remove dashboard_links` runs a repair step that deletes every
`dashboard_links` app-config key and the AppData icon folder. Nothing is left
behind.

---

# Call sites

## 1. Registering the widget — `lib/AppInfo/Application.php`

The whole bootstrap. The widget is registered only here, and nothing is resolved
or queried during `register()`.

```php
final class Application extends App implements IBootstrap {
	public const APP_ID = 'dashboard_links';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerDashboardWidget(LinksWidget::class);
	}

	public function boot(IBootContext $context): void {
		// Nothing. Routes are attributes, settings are declared in info.xml.
	}
}
```

## 2. Serving the tile — `lib/Dashboard/LinksWidget.php`

The browser calls `/ocs/v2.php/apps/dashboard/api/v2/widget-items` with no
`limit`, so the server passes `7`. One private mapper serves both API versions.

```php
public function getItemsV2(string $userId, ?string $since = null, int $limit = 7): WidgetItems {
	return new WidgetItems(
		$this->items($since, $limit),
		$this->l10n->t('No company links have been configured yet'),
	);
}

public function getItems(string $userId, ?string $since = null, int $limit = 7): array {
	return $this->items($since, $limit);
}

/** @return list<WidgetItem> */
private function items(?string $since, int $limit): array {
	$page = $this->store->load()
		->visible()
		->page($limit, LinkId::tryFromSince($since));

	return array_map(
		static fn (LinkView $view): WidgetItem => new WidgetItem(
			$view->title,
			$view->subtitle,
			$view->href,
			$view->iconUrl,
			(string)$view->id->value,
			$view->overlayIconUrl,
		),
		$this->presenter->views($page),
	);
}

/** @return list<WidgetButton> */
public function getWidgetButtons(string $userId): array {
	$buttons = [];

	if ($this->store->load()->visible()->exceeds(self::TILE_LIMIT)) {
		$buttons[] = new WidgetButton(
			WidgetButton::TYPE_MORE,
			$this->urlGenerator->linkToRouteAbsolute('dashboard_links.pages.index'),
			$this->l10n->t('All company links'),
		);
	}

	if ($this->groupManager->isAdmin($userId)) {
		$buttons[] = new WidgetButton(
			WidgetButton::TYPE_SETUP,
			$this->urlGenerator->linkToRouteAbsolute(
				'settings.AdminSettings.index',
				['section' => 'dashboard_links'],
			),
			$this->l10n->t('Manage links'),
		);
	}

	return $buttons;
}
```

Note what is *not* here: no filtering of disabled links, no importance sort, no
open-mode branch, no icon URL building. `visible()` can only yield enabled
links, `page()` owns `since`/`limit`, and `LinkPresenter` owns every URL and
label. The widget maps our view type onto the framework's and stops.

## 3. Saving the catalog — `lib/Controller/CatalogApiController.php`

The one mutating admin endpoint. Untrusted arrays die in
`SubmittedCatalog::fromOcs()`; everything after it is typed.

```php
#[ApiRoute(verb: 'PUT', url: '/api/v1/catalog')]
#[PasswordConfirmationRequired]
public function save(array $links, string $revision): DataResponse {
	try {
		$catalog = $this->store->save(SubmittedCatalog::fromOcs($links, $revision));
	} catch (InvalidCatalog $e) {
		return new DataResponse(['errors' => $e->errors], Http::STATUS_BAD_REQUEST);
	} catch (StaleCatalog $e) {
		// Another admin saved first. Hand back what is there now so the UI can
		// show the difference instead of clobbering their rows.
		return new DataResponse(
			$this->rows($e->current),
			Http::STATUS_PRECONDITION_FAILED,
		);
	}

	return new DataResponse($this->rows($catalog));
}
```

There is no `#[NoAdminRequired]` on this controller, so every method is
admin-only, and CSRF stays on for `PUT`/`POST`/`DELETE` —
`@nextcloud/axios` sends `requesttoken` for us.

## 4. The embed page — `lib/Controller/PagesController.php`

The only place in the app that relaxes a CSP, and the only place that can.

```php
#[NoAdminRequired]
#[NoCSRFRequired]
#[FrontpageRoute(verb: 'GET', url: '/open/{id}')]
public function embed(int $id): Response {
	try {
		$link = $this->store->load()->embeddable(LinkId::parse($id));
	} catch (LinkNotEmbeddable) {
		// Disabled, unknown, or a redirect-mode link: not framable by design.
		return new NotFoundResponse();
	}

	$response = new TemplateResponse(Application::APP_ID, 'frame', [
		'title' => $link->title->value,
		'src' => $link->href->value,
	]);

	$policy = new ContentSecurityPolicy();
	$policy->addAllowedFrameDomain($link->href->origin());
	$response->setContentSecurityPolicy($policy);

	return $response;
}
```

`HttpsUrl::origin()` is the single source for that CSP value, so the allowance
can never drift from the URL actually framed. Unlike `external`, we never widen
`frame-src` to `*`.

## OCS reference

All admin-only. Responses carry `revision`; mutating verbs keep CSRF.

| Verb     | Route                              | Body / result |
| -------- | ---------------------------------- | ------------- |
| `GET`    | `/api/v1/catalog`                  | `{revision, links: [...]}` |
| `PUT`    | `/api/v1/catalog`                  | `{revision, links: [...]}` → `200` \| `400` field errors \| `412` current catalog |
| `POST`   | `/api/v1/catalog/import`           | `{sites: [...]}` → `{revision, links, added, updated, skipped}` |
| `PUT`    | `/api/v1/links/{id}/icon`          | multipart upload → `{revision, links}` |
| `DELETE` | `/api/v1/links/{id}/icon`          | → `{revision, links}` |

`PUT /api/v1/catalog` never touches icons. Logos are managed by the icon
endpoints alone, so the settings UI does not have to round-trip an icon
reference it cannot construct.

## Admin browser: the import call

```ts
const available = getCapabilities()?.external?.v1?.includes('sites') ?? false
if (!available) {
	return // No button. Nothing to degrade.
}

const { data } = await axios.get(generateOcsUrl('apps/external/api/v1/sites'))
const result = await axios.post(
	generateOcsUrl('apps/dashboard_links/api/v1/catalog/import'),
	{ sites: data.ocs.data },
)
// result.data.ocs.data => { revision, links, added, updated, skipped }
```

Run it twice and `added` is `0` the second time: the import upserts on the
external site id.
