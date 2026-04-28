// src/services/admin.service.ts

import api, { getBaseUrl } from './api'
import type {
  User,
  ActivityLog,
  LogFilters,
  PaginatedResponse,
  ResetPasswordRequest,
  UpdateUserStatusRequest,
  ApiResponse
} from '../types'

/**
 * Admin Service
 * Handles administrative operations
 */
export const adminService = {
  /**
   * Get all users (admin only)
   */
  async getUsers(): Promise<User[]> {
    const response = await api.get<User[]>('/admin/users')
    return response.data
  },

  /**
   * Reset user password (admin only)
   */
  async resetPassword(request: ResetPasswordRequest): Promise<ApiResponse<void>> {
    const response = await api.post<ApiResponse<void>>('/admin/reset-password', request)
    return response.data
  },

  /**
   * Enable or disable a user account (admin only)
   */
  async updateUserStatus(userId: number, request: UpdateUserStatusRequest): Promise<ApiResponse<void>> {
    const response = await api.post<ApiResponse<void>>(`/admin/users/${userId}/status`, request)
    return response.data
  },

  /**
   * Get activity logs with pagination and filters (admin only)
   */
  async getLogs(filters: LogFilters): Promise<PaginatedResponse<ActivityLog>> {
    const params = {
      page: filters.page,
      limit: filters.limit,
      search: filters.search,
      action: filters.action !== 'ALL' ? filters.action : undefined,
      start_date: filters.start_date || undefined,
      end_date: filters.end_date || undefined
    }

    const response = await api.get<PaginatedResponse<ActivityLog>>('/api/logs', { params })
    return response.data
  },

  /**
   * Get URL for exporting logs (opens in new window)
   */
  getExportLogsUrl(filters: LogFilters): string {
    const params = new URLSearchParams()
    if (filters.search) params.append('search', filters.search)
    if (filters.action && filters.action !== 'ALL') params.append('action', filters.action)
    if (filters.start_date) params.append('start_date', filters.start_date)
    if (filters.end_date) params.append('end_date', filters.end_date)

    const baseUrl = getBaseUrl()
    return `${baseUrl}/api/logs/export?${params.toString()}`
  }
}
