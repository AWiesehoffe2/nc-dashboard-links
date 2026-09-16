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
		public OpenMode $openMode,
		public ?Icon $icon,
		public ?CategoryId $categoryId,
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
	 */
	public static function parse(mixed $row): self {
		if (!is_array($row)) {
			throw new InvalidLink('id', 'row must be an object');
		}
		if (array_key_exists('importance', $row)) {
			throw new InvalidLink('importance', 'importance is not used; assign a category or leave the default list');
		}

		$id = self::stringField($row, 'id');
		$title = self::stringField($row, 'title');
		$href = self::stringField($row, 'href');
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

		$categoryId = null;
		if (array_key_exists('categoryId', $row) && $row['categoryId'] !== null) {
			$categoryId = CategoryId::tryParse($row['categoryId']);
		}

		if (!array_key_exists('enabled', $row) || !is_bool($row['enabled'])) {
			throw new InvalidLink('enabled', 'enabled must be a boolean');
		}

		return new self(
			LinkId::parse($id),
			$title,
			HttpsUrl::parse($href),
			$openMode,
			$icon,
			$categoryId,
			$row['enabled'],
		);
	}

	/**
	 * @return array{id: string, title: string, href: string, openMode: string, icon: ?string, categoryId: ?string, enabled: bool}
	 */
	#[\Override]
	public function jsonSerialize(): array {
		return [
			'id' => (string)$this->id,
			'title' => $this->title,
			'href' => (string)$this->href,
			'openMode' => $this->openMode->value,
			'icon' => $this->icon === null ? null : (string)$this->icon,
			'categoryId' => $this->categoryId === null ? null : (string)$this->categoryId,
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
}
