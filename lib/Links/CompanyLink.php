<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Links;

final readonly class CompanyLink implements \JsonSerializable {
	public const TITLE_MAX = 120;

	public string $title;

	public function __construct(
		public LinkId $id,
		string $title,
		public HttpsUrl $href,
		public Importance $importance,
		public OpenMode $openMode,
		public ?Icon $icon,
		public bool $enabled,
	) {
		$title = trim($title);
		$length = mb_strlen($title);
		if ($length < 1 || $length > self::TITLE_MAX) {
			throw new InvalidLink('title', 'title must be between 1 and 120 characters');
		}
		$this->title = $title;
	}

	/**
	 * @param mixed $row untrusted wire or storage row
	 * @param ?Importance $lane when set, importance comes from the lane
	 */
	public static function parse(mixed $row, ?Importance $lane = null): self {
		if (!is_array($row)) {
			throw new InvalidLink('id', 'row must be an object');
		}
		if ($lane !== null && array_key_exists('importance', $row)) {
			throw new InvalidLink('importance', 'importance belongs on the lane, not the row');
		}

		$id = self::stringField($row, 'id');
		$title = self::stringField($row, 'title');
		$href = self::stringField($row, 'href');
		$importance = $lane ?? self::importanceField($row);
		$openMode = OpenMode::tryFrom(self::stringField($row, 'openMode'));
		if ($openMode === null) {
			throw new InvalidLink('openMode', 'openMode must be iframe or redirect');
		}

		$icon = null;
		if (array_key_exists('icon', $row) && $row['icon'] !== null) {
			if (!is_string($row['icon'])) {
				throw new InvalidLink('icon', 'icon must be a file name or null');
			}
			$icon = Icon::parse($row['icon']);
		}

		if (!array_key_exists('enabled', $row) || !is_bool($row['enabled'])) {
			throw new InvalidLink('enabled', 'enabled must be a boolean');
		}

		return new self(
			LinkId::parse($id),
			$title,
			HttpsUrl::parse($href),
			$importance,
			$openMode,
			$icon,
			$row['enabled'],
		);
	}

	/**
	 * @return array{id: string, title: string, href: string, importance: string, openMode: string, icon: ?string, enabled: bool}
	 */
	#[\Override]
	public function jsonSerialize(): array {
		return [
			'id' => (string)$this->id,
			'title' => $this->title,
			'href' => (string)$this->href,
			'importance' => $this->importance->value,
			'openMode' => $this->openMode->value,
			'icon' => $this->icon === null ? null : (string)$this->icon,
			'enabled' => $this->enabled,
		];
	}

	/**
	 * @param array<array-key, mixed> $row
	 */
	private static function stringField(array $row, string $field): string {
		if (!array_key_exists($field, $row) || !is_string($row[$field])) {
			throw new InvalidLink($field, $field . ' must be a string');
		}

		return $row[$field];
	}

	/**
	 * @param array<array-key, mixed> $row
	 */
	private static function importanceField(array $row): Importance {
		$raw = self::stringField($row, 'importance');
		$importance = Importance::tryFrom($raw);
		if ($importance === null) {
			throw new InvalidLink('importance', 'importance must be featured, normal, or reference');
		}

		return $importance;
	}
}
