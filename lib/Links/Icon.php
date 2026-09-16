<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Links;

final readonly class Icon implements \Stringable {
	private const PATTERN = '/^[0-9a-f]{16}\.(png|svg|jpg|webp)$/';

	private function __construct(
		public string $file,
	) {
	}

	public static function parse(string $file): self {
		$file = trim($file);
		if (str_contains($file, '://') || str_starts_with($file, '//') || str_contains($file, '/')) {
			throw new InvalidLink('icon', 'icon must be a stored file name, not a URL');
		}
		if (preg_match(self::PATTERN, $file) !== 1) {
			throw new InvalidLink('icon', 'icon must be a content-addressed file name');
		}

		return new self($file);
	}

	#[\Override]
	public function __toString(): string {
		return $this->file;
	}
}
