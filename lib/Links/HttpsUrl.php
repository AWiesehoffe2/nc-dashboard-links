<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Andrew Iesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Links;

final readonly class HttpsUrl implements \Stringable {
	public const MAX_LENGTH = 2048;

	private function __construct(
		private string $value,
	) {
	}

	public static function parse(string $raw): self {
		$value = trim($raw);
		if ($value === '' || strlen($value) > self::MAX_LENGTH) {
			throw new InvalidLink('href', 'href must be an https URL of at most 2048 characters');
		}
		if (filter_var($value, FILTER_VALIDATE_URL) === false) {
			throw new InvalidLink('href', 'href must be an https URL');
		}
		$parts = parse_url($value);
		if (!is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || !isset($parts['host']) || $parts['host'] === '') {
			throw new InvalidLink('href', 'href must be an https URL');
		}
		if (isset($parts['user']) || isset($parts['pass'])) {
			throw new InvalidLink('href', 'href must not contain userinfo');
		}

		return new self($value);
	}

	public function host(): string {
		$host = parse_url($this->value, PHP_URL_HOST);
		return is_string($host) ? $host : '';
	}

	public function normalized(): string {
		$parts = parse_url($this->value);
		if (!is_array($parts)) {
			return $this->value;
		}
		$host = strtolower($parts['host'] ?? '');
		$port = $parts['port'] ?? null;
		if ($port === 443) {
			$port = null;
		}
		$path = $parts['path'] ?? '';
		if ($path === '/') {
			$path = '';
		}
		$query = isset($parts['query']) ? '?' . $parts['query'] : '';
		$authority = $host;
		if ($port !== null) {
			$authority .= ':' . $port;
		}

		return 'https://' . $authority . $path . $query;
	}

	#[\Override]
	public function __toString(): string {
		return $this->value;
	}
}
