// src/services/auth.service.ts

import api from './api'
import type {
  LoginRequest,
  LoginResponse,
  VerifyResponse,
  RegisterRequest,
  ApiResponse,
  User
} from '../types'

/**
 * Authentication Service
 * Handles user authentication, registration, and session verification
 */
export const authService = {
  /**
   * Authenticate user with email and password
   */
  async login(credentials: LoginRequest): Promise<LoginResponse> {
    const response = await api.post<LoginResponse>('/auth/login', credentials)
    return response.data
  },

  /**
   * Log out current user
   */
  async logout(): Promise<void> {
    await api.post('/auth/logout')
  },

  /**
   * Verify current session and get CSRF token
   */
  async verify(): Promise<VerifyResponse> {
    const response = await api.get<VerifyResponse>('/auth/verify')
    return response.data
  },

  /**
   * Register a new user (admin only)
   */
  async register(userData: RegisterRequest): Promise<ApiResponse<User>> {
    const response = await api.post<ApiResponse<User>>('/auth/register', userData)
    return response.data
  }
}
