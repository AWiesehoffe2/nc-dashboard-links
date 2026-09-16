<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Andrew Iesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** @var array{bands: list<array{label: string, links: list<array{title: string, url: string, iconUrl: string, subtitle: string}>}>} $_ */
?>
<div id="dashboard-links-all">
	<?php foreach ($_['bands'] as $band) { ?>
		<section>
			<h2><?php echo htmlspecialchars($band['label'], ENT_QUOTES, 'UTF-8'); ?></h2>
			<ul>
				<?php foreach ($band['links'] as $link) { ?>
					<li>
						<a href="<?php echo htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8'); ?>">
							<img src="<?php echo htmlspecialchars($link['iconUrl'], ENT_QUOTES, 'UTF-8'); ?>" alt="">
							<?php echo htmlspecialchars($link['title'], ENT_QUOTES, 'UTF-8'); ?>
							<span><?php echo htmlspecialchars($link['subtitle'], ENT_QUOTES, 'UTF-8'); ?></span>
						</a>
					</li>
				<?php } ?>
			</ul>
		</section>
	<?php } ?>
</div>
