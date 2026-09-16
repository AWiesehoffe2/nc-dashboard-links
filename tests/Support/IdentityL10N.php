<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Tests\Support;

use OCP\IL10N;

final class IdentityL10N implements IL10N {
	public function t(string $text, $parameters = []): string {
		return $text;
	}

	public function n(string $text_singular, string $text_plural, int $count, array $parameters = []): string {
		return $count === 1 ? $text_singular : $text_plural;
	}

	public function l(string $type, $data, array $options = []) {
		throw new \BadMethodCallException();
	}

	public function getLanguageCode(): string {
		return 'en';
	}

	public function getLocaleCode(): string {
		return 'en_US';
	}
}
