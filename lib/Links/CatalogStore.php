<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Links;

use OCA\DashboardLinks\AppInfo\Application;
use OCP\IAppConfig;

final class CatalogStore {
	private const KEY = 'catalog';
	private const SCHEMA = 1;

	public function __construct(
		private readonly IAppConfig $config,
	) {
	}

	public function current(): Catalog {
		$doc = $this->config->getValueArray(Application::APP_ID, self::KEY, [], true);
		if ($doc === []) {
			return Catalog::empty();
		}
		if (($doc['schema'] ?? null) !== self::SCHEMA) {
			return Catalog::empty();
		}
		$rows = $doc['links'] ?? [];
		if (!is_array($rows)) {
			return Catalog::empty();
		}
		$kept = [];
		$seen = [];
		foreach ($rows as $row) {
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
			$kept[] = $link;
		}
		try {
			return Catalog::of(...$kept);
		} catch (InvalidCatalog) {
			return Catalog::empty();
		}
	}

	/**
	 * IAppConfig has no compare-and-swap. Equal content returns without a write
	 * so a retry is safe. A lost race still 412s the next distinct save.
	 *
	 * @throws StaleCatalog
	 */
	public function replace(Catalog $next, string $expectedRevision): Catalog {
		$current = $this->current();
		if ($next->revision() === $current->revision()) {
			return $current;
		}
		if ($expectedRevision !== $current->revision()) {
			throw new StaleCatalog($current);
		}
		$links = [];
		foreach ($next->links() as $link) {
			$links[] = $link->jsonSerialize();
		}
		$this->config->setValueArray(
			Application::APP_ID,
			self::KEY,
			['schema' => self::SCHEMA, 'links' => $links],
			true,
		);

		return $next;
	}
}
