<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Links;

use OCP\IL10N;
use OCP\IURLGenerator;

final class CoreIcons {
	/** @var list<string> */
	private const PATHS = [
		'places/link.svg',
		'actions/timezone.svg',
		'actions/mail.svg',
		'actions/group.svg',
		'actions/user.svg',
		'actions/phone.svg',
		'actions/video.svg',
		'actions/settings.svg',
		'actions/external.svg',
		'places/calendar.svg',
		'places/files.svg',
		'places/contacts.svg',
	];

	public static function isAllowed(string $path): bool {
		return in_array($path, self::PATHS, true);
	}

	public static function id(string $path): string {
		return 'core:' . $path;
	}

	/**
	 * @return list<array{id: string, url: string, label: string}>
	 */
	public static function choices(IURLGenerator $urls, IL10N $l10n): array {
		$choices = [];
		foreach (self::PATHS as $path) {
			$choices[] = [
				'id' => self::id($path),
				'url' => $urls->getAbsoluteURL($urls->imagePath('core', $path)),
				'label' => self::label($path, $l10n),
			];
		}

		return $choices;
	}

	private static function label(string $path, IL10N $l10n): string {
		return match ($path) {
			'places/link.svg' => $l10n->t('Link'),
			'actions/timezone.svg' => $l10n->t('Time'),
			'actions/mail.svg' => $l10n->t('Mail'),
			'actions/group.svg' => $l10n->t('Group'),
			'actions/user.svg' => $l10n->t('Person'),
			'actions/phone.svg' => $l10n->t('Phone'),
			'actions/video.svg' => $l10n->t('Video'),
			'actions/settings.svg' => $l10n->t('Settings'),
			'actions/external.svg' => $l10n->t('External'),
			'places/calendar.svg' => $l10n->t('Calendar'),
			'places/files.svg' => $l10n->t('Files'),
			'places/contacts.svg' => $l10n->t('Contacts'),
			default => $path,
		};
	}
}
