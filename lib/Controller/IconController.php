<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Controller;

use OCA\DashboardLinks\Links\Icon;
use OCA\DashboardLinks\Links\Icons;
use OCA\DashboardLinks\Links\InvalidLink;
use OCA\DashboardLinks\Links\LinkUrls;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\EmptyContentSecurityPolicy;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\Response;
use OCP\Files\NotFoundException;
use OCP\IRequest;

final class IconController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly Icons $icons,
		private readonly LinkUrls $urls,
	) {
		parent::__construct($appName, $request);
	}

	#[FrontpageRoute(verb: 'POST', url: '/icons')]
	public function upload(): DataResponse {
		try {
			$icon = $this->icons->store($this->uploadedBytes());
			return new DataResponse([
				'icon' => (string)$icon,
				'url' => $this->urls->storedIconUrl($icon),
			]);
		} catch (InvalidLink $e) {
			return new DataResponse(
				['field' => $e->field, 'message' => $e->getMessage()],
				Http::STATUS_BAD_REQUEST,
			);
		}
	}

	#[FrontpageRoute(verb: 'GET', url: '/icons/{file}')]
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function show(string $file): Response {
		try {
			$icon = Icon::parse($file);
			$stored = $this->icons->open($icon);
		} catch (InvalidLink|NotFoundException) {
			return new NotFoundResponse();
		}

		$response = new FileDisplayResponse($stored);
		$response->addHeader('Content-Type', $this->mimeFor($icon));
		$response->addHeader('X-Content-Type-Options', 'nosniff');
		$response->addHeader('Cache-Control', 'private, max-age=31536000, immutable');
		$response->setContentSecurityPolicy(new EmptyContentSecurityPolicy());
		$response->addHeader('Content-Security-Policy', "sandbox; default-src 'none'");

		return $response;
	}

	/**
	 * @throws InvalidLink
	 */
	private function uploadedBytes(): string {
		$uploaded = $this->request->getUploadedFile('icon');
		if (!is_array($uploaded) || !isset($uploaded['tmp_name']) || !is_string($uploaded['tmp_name'])) {
			throw new InvalidLink('icon', 'icon upload is missing');
		}
		$error = $uploaded['error'] ?? \UPLOAD_ERR_OK;
		if ($error !== \UPLOAD_ERR_OK) {
			throw new InvalidLink('icon', 'icon upload failed');
		}
		$bytes = file_get_contents($uploaded['tmp_name']);
		if ($bytes === false) {
			throw new InvalidLink('icon', 'icon upload is unreadable');
		}

		return $bytes;
	}

	private function mimeFor(Icon $icon): string {
		$extension = pathinfo($icon->file, PATHINFO_EXTENSION);

		return match ($extension) {
			'png' => 'image/png',
			'svg' => 'image/svg+xml',
			'jpg' => 'image/jpeg',
			'webp' => 'image/webp',
			default => throw new \LogicException('Icon::parse already constrained the file name'),
		};
	}
}
