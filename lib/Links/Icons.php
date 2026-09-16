<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Andrew Iesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Links;

use OCA\DashboardLinks\AppInfo\Application;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;

final class Icons {
	public const MAX_BYTES = 262144;

	/** @var array<string, string> mime → extension. Sniffed from bytes, never from the upload name. */
	private const TYPES = [
		'image/png' => 'png',
		'image/svg+xml' => 'svg',
		'image/jpeg' => 'jpg',
		'image/webp' => 'webp',
	];

	public function __construct(
		private readonly IAppDataFactory $appDataFactory,
	) {
	}

	/**
	 * @throws InvalidLink field "icon" when the type is not in TYPES or the size exceeds MAX_BYTES
	 */
	public function store(string $bytes): Icon {
		if (strlen($bytes) > self::MAX_BYTES) {
			throw new InvalidLink('icon', 'icon must be at most 262144 bytes');
		}
		$extension = self::TYPES[$this->sniff($bytes)] ?? null;
		if ($extension === null) {
			throw new InvalidLink('icon', 'icon must be a png, svg, jpg, or webp image');
		}
		$name = substr(hash('sha256', $bytes), 0, 16) . '.' . $extension;
		$icon = Icon::parse($name);
		$folder = $this->folder();
		if (!$folder->fileExists($name)) {
			$folder->newFile($name, $bytes);
		}

		return $icon;
	}

	public function exists(Icon $icon): bool {
		try {
			return $this->folder()->fileExists($icon->file);
		} catch (NotFoundException) {
			return false;
		}
	}

	/**
	 * @throws NotFoundException
	 */
	public function open(Icon $icon): ISimpleFile {
		return $this->folder()->getFile($icon->file);
	}

	public function deleteAll(): void {
		try {
			$this->appDataFactory->get(Application::APP_ID)->getFolder('icons')->delete();
		} catch (NotFoundException) {
		}
	}

	private function sniff(string $bytes): string {
		$mime = '';
		if (class_exists(\finfo::class)) {
			$detected = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
			if (is_string($detected)) {
				$mime = strtolower(trim(explode(';', $detected, 2)[0]));
			}
		}
		if (!isset(self::TYPES[$mime]) && str_starts_with(trim($bytes), '<svg')) {
			return 'image/svg+xml';
		}

		return $mime;
	}

	private function folder(): ISimpleFolder {
		$root = $this->appDataFactory->get(Application::APP_ID);
		try {
			return $root->getFolder('icons');
		} catch (NotFoundException) {
			return $root->newFolder('icons');
		}
	}
}
