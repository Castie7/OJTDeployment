import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { dashboardService, notificationService } from '../services'
import api from '../services/api'
import type { Stat } from '../types'
import { useAuthStore } from '../stores/auth'

export function useDashboard() {
  const router = useRouter()
  const authStore = useAuthStore()

  // --- 1. CORE STATE ---
  const currentTab = ref('home')

  // Child Component Refs (To access methods inside children)
  // We use 'any' here for simplicity, or you could define the specific Component type
  const workspaceRef = ref<any>(null)
  const approvalRef = ref<any>(null)

  // Default Loading State
  const stats = ref<Stat[]>([
    { id: 'stat-1', title: 'Loading...', value: '...', color: 'text-gray-400' },
    { id: 'stat-2', title: 'Root Crop Varieties', value: '8', color: 'text-yellow-600', action: 'home' },
    { id: 'stat-3', title: 'Loading...', value: '...', color: 'text-gray-400' }
  ])

  // --- 2. ADMIN MENU LOGIC ---
  const showAdminMenu = ref(false)
  const closeAdminMenu = () => { setTimeout(() => showAdminMenu.value = false, 200) }

  // --- 3. NOTIFICATION STATE ---
  const showNotifications = ref(false)
  const notifications = ref<any[]>([])
  const pollingInterval = ref<any>(null)
  const prevUnread = ref<number>(0)
  const initialized = ref(false)

  // --- 4. CORE ACTIONS ---
  const setTab = (tab: string) => {
    currentTab.value = tab
  }

  const fetchDashboardStats = async () => {
    const user = authStore.currentUser
    if (!user) return

    try {
      let response;
      if (user.role === 'admin') {
        const data = await dashboardService.getStats()

        stats.value = [
          { id: 'stat-1', title: 'Total Researches', value: data.total, color: 'text-primary-600', action: 'research' },
          { id: 'stat-2', title: 'Root Crop Varieties', value: '8', color: 'text-secondary-600', action: 'home' },
          { id: 'stat-3', title: 'Pending Reviews', value: data.pending, color: 'text-red-600', action: 'approval' }
        ]
      } else {
        // For non-admin users, still use api directly as there's no service method for user-specific stats
        response = await api.get(`/research/user-stats/${user.id}`)

        stats.value = [
          { id: 'stat-1', title: 'My Published Works', value: response.data.published, color: 'text-primary-600', action: 'workspace' },
          { id: 'stat-2', title: 'Root Crop Varieties', value: '8', color: 'text-secondary-600', action: 'home' },
          { id: 'stat-3', title: 'My Pending Submissions', value: response.data.pending, color: 'text-orange-500', action: 'workspace' }
        ]
      }
    } catch (e) {
      console.error("Stats Fetch Failed:", e)
      stats.value[0] = { ...stats.value[0], title: "Connection Failed", value: "Error", color: "text-red-500" }
      stats.value[2] = { ...stats.value[2], title: "Connection Failed", value: "Error", color: "text-red-500" }
    }
  }

  const updateStats = (count: number) => {
    const firstStat = stats.value[0]
    if (firstStat && firstStat.title === 'Total Researches') {
      firstStat.value = count
    }
  }

  // --- 5. NOTIFICATION LOGIC ---

  // Audio Context (Sound)
  let audioCtx: AudioContext | null = null
  const ensureAudioContext = () => {
    if (!audioCtx) {
      try { audioCtx = new (window.AudioContext || (window as any).webkitAudioContext)() } catch (e) { audioCtx = null }
    }
    return audioCtx
  }

  const playNotificationSound = (count = 1) => {
    const ctx = ensureAudioContext()
    if (!ctx) return
    if (ctx.state === 'suspended') void ctx.resume().catch(() => { })

    const now = ctx.currentTime
    const spacing = 0.18

    for (let i = 0; i < count; i++) {
      const o = ctx.createOscillator()
      const g = ctx.createGain()
      o.type = 'sine'
      o.frequency.value = 1000
      o.connect(g)
      g.connect(ctx.destination)

      const start = now + i * spacing
      g.gain.setValueAtTime(0, start)
      g.gain.linearRampToValueAtTime(0.28, start + 0.004)
      g.gain.exponentialRampToValueAtTime(0.001, start + 0.14)

      o.start(start)
      o.stop(start + 0.16)
    }
  }

  const unreadCount = computed(() => notifications.value.filter(n => n.is_read == 0).length)

  const fetchNotifications = async () => {
    const user = authStore.currentUser
    if (!user) return
    try {
      notifications.value = await notificationService.getAll()
    } catch (error) {
      console.error("Failed to fetch notifications", error)
    }
  }

  const toggleNotifications = async () => {
    showNotifications.value = !showNotifications.value
    const user = authStore.currentUser

    if (showNotifications.value && unreadCount.value > 0 && user) {
      try {
        // Optimistic update
        notifications.value.forEach(n => n.is_read = 1)

        await notificationService.markAllAsRead()
      } catch (e) { console.error(e) }
    }
  }

  const handleNotificationClick = async (notif: any) => {
    // Determine the ID: 'research_id' is standard, fallback to 'reference_id' if needed
    const targetId = notif.research_id || notif.reference_id
    if (!targetId) return

    showNotifications.value = false // Close dropdown
    const user = authStore.currentUser

    // 1. If User is Admin -> Go to Approval Tab
    if (user?.role === 'admin') {
      router.push({ path: '/approval', query: { open: targetId } })
    }
    // 2. If User is Student -> Go to Workspace Tab
    else {
      router.push({ path: '/workspace', query: { open: targetId } })
    }
  }

  const formatTimeAgo = (dateInput: any) => {
    if (!dateInput) return ''

    let dateString = dateInput

    // Handle PHP DateTime Object { date: "...", timezone: "..." }
    if (typeof dateInput === 'object' && dateInput !== null && dateInput.date) {
      dateString = dateInput.date
    }

    if (typeof dateString !== 'string') {
      return 'Invalid Date'
    }

    // Fix: Convert "YYYY-MM-DD HH:MM:SS" to "YYYY-MM-DDTHH:MM:SS"
    // Also strip microseconds if present and appended with .000000 for cleaner parsing in some envs, 
    // though 'T' replacement is usually enough.
    let safeDateString = dateString.replace(' ', 'T')

    // Assume UTC if it comes from server to align with "timezone": "UTC"
    // But don't double append if already likely ISO
    if (!safeDateString.endsWith('Z') && !safeDateString.includes('+')) {
      safeDateString += 'Z'
    }

    const date = new Date(safeDateString)

    // Fallback
    if (isNaN(date.getTime())) return dateString

    const now = new Date()
    const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000)

    if (diffInSeconds < 60) return 'Just now'
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`
    return date.toLocaleDateString()
  }

  // --- 6. WATCHERS & LIFECYCLE ---

  watch(() => authStore.currentUser, (newUser) => {
    if (newUser) {
      fetchDashboardStats()
      fetchNotifications().then(() => {
        prevUnread.value = unreadCount.value
        initialized.value = true
      })
    } else {
      notifications.value = []
    }
  }, { immediate: true })

  watch(unreadCount, (newVal, oldVal) => {
    if (!initialized.value) return
    const prev = (typeof oldVal === 'number') ? oldVal : prevUnread.value || 0
    const diff = newVal - prev
    if (diff > 0) {
      playNotificationSound(diff)
    }
    prevUnread.value = newVal
  })

  onMounted(() => {
    // Poll every 10 seconds
    pollingInterval.value = setInterval(() => {
      void fetchNotifications()
    }, 60000)
  })

  onUnmounted(() => {
    if (pollingInterval.value) clearInterval(pollingInterval.value)
  })

  return {
    currentTab, stats, workspaceRef, approvalRef, showAdminMenu, showNotifications, notifications, unreadCount,
    setTab, updateStats, fetchDashboardStats, closeAdminMenu, toggleNotifications, handleNotificationClick, formatTimeAgo
  }
}
