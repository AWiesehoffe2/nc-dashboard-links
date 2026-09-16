<!--
  SPDX-FileCopyrightText: 2026 André Wiesehoff
  SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## 1.1.0 - 2026-09-16

### Changed

- Catalog is one list plus optional custom categories. Featured, Company, and Reference lanes are gone.
- Tile subtitle is the host only. The widget title stays the translated app name.
- Admins pick a Nextcloud icon or upload one.
- Save shows a success note.
- Admin OCS body is `{revision, categories, links}`. Schema 1 catalogs migrate on read.

## 1.0.0 - 2026-09-16

First version of Company Links Dashboard (`dashboard_links`). This repository is a public reference for an app designed and implemented entirely with AI.

### Added

- Dashboard tile of admin-configured company links for Nextcloud 33–35 and PHP 8.2–8.5.
- `IAPIWidgetV2` tile with no dashboard JavaScript.
- iframe and redirect open modes, both through `/open/{id}` so bookmarks survive a mode change.
- Admin OCS `GET`/`PUT` `/ocs/v2.php/apps/dashboard_links/api/v1/catalog`.
- Catalog stored in one lazy `IAppConfig` key `catalog`.
- Admin settings Vue page with Save over the OCS envelope and optional browser-only External sites import.
- Privacy notice for iframe and redirect (browser request to the destination).
- Password confirmation on catalog save and icon upload.
- German translations (`l10n/de`).
