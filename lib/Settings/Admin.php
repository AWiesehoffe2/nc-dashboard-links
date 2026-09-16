<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Andrew Iesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Settings;

use OCA\DashboardLinks\AppInfo\Application;
use OCA\DashboardLinks\Links\CatalogStore;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Settings\ISettings;
use OCP\Util;

final class Admin implements ISettings {
	public function __construct(
		private readonly CatalogStore $store,
		private readonly IInitialState $initialState,
		private readonly IAppManager $appManager,
	) {
	}

	#[\Override]
	public function getForm(): TemplateResponse {
		$this->initialState->provideInitialState('catalog', $this->store->current()->jsonSerialize());
		$this->initialState->provideInitialState(
			'externalSitesAvailable',
			$this->appManager->isEnabledForUser('external'),
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
