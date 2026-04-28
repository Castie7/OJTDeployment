<script setup lang="ts">
import { watch, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { useApproval } from '../../../composables/useApproval'
import { researchService } from '../../../services'
import { useAuthStore } from '../../../stores/auth'
import ResearchDetailsModal from './ResearchDetailsModal.vue'

const route = useRoute()
const authStore = useAuthStore()


const {
  activeTab, items, isLoading, selectedResearch, 
  currentPage, itemsPerPage, paginatedItems, totalPages, nextPage, prevPage,
  deadlineModal, commentModal, isSendingComment, chatContainer,
  fetchData, handleAction, executeAction, formatDate, getDaysLeft,
  openDeadlineModal, saveNewDeadline, openComments, postComment,
  confirmModal
} = useApproval()

// --- NEW: Handle Notification Clicks from Dashboard ---
const openNotification = async (researchId: number) => {
  try {
      // 1. Fetch item to know status
      const item = await researchService.getById(researchId)
      if (!item) return

      // 2. Determine Tab
      const targetTab = (item.status === 'rejected') ? 'rejected' : 'pending' // Default to pending for approved/pending items in this view? 
      // Actually Approval view only shows Pending and Rejected (and maybe Approved are hidden or history?)
      // The tabs are 'pending' and 'rejected'.
      // If it's approved, it shouldn't be here? Admin usually sees pending. 
      // If it's approved, maybe we shouldn't redirect or just show it in pending if it's there?
      // Assuming notification is for pending/rejected/feedback.

      // 3. Switch if needed
      if (activeTab.value !== targetTab) {
          activeTab.value = targetTab
          await nextTick()
          await fetchData()
      } else {
          // Even if tab is same, refresh to ensure we have the item
          await fetchData()
      }

      // 4. Find and Open
      const targetItem = items.value.find(i => i.id === researchId)
      if (targetItem) {
          openComments(targetItem)
      } else {
          // If still not found (e.g. approved item not in pending list), maybe warn or ignore?
          // But user said it IS pending. So it should be found now.
      }
      
  } catch (e) {
      console.error(e)
  }
}

// Expose this function so Dashboard.vue can call it via ref
defineExpose({ openNotification })

// Watch for query param changes
watch(() => route.query.open, (newId) => {
    if (newId) openNotification(Number(newId))
}, { immediate: true })


// Fix for vue-tsc unused variable error
void chatContainer
void confirmModal
void isSendingComment
</script>

<template>
  <div class="bg-white p-6 rounded-lg shadow-lg min-h-[500px]">
    
    <div class="mb-6 border-b pb-4 flex flex-col md:flex-row justify-between items-center gap-4">
      <div>
        <h2 class="text-xl font-bold text-gray-800">📋 Approvals & Rejections</h2>
        <p class="text-sm text-gray-500">Manage submissions and restore rejected items.</p>
      </div>
      <div class="flex bg-gray-100 p-1 rounded-lg">
        <button @click="activeTab = 'pending'; fetchData()" :class="`px-4 py-2 text-sm font-bold rounded-md transition ${activeTab === 'pending' ? 'bg-white text-green-700 shadow' : 'text-gray-500 hover:text-gray-700'}`">⏳ Pending</button>
        <button @click="activeTab = 'rejected'; fetchData()" :class="`px-4 py-2 text-sm font-bold rounded-md transition ${activeTab === 'rejected' ? 'bg-white text-red-600 shadow' : 'text-gray-500 hover:text-gray-700'}`">🗑️ Rejected Bin</button>
      </div>
    </div>

    <div v-if="isLoading" class="text-center py-10 text-gray-400">Loading...</div>

    <div v-else class="flex flex-col min-h-[400px]">
      <div class="overflow-x-auto flex-1">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Details</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">{{ activeTab === 'pending' ? 'Deadline' : 'Auto-Delete In' }}</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Review</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="item in paginatedItems" :key="item.id" v-memo="[item.id, item.status, item.title, item.updated_at]" class="hover:bg-green-50 transition cursor-pointer" @click="selectedResearch = item">
              <td class="px-6 py-4">
                <div class="font-bold text-gray-900 line-clamp-2 max-w-[300px]" :title="item.title">{{ item.title }}</div>
                <div class="text-sm text-gray-500">By: {{ item.author }}</div>
              </td>
              <td class="px-6 py-4">
                <div v-if="activeTab === 'pending'" class="flex items-center gap-2">
                  <span :class="`text-sm font-medium ${!item.deadline_date ? 'text-gray-400' : 'text-gray-700'}`">{{ formatDate(item.deadline_date) }}</span>
                  <button @click.stop="openDeadlineModal(item)" class="text-gray-400 hover:text-green-600 transition" title="Extend Deadline">🕒</button>
                </div>
                <div v-else><span class="text-xs font-bold px-2 py-1 rounded bg-red-100 text-red-700 border border-red-200 whitespace-nowrap">⚠️ {{ getDaysLeft(item.rejected_at) }} Days left</span></div>
              </td>
              <td class="px-6 py-4"><button @click.stop="openComments(item)" class="text-blue-600 hover:text-blue-800 text-sm font-bold flex items-center gap-1">💬 Comments</button></td>
              <td class="px-6 py-4 text-right">
                <div class="flex items-center justify-end gap-2">
                  <template v-if="activeTab === 'pending'">
                    <button 
                      @click.stop="handleAction(item.id, 'approve')" 
                      class="p-2.5 rounded-full text-green-600 hover:bg-green-100 transition-colors"
                      title="Approve"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                      </svg>
                    </button>
                    <button 
                      @click.stop="handleAction(item.id, 'reject')" 
                      class="p-2.5 rounded-full text-red-500 hover:bg-red-100 transition-colors"
                      title="Reject"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </template>
                  <template v-else>
                    <button 
                      @click.stop="handleAction(item.id, 'restore')" 
                      class="p-2.5 rounded-full text-blue-600 hover:bg-blue-100 transition-colors"
                      title="Restore to Pending"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                      </svg>
                    </button>
                    <button
                      @click.stop="handleAction(item.id, 'delete')"
                      class="p-2.5 rounded-full text-red-600 hover:bg-red-100 transition-colors"
                      title="Delete Permanently"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7l1 12a1 1 0 001 1h4a1 1 0 001-1l1-12" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 11v5M14 11v5" />
                      </svg>
                    </button>
                  </template>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="items.length === 0" class="text-center py-10 text-gray-500">{{ activeTab === 'pending' ? 'No pending items to review.' : 'The rejected bin is empty.' }}</div>
      </div>

      <div v-if="items.length > itemsPerPage" class="mt-4 flex justify-between items-center border-t pt-4">
        <span class="text-sm text-gray-500">Showing {{ ((currentPage - 1) * itemsPerPage) + 1 }} to {{ Math.min(currentPage * itemsPerPage, items.length) }} of {{ items.length }} entries</span>
        <div class="flex gap-2">
          <button @click="prevPage" :disabled="currentPage === 1" class="px-3 py-1 text-sm font-bold rounded-lg border bg-white hover:bg-gray-50 disabled:opacity-50 transition">Previous</button>
          <span class="px-3 py-1 text-sm font-bold bg-green-50 text-green-700 rounded-lg border border-green-200">Page {{ currentPage }} of {{ totalPages }}</span>
          <button @click="nextPage" :disabled="currentPage === totalPages" class="px-3 py-1 text-sm font-bold rounded-lg border bg-white hover:bg-gray-50 disabled:opacity-50 transition">Next</button>
        </div>
      </div>
    </div>

    <Transition name="fade">
      <div v-if="deadlineModal.show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-75 backdrop-blur-sm">
        <div class="bg-white rounded-lg w-full max-w-sm p-6 shadow-2xl">
          <h3 class="font-bold text-lg mb-2 text-gray-800">🕒 Extend Deadline</h3>
          <p class="text-sm text-gray-500 mb-4">Set a new due date for: <br><b>{{ deadlineModal.title }}</b></p>
          <input v-model="deadlineModal.newDate" type="date" class="w-full border p-2 rounded mb-6 focus:ring-2 focus:ring-green-500 outline-none"/>
          <div class="flex justify-end gap-2">
            <button @click="deadlineModal.show = false" class="px-4 py-2 text-gray-500 font-bold hover:bg-gray-100 rounded">Cancel</button>
            <button @click="saveNewDeadline" class="px-4 py-2 bg-green-600 text-white font-bold rounded hover:bg-green-700">Update Date</button>
          </div>
        </div>
      </div>
    </Transition>

    <ResearchDetailsModal 
      :research="selectedResearch" 
      @close="selectedResearch = null" 
    />
    
    <Transition name="fade">
      <div v-if="commentModal.show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl flex flex-col h-[600px] overflow-hidden transform transition-all">
          
          <div class="bg-white border-b px-6 py-4 flex justify-between items-center z-10">
            <div>
              <h3 class="font-bold text-gray-800 text-lg">Feedback & Review</h3>
              <p class="text-xs text-gray-500 truncate max-w-[250px]">Submission: {{ commentModal.title }}</p>
            </div>
            <button @click="commentModal.show = false" class="w-8 h-8 flex items-center justify-center rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 hover:text-red-500 transition-colors">
              <span class="text-xl leading-none">&times;</span>
            </button>
          </div>

          <div class="flex-1 bg-gray-50 overflow-y-auto p-4 custom-scrollbar" ref="chatContainer">
            <div v-if="commentModal.list.length === 0" class="h-full flex flex-col items-center justify-center text-gray-400 space-y-2">
              <span class="text-4xl">💬</span>
              <p class="text-sm">No remarks yet.</p>
            </div>

            <TransitionGroup name="message" tag="div" class="space-y-3">
              <div 
                v-for="c in commentModal.list" 
                :key="c.id" 
                class="flex flex-col max-w-[85%]"
                :class="c.user_name === authStore.currentUser?.name ? 'self-end items-end ml-auto' : 'self-start items-start'"
              >
                <span class="text-[10px] text-gray-400 mb-1 px-1">
                  {{ c.user_name }} <span v-if="c.user_name === authStore.currentUser?.name" class="text-green-600 font-bold">(You)</span>
                </span>
                
                <div 
                  class="px-4 py-2.5 shadow-sm text-sm break-words relative"
                  :class="c.user_name === authStore.currentUser?.name
                    ? 'bg-green-600 text-white rounded-2xl rounded-tr-none' 
                    : 'bg-white text-gray-800 rounded-2xl rounded-tl-none border border-gray-100'"
                >
                  <p>{{ c.comment }}</p>
                </div>
              </div>
            </TransitionGroup>
          </div>

          <div class="bg-white border-t p-4">
            <div class="relative flex items-end gap-2 bg-gray-100 rounded-xl p-2 border border-transparent focus-within:border-green-300 focus-within:ring-2 focus-within:ring-green-100 transition-all">
              <textarea 
                v-model="commentModal.newComment" 
                @keydown.enter.prevent="postComment" 
                placeholder="Type your feedback..." 
                class="w-full bg-transparent border-none focus:ring-0 text-sm resize-none max-h-32 text-gray-700 placeholder-gray-400 py-2 pl-2"
                rows="1"
                style="min-height: 44px;"
              ></textarea>
              
              <button 
                @click="postComment" 
                :disabled="isSendingComment || !commentModal.newComment.trim()"
                class="mb-1 p-2 rounded-full flex-shrink-0 transition-all duration-300 ease-in-out"
                :class="isSendingComment || !commentModal.newComment.trim() 
                  ? 'bg-gray-300 cursor-not-allowed text-gray-500' 
                  : 'bg-green-600 hover:bg-green-700 text-white shadow-md hover:scale-105 active:scale-95'"
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


    <Transition name="pop">
      <div v-if="confirmModal.show" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm">
        <div class="bg-white rounded-2xl p-6 text-center w-full max-w-sm shadow-2xl transform transition-all">
          <div class="mb-4 text-5xl">{{ confirmModal.action === 'reject' || confirmModal.action === 'delete' ? '🗑️' : (confirmModal.action === 'restore' ? '♻️' : '✅') }}</div>
          <h3 class="text-xl font-bold text-gray-900 mb-2">{{ confirmModal.title }}</h3>
          <p class="text-gray-500 text-sm mb-4">{{ confirmModal.subtext }}</p>

          <div v-if="confirmModal.action === 'reject'" class="mb-6 text-left">
             <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Remarks / Reason <span class="text-red-500">*</span></label>
             <textarea 
               v-model="confirmModal.remark" 
               placeholder="Why are you rejecting this submission?"
               class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-red-500 outline-none text-sm resize-none bg-gray-50 focus:bg-white transition-colors"
               rows="3"
             ></textarea>
          </div>

          <div class="flex gap-3 justify-center">
            <button @click="confirmModal.show=false" class="px-5 py-2 bg-gray-100 text-gray-700 font-bold rounded-lg hover:bg-gray-200 transition" :disabled="confirmModal.isProcessing">Cancel</button>
            <button 
                @click="executeAction" 
                class="px-5 py-2 text-white font-bold rounded-lg shadow-lg transition" 
                :class="confirmModal.action === 'reject' || confirmModal.action === 'delete' ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700'"
                :disabled="confirmModal.isProcessing"
            >
                <span v-if="confirmModal.isProcessing" class="animate-spin mr-2">⏳</span>
                Yes, {{ confirmModal.action === 'restore' ? 'Restore' : (confirmModal.action === 'reject' ? 'Reject' : (confirmModal.action === 'delete' ? 'Delete' : 'Approve')) }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped src="../../../assets/styles/Approval.css"></style>
