<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** @var array{href: string} $_ */
if (function_exists('style')) {
	style('dashboard_links', 'frame');
}
?>
<iframe
	class="dashboard-links-frame"
	src="<?php echo htmlspecialchars($_['href'], ENT_QUOTES, 'UTF-8'); ?>"
	title=""></iframe>
