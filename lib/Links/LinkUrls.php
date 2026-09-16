<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Andrew Iesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Links;

use OCA\DashboardLinks\AppInfo\Application;
use OCP\IURLGenerator;

final class LinkUrls {
	public function __construct(
		private readonly IURLGenerator $urlGenerator,
	) {
	}

	public function openUrl(CompanyLink $link): string {
		return $this->urlGenerator->linkToRouteAbsolute(
			Application::APP_ID . '.page.open',
			['id' => (string)$link->id],
		);
	}

	public function iconUrl(CompanyLink $link): string {
		if ($link->icon === null) {
			return $this->defaultIconUrl();
		}

		return $this->storedIconUrl($link->icon);
	}

	public function storedIconUrl(Icon $icon): string {
		return $this->urlGenerator->linkToRouteAbsolute(
			Application::APP_ID . '.icon.show',
			['file' => (string)$icon],
		);
	}

	public function overlayIconUrl(CompanyLink $link): string {
		return match ($link->importance) {
			Importance::Featured => $this->urlGenerator->getAbsoluteURL(
				$this->urlGenerator->imagePath(Application::APP_ID, 'overlay-featured.svg'),
			),
			Importance::Normal, Importance::Reference => '',
		};
	}

	public function defaultIconUrl(): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg'),
		);
	}

	public function allLinksUrl(): string {
		return $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.page.index');
	}

	public function settingsUrl(): string {
		return $this->urlGenerator->getAbsoluteURL('/settings/admin/' . Application::APP_ID);
	}
}
