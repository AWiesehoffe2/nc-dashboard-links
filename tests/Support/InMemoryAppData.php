<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Andrew Iesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Tests\Support;

use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFolder;

final class InMemoryAppData implements IAppData {
	/** @var array<string, InMemorySimpleFolder> */
	private array $folders = [];

	public function getFolder(string $name): ISimpleFolder {
		if (!isset($this->folders[$name])) {
			throw new NotFoundException('Folder not found: ' . $name);
		}

		return $this->folders[$name];
	}

	public function getDirectoryListing(): array {
		return array_values($this->folders);
	}

	public function newFolder(string $name): ISimpleFolder {
		$folder = new InMemorySimpleFolder($name, function () use ($name): void {
			unset($this->folders[$name]);
		});
		$this->folders[$name] = $folder;

		return $folder;
	}
}
