<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Andrew Iesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Tests\Support;

use OCP\IAppConfig;

final class InMemoryAppConfig implements IAppConfig {
	/** @var array<string, array<string, array>> */
	private array $arrays = [];

	public int $arrayWrites = 0;

	public function getValueArray(string $app, string $key, array $default = [], bool $lazy = false): array {
		return $this->arrays[$app][$key] ?? $default;
	}

	public function setValueArray(string $app, string $key, array $value, bool $lazy = false, bool $sensitive = false): bool {
		$this->arrayWrites++;
		$this->arrays[$app][$key] = $value;
		return true;
	}

	public function hasKey(string $app, string $key, ?bool $lazy = false): bool {
		return isset($this->arrays[$app][$key]);
	}

	public function deleteKey(string $app, string $key): void {
		unset($this->arrays[$app][$key]);
	}

	public function deleteApp(string $app): void {
		unset($this->arrays[$app]);
	}

	public function getApps(): array {
		throw new \BadMethodCallException();
	}

	public function getKeys(string $app): array {
		throw new \BadMethodCallException();
	}

	public function searchKeys(string $app, string $prefix = '', bool $lazy = false): array {
		throw new \BadMethodCallException();
	}

	public function isSensitive(string $app, string $key, ?bool $lazy = false): bool {
		throw new \BadMethodCallException();
	}

	public function isLazy(string $app, string $key): bool {
		throw new \BadMethodCallException();
	}

	public function getAllValues(string $app, string $prefix = '', bool $filtered = false): array {
		throw new \BadMethodCallException();
	}

	public function searchValues(string $key, bool $lazy = false, ?int $typedAs = null): array {
		throw new \BadMethodCallException();
	}

	public function getValueString(string $app, string $key, string $default = '', bool $lazy = false): string {
		throw new \BadMethodCallException();
	}

	public function getValueInt(string $app, string $key, int $default = 0, bool $lazy = false): int {
		throw new \BadMethodCallException();
	}

	public function getValueFloat(string $app, string $key, float $default = 0, bool $lazy = false): float {
		throw new \BadMethodCallException();
	}

	public function getValueBool(string $app, string $key, bool $default = false, bool $lazy = false): bool {
		throw new \BadMethodCallException();
	}

	public function getValueType(string $app, string $key, ?bool $lazy = null): int {
		throw new \BadMethodCallException();
	}

	public function setValueString(string $app, string $key, string $value, bool $lazy = false, bool $sensitive = false): bool {
		throw new \BadMethodCallException();
	}

	public function setValueInt(string $app, string $key, int $value, bool $lazy = false, bool $sensitive = false): bool {
		throw new \BadMethodCallException();
	}

	public function setValueFloat(string $app, string $key, float $value, bool $lazy = false, bool $sensitive = false): bool {
		throw new \BadMethodCallException();
	}

	public function setValueBool(string $app, string $key, bool $value, bool $lazy = false): bool {
		throw new \BadMethodCallException();
	}

	public function updateSensitive(string $app, string $key, bool $sensitive): bool {
		throw new \BadMethodCallException();
	}

	public function updateLazy(string $app, string $key, bool $lazy): bool {
		throw new \BadMethodCallException();
	}

	public function getDetails(string $app, string $key): array {
		throw new \BadMethodCallException();
	}

	public function getKeyDetails(string $app, string $key): array {
		throw new \BadMethodCallException();
	}

	public function convertTypeToInt(string $type): int {
		throw new \BadMethodCallException();
	}

	public function convertTypeToString(int $type): string {
		throw new \BadMethodCallException();
	}

	public function clearCache(bool $reload = false): void {
	}

	public function getValues($app, $key) {
		throw new \BadMethodCallException();
	}

	public function getFilteredValues($app) {
		throw new \BadMethodCallException();
	}

	public function getAppInstalledVersions(bool $onlyEnabled = false): array {
		throw new \BadMethodCallException();
	}
}
