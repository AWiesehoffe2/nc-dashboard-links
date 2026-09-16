# Arena rubric

Score each candidate 1–5 per criterion. Recommend one base. The base is the design a future maintainer can extend without breaking invariants. Prefer the smaller public API when two are tied.

1. **Illegal states.** Importance and open mode cannot disagree with storage, sort, or the iframe page. Non-https hrefs and remote icons cannot enter trusted code.
2. **Interface depth.** Few public operations hide parse, sort, open-mode resolution, and WidgetItem mapping. Callers do not coordinate stages.
3. **Nextcloud boundaries.** IBootstrap only, empty `load()`, IAPIWidget+V2+IIcon+IButton, settings in info.xml, IAppConfig lazy array, no `OCA\External\*`, CSRF on writes.
4. **Iframe vs redirect.** Tile never embeds. iframe is an owned page. redirect is https. Mode change does not orphan bookmarks if the design claims that.
5. **External import.** Browser OCS only, degradable, no live sync, no write to `external/sites`.
6. **Marketplace and versions.** PHP 8.2 (no typed class constants), NC 33–35, AGPL, category dashboard, max-version, no Nextcloud in the name.
7. **Red flags.** No shallow modules, no leaked wire/storage types, no temporal load/validate/save packages, no pass-through layers.
8. **Idempotent catalog write.** Two admins, retries, and a crash mid-save converge or fail closed.
