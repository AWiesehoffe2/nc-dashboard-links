<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Tests\Support;

use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IAppData;

final class InMemoryAppDataFactory implements IAppDataFactory {
	/** @var array<string, InMemoryAppData> */
	private array $apps = [];

	public function get(string $appId): IAppData {
		return $this->apps[$appId] ??= new InMemoryAppData();
	}
}
