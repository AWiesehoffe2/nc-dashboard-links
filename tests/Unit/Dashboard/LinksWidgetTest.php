<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Andrew Iesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Tests\Unit\Dashboard;

use OCA\DashboardLinks\Dashboard\LinksWidget;
use OCA\DashboardLinks\Links\Catalog;
use OCA\DashboardLinks\Links\CatalogStore;
use OCA\DashboardLinks\Links\LinkPresenter;
use OCA\DashboardLinks\Links\LinkUrls;
use OCA\DashboardLinks\Tests\Support\FakeUrlGenerator;
use OCA\DashboardLinks\Tests\Support\IdentityL10N;
use OCA\DashboardLinks\Tests\Support\InMemoryAppConfig;
use OCP\Dashboard\Model\WidgetButton;
use OCP\IGroupManager;
use PHPUnit\Framework\TestCase;

final class LinksWidgetTest extends TestCase {
	private const INTRANET_ID = '6d4f0c4e-6a8c-4a0b-9d3a-2f0a1c3b5e7d';
	private const WIKI_ID = 'a1b2c3d4-e5f6-4789-8abc-def012345678';
	private const HANDBOOK_ID = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';

	private CatalogStore $store;
	private LinksWidget $widget;

	protected function setUp(): void {
		$this->store = new CatalogStore(new InMemoryAppConfig());
		$urls = new LinkUrls(new FakeUrlGenerator());
		$groups = $this->createStub(IGroupManager::class);
		$groups->method('isAdmin')->willReturnCallback(
			static fn (string $userId): bool => $userId === 'admin',
		);
		$this->widget = new LinksWidget(
			new IdentityL10N(),
			$groups,
			$this->store,
			new LinkPresenter($urls),
			$urls,
		);
	}

	public function testGetItemsV2ReturnsSevenTitlesFeaturedFirst(): void {
		$this->replaceEightVisible();

		$items = $this->widget->getItemsV2('alice', null, 7)->getItems();

		self::assertCount(7, $items);
		self::assertSame(
			['Intranet', 'Wiki', 'Docs', 'Chat', 'HR', 'Handbook', 'Legal'],
			array_map(static fn ($item): string => $item->getTitle(), $items),
		);
		self::assertSame('Intranet', $items[0]->getTitle());
	}

	public function testGetWidgetButtonsNonAdminWithEightVisibleIsMore(): void {
		$this->replaceEightVisible();

		$buttons = $this->widget->getWidgetButtons('alice');

		self::assertCount(1, $buttons);
		self::assertSame(WidgetButton::TYPE_MORE, $buttons[0]->getType());
	}

	public function testGetWidgetButtonsEmptyCatalogAdminIsSetup(): void {
		$buttons = $this->widget->getWidgetButtons('admin');

		self::assertCount(1, $buttons);
		self::assertSame(WidgetButton::TYPE_SETUP, $buttons[0]->getType());
	}

	public function testGetWidgetButtonsEmptyCatalogNonAdminIsNone(): void {
		self::assertSame([], $this->widget->getWidgetButtons('alice'));
	}

	public function testItemLinkUsesOpenRouteAndId(): void {
		$this->replaceEightVisible();

		$link = $this->widget->getItemsV2('alice', null, 7)->getItems()[0]->getLink();

		self::assertStringContainsString('/open/', $link);
		self::assertStringContainsString(self::INTRANET_ID, $link);
		self::assertStringNotContainsString('https://intranet.example.com', $link);
	}

	private function replaceEightVisible(): void {
		$catalog = Catalog::parse([
			'featured' => [$this->laneRow(self::INTRANET_ID, 'Intranet', 'https://intranet.example.com/')],
			'normal' => [
				$this->laneRow(self::WIKI_ID, 'Wiki', 'https://wiki.example.com/'),
				$this->laneRow('11111111-1111-4111-8111-111111111111', 'Docs', 'https://docs.example.com/'),
				$this->laneRow('22222222-2222-4222-8222-222222222222', 'Chat', 'https://chat.example.com/'),
				$this->laneRow('33333333-3333-4333-8333-333333333333', 'HR', 'https://hr.example.com/'),
			],
			'reference' => [
				$this->laneRow(self::HANDBOOK_ID, 'Handbook', 'https://handbook.example.com/'),
				$this->laneRow('44444444-4444-4444-8444-444444444444', 'Legal', 'https://legal.example.com/'),
				$this->laneRow('55555555-5555-4555-8555-555555555555', 'Status', 'https://status.example.com/'),
			],
		]);
		$this->store->replace($catalog, $this->store->current()->revision());
	}

	/**
	 * @return array{id: string, title: string, href: string, openMode: string, icon: null, enabled: bool}
	 */
	private function laneRow(string $id, string $title, string $href): array {
		return [
			'id' => $id,
			'title' => $title,
			'href' => $href,
			'openMode' => $title === 'Intranet' ? 'iframe' : 'redirect',
			'icon' => null,
			'enabled' => true,
		];
	}
}
