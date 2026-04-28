<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useAdminLogs } from '../../../composables/useAdminLogs'

const { logs, loading, pagination, filters, fetchLogs, downloadLogs, formatActionColor } = useAdminLogs()

// Row background color based on action severity
const getLogRowColor = (action: string) => {
    switch (action) {
        case 'APPROVE_RESEARCH': return 'bg-green-50 border-l-4 border-l-green-400'
        case 'LOGIN': return 'bg-emerald-50 border-l-4 border-l-emerald-300'
        case 'CREATE_RESEARCH': return 'bg-blue-50 border-l-4 border-l-blue-400'
        case 'REJECT_RESEARCH': return 'bg-red-50 border-l-4 border-l-red-400'
        case 'ARCHIVE_RESEARCH': return 'bg-yellow-50 border-l-4 border-l-yellow-400'
        case 'UPDATE_PROFILE': return 'bg-purple-50 border-l-4 border-l-purple-400'
        case 'LOGOUT': return 'bg-gray-50 border-l-4 border-l-gray-300'
        default: return 'bg-white border-l-4 border-l-gray-200'
    }
}

const isRefreshing = ref(false)
const resetAndRefresh = async () => {
    filters.value.startDate = ''
    filters.value.endDate = ''
    filters.value.action = 'ALL'
    filters.value.search = ''
    isRefreshing.value = true
    try {
        await fetchLogs(1)
    } finally {
        setTimeout(() => { isRefreshing.value = false }, 500)
    }
}

// Debounce Search
let debounceTimer: any = null
const handleSearch = () => {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
        fetchLogs(1)
    }, 500)
}

// Watch filters to auto-refresh (except search which is debounced)
watch(() => filters.value.action, () => fetchLogs(1))
watch(() => filters.value.startDate, () => fetchLogs(1))
watch(() => filters.value.endDate, () => fetchLogs(1))

const nextPage = () => {
    if (pagination.value.currentPage < pagination.value.totalPages) {
        fetchLogs(pagination.value.currentPage + 1)
    }
}

const prevPage = () => {
    if (pagination.value.currentPage > 1) {
        fetchLogs(pagination.value.currentPage - 1)
    }
}

onMounted(() => {
    fetchLogs()
})
</script>

<template>
  <div class="p-6 bg-gray-50 min-h-screen">
      
      <div class="mb-6 flex flex-col sm:flex-row justify-between items-center gap-4">
          <div>
            <h1 class="text-2xl font-bold text-gray-800">Activity Audit Trail</h1>
            <p class="text-sm text-gray-500">Monitor user actions and system events.</p>
          </div>
          
          <button 
            @click="downloadLogs"
            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 shadow transition"
          >
            📂 Export CSV
          </button>
      </div>

      <!-- Filters Toolbar -->
      <div class="bg-white p-4 rounded-lg shadow mb-4 flex flex-wrap gap-4 items-center">
          
          <!-- Search -->
          <div class="relative flex-1 min-w-[200px]">
              <input 
                v-model="filters.search"
                @input="handleSearch"
                type="text" 
                placeholder="Search user, details..." 
                class="pl-10 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500 outline-none w-full"
              >
              <span class="absolute left-3 top-2.5 text-gray-400">🔍</span>
          </div>

          <!-- Action Filter -->
          <div class="flex items-center gap-2">
            <span class="text-sm text-gray-600 font-medium">Action:</span>
            <select v-model="filters.action" class="border rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-green-500">
                <option value="ALL">All Actions</option>
                <option value="LOGIN">Login</option>
                <option value="LOGOUT">Logout</option>
                <option value="CREATE_RESEARCH">Create Research</option>
                <option value="UPDATE_PROFILE">Update Profile</option>
                <option value="APPROVE_RESEARCH">Approve</option>
                <option value="REJECT_RESEARCH">Reject</option>
                <option value="ARCHIVE_RESEARCH">Archive</option>
                <option value="REGISTER_USER">Register User</option>
            </select>
          </div>

          <!-- Date Filter -->
          <div class="flex items-center gap-2">
              <span class="text-sm text-gray-600 font-medium">Date:</span>
              <input 
                v-model="filters.startDate" 
                type="date" 
                class="border rounded-lg px-2 py-2 text-sm outline-none focus:ring-2 focus:ring-green-500" 
              />
              <span class="text-gray-400">-</span>
              <input 
                v-model="filters.endDate" 
                type="date" 
                class="border rounded-lg px-2 py-2 text-sm outline-none focus:ring-2 focus:ring-green-500" 
              />
          </div>

          <!-- Reset -->
          <button 
            @click="resetAndRefresh"
            class="p-2 bg-green-100 text-green-700 border border-green-200 rounded-lg hover:bg-green-200 hover:text-green-800 transition-all shadow-sm"
            title="Refresh & Reset Filters"
            :disabled="isRefreshing"
          >
            <svg 
              xmlns="http://www.w3.org/2000/svg" 
              class="h-5 w-5 transition-transform" 
              :class="{ 'animate-spin-refresh': isRefreshing }"
              fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
            >
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
          </button>
      </div>

      <!-- Table Container -->
      <div class="bg-white rounded-lg shadow overflow-hidden">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-if="loading" class="animate-pulse">
                        <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                            <div class="flex justify-center items-center gap-2">
                                <span class="animate-spin text-xl">⏳</span> Loading activity logs...
                            </div>
                        </td>
                    </tr>
                    
                    <tr v-else-if="logs.length === 0">
                        <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                            No logs found matching your filters.
                        </td>
                    </tr>

                    <tr v-for="log in logs" :key="log.id" class="hover:brightness-95 transition" :class="getLogRowColor(log.action)">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ new Date(log.created_at).toLocaleString() }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="ml-0">
                                    <div class="text-sm font-medium text-gray-900">{{ log.user_name }}</div>
                                    <div class="text-xs text-gray-500 capitalize">{{ log.role }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span :class="['px-2 inline-flex text-xs leading-5 font-semibold rounded-full', formatActionColor(log.action)]">
                                {{ log.action }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 max-w-md truncate" :title="log.details">
                            {{ log.details }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ log.ip_address }}
                        </td>
                    </tr>
                </tbody>
            </table>
          </div>
      </div>

      <!-- Pagination -->
      <div class="mt-4 flex justify-between items-center px-4">
          <span class="text-sm text-gray-600">
              Page {{ pagination.currentPage }} of {{ pagination.totalPages }}
          </span>
          <div class="flex gap-2">
              <button 
                @click="prevPage" 
                :disabled="pagination.currentPage === 1"
                class="px-3 py-1 bg-white border rounded hover:bg-gray-50 disabled:opacity-50 transition"
              >
                Previous
              </button>
              <button 
                @click="nextPage" 
                :disabled="pagination.currentPage >= pagination.totalPages"
                class="px-3 py-1 bg-white border rounded hover:bg-gray-50 disabled:opacity-50 transition"
              >
                Next
              </button>
          </div>
      </div>

  </div>
</template>

<style scoped>
@keyframes spin-refresh {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
.animate-spin-refresh {
  animation: spin-refresh 0.6s ease-in-out;
}
</style>
