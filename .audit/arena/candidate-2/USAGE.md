# Company Links Dashboard

`dashboard_links` gives administrators one ordered company-link catalog and gives
users a seven-item Dashboard tile. Featured links appear first, followed by
normal and reference links; each group follows the administrator's sort order.

The app supports Nextcloud 33–35 and PHP 8.2+. Its app id is
`dashboard_links`, namespace is `OCA\DashboardLinks`, licence is
`AGPL-3.0-or-later`, and App Store category is `dashboard`. The app name in
`info.xml` must not contain “Nextcloud”, and the store-XSD dependency must
include `max-version="35"`.

## Administrator usage

Open **Administration settings → Company links**. Add, reorder, disable, or
remove links, then save the whole catalog. Every URL must use HTTPS.

Each link has:

- a non-empty title;
- an HTTPS destination;
- importance: `featured`, `normal`, or `reference`;
- open mode: `iframe` or `redirect`;
- an optional app-managed, same-origin icon;
- enabled/disabled state;
- a non-negative sort position.

Saving uses the revision returned with the page. If another administrator has
saved in the meantime, the API returns a conflict and the editor reloads before
the administrator retries. New links omit `id`; existing links retain their
opaque id. A complete save is one atomic replacement, including id allocation.

The editor may offer **Import from External sites** when the official
`external` app's admin OCS endpoint is available. The administrator's browser
reads that endpoint and submits selected records to this app's normal save
endpoint. Import is a one-shot copy: there is no PHP dependency, live sync, or
read/write access to `external` appconfig. Unsupported or remote icons are
omitted. If `external` is missing, the editor remains fully usable.

Mutating OCS requests keep Nextcloud CSRF protection. The frontend uses
`@nextcloud/axios`, which supplies the request token.

## User usage

Enable **Company links** in Dashboard → Customize. Enabling the app does not
rewrite anyone's saved Dashboard layout. An administrator may set the normal
Dashboard default layout for users who have not customized it.

The tile renders at most seven enabled links. It never embeds an iframe:

- `redirect` items navigate directly to their HTTPS URL;
- `iframe` items navigate to this app's authenticated embed route, which
  supplies the iframe-specific CSP.

When more than seven links are enabled, the tile has a **More** button leading
to this app's complete links page. The widget header leads to the same page.
Icons are served only from `img/` or an app-data controller on this origin.
Remote sites may still refuse framing through `X-Frame-Options` or
`frame-ancestors`; use redirect mode in that case.

## PHP call sites

Widget registration happens only during bootstrap registration. `boot()` is
empty, and the widget's `load()` method is also empty.

```php
final class Application extends App implements IBootstrap {
	public const APP_ID = 'dashboard_links';

	public function register(IRegistrationContext $context): void {
		$context->registerDashboardWidget(LinksWidget::class);
	}

	public function boot(IBootContext $context): void {
	}
}
```

The widget asks one deep interface for a presentation-ready slice. Sorting,
open-mode URL resolution, icon URL resolution, storage parsing, and
`WidgetItem` mapping stay behind that interface.

```php
public function getItemsV2(
	string $userId,
	?string $since = null,
	int $limit = 7,
): WidgetItems {
	$slice = $this->feed->slice(
		WidgetQuery::fromApi($since, min($limit, self::WEB_LIMIT)),
	);

	return new WidgetItems(
		$slice->items,
		$this->l10n->t('No company links configured yet'),
	);
}

public function getWidgetButtons(string $userId): array {
	$slice = $this->feed->slice(WidgetQuery::first(self::WEB_LIMIT));

	return $slice->hasMore
		? [new WidgetButton(
			WidgetButton::TYPE_MORE,
			$this->urlGenerator->linkToRouteAbsolute(
				'dashboard_links.page.index',
			),
			$this->l10n->t('More'),
		)]
		: [];
}
```

The admin controller parses the OCS payload at its boundary and passes only
typed domain input to the catalog. The catalog atomically checks the revision,
allocates ids, validates all records, and writes one lazy `IAppConfig` array.

```php
public function replace(): DataResponse {
	$replacement = $this->requestParser->replacement($this->request);
	$snapshot = $this->catalog->replace($replacement);

	return new DataResponse($this->responseMapper->adminSnapshot($snapshot));
}
```

The embed controller cannot be used to frame a redirect-only or disabled link.
That invariant is checked by the navigation boundary before CSP is relaxed.

```php
public function embed(string $id): TemplateResponse {
	$source = $this->navigation->iframeSource(LinkId::parse($id));
	$response = new TemplateResponse(
		Application::APP_ID,
		'frame',
		['src' => $source->value],
	);
	$response->getContentSecurityPolicy()->addAllowedFrameDomain(
		$source->origin(),
	);

	return $response;
}
```
