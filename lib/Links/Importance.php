<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Andrew Iesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Links;

enum Importance: string {
	case Featured = 'featured';
	case Normal = 'normal';
	case Reference = 'reference';

	public function rank(): int {
		return match ($this) {
			self::Featured => 0,
			self::Normal => 1,
			self::Reference => 2,
		};
	}
}
