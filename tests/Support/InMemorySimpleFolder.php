<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Andrew Iesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Tests\Support;

use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\InMemoryFile;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;

final class InMemorySimpleFolder implements ISimpleFolder {
	/** @var array<string, ISimpleFile> */
	private array $files = [];

	public function __construct(
		private readonly string $name,
		private readonly \Closure $onDelete,
	) {
	}

	public function getDirectoryListing(): array {
		return array_values($this->files);
	}

	public function fileExists(string $name): bool {
		return isset($this->files[$name]);
	}

	public function getFile(string $name): ISimpleFile {
		if (!isset($this->files[$name])) {
			throw new NotFoundException('File not found: ' . $name);
		}

		return $this->files[$name];
	}

	public function newFile(string $name, $content = null): ISimpleFile {
		$file = new InMemoryFile($name, is_string($content) ? $content : '');
		$this->files[$name] = $file;

		return $file;
	}

	public function delete(): void {
		$this->files = [];
		($this->onDelete)();
	}

	public function getName(): string {
		return $this->name;
	}

	public function getFolder(string $name): ISimpleFolder {
		throw new \BadMethodCallException();
	}

	public function newFolder(string $path): ISimpleFolder {
		throw new \BadMethodCallException();
	}
}
