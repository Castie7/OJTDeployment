
import { ref, watch, nextTick, computed, onMounted } from 'vue'
import { researchService, commentService } from '../services'
import { useToast } from './useToast'
import { useErrorHandler } from './useErrorHandler'
import { sortResearchByNewest } from '../utils/formatters'
import type { Research, Comment } from '../types'

import { useAuthStore } from '../stores/auth'

export function useSubmittedResearches(props: { statusFilter: string }) {
    const authStore = useAuthStore()

    // --- STATE ---
    const myItems = ref<Research[]>([])
    const isLoading = ref(false)
    const searchQuery = ref('')
    const currentPage = ref(1)
    const itemsPerPage = 10

    // UI State
    const editingItem = ref<Research | null>(null)
    const editPdfFile = ref<File | null>(null)
    const isSaving = ref(false)
    const selectedResearch = ref<Research | null>(null)
    const { showToast } = useToast()
    const { handleError } = useErrorHandler()

    // Modal State
    const commentModal = ref({
        show: false,
        researchId: null as number | null,
        title: '',
        list: [] as Comment[],
        newComment: ''
    })
    const isSendingComment = ref(false)
    const chatContainer = ref<HTMLElement | null>(null)
    const confirmModal = ref({
        show: false,
        id: null as number | null,
        action: '',
        title: '',
        subtext: '',
        isProcessing: false
    })

    // --- HELPERS ---
    const getDeadlineStatus = (deadline?: string) => {
        if (!deadline) return null
        const today = new Date()
        const due = new Date(deadline)
        const diffTime = due.getTime() - today.getTime()
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))

        if (diffDays < 0) return { text: `Overdue by ${Math.abs(diffDays)} days`, color: 'text-red-600 bg-red-100' }
        if (diffDays === 0) return { text: 'Due Today!', color: 'text-red-600 font-bold bg-red-100' }
        if (diffDays <= 7) return { text: `${diffDays} days left`, color: 'text-yellow-700 bg-yellow-100' }

        return { text: due.toLocaleDateString(), color: 'text-gray-500' }
    }

    const formatSimpleDate = (dateStr?: any) => {
        if (!dateStr) return 'N/A'

        let dateVal = dateStr
        // Handle { date: "...", timezone: ... } object from backend
        if (typeof dateStr === 'object' && dateStr.date) {
            dateVal = dateStr.date
        }

        try {
            const d = new Date(dateVal)
            // Check if valid date
            if (isNaN(d.getTime())) return dateVal

            return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
        } catch (e) {
            return dateVal
        }
    }

    // --- FETCH DATA ---
    const fetchData = async () => {
        isLoading.value = true
        try {
            // Use getMyArchived or getMySubmissions based on filter
            if (props.statusFilter === 'archived') {
                myItems.value = await researchService.getMyArchived()
            } else {
                myItems.value = await researchService.getMySubmissions()
            }

            currentPage.value = 1
            searchQuery.value = ''

        } catch (error) {
            handleError(error, 'Failed to load submissions')
        } finally {
            isLoading.value = false
        }
    }

    // --- SEARCH & PAGINATION ---
    // --- SEARCH & PAGINATION ---
    const filteredItems = computed(() => {
        let items = myItems.value

        // 1. Filter by status (if not archived)
        if (props.statusFilter !== 'archived') {
            items = items.filter(item => item.status === props.statusFilter)
        }

        // 2. Filter by search query
        if (searchQuery.value) {
            const query = searchQuery.value.toLowerCase()
            items = items.filter(item =>
                item.title.toLowerCase().includes(query) ||
                item.author.toLowerCase().includes(query)
            )
        }

        const sortFields: Array<keyof Research> =
            props.statusFilter === 'archived'
                ? ['archived_at', 'updated_at', 'created_at']
                : props.statusFilter === 'approved'
                    ? ['approved_at', 'updated_at', 'created_at']
                    : props.statusFilter === 'rejected'
                        ? ['rejected_at', 'updated_at', 'created_at']
                        : ['created_at', 'updated_at']

        return sortResearchByNewest(items, sortFields)
    })

    const paginatedItems = computed(() => {
        const start = (currentPage.value - 1) * itemsPerPage
        const end = start + itemsPerPage
        return filteredItems.value.slice(start, end)
    })

    const totalPages = computed(() => Math.ceil(filteredItems.value.length / itemsPerPage))

    // Watchers & Lifecycle
    watch(searchQuery, () => { currentPage.value = 1 })
    watch(() => props.statusFilter, (newVal, oldVal) => {
        // If we switch TO 'archived', or FROM 'archived', or have no items, we must fetch fresh data.
        // Because 'archived' uses a different API endpoint than the other statuses.
        if (newVal === 'archived' || oldVal === 'archived' || myItems.value.length === 0) {
            fetchData()
        }
    })
    onMounted(() => fetchData())

    const nextPage = () => { if (currentPage.value < totalPages.value) currentPage.value++ }
    const prevPage = () => { if (currentPage.value > 1) currentPage.value-- }

    // --- ACTIONS ---
    const requestArchive = (item: Research) => {
        const action = props.statusFilter === 'archived' ? 'Restore' : 'Archive'
        confirmModal.value = {
            show: true, id: item.id, action: action,
            title: action === 'Archive' ? 'Move to Trash?' : 'Restore File?',
            subtext: action === 'Archive' ? `Remove "${item.title}" ? ` : `Restore "${item.title}" ? `,
            isProcessing: false
        }
    }

    const executeArchive = async () => {
        if (!confirmModal.value.id || confirmModal.value.isProcessing) return
        confirmModal.value.isProcessing = true
        try {
            if (props.statusFilter === 'archived') {
                // Restore
                await researchService.restore(confirmModal.value.id)
            } else {
                // Archive
                await researchService.archive(confirmModal.value.id)
            }

            confirmModal.value.show = false
            showToast(`${confirmModal.value.action} successful!`, 'success')
            fetchData()

        } catch (e) {
            handleError(e, 'Archive action failed')
        } finally {
            confirmModal.value.isProcessing = false
        }
    }

    // --- COMMENTS ---
    const openComments = async (item: Research) => {
        commentModal.value = { show: true, researchId: item.id, title: item.title, list: [], newComment: '' }
        try {
            commentModal.value.list = await researchService.getComments(item.id)
            nextTick(() => { if (chatContainer.value) chatContainer.value.scrollTop = chatContainer.value.scrollHeight })
        } catch (e) { }
    }

    const postComment = async () => {
        if (isSendingComment.value || !commentModal.value.newComment.trim() || !authStore.currentUser || !commentModal.value.researchId) return
        isSendingComment.value = true
        try {
            await commentService.create({
                research_id: commentModal.value.researchId,
                user_id: authStore.currentUser.id,
                user_name: authStore.currentUser.name,
                role: authStore.currentUser.role,
                comment: commentModal.value.newComment
            })

            // Refresh comments
            commentModal.value.list = await researchService.getComments(commentModal.value.researchId)
            commentModal.value.newComment = ''
            nextTick(() => { if (chatContainer.value) chatContainer.value.scrollTop = chatContainer.value.scrollHeight })
        } catch (e: any) {
            showToast("Failed: " + (e.response?.data?.message || e.message), "error")
        } finally {
            isSendingComment.value = false
        }
    }

    // --- EDIT LOGIC ---
    const openEdit = (item: Research) => { editingItem.value = { ...item }; editPdfFile.value = null }

    const handleEditFile = (e: Event) => {
        const target = e.target as HTMLInputElement
        const file = target.files?.[0]
        if (!file) { editPdfFile.value = null; return }

        const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png']
        if (!allowedExtensions.includes(file.name.split('.').pop()?.toLowerCase() || '')) {
            showToast("Invalid File!", "error")
            target.value = ''
            editPdfFile.value = null
            return
        }
        editPdfFile.value = file
    }

    const saveEdit = async () => {
        if (isSaving.value || !editingItem.value) return
        const item = editingItem.value

        if (!item.title.trim() || !item.author.trim() || !item.deadline_date) {
            showToast("Missing Fields", "warning")
            return
        }

        isSaving.value = true
        const formData = new FormData()
        formData.append('title', item.title)
        formData.append('author', item.author)
        formData.append('abstract', item.abstract || '')
        formData.append('start_date', item.start_date || '')
        formData.append('deadline_date', item.deadline_date)
        if (editPdfFile.value) formData.append('pdf_file', editPdfFile.value)

        try {
            await researchService.update(item.id, formData)
            showToast("Updated!", "success")
            editingItem.value = null
            fetchData()

        } catch (e) {
            handleError(e, 'Failed to update research')
        } finally {
            isSaving.value = false
        }
    }

    return {
        myItems, isLoading, searchQuery, currentPage, itemsPerPage,
        editingItem, isSaving, selectedResearch, commentModal, isSendingComment,
        chatContainer, confirmModal,
        fetchData, filteredItems, paginatedItems, totalPages, nextPage, prevPage,
        requestArchive, executeArchive, openComments, postComment,
        openEdit, handleEditFile, saveEdit,
        getDeadlineStatus, formatSimpleDate
    }
}
