<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Links;

final readonly class VisibleLinks implements \Countable {
	/**
	 * @param list<CompanyLink> $links enabled links in canonical order
	 */
	private function __construct(
		private array $links,
	) {
	}

	/**
	 * @param list<CompanyLink> $links
	 * @internal Only Catalog::visible() constructs this type.
	 */
	public static function fromEnabled(array $links): self {
		return new self(array_values($links));
	}

	public function after(?LinkId $since): self {
		if ($since === null) {
			return $this;
		}
		foreach ($this->links as $index => $link) {
			if ($link->id->equals($since)) {
				return new self(array_values(array_slice($this->links, $index + 1)));
			}
		}

		return $this;
	}

	public function take(int $limit): self {
		if ($limit < 0) {
			$limit = 0;
		}

		return new self(array_values(array_slice($this->links, 0, $limit)));
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
}
