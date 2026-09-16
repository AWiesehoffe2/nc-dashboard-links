<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Tests\Unit\Controller;

use OCA\DashboardLinks\Controller\CatalogController;
use OCA\DashboardLinks\Links\Catalog;
use OCA\DashboardLinks\Links\CatalogStore;
use OCA\DashboardLinks\Tests\Support\InMemoryAppConfig;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

final class CatalogControllerTest extends TestCase {
	private const INTRANET_ID = '6d4f0c4e-6a8c-4a0b-9d3a-2f0a1c3b5e7d';
	private const WIKI_ID = 'a1b2c3d4-e5f6-4789-8abc-def012345678';
	private const HANDBOOK_ID = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';

	private CatalogStore $store;
	private CatalogController $controller;

	protected function setUp(): void {
		$this->store = new CatalogStore(new InMemoryAppConfig());
		$this->controller = new CatalogController(
			'dashboard_links',
			$this->createStub(IRequest::class),
			$this->store,
		);
	}

	public function testPutThreeLanesReturnsTitlesAndHexRevision(): void {
		$response = $this->controller->replace(
			$this->store->current()->revision(),
			[$this->laneRow(self::INTRANET_ID, 'Intranet', 'https://intranet.example.com/')],
			[$this->laneRow(self::WIKI_ID, 'Wiki', 'https://wiki.example.com/')],
			[$this->laneRow(self::HANDBOOK_ID, 'Handbook', 'https://handbook.example.com/')],
		);

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		self::assertSame(['Intranet', 'Wiki', 'Handbook'], $this->titles($data));
		self::assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $data['revision']);
	}

	public function testPutRowLevelImportanceIs400(): void {
		$response = $this->controller->replace(
			$this->store->current()->revision(),
			[[
				'id' => self::INTRANET_ID,
				'title' => 'Intranet',
				'href' => 'https://intranet.example.com/',
				'openMode' => 'iframe',
				'icon' => null,
				'enabled' => true,
				'importance' => 'featured',
			]],
		);

		self::assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		self::assertSame(
			[[
				'index' => 0,
				'field' => 'importance',
				'message' => 'importance belongs on the lane, not the row',
			]],
			$response->getData()['errors'],
		);
	}

	public function testPutStaleRevisionIs412WithPreviousTitle(): void {
		$first = $this->controller->replace(
			$this->store->current()->revision(),
			[$this->laneRow(self::INTRANET_ID, 'Intranet', 'https://intranet.example.com/')],
			[$this->laneRow(self::WIKI_ID, 'Wiki', 'https://wiki.example.com/')],
			[$this->laneRow(self::HANDBOOK_ID, 'Handbook', 'https://handbook.example.com/')],
		);
		self::assertSame(Http::STATUS_OK, $first->getStatus());

		$stale = $this->controller->replace(
			'deadbeefdead',
			[$this->laneRow(self::WIKI_ID, 'Wiki', 'https://wiki.example.com/')],
		);

		self::assertSame(Http::STATUS_PRECONDITION_FAILED, $stale->getStatus());
		self::assertSame('Intranet', $this->titles($stale->getData())[0]);
	}

	public function testGetAfterSaveReturnsCatalogRevision(): void {
		$catalog = Catalog::parse([
			'featured' => [$this->laneRow(self::INTRANET_ID, 'Intranet', 'https://intranet.example.com/')],
			'normal' => [$this->laneRow(self::WIKI_ID, 'Wiki', 'https://wiki.example.com/')],
			'reference' => [$this->laneRow(self::HANDBOOK_ID, 'Handbook', 'https://handbook.example.com/')],
		]);
		$this->controller->replace(
			$this->store->current()->revision(),
			[$this->laneRow(self::INTRANET_ID, 'Intranet', 'https://intranet.example.com/')],
			[$this->laneRow(self::WIKI_ID, 'Wiki', 'https://wiki.example.com/')],
			[$this->laneRow(self::HANDBOOK_ID, 'Handbook', 'https://handbook.example.com/')],
		);

		$response = $this->controller->show();

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertSame($catalog->revision(), $response->getData()['revision']);
	}

	/**
	 * @param array{revision: string, featured: list<array<string, mixed>>, normal: list<array<string, mixed>>, reference: list<array<string, mixed>>} $envelope
	 * @return list<string>
	 */
	private function titles(array $envelope): array {
		return [
			...array_column($envelope['featured'], 'title'),
			...array_column($envelope['normal'], 'title'),
			...array_column($envelope['reference'], 'title'),
		];
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
