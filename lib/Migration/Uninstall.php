<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Andrew Iesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Migration;

use OCA\DashboardLinks\AppInfo\Application;
use OCA\DashboardLinks\Links\Icons;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

final class Uninstall implements IRepairStep {
	public function __construct(
		private readonly IAppConfig $config,
		private readonly Icons $icons,
	) {
	}

	#[\Override]
	public function getName(): string {
		return 'Remove Company Links catalog and icons';
	}

	#[\Override]
	public function run(IOutput $output): void {
		$this->config->deleteKey(Application::APP_ID, 'catalog');
		$this->icons->deleteAll();
	}
}
