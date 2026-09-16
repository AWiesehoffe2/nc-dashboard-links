<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Links;

final class StaleCatalog extends \RuntimeException {
	public function __construct(
		public readonly Catalog $current,
	) {
		parent::__construct('catalog revision mismatch');
	}
}
