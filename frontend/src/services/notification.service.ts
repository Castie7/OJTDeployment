// src/services/notification.service.ts

import api from './api'
import type { Notification, ApiResponse } from '../types'

/**
 * Notification Service
 * Handles notification operations
 */
export const notificationService = {
  /**
   * Get all notifications for current user
   */
  async getAll(): Promise<Notification[]> {
    const response = await api.get<Notification[]>('/api/notifications')
    return response.data
  },

  /**
   * Mark all notifications as read for a user
   */
  async markAllAsRead(): Promise<ApiResponse<void>> {
    const response = await api.post<ApiResponse<void>>('/api/notifications/read')
    return response.data
  }
}
