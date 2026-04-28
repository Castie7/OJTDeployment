// src/utils/formatters.ts
// Shared formatting helpers — import from here instead of redefining locally.

import type { Research } from '../types'

/**
 * Formats a date value into a human-readable string.
 * Handles raw strings, ISO dates, and PHP DateTime objects ({ date: "..." }).
 */
export function formatDate(dateStr?: any): string {
  if (!dateStr) return 'N/A'

  let dateVal = dateStr
  // Handle PHP DateTime object { date: "...", timezone: "..." }
  if (typeof dateStr === 'object' && dateStr.date) {
    dateVal = dateStr.date
  }

  try {
    const d = new Date(dateVal)
    if (isNaN(d.getTime())) return dateVal
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
  } catch {
    return dateVal
  }
}

/**
 * Converts a date-like value into a sortable timestamp.
 * Supports strings, numbers, Date instances, and PHP DateTime payloads.
 */
export function toTimestamp(value?: unknown): number {
  if (!value) return 0

  let raw: unknown = value
  if (typeof value === 'object' && value !== null && 'date' in (value as Record<string, unknown>)) {
    raw = (value as { date?: unknown }).date
  }

  if (raw instanceof Date) {
    const time = raw.getTime()
    return Number.isFinite(time) ? time : 0
  }

  if (typeof raw !== 'string' && typeof raw !== 'number') {
    return 0
  }

  const parsed = new Date(raw)
  const time = parsed.getTime()
  return Number.isFinite(time) ? time : 0
}

/**
 * Returns a new research list sorted with the newest matching timestamp first.
 */
export function sortResearchByNewest(
  items: Research[],
  fields: Array<keyof Research> = ['created_at', 'updated_at']
): Research[] {
  const getSortTime = (item: Research): number => {
    for (const field of fields) {
      const time = toTimestamp(item[field])
      if (time > 0) return time
    }

    return 0
  }

  return [...items].sort((a, b) => {
    const timeDiff = getSortTime(b) - getSortTime(a)
    if (timeDiff !== 0) return timeDiff
    return b.id - a.id
  })
}

/**
 * Returns a label and CSS classes for a research status badge.
 */
export function getStatusBadge(status: string) {
  switch (status) {
    case 'approved': return { label: '✅ Published', classes: 'bg-green-100 text-green-700 border-green-200' }
    case 'pending': return { label: '⏳ Pending', classes: 'bg-yellow-100 text-yellow-800 border-yellow-200' }
    case 'rejected': return { label: '❌ Rejected', classes: 'bg-red-100 text-red-700 border-red-200' }
    case 'archived': return { label: '🗑️ Archived', classes: 'bg-gray-200 text-gray-600 border-gray-300' }
    default: return { label: status, classes: 'bg-gray-100 text-gray-700 border-gray-200' }
  }
}

/**
 * Returns the image path based on the crop variation.
 */
export const getCropImage = (crop?: string): string => {
  const c = (crop || '').toLowerCase()
  if (c.includes('sweetpotato') || c.includes('sweet potato') || c.includes('kamote')) {
    return '/images/crops/sweetpotato.jpg'
  }
  if (c.includes('potato')) {
    return '/images/crops/potato.jpg'
  }
  if (c.includes('cassava') || c.includes('kamoteng kahoy')) {
    return '/images/crops/cassava.jpg'
  }
  if (c.includes('yam') || c.includes('ubi')) {
    return '/images/crops/yam.jpg'
  }
  if (c.includes('taro') || c.includes('gabi')) {
    return '/images/crops/taro.jpg'
  }
  return '/images/crops/default.jpg'
}

/**
 * Sanitizes a URL to prevent XSS via dangerous protocols.
 * Only allows http: and https: URLs. Returns empty string for anything else
 * (e.g. javascript:, data:, vbscript:, or malformed input).
 */
export function sanitizeUrl(url?: string | null): string {
  if (!url) return ''

  const trimmed = url.trim()
  if (trimmed === '') return ''

  try {
    const parsed = new URL(trimmed)
    if (parsed.protocol === 'http:' || parsed.protocol === 'https:') {
      return trimmed
    }
    return ''
  } catch {
    // Relative URLs or malformed — block them in link contexts
    return ''
  }
}
