<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Links;

final readonly class Catalog implements \JsonSerializable, \Countable {
	public const MAX_LINKS = 200;

	private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

	/**
	 * @var array<string, Importance>
	 */
	private const LANES = [
		'featured' => Importance::Featured,
		'normal' => Importance::Normal,
		'reference' => Importance::Reference,
	];

	/** @param list<CompanyLink> $links already canonical */
	private function __construct(
		private array $links,
	) {
	}

	public static function empty(): self {
		return new self([]);
	}

	/**
	 * @throws InvalidCatalog
	 */
	public static function of(CompanyLink ...$links): self {
		if (count($links) > self::MAX_LINKS) {
			throw new InvalidCatalog([
				['index' => 0, 'field' => 'catalog', 'message' => 'at most 200 links'],
			]);
		}
		$seen = [];
		$errors = [];
		foreach ($links as $index => $link) {
			$id = (string)$link->id;
			if (isset($seen[$id])) {
				$errors[] = ['index' => $index, 'field' => 'id', 'message' => 'duplicate id'];
				continue;
			}
			$seen[$id] = true;
		}
		if ($errors !== []) {
			throw new InvalidCatalog($errors);
		}
		usort(
			$links,
			static fn (CompanyLink $a, CompanyLink $b): int => $a->importance->rank() <=> $b->importance->rank(),
		);

		return new self(array_values($links));
	}

	/**
	 * Admin lane envelope. A row-level importance key is invalid.
	 *
	 * @param array<array-key, mixed> $lanes
	 * @throws InvalidCatalog
	 */
	public static function parse(array $lanes): self {
		$errors = [];
		$links = [];
		$seen = [];
		$index = 0;
		foreach (self::LANES as $key => $importance) {
			$rows = $lanes[$key] ?? [];
			if (!is_array($rows) || !array_is_list($rows)) {
				$errors[] = ['index' => $index, 'field' => $key, 'message' => 'must be a list of rows'];
				continue;
			}
			foreach ($rows as $row) {
				try {
					$link = CompanyLink::parse($row, $importance);
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
				$index++;
			}
		}
		if (count($links) > self::MAX_LINKS) {
			$errors[] = ['index' => 0, 'field' => 'catalog', 'message' => 'at most 200 links'];
		}
		if ($errors !== []) {
			throw new InvalidCatalog($errors);
		}

		return self::of(...$links);
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

	public function band(Importance $importance): self {
		$links = [];
		foreach ($this->links as $link) {
			if ($link->importance === $importance) {
				$links[] = $link;
			}
		}

		return new self($links);
	}

	public function find(LinkId $id): ?CompanyLink {
		foreach ($this->links as $link) {
			if ($link->id->equals($id)) {
				return $link;
			}
		}

		return null;
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
		$payload = [];
		foreach ($this->links as $link) {
			$payload[] = $link->jsonSerialize();
		}

		return substr(hash('sha256', json_encode($payload, self::JSON_FLAGS)), 0, 12);
	}

	/**
	 * @return array{revision: string, featured: list<array<string, mixed>>, normal: list<array<string, mixed>>, reference: list<array<string, mixed>>}
	 */
	#[\Override]
	public function jsonSerialize(): array {
		return [
			'revision' => $this->revision(),
			'featured' => $this->laneRows(Importance::Featured),
			'normal' => $this->laneRows(Importance::Normal),
			'reference' => $this->laneRows(Importance::Reference),
		];
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function laneRows(Importance $importance): array {
		$rows = [];
		foreach ($this->links as $link) {
			if ($link->importance !== $importance) {
				continue;
			}
			$row = $link->jsonSerialize();
			unset($row['importance']);
			$rows[] = $row;
		}

		return $rows;
	}
}
