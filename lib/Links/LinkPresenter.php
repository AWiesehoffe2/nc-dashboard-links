<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
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
				$link->href->host(),
				$this->urls->openUrl($link),
				$this->urls->iconUrl($link),
				'',
			);
		}

		return $views;
	}
}
