// src/constants.ts
// Canonical constants — import from here instead of using magic numbers.

// ============================================================================
// AUTHENTICATION
// ============================================================================
export const AUTH = {
    MAX_LOGIN_ATTEMPTS: 5,
    LOCKOUT_DURATION_MS: 60_000,         // 1 minute
    SESSION_TIMEOUT_MS: 30 * 60 * 1000,  // 30 minutes
} as const

// ============================================================================
// PAGINATION
// ============================================================================
export const PAGINATION = {
    DEFAULT_PAGE_SIZE: 10,
    MAX_PAGE_SIZE: 100,
} as const

// ============================================================================
// CACHE TTL (milliseconds)
// ============================================================================
export const CACHE_TTL = {
    SHORT: 1 * 60 * 1000,   // 1 minute  (comments, notifications)
    MEDIUM: 5 * 60 * 1000,  // 5 minutes (research lists)
    LONG: 30 * 60 * 1000,   // 30 minutes (static data)
} as const

// ============================================================================
// RETRY CONFIG
// ============================================================================
export const RETRY = {
    MAX_RETRIES: 3,
    BASE_DELAY_MS: 1000,   // 1s → 2s → 4s (exponential backoff)
    RETRYABLE_STATUSES: [502, 503, 504] as readonly number[],
} as const

// ============================================================================
// RESEARCH STATUS
// ============================================================================
export const RESEARCH_STATUS = {
    PENDING: 'pending',
    APPROVED: 'approved',
    REJECTED: 'rejected',
    ARCHIVED: 'archived',
} as const

// ============================================================================
// USER ROLES
// ============================================================================
export const USER_ROLES = {
    ADMIN: 'admin',
    USER: 'user',
} as const

// ============================================================================
// FILE UPLOAD
// ============================================================================
export const UPLOAD = {
    ALLOWED_EXTENSIONS: ['pdf', 'jpg', 'jpeg', 'png'] as readonly string[],
} as const

// ============================================================================
// ARCHIVE
// ============================================================================
export const ARCHIVE = {
    AUTO_DELETE_DAYS: 60,
    REJECTED_RETENTION_DAYS: 30,
} as const
