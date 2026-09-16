<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Tests\Unit\Links;

use OCA\DashboardLinks\Links\Catalog;
use OCA\DashboardLinks\Links\CatalogStore;
use OCA\DashboardLinks\Links\Category;
use OCA\DashboardLinks\Links\CategoryId;
use OCA\DashboardLinks\Links\CompanyLink;
use OCA\DashboardLinks\Links\HttpsUrl;
use OCA\DashboardLinks\Links\Icon;
use OCA\DashboardLinks\Links\InvalidCatalog;
use OCA\DashboardLinks\Links\InvalidLink;
use OCA\DashboardLinks\Links\LinkId;
use OCA\DashboardLinks\Links\LinkUrls;
use OCA\DashboardLinks\Links\OpenMode;
use OCA\DashboardLinks\Links\StaleCatalog;
use OCA\DashboardLinks\Tests\Support\FakeUrlGenerator;
use OCA\DashboardLinks\Tests\Support\InMemoryAppConfig;
use PHPUnit\Framework\TestCase;

final class CatalogTest extends TestCase {
	private const INTRANET_ID = '6d4f0c4e-6a8c-4a0b-9d3a-2f0a1c3b5e7d';
	private const WIKI_ID = 'a1b2c3d4-e5f6-4789-8abc-def012345678';
	private const HANDBOOK_ID = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
	private const DISABLED_ID = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
	private const UNKNOWN_ID = 'dddddddd-dddd-4ddd-8ddd-dddddddddddd';
	private const TOOLS_ID = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';

	public function testParseRejectsLegacyLaneKeys(): void {
		try {
			Catalog::parse([
				'featured' => [$this->row(self::INTRANET_ID, 'Intranet', 'https://intranet.example.com/')],
				'categories' => [],
				'links' => [],
			]);
			self::fail('Catalog::parse accepted featured');
		} catch (InvalidCatalog $e) {
			self::assertSame(
				[[
					'index' => 0,
					'field' => 'categories',
					'message' => 'use categories and links; featured, normal, and reference are not used',
				]],
				$e->errors,
			);
		}
	}

	public function testParseRejectsRowLevelImportance(): void {
		try {
			Catalog::parse([
				'categories' => [],
				'links' => [[
					'id' => self::INTRANET_ID,
					'title' => 'Intranet',
					'href' => 'https://intranet.example.com/',
					'openMode' => 'iframe',
					'icon' => null,
					'enabled' => true,
					'importance' => 'featured',
				]],
			]);
			self::fail('Catalog::parse accepted a row-level importance key');
		} catch (InvalidCatalog $e) {
			self::assertSame(
				[[
					'index' => 0,
					'field' => 'importance',
					'message' => 'importance is not used; assign a category or leave the default list',
				]],
				$e->errors,
			);
		}
	}

	public function testParseKeepsLinkOrder(): void {
		$catalog = Catalog::parse([
			'categories' => [],
			'links' => [
				$this->row(self::HANDBOOK_ID, 'Handbook', 'https://handbook.example.com/'),
				$this->row(self::INTRANET_ID, 'Intranet', 'https://intranet.example.com/'),
				$this->row(self::WIKI_ID, 'Wiki', 'https://wiki.example.com/'),
			],
		]);

		self::assertSame(
			['Handbook', 'Intranet', 'Wiki'],
			array_map(static fn (CompanyLink $link): string => $link->title, $catalog->links()),
		);
	}

	public function testDuplicateIdIsInvalidCatalog(): void {
		try {
			Catalog::parse([
				'categories' => [],
				'links' => [
					$this->row(self::INTRANET_ID, 'Intranet', 'https://intranet.example.com/'),
					$this->row(self::INTRANET_ID, 'Also intranet', 'https://intranet.example.com/hr'),
				],
			]);
			self::fail('Catalog::parse accepted a duplicate id');
		} catch (InvalidCatalog $e) {
			self::assertSame(
				[[
					'index' => 1,
					'field' => 'id',
					'message' => 'duplicate id',
				]],
				$e->errors,
			);
		}
	}

	public function testUnknownCategoryIdIsInvalid(): void {
		try {
			Catalog::parse([
				'categories' => [],
				'links' => [$this->row(self::INTRANET_ID, 'Intranet', 'https://intranet.example.com/', categoryId: self::TOOLS_ID)],
			]);
			self::fail('Catalog::parse accepted an unknown categoryId');
		} catch (InvalidCatalog $e) {
			self::assertSame(
				[[
					'index' => 0,
					'field' => 'categoryId',
					'message' => 'categoryId must match a category',
				]],
				$e->errors,
			);
		}
	}

	public function testRevisionChangesWhenOrderTitleOrCategoryChanges(): void {
		$first = Catalog::parse([
			'categories' => [],
			'links' => [
				$this->row(self::INTRANET_ID, 'Intranet', 'https://intranet.example.com/'),
				$this->row(self::WIKI_ID, 'Wiki', 'https://wiki.example.com/'),
			],
		]);
		$same = Catalog::of(
			[],
			[
				new CompanyLink(
					LinkId::parse(self::INTRANET_ID),
					'Intranet',
					HttpsUrl::parse('https://intranet.example.com/'),
					OpenMode::Iframe,
					null,
					null,
					true,
				),
				new CompanyLink(
					LinkId::parse(self::WIKI_ID),
					'Wiki',
					HttpsUrl::parse('https://wiki.example.com/'),
					OpenMode::Redirect,
					null,
					null,
					true,
				),
			],
		);
		$swapped = Catalog::parse([
			'categories' => [],
			'links' => [
				$this->row(self::WIKI_ID, 'Wiki', 'https://wiki.example.com/'),
				$this->row(self::INTRANET_ID, 'Intranet', 'https://intranet.example.com/'),
			],
		]);
		$renamed = Catalog::parse([
			'categories' => [],
			'links' => [
				$this->row(self::INTRANET_ID, 'Intranet', 'https://intranet.example.com/'),
				$this->row(self::WIKI_ID, 'Handbook', 'https://wiki.example.com/'),
			],
		]);
		$withCategory = Catalog::parse([
			'categories' => [['id' => self::TOOLS_ID, 'title' => 'Tools']],
			'links' => [
				$this->row(self::INTRANET_ID, 'Intranet', 'https://intranet.example.com/', categoryId: self::TOOLS_ID),
				$this->row(self::WIKI_ID, 'Wiki', 'https://wiki.example.com/'),
			],
		]);
		$renamedCategory = Catalog::parse([
			'categories' => [['id' => self::TOOLS_ID, 'title' => 'Time']],
			'links' => [
				$this->row(self::INTRANET_ID, 'Intranet', 'https://intranet.example.com/', categoryId: self::TOOLS_ID),
				$this->row(self::WIKI_ID, 'Wiki', 'https://wiki.example.com/'),
			],
		]);

		self::assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $first->revision());
		self::assertSame($first->revision(), $same->revision());
		self::assertNotSame($first->revision(), $swapped->revision());
		self::assertNotSame($first->revision(), $renamed->revision());
		self::assertNotSame($first->revision(), $withCategory->revision());
		self::assertNotSame($withCategory->revision(), $renamedCategory->revision());
	}

	public function testReplaceSameContentIsNoOpEvenWithStaleRevision(): void {
		$config = new InMemoryAppConfig();
		$store = new CatalogStore($config);
		$catalog = Catalog::parse([
			'categories' => [],
			'links' => [$this->row(self::INTRANET_ID, 'Intranet', 'https://intranet.example.com/')],
		]);

		$written = $store->replace($catalog, $store->current()->revision());
		self::assertSame(1, $config->arrayWrites);
		self::assertSame($catalog->revision(), $written->revision());

		$again = $store->replace($catalog, 'deadbeefdead');
		self::assertSame(1, $config->arrayWrites);
		self::assertSame($catalog->revision(), $again->revision());
	}

	public function testReplaceDifferentContentWithStaleRevisionThrowsStaleCatalog(): void {
		$config = new InMemoryAppConfig();
		$store = new CatalogStore($config);
		$first = Catalog::parse([
			'categories' => [],
			'links' => [$this->row(self::INTRANET_ID, 'Intranet', 'https://intranet.example.com/')],
		]);
		$second = Catalog::parse([
			'categories' => [],
			'links' => [$this->row(self::WIKI_ID, 'Wiki', 'https://wiki.example.com/')],
		]);
		$store->replace($first, $store->current()->revision());

		try {
			$store->replace($second, 'deadbeefdead');
			self::fail('CatalogStore::replace accepted a stale revision');
		} catch (StaleCatalog $e) {
			self::assertSame($first->revision(), $e->current->revision());
			self::assertSame('Intranet', $e->current->links()[0]->title);
		}
	}

	public function testSchema1MigratesToDefaultListInFeaturedThenNormalThenReferenceOrder(): void {
		$config = new InMemoryAppConfig();
		$config->setValueArray('dashboard_links', 'catalog', [
			'schema' => 1,
			'links' => [
				$this->legacyRow(self::WIKI_ID, 'Wiki', 'https://wiki.example.com/', 'normal'),
				$this->legacyRow(self::HANDBOOK_ID, 'Handbook', 'https://handbook.example.com/', 'reference'),
				$this->legacyRow(self::INTRANET_ID, 'Intranet', 'https://intranet.example.com/', 'featured'),
			],
		], true);
		$store = new CatalogStore($config);
		$writesAfterSeed = $config->arrayWrites;

		$current = $store->current();

		self::assertSame($writesAfterSeed, $config->arrayWrites);
		self::assertSame(
			['Intranet', 'Wiki', 'Handbook'],
			array_map(static fn (CompanyLink $link): string => $link->title, $current->links()),
		);
		self::assertNull($current->links()[0]->categoryId);
		self::assertSame([], $current->categories());

		$store->replace($current, $current->revision());
		self::assertSame($writesAfterSeed + 1, $config->arrayWrites);
		$stored = $config->getValueArray('dashboard_links', 'catalog', [], true);
		self::assertSame(2, $stored['schema']);
		self::assertArrayNotHasKey('importance', $stored['links'][0]);
	}

	public function testVisibleDropsDisabled(): void {
		$catalog = Catalog::parse([
			'categories' => [],
			'links' => [
				$this->row(self::INTRANET_ID, 'Intranet', 'https://intranet.example.com/'),
				$this->row(self::DISABLED_ID, 'Hidden', 'https://hidden.example.com/', enabled: false),
				$this->row(self::WIKI_ID, 'Wiki', 'https://wiki.example.com/'),
			],
		]);

		self::assertSame(
			['Intranet', 'Wiki'],
			array_map(static fn (CompanyLink $link): string => $link->title, $catalog->visible()->links()),
		);
	}

	public function testAfterUnknownReturnsFullVisibleList(): void {
		$catalog = Catalog::parse([
			'categories' => [],
			'links' => [
				$this->row(self::INTRANET_ID, 'Intranet', 'https://intranet.example.com/'),
				$this->row(self::WIKI_ID, 'Wiki', 'https://wiki.example.com/'),
			],
		]);
		$visible = $catalog->visible();

		self::assertSame(
			[self::INTRANET_ID, self::WIKI_ID],
			array_map(static fn (CompanyLink $link): string => (string)$link->id, $visible->after(LinkId::parse(self::UNKNOWN_ID))->links()),
		);
	}

	public function testSectionsWithoutCategoriesHaveEmptyLabel(): void {
		$catalog = Catalog::parse([
			'categories' => [],
			'links' => [$this->row(self::INTRANET_ID, 'Intranet', 'https://intranet.example.com/')],
		]);

		$sections = $catalog->sections('Uncategorized');

		self::assertCount(1, $sections);
		self::assertSame('', $sections[0]['label']);
		self::assertSame('Intranet', $sections[0]['links']->links()[0]->title);
	}

	public function testSectionsWithCategoriesPutUncategorizedFirst(): void {
		$catalog = Catalog::parse([
			'categories' => [
				['id' => self::TOOLS_ID, 'title' => 'Tools'],
			],
			'links' => [
				$this->row(self::INTRANET_ID, 'Intranet', 'https://intranet.example.com/', categoryId: self::TOOLS_ID),
				$this->row(self::WIKI_ID, 'Wiki', 'https://wiki.example.com/'),
			],
		]);

		$sections = $catalog->sections('Uncategorized');

		self::assertSame(['Uncategorized', 'Tools'], array_column($sections, 'label'));
		self::assertSame('Wiki', $sections[0]['links']->links()[0]->title);
		self::assertSame('Intranet', $sections[1]['links']->links()[0]->title);
	}

	public function testHttpsUrlRejectsHttpAndUserinfo(): void {
		try {
			HttpsUrl::parse('http://intranet.example.com');
			self::fail('HttpsUrl::parse accepted http');
		} catch (InvalidLink $e) {
			self::assertSame('href', $e->field);
		}

		try {
			HttpsUrl::parse('https://user:secret@intranet.example.com');
			self::fail('HttpsUrl::parse accepted userinfo');
		} catch (InvalidLink $e) {
			self::assertSame('href', $e->field);
		}

		$url = HttpsUrl::parse('https://intranet.example.com/path');
		self::assertSame('intranet.example.com', $url->host());
		self::assertSame('https://intranet.example.com/path', $url->normalized());
	}

	public function testIconParseRejectsUrl(): void {
		try {
			Icon::parse('https://cdn.example.com/logo.png');
			self::fail('Icon::parse accepted a URL');
		} catch (InvalidLink $e) {
			self::assertSame('icon', $e->field);
		}

		self::assertSame('0123456789abcdef.png', (string)Icon::parse('0123456789abcdef.png'));
	}

	public function testIconParseAcceptsAllowlistedCoreIcon(): void {
		$icon = Icon::parse('core:actions/timezone.svg');

		self::assertTrue($icon->isCore());
		self::assertSame('actions/timezone.svg', $icon->corePath());
		self::assertSame(
			'https://cloud.example.test/apps/core/img/actions/timezone.svg',
			(new LinkUrls(new FakeUrlGenerator()))->iconUrl(new CompanyLink(
				LinkId::parse(self::INTRANET_ID),
				'Time',
				HttpsUrl::parse('https://my.clockodo.com/'),
				OpenMode::Iframe,
				$icon,
				null,
				true,
			)),
		);
	}

	public function testIconParseRejectsUnknownCoreIcon(): void {
		try {
			Icon::parse('core:actions/delete.svg');
			self::fail('Icon::parse accepted an unknown core icon');
		} catch (InvalidLink $e) {
			self::assertSame('icon', $e->field);
		}
	}

	public function testCategoryParseRejectsEmptyTitle(): void {
		try {
			Category::parse(['id' => self::TOOLS_ID, 'title' => '   ']);
			self::fail('Category::parse accepted a blank title');
		} catch (InvalidLink $e) {
			self::assertSame('title', $e->field);
		}

		self::assertSame(self::TOOLS_ID, (string)CategoryId::parse(self::TOOLS_ID));
	}

	/**
	 * @return array{id: string, title: string, href: string, openMode: string, icon: null, categoryId: ?string, enabled: bool}
	 */
	private function row(string $id, string $title, string $href, bool $enabled = true, ?string $categoryId = null): array {
		return [
			'id' => $id,
			'title' => $title,
			'href' => $href,
			'openMode' => $title === 'Intranet' || $title === 'Time' ? 'iframe' : 'redirect',
			'icon' => null,
			'categoryId' => $categoryId,
			'enabled' => $enabled,
		];
	}

	/**
	 * @return array{id: string, title: string, href: string, openMode: string, icon: null, enabled: bool, importance: string}
	 */
	private function legacyRow(string $id, string $title, string $href, string $importance): array {
		return [
			'id' => $id,
			'title' => $title,
			'href' => $href,
			'openMode' => $title === 'Intranet' ? 'iframe' : 'redirect',
			'icon' => null,
			'enabled' => true,
			'importance' => $importance,
		];
	}
}
