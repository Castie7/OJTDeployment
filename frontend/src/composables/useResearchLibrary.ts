import { ref, computed, watch, onMounted } from 'vue'
import { researchService } from '../services'
import { useToast } from './useToast'
import { useErrorHandler } from './useErrorHandler'
import { debounce } from '../utils/debounce'
import type { Research } from '../types'


export function useResearchLibrary(emit: (event: 'update-stats', count: number) => void) {

  // --- STATE ---
  const researches = ref<Research[]>([])

  const searchQuery = ref('')
  const selectedType = ref('') // Dropdown Filter
  const startDate = ref('') // Date Filter
  const endDate = ref('') // Date Filter


  const showArchived = ref(false)
  const viewMode = ref<'list' | 'grid'>('list')
  const selectedResearch = ref<Research | null>(null)

  // UI State
  const isLoading = ref(false)
  const { showToast } = useToast()
  const { handleError } = useErrorHandler()
  const confirmModal = ref({
    show: false,
    id: null as number | null,
    action: '',
    title: '',
    subtext: '',
    isProcessing: false
  })

  // Pagination State
  const currentPage = ref(1)
  const itemsPerPage = 10
  const sortField = ref<'' | 'title' | 'knowledge_type' | 'publication_date' | 'shelf_location'>('')
  const sortDirection = ref<'asc' | 'desc'>('desc')

  // --- HELPERS ---


  const formatSimpleDate = (dateStr?: string) => {
    if (!dateStr) return 'N/A'
    return new Date(dateStr).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
  }

  const toDateTimestamp = (value: unknown): number | null => {
    if (!value) return null

    let raw: unknown = value
    if (typeof value === 'object' && value !== null && 'date' in (value as Record<string, unknown>)) {
      raw = (value as { date?: unknown }).date
    }

    if (raw instanceof Date) {
      const time = raw.getTime()
      return Number.isFinite(time) ? time : null
    }

    if (typeof raw !== 'string' && typeof raw !== 'number') {
      return null
    }

    const parsed = new Date(raw)
    const time = parsed.getTime()
    return Number.isFinite(time) ? time : null
  }

  // --- API FETCH ---
  const fetchResearches = async () => {
    isLoading.value = true
    try {
      const filters = {
        start_date: startDate.value,
        end_date: endDate.value,
        search: searchQuery.value
      }

      researches.value = showArchived.value
        ? await researchService.getArchived({
          start_date: startDate.value,
          end_date: endDate.value
        })
        : await researchService.getAll(filters)

      if (!showArchived.value) {
        emit('update-stats', researches.value.length)
      }

    } catch (error: any) {
      if (showArchived.value && error.response?.status === 403) {
        showToast('Access Denied to Archives', 'error')
      } else {
        handleError(error, 'Failed to load data')
      }
    } finally {
      isLoading.value = false
    }
  }

  // --- FILTERING LOGIC ---
  const filteredResearches = computed(() => {
    return researches.value.filter(item => {
      // A. Search Query
      // For approved library results, search is handled server-side (DB-ranked).
      // For archived view, keep client-side search behavior.
      const q = searchQuery.value.toLowerCase().trim()
      let matchesSearch = true
      if (showArchived.value && q !== '') {
        matchesSearch =
          item.title.toLowerCase().includes(q) ||
          item.author.toLowerCase().includes(q) ||
          (!!item.isbn_issn && item.isbn_issn.toLowerCase().includes(q)) ||
          (!!item.subjects && item.subjects.toLowerCase().includes(q))
      }

      // B. Knowledge Type Filter
      const matchesType = selectedType.value === '' ||
        (item.knowledge_type && item.knowledge_type.includes(selectedType.value))

      return matchesSearch && matchesType
    })
  })

  const sortedResearches = computed(() => {
    const list = [...filteredResearches.value]
    if (!sortField.value) return list

    if (sortField.value === 'title') {
      return list.sort((a, b) => {
        const aTitle = (a.title || '').trim().toLowerCase()
        const bTitle = (b.title || '').trim().toLowerCase()

        if (aTitle === '' && bTitle === '') return 0
        if (aTitle === '') return 1
        if (bTitle === '') return -1

        return sortDirection.value === 'asc'
          ? aTitle.localeCompare(bTitle)
          : bTitle.localeCompare(aTitle)
      })
    }

    if (sortField.value === 'knowledge_type') {
      return list.sort((a, b) => {
        const aType = (a.knowledge_type || '').trim().toLowerCase()
        const bType = (b.knowledge_type || '').trim().toLowerCase()

        if (aType === '' && bType === '') return 0
        if (aType === '') return 1
        if (bType === '') return -1

        return sortDirection.value === 'asc'
          ? aType.localeCompare(bType)
          : bType.localeCompare(aType)
      })
    }

    if (sortField.value === 'publication_date') {
      return list.sort((a, b) => {
        const aTime = toDateTimestamp(a.publication_date)
        const bTime = toDateTimestamp(b.publication_date)

        if (aTime === null && bTime === null) return 0
        if (aTime === null) return 1
        if (bTime === null) return -1

        return sortDirection.value === 'asc' ? aTime - bTime : bTime - aTime
      })
    }

    return list.sort((a, b) => {
      const aLocation = (a.shelf_location || '').trim().toLowerCase()
      const bLocation = (b.shelf_location || '').trim().toLowerCase()

      if (aLocation === '' && bLocation === '') return 0
      if (aLocation === '') return 1
      if (bLocation === '') return -1

      return sortDirection.value === 'asc'
        ? aLocation.localeCompare(bLocation)
        : bLocation.localeCompare(aLocation)
    })
  })

  const paginatedResearches = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage
    const end = start + itemsPerPage
    return sortedResearches.value.slice(start, end)
  })

  const totalPages = computed(() => Math.ceil(sortedResearches.value.length / itemsPerPage))

  // --- ACTIONS ---
  const nextPage = () => { if (currentPage.value < totalPages.value) currentPage.value++ }
  const prevPage = () => { if (currentPage.value > 1) currentPage.value-- }

  const toggleSort = (field: 'title' | 'knowledge_type' | 'publication_date' | 'shelf_location') => {
    if (sortField.value === field) {
      sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
    } else {
      sortField.value = field
      sortDirection.value = field === 'publication_date' ? 'desc' : 'asc'
    }
    currentPage.value = 1
  }

  const openResearch = async (item: Research) => {
    selectedResearch.value = item

    if (showArchived.value) {
      return
    }

    try {
      await researchService.trackView(item.id)
      item.view_count = (item.view_count ?? 0) + 1
    } catch (error) {
      console.error('Failed to track view count', error)
    }
  }

  const requestArchiveToggle = (item: Research) => {
    const action = item.status === 'archived' ? 'Restore' : 'Archive'
    confirmModal.value = {
      show: true,
      id: item.id,
      action: action,
      title: action === 'Archive' ? 'Move to Trash?' : 'Restore Research?',
      subtext: action === 'Archive' ? `Remove "${item.title}"?` : `Restore "${item.title}"?`,
      isProcessing: false
    }
  }

  const executeArchiveToggle = async () => {
    if (!confirmModal.value.id || confirmModal.value.isProcessing) return

    confirmModal.value.isProcessing = true
    try {
      if (confirmModal.value.action === 'Restore') {
        await researchService.restore(confirmModal.value.id)
      } else {
        await researchService.archive(confirmModal.value.id)
      }

      fetchResearches()
      showToast(`Item ${confirmModal.value.action}d successfully!`, "success")
      confirmModal.value.show = false

    } catch (error: any) {
      handleError(error, 'Failed to update status')
    } finally {
      confirmModal.value.isProcessing = false
    }
  }

  // --- WATCHERS ---

  // Reload when switching between Active/Archived
  watch(showArchived, () => {
    currentPage.value = 1
    fetchResearches()
  })

  // Debounce search/date-filter changes that trigger API calls
  const debouncedFetch = debounce(() => {
    currentPage.value = 1
    fetchResearches()
  }, 400)

  watch(searchQuery, () => {
    if (showArchived.value) {
      currentPage.value = 1
      return
    }
    debouncedFetch()
  })
  watch(selectedType, () => {
    currentPage.value = 1
  })
  watch([startDate, endDate], () => debouncedFetch())

  // Clear all filters
  const clearFilters = () => {
    searchQuery.value = ''
    selectedType.value = ''
    startDate.value = ''
    endDate.value = ''
    currentPage.value = 1
  }

  onMounted(() => {
    fetchResearches()
  })

  return {
    // State
    researches,
    searchQuery,
    selectedType,
    startDate,
    endDate,
    showArchived,
    viewMode,
    selectedResearch,
    isLoading,
    confirmModal,
    currentPage,
    itemsPerPage,
    sortField,
    sortDirection,

    // Computed
    filteredResearches,
    sortedResearches,
    paginatedResearches,
    totalPages,

    // Methods
    fetchResearches,
    nextPage,
    prevPage,
    toggleSort,
    openResearch,
    requestArchiveToggle,
    executeArchiveToggle,
    formatSimpleDate,
    showToast,
    clearFilters
  }
}
