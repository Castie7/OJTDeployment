import { ref, computed, onUnmounted } from 'vue'

/**
 * Client-side rate limiter for rapid user actions (e.g. login button).
 *
 * Prevents firing unnecessary requests before the backend even sees them.
 * This is a UX enhancement, NOT a security measure — the backend still
 * enforces its own rate limits.
 *
 * @param maxAttempts  Number of attempts before lockout (default 5)
 * @param windowMs     Lockout window in milliseconds (default 60 000 = 1 min)
 */
export function useRateLimiter(maxAttempts = 5, windowMs = 60_000) {
    const attempts = ref(0)
    const lockedUntil = ref<number | null>(null)
    let resetTimer: ReturnType<typeof setTimeout> | null = null

    const isLocked = computed(() => {
        if (!lockedUntil.value) return false
        return Date.now() < lockedUntil.value
    })

    const remainingAttempts = computed(() => Math.max(0, maxAttempts - attempts.value))

    /** Returns `true` if the action is allowed to proceed */
    const canProceed = (): boolean => {
        if (isLocked.value) return false
        return attempts.value < maxAttempts
    }

    /** Record a failed attempt. Locks out if limit is reached. */
    const recordAttempt = (): void => {
        attempts.value++

        if (attempts.value >= maxAttempts) {
            lockedUntil.value = Date.now() + windowMs

            if (resetTimer) clearTimeout(resetTimer)
            resetTimer = setTimeout(() => {
                attempts.value = 0
                lockedUntil.value = null
            }, windowMs)
        }
    }

    /** Call on a successful action to reset the counter */
    const reset = (): void => {
        attempts.value = 0
        lockedUntil.value = null
        if (resetTimer) {
            clearTimeout(resetTimer)
            resetTimer = null
        }
    }

    onUnmounted(() => {
        if (resetTimer) clearTimeout(resetTimer)
    })

    return { canProceed, recordAttempt, reset, isLocked, remainingAttempts }
}
