<!--
  SPDX-FileCopyrightText: 2026 André Wiesehoff
  SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcSettingsSection
		:name="t('dashboard_links', 'Company links')"
		:description="t('dashboard_links', 'Featured, Company, and Reference share one catalog. Save writes all three lanes. The Dashboard tile reads that same catalog.')">
		<NcNoteCard v-if="staleNotice" type="warning">
			{{ t('dashboard_links', 'The catalog was changed. The editor now shows the saved catalog.') }}
		</NcNoteCard>
		<NcNoteCard v-if="fieldErrors.length > 0" type="error">
			<ul class="dashboard-links-notes">
				<li v-for="(error, errorIndex) in fieldErrors" :key="errorIndex">
					{{ formatFieldError(error) }}
				</li>
			</ul>
		</NcNoteCard>
		<NcNoteCard v-if="saveError" type="error">
			{{ saveError }}
		</NcNoteCard>
		<NcNoteCard v-if="iconError" type="error">
			{{ iconError }}
		</NcNoteCard>
		<NcNoteCard v-for="(notice, noticeIndex) in notices" :key="'notice-' + noticeIndex" type="info">
			{{ notice }}
		</NcNoteCard>

		<div v-if="externalSitesAvailable" class="dashboard-links-toolbar">
			<NcButton :disabled="importing || saving" @click="importExternalSites">
				{{ t('dashboard_links', 'Import from External sites') }}
			</NcButton>
		</div>

		<section v-for="lane in lanes" :key="lane" class="dashboard-links-lane">
			<h3>{{ laneLabel(lane) }}</h3>
			<ul class="dashboard-links-list">
				<li v-for="(row, index) in catalog[lane]" :key="row.id" class="dashboard-links-row">
					<NcTextField
						class="dashboard-links-title"
						:label="t('dashboard_links', 'Title')"
						:modelValue="row.title"
						@update:modelValue="row.title = $event" />
					<NcTextField
						class="dashboard-links-href"
						:label="t('dashboard_links', 'URL')"
						:modelValue="row.href"
						placeholder="https://"
						@update:modelValue="row.href = $event" />
					<fieldset class="dashboard-links-mode">
						<legend>{{ t('dashboard_links', 'Open mode') }}</legend>
						<NcCheckboxRadioSwitch
							:name="'open-mode-' + row.id"
							type="radio"
							value="iframe"
							:modelValue="row.openMode"
							@update:modelValue="setOpenMode(row, $event)">
							{{ openModeLabel('iframe') }}
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch
							:name="'open-mode-' + row.id"
							type="radio"
							value="redirect"
							:modelValue="row.openMode"
							@update:modelValue="setOpenMode(row, $event)">
							{{ openModeLabel('redirect') }}
						</NcCheckboxRadioSwitch>
					</fieldset>
					<div class="dashboard-links-icon">
						<img
							v-if="previewUrl(row.icon)"
							class="dashboard-links-icon-preview"
							:src="previewUrl(row.icon)"
							alt="">
						<NcButton @click="pickIcon(lane, index)">
							{{ row.icon ? t('dashboard_links', 'Replace icon') : t('dashboard_links', 'Upload icon') }}
						</NcButton>
						<NcButton v-if="row.icon" variant="tertiary" @click="row.icon = null">
							{{ t('dashboard_links', 'Remove icon') }}
						</NcButton>
					</div>
					<NcCheckboxRadioSwitch
						type="switch"
						:modelValue="row.enabled"
						@update:modelValue="row.enabled = $event">
						{{ t('dashboard_links', 'Enabled') }}
					</NcCheckboxRadioSwitch>
					<NcSelect
						class="dashboard-links-lane-select"
						:inputLabel="t('dashboard_links', 'Lane')"
						:modelValue="laneOption(lane)"
						:options="laneOptions"
						:clearable="false"
						@update:modelValue="onLaneSelect(lane, index, $event)" />
					<div class="dashboard-links-row-actions">
						<NcButton
							variant="tertiary"
							:disabled="index === 0"
							@click="moveWithinLane(lane, index, -1)">
							{{ t('dashboard_links', 'Move up') }}
						</NcButton>
						<NcButton
							variant="tertiary"
							:disabled="index === catalog[lane].length - 1"
							@click="moveWithinLane(lane, index, 1)">
							{{ t('dashboard_links', 'Move down') }}
						</NcButton>
						<NcButton variant="tertiary" @click="removeRow(lane, index)">
							{{ t('dashboard_links', 'Remove') }}
						</NcButton>
					</div>
				</li>
			</ul>
			<NcButton @click="addRow(lane)">
				{{ t('dashboard_links', 'Add link') }}
			</NcButton>
		</section>

		<div class="dashboard-links-save">
			<NcButton variant="primary" :disabled="saving" @click="save">
				{{ t('dashboard_links', 'Save') }}
			</NcButton>
		</div>

		<input
			ref="fileInput"
			class="hidden-upload-input"
			type="file"
			accept="image/png,image/svg+xml,image/jpeg,image/webp,.png,.svg,.jpg,.jpeg,.webp"
			@change="onIconPicked">
	</NcSettingsSection>
</template>

<script setup lang="ts">
import type { CatalogEnvelope, FieldError, LaneKey, OpenMode, Row } from './types.ts'

import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'
import { ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { LANES } from './types.ts'

interface SelectOption {
	id: string
	label: string
}

const lanes = LANES
const catalog = ref(cloneEnvelope(loadState<CatalogEnvelope>('dashboard_links', 'catalog')))
const externalSitesAvailable = loadState<boolean>('dashboard_links', 'externalSitesAvailable', false)
const fieldErrors = ref<FieldError[]>([])
const notices = ref<string[]>([])
const saveError = ref<string | null>(null)
const iconError = ref<string | null>(null)
const staleNotice = ref(false)
const saving = ref(false)
const importing = ref(false)
const iconUrls = ref<Record<string, string>>({})
const fileInput = ref<HTMLInputElement | null>(null)
const pendingIcon = ref<{ lane: LaneKey, index: number } | null>(null)

const laneOptions: SelectOption[] = LANES.map((lane) => ({
	id: lane,
	label: laneLabel(lane),
}))

/**
 * Human-readable lane name.
 *
 * @param lane Lane wire key
 */
function laneLabel(lane: LaneKey): string {
	switch (lane) {
		case 'featured':
			return t('dashboard_links', 'Featured')
		case 'normal':
			return t('dashboard_links', 'Company')
		case 'reference':
			return t('dashboard_links', 'Reference')
		default: {
			const _exhaustive: never = lane
			return _exhaustive
		}
	}
}

/**
 * Human-readable open mode.
 *
 * @param mode iframe or redirect
 */
function openModeLabel(mode: OpenMode): string {
	switch (mode) {
		case 'iframe':
			return t('dashboard_links', 'Iframe')
		case 'redirect':
			return t('dashboard_links', 'Redirect')
		default: {
			const _exhaustive: never = mode
			return _exhaustive
		}
	}
}

/**
 * Select option for the row's current lane.
 *
 * @param lane Current lane
 */
function laneOption(lane: LaneKey): SelectOption {
	return {
		id: lane,
		label: laneLabel(lane),
	}
}

/**
 * Restrict a value to iframe or redirect.
 *
 * @param value Untrusted open mode
 */
function parseOpenMode(value: unknown): OpenMode {
	switch (value) {
		case 'iframe':
			return 'iframe'
		case 'redirect':
			return 'redirect'
		default:
			return 'iframe'
	}
}

/**
 * Restrict a value to a lane key.
 *
 * @param value Untrusted lane
 */
function parseLaneKey(value: string): LaneKey | null {
	switch (value) {
		case 'featured':
		case 'normal':
		case 'reference':
			return value
		default:
			return null
	}
}

/**
 * Assign an exhaustive open mode.
 *
 * @param row Editor row
 * @param value Radio value
 */
function setOpenMode(row: Row, value: unknown): void {
	row.openMode = parseOpenMode(value)
}

/**
 * Mint a new lowercase UUIDv4 row.
 */
function mintRow(): Row {
	return {
		id: crypto.randomUUID().toLowerCase(),
		title: '',
		href: '',
		openMode: 'iframe',
		icon: null,
		enabled: true,
	}
}

/**
 * Copy a row without an importance key.
 *
 * @param row Source row
 */
function cloneRow(row: Partial<Row>): Row {
	return {
		id: typeof row.id === 'string' ? row.id : crypto.randomUUID().toLowerCase(),
		title: typeof row.title === 'string' ? row.title : '',
		href: typeof row.href === 'string' ? row.href : '',
		openMode: parseOpenMode(row.openMode),
		icon: typeof row.icon === 'string' && row.icon !== '' ? row.icon : null,
		enabled: row.enabled === true,
	}
}

/**
 * Copy the three-lane envelope. Importance stays on the lane.
 *
 * @param raw Catalog from PHP or OCS
 */
function cloneEnvelope(raw: CatalogEnvelope): CatalogEnvelope {
	return {
		revision: typeof raw.revision === 'string' ? raw.revision : '',
		featured: Array.isArray(raw.featured) ? raw.featured.map(cloneRow) : [],
		normal: Array.isArray(raw.normal) ? raw.normal.map(cloneRow) : [],
		reference: Array.isArray(raw.reference) ? raw.reference.map(cloneRow) : [],
	}
}

/**
 * PUT body: lanes plus revision, no row-level importance.
 *
 * @param row Editor row
 */
function wireRow(row: Row): Row {
	return {
		id: row.id,
		title: row.title,
		href: row.href,
		openMode: row.openMode,
		icon: row.icon,
		enabled: row.enabled,
	}
}

/**
 * PUT envelope from the editor.
 *
 * @param envelope Editor catalog
 */
function wireEnvelope(envelope: CatalogEnvelope): CatalogEnvelope {
	return {
		revision: envelope.revision,
		featured: envelope.featured.map(wireRow),
		normal: envelope.normal.map(wireRow),
		reference: envelope.reference.map(wireRow),
	}
}

/**
 * @param lane Lane to append
 */
function addRow(lane: LaneKey): void {
	catalog.value[lane].push(mintRow())
}

/**
 * @param lane Lane containing the row
 * @param index Row index
 */
function removeRow(lane: LaneKey, index: number): void {
	catalog.value[lane].splice(index, 1)
}

/**
 * @param lane Lane containing the row
 * @param index Row index
 * @param direction -1 up, 1 down
 */
function moveWithinLane(lane: LaneKey, index: number, direction: -1 | 1): void {
	const next = index + direction
	const rows = catalog.value[lane]
	if (next < 0 || next >= rows.length) {
		return
	}
	const copy = [...rows]
	const [row] = copy.splice(index, 1)
	copy.splice(next, 0, row)
	catalog.value[lane] = copy
}

/**
 * @param from Current lane
 * @param index Row index
 * @param to Destination lane
 */
function moveToLane(from: LaneKey, index: number, to: LaneKey): void {
	if (from === to) {
		return
	}
	const [row] = catalog.value[from].splice(index, 1)
	if (row === undefined) {
		return
	}
	catalog.value[to].push(row)
}

/**
 * @param from Current lane
 * @param index Row index
 * @param selected NcSelect value
 */
function onLaneSelect(from: LaneKey, index: number, selected: unknown): void {
	const id = selectedLaneId(selected)
	if (id === null) {
		return
	}
	const to = parseLaneKey(id)
	if (to === null) {
		return
	}
	moveToLane(from, index, to)
}

/**
 * @param value NcSelect model
 */
function selectedLaneId(value: unknown): string | null {
	if (typeof value === 'string') {
		return value
	}
	if (value !== null && typeof value === 'object' && 'id' in value && typeof value.id === 'string') {
		return value.id
	}
	return null
}

/**
 * @param icon Stored file name
 */
function previewUrl(icon: string | null): string | null {
	if (icon === null || icon === '') {
		return null
	}
	return iconUrls.value[icon] ?? generateUrl('/apps/dashboard_links/icons/{file}', { file: icon })
}

/**
 * @param lane Lane containing the row
 * @param index Row index
 */
function pickIcon(lane: LaneKey, index: number): void {
	pendingIcon.value = { lane, index }
	fileInput.value?.click()
}

/**
 * POST /apps/dashboard_links/icons field icon.
 *
 * @param event File input change
 */
async function onIconPicked(event: Event): Promise<void> {
	const input = event.target as HTMLInputElement
	const file = input.files?.[0]
	const target = pendingIcon.value
	input.value = ''
	pendingIcon.value = null
	if (file === undefined || target === null) {
		return
	}

	const form = new FormData()
	form.append('icon', file)
	try {
		const { data } = await axios.post(generateUrl('/apps/dashboard_links/icons'), form)
		const uploaded = data as { icon?: unknown, url?: unknown }
		if (typeof uploaded.icon !== 'string') {
			iconError.value = t('dashboard_links', 'The icon could not be uploaded.')
			return
		}
		const row = catalog.value[target.lane][target.index]
		if (row === undefined) {
			return
		}
		row.icon = uploaded.icon
		if (typeof uploaded.url === 'string') {
			iconUrls.value = { ...iconUrls.value, [uploaded.icon]: uploaded.url }
		}
		iconError.value = null
	} catch (error: unknown) {
		const body = axiosBody(error)
		const message = body !== null && typeof body === 'object' && 'message' in body && typeof body.message === 'string'
			? body.message
			: t('dashboard_links', 'The icon could not be uploaded.')
		iconError.value = message
	}
}

/**
 * PUT the whole envelope. Never send importance on rows.
 */
async function save(): Promise<void> {
	saving.value = true
	fieldErrors.value = []
	saveError.value = null
	staleNotice.value = false
	try {
		const { data } = await axios.put(
			generateOcsUrl('/apps/dashboard_links/api/v1/catalog'),
			wireEnvelope(catalog.value),
		)
		const saved = asEnvelope(data)
		if (saved !== null) {
			catalog.value = saved
		}
	} catch (error: unknown) {
		const status = axiosStatus(error)
		const body = axiosBody(error)
		if (status === 400) {
			fieldErrors.value = asFieldErrors(body)
			if (fieldErrors.value.length === 0) {
				saveError.value = t('dashboard_links', 'The catalog could not be saved.')
			}
		} else if (status === 412) {
			const current = asEnvelope(body)
			if (current !== null) {
				catalog.value = current
				staleNotice.value = true
			} else {
				saveError.value = t('dashboard_links', 'The catalog could not be saved.')
			}
		} else {
			saveError.value = t('dashboard_links', 'The catalog could not be saved.')
		}
	} finally {
		saving.value = false
	}
}

/**
 * Browser-only import. Nothing is stored until Save.
 */
async function importExternalSites(): Promise<void> {
	importing.value = true
	notices.value = []
	try {
		const { data } = await axios.get(generateOcsUrl('/apps/external/api/v1/sites'))
		const sites = sitesFromExternalPayload(readOcsData(data))
		const existing = new Set<string>()
		for (const lane of LANES) {
			for (const row of catalog.value[lane]) {
				const normalized = normalizeHttps(row.href)
				if (normalized !== null) {
					existing.add(normalized)
				}
			}
		}

		let imported = 0
		let skippedScheme = 0
		let skippedDup = 0
		for (const site of sites) {
			const title = typeof site.name === 'string' ? site.name : ''
			const href = typeof site.url === 'string' ? site.url : ''
			const normalized = normalizeHttps(href)
			if (normalized === null) {
				skippedScheme += 1
				continue
			}
			if (existing.has(normalized)) {
				skippedDup += 1
				continue
			}
			existing.add(normalized)
			catalog.value.normal.push({
				id: crypto.randomUUID().toLowerCase(),
				title,
				href,
				openMode: site.redirect === true || site.redirect === 1 ? 'redirect' : 'iframe',
				icon: null,
				enabled: true,
			})
			imported += 1
		}

		if (skippedScheme > 0) {
			notices.value.push(t('dashboard_links', 'Skipped {count} External sites that are not https.', { count: skippedScheme }))
		}
		if (skippedDup > 0) {
			notices.value.push(t('dashboard_links', 'Skipped {count} External sites already in the catalog.', { count: skippedDup }))
		}
		if (imported > 0) {
			notices.value.push(t('dashboard_links', 'Added {count} External sites to Company. Save to store them.', { count: imported }))
		} else if (skippedScheme === 0 && skippedDup === 0) {
			notices.value.push(t('dashboard_links', 'No External sites to import.'))
		}
	} catch {
		notices.value = [t('dashboard_links', 'Could not load External sites.')]
	} finally {
		importing.value = false
	}
}

/**
 * @param error Field error from PUT 400
 */
function formatFieldError(error: FieldError): string {
	return t('dashboard_links', 'Row {index}, {field}: {message}', {
		index: error.index,
		field: error.field,
		message: error.message,
	})
}

/**
 * Match HttpsUrl::normalized(). Non-https returns null.
 *
 * @param raw URL text
 */
function normalizeHttps(raw: string): string | null {
	const value = raw.trim()
	if (value === '') {
		return null
	}
	let url: URL
	try {
		url = new URL(value)
	} catch {
		return null
	}
	if (url.protocol !== 'https:') {
		return null
	}
	if (url.username !== '' || url.password !== '') {
		return null
	}
	const host = url.hostname.toLowerCase()
	if (host === '') {
		return null
	}
	const port = url.port === '443' ? '' : url.port
	let path = url.pathname
	if (path === '/') {
		path = ''
	}
	return `https://${host}${port !== '' ? `:${port}` : ''}${path}${url.search}`
}

/**
 * @param payload OCS or raw body
 */
function readOcsData(payload: unknown): unknown {
	if (payload !== null && typeof payload === 'object' && 'ocs' in payload) {
		const ocs = payload.ocs
		if (ocs !== null && typeof ocs === 'object' && 'data' in ocs) {
			return ocs.data
		}
	}
	return payload
}

/**
 * @param payload OCS or raw catalog
 */
function asEnvelope(payload: unknown): CatalogEnvelope | null {
	const data = readOcsData(payload)
	if (data === null || typeof data !== 'object') {
		return null
	}
	const candidate = data as Partial<CatalogEnvelope>
	if (typeof candidate.revision !== 'string') {
		return null
	}
	return cloneEnvelope({
		revision: candidate.revision,
		featured: Array.isArray(candidate.featured) ? candidate.featured : [],
		normal: Array.isArray(candidate.normal) ? candidate.normal : [],
		reference: Array.isArray(candidate.reference) ? candidate.reference : [],
	})
}

/**
 * @param payload OCS 400 body
 */
function asFieldErrors(payload: unknown): FieldError[] {
	const data = readOcsData(payload)
	if (data === null || typeof data !== 'object' || !('errors' in data) || !Array.isArray(data.errors)) {
		return []
	}
	return data.errors.filter((item): item is FieldError => {
		return item !== null
			&& typeof item === 'object'
			&& typeof item.index === 'number'
			&& typeof item.field === 'string'
			&& typeof item.message === 'string'
	})
}

/**
 * @param payload External sites OCS body
 */
function sitesFromExternalPayload(payload: unknown): Array<{ name?: unknown, url?: unknown, redirect?: unknown }> {
	if (Array.isArray(payload)) {
		return payload
	}
	if (payload !== null && typeof payload === 'object' && 'sites' in payload && Array.isArray(payload.sites)) {
		return payload.sites
	}
	return []
}

/**
 * @param error Axios error
 */
function axiosStatus(error: unknown): number {
	if (typeof error === 'object' && error !== null && 'response' in error) {
		const response = error.response as { status?: number } | undefined
		return typeof response?.status === 'number' ? response.status : 0
	}
	return 0
}

/**
 * @param error Axios error
 */
function axiosBody(error: unknown): unknown {
	if (typeof error === 'object' && error !== null && 'response' in error) {
		const response = error.response as { data?: unknown } | undefined
		return response?.data
	}
	return undefined
}
</script>

<style scoped>
.dashboard-links-notes {
	margin: 0;
	padding-inline-start: 1.25rem;
}

.dashboard-links-toolbar,
.dashboard-links-save {
	display: flex;
	flex-wrap: wrap;
	gap: 0.5rem;
	margin-block: 1rem;
}

.dashboard-links-lane {
	margin-block-end: 2rem;
}

.dashboard-links-list {
	list-style: none;
	margin: 0 0 0.75rem;
	padding: 0;
}

.dashboard-links-row {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-end;
	gap: 0.75rem 1rem;
	margin-block-end: 1rem;
	padding-block-end: 1rem;
	border-block-end: 1px solid var(--color-border);
}

.dashboard-links-title,
.dashboard-links-href {
	flex: 1 1 16rem;
}

.dashboard-links-mode {
	border: 0;
	margin: 0;
	padding: 0;
}

.dashboard-links-icon,
.dashboard-links-row-actions {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 0.5rem;
}

.dashboard-links-icon-preview {
	width: 32px;
	height: 32px;
	object-fit: contain;
}

.dashboard-links-lane-select {
	min-width: 10rem;
}

.hidden-upload-input {
	position: absolute;
	width: 1px;
	height: 1px;
	overflow: hidden;
	clip: rect(0, 0, 0, 0);
}
</style>
