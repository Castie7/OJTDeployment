<script setup lang="ts">
import { computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useResearchLibrary } from '../../../composables/useResearchLibrary'
import { useAuthStore } from '../../../stores/auth'
import BaseButton from '../../ui/BaseButton.vue'
import ResearchFilters from './library/ResearchFilters.vue'
import ResearchGridItem from './library/ResearchGridItem.vue'
import ResearchListItem from './library/ResearchListItem.vue'
import ResearchDetailModal from './library/ResearchDetailModal.vue'

// ✅ USE THE DYNAMIC URL
import { getAssetUrl } from '../../../services/api'
const ASSET_URL = getAssetUrl()
const route = useRoute()
const router = useRouter()

const emit = defineEmits<{
  (e: 'update-stats', count: number): void
}>()

const authStore = useAuthStore()

const {
  researches,
  searchQuery, selectedType, startDate, endDate, showArchived, viewMode, selectedResearch,
  isLoading, confirmModal, currentPage, 
  sortField, sortDirection,
  filteredResearches, paginatedResearches, totalPages,
  nextPage, prevPage, toggleSort, openResearch, requestArchiveToggle, executeArchiveToggle, clearFilters
} = useResearchLibrary(emit)

const clearOpenQueryParam = async () => {
  if (!Object.prototype.hasOwnProperty.call(route.query, 'open')) return

  const nextQuery = { ...route.query }
  delete nextQuery.open
  await router.replace({ path: route.path, query: nextQuery })
}

const openResearchFromQuery = async (rawId: unknown) => {
  const openId = Number(rawId)
  if (!Number.isInteger(openId) || openId <= 0) return
  if (isLoading.value) return

  const target = researches.value.find(item => item.id === openId)
  if (!target) return

  await openResearch(target)
  await clearOpenQueryParam()
}

watch(
  [() => route.query.open, () => researches.value.length, isLoading],
  async ([openQuery, _count, loading]) => {
    if (!openQuery || loading) return
    const candidate = Array.isArray(openQuery) ? openQuery[0] : openQuery
    await openResearchFromQuery(candidate)
  },
  { immediate: true }
)

// Check if any filters are active
const hasActiveFilters = computed(() => {
  return searchQuery.value !== '' || 
         selectedType.value !== '' || 
         startDate.value !== '' || 
         endDate.value !== ''
})
</script>

<template>
  <div class="animate-fade-in space-y-6">

    <div class="flex items-center justify-between">
         <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
            <span class="text-3xl">📚</span> Research Library
            <span class="bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded-full border border-gray-200">{{ filteredResearches.length }} items</span>
         </h1>
         <div class="flex items-center gap-2">
            <BaseButton 
                v-if="authStore.currentUser && authStore.currentUser.role === 'admin'" 
                @click="showArchived = !showArchived" 
                :variant="showArchived ? 'danger' : 'secondary'"
                size="sm"
            >
                {{ showArchived ? 'Exit Archive' : 'View Archive' }}
            </BaseButton>
         </div>
    </div>
    
    <div class="flex flex-col lg:flex-row gap-8 items-start">
        
        <!-- SIDEBAR FILTERS -->
        <div class="w-full lg:w-64 shrink-0 space-y-6">
            <ResearchFilters
                v-model:searchQuery="searchQuery"
                v-model:selectedType="selectedType"
                v-model:startDate="startDate"
                v-model:endDate="endDate"
                :hasActiveFilters="hasActiveFilters"
                @clear-filters="clearFilters"
            />
        </div>

        <!-- MAIN CONTENT -->
        <div class="flex-1 w-full min-w-0">
             
             <!-- Toolbar -->
             <div class="flex justify-between items-center mb-4">
                 <div class="flex gap-2 bg-gray-100 p-1 rounded-lg">
                    <button @click="viewMode = 'grid'" :class="['px-3 py-1.5 text-sm font-medium rounded-md transition', viewMode === 'grid' ? 'bg-white text-emerald-700 shadow-sm' : 'text-gray-500 hover:text-gray-700']">Grid</button>
                    <button @click="viewMode = 'list'" :class="['px-3 py-1.5 text-sm font-medium rounded-md transition', viewMode === 'list' ? 'bg-white text-emerald-700 shadow-sm' : 'text-gray-500 hover:text-gray-700']">List</button>
                 </div>
                 
                 <!-- Pagination Top -->
                 <div class="flex items-center gap-2 text-sm text-gray-500">
                    <span>Page {{ currentPage }} of {{ totalPages || 1 }}</span>
                    <div class="flex gap-1">
                        <button @click="prevPage" :disabled="currentPage === 1" class="p-1 hover:bg-gray-100 rounded disabled:opacity-30">◀</button>
                        <button @click="nextPage" :disabled="currentPage === totalPages || totalPages === 0" class="p-1 hover:bg-gray-100 rounded disabled:opacity-30">▶</button>
                    </div>
                 </div>
             </div>

             <Transition name="fade" mode="out-in">
                <!-- GRID VIEW -->
                <div v-if="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                     <!-- ResearchGridItem Component -->
                     <ResearchGridItem 
                        v-for="item in paginatedResearches"
                        :key="item.id"
                        :item="item"
                        @click="openResearch(item)"
                     />
                </div>

                <!-- LIST VIEW -->
                <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
                   <table class="min-w-full divide-y divide-gray-100">
                      <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                              <button @click="toggleSort('title')" class="inline-flex items-center gap-1 hover:text-emerald-700 transition">
                                Research
                                <span v-if="sortField === 'title'">{{ sortDirection === 'asc' ? '▲' : '▼' }}</span>
                              </button>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                              <button @click="toggleSort('knowledge_type')" class="inline-flex items-center gap-1 hover:text-emerald-700 transition">
                                Type
                                <span v-if="sortField === 'knowledge_type'">{{ sortDirection === 'asc' ? '▲' : '▼' }}</span>
                              </button>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                              <button @click="toggleSort('publication_date')" class="inline-flex items-center gap-1 hover:text-emerald-700 transition">
                                Date
                                <span v-if="sortField === 'publication_date'">{{ sortDirection === 'asc' ? '▲' : '▼' }}</span>
                              </button>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                              <button @click="toggleSort('shelf_location')" class="inline-flex items-center gap-1 hover:text-emerald-700 transition">
                                Location
                                <span v-if="sortField === 'shelf_location'">{{ sortDirection === 'asc' ? '▲' : '▼' }}</span>
                              </button>
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Action</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-gray-100">
                        <!-- ResearchListItem Component -->
                        <ResearchListItem 
                            v-for="item in paginatedResearches"
                            :key="item.id"
                            :item="item"
                            @click="openResearch(item)"
                            @archive="requestArchiveToggle"
                        />
                      </tbody>
                   </table>
                </div>
             </Transition>

            <!-- Empty State -->
            <div v-if="paginatedResearches.length === 0 && !isLoading" class="text-center py-20 bg-gray-50 rounded-xl border border-dashed border-gray-200 mt-4">
                <div class="text-5xl mb-4 opacity-20">🔍</div>
                <h3 class="text-lg font-bold text-gray-900">No Researches Found</h3>
                <p class="text-gray-500 text-sm">Try adjusting your filters or search terms.</p>
                <button @click="clearFilters" class="mt-4 text-emerald-600 font-bold text-sm hover:underline">Clear Filters</button>
            </div>
        </div>
    </div>

    <!-- Modal: View Details / PDF -->
    <Transition name="fade">
      <ResearchDetailModal 
        v-if="selectedResearch"
        :research="selectedResearch"
        :asset-url="ASSET_URL"
        @close="selectedResearch = null"
      />
    </Transition>

    <!-- Modal: Confirmation -->
    <Transition name="fade">
      <div v-if="confirmModal.show" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-gray-900/80 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden transform transition-all p-8 text-center animate-pop">
          <div class="mb-6 flex justify-center">
            <div class="w-20 h-20 rounded-full bg-gray-50 flex items-center justify-center text-4xl shadow-inner">
                {{ confirmModal.action === 'Archive' ? '🗑️' : '♻️' }}
            </div>
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-2">{{ confirmModal.title }}</h3>
          <p class="text-gray-500 text-sm mb-8 leading-relaxed">{{ confirmModal.subtext }}</p>
          <div class="flex gap-3 justify-center">
            <BaseButton @click="confirmModal.show = false" variant="ghost" class="w-full">Cancel</BaseButton>
            <BaseButton 
                @click="executeArchiveToggle" 
                :disabled="confirmModal.isProcessing" 
                :variant="confirmModal.action === 'Archive' ? 'danger' : 'primary'"
                class="w-full"
            >
                Yes, {{ confirmModal.action }}
            </BaseButton>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.animate-fade-in {
  animation: fadeIn 0.3s ease-out;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(5px); }
  to { opacity: 1; transform: translateY(0); }
}

.animate-pop {
    animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
@keyframes popIn {
    from { opacity: 0; transform: scale(0.95) translateY(10px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

/* Transitions */
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
