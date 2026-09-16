<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** @var array{pageTitle: string, emptyTitle: string, emptyHint: string, bands: list<array{label: string, links: list<array{title: string, url: string, iconUrl: string, subtitle: string}>}>} $_ */
if (function_exists('style')) {
	style('dashboard_links', 'links');
}
?>
<div id="dashboard-links-all" class="section">
	<h2><?php echo htmlspecialchars($_['pageTitle'], ENT_QUOTES, 'UTF-8'); ?></h2>
	<?php if ($_['bands'] === []) { ?>
		<div class="empty-content">
			<div class="empty-content__icon icon-link"></div>
			<h3 class="empty-content__name"><?php echo htmlspecialchars($_['emptyTitle'], ENT_QUOTES, 'UTF-8'); ?></h3>
			<p class="empty-content__hint"><?php echo htmlspecialchars($_['emptyHint'], ENT_QUOTES, 'UTF-8'); ?></p>
		</div>
	<?php } ?>
	<?php foreach ($_['bands'] as $band) { ?>
		<section class="dashboard-links-band">
			<?php if ($band['label'] !== '') { ?>
				<h3><?php echo htmlspecialchars($band['label'], ENT_QUOTES, 'UTF-8'); ?></h3>
			<?php } ?>
			<ul class="dashboard-links-list">
				<?php foreach ($band['links'] as $link) { ?>
					<li class="dashboard-links-item">
						<a href="<?php echo htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8'); ?>">
							<img src="<?php echo htmlspecialchars($link['iconUrl'], ENT_QUOTES, 'UTF-8'); ?>" alt="">
							<span class="dashboard-links-item-text">
								<span class="dashboard-links-item-title"><?php echo htmlspecialchars($link['title'], ENT_QUOTES, 'UTF-8'); ?></span>
								<span class="dashboard-links-item-subtitle"><?php echo htmlspecialchars($link['subtitle'], ENT_QUOTES, 'UTF-8'); ?></span>
							</span>
						</a>
					</li>
				<?php } ?>
			</ul>
		</section>
	<?php } ?>
</div>
