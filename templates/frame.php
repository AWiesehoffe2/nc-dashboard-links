<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** @var array{href: string} $_ */
?>
<iframe src="<?php echo htmlspecialchars($_['href'], ENT_QUOTES, 'UTF-8'); ?>" style="width:100%;height:100vh;border:0;"></iframe>
