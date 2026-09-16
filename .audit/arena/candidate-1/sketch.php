<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2026 dashboard_links contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
//
// Design sketch for the dashboard_links app. One file, bracketed namespaces;
// the `// file:` comment above each class is where it lives in the real tree.
// Bodies throw `not implemented`. Comments state only what the types cannot.
//
// Module map (save-to-tile path is three files: CatalogController → CatalogStore → LinksWidget):
//
//   lib/AppInfo/Application.php           IBootstrap. Registers the widget. Nothing else.
//   lib/Links/                            The deep module. Pure domain + two adapters.
//     Importance.php OpenMode.php         Enums.
//     LinkId.php HttpsUrl.php Icon.php    Value types, valid by construction.
//     CompanyLink.php                     One link. Canonical JSON in/out.
//     Catalog.php                         Ordered, canonical, immutable set of links.
//     InvalidLink.php InvalidCatalog.php StaleCatalog.php
//     CatalogStore.php                    IAppConfig adapter. current() / replace().
//     Icons.php                           IAppData adapter for uploaded icons.
//     LinkUrls.php                        Every route name in the app, in one place.
//   lib/Dashboard/LinksWidget.php         The tile.
//   lib/Controller/CatalogController.php  OCS: GET/PUT the whole catalog.
//   lib/Controller/IconController.php     Upload + serve icons.
//   lib/Controller/PageController.php     All-links page + open/{id}.
//   lib/Settings/Admin.php Section.php    Settings form (declared in info.xml).
//   lib/Migration/Uninstall.php           Repair step: delete config + icons.
//   templates/settings.php links.php frame.php
//   src/admin.ts AdminSettings.vue        The only JavaScript. Reads initial state, PUTs the catalog.
//   tests/Unit/Links/*                    Pure tests, no server checkout needed.

namespace OCA\DashboardLinks\AppInfo {

	use OCA\DashboardLinks\Dashboard\LinksWidget;
	use OCP\AppFramework\App;
	use OCP\AppFramework\Bootstrap\IBootContext;
	use OCP\AppFramework\Bootstrap\IBootstrap;
	use OCP\AppFramework\Bootstrap\IRegistrationContext;

	// file: lib/AppInfo/Application.php
	final class Application extends App implements IBootstrap {
		public const APP_ID = 'dashboard_links';

		public function __construct(array $urlParams = []) {
			parent::__construct(self::APP_ID, $urlParams);
		}

		/** Lazy registrations only. No config, no IAppManager, no I/O in here. */
		#[\Override]
		public function register(IRegistrationContext $context): void {
			$context->registerDashboardWidget(LinksWidget::class);
		}

		#[\Override]
		public function boot(IBootContext $context): void {
		}
	}
}

namespace OCA\DashboardLinks\Links {

	use OCP\Files\AppData\IAppDataFactory;
	use OCP\Files\SimpleFS\ISimpleFile;
	use OCP\IAppConfig;
	use OCP\IURLGenerator;
	use Psr\Log\LoggerInterface;

	// file: lib/Links/Importance.php
	enum Importance: string {
		case Featured = 'featured';
		case Normal = 'normal';
		case Reference = 'reference';

		/** Display order. Lower ranks first. The only place this order is defined. */
		public function rank(): int {
			throw new \LogicException('not implemented');
		}
	}

	// file: lib/Links/OpenMode.php
	enum OpenMode: string {
		case Iframe = 'iframe';
		case Redirect = 'redirect';
	}

	// file: lib/Links/LinkId.php
	/** Lowercase UUIDv4. Minted by the client; the server never assigns ids. */
	final readonly class LinkId implements \Stringable {
		private function __construct(public string $value) {
		}

		/** @throws InvalidLink field "id" */
		public static function parse(string $raw): self {
			throw new \LogicException('not implemented');
		}

		public static function tryParse(?string $raw): ?self {
			throw new \LogicException('not implemented');
		}

		/** For tests and server-side seeding only. */
		public static function fresh(): self {
			throw new \LogicException('not implemented');
		}

		public function equals(self $other): bool {
			throw new \LogicException('not implemented');
		}

		#[\Override]
		public function __toString(): string {
			throw new \LogicException('not implemented');
		}
	}

	// file: lib/Links/HttpsUrl.php
	/** Absolute URL, scheme exactly https, host present, no userinfo, at most 2048 chars. Not normalised beyond trim. */
	final readonly class HttpsUrl implements \Stringable {
		public const MAX_LENGTH = 2048;

		private function __construct(private string $value) {
		}

		/** @throws InvalidLink field "href" */
		public static function parse(string $raw): self {
			throw new \LogicException('not implemented');
		}

		/** Host only, for the tile subtitle. */
		public function host(): string {
			throw new \LogicException('not implemented');
		}

		#[\Override]
		public function __toString(): string {
			throw new \LogicException('not implemented');
		}
	}

	// file: lib/Links/Icon.php
	/**
	 * Reference to an uploaded icon in this app's AppData folder.
	 * File name is `<16 hex of sha256(content)>.<png|svg|jpg|webp>`, so it is safe
	 * as a route parameter and identical uploads collapse to one file.
	 * Existence on disk is not part of this type; CatalogStore::replace() checks it.
	 */
	final readonly class Icon implements \Stringable {
		private function __construct(public string $file) {
		}

		/** @throws InvalidLink field "icon" */
		public static function parse(string $file): self {
			throw new \LogicException('not implemented');
		}

		#[\Override]
		public function __toString(): string {
			throw new \LogicException('not implemented');
		}
	}

	// file: lib/Links/InvalidLink.php
	/** One field of one link failed to parse. Message is admin-facing and already translated. */
	final class InvalidLink extends \InvalidArgumentException {
		public function __construct(
			public readonly string $field,
			string $message,
		) {
			parent::__construct($message);
		}
	}

	// file: lib/Links/InvalidCatalog.php
	/** Every row error from one parse, so the admin UI can mark all bad fields at once. */
	final class InvalidCatalog extends \InvalidArgumentException {
		/** @param list<array{index: int, field: string, message: string}> $errors */
		public function __construct(public readonly array $errors) {
			parent::__construct('catalog has ' . count($errors) . ' invalid field(s)');
		}
	}

	// file: lib/Links/StaleCatalog.php
	/** The stored catalog moved since the caller read it. Carries what is stored now. */
	final class StaleCatalog extends \RuntimeException {
		public function __construct(public readonly Catalog $current) {
			parent::__construct('catalog revision mismatch');
		}
	}

	// file: lib/Links/CompanyLink.php
	/**
	 * Valid by construction: if you hold one, every field is legal.
	 * Sort position is not a field. It is the link's index in its Catalog.
	 */
	final readonly class CompanyLink implements \JsonSerializable {
		public const TITLE_MAX = 120;

		/** @throws InvalidLink field "title" when trimmed title is empty or longer than TITLE_MAX */
		public function __construct(
			public LinkId $id,
			public string $title,
			public HttpsUrl $href,
			public Importance $importance,
			public OpenMode $openMode,
			public ?Icon $icon,
			public bool $enabled,
		) {
			throw new \LogicException('not implemented');
		}

		/**
		 * Boundary. Untrusted row (wire or storage) → link.
		 * Inverse of jsonSerialize(): parse(json_decode(json_encode($link))) equals $link.
		 *
		 * @throws InvalidLink first failing field, in declaration order
		 */
		public static function parse(mixed $row): self {
			throw new \LogicException('not implemented');
		}

		/**
		 * Canonical form, used both on the OCS wire and inside the stored document:
		 * {id, title, href, importance, openMode, icon: string|null, enabled}
		 *
		 * @return array{id: string, title: string, href: string, importance: string, openMode: string, icon: ?string, enabled: bool}
		 */
		#[\Override]
		public function jsonSerialize(): array {
			throw new \LogicException('not implemented');
		}
	}

	// file: lib/Links/Catalog.php
	/**
	 * Immutable, always in canonical order: stable sort by Importance::rank(),
	 * input order preserved inside a band. Ids unique. At most MAX_LINKS.
	 * Every method that returns a Catalog returns one that still holds these invariants.
	 */
	final class Catalog implements \JsonSerializable, \Countable {
		public const MAX_LINKS = 200;

		/** @param list<CompanyLink> $links already canonical */
		private function __construct(private readonly array $links) {
		}

		public static function empty(): self {
			throw new \LogicException('not implemented');
		}

		/**
		 * Canonicalises. Duplicate ids or more than MAX_LINKS → InvalidCatalog.
		 *
		 * @throws InvalidCatalog
		 */
		public static function of(CompanyLink ...$links): self {
			throw new \LogicException('not implemented');
		}

		/**
		 * Boundary. Untrusted list of rows → Catalog. Collects every row's first
		 * InvalidLink into one InvalidCatalog instead of failing on the first row.
		 *
		 * @throws InvalidCatalog
		 */
		public static function parse(array $rows): self {
			throw new \LogicException('not implemented');
		}

		/** Enabled links only. */
		public function visible(): self {
			throw new \LogicException('not implemented');
		}

		public function band(Importance $importance): self {
			throw new \LogicException('not implemented');
		}

		/**
		 * Links after $since in canonical order. null or an unknown id → the whole catalog
		 * (a client whose anchor was deleted restarts from the top rather than stalling).
		 */
		public function after(?LinkId $since): self {
			throw new \LogicException('not implemented');
		}

		public function take(int $limit): self {
			throw new \LogicException('not implemented');
		}

		public function find(LinkId $id): ?CompanyLink {
			throw new \LogicException('not implemented');
		}

		/** @return list<CompanyLink> canonical order */
		public function links(): array {
			throw new \LogicException('not implemented');
		}

		#[\Override]
		public function count(): int {
			throw new \LogicException('not implemented');
		}

		public function isEmpty(): bool {
			throw new \LogicException('not implemented');
		}

		/**
		 * Derived, never stored: first 12 hex chars of sha256 over the canonical
		 * JSON of links(). Equal content ⇔ equal revision. Optimistic-concurrency token.
		 */
		public function revision(): string {
			throw new \LogicException('not implemented');
		}

		/** @return array{revision: string, links: list<array<string, mixed>>} */
		#[\Override]
		public function jsonSerialize(): array {
			throw new \LogicException('not implemented');
		}
	}

	// file: lib/Links/CatalogStore.php
	/**
	 * The only reader and writer of this app's IAppConfig.
	 * One lazy array key holds the whole document: {schema: 1, links: [...canonical rows...]}.
	 * Nothing else is stored: no counter, no sort integers, no revision.
	 */
	final class CatalogStore {
		private const KEY = 'catalog';
		private const SCHEMA = 1;

		public function __construct(
			private readonly IAppConfig $config,
			private readonly Icons $icons,
			private readonly LoggerInterface $logger,
		) {
		}

		/**
		 * Never throws. Runs on every Dashboard load, so a corrupt row must not take
		 * the tile down: unparseable rows and duplicate ids are dropped with one warning
		 * each; an unknown schema yields Catalog::empty() with an error log.
		 */
		public function current(): Catalog {
			// TODO
			//   $doc = $config->getValueArray(APP_ID, KEY, [], lazy: true)
			//   if ($doc['schema'] ?? SCHEMA) !== SCHEMA → log error, return empty
			//   foreach rows: try CompanyLink::parse → keep; catch InvalidLink → log warning, skip
			//   return Catalog::of(...kept) with duplicates dropped (first wins) rather than thrown
			throw new \LogicException('not implemented');
		}

		/**
		 * Replace the whole catalog. Idempotent and race-safe:
		 *   1. if $next->revision() === current()->revision(): return current(), write nothing
		 *   2. if $expectedRevision !== current()->revision(): throw StaleCatalog(current())
		 *   3. every $next icon must exist in Icons, else InvalidCatalog(field "icon")
		 *   4. one setValueArray(APP_ID, KEY, document, lazy: true)
		 *   5. Icons::pruneExcept(icons referenced by $next)
		 * Step 4 is the single write; a crash before it leaves the old catalog intact,
		 * a crash after it leaves only orphan icon files, which the next save prunes.
		 *
		 * @throws StaleCatalog
		 * @throws InvalidCatalog
		 */
		public function replace(Catalog $next, string $expectedRevision): Catalog {
			throw new \LogicException('not implemented');
		}
	}

	// file: lib/Links/Icons.php
	/** Admin-uploaded icons in AppData `icons/`. Content-addressed, so store() is idempotent. */
	final class Icons {
		public const MAX_BYTES = 262144;

		/** mime → extension. The whole allowlist. Sniffed from bytes, never trusted from the upload. */
		private const TYPES = [
			'image/png' => 'png',
			'image/svg+xml' => 'svg',
			'image/jpeg' => 'jpg',
			'image/webp' => 'webp',
		];

		public function __construct(private readonly IAppDataFactory $appDataFactory) {
		}

		/** @throws InvalidLink field "icon" when the type is not in TYPES or the size exceeds MAX_BYTES */
		public function store(string $bytes): Icon {
			throw new \LogicException('not implemented');
		}

		public function exists(Icon $icon): bool {
			throw new \LogicException('not implemented');
		}

		/** @throws \OCP\Files\NotFoundException */
		public function open(Icon $icon): ISimpleFile {
			throw new \LogicException('not implemented');
		}

		/** @param iterable<Icon> $keep */
		public function pruneExcept(iterable $keep): void {
			throw new \LogicException('not implemented');
		}

		/** Uninstall. */
		public function deleteAll(): void {
			throw new \LogicException('not implemented');
		}
	}

	// file: lib/Links/LinkUrls.php
	/**
	 * Every URL the app hands to a browser. The only class that knows route names,
	 * so the widget, the all-links page and the settings UI cannot disagree.
	 * All results are absolute and same-origin except openUrl() for Redirect links.
	 */
	final class LinkUrls {
		public function __construct(private readonly IURLGenerator $urlGenerator) {
		}

		/** Redirect → (string) $link->href. Iframe → route dashboard_links.page.open {id}. */
		public function openUrl(CompanyLink $link): string {
			throw new \LogicException('not implemented');
		}

		/** $link->icon → route dashboard_links.icon.show {file}. null → defaultIconUrl(). */
		public function iconUrl(CompanyLink $link): string {
			throw new \LogicException('not implemented');
		}

		/** img/app-dark.svg, absolute. Also the widget's own icon. */
		public function defaultIconUrl(): string {
			throw new \LogicException('not implemented');
		}

		/** route dashboard_links.page.index. Widget header click and TYPE_MORE. */
		public function allLinksUrl(): string {
			throw new \LogicException('not implemented');
		}

		/** Admin settings, our section. TYPE_SETUP. */
		public function settingsUrl(): string {
			throw new \LogicException('not implemented');
		}
	}
}

namespace OCA\DashboardLinks\Dashboard {

	use OCA\DashboardLinks\AppInfo\Application;
	use OCA\DashboardLinks\Links\Catalog;
	use OCA\DashboardLinks\Links\CatalogStore;
	use OCA\DashboardLinks\Links\CompanyLink;
	use OCA\DashboardLinks\Links\LinkUrls;
	use OCP\Dashboard\IAPIWidget;
	use OCP\Dashboard\IAPIWidgetV2;
	use OCP\Dashboard\IButtonWidget;
	use OCP\Dashboard\IIconWidget;
	use OCP\Dashboard\Model\WidgetItem;
	use OCP\Dashboard\Model\WidgetItems;
	use OCP\IGroupManager;
	use OCP\IL10N;

	// file: lib/Dashboard/LinksWidget.php
	/**
	 * Rendered by the server's own Dashboard through API v2. No JavaScript from this app.
	 * Not IConditionalWidget: an empty catalog shows the empty message and, to admins,
	 * a Configure button, which a hidden tile could not.
	 */
	final class LinksWidget implements IAPIWidget, IAPIWidgetV2, IIconWidget, IButtonWidget {
		public const ID = Application::APP_ID;
		public const ORDER = 20;

		/** DashboardApp.vue calls widget-items without `limit`; the server then passes 7. */
		private const WEB_TILE_LIMIT = 7;

		public function __construct(
			private readonly IL10N $l10n,
			private readonly IGroupManager $groupManager,
			private readonly CatalogStore $store,
			private readonly LinkUrls $urls,
		) {
		}

		#[\Override]
		public function getId(): string {
			return self::ID;
		}

		#[\Override]
		public function getTitle(): string {
			throw new \LogicException('not implemented');
		}

		#[\Override]
		public function getOrder(): int {
			return self::ORDER;
		}

		/** Monochrome CSS class; the Dashboard inverts it for dark mode. */
		#[\Override]
		public function getIconClass(): string {
			throw new \LogicException('not implemented');
		}

		#[\Override]
		public function getIconUrl(): string {
			throw new \LogicException('not implemented');
		}

		/** Header click → all-links page. */
		#[\Override]
		public function getUrl(): ?string {
			throw new \LogicException('not implemented');
		}

		/** Must stay empty. A script here would fight the generic V2 renderer. */
		#[\Override]
		public function load(): void {
		}

		/** @return list<WidgetItem> */
		#[\Override]
		public function getItems(string $userId, ?string $since = null, int $limit = 7): array {
			throw new \LogicException('not implemented');
		}

		#[\Override]
		public function getItemsV2(string $userId, ?string $since = null, int $limit = 7): WidgetItems {
			throw new \LogicException('not implemented');
		}

		/**
		 * TYPE_MORE → allLinksUrl() when visible count > WEB_TILE_LIMIT.
		 * TYPE_SETUP → settingsUrl() when the visible catalog is empty and $userId is an admin.
		 * Never TYPE_NEW: the web renderer does not draw it.
		 *
		 * @return list<\OCP\Dashboard\Model\WidgetButton>
		 */
		#[\Override]
		public function getWidgetButtons(string $userId): array {
			throw new \LogicException('not implemented');
		}

		/** visible → after(since) → take(limit). Shared by both item APIs. */
		private function page(?string $since, int $limit): Catalog {
			throw new \LogicException('not implemented');
		}

		/** WidgetItem(title, href host, urls->openUrl, urls->iconUrl, (string) id). */
		private function toItem(CompanyLink $link): WidgetItem {
			throw new \LogicException('not implemented');
		}
	}
}

namespace OCA\DashboardLinks\Controller {

	use OCA\DashboardLinks\Links\CatalogStore;
	use OCA\DashboardLinks\Links\CompanyLink;
	use OCA\DashboardLinks\Links\Icons;
	use OCA\DashboardLinks\Links\LinkUrls;
	use OCP\AppFramework\Controller;
	use OCP\AppFramework\Http\Attribute\ApiRoute;
	use OCP\AppFramework\Http\Attribute\FrontpageRoute;
	use OCP\AppFramework\Http\Attribute\NoAdminRequired;
	use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
	use OCP\AppFramework\Http\DataResponse;
	use OCP\AppFramework\Http\Response;
	use OCP\AppFramework\Http\TemplateResponse;
	use OCP\AppFramework\OCSController;
	use OCP\IRequest;

	// file: lib/Controller/CatalogController.php
	/** Admin only (no NoAdminRequired anywhere). CSRF on for both verbs; axios sends the token. */
	final class CatalogController extends OCSController {
		public function __construct(
			string $appName,
			IRequest $request,
			private readonly CatalogStore $store,
		) {
			parent::__construct($appName, $request);
		}

		/** 200 {revision, links}. */
		#[ApiRoute(verb: 'GET', url: '/api/v1/catalog')]
		public function show(): DataResponse {
			throw new \LogicException('not implemented');
		}

		/**
		 * Whole-catalog replace.
		 * 200 {revision, links} saved (or unchanged) catalog
		 * 400 {errors: [{index, field, message}]} from InvalidCatalog
		 * 412 {revision, links} current catalog, from StaleCatalog
		 */
		#[ApiRoute(verb: 'PUT', url: '/api/v1/catalog')]
		public function replace(string $revision, array $links): DataResponse {
			throw new \LogicException('not implemented');
		}
	}

	// file: lib/Controller/IconController.php
	final class IconController extends Controller {
		public function __construct(
			string $appName,
			IRequest $request,
			private readonly Icons $icons,
			private readonly LinkUrls $urls,
		) {
			parent::__construct($appName, $request);
		}

		/**
		 * Admin; CSRF on. multipart field "icon" via IRequest::getUploadedFile().
		 * 200 {icon, url}. 400 {field: "icon", message} on InvalidLink.
		 */
		#[FrontpageRoute(verb: 'POST', url: '/icons')]
		public function upload(): DataResponse {
			throw new \LogicException('not implemented');
		}

		/**
		 * Any logged-in user; images carry no CSRF token.
		 * FileDisplayResponse with a long immutable cache (name is content-addressed) and a
		 * `Content-Security-Policy: sandbox; default-src 'none'` header so an SVG opened
		 * directly cannot script against the Nextcloud origin.
		 * 404 when Icon::parse($file) fails or the file is missing.
		 */
		#[FrontpageRoute(verb: 'GET', url: '/icons/{file}')]
		#[NoAdminRequired]
		#[NoCSRFRequired]
		public function show(string $file): Response {
			throw new \LogicException('not implemented');
		}
	}

	// file: lib/Controller/PageController.php
	final class PageController extends Controller {
		public function __construct(
			string $appName,
			IRequest $request,
			private readonly CatalogStore $store,
			private readonly LinkUrls $urls,
		) {
			parent::__construct($appName, $request);
		}

		/**
		 * All visible links grouped by band, server-rendered (templates/links.php).
		 * Target of getUrl() and TYPE_MORE. Rows are precomputed
		 * {title, url: urls->openUrl, iconUrl: urls->iconUrl, host}; the template holds no logic.
		 */
		#[FrontpageRoute(verb: 'GET', url: '/')]
		#[NoAdminRequired]
		#[NoCSRFRequired]
		public function index(): TemplateResponse {
			throw new \LogicException('not implemented');
		}

		/**
		 * "Open link {id} the way the admin configured it."
		 * Redirect → 303 to the https href. Iframe → frame(). Unknown, unparseable or disabled id → 404.
		 * Both modes are served so a stale bookmark to /open/{id} keeps working after a mode change.
		 */
		#[FrontpageRoute(verb: 'GET', url: '/open/{id}')]
		#[NoAdminRequired]
		#[NoCSRFRequired]
		public function open(string $id): Response {
			throw new \LogicException('not implemented');
		}

		/**
		 * templates/frame.php: one full-height <iframe src=href>. RENDER_AS_USER.
		 * CSP addAllowedFrameDomain('*'): the src is admin-chosen and redirect chains
		 * (SSO) inside the frame would fail a per-origin allowlist. The remote site's own
		 * X-Frame-Options / frame-ancestors still win; that is the admin's Redirect fallback.
		 */
		private function frame(CompanyLink $link): TemplateResponse {
			throw new \LogicException('not implemented');
		}
	}
}

namespace OCA\DashboardLinks\Settings {

	use OCA\DashboardLinks\Links\CatalogStore;
	use OCP\App\IAppManager;
	use OCP\AppFramework\Http\TemplateResponse;
	use OCP\AppFramework\Services\IInitialState;
	use OCP\IL10N;
	use OCP\IURLGenerator;
	use OCP\Settings\IIconSection;
	use OCP\Settings\ISettings;

	// file: lib/Settings/Admin.php  (declared in info.xml <settings><admin>)
	final class Admin implements ISettings {
		public function __construct(
			private readonly CatalogStore $store,
			private readonly IInitialState $initialState,
			private readonly IAppManager $appManager,
		) {
		}

		/**
		 * Initial state: `catalog` = store->current() (JsonSerializable → {revision, links}),
		 * `externalSitesAvailable` = appManager->isEnabledForUser('external').
		 * Util::addScript(APP_ID, APP_ID . '-admin'); templates/settings.php is one mount div.
		 * The External sites import is entirely browser-side and needs nothing else from PHP.
		 */
		#[\Override]
		public function getForm(): TemplateResponse {
			throw new \LogicException('not implemented');
		}

		#[\Override]
		public function getSection(): string {
			return Section::ID;
		}

		#[\Override]
		public function getPriority(): int {
			throw new \LogicException('not implemented');
		}
	}

	// file: lib/Settings/Section.php  (declared in info.xml <settings><admin-section>)
	final class Section implements IIconSection {
		public const ID = 'dashboard_links';

		public function __construct(
			private readonly IL10N $l10n,
			private readonly IURLGenerator $urlGenerator,
		) {
		}

		#[\Override]
		public function getID(): string {
			return self::ID;
		}

		#[\Override]
		public function getName(): string {
			throw new \LogicException('not implemented');
		}

		#[\Override]
		public function getPriority(): int {
			throw new \LogicException('not implemented');
		}

		#[\Override]
		public function getIcon(): string {
			throw new \LogicException('not implemented');
		}
	}
}

namespace OCA\DashboardLinks\Migration {

	use OCA\DashboardLinks\Links\Icons;
	use OCP\IAppConfig;
	use OCP\Migration\IOutput;
	use OCP\Migration\IRepairStep;

	// file: lib/Migration/Uninstall.php  (declared in info.xml <repair-steps><uninstall>)
	final class Uninstall implements IRepairStep {
		public function __construct(
			private readonly IAppConfig $config,
			private readonly Icons $icons,
		) {
		}

		#[\Override]
		public function getName(): string {
			throw new \LogicException('not implemented');
		}

		/** config->deleteApp(APP_ID); icons->deleteAll(). Both idempotent. */
		#[\Override]
		public function run(IOutput $output): void {
			throw new \LogicException('not implemented');
		}
	}
}
