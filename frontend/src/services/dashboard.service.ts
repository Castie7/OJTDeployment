// src/services/dashboard.service.ts

import api from './api'
import type { DashboardStats } from '../types'

/**
 * Dashboard Service
 * Handles dashboard statistics and data
 */
export const dashboardService = {
  /**
   * Get dashboard statistics
   */
  async getStats(): Promise<DashboardStats> {
    const response = await api.get<DashboardStats>('/research/stats')
    return response.data
  }
}
