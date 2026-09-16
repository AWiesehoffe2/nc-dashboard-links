<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Links;

final readonly class Category implements \JsonSerializable {
	public const TITLE_MAX = 80;

	public string $title;

	public function __construct(
		public CategoryId $id,
		string $title,
	) {
		$title = trim($title);
		$length = mb_strlen($title);
		if ($length < 1 || $length > self::TITLE_MAX) {
			throw new InvalidLink('title', 'category title must be between 1 and 80 characters');
		}
		$this->title = $title;
	}

	/**
	 * @param mixed $row
	 */
	public static function parse(mixed $row): self {
		if (!is_array($row)) {
			throw new InvalidLink('id', 'category must be an object');
		}
		if (!isset($row['id']) || !is_string($row['id'])) {
			throw new InvalidLink('id', 'id must be a string');
		}
		if (!isset($row['title']) || !is_string($row['title'])) {
			throw new InvalidLink('title', 'title must be a string');
		}

		return new self(CategoryId::parse($row['id']), $row['title']);
	}

	/**
	 * @return array{id: string, title: string}
	 */
	#[\Override]
	public function jsonSerialize(): array {
		return [
			'id' => (string)$this->id,
			'title' => $this->title,
		];
	}
}
