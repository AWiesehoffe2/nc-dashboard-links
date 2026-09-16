<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Andrew Iesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Dashboard;

use OCA\DashboardLinks\AppInfo\Application;
use OCA\DashboardLinks\Links\CatalogStore;
use OCA\DashboardLinks\Links\LinkId;
use OCA\DashboardLinks\Links\LinkPresenter;
use OCA\DashboardLinks\Links\LinkUrls;
use OCA\DashboardLinks\Links\LinkView;
use OCP\Dashboard\IAPIWidget;
use OCP\Dashboard\IAPIWidgetV2;
use OCP\Dashboard\IButtonWidget;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\Model\WidgetButton;
use OCP\Dashboard\Model\WidgetItem;
use OCP\Dashboard\Model\WidgetItems;
use OCP\IGroupManager;
use OCP\IL10N;

final class LinksWidget implements IAPIWidget, IAPIWidgetV2, IIconWidget, IButtonWidget {
	public const ID = Application::APP_ID;
	public const ORDER = 20;

	private const WEB_TILE_LIMIT = 7;

	public function __construct(
		private readonly IL10N $l10n,
		private readonly IGroupManager $groupManager,
		private readonly CatalogStore $store,
		private readonly LinkPresenter $presenter,
		private readonly LinkUrls $urls,
	) {
	}

	#[\Override]
	public function getId(): string {
		return self::ID;
	}

	#[\Override]
	public function getTitle(): string {
		return $this->l10n->t('Company links');
	}

	#[\Override]
	public function getOrder(): int {
		return self::ORDER;
	}

	#[\Override]
	public function getIconClass(): string {
		return 'icon-dashboard_links';
	}

	#[\Override]
	public function getIconUrl(): string {
		return $this->urls->defaultIconUrl();
	}

	#[\Override]
	public function getUrl(): ?string {
		return $this->urls->allLinksUrl();
	}

	#[\Override]
	public function load(): void {
	}

	/** @return list<WidgetItem> */
	#[\Override]
	public function getItems(string $userId, ?string $since = null, int $limit = 7): array {
		return $this->page($since, $limit);
	}

	#[\Override]
	public function getItemsV2(string $userId, ?string $since = null, int $limit = 7): WidgetItems {
		return new WidgetItems(
			$this->page($since, $limit),
			$this->l10n->t('No company links configured yet'),
		);
	}

	/** @return list<WidgetButton> */
	#[\Override]
	public function getWidgetButtons(string $userId): array {
		$visible = $this->store->current()->visible();
		if (count($visible) > self::WEB_TILE_LIMIT) {
			return [
				new WidgetButton(
					WidgetButton::TYPE_MORE,
					$this->urls->allLinksUrl(),
					$this->l10n->t('All links'),
				),
			];
		}
		if (count($visible) === 0 && $this->groupManager->isAdmin($userId)) {
			return [
				new WidgetButton(
					WidgetButton::TYPE_SETUP,
					$this->urls->settingsUrl(),
					$this->l10n->t('Configure'),
				),
			];
		}

		return [];
	}

	/** @return list<WidgetItem> */
	private function page(?string $since, int $limit): array {
		$views = $this->presenter->views(
			$this->store->current()->visible()->after(LinkId::tryParse($since))->take($limit),
		);

		return array_map(
			static fn (LinkView $view): WidgetItem => new WidgetItem(
				$view->title,
				$view->subtitle,
				$view->href,
				$view->iconUrl,
				$view->id,
				$view->overlayIconUrl,
			),
			$views,
		);
	}
}
