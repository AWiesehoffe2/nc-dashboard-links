// SPDX-FileCopyrightText: 2026 Andrew Iesehoff
// SPDX-License-Identifier: AGPL-3.0-or-later

export type OpenMode = 'iframe' | 'redirect'

export type LaneKey = 'featured' | 'normal' | 'reference'

export interface Row {
	id: string
	title: string
	href: string
	openMode: OpenMode
	icon: string | null
	enabled: boolean
}

export interface CatalogEnvelope {
	revision: string
	featured: Row[]
	normal: Row[]
	reference: Row[]
}

export interface FieldError {
	index: number
	field: string
	message: string
}

export const LANES: readonly LaneKey[] = ['featured', 'normal', 'reference']
