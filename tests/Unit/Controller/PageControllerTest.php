<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Tests\Unit\Controller;

use OCA\DashboardLinks\Controller\PageController;
use OCA\DashboardLinks\Links\Catalog;
use OCA\DashboardLinks\Links\CatalogStore;
use OCA\DashboardLinks\Links\LinkPresenter;
use OCA\DashboardLinks\Links\LinkUrls;
use OCA\DashboardLinks\Tests\Support\FakeUrlGenerator;
use OCA\DashboardLinks\Tests\Support\IdentityL10N;
use OCA\DashboardLinks\Tests\Support\InMemoryAppConfig;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

final class PageControllerTest extends TestCase {
	private const INTRANET_ID = '6d4f0c4e-6a8c-4a0b-9d3a-2f0a1c3b5e7d';
	private const WIKI_ID = 'a1b2c3d4-e5f6-4789-8abc-def012345678';
	private const DISABLED_ID = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
	private const UNKNOWN_ID = 'dddddddd-dddd-4ddd-8ddd-dddddddddddd';

	private PageController $controller;

	protected function setUp(): void {
		$store = new CatalogStore(new InMemoryAppConfig());
		$store->replace(
			Catalog::parse([
				'categories' => [],
				'links' => [
					$this->row(self::INTRANET_ID, 'Intranet', 'https://intranet.example.com/'),
					$this->row(self::WIKI_ID, 'Wiki', 'https://wiki.example.com/'),
					$this->row(self::DISABLED_ID, 'Hidden', 'https://hidden.example.com/', false),
				],
			]),
			$store->current()->revision(),
		);
		$urls = new LinkUrls(new FakeUrlGenerator());
		$this->controller = new PageController(
			'dashboard_links',
			$this->createStub(IRequest::class),
			$store,
			new LinkPresenter($urls),
			new IdentityL10N(),
		);
	}

	public function testIndexBandsHaveHostSubtitleAndNoLaneLabel(): void {
		$response = $this->controller->index();

		self::assertInstanceOf(TemplateResponse::class, $response);
		$bands = $response->getParams()['bands'];
		self::assertCount(1, $bands);
		self::assertSame('', $bands[0]['label']);
		self::assertSame('Intranet', $bands[0]['links'][0]['title']);
		self::assertSame('intranet.example.com', $bands[0]['links'][0]['subtitle']);
		self::assertStringNotContainsString('Company', $bands[0]['links'][0]['subtitle']);
	}

	public function testOpenRedirectLinkIs303ToHttpsHref(): void {
		$response = $this->controller->open(self::WIKI_ID);

		self::assertInstanceOf(RedirectResponse::class, $response);
		self::assertSame(Http::STATUS_SEE_OTHER, $response->getStatus());
		self::assertSame('https://wiki.example.com/', $response->getRedirectURL());
	}

	public function testOpenIframeLinkIsFrameTemplate(): void {
		$response = $this->controller->open(self::INTRANET_ID);

		self::assertInstanceOf(TemplateResponse::class, $response);
		self::assertSame('frame', $response->getTemplateName());
	}

	public function testOpenDisabledOrUnknownIdIsNotFound(): void {
		self::assertInstanceOf(NotFoundResponse::class, $this->controller->open(self::DISABLED_ID));
		self::assertInstanceOf(NotFoundResponse::class, $this->controller->open(self::UNKNOWN_ID));
		self::assertInstanceOf(NotFoundResponse::class, $this->controller->open('not-a-uuid'));
	}

	/**
	 * @return array{id: string, title: string, href: string, openMode: string, icon: null, categoryId: null, enabled: bool}
	 */
	private function row(string $id, string $title, string $href, bool $enabled = true): array {
		return [
			'id' => $id,
			'title' => $title,
			'href' => $href,
			'openMode' => $title === 'Intranet' ? 'iframe' : 'redirect',
			'icon' => null,
			'categoryId' => null,
			'enabled' => $enabled,
		];
	}
}
