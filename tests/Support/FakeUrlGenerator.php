<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Tests\Support;

use OCP\IURLGenerator;

final class FakeUrlGenerator implements IURLGenerator {
	public function linkToRoute(string $routeName, array $arguments = []): string {
		return $this->linkToRouteAbsolute($routeName, $arguments);
	}

	public function linkToRouteAbsolute(string $routeName, array $arguments = []): string {
		return match ($routeName) {
			'dashboard_links.page.open' => 'https://cloud.example.test/apps/dashboard_links/open/' . ($arguments['id'] ?? ''),
			'dashboard_links.page.index' => 'https://cloud.example.test/apps/dashboard_links/',
			'dashboard_links.icon.show' => 'https://cloud.example.test/apps/dashboard_links/icons/' . ($arguments['file'] ?? ''),
			default => throw new \BadMethodCallException('unknown route ' . $routeName),
		};
	}

	public function linkToOCSRouteAbsolute(string $routeName, array $arguments = []): string {
		throw new \BadMethodCallException();
	}

	public function linkTo(string $appName, string $file, array $args = []): string {
		throw new \BadMethodCallException();
	}

	public function imagePath(string $appName, string $file): string {
		return '/apps/' . $appName . '/img/' . $file;
	}

	public function getAbsoluteURL(string $url): string {
		if (str_starts_with($url, 'https://') || str_starts_with($url, 'http://')) {
			return $url;
		}

		return 'https://cloud.example.test' . $url;
	}

	public function linkToDocs(string $key): string {
		throw new \BadMethodCallException();
	}

	public function linkToDefaultPageUrl(): string {
		throw new \BadMethodCallException();
	}

	public function getBaseUrl(): string {
		throw new \BadMethodCallException();
	}

	public function getWebroot(): string {
		throw new \BadMethodCallException();
	}
}
