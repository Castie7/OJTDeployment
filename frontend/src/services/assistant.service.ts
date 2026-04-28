import api from './api'
import type {
  AssistantAnalyticsResponse,
  AssistantFeedbackRequest,
  AssistantSearchLogRequest,
  AssistantSearchLogResponse
} from '../types'

export const assistantService = {
  async logSearch(payload: AssistantSearchLogRequest): Promise<AssistantSearchLogResponse> {
    const response = await api.post<AssistantSearchLogResponse>('/api/assistant/log', payload)
    return response.data
  },

  async submitFeedback(payload: AssistantFeedbackRequest): Promise<void> {
    await api.post('/api/assistant/feedback', payload)
  },

  async getAnalytics(): Promise<AssistantAnalyticsResponse> {
    const response = await api.get<AssistantAnalyticsResponse>('/api/assistant/analytics')
    return response.data
  }
}

