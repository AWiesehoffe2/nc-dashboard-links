// SPDX-FileCopyrightText: 2026 André Wiesehoff
// SPDX-License-Identifier: AGPL-3.0-or-later

import { createAppConfig } from '@nextcloud/vite-config'
import { join } from 'node:path'

const isProduction = process.env.NODE_ENV === 'production'

export default createAppConfig({
	admin: join(import.meta.dirname, 'src', 'admin.ts'),
}, {
	minify: isProduction,
	inlineCSS: true,
	extractLicenseInformation: true,
	thirdPartyLicense: false,
})
