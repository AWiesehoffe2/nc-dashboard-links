<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Andrew Iesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Links;

final class InvalidCatalog extends \InvalidArgumentException {
	/** @param list<array{index: int, field: string, message: string}> $errors */
	public function __construct(
		public readonly array $errors,
	) {
		parent::__construct('catalog has ' . count($errors) . ' invalid field(s)');
	}
}
