// src/services/index.ts

/**
 * Barrel export for all API services
 * Provides convenient single import point for service modules
 */

export * from './auth.service'
export * from './research.service'
export * from './admin.service'
export * from './notification.service'
export * from './comment.service'
export * from './dashboard.service'
export * from './assistant.service'


// Re-export common utilities
export { getAssetUrl } from './api'
