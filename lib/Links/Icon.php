<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Links;

final readonly class Icon implements \Stringable {
	private const UPLOAD = '/^[0-9a-f]{16}\.(png|svg|jpg|webp)$/';

	private function __construct(
		public string $file,
	) {
	}

	public static function parse(string $file): self {
		$file = trim($file);
		if (str_starts_with($file, 'core:')) {
			$path = substr($file, 5);
			if (!CoreIcons::isAllowed($path)) {
				throw new InvalidLink('icon', 'icon must be a stored file or a Nextcloud icon');
			}

			return new self(CoreIcons::id($path));
		}
		if (str_contains($file, '://') || str_starts_with($file, '//') || str_contains($file, '/')) {
			throw new InvalidLink('icon', 'icon must be a stored file name, not a URL');
		}
		if (preg_match(self::UPLOAD, $file) !== 1) {
			throw new InvalidLink('icon', 'icon must be a content-addressed file name');
		}

		return new self($file);
	}

	public function isCore(): bool {
		return str_starts_with($this->file, 'core:');
	}

	public function corePath(): string {
		if (!$this->isCore()) {
			throw new \LogicException('corePath is only for Nextcloud icons');
		}

		return substr($this->file, 5);
	}

	#[\Override]
	public function __toString(): string {
		return $this->file;
	}
}
