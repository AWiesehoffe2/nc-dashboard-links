<?php

declare(strict_types=1);

namespace OCA\DashboardLinks;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\StreamResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Files\IAppData;
use OCP\Dashboard\IAPIWidget;
use OCP\Dashboard\IAPIWidgetV2;
use OCP\Dashboard\IButtonWidget;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\Model\WidgetButton;
use OCP\Dashboard\Model\WidgetItem;
use OCP\Dashboard\Model\WidgetItems;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use OCP\Settings\IIconSection;
use OCP\Settings\ISettings;

// ---------------------------------------------------------------------------
// AppInfo/Application.php
// ---------------------------------------------------------------------------

final class Application extends App implements IBootstrap {
	public const APP_ID = 'dashboard_links';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerDashboardWidget(LinksWidget::class);
	}

	public function boot(IBootContext $context): void {
	}
}

// ---------------------------------------------------------------------------
// Domain — importance is a lane, not a field on the link
// ---------------------------------------------------------------------------

enum Importance: string {
	case Featured = 'featured';
	case Normal = 'normal';
	case Reference = 'reference';
}

enum OpenMode: string {
	case Iframe = 'iframe';
	case Redirect = 'redirect';
}

enum IconKind {
	case None;
	case Bundled;
	case Stored;
}

final class InvalidCatalog extends \InvalidArgumentException {
}

final class LinkId {
	private function __construct(
		private readonly int $value,
	) {
	}

	public static function fromInt(int $value): self {
		// Positive integers only. Callers that invent ids still go through parse.
		throw new \RuntimeException('not implemented');
	}

	public function value(): int {
		throw new \RuntimeException('not implemented');
	}
}

/**
 * Never-reused ids. maxId only grows, including across deletes and
 * import drafts that later merge onto an existing href.
 */
final class IdIssuer {
	/** @var array<int, true> */
	private array $used = [];

	public function __construct(
		private int $maxId,
	) {
	}

	public function adopt(int $id): LinkId {
		throw new \RuntimeException('not implemented');
	}

	public function next(): LinkId {
		throw new \RuntimeException('not implemented');
	}

	public function maxId(): int {
		throw new \RuntimeException('not implemented');
	}
}

final class LinkTitle {
	private function __construct(
		private readonly string $value,
	) {
	}

	public static function parse(string $raw): self {
		// Trimmed, non-empty, ≤128 chars.
		throw new \RuntimeException('not implemented');
	}

	public function value(): string {
		throw new \RuntimeException('not implemented');
	}
}

final class HttpsUrl {
	private function __construct(
		private readonly string $value,
	) {
	}

	public static function parse(string $raw): self {
		// FILTER_VALIDATE_URL + scheme https + host. No http, mailto, or credentials.
		throw new \RuntimeException('not implemented');
	}

	public function value(): string {
		throw new \RuntimeException('not implemented');
	}

	/** Lowercased host, no fragment, no trailing slash on path `/`. Import merge key. */
	public function normalized(): string {
		throw new \RuntimeException('not implemented');
	}
}

final class LinkIcon {
	private function __construct(
		private readonly IconKind $kind,
		private readonly ?string $ref,
	) {
	}

	public static function none(): self {
		throw new \RuntimeException('not implemented');
	}

	public static function bundled(string $filename): self {
		// Basename only, file must live under img/.
		throw new \RuntimeException('not implemented');
	}

	public static function stored(string $fileId): self {
		throw new \RuntimeException('not implemented');
	}

	public static function parse(mixed $raw): self {
		// Rejects any URL. Missing/null → none.
		throw new \RuntimeException('not implemented');
	}

	public function kind(): IconKind {
		throw new \RuntimeException('not implemented');
	}

	public function ref(): ?string {
		throw new \RuntimeException('not implemented');
	}
}

final class CompanyLink {
	private function __construct(
		private readonly LinkId $id,
		private readonly LinkTitle $title,
		private readonly HttpsUrl $href,
		private readonly OpenMode $openMode,
		private readonly LinkIcon $icon,
		private readonly bool $enabled,
		private readonly int $sort,
	) {
	}

	/**
	 * @param array<string, mixed> $row
	 */
	public static function parse(array $row, LinkId $id): self {
		// A present `importance` key is InvalidCatalog — the lane is the only source.
		throw new \RuntimeException('not implemented');
	}

	public function id(): LinkId {
		throw new \RuntimeException('not implemented');
	}

	public function title(): LinkTitle {
		throw new \RuntimeException('not implemented');
	}

	public function href(): HttpsUrl {
		throw new \RuntimeException('not implemented');
	}

	public function openMode(): OpenMode {
		throw new \RuntimeException('not implemented');
	}

	public function icon(): LinkIcon {
		throw new \RuntimeException('not implemented');
	}

	public function enabled(): bool {
		throw new \RuntimeException('not implemented');
	}

	public function sort(): int {
		throw new \RuntimeException('not implemented');
	}
}

/**
 * Read model. Only Catalog::visible() / sliceVisible() construct this,
 * so importance always comes from the lane the link actually sits in.
 */
final class DisplayedLink {
	public function __construct(
		public readonly CompanyLink $link,
		public readonly Importance $importance,
	) {
	}
}

final class LinkLane {
	/** @param list<CompanyLink> $links */
	private function __construct(
		private readonly array $links,
	) {
	}

	public static function empty(): self {
		throw new \RuntimeException('not implemented');
	}

	/**
	 * @param list<mixed> $rows
	 */
	public static function parse(array $rows, IdIssuer $issuer): self {
		// Unique ids inside this parse come from the shared issuer.
		throw new \RuntimeException('not implemented');
	}

	/** @return list<CompanyLink> */
	public function all(): array {
		throw new \RuntimeException('not implemented');
	}

	/** @return list<CompanyLink> enabled, by sort then id */
	public function enabledSorted(): array {
		throw new \RuntimeException('not implemented');
	}

	/** @return list<array<string, mixed>> */
	public function toAdminArray(): array {
		throw new \RuntimeException('not implemented');
	}
}

final class Catalog {
	private function __construct(
		private readonly LinkLane $featured,
		private readonly LinkLane $normal,
		private readonly LinkLane $reference,
		private readonly int $maxId,
	) {
	}

	public static function empty(): self {
		throw new \RuntimeException('not implemented');
	}

	/**
	 * @param array<string, mixed> $payload featured/normal/reference arrays.
	 * A flat `links` list is InvalidCatalog — importance is the lane key.
	 */
	public static function parse(array $payload, IdIssuer $issuer): self {
		throw new \RuntimeException('not implemented');
	}

	/** @return array{featured: list<array<string, mixed>>, normal: list<array<string, mixed>>, reference: list<array<string, mixed>>} */
	public function toAdminArray(): array {
		throw new \RuntimeException('not implemented');
	}

	public function lane(Importance $importance): LinkLane {
		throw new \RuntimeException('not implemented');
	}

	/** @return list<DisplayedLink> Featured enabled, then Company, then Reference. */
	public function visible(): array {
		throw new \RuntimeException('not implemented');
	}

	/**
	 * @return list<DisplayedLink>
	 * $since is a link id. Unknown since → first page, not an error.
	 */
	public function sliceVisible(?string $since, int $limit): array {
		throw new \RuntimeException('not implemented');
	}

	public function find(LinkId $id): ?CompanyLink {
		throw new \RuntimeException('not implemented');
	}

	/**
	 * Match by HttpsUrl::normalized(), first hit in Featured then Company then
	 * Reference. Duplicate hrefs are allowed on Save; import updates the first
	 * only. Existing row keeps id, lane, openMode, icon, enabled, sort; title
	 * updates. New drafts append to Normal, enabled, sort = end, icon none.
	 *
	 * @param list<ImportedDraft> $drafts
	 */
	public function mergeImported(array $drafts, IdIssuer $issuer): self {
		throw new \RuntimeException('not implemented');
	}

	public function maxId(): int {
		throw new \RuntimeException('not implemented');
	}

	/** @return list<string> stored icon file ids still referenced */
	public function storedIconIds(): array {
		throw new \RuntimeException('not implemented');
	}
}

final class LinkTarget {
	private function __construct(
		private readonly string $href,
	) {
	}

	/**
	 * Iframe → $routes->embed($id). Redirect → $link->href()->value().
	 * Tile, list page, and buttons must not read href() for iframe mode.
	 */
	public static function resolve(CompanyLink $link, AppRoutes $routes): self {
		throw new \RuntimeException('not implemented');
	}

	public function href(): string {
		throw new \RuntimeException('not implemented');
	}
}

final class ImportedDraft {
	public function __construct(
		public readonly LinkTitle $title,
		public readonly HttpsUrl $href,
		public readonly OpenMode $openMode,
	) {
	}
}

final class ImportBatch {
	/**
	 * @param list<ImportedDraft> $accepted
	 * @param list<string> $skipped human-readable reasons, already safe to return
	 */
	public function __construct(
		private readonly array $accepted,
		private readonly array $skipped,
	) {
	}

	/** @return list<ImportedDraft> */
	public function accepted(): array {
		throw new \RuntimeException('not implemented');
	}

	/** @return list<string> */
	public function skipped(): array {
		throw new \RuntimeException('not implemented');
	}
}

final class ImportResult {
	public function __construct(
		public readonly Catalog $catalog,
		public readonly ImportBatch $batch,
	) {
	}
}

/**
 * External's admin OCS row shape stays in this class.
 * name→title, url→HttpsUrl, redirect bool→OpenMode. Icons discarded.
 */
final class ExternalSiteImport {
	/**
	 * @param list<mixed> $rawSites
	 */
	public static function parse(array $rawSites): ImportBatch {
		// Illegal rows skip; they do not fail the import.
		throw new \RuntimeException('not implemented');
	}
}

// ---------------------------------------------------------------------------
// Persistence + icons — one deep write surface
// ---------------------------------------------------------------------------

final class IconStore {
	public function __construct(
		private readonly IAppData $appData,
	) {
	}

	public function put(string $filename, string $contents): LinkIcon {
		throw new \RuntimeException('not implemented');
	}

	public function stream(string $fileId): StreamResponse {
		throw new \RuntimeException('not implemented');
	}

	/** @param list<string> $keepIds */
	public function deleteUnused(array $keepIds): void {
		throw new \RuntimeException('not implemented');
	}
}

final class AppRoutes {
	public function __construct(
		private readonly IURLGenerator $urls,
	) {
	}

	public function embed(LinkId $id): string {
		throw new \RuntimeException('not implemented');
	}

	public function listPage(): string {
		throw new \RuntimeException('not implemented');
	}

	public function settings(): string {
		throw new \RuntimeException('not implemented');
	}

	public function icon(LinkIcon $icon): string {
		// none → img/app-dark.svg; bundled → imagePath; stored → our icon route.
		throw new \RuntimeException('not implemented');
	}
}

/**
 * Owns the single lazy IAppConfig blob `catalog` = {version, maxId, featured,
 * normal, reference}. maxId is not a second key — a torn write cannot mint
 * an id without the row, or a row without the counter.
 */
final class LinkCatalog {
	public const CONFIG_KEY = 'catalog';

	public function __construct(
		private readonly IAppConfig $config,
		private readonly IconStore $icons,
	) {
	}

	public function get(): Catalog {
		throw new \RuntimeException('not implemented');
	}

	public function save(Catalog $catalog): Catalog {
		// setValueArray(..., lazy: true), then icons->deleteUnused.
		throw new \RuntimeException('not implemented');
	}

	/** @param array<string, mixed> $payload */
	public function saveFromAdmin(array $payload): Catalog {
		throw new \RuntimeException('not implemented');
	}

	/** @param list<mixed> $rawSites forwarded External admin OCS rows */
	public function importExternal(array $rawSites): ImportResult {
		throw new \RuntimeException('not implemented');
	}
}

// ---------------------------------------------------------------------------
// Dashboard/LinksWidget.php
// ---------------------------------------------------------------------------

final class LinksWidget implements IAPIWidget, IAPIWidgetV2, IIconWidget, IButtonWidget {
	private const TILE_SIZE = 7;

	public function __construct(
		private readonly IL10N $l10n,
		private readonly IGroupManager $groups,
		private readonly LinkCatalog $links,
		private readonly AppRoutes $routes,
	) {
	}

	public function getId(): string {
		return Application::APP_ID;
	}

	public function getTitle(): string {
		throw new \RuntimeException('not implemented');
	}

	public function getOrder(): int {
		return 20;
	}

	public function getIconClass(): string {
		throw new \RuntimeException('not implemented');
	}

	public function getIconUrl(): string {
		throw new \RuntimeException('not implemented');
	}

	public function getUrl(): ?string {
		throw new \RuntimeException('not implemented');
	}

	public function load(): void {
	}

	/** @return list<WidgetItem> */
	public function getItems(string $userId, ?string $since = null, int $limit = 7): array {
		throw new \RuntimeException('not implemented');
	}

	public function getItemsV2(?string $userId, ?string $since = null, int $limit = 7): WidgetItems {
		throw new \RuntimeException('not implemented');
	}

	/** @return list<WidgetButton> */
	public function getWidgetButtons(string $userId): array {
		throw new \RuntimeException('not implemented');
	}

	private function toItem(DisplayedLink $row): WidgetItem {
		throw new \RuntimeException('not implemented');
	}
}

// ---------------------------------------------------------------------------
// Controllers — HTTP adapters. CSRF on mutating OCS. No #[NoAdminRequired]
// on the catalog writes.
// ---------------------------------------------------------------------------

final class APIController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly LinkCatalog $links,
		private readonly IconStore $icons,
	) {
		parent::__construct($appName, $request);
	}

	public function show(): DataResponse {
		throw new \RuntimeException('not implemented');
	}

	public function replace(): DataResponse {
		throw new \RuntimeException('not implemented');
	}

	public function import(): DataResponse {
		throw new \RuntimeException('not implemented');
	}

	public function uploadIcon(): DataResponse {
		throw new \RuntimeException('not implemented');
	}
}

final class PageController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly LinkCatalog $links,
		private readonly AppRoutes $routes,
	) {
		parent::__construct($appName, $request);
	}

	public function index(): TemplateResponse {
		throw new \RuntimeException('not implemented');
	}

	public function embed(int $id): TemplateResponse|RedirectResponse {
		throw new \RuntimeException('not implemented');
	}

	private function frame(CompanyLink $link): TemplateResponse {
		// ContentSecurityPolicy::addAllowedFrameDomain('*'). iframe src = href().
		throw new \RuntimeException('not implemented');
	}
}

final class IconController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IconStore $icons,
	) {
		parent::__construct($appName, $request);
	}

	public function get(string $fileId): StreamResponse {
		throw new \RuntimeException('not implemented');
	}
}

// ---------------------------------------------------------------------------
// Settings (declared in info.xml, not IBootstrap) + uninstall
// ---------------------------------------------------------------------------

final class Section implements IIconSection {
	public function getID(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		throw new \RuntimeException('not implemented');
	}

	public function getPriority(): int {
		throw new \RuntimeException('not implemented');
	}

	public function getIcon(): string {
		throw new \RuntimeException('not implemented');
	}
}

final class Admin implements ISettings {
	public function getForm(): TemplateResponse {
		throw new \RuntimeException('not implemented');
	}

	public function getSection(): string {
		return Application::APP_ID;
	}

	public function getPriority(): int {
		throw new \RuntimeException('not implemented');
	}
}

final class Uninstall implements IRepairStep {
	public function __construct(
		private readonly \OCP\IAppConfig $config,
	) {
	}

	public function getName(): string {
		throw new \RuntimeException('not implemented');
	}

	public function run(IOutput $output): void {
		// $this->config->deleteApp(Application::APP_ID);
		throw new \RuntimeException('not implemented');
	}
}
