<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Links;

final readonly class LinkId implements \Stringable {
	private const PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

	private function __construct(
		public string $value,
	) {
	}

	public static function parse(string $raw): self {
		$value = strtolower(trim($raw));
		if (preg_match(self::PATTERN, $value) !== 1) {
			throw new InvalidLink('id', 'id must be a lowercase UUIDv4');
		}

		return new self($value);
	}

	public static function tryParse(?string $raw): ?self {
		if ($raw === null) {
			return null;
		}
		try {
			return self::parse($raw);
		} catch (InvalidLink) {
			return null;
		}
	}

	public function equals(self $other): bool {
		return $this->value === $other->value;
	}

	#[\Override]
	public function __toString(): string {
		return $this->value;
	}
}
