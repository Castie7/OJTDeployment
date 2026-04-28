import { ref, computed, nextTick, onMounted } from 'vue'
import { researchService, commentService } from '../services'
import { useToast } from './useToast'
import { useErrorHandler } from './useErrorHandler'
import { sortResearchByNewest } from '../utils/formatters'
import type { Research, Comment } from '../types'

import { useAuthStore } from '../stores/auth'

export function useApproval() {
  const authStore = useAuthStore()

  // --- STATE ---
  const activeTab = ref<'pending' | 'rejected'>('pending')
  const items = ref<Research[]>([])
  const isLoading = ref(false)
  const selectedResearch = ref<Research | null>(null)
  const { showToast } = useToast()
  const { handleError } = useErrorHandler()

  const currentPage = ref(1)
  const itemsPerPage = 10

  // Modals
  const deadlineModal = ref({ show: false, id: null as number | null, title: '', currentDate: '', newDate: '' })
  const commentModal = ref({ show: false, researchId: null as number | null, title: '', list: [] as Comment[], newComment: '' })
  const isSendingComment = ref(false)
  const chatContainer = ref<HTMLElement | null>(null)

  // --- HELPERS ---

  const formatDate = (dateString?: any) => {
    if (!dateString) return 'No Date'

    let dateVal = dateString
    if (typeof dateString === 'object' && dateString.date) {
      dateVal = dateString.date
    }

    try {
      const d = new Date(dateVal)
      if (isNaN(d.getTime())) return dateVal

      return d.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      })
    } catch {
      return dateVal
    }
  }

  const formatSimpleDate = formatDate

  const getDaysLeft = (rejectedDate?: string) => {
    if (!rejectedDate) return 30
    const rejected = new Date(rejectedDate)
    const expiration = new Date(rejected)
    expiration.setDate(rejected.getDate() + 30)
    const today = new Date()
    const diffTime = expiration.getTime() - today.getTime()
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
    return diffDays > 0 ? diffDays : 0
  }

  // --- API ACTIONS ---

  const fetchData = async () => {
    isLoading.value = true
    items.value = []
    try {
      const fetchedItems = activeTab.value === 'pending'
        ? await researchService.getPending()
        : await researchService.getRejected()

      items.value = sortResearchByNewest(
        fetchedItems,
        activeTab.value === 'pending'
          ? ['created_at', 'updated_at']
          : ['rejected_at', 'updated_at', 'created_at']
      )

      currentPage.value = 1

    } catch (error) {
      handleError(error, 'Failed to load approval data')
    } finally {
      isLoading.value = false
    }
  }

  // --- CONFIRMATION MODAL STATE ---
  const confirmModal = ref({
    show: false,
    id: null as number | null,
    action: '' as 'approve' | 'reject' | 'restore' | 'delete',
    title: '',
    subtext: '',
    remark: '', // Required for rejection
    isProcessing: false
  })

  // Open the modal
  const handleAction = (id: number, action: 'approve' | 'reject' | 'restore' | 'delete') => {
    let title = ''
    let subtext = ''

    if (action === 'approve') {
      title = 'Approve Research?'
      subtext = 'This will publish the research. Visibility follows the item access setting.'
    } else if (action === 'reject') {
      title = 'Reject Submission?'
      subtext = 'Please provide a remark/reason for rejection. This will move the research to the Rejected Bin.'
    } else if (action === 'delete') {
      title = 'Delete Rejected Research?'
      subtext = 'This will permanently remove the record from the database and delete its PDF from the server.'
    } else {
      title = 'Restore to Pending?'
      subtext = 'This will move the research back to the Pending list for review.'
    }

    confirmModal.value = {
      show: true,
      id,
      action,
      title,
      subtext,
      remark: '', // reset reason
      isProcessing: false
    }
  }

  // Execute the action (called by modal "Yes" button)
  const executeAction = async () => {
    if (!confirmModal.value.id || confirmModal.value.isProcessing) return

    // Enforce required remark on reject
    if (confirmModal.value.action === 'reject' && !confirmModal.value.remark.trim()) {
       showToast('A remark/reason is required when rejecting a submission.', 'warning')
       return
    }

    confirmModal.value.isProcessing = true
    const { id, action, remark } = confirmModal.value

    try {
      if (action === 'approve') {
        await researchService.approve(id)
      } else if (action === 'reject') {
        // Step 1: Submit the remark as a comment from the Admin
        if (authStore.currentUser && remark.trim()) {
          await commentService.create({
            research_id: id,
            user_id: authStore.currentUser.id,
            user_name: authStore.currentUser.name,
            role: 'admin',
            comment: remark.trim()
          })
        }
        // Step 2: Reject the actual submission
        await researchService.reject(id)
      } else if (action === 'restore') {
        await researchService.restore(id)
      } else if (action === 'delete') {
        await researchService.remove(id)
      }

      const successMessage = action === 'delete'
        ? 'Rejected research deleted successfully.'
        : `Action ${action} successful!`

      showToast(successMessage, 'success')
      fetchData()
      confirmModal.value.show = false

    } catch (error) {
      handleError(error, 'Action failed')
    } finally {
      confirmModal.value.isProcessing = false
    }
  }

  const paginatedItems = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage
    const end = start + itemsPerPage
    return items.value.slice(start, end)
  })

  const totalPages = computed(() => Math.ceil(items.value.length / itemsPerPage))
  const nextPage = () => { if (currentPage.value < totalPages.value) currentPage.value++ }
  const prevPage = () => { if (currentPage.value > 1) currentPage.value-- }

  // --- DEADLINE LOGIC ---
  const openDeadlineModal = (item: Research) => {
    deadlineModal.value = {
      show: true,
      id: item.id,
      title: item.title,
      currentDate: item.deadline_date || '',
      newDate: item.deadline_date || ''
    }
  }

  const saveNewDeadline = async () => {
    if (!deadlineModal.value.newDate || !deadlineModal.value.id) return
    try {
      await researchService.extendDeadline(deadlineModal.value.id, deadlineModal.value.newDate)

      showToast("Deadline Updated!", 'success')
      deadlineModal.value.show = false
      fetchData()
    } catch (e) {
      showToast("Server Error: Failed to update deadline.", 'error')
    }
  }

  // --- COMMENTS LOGIC ---
  const scrollToBottom = () => { nextTick(() => { if (chatContainer.value) chatContainer.value.scrollTop = chatContainer.value.scrollHeight }) }

  const openComments = async (item: Research) => {
    commentModal.value = { show: true, researchId: item.id, title: item.title, list: [], newComment: '' }
    try {
      commentModal.value.list = await researchService.getComments(item.id)
      scrollToBottom()
    } catch (e) {
      handleError(e, 'Failed to load comments')
    }
  }

  const postComment = async () => {
    if (isSendingComment.value || !commentModal.value.newComment.trim() || !authStore.currentUser) return
    isSendingComment.value = true
    try {
      await commentService.create({
        research_id: commentModal.value.researchId!,
        user_id: authStore.currentUser.id,
        user_name: authStore.currentUser.name,
        role: 'admin',
        comment: commentModal.value.newComment
      })

      // Refresh comments
      commentModal.value.list = await researchService.getComments(commentModal.value.researchId!)
      commentModal.value.newComment = ''
      scrollToBottom()

    } catch (e: any) {
      showToast("Failed: " + (e.response?.data?.message || e.message), 'error')
    } finally {
      isSendingComment.value = false
    }
  }

  onMounted(() => fetchData())

  return {
    activeTab, items, isLoading, selectedResearch,
    currentPage, itemsPerPage, paginatedItems, totalPages, nextPage, prevPage,
    deadlineModal, commentModal, isSendingComment, chatContainer,
    fetchData, handleAction, executeAction, formatDate, getDaysLeft,
    openDeadlineModal, saveNewDeadline, openComments, postComment,
    formatSimpleDate,
    confirmModal // Export the modal state
  }
}
