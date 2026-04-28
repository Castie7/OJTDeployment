import { ref } from 'vue'
import { adminService } from '../services'
import type { ActivityLog } from '../types'

export function useAdminLogs() {
    const logs = ref<ActivityLog[]>([])
    const loading = ref(false)
    const pagination = ref({
        currentPage: 1,
        totalPages: 1,
        perPage: 20
    })

    // Filter State
    const filters = ref({
        action: 'ALL',
        startDate: '',
        endDate: '',
        search: ''
    })

    // Fetch logs from DB
    const fetchLogs = async (page = 1) => {
        loading.value = true
        try {
            const response = await adminService.getLogs({
                page,
                limit: pagination.value.perPage,
                search: filters.value.search,
                action: filters.value.action,
                start_date: filters.value.startDate,
                end_date: filters.value.endDate
            })

            logs.value = response.data

            if (response.pager) {
                pagination.value.currentPage = response.pager.currentPage
                pagination.value.totalPages = response.pager.pageCount
            }
        } catch (error) {
            console.error("Failed to fetch activity logs", error)
            logs.value = []
        } finally {
            loading.value = false
        }
    }

    const downloadLogs = () => {
        const url = adminService.getExportLogsUrl({
            search: filters.value.search,
            action: filters.value.action,
            start_date: filters.value.startDate,
            end_date: filters.value.endDate
        })

        window.open(url, '_blank')
    }

    const formatActionColor = (action: string) => {
        switch (action) {
            case 'LOGIN': return 'bg-green-100 text-green-800'
            case 'LOGOUT': return 'bg-gray-100 text-gray-800'
            case 'CREATE_RESEARCH': return 'bg-blue-100 text-blue-800'
            case 'UPDATE_PROFILE': return 'bg-purple-100 text-purple-800'
            case 'REJECT_RESEARCH': return 'bg-red-100 text-red-800'
            case 'ARCHIVE_RESEARCH': return 'bg-yellow-100 text-yellow-800'
            case 'APPROVE_RESEARCH': return 'bg-green-100 text-green-800'
            default: return 'bg-gray-100 text-gray-800'
        }
    }

    return {
        logs,
        loading,
        pagination,
        filters,
        fetchLogs,
        downloadLogs,
        formatActionColor
    }
}
