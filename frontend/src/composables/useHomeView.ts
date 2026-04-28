import { ref, onMounted, onUnmounted } from 'vue'
import { adminService, researchService } from '../services'
import { useAuthStore } from '../stores/auth'
import type { ActivityLog, Research } from '../types'

export function useHomeView() {
  const authStore = useAuthStore()

  // --- STATE ---
  const topViewedResearches = ref<Research[]>([])
  const recentSystemLogs = ref<ActivityLog[]>([])
  const upcomingDeadlines = ref<Research[]>([])
  const currentSlide = ref(0)
  const slideInterval = ref<number | null>(null)
  const isSystemLogsLoading = ref(false)
  const isDeadlinesLoading = ref(false)

  // --- API ---
  const fetchTopViewedData = async () => {
    try {
      topViewedResearches.value = await researchService.getTopViewed(5)
    } catch (e) {
      console.error('Failed to load top viewed data, falling back to latest list', e)
      try {
        topViewedResearches.value = await researchService.getAll({ limit: 5 })
      } catch (fallbackError) {
        console.error('Fallback slider data load failed', fallbackError)
      }
    }

    currentSlide.value = 0
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

  const fetchSystemLogs = async () => {
    if (authStore.currentUser?.role !== 'admin') {
      recentSystemLogs.value = []
      return
    }

    isSystemLogsLoading.value = true
    try {
      const response = await adminService.getLogs({ page: 1, limit: 5 })
      recentSystemLogs.value = response.data
    } catch (error) {
      console.error('Failed to load recent system logs', error)
      recentSystemLogs.value = []
    } finally {
      isSystemLogsLoading.value = false
    }
  }

  const fetchUpcomingDeadlines = async () => {
    if (authStore.currentUser?.role !== 'admin') {
      upcomingDeadlines.value = []
      return
    }

    isDeadlinesLoading.value = true
    try {
      const pending = await researchService.getPending()
      const now = new Date()
      now.setHours(0, 0, 0, 0)
      const todayTs = now.getTime()

      upcomingDeadlines.value = pending
        .filter(item => {
          const deadlineTs = toDateTimestamp(item.deadline_date)
          return deadlineTs !== null && deadlineTs >= todayTs
        })
        .sort((a, b) => {
          const aTs = toDateTimestamp(a.deadline_date) ?? Number.MAX_SAFE_INTEGER
          const bTs = toDateTimestamp(b.deadline_date) ?? Number.MAX_SAFE_INTEGER
          return aTs - bTs
        })
        .slice(0, 5)
    } catch (error) {
      console.error('Failed to load upcoming deadlines', error)
      upcomingDeadlines.value = []
    } finally {
      isDeadlinesLoading.value = false
    }
  }

  const fetchAdminHomeData = async () => {
    await Promise.all([fetchSystemLogs(), fetchUpcomingDeadlines()])
  }

  // --- SLIDER LOGIC ---
  const nextSlide = () => {
    if (topViewedResearches.value.length === 0) return
    currentSlide.value = (currentSlide.value + 1) % topViewedResearches.value.length
  }

  const prevSlide = () => {
    if (topViewedResearches.value.length === 0) return
    currentSlide.value = (currentSlide.value - 1 + topViewedResearches.value.length) % topViewedResearches.value.length
  }

  const startSlideTimer = () => {
    stopSlideTimer()
    // Cast to unknown then number to satisfy TypeScript in browser env
    slideInterval.value = setInterval(nextSlide, 5000) as unknown as number
  }

  const stopSlideTimer = () => {
    if (slideInterval.value !== null) {
      clearInterval(slideInterval.value)
      slideInterval.value = null
    }
  }

  // --- LIFECYCLE ---
  onMounted(() => {
    fetchTopViewedData()
    fetchAdminHomeData()
    startSlideTimer()
  })

  onUnmounted(() => {
    stopSlideTimer()
  })

  return {
    topViewedResearches,
    recentSystemLogs,
    upcomingDeadlines,
    currentSlide,
    isSystemLogsLoading,
    isDeadlinesLoading,
    nextSlide,
    prevSlide,
    startSlideTimer,
    stopSlideTimer,
    fetchAdminHomeData
  }
}
