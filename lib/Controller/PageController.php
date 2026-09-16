<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Controller;

use OCA\DashboardLinks\AppInfo\Application;
use OCA\DashboardLinks\Links\CatalogStore;
use OCA\DashboardLinks\Links\CompanyLink;
use OCA\DashboardLinks\Links\LinkId;
use OCA\DashboardLinks\Links\LinkPresenter;
use OCA\DashboardLinks\Links\LinkView;
use OCA\DashboardLinks\Links\OpenMode;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IL10N;
use OCP\IRequest;

final class PageController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly CatalogStore $store,
		private readonly LinkPresenter $presenter,
		private readonly IL10N $l10n,
	) {
		parent::__construct($appName, $request);
	}

	#[FrontpageRoute(verb: 'GET', url: '/')]
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		return new TemplateResponse(
			Application::APP_ID,
			'links',
			[
				'pageTitle' => $this->l10n->t('Company links'),
				'emptyTitle' => $this->l10n->t('No company links'),
				'emptyHint' => $this->l10n->t('Ask an administrator to add company links.'),
				'bands' => $this->bands(),
			],
		);
	}

	#[FrontpageRoute(verb: 'GET', url: '/open/{id}')]
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function open(string $id): Response {
		$linkId = LinkId::tryParse($id);
		if ($linkId === null) {
			return new NotFoundResponse();
		}
		$link = $this->store->current()->visible()->find($linkId);
		if ($link === null) {
			return new NotFoundResponse();
		}

		$response = match ($link->openMode) {
			OpenMode::Redirect => new RedirectResponse((string)$link->href),
			OpenMode::Iframe => $this->frame($link),
		};
		$response->addHeader('Referrer-Policy', 'no-referrer');

		return $response;
	}

	private function frame(CompanyLink $link): TemplateResponse {
		$response = new TemplateResponse(
			Application::APP_ID,
			'frame',
			['href' => (string)$link->href],
			TemplateResponse::RENDER_AS_USER,
		);
		$response->getContentSecurityPolicy()->addAllowedFrameDomain('*');

		return $response;
	}

	/**
	 * @return list<array{label: string, links: list<array{title: string, url: string, iconUrl: string, subtitle: string}>}>
	 */
	private function bands(): array {
		$bands = [];
		foreach ($this->store->current()->sections($this->l10n->t('Uncategorized')) as $section) {
			$views = $this->presenter->views($section['links']);
			if ($views === []) {
				continue;
			}
			$bands[] = [
				'label' => $section['label'],
				'links' => array_map(
					static fn (LinkView $view): array => [
						'title' => $view->title,
						'url' => $view->href,
						'iconUrl' => $view->iconUrl,
						'subtitle' => $view->subtitle,
					],
					$views,
				),
			];
		}

		return $bands;
	}
}
