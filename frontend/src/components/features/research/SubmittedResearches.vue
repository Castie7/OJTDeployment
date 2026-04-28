<script setup lang="ts">
import { computed, watch } from 'vue'
import { useSubmittedResearches } from '../../../composables/useSubmittedResearches' 
import { usePdfViewer } from '../../../composables/usePdfViewer'
import { sanitizeUrl } from '../../../utils/formatters'

import { useAuthStore } from '../../../stores/auth'

// No unused useToast import here anymore
const props = defineProps<{
  statusFilter: string
}>()

const authStore = useAuthStore()

const isArchived = computed(() => props.statusFilter === 'archived')

// 1. Define Emit for the parent to catch
const emit = defineEmits<{
  (e: 'edit', item: any): void
  (e: 'easy-resubmit', item: any): void
  (e: 'view', item: any): void
}>()

const {
  // State
  myItems, 
  isLoading, searchQuery, 
  selectedResearch, commentModal, isSendingComment,
  chatContainer, confirmModal,
  
  // Computed
  filteredItems, paginatedItems, currentPage, totalPages, itemsPerPage,
  
  // Methods
  fetchData, nextPage, prevPage,
  requestArchive, executeArchive, openComments, postComment,
  
  // Helpers
  getDeadlineStatus, 
  formatSimpleDate
} = useSubmittedResearches(props)

const { pdfBlobUrl, isPdfLoading, pdfError, loadPdf, clearPdf } = usePdfViewer()

// Watch for selected research to dynamically load securely via Blob
watch(() => selectedResearch.value, (newVal) => {
  if (newVal && newVal.id && newVal.file_path) {
    loadPdf(newVal.id)
  } else {
    clearPdf()
  }
})

const openExternalLink = (url?: string | null) => {
  const safeUrl = sanitizeUrl(url)
  if (safeUrl) {
    window.open(safeUrl, '_blank', 'noopener,noreferrer')
  }
}

// --- Handle Notification Click ---
const openNotification = async (researchId: number) => {
  // 1. Ensure data is loaded
  if (myItems.value.length === 0) {
      await fetchData()
  }
  
  // 2. Find the item
  const targetItem = myItems.value.find(i => i.id === researchId)
  
  // 3. Open it
  if (targetItem) {
    openComments(targetItem)
  }
}

// Expose functions to parent (MyWorkspace.vue)
defineExpose({ fetchData, openNotification })

// Fix for vue-tsc unused variable error
void chatContainer
void confirmModal
void isSendingComment
</script>

<template>
  <div class="flex flex-col h-full bg-white">
    
    <!-- Toolbar -->
    <div class="flex flex-col sm:flex-row justify-between items-center p-4 border-b border-gray-100 gap-4">
      <div class="relative w-full sm:w-72">
        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">🔍</span>
        <input 
            v-model="searchQuery" 
            type="text" 
            placeholder="Search items..." 
            class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm transition-shadow"
        />
      </div>
      <div class="text-xs text-gray-500 font-medium">
          Showing {{ paginatedItems.length }} of {{ filteredItems.length }}
      </div>
    </div>

    <div v-if="isLoading" class="flex-1 flex flex-col items-center justify-center py-20 text-gray-400">
        <div class="w-8 h-8 border-2 border-emerald-500 border-t-transparent rounded-full animate-spin mb-2"></div>
        <span>Loading uploads...</span>
    </div>

    <div v-else class="flex flex-col min-h-[400px]">
      <div class="overflow-x-auto flex-1">
        <table class="min-w-full divide-y divide-gray-100 table-fixed">
          <thead class="bg-gray-50">
            <tr>
              <th :class="isArchived ? 'px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider w-[40%]' : 'px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider w-1/3'">Title</th>
              <th v-if="!isArchived" class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider w-1/6">
                Timeline
              </th>
              <th :class="isArchived ? 'px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider w-1/5' : 'px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider w-1/6'">Details</th>
              <th :class="isArchived ? 'px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider w-[15%]' : 'px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider w-1/6'">Status</th>
              <th :class="isArchived ? 'px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider w-1/4' : 'px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider w-1/6'">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-50">
            <tr v-for="item in paginatedItems" :key="item.id" class="hover:bg-emerald-50/60 transition cursor-pointer group" @click="$emit('view', item)">
              
              <td class="px-6 py-4">
                 <div class="font-bold text-gray-900 line-clamp-2 leading-snug group-hover:text-emerald-700 transition-colors" :title="item.title">{{ item.title }}</div>
                 <div class="text-xs text-gray-500 mt-1 font-medium">By: {{ item.author }}</div>
              </td>
              
              <td v-if="!isArchived" class="px-6 py-4">
                <div v-if="item.status === 'approved'">
                  <div class="mb-1">
                    <span class="px-2 py-0.5 text-[10px] uppercase font-bold rounded bg-emerald-100 text-emerald-700">Completed</span>
                  </div>
                  <div class="text-[10px] text-gray-400 flex flex-col gap-0.5">
                    <span>Appr: {{ formatSimpleDate(item.approved_at || item.updated_at) }}</span>
                  </div>
                </div>
                
                <div v-else-if="item.deadline_date">
                  <span :class="`inline-block px-2 py-0.5 text-[10px] uppercase font-bold rounded ${getDeadlineStatus(item.deadline_date)?.color}`">
                    {{ getDeadlineStatus(item.deadline_date)?.text }}
                  </span>
                  <div class="text-[10px] text-gray-400 mt-1">
                    Sub: {{ formatSimpleDate(item.created_at) }}
                  </div>
                </div>

                <span v-else class="text-gray-400 text-xs italic">No Deadline</span>
              </td>
              
              <td class="px-6 py-4">
                <div class="flex flex-col gap-1">
                    <span class="text-xs font-medium text-gray-700">{{ item.crop_variation || 'General' }}</span>
                    <span class="text-[10px] text-gray-400 uppercase tracking-wide">{{ item.knowledge_type }}</span>
                </div>
              </td>
              
              <td class="px-6 py-4">
                <span v-if="isArchived" class="px-2 py-1 text-xs font-bold rounded-full bg-gray-100 text-gray-600">Archived</span>
                <span v-else-if="item.status === 'pending'" class="px-2 py-1 text-xs font-bold rounded-full bg-yellow-50 text-yellow-700 border border-yellow-100">Pending</span>
                <span v-else-if="item.status === 'approved'" class="px-2 py-1 text-xs font-bold rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100">Published</span>
                <span v-else-if="item.status === 'rejected'" class="px-2 py-1 text-xs font-bold rounded-full bg-red-50 text-red-700 border border-red-100">Rejected</span>
                
                <button @click.stop="openComments(item)" class="mt-2 text-xs flex items-center gap-1 font-medium text-blue-600 hover:text-blue-800 hover:underline">
                    <span>💬</span> Feedback
                </button>
              </td>

              <td class="px-6 py-4 text-right">
                <div class="flex items-center justify-end gap-2" @click.stop>
                  <button 
                    v-if="item.status === 'approved' && !isArchived && (item.file_path || sanitizeUrl(item.link))" 
                    @click.stop="item.file_path ? selectedResearch = item : openExternalLink(item.link)" 
                    class="p-2 rounded-full text-blue-600 hover:bg-blue-50 transition-colors"
                    :title="item.file_path ? 'View PDF' : 'Open external link'"
                  >
                    <svg v-if="item.file_path" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 010 5.656l-1.414 1.414a4 4 0 01-5.657-5.657l1.414-1.414" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M10.172 13.828a4 4 0 010-5.656l1.414-1.414a4 4 0 015.657 5.657l-1.414 1.414" />
                    </svg>
                  </button>
                  
                  <template v-else>
                    <button 
                      v-if="!isArchived" 
                      @click.stop="emit('edit', item)" 
                      class="p-2 rounded-full text-amber-500 hover:bg-amber-50 transition-colors"
                      title="Full Edit"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                      </svg>
                    </button>

                    <button 
                      v-if="!isArchived && item.status === 'rejected'"
                      @click.stop="emit('easy-resubmit', item)"
                      class="px-3 py-1.5 rounded-lg text-xs font-bold text-white bg-amber-500 hover:bg-amber-600 transition-colors shadow-sm ml-1"
                      title="Quick Resubmit"
                    >
                      Resubmit
                    </button>

                    <button 
                      @click.stop="requestArchive(item)" 
                      :class="`p-2 rounded-full transition-colors ${isArchived ? 'text-emerald-600 hover:bg-emerald-50' : 'text-red-500 hover:bg-red-50'}`"
                      :title="isArchived ? 'Restore' : 'Archive'"
                    >
                      <svg v-if="isArchived" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                      </svg>
                      <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </template>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        
        <div v-if="filteredItems.length === 0" class="text-center py-20 bg-gray-50/50 rounded-xl border-dashed border-2 border-gray-100 mt-4 mx-4">
           <div class="text-4xl opacity-20 mb-2">📂</div>
           <p class="text-gray-500 font-medium">No {{ statusFilter }} items found.</p>
        </div>
      </div>
      
      <div v-if="filteredItems.length > itemsPerPage" class="p-4 flex justify-between items-center border-t border-gray-100 bg-gray-50/50">
        <span class="text-xs text-gray-500">
          Showing {{ ((currentPage - 1) * itemsPerPage) + 1 }} to {{ Math.min(currentPage * itemsPerPage, filteredItems.length) }}
        </span>
        <div class="flex gap-2">
          <button @click="prevPage" :disabled="currentPage === 1" class="px-3 py-1 text-xs font-bold rounded border bg-white hover:bg-gray-50 disabled:opacity-50 transition shadow-sm">Previous</button>
          <span class="px-3 py-1 text-xs font-bold bg-emerald-100 text-emerald-700 rounded border border-emerald-200">{{ currentPage }} / {{ totalPages }}</span>
          <button @click="nextPage" :disabled="currentPage === totalPages" class="px-3 py-1 text-xs font-bold rounded border bg-white hover:bg-gray-50 disabled:opacity-50 transition shadow-sm">Next</button>
        </div>
      </div>
    </div>

    <Transition name="fade">
      <div v-if="commentModal.show" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 backdrop-blur-sm p-4">
        
        <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl flex flex-col h-[600px] overflow-hidden transform transition-all animate-pop">
          
          <div class="bg-white border-b px-6 py-4 flex justify-between items-center z-10 shrink-0">
            <div>
              <h3 class="font-bold text-gray-800 text-lg">💬 Feedback & Review</h3>
              <p class="text-xs text-gray-500 truncate max-w-[250px]">Topic: {{ commentModal.title }}</p>
            </div>
            <button 
              @click="commentModal.show = false" 
              class="w-8 h-8 flex items-center justify-center rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 hover:text-red-500 transition-colors"
            >
              <span class="text-xl leading-none">&times;</span>
            </button>
          </div>

          <div class="flex-1 bg-gray-50 overflow-y-auto p-4 custom-scrollbar" ref="chatContainer">
            <div v-if="commentModal.list.length === 0" class="h-full flex flex-col items-center justify-center text-gray-400 space-y-2">
              <span class="text-4xl opacity-30">💭</span>
              <p class="text-sm">No comments yet. Start the conversation.</p>
            </div>

            <TransitionGroup name="message" tag="div" class="space-y-4">
              <div 
                v-for="c in commentModal.list" 
                :key="c.id" 
                class="flex flex-col max-w-[85%]"
                :class="c.user_name === authStore.currentUser?.name ? 'self-end items-end ml-auto' : 'self-start items-start'"
              >
                <div class="flex items-center gap-2 mb-1 px-1">
                     <div v-if="c.user_name !== authStore.currentUser?.name" class="w-5 h-5 rounded-full bg-gray-200 flex items-center justify-center text-[10px] font-bold text-gray-600">
                         {{ c.user_name.charAt(0) }}
                     </div>
                     <span class="text-[10px] font-bold text-gray-500">
                        {{ c.user_name }} <span v-if="c.user_name === authStore.currentUser?.name" class="text-emerald-600">(You)</span>
                     </span>
                </div>
                
                <div 
                  class="px-4 py-2.5 shadow-sm text-sm break-words relative leading-relaxed"
                  :class="c.user_name === authStore.currentUser?.name
                    ? 'bg-emerald-600 text-white rounded-2xl rounded-tr-sm' 
                    : 'bg-white text-gray-800 rounded-2xl rounded-tl-sm border border-gray-100'"
                >
                  <p>{{ c.comment }}</p>
                </div>
                <span class="text-[9px] text-gray-300 mt-1 px-1">{{ formatSimpleDate(c.created_at) }}</span>
              </div>
            </TransitionGroup>
          </div>

          <div class="bg-white border-t p-4 shrink-0">
            <div class="relative flex items-end gap-2 bg-gray-50 rounded-xl p-2 border border-gray-200 focus-within:border-emerald-300 focus-within:ring-2 focus-within:ring-emerald-100 transition-all">
              <textarea 
                v-model="commentModal.newComment" 
                @keydown.enter.prevent="postComment" 
                placeholder="Type your reply..." 
                class="w-full bg-transparent border-none focus:ring-0 text-sm resize-none max-h-32 text-gray-700 placeholder-gray-400 py-2 pl-2"
                rows="1"
                style="min-height: 44px;"
              ></textarea>
              
              <button 
                @click="postComment" 
                :disabled="isSendingComment || !commentModal.newComment.trim()"
                class="mb-1 p-2 rounded-xl flex-shrink-0 transition-all duration-300 ease-in-out"
                :class="isSendingComment || !commentModal.newComment.trim() 
                  ? 'bg-gray-200 cursor-not-allowed text-gray-400' 
                  : 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-md hover:scale-105 active:scale-95'"
              >
                <svg v-if="isSendingComment" class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                  <path d="M3.478 2.405a.75.75 0 00-.926.94l2.432 7.905H13.5a.75.75 0 010 1.5H4.984l-2.432 7.905a.75.75 0 00.926.94 60.519 60.519 0 0018.445-8.986.75.75 0 000-1.218A60.517 60.517 0 003.478 2.405z" />
                </svg>
              </button>
            </div>
            <div class="text-[10px] text-gray-400 mt-2 text-right">Press Enter to send</div>
          </div>

        </div>
      </div>
    </Transition>
    
    <div v-if="selectedResearch" class="modal-overlay fixed inset-0 z-50 flex items-center justify-center bg-gray-900/80 backdrop-blur-sm p-4" @click.self="selectedResearch=null">
        <div class="bg-white rounded-2xl w-full max-w-4xl h-[90vh] flex flex-col shadow-2xl overflow-hidden animate-pop">
            <div class="bg-emerald-900 text-white p-4 flex justify-between items-center shrink-0">
                <h2 class="font-bold text-lg line-clamp-1">{{ selectedResearch.title }}</h2>
                <button @click="selectedResearch=null" class="text-white/70 hover:text-white transition w-8 h-8 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 font-bold">&times;</button>
            </div>
            <div class="flex-1 bg-gray-100 p-4 relative flex flex-col items-center justify-center">
                <div v-if="isPdfLoading" class="flex flex-col items-center justify-center text-gray-500 space-y-4">
                  <div class="w-12 h-12 border-4 border-emerald-500 border-t-transparent rounded-full animate-spin"></div>
                  <span class="font-medium animate-pulse">Decrypting and loading document securely...</span>
                </div>
                
                <div v-else-if="pdfError" class="text-red-500 font-bold p-6 bg-red-50 rounded-xl text-center border border-red-200">
                  <div class="text-4xl mb-2">🔒</div>
                  {{ pdfError }}
                </div>

                <iframe 
                  v-else-if="pdfBlobUrl"
                  :src="pdfBlobUrl" 
                  class="w-full h-full border-none bg-white rounded-lg shadow-sm"
                  title="Secure PDF Viewer"
                ></iframe>
            </div>
        </div>
    </div>
    
    <Transition name="pop">
      <div v-if="confirmModal.show" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 backdrop-blur-sm">
        <div class="bg-white rounded-2xl p-8 text-center w-full max-w-sm shadow-2xl transform transition-all animate-pop">
          <div class="mb-6 flex justify-center">
            <div class="w-20 h-20 rounded-full bg-gray-50 flex items-center justify-center text-4xl shadow-inner">
                {{ isArchived ? '♻️' : '🗑️' }}
            </div>
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-2">{{ confirmModal.title }}</h3>
          <p class="text-gray-500 text-sm mb-6">{{ confirmModal.subtext }}</p>
          <div class="flex gap-3 justify-center">
            <button @click="confirmModal.show=false" class="px-5 py-2.5 bg-gray-100 text-gray-600 font-bold rounded-xl hover:bg-gray-200 transition" :disabled="confirmModal.isProcessing">Cancel</button>
            <button 
                @click="executeArchive" 
                class="px-6 py-2.5 text-white font-bold rounded-xl shadow-lg transition hover:scale-105 active:scale-95" 
                :class="isArchived ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-red-600 hover:bg-red-700'"
                :disabled="confirmModal.isProcessing"
            >
                Yes, {{ isArchived ? 'Restore' : 'Archive' }}
            </button>
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

.custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }

/* Transitions */
.message-enter-active,
.message-leave-active {
  transition: all 0.3s ease;
}
.message-enter-from {
  opacity: 0;
  transform: translateY(10px);
}
.message-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}
</style>
