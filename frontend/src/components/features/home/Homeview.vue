<script setup lang="ts">
import { computed, ref } from 'vue'
import { useHomeView } from '../../../composables/useHomeView'
import type { Stat } from '../../../types'
import { useAuthStore } from '../../../stores/auth'
import BaseButton from '../../ui/BaseButton.vue'
import BaseCard from '../../ui/BaseCard.vue'

defineProps<{
  stats: Stat[] // title, value, color
}>()

const authStore = useAuthStore()
const isAdmin = computed(() => authStore.currentUser?.role === 'admin')
const topSectionGridClass = computed(() => isAdmin.value ? 'xl:grid-cols-3' : 'xl:grid-cols-1')
const topSectionMainClass = computed(() => isAdmin.value ? 'xl:col-span-2' : 'xl:col-span-1')

const getStatIcon = (title: string) => {
  const normalized = title.toLowerCase()
  if (normalized.includes('pending')) return '⏳'
  if (normalized.includes('published') || normalized.includes('approved') || normalized.includes('total')) return '📚'
  if (normalized.includes('varieties')) return '🌱'
  return '📊'
}

const getStatCardTone = (title: string) => {
  const normalized = title.toLowerCase()
  if (normalized.includes('pending')) return 'from-red-50 to-white border-red-100'
  if (normalized.includes('varieties')) return 'from-amber-50 to-white border-amber-100'
  return 'from-emerald-50 to-white border-emerald-100'
}

const emit = defineEmits<{
  (e: 'browse-click'): void
  (e: 'stat-click', tab: string): void
  (e: 'view-research', researchId: number): void
}>()

const { 
  topViewedResearches,
  recentSystemLogs,
  upcomingDeadlines,
  currentSlide,
  isSystemLogsLoading,
  isDeadlinesLoading,
  nextSlide, prevSlide,
  startSlideTimer, stopSlideTimer,
  fetchAdminHomeData
} = useHomeView()

const swipeStartX = ref<number | null>(null)
const SWIPE_THRESHOLD = 50

const handleTouchStart = (event: TouchEvent) => {
  if (event.touches.length !== 1) return
  const firstTouch = event.touches[0]
  if (!firstTouch) return
  swipeStartX.value = firstTouch.clientX
  stopSlideTimer()
}

const handleTouchEnd = (event: TouchEvent) => {
  if (swipeStartX.value === null || event.changedTouches.length === 0) {
    swipeStartX.value = null
    startSlideTimer()
    return
  }

  const changedTouch = event.changedTouches[0]
  if (!changedTouch) {
    swipeStartX.value = null
    startSlideTimer()
    return
  }

  const deltaX = changedTouch.clientX - swipeStartX.value
  if (Math.abs(deltaX) >= SWIPE_THRESHOLD) {
    if (deltaX < 0) {
      nextSlide()
    } else {
      prevSlide()
    }
  }

  swipeStartX.value = null
  startSlideTimer()
}

const handleTouchCancel = () => {
  swipeStartX.value = null
  startSlideTimer()
}

const parseDateValue = (value: unknown): Date | null => {
  if (!value) return null

  let raw: unknown = value
  if (typeof value === 'object' && value !== null && 'date' in (value as Record<string, unknown>)) {
    raw = (value as { date?: unknown }).date
  }

  const parsed = new Date(raw as string | number | Date)
  if (isNaN(parsed.getTime())) return null
  return parsed
}

const formatLogDate = (value?: string) => {
  const parsed = parseDateValue(value)
  if (!parsed) return 'Unknown time'
  return parsed.toLocaleString()
}

const formatDeadline = (value: unknown) => {
  const parsed = parseDateValue(value)
  if (!parsed) return 'No deadline'
  return parsed.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

const deadlineCountdown = (value: unknown) => {
  const parsed = parseDateValue(value)
  if (!parsed) return ''

  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const due = new Date(parsed)
  due.setHours(0, 0, 0, 0)
  const diffDays = Math.round((due.getTime() - today.getTime()) / 86400000)

  if (diffDays <= 0) return 'Due today'
  if (diffDays === 1) return '1 day left'
  return `${diffDays} days left`
}

const shorten = (text: string, max = 70) => {
  if (!text) return ''
  if (text.length <= max) return text
  return `${text.slice(0, max - 1)}...`
}

// Map crop variation to a local background image
const getCropImage = (crop?: string): string => {
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
  // Default: generic agriculture field
  return '/images/crops/default.jpg'
}
</script>

<template>
  <div class="space-y-8 animate-fade-in"> 
    
    <div v-if="authStore.currentUser">
      <h1 class="text-2xl font-bold text-gray-900 mb-6">
        {{ authStore.currentUser.role === 'admin' ? '📢 System Overview' : '👋 My Research Overview' }}
      </h1>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <BaseCard 
            v-for="stat in stats" 
            :key="stat.id || stat.title" 
            @click="stat.action ? emit('stat-click', stat.action) : null"
            class="relative overflow-hidden border bg-gradient-to-br transition-all duration-200 hover:-translate-y-1 hover:shadow-md"
            :class="[getStatCardTone(stat.title), stat.action ? 'cursor-pointer' : '']"
        >
          <div class="absolute -right-5 -top-5 w-16 h-16 rounded-full bg-white/40 blur-md"></div>
          <div class="relative flex items-start justify-between gap-4">
            <div>
              <p class="text-gray-500 text-[11px] uppercase font-bold tracking-wider">{{ stat.title }}</p>
              <h3 class="text-3xl font-bold text-gray-900 leading-tight mt-1">{{ stat.value }}</h3>
            </div>
            <div class="w-11 h-11 rounded-xl bg-white/80 border border-white/70 shadow-sm flex items-center justify-center text-lg">
              {{ getStatIcon(stat.title) }}
            </div>
          </div>
        </BaseCard>
      </div>
    </div>

    <div v-if="topViewedResearches.length > 0 || isAdmin" :class="['grid grid-cols-1 gap-6 items-stretch', topSectionGridClass]">
      <!-- Featured Research Carousel -->
      <div
        v-if="topViewedResearches.length > 0"
        :class="['relative w-full h-[460px] rounded-2xl overflow-hidden shadow-lg group bg-gray-900', topSectionMainClass]"
        @mouseenter="stopSlideTimer"
        @mouseleave="startSlideTimer"
        @touchstart="handleTouchStart"
        @touchend="handleTouchEnd"
        @touchcancel="handleTouchCancel"
      >
        <div
          class="absolute inset-0 flex transition-transform duration-700 ease-out"
          :style="{ transform: `translateX(-${currentSlide * 100}%)` }"
        >
          <div
            v-for="item in topViewedResearches"
            :key="item.id"
            class="min-w-full h-full relative"
          >
            <img
              :src="getCropImage(item.crop_variation)"
              :alt="item.crop_variation || 'Root Crops'"
              class="absolute inset-0 w-full h-full object-cover opacity-60"
            />
            <div class="absolute inset-0 bg-gradient-to-t from-gray-900 via-gray-900/40 to-transparent"></div>

            <div class="absolute bottom-0 left-0 p-8 md:p-12 max-w-4xl">
              <div class="flex items-center gap-2 mb-3">
                <span class="px-2 py-0.5 bg-emerald-500 text-white text-[10px] uppercase font-bold rounded">Top Viewed</span>
                <span v-if="item.crop_variation" class="text-emerald-300 text-sm font-medium">
                  {{ item.crop_variation }}
                </span>
                <span class="text-white/80 text-xs font-medium">
                  {{ item.view_count ?? 0 }} views
                </span>
              </div>
              <h2 class="text-3xl md:text-4xl font-bold text-white mb-2 leading-tight">
                {{ item.title }}
              </h2>
              <p class="text-gray-300 text-sm line-clamp-2 mb-6 max-w-xl">
                {{ item.abstract || 'Explore this latest research in our library.' }}
              </p>

              <BaseButton
                @click="$emit('view-research', item.id)"
                size="md"
                class="!bg-white !text-gray-900 hover:!bg-emerald-50 font-semibold border-none"
              >
                Read Paper
              </BaseButton>
            </div>
          </div>
        </div>

        <!-- Carousel Controls -->
        <div class="absolute bottom-6 right-6 flex gap-2">
          <button
            v-for="(_, index) in topViewedResearches"
            :key="index"
            @click="currentSlide = index"
            :class="`h-1.5 rounded-full transition-all duration-300 ${currentSlide === index ? 'w-6 bg-emerald-500' : 'w-1.5 bg-white/30 hover:bg-white'}`"
          ></button>
        </div>
      </div>

      <BaseCard v-else :class="['!p-8', topSectionMainClass]">
        <h3 class="text-lg font-bold text-gray-900 mb-2">Top Viewed Researches</h3>
        <p class="text-sm text-gray-500 mb-4">No top-viewed data yet. Researches will appear after users start opening items in the library.</p>
        <BaseButton @click="$emit('browse-click')" variant="secondary" size="sm">Open Library</BaseButton>
      </BaseCard>

      <div v-if="isAdmin" class="xl:col-span-1 grid grid-rows-2 gap-4 h-[460px]">
        <BaseCard :noPadding="true" class="overflow-hidden h-full border border-gray-200 shadow-sm flex flex-col">
          <div class="px-4 py-3 border-b border-emerald-100 flex items-center justify-between bg-gradient-to-r from-emerald-50 to-white sticky top-0 z-10">
            <h3 class="text-sm font-bold text-gray-900">System Log</h3>
            <button @click="emit('stat-click', 'logs')" class="text-xs font-semibold text-emerald-700 hover:underline">View all</button>
          </div>
          <div class="flex-1 overflow-y-scroll panel-scroll">
            <div v-if="isSystemLogsLoading" class="px-4 py-6 text-sm text-gray-500">Loading logs...</div>
            <div v-else-if="recentSystemLogs.length === 0" class="px-4 py-6 text-sm text-gray-500">No recent system log entries.</div>
            <div v-else class="divide-y divide-gray-100">
              <div v-for="log in recentSystemLogs" :key="log.id" class="px-4 py-3 hover:bg-gray-50/80 transition-colors">
                <div class="flex items-center justify-between gap-3 mb-1">
                  <span class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">{{ log.action }}</span>
                  <span class="text-[10px] text-gray-400">{{ formatLogDate(log.created_at) }}</span>
                </div>
                <p class="text-xs text-gray-700 font-medium">{{ log.user_name }} ({{ log.role }})</p>
                <p class="text-xs text-gray-500 mt-1">{{ shorten(log.details, 80) }}</p>
              </div>
            </div>
          </div>
        </BaseCard>

        <BaseCard :noPadding="true" class="overflow-hidden h-full border border-gray-200 shadow-sm flex flex-col">
          <div class="px-4 py-3 border-b border-amber-100 flex items-center justify-between bg-gradient-to-r from-amber-50 to-white sticky top-0 z-10">
            <h3 class="text-sm font-bold text-gray-900">Upcoming Deadlines</h3>
            <button @click="fetchAdminHomeData" class="text-xs font-semibold text-emerald-700 hover:underline">Refresh</button>
          </div>
          <div class="flex-1 overflow-y-scroll panel-scroll">
            <div v-if="isDeadlinesLoading" class="px-4 py-6 text-sm text-gray-500">Loading deadlines...</div>
            <div v-else-if="upcomingDeadlines.length === 0" class="px-4 py-6 text-sm text-gray-500">No upcoming deadlines found.</div>
            <div v-else class="divide-y divide-gray-100">
              <div v-for="item in upcomingDeadlines" :key="item.id" class="px-4 py-3 hover:bg-gray-50/80 transition-colors">
                <p class="text-xs font-semibold text-gray-900 line-clamp-1">{{ item.title }}</p>
                <p class="text-[11px] text-gray-500 mt-1">By {{ item.author }}</p>
                <div class="flex items-center justify-between mt-2">
                  <span class="text-[11px] font-medium text-emerald-700">{{ formatDeadline(item.deadline_date) }}</span>
                  <span class="text-[11px] text-amber-700 font-semibold">{{ deadlineCountdown(item.deadline_date) }}</span>
                </div>
              </div>
            </div>
          </div>
        </BaseCard>
      </div>
    </div>

    <!-- Welcome Section -->
    <BaseCard class="bg-gradient-to-r from-emerald-50 to-white border-none !p-8">
      <div class="flex flex-col md:flex-row items-center justify-between gap-6">
        <div>
            <h1 class="text-2xl font-bold text-emerald-900 mb-2">Welcome to BSU RootCrops</h1>
            <p class="text-gray-600 max-w-2xl text-sm">The official repository for root crop research. Browse our open collection of research data and gain insights into agricultural innovation.</p>
        </div>
        <div class="flex gap-3 shrink-0">
          <BaseButton @click="$emit('browse-click')" variant="primary">
            Browse Library
          </BaseButton>
        </div>
      </div>
    </BaseCard>

    <!-- Mission / Vision / Goal (Compact) -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <BaseCard class="!bg-emerald-900 text-white !p-6 border-none hover:shadow-lg transition-shadow">
            <h3 class="text-xs font-bold text-emerald-400 uppercase mb-2">Vision</h3>
            <p class="text-sm leading-relaxed">A prime mover of sustainable rootcrops industry.</p>
        </BaseCard>
        <BaseCard class="!bg-teal-700 text-white !p-6 border-none hover:shadow-lg transition-shadow">
            <h3 class="text-xs font-bold text-teal-300 uppercase mb-2">Mission</h3>
            <p class="text-sm leading-relaxed">To develop efficient root crops production and utilization systems.</p>
        </BaseCard>
        <BaseCard class="!bg-emerald-700 text-white !p-6 border-none hover:shadow-lg transition-shadow">
            <h3 class="text-xs font-bold text-emerald-200 uppercase mb-2">Goal</h3>
            <p class="text-sm leading-relaxed">Increase productivity and organizational capacity.</p>
        </BaseCard>
        <BaseCard class="!bg-teal-600 text-white !p-6 border-none hover:shadow-lg transition-shadow">
            <h3 class="text-xs font-bold text-teal-100 uppercase mb-2">Objective</h3>
            <p class="text-sm leading-relaxed">Develop profitable and sustainable root crop industry.</p>
        </BaseCard>
    </div>

    <!-- Divisions & Services -->
    <div class="space-y-6">
         <div class="flex items-center gap-4">
            <h2 class="text-lg font-bold text-gray-900">Research Divisions</h2>
            <div class="h-px bg-gray-200 flex-1"></div>
         </div>

         <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <BaseCard class="group hover:border-emerald-500 transition-colors">
              <div class="mb-3 w-10 h-10 bg-emerald-50 rounded-lg flex items-center justify-center text-xl">🧬</div>
              <h3 class="font-bold text-gray-900 mb-2">Crop Improvement</h3>
              <p class="text-xs text-gray-500">Evaluate varieties, maintain germplasm, clean up services.</p>
            </BaseCard>

            <BaseCard class="group hover:border-emerald-500 transition-colors">
              <div class="mb-3 w-10 h-10 bg-emerald-50 rounded-lg flex items-center justify-center text-xl">🌱</div>
              <h3 class="font-bold text-gray-900 mb-2">Crop Management</h3>
              <p class="text-xs text-gray-500">Production techniques, pest control, soil analysis.</p>
            </BaseCard>

            <BaseCard class="group hover:border-emerald-500 transition-colors">
              <div class="mb-3 w-10 h-10 bg-emerald-50 rounded-lg flex items-center justify-center text-xl">🏭</div>
              <h3 class="font-bold text-gray-900 mb-2">Processing</h3>
              <p class="text-xs text-gray-500">Postharvest tech, product utilization, waste management.</p>
            </BaseCard>
         </div>
    </div>
  </div>
</template>

<style scoped>
.animate-fade-in {
  animation: fadeIn 0.4s ease-out;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

.panel-scroll {
  scrollbar-gutter: stable;
}

.panel-scroll::-webkit-scrollbar {
  width: 8px;
}

.panel-scroll::-webkit-scrollbar-track {
  background: #f1f5f9;
}

.panel-scroll::-webkit-scrollbar-thumb {
  background: #94a3b8;
  border-radius: 9999px;
}

.panel-scroll::-webkit-scrollbar-thumb:hover {
  background: #64748b;
}
</style>
