<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Settings;

use OCA\DashboardLinks\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

final class Section implements IIconSection {
	public const ID = 'dashboard_links';

	public function __construct(
		private readonly IURLGenerator $urlGenerator,
	) {
	}

	#[\Override]
	public function getID(): string {
		return self::ID;
	}

	#[\Override]
	public function getName(): string {
		return 'Company links';
	}

	#[\Override]
	public function getPriority(): int {
		return 80;
	}

	#[\Override]
	public function getIcon(): string {
		return $this->urlGenerator->imagePath(Application::APP_ID, 'app.svg');
	}
}
