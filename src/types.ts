// SPDX-FileCopyrightText: 2026 André Wiesehoff
// SPDX-License-Identifier: AGPL-3.0-or-later

export type OpenMode = 'iframe' | 'redirect'

export interface Category {
	id: string
	title: string
}

export interface Row {
	id: string
	title: string
	href: string
	openMode: OpenMode
	icon: string | null
	categoryId: string | null
	enabled: boolean
}

export interface CatalogEnvelope {
	revision: string
	categories: Category[]
	links: Row[]
}

export interface CoreIconChoice {
	id: string
	url: string
	label: string
}

export interface FieldError {
	index: number
	field: string
	message: string
}
