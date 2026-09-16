<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 the dashboard_links authors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Design sketch for the `dashboard_links` app. One file here; one file per type
 * in the real tree, laid out as:
 *
 *   lib/AppInfo/Application.php          IBootstrap, the only registration site
 *   lib/Catalog/*.php                    the domain and its only persistence
 *   lib/Presentation/*.php               links -> view models (URLs, labels)
 *   lib/Dashboard/LinksWidget.php        view models -> WidgetItem
 *   lib/Controller/*.php                 OCS + pages + icon blobs
 *   lib/Settings/{Admin,Section}.php     declared in info.xml, not bootstrap
 *   lib/Migration/UninstallCatalog.php   <repair-steps><uninstall>
 *
 * Bodies are `not implemented`. Comments state invariants the types cannot.
 *
 * PHP 8.2 floor: no typed class constants (`public const string`). Readonly
 * classes are 8.2, promoted readonly properties are 8.1, so both are in play.
 */

namespace OCA\DashboardLinks\AppInfo {

	use OCA\DashboardLinks\Dashboard\LinksWidget;
	use OCP\AppFramework\App;
	use OCP\AppFramework\Bootstrap\IBootContext;
	use OCP\AppFramework\Bootstrap\IBootstrap;
	use OCP\AppFramework\Bootstrap\IRegistrationContext;

	final class Application extends App implements IBootstrap {
		public const APP_ID = 'dashboard_links';

		public function __construct() {
			parent::__construct(self::APP_ID);
		}

		/**
		 * Declarative only. Nothing may be resolved from the container or read
		 * from config here: the Coordinator calls this on every request for
		 * every enabled app, and `lazyRegisterWidget()` instantiates nothing.
		 *
		 * `registerConfigLexicon()` would make our IAppConfig key types and
		 * lazy flags declarative. TODO: confirm its @since covers NC 33 before
		 * relying on it; until then CatalogStore is the single writer and the
		 * only place that names a key.
		 */
		public function register(IRegistrationContext $context): void {
			$context->registerDashboardWidget(LinksWidget::class);
		}

		/** Deliberately empty. Routes are attributes; settings live in info.xml. */
		public function boot(IBootContext $context): void {
		}
	}
}

namespace OCA\DashboardLinks\Catalog {

	use OCP\Files\IAppData;
	use OCP\Files\SimpleFS\ISimpleFile;
	use OCP\IAppConfig;
	use OCP\IL10N;
	use Psr\Log\LoggerInterface;

	/*
	 * ---------------------------------------------------------------------
	 * Value types. Every illegal state a string could hold dies in one of
	 * these constructors, so nothing downstream re-checks.
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Rank is the primary sort key for the tile. The numbers are an
	 * implementation detail of ordering and are never persisted.
	 */
	enum Importance: string {
		case Featured = 'featured';
		case Normal = 'normal';
		case Reference = 'reference';

		public function rank(): int {
			throw new \LogicException('not implemented');
		}

		/** Localised subtitle text. Literal strings live here so gettext finds them. */
		public function label(IL10N $l10n): string {
			throw new \LogicException('not implemented');
		}

		/** Asset name under `img/` for a WidgetItem overlay, or null for no badge. */
		public function overlayIcon(): ?string {
			throw new \LogicException('not implemented');
		}
	}

	enum OpenMode: string {
		case Iframe = 'iframe';
		case Redirect = 'redirect';
	}

	final readonly class LinkId {
		private function __construct(public int $value) {
		}

		/** @throws InvalidLinkField when not a positive integer */
		public static function parse(int|string $raw): self {
			throw new \LogicException('not implemented');
		}

		/**
		 * `IAPIWidget::$since` is client-supplied and free-form. Garbage means
		 * "from the start", never an error: a bad paging cursor must not break
		 * a tile.
		 */
		public static function tryFromSince(?string $since): ?self {
			throw new \LogicException('not implemented');
		}

		public function equals(self $other): bool {
			throw new \LogicException('not implemented');
		}
	}

	final readonly class ExternalSiteId {
		/** @throws InvalidLinkField when not a positive integer */
		public function __construct(public int $value) {
			throw new \LogicException('not implemented');
		}
	}

	final readonly class LinkTitle {
		public const MAX_LENGTH = 128;

		/** @throws InvalidLinkField when blank after trimming or over MAX_LENGTH */
		public function __construct(public string $value) {
			throw new \LogicException('not implemented');
		}
	}

	/**
	 * The only URL type in the domain. There is no `Url` that could hold
	 * `http://`, so "https after parse" is a property of the type rather than
	 * a rule someone has to remember at four call sites.
	 */
	final readonly class HttpsUrl {
		/**
		 * @throws InvalidLinkField when the value is not an absolute https URL,
		 *                          or carries `user:pass@` credentials
		 */
		public function __construct(public string $value) {
			throw new \LogicException('not implemented');
		}

		/**
		 * `scheme://host[:port]`. The single source for the embed page's
		 * `frame-src` allowance, so the CSP cannot drift from the framed URL.
		 */
		public function origin(): string {
			throw new \LogicException('not implemented');
		}
	}

	/**
	 * Sealed: an icon is a bundled asset or an AppData upload. There is no
	 * remote-URL case, because the Dashboard page's `img-src` is
	 * `'self' data: blob:` and an off-origin favicon renders blank. "Icons are
	 * same-origin" is therefore unrepresentable-otherwise rather than reviewed.
	 */
	interface LinkIcon {
		/** Cache-busting token for the served URL. */
		public function version(): string;
	}

	/** `img/links/{name}.svg`, shipped with the app. */
	final readonly class BundledIcon implements LinkIcon {
		/** @throws InvalidLinkField when $name is not in the shipped set */
		public function __construct(public string $name) {
			throw new \LogicException('not implemented');
		}

		public function version(): string {
			throw new \LogicException('not implemented');
		}
	}

	/** Served by our own IconController out of IAppData. */
	final readonly class AppDataIcon implements LinkIcon {
		public function __construct(
			public LinkId $link,
			/** Content hash of the stored blob; changes on re-upload. */
			public string $revision,
		) {
		}

		public function version(): string {
			throw new \LogicException('not implemented');
		}
	}

	/*
	 * ---------------------------------------------------------------------
	 * The aggregate. Read side.
	 * ---------------------------------------------------------------------
	 */

	final readonly class Link {
		public function __construct(
			public LinkId $id,
			public LinkTitle $title,
			public HttpsUrl $href,
			public Importance $importance,
			public OpenMode $openMode,
			public ?LinkIcon $icon,
			public bool $enabled,
			/**
			 * Position within the importance band. Assigned by
			 * CatalogStore::save() from the submitted row order; never chosen
			 * by a client, which is why SubmittedLink has no counterpart field.
			 */
			public int $sort,
			/** Provenance for idempotent re-import. Never affects href resolution. */
			public ?ExternalSiteId $importedFrom,
		) {
		}
	}

	/**
	 * The whole catalog, canonically ordered and id-unique by construction.
	 *
	 * Immutable. Writes do not go through here — they go through
	 * CatalogStore::save() with a whole SubmittedCatalog — so there is no
	 * `withLink()`/`without()` surface to keep consistent with the store.
	 */
	final class LinkCatalog implements \Countable {
		/**
		 * Sorts by (importance rank, sort, id). Order is established once, here,
		 * so no consumer sorts and no consumer can forget to.
		 *
		 * @param list<Link> $links
		 * @throws InvalidCatalog on duplicate ids
		 */
		public static function of(array $links): self {
			throw new \LogicException('not implemented');
		}

		public static function empty(): self {
			throw new \LogicException('not implemented');
		}

		/**
		 * Content hash over the canonical link list. Derived, never stored, so
		 * there is no counter to bump and nothing to get out of step with the
		 * rows it describes.
		 */
		public function revision(): CatalogRevision {
			throw new \LogicException('not implemented');
		}

		/** Everything, enabled or not. For the admin editor only. @return list<Link> */
		public function links(): array {
			throw new \LogicException('not implemented');
		}

		/** The only route from a catalog to anything user-facing. */
		public function visible(): VisibleLinks {
			throw new \LogicException('not implemented');
		}

		public function byId(LinkId $id): ?Link {
			throw new \LogicException('not implemented');
		}

		/**
		 * @throws LinkNotEmbeddable when unknown, disabled, or redirect-mode.
		 *         The embed page's 404 branch is this one throw, so a
		 *         redirect-only link can never be framed by guessing its id.
		 */
		public function embeddable(LinkId $id): Link {
			throw new \LogicException('not implemented');
		}

		public function count(): int {
			throw new \LogicException('not implemented');
		}
	}

	/**
	 * Enabled links, in tile order. A distinct type, because "disabled links
	 * never render" is then enforced by LinkPresenter's signature instead of by
	 * a filter every caller has to remember.
	 *
	 * Closed under paging: a page of visible links is still visible links.
	 */
	final class VisibleLinks implements \Countable {
		/** @return list<Link> */
		public function links(): array {
			throw new \LogicException('not implemented');
		}

		public function count(): int {
			throw new \LogicException('not implemented');
		}

		/** Drives the TYPE_MORE button without the widget counting anything. */
		public function exceeds(int $limit): bool {
			throw new \LogicException('not implemented');
		}

		/**
		 * `$after` is the `since` cursor: the items following that id in tile
		 * order. An unknown id pages from the start.
		 */
		public function page(int $limit, ?LinkId $after = null): self {
			throw new \LogicException('not implemented');
		}
	}

	/**
	 * Optimistic-concurrency token. A content hash, not a counter, so it is
	 * derivable from the rows at any time by anyone.
	 */
	final readonly class CatalogRevision {
		/** @param list<Link> $links */
		public static function ofLinks(array $links): self {
			throw new \LogicException('not implemented');
		}

		/** @throws InvalidCatalog when the client sent a malformed token */
		public static function parse(string $raw): self {
			throw new \LogicException('not implemented');
		}

		public function equals(self $other): bool {
			throw new \LogicException('not implemented');
		}

		public function __toString(): string {
			throw new \LogicException('not implemented');
		}
	}

	/*
	 * ---------------------------------------------------------------------
	 * The write boundary. The only types that accept arrays.
	 * ---------------------------------------------------------------------
	 */

	/**
	 * One row as the admin UI sent it. Note the absences: no `sort` (position
	 * is the sort) and no `icon` (icons are their own endpoints, and a save
	 * preserves whatever icon the surviving id already had). Neither field can
	 * be sent, so neither can disagree with the truth.
	 */
	final readonly class SubmittedLink {
		public function __construct(
			/** null means "create"; CatalogStore::save() allocates the id. */
			public ?LinkId $id,
			public LinkTitle $title,
			public HttpsUrl $href,
			public Importance $importance,
			public OpenMode $openMode,
			public bool $enabled,
			public ?ExternalSiteId $importedFrom,
		) {
		}

		/**
		 * @param array<string, mixed> $row
		 * @throws InvalidLinkField naming the offending field
		 */
		public static function fromRow(array $row): self {
			throw new \LogicException('not implemented');
		}
	}

	/**
	 * A whole-catalog replace. The admin saves the list they see, which is why
	 * position can be the sort and why replaying the same save is not a
	 * partial-update hazard.
	 */
	final readonly class SubmittedCatalog {
		/**
		 * @param list<SubmittedLink> $links in display order within each band
		 */
		public function __construct(
			public array $links,
			public CatalogRevision $expected,
		) {
		}

		/**
		 * The place untrusted input stops being untrusted. Collects every field
		 * error before throwing, so the settings UI marks all bad rows at once
		 * instead of one save per mistake.
		 *
		 * @param list<array<string, mixed>> $rows
		 * @throws InvalidCatalog
		 */
		public static function fromOcs(array $rows, string $expectedRevision): self {
			throw new \LogicException('not implemented');
		}
	}

	/*
	 * ---------------------------------------------------------------------
	 * Import. Fed by the admin's browser; no server-side call to `external`,
	 * no `OCA\External\*` symbol, no read of the `external` app's config.
	 * ---------------------------------------------------------------------
	 */

	final readonly class ImportedSite {
		public function __construct(
			public ExternalSiteId $siteId,
			public LinkTitle $title,
			public HttpsUrl $href,
			public OpenMode $openMode,
		) {
		}
	}

	final readonly class SkippedSite {
		public function __construct(
			public ?int $siteId,
			public string $name,
			/** Localisable reason shown back to the admin, e.g. not https. */
			public string $reason,
		) {
		}
	}

	final readonly class ImportBatch {
		/**
		 * @param list<ImportedSite> $sites
		 * @param list<SkippedSite> $skipped
		 */
		public function __construct(
			public array $sites,
			public array $skipped,
		) {
		}

		/**
		 * Tolerant by design: one unusable site (http, `mailto:`, blank name)
		 * must not fail an otherwise good import, so bad records become
		 * SkippedSite entries rather than exceptions.
		 *
		 * Maps `name`->title, `url`->href, `redirect`->openMode, `id`->siteId.
		 * `icon` is dropped: it is an asset inside another app, and keeping it
		 * would reintroduce the runtime coupling the import exists to avoid.
		 *
		 * @param list<array<string, mixed>> $records raw admin OCS records
		 */
		public static function fromExternalSites(array $records): self {
			throw new \LogicException('not implemented');
		}
	}

	final readonly class ImportOutcome {
		/** @param list<SkippedSite> $skipped */
		public function __construct(
			public LinkCatalog $catalog,
			public int $added,
			public int $updated,
			public array $skipped,
		) {
		}
	}

	/*
	 * ---------------------------------------------------------------------
	 * Persistence. The only module that knows a storage key exists.
	 * ---------------------------------------------------------------------
	 */

	final readonly class IconUpload {
		public const MAX_BYTES = 65536;

		/** @param resource $stream */
		private function __construct(
			public mixed $stream,
			public string $mimeType,
		) {
		}

		/**
		 * @param array{tmp_name?: string, type?: string, size?: int, error?: int} $file
		 *        as returned by IRequest::getUploadedFile()
		 * @throws UnsupportedIcon when over MAX_BYTES or not image/png,
		 *         image/jpeg, image/webp. SVG is refused on purpose: we serve
		 *         these same-origin, and an SVG is a script-bearing document.
		 */
		public static function fromUploadedFile(array $file): self {
			throw new \LogicException('not implemented');
		}
	}

	/** Owns the AppData layout for icon blobs. Nothing else opens that folder. */
	final class IconStore {
		public function __construct(
			private readonly IAppData $appData,
		) {
		}

		public function put(LinkId $id, IconUpload $upload): AppDataIcon {
			throw new \LogicException('not implemented');
		}

		/** @throws \OCP\Files\NotFoundException */
		public function get(AppDataIcon $icon): ISimpleFile {
			throw new \LogicException('not implemented');
		}

		/** Idempotent: deleting a missing icon is a no-op, not an error. */
		public function delete(LinkId $id): void {
			throw new \LogicException('not implemented');
		}
	}

	/**
	 * The catalog's whole lifecycle behind four methods.
	 *
	 * Hidden here and nowhere else: the IAppConfig keys and their lazy/array
	 * types, the row schema, id allocation, the never-reused id counter, the
	 * revision check and its retry, and the ordering of blob write vs row
	 * write. No caller ever sees an IAppConfig array.
	 *
	 * Keys (all `lazy: true`, types locked on first write):
	 *   `links`  array   the row list
	 *   `max_id` int     monotonic; ids are never reused, so a stale `since`
	 *                    cursor or a bookmarked embed URL can never resolve to
	 *                    a different link than it did before
	 */
	final class CatalogStore {
		public function __construct(
			private readonly IAppConfig $appConfig,
			private readonly IconStore $icons,
			private readonly LoggerInterface $logger,
		) {
		}

		/**
		 * Tolerant read: a row that no longer parses is dropped and logged, not
		 * thrown. One bad row must never blank every user's tile.
		 *
		 * Self-healing follows from revisions being content hashes: the admin's
		 * GET already excludes the bad row, so their next save writes the
		 * cleaned list and the row is gone. No repair step needed.
		 */
		public function load(): LinkCatalog {
			throw new \LogicException('not implemented');
		}

		/**
		 * Whole-catalog replace under an optimistic guard.
		 *
		 * - Allocates ids for rows with `id === null` from `max_id`.
		 * - Assigns `sort` from submitted position.
		 * - Carries over the existing icon of every surviving id; a save can
		 *   neither set nor clear an icon.
		 * - Drops the icon blob of every id that disappeared.
		 *
		 * Runs twice? A replay of a no-op save is a 200 (submitted content
		 * already hashes to the current revision). A replay of a save that
		 * created rows is a StaleCatalog, which is the point: the guard is what
		 * stops a retried create from inserting the row twice.
		 *
		 * @throws StaleCatalog when $submitted->expected is no longer current
		 */
		public function save(SubmittedCatalog $submitted): LinkCatalog {
			throw new \LogicException('not implemented');
		}

		/**
		 * Upsert on ExternalSiteId, so importing the same sites twice updates
		 * instead of duplicating and needs no client-supplied revision.
		 * Internally load-merge-save against the revision just read, retried
		 * once if an admin saved in between.
		 *
		 * New links land as Importance::Reference, enabled, without an icon.
		 * Existing imported links keep their local importance, enabled flag and
		 * icon: the import refreshes title, href and open mode only, so it
		 * never undoes a deliberate local edit.
		 */
		public function import(ImportBatch $batch): ImportOutcome {
			throw new \LogicException('not implemented');
		}

		/**
		 * Blob and row in one call, in that order, so a crash between them
		 * leaves an orphan blob (invisible, overwritten on the next upload)
		 * rather than a row pointing at nothing.
		 *
		 * @param IconUpload|null $upload null clears the icon
		 * @throws UnknownLink
		 */
		public function setIcon(LinkId $id, ?IconUpload $upload): LinkCatalog {
			throw new \LogicException('not implemented');
		}
	}

	/*
	 * ---------------------------------------------------------------------
	 * Failures. Each one maps to exactly one boundary response.
	 * ---------------------------------------------------------------------
	 */

	abstract class CatalogException extends \RuntimeException {
	}

	/** Never reaches a boundary alone; collected into InvalidCatalog. */
	final class InvalidLinkField extends CatalogException {
		public function __construct(
			public readonly string $field,
			string $message,
		) {
			parent::__construct($message);
		}
	}

	/** -> OCS 400 */
	final class InvalidCatalog extends CatalogException {
		/** @param array<int, array<string, string>> $errors row index => field => message */
		public function __construct(
			public readonly array $errors,
		) {
			parent::__construct('invalid catalog');
		}
	}

	/** -> OCS 412, carrying what to show the admin instead. */
	final class StaleCatalog extends CatalogException {
		public function __construct(
			public readonly LinkCatalog $current,
		) {
			parent::__construct('catalog changed since it was read');
		}
	}

	/** -> OCS 404 */
	final class UnknownLink extends CatalogException {
	}

	/** -> page 404 */
	final class LinkNotEmbeddable extends CatalogException {
	}

	/** -> OCS 400 */
	final class UnsupportedIcon extends CatalogException {
	}
}

namespace OCA\DashboardLinks\Presentation {

	use OCA\DashboardLinks\Catalog\Importance;
	use OCA\DashboardLinks\Catalog\LinkId;
	use OCA\DashboardLinks\Catalog\VisibleLinks;
	use OCP\IL10N;
	use OCP\IURLGenerator;

	/**
	 * A link as any surface renders it. Our own type, not the Dashboard's, so
	 * the tile and the overflow page share one presentation without the page
	 * depending on `OCP\Dashboard\Model\WidgetItem`.
	 */
	final readonly class LinkView {
		public function __construct(
			public LinkId $id,
			public string $title,
			/** Localised importance label: the tile's visible ranking signal. */
			public string $subtitle,
			/** Absolute. Our embed route for iframe mode, the site for redirect. */
			public string $href,
			/** Absolute and same-origin, always set; falls back to a shipped asset. */
			public string $iconUrl,
			/** '' when the importance carries no badge. */
			public string $overlayIconUrl,
			public Importance $importance,
		) {
		}
	}

	/**
	 * One public method. Behind it: open-mode href resolution, the
	 * same-origin icon rules and their fallback, cache-busting versions, and
	 * the importance label and badge.
	 *
	 * No IAppManager, and no route into the `external` app: an imported
	 * iframe link gets our embed page like any other, so a disabled or
	 * uninstalled `external` cannot break a link we are showing.
	 */
	final class LinkPresenter {
		public function __construct(
			private readonly IURLGenerator $urlGenerator,
			private readonly IL10N $l10n,
		) {
		}

		/**
		 * Takes VisibleLinks rather than a list, so a disabled link is not
		 * something a caller can pass by mistake.
		 *
		 * @return list<LinkView>
		 */
		public function views(VisibleLinks $links): array {
			throw new \LogicException('not implemented');
		}
	}
}

namespace OCA\DashboardLinks\Dashboard {

	use OCA\DashboardLinks\Catalog\CatalogStore;
	use OCA\DashboardLinks\Presentation\LinkPresenter;
	use OCP\Dashboard\IAPIWidget;
	use OCP\Dashboard\IAPIWidgetV2;
	use OCP\Dashboard\IButtonWidget;
	use OCP\Dashboard\IIconWidget;
	use OCP\Dashboard\Model\WidgetItem;
	use OCP\Dashboard\Model\WidgetItems;
	use OCP\IGroupManager;
	use OCP\IL10N;
	use OCP\IURLGenerator;

	/**
	 * API v2 means the server's own Vue renders the tile, so this app ships no
	 * dashboard JavaScript and `load()` stays empty. IReloadableWidget and
	 * IOptionWidget are omitted: a static catalog gains nothing from polling,
	 * and square item icons already suit company logos.
	 */
	final class LinksWidget implements IAPIWidget, IAPIWidgetV2, IIconWidget, IButtonWidget {
		/**
		 * The web frontend never sends `limit`, so the server passes 7. This is
		 * also the TYPE_MORE threshold, because getWidgetButtons() is served by
		 * a different OCS call that has no limit to consult.
		 */
		public const TILE_LIMIT = 7;

		public function __construct(
			private readonly IL10N $l10n,
			private readonly IURLGenerator $urlGenerator,
			private readonly IGroupManager $groupManager,
			private readonly CatalogStore $store,
			private readonly LinkPresenter $presenter,
		) {
		}

		/** Matches the app id, which keeps it globally unique. */
		public function getId(): string {
			throw new \LogicException('not implemented');
		}

		public function getTitle(): string {
			throw new \LogicException('not implemented');
		}

		/** 20. 0-9 is docs-reserved for shipped widgets. */
		public function getOrder(): int {
			throw new \LogicException('not implemented');
		}

		public function getIconClass(): string {
			throw new \LogicException('not implemented');
		}

		/** Absolute URL to a monochrome asset; the Dashboard inverts it for dark mode. */
		public function getIconUrl(): string {
			throw new \LogicException('not implemented');
		}

		/** The overflow page, which is also the TYPE_MORE target. */
		public function getUrl(): ?string {
			throw new \LogicException('not implemented');
		}

		/** Empty on purpose. Adding a script here fights the generic renderer. */
		public function load(): void {
		}

		/** @return list<WidgetItem> */
		public function getItems(string $userId, ?string $since = null, int $limit = self::TILE_LIMIT): array {
			throw new \LogicException('not implemented');
		}

		public function getItemsV2(string $userId, ?string $since = null, int $limit = self::TILE_LIMIT): WidgetItems {
			throw new \LogicException('not implemented');
		}

		/**
		 * TYPE_MORE only when the visible catalog overflows the tile.
		 * TYPE_SETUP only for admins. TYPE_NEW is not rendered on the web.
		 *
		 * @return list<\OCP\Dashboard\Model\WidgetButton>
		 */
		public function getWidgetButtons(string $userId): array {
			throw new \LogicException('not implemented');
		}

		/**
		 * The single mapper both API versions use. Our LinkView -> the
		 * framework's WidgetItem, and nothing else: no filtering, no sorting,
		 * no URL building.
		 *
		 * @return list<WidgetItem>
		 */
		private function items(?string $since, int $limit): array {
			throw new \LogicException('not implemented');
		}
	}
}

namespace OCA\DashboardLinks\Controller {

	use OCA\DashboardLinks\Catalog\CatalogStore;
	use OCP\AppFramework\Controller;
	use OCP\AppFramework\Http\Attribute\ApiRoute;
	use OCP\AppFramework\Http\Attribute\FrontpageRoute;
	use OCP\AppFramework\Http\Attribute\NoAdminRequired;
	use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
	use OCP\AppFramework\Http\Attribute\PasswordConfirmationRequired;
	use OCP\AppFramework\Http\DataResponse;
	use OCP\AppFramework\Http\Response;
	use OCP\AppFramework\Http\TemplateResponse;
	use OCP\AppFramework\OCSController;
	use OCP\IRequest;

	/**
	 * Admin-only by omission: no method carries #[NoAdminRequired]. CSRF stays
	 * on every mutating verb; @nextcloud/axios sends the token.
	 *
	 * Attribute routes, not appinfo/routes.php, so the route and the handler
	 * are one file rather than two.
	 *
	 * Every method returns the same envelope, `{revision, links}`, so the
	 * settings UI never has to re-GET after a write to learn the new revision.
	 */
	final class CatalogApiController extends OCSController {
		public function __construct(
			string $appName,
			IRequest $request,
			private readonly CatalogStore $store,
		) {
			parent::__construct($appName, $request);
		}

		#[ApiRoute(verb: 'GET', url: '/api/v1/catalog')]
		#[NoCSRFRequired]
		public function show(): DataResponse {
			throw new \LogicException('not implemented');
		}

		/**
		 * @param list<array<string, mixed>> $links whole catalog, in row order
		 * @param string $revision the revision this edit started from
		 *
		 * 400 with per-row field errors, 412 with the current catalog, else 200.
		 */
		#[ApiRoute(verb: 'PUT', url: '/api/v1/catalog')]
		#[PasswordConfirmationRequired]
		public function save(array $links, string $revision): DataResponse {
			throw new \LogicException('not implemented');
		}

		/**
		 * The admin's browser read these from `external`'s admin OCS. No
		 * server-side HTTP call, no credentials to forward, and `external` may
		 * be absent without this app noticing.
		 *
		 * @param list<array<string, mixed>> $sites
		 */
		#[ApiRoute(verb: 'POST', url: '/api/v1/catalog/import')]
		public function importExternalSites(array $sites): DataResponse {
			throw new \LogicException('not implemented');
		}

		#[ApiRoute(verb: 'PUT', url: '/api/v1/links/{id}/icon')]
		#[PasswordConfirmationRequired]
		public function setIcon(int $id): DataResponse {
			throw new \LogicException('not implemented');
		}

		#[ApiRoute(verb: 'DELETE', url: '/api/v1/links/{id}/icon')]
		public function clearIcon(int $id): DataResponse {
			throw new \LogicException('not implemented');
		}

		/**
		 * The one place the admin wire format is written. Emits
		 * `{revision, links: [{id, title, href, importance, openMode, enabled,
		 * iconUrl, importedFrom}]}` — `iconUrl` read-only, no `sort`, because
		 * the editor sends back row order and never a sort number.
		 *
		 * @return array<string, mixed>
		 */
		private function rows(\OCA\DashboardLinks\Catalog\LinkCatalog $catalog): array {
			throw new \LogicException('not implemented');
		}
	}

	/**
	 * Both pages this app owns. Reachable by every user, hence
	 * #[NoAdminRequired] on both.
	 */
	final class PagesController extends Controller {
		public function __construct(
			string $appName,
			IRequest $request,
			private readonly CatalogStore $store,
			private readonly \OCA\DashboardLinks\Presentation\LinkPresenter $presenter,
		) {
			parent::__construct($appName, $request);
		}

		/**
		 * The overflow page: getUrl() and TYPE_MORE both point here. Rendered
		 * server-side from the same LinkView list the tile uses, so the two
		 * surfaces cannot disagree and the page needs no JavaScript.
		 */
		#[NoAdminRequired]
		#[NoCSRFRequired]
		#[FrontpageRoute(verb: 'GET', url: '/')]
		public function index(): TemplateResponse {
			throw new \LogicException('not implemented');
		}

		/**
		 * The in-Nextcloud embed page for iframe-mode links, and the only CSP
		 * relaxation in the app: `frame-src` is widened to this link's origin
		 * alone, never to `*`.
		 *
		 * Non-embeddable ids 404 rather than redirect, so the route cannot be
		 * used to frame an arbitrary link by id guessing.
		 */
		#[NoAdminRequired]
		#[NoCSRFRequired]
		#[FrontpageRoute(verb: 'GET', url: '/open/{id}')]
		public function embed(int $id): Response {
			throw new \LogicException('not implemented');
		}
	}

	/**
	 * Serves uploaded logos same-origin. `$v` is the icon revision from
	 * AppDataIcon, so responses are immutably cacheable and a re-upload busts
	 * the cache without a config flag.
	 */
	final class IconController extends Controller {
		public function __construct(
			string $appName,
			IRequest $request,
			private readonly CatalogStore $store,
			private readonly \OCA\DashboardLinks\Catalog\IconStore $icons,
		) {
			parent::__construct($appName, $request);
		}

		/** Sent with `nosniff` and a sandboxed CSP; only raster types are ever stored. */
		#[NoAdminRequired]
		#[NoCSRFRequired]
		#[FrontpageRoute(verb: 'GET', url: '/icon/{id}')]
		public function show(int $id, string $v = ''): Response {
			throw new \LogicException('not implemented');
		}
	}
}

namespace OCA\DashboardLinks\Settings {

	use OCP\AppFramework\Http\TemplateResponse;
	use OCP\IL10N;
	use OCP\IURLGenerator;
	use OCP\Settings\IIconSection;
	use OCP\Settings\ISettings;

	/**
	 * Registered in info.xml `<settings><admin>`, not in IBootstrap. Mixing the
	 * two is the classic first-app mistake.
	 *
	 * getForm() adds the Vite bundle `dashboard_links-admin` and returns a
	 * template that is one mount point. No initial state: the editor GETs the
	 * catalog so it always starts from a live revision.
	 */
	final class Admin implements ISettings {
		public function getForm(): TemplateResponse {
			throw new \LogicException('not implemented');
		}

		public function getSection(): string {
			throw new \LogicException('not implemented');
		}

		public function getPriority(): int {
			throw new \LogicException('not implemented');
		}
	}

	/** Its own section: a CRUD list is too big for `additional`. */
	final class Section implements IIconSection {
		public function __construct(
			private readonly IL10N $l10n,
			private readonly IURLGenerator $urlGenerator,
		) {
		}

		public function getID(): string {
			throw new \LogicException('not implemented');
		}

		public function getName(): string {
			throw new \LogicException('not implemented');
		}

		public function getPriority(): int {
			throw new \LogicException('not implemented');
		}

		public function getIcon(): string {
			throw new \LogicException('not implemented');
		}
	}
}

namespace OCA\DashboardLinks\Migration {

	use OCP\Files\IAppData;
	use OCP\IAppConfig;
	use OCP\Migration\IOutput;
	use OCP\Migration\IRepairStep;

	/**
	 * info.xml `<repair-steps><uninstall>`. Deletes every app-config key and the
	 * AppData icon folder. Idempotent: running it on an already-clean instance
	 * does nothing.
	 */
	final class UninstallCatalog implements IRepairStep {
		public function __construct(
			private readonly IAppConfig $appConfig,
			private readonly IAppData $appData,
		) {
		}

		public function getName(): string {
			throw new \LogicException('not implemented');
		}

		public function run(IOutput $output): void {
			throw new \LogicException('not implemented');
		}
	}
}
