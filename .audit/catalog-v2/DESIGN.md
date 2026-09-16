<!--
  SPDX-FileCopyrightText: 2026 André Wiesehoff
  SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Catalog v2

## Problem

Featured, Company, and Reference read as three mystery lanes. The tile subtitle shows "Company · host". Icons are upload-only. Save is silent. German strings exist but the product still speaks in those three English lane names.

## Usage

Admin opens settings and sees one list. Add link. Optionally add a category and assign links. Pick a Nextcloud core icon or upload one. Save shows a success note. The tile lists enabled links in list order. Subtitle is the host. All links groups only when at least one category exists.

## Shape

Picked: a flat `links` list plus optional `categories`. `categoryId` is null for the default tree.

Rejected: a mandatory root category. That still forces a grouping the screenshot does not need.

```
schema 2
{ categories: [{id, title}], links: [{id, title, href, openMode, icon, categoryId, enabled}] }
icon = null | uploaded hash | core:<allowlisted path>
```

Schema 1 lanes migrate on read: drop importance, `categoryId` null, keep featured then normal then reference order. Next save writes schema 2.

## Synthesis decision

Parent pick. Two shapes compared. No four-model arena. The user already named the product.

## Tradeoffs accepted

We accept a category that still exists after its last link is moved, in exchange for not auto-deleting named groups.

## Alternatives considered

Always-on default category named Links. Lost because the default tree must have no category.

## Next implementation step

Done. Schema 2 is in `CatalogStore`. Schema 1 migrates on read.
