// src/services/research.service.ts

import api from './api'
import { apiCache } from '../utils/apiCache'
import type {
  Research,
  ResearchFilters,
  ApiResponse,
  Comment
} from '../types'

interface BulkAccessLevelResponse {
  status: 'success' | 'error'
  message?: string
  access_level: 'public' | 'private'
  matched: number
  updated: number
}

/**
 * Research Service
 * Handles all research-related API operations
 */
export const researchService = {
  /**
   * Get all approved research items (for library/public view)
   */
  async getById(id: number): Promise<Research> {
    return apiCache.get(`research:${id}`, async () => {
      const response = await api.get<Research>(`research/${id}`)
      return response.data
    })
  },

  async getAll(filters?: ResearchFilters): Promise<Research[]> {
    const params = new URLSearchParams()
    if (filters?.start_date) params.append('start_date', filters.start_date)
    if (filters?.end_date) params.append('end_date', filters.end_date)
    if (filters?.knowledge_type && filters.knowledge_type.trim().length > 0) {
      params.append('knowledge_type', filters.knowledge_type.trim())
    }
    if (filters?.author && filters.author.trim().length > 0) {
      params.append('author', filters.author.trim())
    }
    if (filters?.crop_variation && filters.crop_variation.trim().length > 0) {
      params.append('crop_variation', filters.crop_variation.trim())
    }
    if (filters?.access_level) {
      params.append('access_level', filters.access_level)
    }
    if (filters?.search && filters.search.trim().length > 0) {
      params.append('search', filters.search.trim())
    }
    if (filters?.search_mode) {
      params.append('search_mode', filters.search_mode)
    }
    if (filters?.search_scope) {
      params.append('search_scope', filters.search_scope)
    }
    if (filters?.strict) {
      params.append('strict', '1')
    }
    if (typeof filters?.limit === 'number' && filters.limit > 0) {
      params.append('limit', String(Math.min(50, Math.floor(filters.limit))))
    }

    const queryString = params.toString()
    const endpoint = queryString ? `research?${queryString}` : 'research'
    const cacheKey = `research:all:${queryString}`

    return apiCache.get(cacheKey, async () => {
      const response = await api.get<Research[]>(endpoint)
      return response.data
    })
  },

  async getTopViewed(limit = 5): Promise<Research[]> {
    const safeLimit = Math.min(50, Math.max(1, Math.floor(limit)))
    const cacheKey = `research:top-viewed:${safeLimit}`

    return apiCache.get(cacheKey, async () => {
      const response = await api.get<Research[]>(`research/top-viewed?limit=${safeLimit}`)
      return response.data
    })
  },

  /**
   * Get all research items for admin masterlist (includes all statuses)
   */
  async getMasterlist(): Promise<Research[]> {
    return apiCache.get('research:masterlist', async () => {
      const response = await api.get<Research[]>('research/masterlist')
      return response.data
    })
  },

  /**
   * Get archived research items
   */
  async getArchived(filters?: ResearchFilters): Promise<Research[]> {
    const params = new URLSearchParams()
    if (filters?.start_date) params.append('start_date', filters.start_date)
    if (filters?.end_date) params.append('end_date', filters.end_date)

    const queryString = params.toString()
    const endpoint = queryString ? `research/archived?${queryString}` : 'research/archived'
    const cacheKey = `research:archived:${queryString}`

    return apiCache.get(cacheKey, async () => {
      const response = await api.get<Research[]>(endpoint)
      return response.data
    })
  },

  /**
   * Get pending research submissions (admin only)
   */
  async getPending(): Promise<Research[]> {
    return apiCache.get('research:pending', async () => {
      const response = await api.get<Research[]>('research/pending')
      return response.data
    })
  },

  /**
   * Get rejected research submissions (admin only)
   */
  async getRejected(): Promise<Research[]> {
    return apiCache.get('research:rejected', async () => {
      const response = await api.get<Research[]>('research/rejected')
      return response.data
    })
  },

  /**
   * Get current user's research submissions
   */
  async getMySubmissions(): Promise<Research[]> {
    return apiCache.get('research:my-submissions', async () => {
      const response = await api.get<Research[]>('research/my-submissions')
      return response.data
    })
  },

  /**
   * Get current user's archived submissions
   */
  async getMyArchived(): Promise<Research[]> {
    return apiCache.get('research:my-archived', async () => {
      const response = await api.get<Research[]>('research/my-archived')
      return response.data
    })
  },

  /**
   * Create new research submission
   */
  async create(data: FormData): Promise<ApiResponse<Research>> {
    const response = await api.post<ApiResponse<Research>>('research', data)
    apiCache.invalidate('research')
    return response.data
  },

  /**
   * Update existing research submission
   */
  async update(id: number, data: FormData): Promise<ApiResponse<Research>> {
    // For file uploads in multipart/form-data, many servers (including CI4) prefer POST with an _method mapping
    // or just POST. We left the POST alias in routes.
    const response = await api.post<ApiResponse<Research>>(`research/${id}`, data)
    apiCache.invalidate('research')
    return response.data
  },

  /**
   * Approve research submission (admin only)
   */
  async approve(id: number): Promise<ApiResponse<void>> {
    const response = await api.post<ApiResponse<void>>(`research/${id}/approve`)
    apiCache.invalidate('research')
    return response.data
  },

  /**
   * Reject research submission (admin only)
   */
  async reject(id: number): Promise<ApiResponse<void>> {
    const response = await api.post<ApiResponse<void>>(`research/${id}/reject`)
    apiCache.invalidate('research')
    return response.data
  },

  /**
   * Restore rejected research to pending (admin only)
   */
  async restore(id: number): Promise<ApiResponse<void>> {
    const response = await api.post<ApiResponse<void>>(`research/${id}/restore`)
    apiCache.invalidate('research')
    return response.data
  },

  /**
   * Permanently delete rejected or archived research (admin only)
   */
  async remove(id: number): Promise<ApiResponse<void>> {
    const response = await api.post<ApiResponse<void>>(`research/${id}/delete`)
    apiCache.invalidate('research')
    return response.data
  },

  /**
   * Archive research item
   */
  async archive(id: number): Promise<ApiResponse<void>> {
    const response = await api.post<ApiResponse<void>>(`research/${id}/archive`)
    apiCache.invalidate('research')
    return response.data
  },

  async trackView(id: number): Promise<void> {
    await api.post(`research/${id}/view`)
    apiCache.invalidate('research:top-viewed')
  },

  /**
   * Bulk update visibility (admin only)
   */
  async bulkUpdateAccessLevel(ids: number[], accessLevel: 'public' | 'private'): Promise<BulkAccessLevelResponse> {
    const response = await api.post<BulkAccessLevelResponse>('research/bulk-access-level', {
      ids,
      access_level: accessLevel
    })
    apiCache.invalidate('research')
    return response.data
  },

  /**
   * Extend deadline for research submission (admin only)
   */
  async extendDeadline(id: number, newDeadline: string): Promise<ApiResponse<void>> {
    const formData = new FormData()
    formData.append('new_deadline', newDeadline)

    const response = await api.post<ApiResponse<void>>(`research/${id}/extend-deadline`, formData)
    apiCache.invalidate('research')
    return response.data
  },

  /**
   * Get comments for a research item
   */
  async getComments(id: number): Promise<Comment[]> {
    return apiCache.get(`research:comments:${id}`, async () => {
      const response = await api.get<Comment[]>(`research/comments/${id}`)
      return response.data
    }, 60_000) // 1-min TTL for comments (more dynamic data)
  }
}
