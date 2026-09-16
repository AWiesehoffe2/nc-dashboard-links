<?php

declare(strict_types=1);

namespace OCA\DashboardLinks;

use JsonSerializable;
use OCP\AppFramework\App;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Dashboard\IAPIWidget;
use OCP\Dashboard\IAPIWidgetV2;
use OCP\Dashboard\IButtonWidget;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\WidgetButton;
use OCP\Dashboard\WidgetItem;
use OCP\Dashboard\WidgetItems;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Security\ISecureRandom;
use RuntimeException;
use Stringable;

enum Importance: string {
	case Featured = 'featured';
	case Normal = 'normal';
	case Reference = 'reference';
}

enum Visibility: string {
	case Enabled = 'enabled';
	case Disabled = 'disabled';
}

final readonly class HttpsUrl implements Stringable, JsonSerializable {
	private function __construct(public string $value) {
	}

	public static function parse(string $value): self {
		throw new RuntimeException('not implemented');
	}

	public function origin(): string {
		throw new RuntimeException('not implemented');
	}

	public function __toString(): string {
		throw new RuntimeException('not implemented');
	}

	public function jsonSerialize(): string {
		throw new RuntimeException('not implemented');
	}
}

final readonly class LinkId implements Stringable, JsonSerializable {
	private function __construct(public string $value) {
	}

	public static function parse(string $value): self {
		throw new RuntimeException('not implemented');
	}

	public function __toString(): string {
		throw new RuntimeException('not implemented');
	}

	public function jsonSerialize(): string {
		throw new RuntimeException('not implemented');
	}
}

final readonly class CatalogRevision implements JsonSerializable {
	private function __construct(public int $value) {
	}

	public static function initial(): self {
		throw new RuntimeException('not implemented');
	}

	public static function parse(int $value): self {
		throw new RuntimeException('not implemented');
	}

	public function next(): self {
		throw new RuntimeException('not implemented');
	}

	public function jsonSerialize(): int {
		throw new RuntimeException('not implemented');
	}
}

final readonly class SortPosition implements JsonSerializable {
	private function __construct(public int $value) {
	}

	public static function parse(int $value): self {
		throw new RuntimeException('not implemented');
	}

	public function jsonSerialize(): int {
		throw new RuntimeException('not implemented');
	}
}

final readonly class LocalIcon implements JsonSerializable {
	private function __construct(public string $token) {
	}

	public static function parse(string $token): self {
		throw new RuntimeException('not implemented');
	}

	public function jsonSerialize(): string {
		throw new RuntimeException('not implemented');
	}
}

interface LinkTarget extends JsonSerializable {
	public function href(): HttpsUrl;
}

final readonly class RedirectTarget implements LinkTarget {
	public function __construct(private HttpsUrl $url) {
	}

	public function href(): HttpsUrl {
		throw new RuntimeException('not implemented');
	}

	/**
	 * @return array{openMode: 'redirect', href: string}
	 */
	public function jsonSerialize(): array {
		throw new RuntimeException('not implemented');
	}
}

final readonly class IframeTarget implements LinkTarget {
	public function __construct(private HttpsUrl $url) {
	}

	public function href(): HttpsUrl {
		throw new RuntimeException('not implemented');
	}

	/**
	 * @return array{openMode: 'iframe', href: string}
	 */
	public function jsonSerialize(): array {
		throw new RuntimeException('not implemented');
	}
}

final readonly class CompanyLink implements JsonSerializable {
	public function __construct(
		public LinkId $id,
		public string $title,
		public Importance $importance,
		public IframeTarget|RedirectTarget $target,
		public ?LocalIcon $icon,
		public Visibility $visibility,
		public SortPosition $sort,
	) {
	}

	/**
	 * @return array<string, bool|int|string|null>
	 */
	public function jsonSerialize(): array {
		throw new RuntimeException('not implemented');
	}
}

final readonly class SubmittedLink {
	public function __construct(
		public ?LinkId $id,
		public string $title,
		public Importance $importance,
		public IframeTarget|RedirectTarget $target,
		public ?LocalIcon $icon,
		public Visibility $visibility,
		public SortPosition $sort,
	) {
	}
}

final readonly class CatalogReplacement {
	/**
	 * @param list<SubmittedLink> $links
	 */
	public function __construct(
		public CatalogRevision $expectedRevision,
		public array $links,
	) {
	}
}

final readonly class CatalogSnapshot implements JsonSerializable {
	/**
	 * @param list<CompanyLink> $links
	 */
	public function __construct(
		public CatalogRevision $revision,
		public array $links,
	) {
	}

	/**
	 * @return array{revision: int, links: list<array<string, bool|int|string|null>>}
	 */
	public function jsonSerialize(): array {
		throw new RuntimeException('not implemented');
	}
}

final readonly class WidgetQuery {
	private function __construct(
		public ?LinkId $after,
		public int $limit,
	) {
	}

	public static function first(int $limit): self {
		throw new RuntimeException('not implemented');
	}

	public static function fromApi(?string $since, int $limit): self {
		throw new RuntimeException('not implemented');
	}
}

final readonly class WidgetSlice {
	/**
	 * @param list<WidgetItem> $items
	 */
	public function __construct(
		public array $items,
		public bool $hasMore,
	) {
	}
}

final readonly class ResolvedLink {
	public function __construct(
		public LinkId $id,
		public string $title,
		public Importance $importance,
		public HttpsUrl $destination,
		public ?string $iconUrl,
	) {
	}
}

interface CatalogAdministration {
	public function snapshot(): CatalogSnapshot;

	/**
	 * @throws StaleCatalogRevision
	 */
	public function replace(CatalogReplacement $replacement): CatalogSnapshot;
}

interface DashboardFeed {
	public function slice(WidgetQuery $query): WidgetSlice;
}

interface LinkNavigation {
	/**
	 * @return list<ResolvedLink>
	 */
	public function allEnabled(): array;

	/**
	 * @throws LinkNotFound
	 * @throws LinkCannotBeFramed
	 */
	public function iframeSource(LinkId $id): HttpsUrl;
}

final class AppConfigLinkCatalog implements
	CatalogAdministration,
	DashboardFeed,
	LinkNavigation {
	private const CATALOG_KEY = 'catalog';
	private const SCHEMA_VERSION = 1;

	public function __construct(
		private readonly IAppConfig $config,
		private readonly ISecureRandom $random,
		private readonly IURLGenerator $urlGenerator,
	) {
	}

	public function snapshot(): CatalogSnapshot {
		throw new RuntimeException('not implemented');
	}

	public function replace(CatalogReplacement $replacement): CatalogSnapshot {
		throw new RuntimeException('not implemented');
	}

	public function slice(WidgetQuery $query): WidgetSlice {
		throw new RuntimeException('not implemented');
	}

	public function allEnabled(): array {
		throw new RuntimeException('not implemented');
	}

	public function iframeSource(LinkId $id): HttpsUrl {
		throw new RuntimeException('not implemented');
	}

	/**
	 * @param array<string, mixed> $stored
	 */
	private function parseEnvelope(array $stored): CatalogSnapshot {
		throw new RuntimeException('not implemented');
	}

	/**
	 * @return array<string, mixed>
	 */
	private function encodeEnvelope(CatalogSnapshot $snapshot): array {
		throw new RuntimeException('not implemented');
	}

	/**
	 * @param list<CompanyLink> $links
	 * @return list<CompanyLink>
	 */
	private function enabledInDisplayOrder(array $links): array {
		throw new RuntimeException('not implemented');
	}

	private function materialize(SubmittedLink $link): CompanyLink {
		throw new RuntimeException('not implemented');
	}

	private function toWidgetItem(CompanyLink $link): WidgetItem {
		throw new RuntimeException('not implemented');
	}

	private function resolveDestination(CompanyLink $link): HttpsUrl {
		throw new RuntimeException('not implemented');
	}

	private function resolveIcon(?LocalIcon $icon): string {
		throw new RuntimeException('not implemented');
	}
}

final class OcsCatalogRequestParser {
	public function replacement(IRequest $request): CatalogReplacement {
		throw new RuntimeException('not implemented');
	}

	/**
	 * @param array<string, mixed> $value
	 */
	private function submittedLink(array $value): SubmittedLink {
		throw new RuntimeException('not implemented');
	}

	/**
	 * @param array<string, mixed> $value
	 */
	private function target(array $value): IframeTarget|RedirectTarget {
		throw new RuntimeException('not implemented');
	}
}

final class OcsResponseMapper {
	/**
	 * @return array<string, mixed>
	 */
	public function adminSnapshot(CatalogSnapshot $snapshot): array {
		throw new RuntimeException('not implemented');
	}
}

final class Application extends App implements IBootstrap {
	public const APP_ID = 'dashboard_links';

	public function __construct() {
		throw new RuntimeException('not implemented');
	}

	public function register(IRegistrationContext $context): void {
		throw new RuntimeException('not implemented');
	}

	public function boot(IBootContext $context): void {
	}
}

final class LinksWidget implements
	IAPIWidget,
	IAPIWidgetV2,
	IIconWidget,
	IButtonWidget {
	private const WEB_LIMIT = 7;

	public function __construct(
		private readonly DashboardFeed $feed,
		private readonly IL10N $l10n,
		private readonly IURLGenerator $urlGenerator,
	) {
	}

	public function getId(): string {
		throw new RuntimeException('not implemented');
	}

	public function getTitle(): string {
		throw new RuntimeException('not implemented');
	}

	public function getOrder(): int {
		throw new RuntimeException('not implemented');
	}

	public function getIconClass(): string {
		throw new RuntimeException('not implemented');
	}

	public function getIconUrl(): string {
		throw new RuntimeException('not implemented');
	}

	public function getUrl(): string {
		throw new RuntimeException('not implemented');
	}

	public function load(): void {
	}

	/**
	 * @return list<WidgetItem>
	 */
	public function getItems(
		string $userId,
		?string $since = null,
		int $limit = 7,
	): array {
		throw new RuntimeException('not implemented');
	}

	public function getItemsV2(
		string $userId,
		?string $since = null,
		int $limit = 7,
	): WidgetItems {
		throw new RuntimeException('not implemented');
	}

	/**
	 * @return list<WidgetButton>
	 */
	public function getWidgetButtons(string $userId): array {
		throw new RuntimeException('not implemented');
	}
}

final class AdminLinksController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly CatalogAdministration $catalog,
		private readonly OcsCatalogRequestParser $requestParser,
		private readonly OcsResponseMapper $responseMapper,
	) {
		throw new RuntimeException('not implemented');
	}

	public function index(): DataResponse {
		throw new RuntimeException('not implemented');
	}

	public function replace(): DataResponse {
		throw new RuntimeException('not implemented');
	}
}

final class PageController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly LinkNavigation $navigation,
	) {
		throw new RuntimeException('not implemented');
	}

	public function index(): TemplateResponse {
		throw new RuntimeException('not implemented');
	}

	public function embed(string $id): TemplateResponse {
		throw new RuntimeException('not implemented');
	}
}

final class StaleCatalogRevision extends RuntimeException {
}

final class InvalidCatalogInput extends RuntimeException {
}

final class CorruptStoredCatalog extends RuntimeException {
}

final class LinkNotFound extends RuntimeException {
}

final class LinkCannotBeFramed extends RuntimeException {
}
