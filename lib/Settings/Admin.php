<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Settings;

use OCA\DashboardLinks\AppInfo\Application;
use OCA\DashboardLinks\Links\CatalogStore;
use OCA\DashboardLinks\Links\CoreIcons;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\ISettings;
use OCP\Util;

final class Admin implements ISettings {
	public function __construct(
		private readonly CatalogStore $store,
		private readonly IInitialState $initialState,
		private readonly IAppManager $appManager,
		private readonly IURLGenerator $urlGenerator,
		private readonly IL10N $l10n,
	) {
	}

	#[\Override]
	public function getForm(): TemplateResponse {
		$this->initialState->provideInitialState('catalog', $this->store->current()->jsonSerialize());
		$this->initialState->provideInitialState(
			'externalSitesAvailable',
			$this->appManager->isEnabledForUser('external'),
		);
		$this->initialState->provideInitialState(
			'coreIcons',
			CoreIcons::choices($this->urlGenerator, $this->l10n),
		);

		Util::addScript(Application::APP_ID, 'dashboard_links-admin');

		return new TemplateResponse(Application::APP_ID, 'settings', [], TemplateResponse::RENDER_AS_BLANK);
	}

	#[\Override]
	public function getSection(): string {
		return Section::ID;
	}

	#[\Override]
	public function getPriority(): int {
		return 50;
	}
}
