<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Andrew Iesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Tests\Unit\Links;

use OCA\DashboardLinks\Links\Icon;
use OCA\DashboardLinks\Links\Icons;
use OCA\DashboardLinks\Links\InvalidLink;
use OCA\DashboardLinks\Tests\Support\InMemoryAppDataFactory;
use PHPUnit\Framework\TestCase;

final class IconsTest extends TestCase {
	/** 1×1 transparent PNG */
	private const PNG_1X1 = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\nIDATx\x9cc\x00\x01\x00\x00\x05\x00\x01\r\n-\xb4\x00\x00\x00\x00IEND\xaeB`\x82";

	private Icons $icons;

	protected function setUp(): void {
		$this->icons = new Icons(new InMemoryAppDataFactory());
	}

	public function testStoreSamePngTwiceIsIdempotentContentAddress(): void {
		$first = $this->icons->store(self::PNG_1X1);
		$second = $this->icons->store(self::PNG_1X1);

		self::assertSame((string)$first, (string)$second);
		self::assertMatchesRegularExpression('/^[0-9a-f]{16}\.png$/', (string)$first);
		self::assertTrue($this->icons->exists($first));
	}

	public function testStoreOverMaxBytesThrowsInvalidLinkIcon(): void {
		try {
			$this->icons->store(str_repeat('x', Icons::MAX_BYTES + 1));
			self::fail('Icons::store accepted 262145 bytes');
		} catch (InvalidLink $e) {
			self::assertSame('icon', $e->field);
		}
	}

	public function testIconParseRejectsUrl(): void {
		try {
			Icon::parse('https://cdn.example.com/logo.png');
			self::fail('Icon::parse accepted a URL');
		} catch (InvalidLink $e) {
			self::assertSame('icon', $e->field);
		}
	}
}
