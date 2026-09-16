<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Andrew Iesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Links;

final class LinkPresenter {
	public function __construct(
		private readonly LinkUrls $urls,
	) {
	}

	/** @return list<LinkView> */
	public function views(VisibleLinks $links): array {
		$views = [];
		foreach ($links->links() as $link) {
			$views[] = new LinkView(
				(string)$link->id,
				$link->title,
				self::subtitle($link),
				$this->urls->openUrl($link),
				$this->urls->iconUrl($link),
				$this->urls->overlayIconUrl($link),
			);
		}

		return $views;
	}

	public static function subtitle(CompanyLink $link): string {
		return self::importanceLabel($link->importance) . ' · ' . $link->href->host();
	}

	public static function importanceLabel(Importance $importance): string {
		return match ($importance) {
			Importance::Featured => 'Featured',
			Importance::Normal => 'Company',
			Importance::Reference => 'Reference',
		};
	}
}
