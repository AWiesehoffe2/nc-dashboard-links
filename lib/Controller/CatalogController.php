<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Controller;

use OCA\DashboardLinks\Links\Catalog;
use OCA\DashboardLinks\Links\CatalogStore;
use OCA\DashboardLinks\Links\InvalidCatalog;
use OCA\DashboardLinks\Links\StaleCatalog;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

final class CatalogController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly CatalogStore $store,
	) {
		parent::__construct($appName, $request);
	}

	#[ApiRoute(verb: 'GET', url: '/api/v1/catalog')]
	public function show(): DataResponse {
		return new DataResponse($this->store->current()->jsonSerialize());
	}

	/**
	 * @param list<array<string, mixed>> $featured
	 * @param list<array<string, mixed>> $normal
	 * @param list<array<string, mixed>> $reference
	 */
	#[ApiRoute(verb: 'PUT', url: '/api/v1/catalog')]
	public function replace(string $revision, array $featured = [], array $normal = [], array $reference = []): DataResponse {
		try {
			$saved = $this->store->replace(
				Catalog::parse([
					'featured' => $featured,
					'normal' => $normal,
					'reference' => $reference,
				]),
				$revision,
			);
			return new DataResponse($saved->jsonSerialize());
		} catch (InvalidCatalog $e) {
			return new DataResponse(['errors' => $e->errors], Http::STATUS_BAD_REQUEST);
		} catch (StaleCatalog $e) {
			return new DataResponse($e->current->jsonSerialize(), Http::STATUS_PRECONDITION_FAILED);
		}
	}
}
