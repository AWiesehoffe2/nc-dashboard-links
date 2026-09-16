<!--
  SPDX-FileCopyrightText: 2026 Andrew Iesehoff
  SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - Unreleased

First version of Company Links Dashboard (`dashboard_links`).

### Added

- Dashboard tile of admin-configured company links for Nextcloud 33–35 and PHP 8.2–8.5.
- Three lanes: Featured, Company (`normal`), and Reference. The lane is the importance.
- `IAPIWidgetV2` tile with no dashboard JavaScript.
- iframe and redirect open modes, both through `/open/{id}` so bookmarks survive a mode change.
- Admin OCS `GET`/`PUT` `/ocs/v2.php/apps/dashboard_links/api/v1/catalog` with body `{revision, featured, normal, reference}`.
- Catalog stored in one lazy `IAppConfig` key `catalog`.
- Admin settings Vue page: three lanes, Save over the OCS envelope, optional browser-only External sites import.
