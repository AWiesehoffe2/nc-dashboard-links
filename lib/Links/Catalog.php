<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Links;

final readonly class Catalog implements \JsonSerializable, \Countable {
	public const MAX_LINKS = 200;
	public const MAX_CATEGORIES = 40;

	private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

	/**
	 * @param list<Category> $categories
	 * @param list<CompanyLink> $links already in display order
	 */
	private function __construct(
		private array $categories,
		private array $links,
	) {
	}

	public static function empty(): self {
		return new self([], []);
	}

	/**
	 * @param list<Category> $categories
	 * @param list<CompanyLink> $links
	 * @throws InvalidCatalog
	 */
	public static function of(array $categories, array $links): self {
		$errors = [];
		if (count($categories) > self::MAX_CATEGORIES) {
			$errors[] = ['index' => 0, 'field' => 'categories', 'message' => 'at most 40 categories'];
		}
		if (count($links) > self::MAX_LINKS) {
			$errors[] = ['index' => 0, 'field' => 'catalog', 'message' => 'at most 200 links'];
		}
		$categoryIds = [];
		foreach ($categories as $index => $category) {
			$id = (string)$category->id;
			if (isset($categoryIds[$id])) {
				$errors[] = ['index' => $index, 'field' => 'id', 'message' => 'duplicate category id'];
				continue;
			}
			$categoryIds[$id] = true;
		}
		$seen = [];
		foreach ($links as $index => $link) {
			$id = (string)$link->id;
			if (isset($seen[$id])) {
				$errors[] = ['index' => $index, 'field' => 'id', 'message' => 'duplicate id'];
				continue;
			}
			$seen[$id] = true;
			if ($link->categoryId !== null && !isset($categoryIds[(string)$link->categoryId])) {
				$errors[] = ['index' => $index, 'field' => 'categoryId', 'message' => 'categoryId must match a category'];
			}
		}
		if ($errors !== []) {
			throw new InvalidCatalog($errors);
		}

		return new self(array_values($categories), array_values($links));
	}

	/**
	 * @param array<array-key, mixed> $payload
	 * @throws InvalidCatalog
	 */
	public static function parse(array $payload): self {
		if (isset($payload['featured']) || isset($payload['normal']) || isset($payload['reference'])) {
			throw new InvalidCatalog([
				['index' => 0, 'field' => 'categories', 'message' => 'use categories and links; featured, normal, and reference are not used'],
			]);
		}

		$errors = [];
		$categories = [];
		$seenCategories = [];
		$rawCategories = $payload['categories'] ?? [];
		if (!is_array($rawCategories) || !array_is_list($rawCategories)) {
			throw new InvalidCatalog([
				['index' => 0, 'field' => 'categories', 'message' => 'must be a list of categories'],
			]);
		}
		foreach ($rawCategories as $index => $row) {
			try {
				$category = Category::parse($row);
				$id = (string)$category->id;
				if (isset($seenCategories[$id])) {
					$errors[] = ['index' => $index, 'field' => 'id', 'message' => 'duplicate category id'];
				} else {
					$seenCategories[$id] = true;
					$categories[] = $category;
				}
			} catch (InvalidLink $e) {
				$errors[] = ['index' => $index, 'field' => $e->field, 'message' => $e->getMessage()];
			}
		}

		$links = [];
		$seen = [];
		$rawLinks = $payload['links'] ?? [];
		if (!is_array($rawLinks) || !array_is_list($rawLinks)) {
			throw new InvalidCatalog([
				['index' => 0, 'field' => 'links', 'message' => 'must be a list of rows'],
			]);
		}
		foreach ($rawLinks as $index => $row) {
			try {
				$link = CompanyLink::parse($row);
				$id = (string)$link->id;
				if (isset($seen[$id])) {
					$errors[] = ['index' => $index, 'field' => 'id', 'message' => 'duplicate id'];
				} else {
					$seen[$id] = true;
					$links[] = $link;
				}
			} catch (InvalidLink $e) {
				$errors[] = ['index' => $index, 'field' => $e->field, 'message' => $e->getMessage()];
			}
		}

		if ($errors !== []) {
			throw new InvalidCatalog($errors);
		}

		return self::of($categories, $links);
	}

	/**
	 * Schema 1 stored rows carried importance. They become the default list.
	 *
	 * @param list<mixed> $rows
	 */
	public static function fromLegacyRows(array $rows): self {
		$rank = static function (mixed $row): int {
			if (!is_array($row) || !isset($row['importance']) || !is_string($row['importance'])) {
				return 99;
			}

			return match ($row['importance']) {
				'featured' => 0,
				'normal' => 1,
				'reference' => 2,
				default => 99,
			};
		};
		usort($rows, static fn (mixed $left, mixed $right): int => $rank($left) <=> $rank($right));

		$links = [];
		$seen = [];
		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}
			unset($row['importance']);
			$row['categoryId'] = $row['categoryId'] ?? null;
			try {
				$link = CompanyLink::parse($row);
			} catch (InvalidLink) {
				continue;
			}
			$id = (string)$link->id;
			if (isset($seen[$id])) {
				continue;
			}
			$seen[$id] = true;
			$links[] = $link;
		}
		try {
			return self::of([], $links);
		} catch (InvalidCatalog) {
			return self::empty();
		}
	}

	public function visible(): VisibleLinks {
		$enabled = [];
		foreach ($this->links as $link) {
			if ($link->enabled) {
				$enabled[] = $link;
			}
		}

		return VisibleLinks::fromEnabled($enabled);
	}

	/**
	 * @return list<array{label: string, links: VisibleLinks}>
	 */
	public function sections(string $uncategorizedLabel): array {
		$visible = $this->visible()->links();
		if ($this->categories === []) {
			return [['label' => '', 'links' => VisibleLinks::fromEnabled($visible)]];
		}

		$sections = [];
		$uncategorized = [];
		foreach ($visible as $link) {
			if ($link->categoryId === null) {
				$uncategorized[] = $link;
			}
		}
		if ($uncategorized !== []) {
			$sections[] = [
				'label' => $uncategorizedLabel,
				'links' => VisibleLinks::fromEnabled($uncategorized),
			];
		}
		foreach ($this->categories as $category) {
			$inCategory = [];
			foreach ($visible as $link) {
				if ($link->categoryId !== null && $link->categoryId->equals($category->id)) {
					$inCategory[] = $link;
				}
			}
			if ($inCategory === []) {
				continue;
			}
			$sections[] = [
				'label' => $category->title,
				'links' => VisibleLinks::fromEnabled($inCategory),
			];
		}

		return $sections;
	}

	public function find(LinkId $id): ?CompanyLink {
		foreach ($this->links as $link) {
			if ($link->id->equals($id)) {
				return $link;
			}
		}

		return null;
	}

	/** @return list<Category> */
	public function categories(): array {
		return $this->categories;
	}

	/** @return list<CompanyLink> */
	public function links(): array {
		return $this->links;
	}

	#[\Override]
	public function count(): int {
		return count($this->links);
	}

	public function isEmpty(): bool {
		return $this->links === [];
	}

	public function revision(): string {
		$payload = [
			'categories' => [],
			'links' => [],
		];
		foreach ($this->categories as $category) {
			$payload['categories'][] = $category->jsonSerialize();
		}
		foreach ($this->links as $link) {
			$payload['links'][] = $link->jsonSerialize();
		}

		return substr(hash('sha256', json_encode($payload, self::JSON_FLAGS)), 0, 12);
	}

	/**
	 * @return array{revision: string, categories: list<array{id: string, title: string}>, links: list<array<string, mixed>>}
	 */
	#[\Override]
	public function jsonSerialize(): array {
		$categories = [];
		foreach ($this->categories as $category) {
			$categories[] = $category->jsonSerialize();
		}
		$links = [];
		foreach ($this->links as $link) {
			$links[] = $link->jsonSerialize();
		}

		return [
			'revision' => $this->revision(),
			'categories' => $categories,
			'links' => $links,
		];
	}
}
