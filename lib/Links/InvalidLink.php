<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Andrew Iesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Links;

final class InvalidLink extends \InvalidArgumentException {
	public function __construct(
		public readonly string $field,
		string $message,
	) {
		parent::__construct($message);
	}
}
