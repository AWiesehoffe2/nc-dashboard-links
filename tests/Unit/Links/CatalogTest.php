<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Tests\Unit\Links;

use OCA\DashboardLinks\Links\Catalog;
use OCA\DashboardLinks\Links\CatalogStore;
use OCA\DashboardLinks\Links\CompanyLink;
use OCA\DashboardLinks\Links\HttpsUrl;
use OCA\DashboardLinks\Links\Icon;
use OCA\DashboardLinks\Links\Importance;
use OCA\DashboardLinks\Links\InvalidCatalog;
use OCA\DashboardLinks\Links\InvalidLink;
use OCA\DashboardLinks\Links\LinkId;
use OCA\DashboardLinks\Links\OpenMode;
use OCA\DashboardLinks\Links\StaleCatalog;
use OCA\DashboardLinks\Tests\Support\InMemoryAppConfig;
use PHPUnit\Framework\TestCase;

final class CatalogTest extends TestCase {
	private const INTRANET_ID = '6d4f0c4e-6a8c-4a0b-9d3a-2f0a1c3b5e7d';
	private const WIKI_ID = 'a1b2c3d4-e5f6-4789-8abc-def012345678';
	private const HANDBOOK_ID = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
	private const DISABLED_ID = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
	private const UNKNOWN_ID = 'dddddddd-dddd-4ddd-8ddd-dddddddddddd';

	public function testParseThreeLanesRejectsRowLevelImportance(): void {
		try {
			Catalog::parse([
				'featured' => [[
					'id' => self::INTRANET_ID,
					'title' => 'Intranet',
					'href' => 'https://intranet.example.com/',
					'openMode' => 'iframe',
					'icon' => null,
					'enabled' => true,
					'importance' => 'featured',
				]],
				'normal' => [],
				'reference' => [],
			]);
			self::fail('Catalog::parse accepted a row-level importance key');
		} catch (InvalidCatalog $e) {
			self::assertSame(
				[[
					'index' => 0,
					'field' => 'importance',
					'message' => 'importance belongs on the lane, not the row',
				]],
				$e->errors,
			);
		}
	}

	public function testParseThreeLanesCanonicalOrderFeaturedThenNormalThenReference(): void {
		$catalog = Catalog::parse([
			'reference' => [$this->laneRow(self::HANDBOOK_ID, 'Handbook', 'https://handbook.example.com/')],
			'featured' => [$this->laneRow(self::INTRANET_ID, 'Intranet', 'https://intranet.example.com/')],
			'normal' => [$this->laneRow(self::WIKI_ID, 'Wiki', 'https://wiki.example.com/')],
		]);

		self::assertSame(
			['Intranet', 'Wiki', 'Handbook'],
			array_map(static fn (CompanyLink $link): string => $link->title, $catalog->links()),
		);
		self::assertSame(
			['featured', 'normal', 'reference'],
			array_map(static fn (CompanyLink $link): string => $link->importance->value, $catalog->links()),
		);
	}

	public function testDuplicateIdIsInvalidCatalog(): void {
		try {
			Catalog::parse([
				'featured' => [
					$this->laneRow(self::INTRANET_ID, 'Intranet', 'https://intranet.example.com/'),
					$this->laneRow(self::INTRANET_ID, 'Also intranet', 'https://intranet.example.com/hr'),
				],
				'normal' => [],
				'reference' => [],
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

	public function testRevisionEqualIffLinksEqual(): void {
		$first = Catalog::parse([
			'featured' => [$this->laneRow(self::INTRANET_ID, 'Intranet', 'https://intranet.example.com/')],
			'normal' => [$this->laneRow(self::WIKI_ID, 'Wiki', 'https://wiki.example.com/')],
			'reference' => [],
		]);
		$reordered = Catalog::of(
			new CompanyLink(
				LinkId::parse(self::WIKI_ID),
				'Wiki',
				HttpsUrl::parse('https://wiki.example.com/'),
				Importance::Normal,
				OpenMode::Redirect,
				null,
				true,
			),
			new CompanyLink(
				LinkId::parse(self::INTRANET_ID),
				'Intranet',
				HttpsUrl::parse('https://intranet.example.com/'),
				Importance::Featured,
				OpenMode::Iframe,
				null,
				true,
			),
		);
		$changed = Catalog::parse([
			'featured' => [$this->laneRow(self::INTRANET_ID, 'Intranet', 'https://intranet.example.com/')],
			'normal' => [$this->laneRow(self::WIKI_ID, 'Handbook', 'https://wiki.example.com/')],
			'reference' => [],
		]);

		self::assertSame('e4da948788ba', $first->revision());
		self::assertSame($first->revision(), $reordered->revision());
		self::assertSame('014e5c5dc94f', $changed->revision());
		self::assertNotSame($first->revision(), $changed->revision());
	}

	public function testReplaceSameContentIsNoOpEvenWithStaleRevision(): void {
		$config = new InMemoryAppConfig();
		$store = new CatalogStore($config);
		$catalog = Catalog::parse([
			'featured' => [$this->laneRow(self::INTRANET_ID, 'Intranet', 'https://intranet.example.com/')],
			'normal' => [],
			'reference' => [],
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
			'featured' => [$this->laneRow(self::INTRANET_ID, 'Intranet', 'https://intranet.example.com/')],
			'normal' => [],
			'reference' => [],
		]);
		$second = Catalog::parse([
			'featured' => [$this->laneRow(self::WIKI_ID, 'Wiki', 'https://wiki.example.com/')],
			'normal' => [],
			'reference' => [],
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

	public function testVisibleDropsDisabled(): void {
		$catalog = Catalog::parse([
			'featured' => [
				$this->laneRow(self::INTRANET_ID, 'Intranet', 'https://intranet.example.com/'),
				$this->laneRow(self::DISABLED_ID, 'Hidden', 'https://hidden.example.com/', enabled: false),
			],
			'normal' => [$this->laneRow(self::WIKI_ID, 'Wiki', 'https://wiki.example.com/')],
			'reference' => [],
		]);

		self::assertSame(
			['Intranet', 'Wiki'],
			array_map(static fn (CompanyLink $link): string => $link->title, $catalog->visible()->links()),
		);
	}

	public function testAfterUnknownReturnsFullVisibleList(): void {
		$catalog = Catalog::parse([
			'featured' => [$this->laneRow(self::INTRANET_ID, 'Intranet', 'https://intranet.example.com/')],
			'normal' => [$this->laneRow(self::WIKI_ID, 'Wiki', 'https://wiki.example.com/')],
			'reference' => [],
		]);
		$visible = $catalog->visible();

		self::assertSame(
			[self::INTRANET_ID, self::WIKI_ID],
			array_map(static fn (CompanyLink $link): string => (string)$link->id, $visible->after(LinkId::parse(self::UNKNOWN_ID))->links()),
		);
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

	/**
	 * @return array{id: string, title: string, href: string, openMode: string, icon: null, enabled: bool}
	 */
	private function laneRow(string $id, string $title, string $href, bool $enabled = true): array {
		return [
			'id' => $id,
			'title' => $title,
			'href' => $href,
			'openMode' => $title === 'Intranet' ? 'iframe' : 'redirect',
			'icon' => null,
			'enabled' => $enabled,
		];
	}
}
