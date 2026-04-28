<script setup lang="ts">
import { computed, ref } from 'vue'
import type {
  AssistantAnalyticsResponse,
  AssistantFeedback,
  AssistantSearchMode,
  Research
} from '../../../types'
import { assistantService, researchService } from '../../../services'
import { getBaseUrl } from '../../../services/api'
import { formatDate, sanitizeUrl } from '../../../utils/formatters'
import { useAuthStore } from '../../../stores/auth'
import { useToast } from '../../../composables/useToast'

type ChatRole = 'user' | 'assistant'
type CitationFormat = 'apa' | 'mla' | 'chicago' | 'ieee' | 'harvard'

interface AssistantResult extends Research {
  _matchType: 'exact' | 'related'
  _snippet: string
}

interface ChatMessage {
  id: number
  role: ChatRole
  text: string
  mode?: AssistantSearchMode
  searchText?: string
  effectiveQuery?: string
  results?: AssistantResult[]
  suggestions?: string[]
  confidence?: number
  isStrongMatch?: boolean
  latencyMs?: number
  logId?: number
  feedback?: AssistantFeedback
}

const STOP_WORDS = new Set([
  'about',
  'and',
  'are',
  'for',
  'from',
  'into',
  'more',
  'that',
  'the',
  'this',
  'with',
  'your'
])

const authStore = useAuthStore()
const { showToast } = useToast()
const isAdmin = computed(() => authStore.currentUser?.role === 'admin')

const query = ref('')
const isLoading = ref(false)
const isStrictMode = ref(false)
const resultLimit = ref(8)
const citationFormat = ref<CitationFormat>('apa')
const authorFilter = ref('')
const cropVariationFilter = ref('')
const publicationStartDate = ref('')
const publicationEndDate = ref('')
const messageId = ref(1)
const contextKeywords = ref<string[]>([])

const showAnalytics = ref(false)
const isLoadingAnalytics = ref(false)
const analytics = ref<AssistantAnalyticsResponse | null>(null)
const feedbackBusyMessageIds = ref<number[]>([])

const createWelcomeMessage = (): ChatMessage => ({
  id: messageId.value++,
  role: 'assistant',
  text:
    'Ask a research question. You can also filter by author, publication date range, and crop variation.'
})

const hasStructuredFilters = computed(
  () =>
    authorFilter.value.trim() !== '' ||
    cropVariationFilter.value.trim() !== '' ||
    publicationStartDate.value !== '' ||
    publicationEndDate.value !== ''
)

const activeFilterSummary = computed(() => {
  const parts: string[] = []
  if (authorFilter.value.trim() !== '') {
    parts.push(`author: ${authorFilter.value.trim()}`)
  }
  if (cropVariationFilter.value.trim() !== '') {
    parts.push(`crop: ${cropVariationFilter.value.trim()}`)
  }
  if (publicationStartDate.value !== '' || publicationEndDate.value !== '') {
    const start = publicationStartDate.value || '...'
    const end = publicationEndDate.value || '...'
    parts.push(`date: ${start} to ${end}`)
  }

  return parts.join('; ')
})

const messages = ref<ChatMessage[]>([createWelcomeMessage()])

const quickPrompts = [
  '"sweet potato blight" control',
  'journal cassava nutrition 2020',
  'research by juan dela cruz',
  'isbn 978'
]

const citationFormats: Array<{ value: CitationFormat; label: string }> = [
  { value: 'apa', label: 'APA' },
  { value: 'mla', label: 'MLA' },
  { value: 'chicago', label: 'Chicago' },
  { value: 'ieee', label: 'IEEE' },
  { value: 'harvard', label: 'Harvard' }
]

const tokenize = (value: string): string[] => {
  const tokens = value
    .toLowerCase()
    .trim()
    .split(/\s+/)
    .filter(token => token.length >= 3)

  return Array.from(new Set(tokens)).slice(0, 10)
}

const toNumber = (value: unknown): number => {
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : 0
}

const escapeHtml = (value: string): string =>
  value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')

const escapeRegExp = (value: string): string =>
  value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')

const highlightText = (value: string, searchText: string): string => {
  const source = escapeHtml(value || '')
  const terms = tokenize(searchText).sort((a, b) => b.length - a.length)

  if (!source || terms.length === 0) {
    return source
  }

  let highlighted = source
  for (const term of terms) {
    const safeTerm = escapeRegExp(escapeHtml(term))
    highlighted = highlighted.replace(
      new RegExp(`(${safeTerm})`, 'ig'),
      '<mark class="assistant-highlight">$1</mark>'
    )
  }

  return highlighted
}

const buildHaystack = (item: Research): string => {
  return [
    item.title || '',
    item.author || '',
    item.subjects || '',
    item.physical_description || '',
    item.publisher || '',
    item.knowledge_type || '',
    item.isbn_issn || ''
  ]
    .join(' ')
    .toLowerCase()
}

const isExactMatch = (item: Research, tokens: string[]): boolean => {
  if (!tokens.length) return true
  const haystack = buildHaystack(item)
  return tokens.every(token => haystack.includes(token))
}

const buildSnippet = (item: Research, searchText: string): string => {
  const pool = [
    item.subjects || '',
    item.physical_description || '',
    item.publisher || '',
    item.knowledge_type || ''
  ]
    .join(' ')
    .replace(/\s+/g, ' ')
    .trim()

  if (!pool) return 'No summary available.'

  const terms = tokenize(searchText)
  const lower = pool.toLowerCase()
  const firstHit = terms
    .map(term => lower.indexOf(term.toLowerCase()))
    .filter(index => index >= 0)
    .sort((a, b) => a - b)[0]

  if (typeof firstHit === 'number') {
    const start = Math.max(0, firstHit - 50)
    const end = Math.min(pool.length, firstHit + 140)
    const prefix = start > 0 ? '...' : ''
    const suffix = end < pool.length ? '...' : ''
    return `${prefix}${pool.slice(start, end)}${suffix}`
  }

  return pool.slice(0, 140) + (pool.length > 140 ? '...' : '')
}

const mapResult = (
  item: Research,
  matchType: 'exact' | 'related',
  searchText: string
): AssistantResult => ({
  ...item,
  _matchType: matchType,
  _snippet: buildSnippet(item, searchText)
})

const buildFollowUpSuggestions = (items: Research[], searchText: string): string[] => {
  const usedTokens = new Set(tokenize(searchText))
  const keywordPool: string[] = []

  for (const item of items.slice(0, 8)) {
    const source = [item.subjects || '', item.knowledge_type || '', item.publisher || ''].join(' ')
    const words = tokenize(source).filter(token => !STOP_WORDS.has(token) && !usedTokens.has(token))
    keywordPool.push(...words)
  }

  const unique = Array.from(new Set(keywordPool)).slice(0, 3)
  return unique.map(word => `${searchText} ${word}`.trim())
}

const updateContextKeywords = (rawQuery: string, items: Research[]): void => {
  const fromQuery = tokenize(rawQuery).filter(token => !STOP_WORDS.has(token))
  const fromResults = items
    .slice(0, 6)
    .flatMap(item => tokenize(`${item.title || ''} ${item.subjects || ''}`))
    .filter(token => !STOP_WORDS.has(token))

  contextKeywords.value = Array.from(new Set([...fromQuery, ...fromResults])).slice(0, 8)
}

const buildEffectiveQuery = (rawQuery: string): string => {
  const normalized = rawQuery.trim()
  if (!normalized) return ''

  const tokens = tokenize(normalized)
  if (normalized.includes('"') || tokens.length >= 3) {
    return normalized
  }

  if (contextKeywords.value.length === 0) {
    return normalized
  }

  const hasContext = tokens.some(token => contextKeywords.value.includes(token))
  if (hasContext || normalized.length > 45) {
    return normalized
  }

  const context = contextKeywords.value.slice(0, 4).join(' ')
  const merged = `${normalized} ${context}`.trim()
  return merged.slice(0, 220)
}

const calculateConfidence = (
  items: Research[],
  effectiveQuery: string,
  mode: AssistantSearchMode
): number => {
  if (items.length === 0) return 0

  const terms = tokenize(effectiveQuery)
  const sample = items.slice(0, Math.min(5, items.length))

  let scoreTotal = 0
  sample.forEach((item, index) => {
    const haystack = buildHaystack(item)
    const hits = terms.filter(term => haystack.includes(term)).length
    const coverage = terms.length > 0 ? hits / terms.length : 1
    const relevanceBoost =
      typeof item.relevance_score === 'number' ? Math.min(1, item.relevance_score / 140) : 0
    const exactBoost = isExactMatch(item, terms) ? 0.25 : 0
    const rankPenalty = index * 0.08

    const score = Math.max(0, Math.min(1, coverage * 0.6 + relevanceBoost * 0.3 + exactBoost - rankPenalty))
    scoreTotal += score
  })

  let confidence = Math.round((scoreTotal / sample.length) * 100)
  if (mode === 'broad') {
    confidence = Math.max(0, confidence - 8)
  }

  return Math.max(0, Math.min(100, confidence))
}

const buildAssistantReply = (
  rawQuery: string,
  effectiveQuery: string,
  items: Research[],
  mode: AssistantSearchMode,
  latencyMs: number,
  appliedFilters: string
): ChatMessage => {
  const tokens = tokenize(effectiveQuery)
  const exact = items.filter(item => isExactMatch(item, tokens))
  const related = items.filter(item => !isExactMatch(item, tokens))

  const topResults = [
    ...exact.map(item => mapResult(item, 'exact', effectiveQuery)),
    ...related.map(item => mapResult(item, 'related', effectiveQuery))
  ].slice(0, resultLimit.value)

  const confidence = calculateConfidence(items, effectiveQuery, mode)
  const isStrongMatch = items.length > 0 && (confidence >= 65 || (confidence >= 55 && exact.length > 0))
  const modeLabel = mode === 'specific' ? 'Specific' : 'Broad'

  const hasQuery = rawQuery.trim() !== ''
  const targetLabel = hasQuery ? `"${rawQuery}"` : 'your selected filters'

  let summary = `${modeLabel} search found ${items.length} result(s) for ${targetLabel}.`
  if (appliedFilters.trim() !== '') {
    summary += ` Filters: ${appliedFilters}.`
  }
  if (hasQuery && effectiveQuery !== rawQuery) {
    summary += ` Context expanded query: "${effectiveQuery}".`
  }
  if (!authStore.isAuthenticated) {
    summary += ' As guest, you only see public records.'
  }

  if (items.length > 0) {
    summary += ` Showing top ${topResults.length}: ${exact.length} exact, ${related.length} related.`
    if (!isStrongMatch) {
      summary += ' Confidence is low, so refine with quoted phrases or author/ISBN.'
    }
  } else if (mode === 'specific') {
    summary += ' No strong match. Try broad mode or shorter keywords.'
  } else {
    summary += ' Try a more specific phrase in quotes.'
  }

  return {
    id: messageId.value++,
    role: 'assistant',
    text: summary,
    searchText: rawQuery,
    effectiveQuery,
    mode,
    results: topResults,
    suggestions: buildFollowUpSuggestions(items, effectiveQuery),
    confidence,
    isStrongMatch,
    latencyMs
  }
}

const setFeedbackBusy = (messageIdValue: number, busy: boolean): void => {
  if (busy) {
    if (!feedbackBusyMessageIds.value.includes(messageIdValue)) {
      feedbackBusyMessageIds.value.push(messageIdValue)
    }
    return
  }

  feedbackBusyMessageIds.value = feedbackBusyMessageIds.value.filter(id => id !== messageIdValue)
}

const isFeedbackBusy = (messageIdValue: number): boolean =>
  feedbackBusyMessageIds.value.includes(messageIdValue)

const logSearchForMessage = async (
  message: ChatMessage,
  rawQuery: string,
  effectiveQuery: string,
  mode: AssistantSearchMode,
  items: Research[]
): Promise<void> => {
  try {
    const response = await assistantService.logSearch({
      query: rawQuery,
      effective_query: effectiveQuery,
      mode,
      result_count: items.length,
      top_research_ids: items.map(item => item.id).slice(0, 20),
      latency_ms: message.latencyMs ?? 0,
      confidence: message.confidence,
      is_strong_match: message.isStrongMatch
    })

    if (response.status === 'success' && typeof response.log_id === 'number') {
      message.logId = response.log_id
    }
  } catch (_error) {
    // Non-blocking: search result should still render even if telemetry fails.
  }
}

const fetchAnalytics = async (force = false): Promise<void> => {
  if (!isAdmin.value || isLoadingAnalytics.value) return
  if (analytics.value && !force) return

  isLoadingAnalytics.value = true
  try {
    analytics.value = await assistantService.getAnalytics()
  } catch (_error) {
    analytics.value = null
  } finally {
    isLoadingAnalytics.value = false
  }
}

const toggleAnalytics = (): void => {
  showAnalytics.value = !showAnalytics.value
  if (showAnalytics.value) {
    void fetchAnalytics()
  }
}

const submitFeedback = async (message: ChatMessage, feedback: AssistantFeedback): Promise<void> => {
  if (!message.logId || message.feedback || isFeedbackBusy(message.id)) return

  setFeedbackBusy(message.id, true)
  try {
    await assistantService.submitFeedback({
      log_id: message.logId,
      feedback
    })
    message.feedback = feedback

    if (isAdmin.value && showAnalytics.value) {
      void fetchAnalytics(true)
    }
  } catch (_error) {
    // Ignore feedback save failures in UI; search history remains usable.
  } finally {
    setFeedbackBusy(message.id, false)
  }
}

const ask = async (): Promise<void> => {
  const rawQuery = query.value.trim()
  if (isLoading.value) return
  if (!rawQuery && !hasStructuredFilters.value) {
    showToast('Enter a query or set at least one filter.', 'warning')
    return
  }
  if (
    publicationStartDate.value !== '' &&
    publicationEndDate.value !== '' &&
    publicationStartDate.value > publicationEndDate.value
  ) {
    showToast('Start date cannot be later than end date.', 'warning')
    return
  }

  const mode: AssistantSearchMode = isStrictMode.value ? 'specific' : 'broad'
  const effectiveQuery = buildEffectiveQuery(rawQuery)
  const filterSummary = activeFilterSummary.value
  const userMessage = rawQuery !== ''
    ? (filterSummary ? `${rawQuery} (${filterSummary})` : rawQuery)
    : `Filter search (${filterSummary})`

  messages.value.push({
    id: messageId.value++,
    role: 'user',
    text: userMessage,
    mode
  })

  query.value = ''
  isLoading.value = true
  const startedAt = performance.now()

  try {
    const items = await researchService.getAll({
      search: effectiveQuery || undefined,
      strict: rawQuery !== '' ? isStrictMode.value : false,
      limit: resultLimit.value,
      author: authorFilter.value.trim() || undefined,
      crop_variation: cropVariationFilter.value.trim() || undefined,
      start_date: publicationStartDate.value || undefined,
      end_date: publicationEndDate.value || undefined
    })
    const latencyMs = Math.round(performance.now() - startedAt)

    const reply = buildAssistantReply(rawQuery, effectiveQuery, items, mode, latencyMs, filterSummary)
    messages.value.push(reply)
    if (rawQuery !== '') {
      updateContextKeywords(rawQuery, items)
    }

    const queryForLog = rawQuery !== '' ? rawQuery : `[filters] ${filterSummary}`
    const effectiveForLog = effectiveQuery !== '' ? effectiveQuery : queryForLog
    void logSearchForMessage(reply, queryForLog, effectiveForLog, mode, items)
    if (isAdmin.value && showAnalytics.value) {
      void fetchAnalytics(true)
    }
  } catch (_error) {
    messages.value.push({
      id: messageId.value++,
      role: 'assistant',
      text: 'Search failed. Please try again.'
    })
  } finally {
    isLoading.value = false
  }
}

const usePrompt = (prompt: string): void => {
  query.value = prompt
  void ask()
}

const resetMemory = (): void => {
  contextKeywords.value = []
}

const clearStructuredFilters = (): void => {
  authorFilter.value = ''
  cropVariationFilter.value = ''
  publicationStartDate.value = ''
  publicationEndDate.value = ''
}

const resetChat = (): void => {
  messages.value = [createWelcomeMessage()]
  query.value = ''
  resetMemory()
  clearStructuredFilters()
}

const openPdf = (item: Research): void => {
  if (!item.file_path) return
  window.open(`${getBaseUrl()}/research/view-pdf/${item.id}`, '_blank', 'noopener')
}

const openResult = (item: AssistantResult): void => {
  if (item.file_path) {
    openPdf(item)
    return
  }

  const safeLink = sanitizeUrl(item.link)
  if (safeLink) {
    window.open(safeLink, '_blank', 'noopener')
  }
}

const feedbackCount = (type: AssistantFeedback): number => {
  const match = analytics.value?.feedback.find(item => item.feedback === type)
  return toNumber(match?.count)
}

const splitAuthors = (author?: string): string[] =>
  (author || '')
    .split(/\s*;\s*|\s+and\s+|\s*&\s*/i)
    .map(item => item.trim())
    .filter(Boolean)

const toLastFirst = (name: string): string => {
  const trimmed = name.trim()
  if (!trimmed) return ''
  if (trimmed.includes(',')) return trimmed

  const parts = trimmed.split(/\s+/)
  if (parts.length <= 1) return trimmed

  const last = parts.pop()
  return `${last}, ${parts.join(' ')}`
}

const toApaName = (name: string): string => {
  const trimmed = name.trim()
  if (!trimmed) return ''
  if (trimmed.includes(',')) return trimmed

  const parts = trimmed.split(/\s+/)
  if (parts.length <= 1) return trimmed

  const last = parts.pop()
  const initials = parts
    .map(part => part.charAt(0).toUpperCase())
    .filter(Boolean)
    .map(initial => `${initial}.`)
    .join(' ')

  return initials ? `${last}, ${initials}` : (last || trimmed)
}

const getCitationYear = (value?: string): string => {
  if (!value) return 'n.d.'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return 'n.d.'
  return String(parsed.getFullYear())
}

const formatCitationAuthors = (author: string | undefined, format: CitationFormat): string => {
  const authors = splitAuthors(author)
  if (authors.length === 0) return 'Unknown author'

  if (format === 'apa') {
    const mapped = authors.map(toApaName)
    const first = mapped[0] || 'Unknown author'
    if (mapped.length === 1) return first
    if (mapped.length === 2) return `${first} & ${mapped[1] || ''}`
    return `${first} et al.`
  }

  if (format === 'mla') {
    const first = toLastFirst(authors[0] || '')
    if (authors.length === 1) return first
    if (authors.length === 2) return `${first}, and ${authors[1] || ''}`
    return `${first}, et al.`
  }

  if (format === 'chicago') {
    const first = toLastFirst(authors[0] || '')
    if (authors.length === 1) return first
    return `${first}, and ${authors.slice(1).join(', ')}`
  }

  if (format === 'ieee') {
    return authors.join(', ')
  }

  const first = authors[0] || 'Unknown author'
  if (authors.length === 1) return first
  if (authors.length === 2) return `${first} and ${authors[1] || ''}`
  return `${first} et al.`
}

const buildCitation = (item: Research, format: CitationFormat): string => {
  const author = formatCitationAuthors(item.author, format)
  const year = getCitationYear(item.publication_date)
  const title = (item.title || 'Untitled research').trim()
  const publisher = (item.publisher || '').trim()
  const sourceLink = (item.link || '').trim() || (item.file_path ? `${getBaseUrl()}/research/view-pdf/${item.id}` : '')

  if (format === 'apa') {
    let text = `${author} (${year}). ${title}.`
    if (publisher) text += ` ${publisher}.`
    if (sourceLink) text += ` ${sourceLink}`
    return text
  }

  if (format === 'mla') {
    let text = `${author}. "${title}."`
    if (publisher) text += ` ${publisher},`
    text += ` ${year}.`
    if (sourceLink) text += ` ${sourceLink}`
    return text
  }

  if (format === 'chicago') {
    let text = `${author}. "${title}."`
    if (publisher) text += ` ${publisher},`
    text += ` ${year}.`
    if (sourceLink) text += ` ${sourceLink}`
    return text
  }

  if (format === 'ieee') {
    let text = `${author}, "${title},"`
    if (publisher) text += ` ${publisher},`
    text += ` ${year}.`
    if (sourceLink) text += ` ${sourceLink}`
    return text
  }

  let text = `${author} (${year}) ${title}.`
  if (publisher) text += ` ${publisher}.`
  if (sourceLink) text += ` ${sourceLink}`
  return text
}

const copyTextFallback = (value: string): void => {
  const textarea = document.createElement('textarea')
  textarea.value = value
  textarea.setAttribute('readonly', 'readonly')
  textarea.style.position = 'absolute'
  textarea.style.left = '-9999px'
  document.body.appendChild(textarea)
  textarea.select()
  document.execCommand('copy')
  document.body.removeChild(textarea)
}

const copyCitation = async (item: AssistantResult): Promise<void> => {
  const citation = buildCitation(item, citationFormat.value)
  const formatLabel =
    citationFormats.find(option => option.value === citationFormat.value)?.label ||
    citationFormat.value.toUpperCase()

  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(citation)
    } else {
      copyTextFallback(citation)
    }
    showToast(`${formatLabel} citation copied.`, 'success')
  } catch (_error) {
    showToast('Could not copy citation automatically. Copy it manually from the dialog.', 'warning')
    window.prompt('Copy citation:', citation)
  }
}
</script>

<template>
  <div class="space-y-4">
    <section class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-xl font-bold text-gray-900">Research Assistant</h1>
          <p class="text-sm text-gray-600 mt-1">
            Chat and search within this system only. No external AI source is used.
          </p>
          <p class="text-xs text-gray-500 mt-1">
            Tip: use quotes for exact phrase match. Example: "sweet potato blight".
          </p>
          <p class="text-xs text-gray-500 mt-1">
            You can also run filter-only searches using author, crop variation, and publication date range.
          </p>
          <p v-if="contextKeywords.length" class="text-xs text-gray-500 mt-1">
            Memory context: {{ contextKeywords.join(', ') }}
          </p>
          <p v-if="hasStructuredFilters" class="text-xs text-emerald-700 mt-1">
            Active filters: {{ activeFilterSummary }}
          </p>
        </div>

        <div class="flex items-center gap-2">
          <button
            v-if="contextKeywords.length"
            @click="resetMemory"
            class="text-xs px-3 py-2 rounded border border-gray-300 text-gray-600 hover:bg-gray-50"
          >
            Clear Memory
          </button>
          <button
            v-if="isAdmin"
            @click="toggleAnalytics"
            class="text-xs px-3 py-2 rounded border border-blue-300 text-blue-700 hover:bg-blue-50"
          >
            {{ showAnalytics ? 'Hide Analytics' : 'Show Analytics' }}
          </button>
          <button
            @click="resetChat"
            class="text-xs px-3 py-2 rounded border border-gray-300 text-gray-600 hover:bg-gray-50"
          >
            Clear Chat
          </button>
          <button
            v-if="hasStructuredFilters"
            @click="clearStructuredFilters"
            class="text-xs px-3 py-2 rounded border border-gray-300 text-gray-600 hover:bg-gray-50"
          >
            Clear Filters
          </button>
        </div>
      </div>

      <div class="mt-3 flex flex-wrap items-center gap-3">
        <div class="inline-flex rounded-lg border border-gray-300 overflow-hidden">
          <button
            @click="isStrictMode = true"
            :class="[
              'px-3 py-1.5 text-xs font-medium',
              isStrictMode
                ? 'bg-emerald-600 text-white'
                : 'bg-white text-gray-700 hover:bg-gray-50'
            ]"
          >
            Specific
          </button>
          <button
            @click="isStrictMode = false"
            :class="[
              'px-3 py-1.5 text-xs font-medium',
              !isStrictMode
                ? 'bg-emerald-600 text-white'
                : 'bg-white text-gray-700 hover:bg-gray-50'
            ]"
          >
            Broad
          </button>
        </div>

        <label class="text-xs text-gray-600 flex items-center gap-2">
          Max results
          <select
            v-model.number="resultLimit"
            class="border border-gray-300 rounded px-2 py-1 text-xs"
          >
            <option :value="5">5</option>
            <option :value="8">8</option>
            <option :value="12">12</option>
            <option :value="20">20</option>
          </select>
        </label>

        <label class="text-xs text-gray-600 flex items-center gap-2">
          Citation
          <select
            v-model="citationFormat"
            class="border border-gray-300 rounded px-2 py-1 text-xs"
          >
            <option
              v-for="option in citationFormats"
              :key="option.value"
              :value="option.value"
            >
              {{ option.label }}
            </option>
          </select>
        </label>
      </div>

      <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2">
        <input
          v-model="authorFilter"
          type="text"
          placeholder="Author"
          class="rounded border border-gray-300 px-2.5 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500"
        />
        <input
          v-model="cropVariationFilter"
          type="text"
          placeholder="Crop variation"
          class="rounded border border-gray-300 px-2.5 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500"
        />
        <input
          v-model="publicationStartDate"
          type="date"
          class="rounded border border-gray-300 px-2.5 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500"
        />
        <input
          v-model="publicationEndDate"
          type="date"
          class="rounded border border-gray-300 px-2.5 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500"
        />
      </div>
    </section>

    <section
      v-if="isAdmin && showAnalytics"
      class="bg-white border border-blue-100 rounded-xl p-4 shadow-sm space-y-3"
    >
      <div class="flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-900">Assistant Analytics</h2>
        <button
          @click="fetchAnalytics(true)"
          class="text-xs px-2.5 py-1 rounded border border-blue-200 text-blue-700 hover:bg-blue-50"
        >
          Refresh
        </button>
      </div>

      <div v-if="isLoadingAnalytics" class="text-sm text-gray-500">
        Loading analytics...
      </div>

      <template v-else-if="analytics">
        <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
          <div class="p-2 rounded-lg border border-gray-200 bg-gray-50">
            <p class="text-[11px] text-gray-500">Total Queries</p>
            <p class="text-lg font-semibold">{{ toNumber(analytics.summary.total_queries) }}</p>
          </div>
          <div class="p-2 rounded-lg border border-gray-200 bg-gray-50">
            <p class="text-[11px] text-gray-500">Zero Results</p>
            <p class="text-lg font-semibold">{{ toNumber(analytics.summary.zero_results) }}</p>
          </div>
          <div class="p-2 rounded-lg border border-gray-200 bg-gray-50">
            <p class="text-[11px] text-gray-500">Avg Latency</p>
            <p class="text-lg font-semibold">{{ toNumber(analytics.summary.avg_latency_ms) }} ms</p>
          </div>
          <div class="p-2 rounded-lg border border-gray-200 bg-gray-50">
            <p class="text-[11px] text-gray-500">Slow Queries</p>
            <p class="text-lg font-semibold">{{ toNumber(analytics.summary.slow_queries) }}</p>
          </div>
          <div class="p-2 rounded-lg border border-gray-200 bg-gray-50">
            <p class="text-[11px] text-gray-500">Feedback</p>
            <p class="text-sm font-semibold">
              {{ feedbackCount('helpful') }} / {{ feedbackCount('not_helpful') }}
            </p>
          </div>
        </div>

        <div class="grid md:grid-cols-2 gap-3">
          <div class="border border-gray-200 rounded-lg p-3">
            <p class="text-xs font-semibold text-gray-600 mb-2">Top Queries</p>
            <div v-if="analytics.top_queries.length === 0" class="text-xs text-gray-500">
              No query logs yet.
            </div>
            <button
              v-for="item in analytics.top_queries"
              :key="`top-${item.effective_query}`"
              @click="usePrompt(item.effective_query)"
              class="w-full text-left text-xs px-2 py-1 rounded hover:bg-emerald-50 transition flex items-center justify-between"
            >
              <span class="truncate mr-2">{{ item.effective_query }}</span>
              <span class="text-gray-500">{{ toNumber(item.count) }}</span>
            </button>
          </div>

          <div class="border border-gray-200 rounded-lg p-3">
            <p class="text-xs font-semibold text-gray-600 mb-2">Zero-Result Queries</p>
            <div v-if="analytics.zero_result_queries.length === 0" class="text-xs text-gray-500">
              No zero-result queries.
            </div>
            <button
              v-for="item in analytics.zero_result_queries"
              :key="`zero-${item.effective_query}`"
              @click="usePrompt(item.effective_query)"
              class="w-full text-left text-xs px-2 py-1 rounded hover:bg-amber-50 transition flex items-center justify-between"
            >
              <span class="truncate mr-2">{{ item.effective_query }}</span>
              <span class="text-gray-500">{{ toNumber(item.count) }}</span>
            </button>
          </div>
        </div>
      </template>

      <p v-else class="text-sm text-gray-500">
        Analytics not available right now.
      </p>
    </section>

    <section class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
      <div class="h-[55vh] overflow-y-auto p-4 space-y-4 custom-scrollbar">
        <div
          v-for="message in messages"
          :key="message.id"
          :class="[
            'max-w-3xl rounded-lg px-4 py-3 text-sm',
            message.role === 'user'
              ? 'ml-auto bg-emerald-600 text-white'
              : 'bg-gray-100 text-gray-800'
          ]"
        >
          <div class="flex items-center justify-between gap-2">
            <p>{{ message.text }}</p>
            <span
              v-if="message.role === 'user' && message.mode"
              class="text-[10px] uppercase px-2 py-0.5 rounded bg-white/20"
            >
              {{ message.mode }}
            </span>
          </div>

          <div v-if="message.results && message.results.length" class="mt-3 space-y-2">
            <div
              v-for="item in message.results"
              :key="item.id"
              :class="[
                'bg-white border border-gray-200 rounded-lg p-3 text-gray-800 transition',
                item.file_path || item.link
                  ? 'cursor-pointer hover:border-emerald-300 hover:bg-emerald-50/30'
                  : ''
              ]"
              @click="openResult(item)"
            >
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p
                    class="font-semibold"
                    v-html="highlightText(item.title, message.effectiveQuery || message.searchText || '')"
                  ></p>
                  <p class="text-xs text-gray-500">by {{ item.author }}</p>
                </div>
                <div class="flex items-center gap-2">
                  <span
                    class="text-[10px] uppercase px-2 py-1 rounded font-semibold"
                    :class="
                      item._matchType === 'exact'
                        ? 'bg-emerald-100 text-emerald-700'
                        : 'bg-amber-100 text-amber-700'
                    "
                  >
                    {{ item._matchType }}
                  </span>
                  <span class="text-xs px-2 py-1 rounded bg-emerald-50 text-emerald-700">
                    {{ item.knowledge_type || 'Research' }}
                  </span>
                </div>
              </div>

              <p
                class="text-xs text-gray-600 mt-2 line-clamp-3"
                v-html="highlightText(item._snippet, message.effectiveQuery || message.searchText || '')"
              ></p>

              <div class="mt-2 flex items-center justify-between">
                <span class="text-[11px] text-gray-500">{{ formatDate(item.publication_date) }}</span>
                <div class="flex items-center gap-3">
                  <button
                    @click.stop="copyCitation(item)"
                    class="text-xs font-medium text-indigo-600 hover:text-indigo-800"
                  >
                    Cite
                  </button>
                  <button
                    v-if="item.file_path"
                    @click.stop="openPdf(item)"
                    class="text-xs font-medium text-blue-600 hover:text-blue-800"
                  >
                    Open PDF
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div
            v-if="message.role === 'assistant' && typeof message.confidence === 'number'"
            class="mt-3 flex flex-wrap items-center gap-2 text-[11px] text-gray-500"
          >
            <span class="px-2 py-0.5 rounded bg-white border border-gray-200">
              Confidence {{ message.confidence }}%
            </span>
            <span
              class="px-2 py-0.5 rounded border"
              :class="
                message.isStrongMatch
                  ? 'bg-emerald-50 border-emerald-200 text-emerald-700'
                  : 'bg-amber-50 border-amber-200 text-amber-700'
              "
            >
              {{ message.isStrongMatch ? 'strong match' : 'low confidence' }}
            </span>
            <span class="px-2 py-0.5 rounded bg-white border border-gray-200">
              {{ message.latencyMs || 0 }} ms
            </span>

            <div v-if="message.logId" class="ml-auto flex items-center gap-1">
              <button
                class="px-2 py-1 rounded border text-[11px]"
                :class="
                  message.feedback === 'helpful'
                    ? 'bg-emerald-100 border-emerald-300 text-emerald-700'
                    : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'
                "
                :disabled="!!message.feedback || isFeedbackBusy(message.id)"
                @click.stop="submitFeedback(message, 'helpful')"
              >
                Helpful
              </button>
              <button
                class="px-2 py-1 rounded border text-[11px]"
                :class="
                  message.feedback === 'not_helpful'
                    ? 'bg-red-100 border-red-300 text-red-700'
                    : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'
                "
                :disabled="!!message.feedback || isFeedbackBusy(message.id)"
                @click.stop="submitFeedback(message, 'not_helpful')"
              >
                Not helpful
              </button>
            </div>
          </div>

          <div v-if="message.suggestions && message.suggestions.length" class="mt-3 flex flex-wrap gap-2">
            <button
              v-for="suggestion in message.suggestions"
              :key="`${message.id}-${suggestion}`"
              @click.stop="usePrompt(suggestion)"
              class="text-[11px] px-2 py-1 rounded border border-emerald-200 text-emerald-700 bg-emerald-50 hover:bg-emerald-100 transition"
            >
              {{ suggestion }}
            </button>
          </div>
        </div>

        <div v-if="isLoading" class="text-sm text-gray-500">Searching local database...</div>
      </div>

      <div class="border-t border-gray-200 p-3 bg-gray-50">
        <div class="flex flex-wrap gap-2 mb-3">
          <button
            v-for="prompt in quickPrompts"
            :key="prompt"
            @click="usePrompt(prompt)"
            class="text-xs px-2 py-1 rounded border border-gray-300 text-gray-600 hover:bg-gray-100"
          >
            {{ prompt }}
          </button>
        </div>

        <form class="flex gap-2" @submit.prevent="ask">
          <input
            v-model="query"
            type="text"
            placeholder="Ask about a topic, title, author, ISBN, or keyword (optional when filters are set)..."
            class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
          />
          <button
            type="submit"
            :disabled="isLoading || (!query.trim() && !hasStructuredFilters)"
            class="rounded-lg bg-emerald-600 hover:bg-emerald-700 disabled:bg-emerald-300 text-white px-4 py-2 text-sm font-medium"
          >
            Send
          </button>
        </form>
      </div>
    </section>
  </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}

.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}

:deep(mark.assistant-highlight) {
  background: #fde68a;
  color: #78350f;
  padding: 0 2px;
  border-radius: 2px;
}
</style>
