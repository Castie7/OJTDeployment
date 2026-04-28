// src/services/api.ts

import axios, { type InternalAxiosRequestConfig, type AxiosResponse, type AxiosError } from 'axios';
import type { ApiResponse } from '../types';
import { RETRY } from '../constants';
import { useGlobalLoading } from '../composables/useGlobalLoading';

// 1. Dynamic Base URL (Auto-detects IP)
export const getBaseUrl = () => {
  const hostname = window.location.hostname;
  const protocol = window.location.protocol;
  // IIS VDIR "OJT2/" maps to backend/public/
  return `${protocol}//${hostname}/OJT2/`;
};

export const getAssetUrl = () => {
  const hostname = window.location.hostname;
  const protocol = window.location.protocol;
  return `${protocol}//${hostname}/OJT2`;
};

const api = axios.create({
  baseURL: getBaseUrl(),
  withCredentials: true,
  xsrfCookieName: 'csrf_cookie_name',
  xsrfHeaderName: 'X-CSRF-TOKEN',
  headers: {
    'Accept': 'application/json',
  },
});

// ============================================================================
// GLOBAL LOADING STATE
// ============================================================================
const { startLoading, stopLoading } = useGlobalLoading();

// ============================================================================
// REQUEST INTERCEPTOR - CSRF Token + Loading State
// ============================================================================
api.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  startLoading();

  // Axios natively drops xsrfCookieName on Cross-Origin (Port 5173 -> 80).
  // We explicitly extract the newly injected insecure cookie provided
  // by our CodeIgniter Cors.php filter.
  const match = document.cookie.match(new RegExp('(^| )XSRF-TOKEN=([^;]+)'));
  if (match?.[2] && config.headers) {
    config.headers['X-CSRF-TOKEN'] = decodeURIComponent(match[2]);
  }

  return config;
}, (error) => {
  stopLoading();
  return Promise.reject(error);
});

// ============================================================================
// RESPONSE INTERCEPTOR - Error Handling + Retry Logic + Loading State
// ============================================================================
api.interceptors.response.use(
  (response: AxiosResponse) => {
    stopLoading();
    return response;
  },
  async (error: AxiosError<ApiResponse>) => {
    stopLoading();

    const config = error.config as InternalAxiosRequestConfig & { __retryCount?: number };

    // --- Retry Logic ---
    // Only retry on network errors or specific server errors (502/503/504)
    const isRetryable =
      !error.response                                          // Network error
      || RETRY.RETRYABLE_STATUSES.includes(error.response.status); // Gateway errors

    // Only auto-retry GET requests (idempotent); POST may cause duplicates
    const isGet = config?.method?.toUpperCase() === 'GET';

    if (config && isRetryable && isGet) {
      config.__retryCount = config.__retryCount || 0;

      if (config.__retryCount < RETRY.MAX_RETRIES) {
        config.__retryCount++;
        const delay = RETRY.BASE_DELAY_MS * Math.pow(2, config.__retryCount - 1);

        if (import.meta.env.DEV) {
          console.warn(`[Retry] Attempt ${config.__retryCount}/${RETRY.MAX_RETRIES} for ${config.url} in ${delay}ms`);
        }

        await new Promise(resolve => setTimeout(resolve, delay));
        startLoading(); // re-entering request flow
        return api(config);
      }
    }

    // --- Error Logging (dev only) ---
    if (import.meta.env.DEV) {
      if (error.response) {
        const status = error.response.status;
        if (status === 401) {
          console.warn('Unauthorized request - user may need to log in');
        } else if (status === 403) {
          console.warn('Forbidden - CSRF token may be invalid or permissions insufficient');
        } else if (status === 429) {
          console.warn('Too many requests - rate limit exceeded');
        } else if (status >= 500) {
          console.error('Server error:', error.response.data?.message || 'Internal server error');
        }
      } else if (error.request) {
        console.error('Network error - no response from server');
      } else {
        console.error('Request error:', error.message);
      }
    }

    return Promise.reject(error);
  }
);

export default api;
