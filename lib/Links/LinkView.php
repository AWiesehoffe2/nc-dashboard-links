<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Links;

final readonly class LinkView {
	public function __construct(
		public string $id,
		public string $title,
		public string $subtitle,
		public string $href,
		public string $iconUrl,
		public string $overlayIconUrl,
	) {
	}
}
