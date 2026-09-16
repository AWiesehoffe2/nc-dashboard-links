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
	private const SCHEMA = 2;
	private const LEGACY_SCHEMA = 1;

	public function __construct(
		private readonly IAppConfig $config,
	) {
	}

	public function current(): Catalog {
		$doc = $this->config->getValueArray(Application::APP_ID, self::KEY, [], true);
		if ($doc === []) {
			return Catalog::empty();
		}
		$schema = $doc['schema'] ?? null;
		if ($schema === self::LEGACY_SCHEMA) {
			$rows = $doc['links'] ?? [];
			return Catalog::fromLegacyRows(is_array($rows) ? array_values($rows) : []);
		}
		if ($schema !== self::SCHEMA) {
			return Catalog::empty();
		}
		try {
			return Catalog::parse([
				'categories' => $doc['categories'] ?? [],
				'links' => $doc['links'] ?? [],
			]);
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
		$stored = $this->config->getValueArray(Application::APP_ID, self::KEY, [], true);
		$needsUpgrade = ($stored['schema'] ?? null) !== self::SCHEMA;
		if ($next->revision() === $current->revision() && !$needsUpgrade) {
			return $current;
		}
		if ($expectedRevision !== $current->revision()) {
			throw new StaleCatalog($current);
		}
		$categories = [];
		foreach ($next->categories() as $category) {
			$categories[] = $category->jsonSerialize();
		}
		$links = [];
		foreach ($next->links() as $link) {
			$links[] = $link->jsonSerialize();
		}
		$this->config->setValueArray(
			Application::APP_ID,
			self::KEY,
			['schema' => self::SCHEMA, 'categories' => $categories, 'links' => $links],
			true,
		);

		return $next;
	}
}
